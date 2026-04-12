<?php
/**
 * mobileshop/pages/cart/GuestCartService.php
 * Сервис для работы с гостевой корзиной (сессия)
 */

require_once __DIR__ . '/CartItemDTO.php';

class GuestCartService {
    private $db;
    private $session;
    
    public function __construct($db, &$session) {
        $this->db = $db;
        $this->session = &$session;
        
        if (!isset($this->session['cart'])) {
            $this->session['cart'] = [];
        }
    }
    
    public function addItem(int $productId, int $quantity, ?int $variationId = null): array {
        if ($productId <= 0 || $quantity <= 0) {
            return ['success' => false, 'message' => 'Некорректные параметры'];
        }
        
        $stockInfo = $this->getStockInfo($productId, $variationId);
        
        if (!$stockInfo || $stockInfo['quantity'] <= 0) {
            return ['success' => false, 'message' => 'Товар недоступен или закончился'];
        }
        
        $cartKey = $this->buildCartKey($productId, $variationId);
        $currentQty = $this->session['cart'][$cartKey] ?? 0;
        $newQty = $currentQty + $quantity;
        
        if ($newQty > $stockInfo['quantity']) {
            $newQty = $stockInfo['quantity'];
            $message = "Добавлено максимальное количество: {$newQty} шт.";
        } else {
            $message = "Товар добавлен (всего: {$newQty} шт.)";
        }
        
        $this->session['cart'][$cartKey] = $newQty;
        
        return [
            'success' => true,
            'message' => $message,
            'quantity' => $newQty,
            'cart_key' => $cartKey
        ];
    }
    
    public function updateQuantity(string $cartKey, int $newQuantity): array {
        $cartKey = $this->sanitizeCartKey($cartKey);
        
        if (!preg_match('/^\d+(_\d+)?$/', $cartKey)) {
            return ['success' => false, 'message' => 'Некорректный ключ'];
        }
        
        list($productId, $variationId) = $this->parseCartKey($cartKey);
        
        $stockInfo = $this->getStockInfo($productId, $variationId);
        $availableStock = (int)($stockInfo['quantity'] ?? 0);
        
        if ($newQuantity <= 0) {
            unset($this->session['cart'][$cartKey]);
            return ['success' => true, 'message' => 'Товар удалён', 'removed' => true];
        }
        
        if ($newQuantity > $availableStock) {
            $newQuantity = $availableStock;
            $message = "Количество ограничено наличием ({$availableStock} шт.)";
        } else {
            $message = 'Количество обновлено';
        }
        
        $this->session['cart'][$cartKey] = $newQuantity;
        
        return [
            'success' => true,
            'message' => $message,
            'quantity' => $newQuantity,
            'cart_key' => $cartKey
        ];
    }
    
    public function removeItem(string $cartKey): bool {
        $cartKey = $this->sanitizeCartKey($cartKey);
        unset($this->session['cart'][$cartKey]);
        return true;
    }
    
    public function getCartItems(): array {
        $items = [];
        $totalPrice = 0;
        $smartphones = [];
        $keysToRemove = [];
        
        foreach ($this->session['cart'] as $cartKey => $quantity) {
            $cartKey = $this->sanitizeCartKey($cartKey);
            
            if (!preg_match('/^\d+(_\d+)?$/', $cartKey)) {
                $keysToRemove[] = $cartKey;
                continue;
            }
            
            list($productId, $variationId) = $this->parseCartKey($cartKey);
            $itemData = $this->getItemDetails($productId, $variationId);
            
            if (!$itemData) {
                $keysToRemove[] = $cartKey;
                continue;
            }
            
            $availableStock = (int)($itemData['stock'] ?? 0);
            $actualQty = min($quantity, $availableStock);
            
            if ($actualQty <= 0) {
                $keysToRemove[] = $cartKey;
                continue;
            }
            
            if ($actualQty < $quantity) {
                $this->session['cart'][$cartKey] = $actualQty;
            }
            
            $dto = CartItemDTO::fromGuestData($itemData, $cartKey, $actualQty);
            // ВАЖНО: Преобразуем DTO в массив
            $items[] = $dto->toArray();
            $totalPrice += $dto->subtotal;
            
            if ($dto->type === 'simple' && $dto->categoryId == 1) {
                $smartphones[] = $dto->productId;
            }
        }
        
        foreach ($keysToRemove as $key) {
            unset($this->session['cart'][$key]);
        }
        
        $this->session['cart_count'] = count($items);
        
        return [
            'items' => $items,
            'total_price' => $totalPrice,
            'count' => count($items),
            'smartphones' => array_unique($smartphones)
        ];
    }
    
    public function getCartCount(): int {
        return array_sum($this->session['cart'] ?? []);
    }
    
    public function getRawCart(): array {
        return $this->session['cart'] ?? [];
    }
    
    public function clear(): void {
        $this->session['cart'] = [];
        $this->session['cart_count'] = 0;
    }
    
    public function mergeToUserCart(int $userId, CartHelper $cartHelper): array {
        $rawCart = $this->getRawCart();
        
        if (empty($rawCart)) {
            return ['merged' => 0, 'added' => 0];
        }
        
        return $cartHelper->mergeGuestCart($userId, $rawCart);
    }
    
    private function getStockInfo(int $productId, ?int $variationId = null): ?array {
        if ($variationId) {
            $stmt = $this->db->prepare("SELECT quantity, price FROM product_variations WHERE id = ? AND product_id = ?");
            $stmt->bind_param('ii', $variationId, $productId);
        } else {
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(quantity), 0) as quantity, 
                       COALESCE(MIN(price), 0) as price 
                FROM product_variations 
                WHERE product_id = ?
            ");
            $stmt->bind_param('i', $productId);
        }
        
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $result;
    }
    
    private function getItemDetails(int $productId, ?int $variationId = null): ?array {
        if ($variationId) {
            return $this->getItemDetailsWithVariation($productId, $variationId);
        } else {
            return $this->getItemDetailsWithoutVariation($productId);
        }
    }
    
    private function getItemDetailsWithVariation(int $productId, int $variationId): ?array {
        $sql = "
            SELECT 
                p.id as product_id,
                p.name,
                p.brand,
                p.model,
                p.type,
                p.category_id,
                COALESCE(pv.price, p.price) as current_price,
                pv.old_price as original_price,
                COALESCE(pv.quantity, 0) as stock,
                pv.sku,
                (SELECT image_url 
                 FROM product_images 
                 WHERE product_id = p.id 
                 AND variation_id = pv.id
                 ORDER BY is_primary DESC, sort_order ASC 
                 LIMIT 1) as image_url,
                s.shop_name as seller_name,
                s.id as seller_id,
                prom.discount_percent,
                prom.discount_type,
                prom.discount_value,
                prom.name as promotion_name
            FROM products p
            INNER JOIN product_variations pv ON pv.product_id = p.id AND pv.id = ?
            LEFT JOIN sellers s ON s.id = p.seller_id
            LEFT JOIN (
                SELECT 
                    pp.product_id,
                    prom.discount_type,
                    prom.discount_value,
                    prom.name,
                    CASE 
                        WHEN prom.discount_type = 'percent' THEN prom.discount_value
                        ELSE NULL
                    END as discount_percent
                FROM promotion_products pp
                JOIN promotions prom ON prom.id = pp.promotion_id 
                WHERE prom.is_active = 1 
                  AND prom.starts_at <= NOW() 
                  AND (prom.expires_at >= NOW() OR prom.expires_at IS NULL)
            ) prom ON prom.product_id = p.id
            WHERE p.id = ? AND p.status = 'active'
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('ii', $variationId, $productId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $result ? $this->processItemData($result) : null;
    }
    
    private function getItemDetailsWithoutVariation(int $productId): ?array {
        $sql = "
            SELECT 
                p.id as product_id,
                p.name,
                p.brand,
                p.model,
                p.type,
                p.category_id,
                COALESCE(
                    (SELECT MIN(pv.price) FROM product_variations pv WHERE pv.product_id = p.id),
                    p.price
                ) as current_price,
                NULL as original_price,
                COALESCE(
                    (SELECT SUM(pv.quantity) FROM product_variations pv WHERE pv.product_id = p.id),
                    0
                ) as stock,
                NULL as sku,
                (SELECT image_url 
                 FROM product_images 
                 WHERE product_id = p.id 
                 AND variation_id IS NULL
                 ORDER BY is_primary DESC, sort_order ASC 
                 LIMIT 1) as image_url,
                s.shop_name as seller_name,
                s.id as seller_id,
                prom.discount_percent,
                prom.discount_type,
                prom.discount_value,
                prom.name as promotion_name
            FROM products p
            LEFT JOIN sellers s ON s.id = p.seller_id
            LEFT JOIN (
                SELECT 
                    pp.product_id,
                    prom.discount_type,
                    prom.discount_value,
                    prom.name,
                    CASE 
                        WHEN prom.discount_type = 'percent' THEN prom.discount_value
                        ELSE NULL
                    END as discount_percent
                FROM promotion_products pp
                JOIN promotions prom ON prom.id = pp.promotion_id 
                WHERE prom.is_active = 1 
                  AND prom.starts_at <= NOW() 
                  AND (prom.expires_at >= NOW() OR prom.expires_at IS NULL)
            ) prom ON prom.product_id = p.id
            WHERE p.id = ? AND p.status = 'active'
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $result ? $this->processItemData($result) : null;
    }
    
    private function processItemData(array $result): array {
        $itemPrice = (float)$result['current_price'];
        $originalPrice = isset($result['original_price']) ? (float)$result['original_price'] : $itemPrice;
        
        if ($result['discount_percent']) {
            $finalPrice = $itemPrice * (100 - $result['discount_percent']) / 100;
        } elseif ($result['discount_type'] === 'fixed' && $result['discount_value'] > 0) {
            $finalPrice = max(0, $itemPrice - $result['discount_value']);
        } else {
            $finalPrice = $itemPrice;
        }
        
        $result['current_price'] = $finalPrice;
        $result['original_price'] = $originalPrice;
        
        return $result;
    }
    
    private function buildCartKey(int $productId, ?int $variationId): string {
        return $variationId ? "{$productId}_{$variationId}" : (string)$productId;
    }
    
    private function parseCartKey(string $cartKey): array {
        if (strpos($cartKey, '_') !== false) {
            list($pid, $vid) = explode('_', $cartKey);
            return [(int)$pid, (int)$vid];
        }
        return [(int)$cartKey, null];
    }
    
    private function sanitizeCartKey(string $cartKey): string {
        return preg_replace('/[^0-9_]/', '', $cartKey);
    }
}