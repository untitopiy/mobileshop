<?php
/**
 * mobileshop/pages/cart/CartHelper.php
 * Класс для работы с корзиной авторизованных пользователей
 */
class CartHelper {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Получить или создать корзину пользователя
     */
    public function getOrCreateCart($user_id) {
        $cart_query = $this->db->prepare("SELECT id FROM cart WHERE user_id = ?");
        $cart_query->bind_param('i', $user_id);
        $cart_query->execute();
        $cart = $cart_query->get_result()->fetch_assoc();
        
        if (!$cart) {
            $create_cart = $this->db->prepare("INSERT INTO cart (user_id, created_at) VALUES (?, NOW())");
            $create_cart->bind_param('i', $user_id);
            $create_cart->execute();
            return $create_cart->insert_id;
        }
        
        return $cart['id'];
    }
    
    /**
     * Получить информацию о наличии (ИСПРАВЛЕНО)
     */
    private function getStockInfo($product_id, $variation_id = null) {
        if ($variation_id) {
            $query = $this->db->prepare("SELECT quantity, price FROM product_variations WHERE id = ? AND product_id = ?");
            $query->bind_param('ii', $variation_id, $product_id);
        } else {
            // Исправлено: корректный SUM(quantity) и MIN(price)
            $query = $this->db->prepare("
                SELECT COALESCE(SUM(quantity), 0) as quantity, 
                       COALESCE(MIN(price), 0) as price 
                FROM product_variations 
                WHERE product_id = ?
            ");
            $query->bind_param('i', $product_id);
        }
        
        $query->execute();
        return $query->get_result()->fetch_assoc();
    }
    
    /**
     * Найти существующий товар в корзине
     */
    public function getExistingItem($cart_id, $product_id, $variation_id) {
        $query = $this->db->prepare("
            SELECT id, quantity FROM cart_items 
            WHERE cart_id = ? AND product_id = ? 
            AND (variation_id = ? OR (variation_id IS NULL AND ? IS NULL))
        ");
        $query->bind_param('iiii', $cart_id, $product_id, $variation_id, $variation_id);
        $query->execute();
        return $query->get_result()->fetch_assoc();
    }
    
    /**
     * Обновить количество товара в корзине
     */
    public function updateItemQuantity($item_id, $quantity) {
        $update = $this->db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
        $update->bind_param('ii', $quantity, $item_id);
        return $update->execute();
    }
    
    /**
     * Добавить новый товар в корзину
     */
    public function insertItem($cart_id, $product_id, $variation_id, $quantity, $price) {
        $insert = $this->db->prepare("
            INSERT INTO cart_items (cart_id, product_id, variation_id, quantity, price, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $insert->bind_param('iiidi', $cart_id, $product_id, $variation_id, $quantity, $price);
        return $insert->execute();
    }
    
    /**
     * Добавить товар в корзину (с транзакцией)
     */
    public function addItem($user_id, $product_id, $quantity, $variation_id = null) {
        // Проверяем наличие на складе
        $stock_info = $this->getStockInfo($product_id, $variation_id);
        
        if (!$stock_info || $stock_info['quantity'] <= 0 || $quantity > $stock_info['quantity']) {
            return ['success' => false, 'message' => 'Недостаточно товара на складе'];
        }
        
        $cart_id = $this->getOrCreateCart($user_id);
        $price = $stock_info['price'];
        
        // Проверяем существующий товар
        $existing = $this->getExistingItem($cart_id, $product_id, $variation_id);
        
        $this->db->begin_transaction();
        
        try {
            if ($existing) {
                $new_quantity = $existing['quantity'] + $quantity;
                if ($new_quantity > $stock_info['quantity']) {
                    throw new Exception('Превышено доступное количество');
                }
                $this->updateItemQuantity($existing['id'], $new_quantity);
            } else {
                $this->insertItem($cart_id, $product_id, $variation_id, $quantity, $price);
            }
            
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
        
        return ['success' => true, 'message' => 'Товар добавлен в корзину'];
    }
    
    /**
     * Получить количество товаров в корзине
     */
    public function getCartCount($user_id) {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(ci.quantity), 0) as count 
            FROM cart c 
            JOIN cart_items ci ON ci.cart_id = c.id 
            WHERE c.user_id = ?
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        
        return $count;
    }
    
    /**
     * Получить полные данные корзины
     */
    public function getCartItems($user_id) {
    $query = $this->db->prepare("
        SELECT 
            ci.id as cart_item_id,
            ci.product_id,
            ci.variation_id,
            ci.quantity,
            ci.price as cart_price,
            p.id,
            p.name,
            p.brand,
            p.model,
            p.type,
            p.category_id,
            COALESCE(pv.price, ci.price) as current_price,
            pv.old_price,
            COALESCE(pv.quantity, 
                (SELECT COALESCE(SUM(quantity), 0) FROM product_variations WHERE product_id = p.id)
            ) as stock,
            pv.sku,
            (SELECT image_url FROM product_images 
             WHERE product_id = p.id 
             AND (variation_id = ci.variation_id OR (variation_id IS NULL AND ci.variation_id IS NULL))
             ORDER BY is_primary DESC, sort_order ASC 
             LIMIT 1) as image_url,
            s.shop_name as seller_name,
            s.id as seller_id,
            prom.discount_percent,
            prom.discount_type,
            prom.discount_value,
            prom.name as promotion_name
        FROM cart c
        JOIN cart_items ci ON ci.cart_id = c.id
        JOIN products p ON p.id = ci.product_id
        LEFT JOIN product_variations pv ON pv.id = ci.variation_id
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
        WHERE c.user_id = ?
        ORDER BY ci.created_at DESC
    ");
    
    $query->bind_param('i', $user_id);
    $query->execute();
    $result = $query->get_result();
    
    $items = [];
    $total_price = 0;
    $smartphones = [];
    
    while ($row = $result->fetch_assoc()) {
        $item_price = (float)$row['current_price'];
        $original_price = $row['old_price'] ? (float)$row['old_price'] : $item_price;
        
        // Расчет скидки
        if ($row['discount_percent']) {
            $final_price = $item_price * (100 - $row['discount_percent']) / 100;
        } elseif ($row['discount_type'] === 'fixed' && $row['discount_value'] > 0) {
            $final_price = max(0, $item_price - $row['discount_value']);
        } else {
            $final_price = $item_price;
        }
        
        // Сохраняем все данные в массив
        $items[] = [
            'cart_key' => $row['variation_id'] 
                ? "{$row['product_id']}_{$row['variation_id']}" 
                : (string)$row['product_id'],
            'product_id' => (int)$row['product_id'],
            'variation_id' => $row['variation_id'] ? (int)$row['variation_id'] : null,
            'quantity' => (int)$row['quantity'],
            'stock' => (int)$row['stock'],
            'name' => $row['name'] ?? '',
            'brand' => $row['brand'] ?? '',
            'model' => $row['model'] ?? null,
            'sku' => $row['sku'] ?? null,
            'current_price' => $final_price,
            'original_price' => $original_price,
            'subtotal' => $final_price * $row['quantity'],
            'image_url' => $row['image_url'] ?? null,
            'seller_name' => $row['seller_name'] ?? null,
            'seller_id' => $row['seller_id'] ? (int)$row['seller_id'] : null,
            'discount_percent' => isset($row['discount_percent']) ? (float)$row['discount_percent'] : null,
            'promotion_name' => $row['promotion_name'] ?? null,
            'type' => $row['type'] ?? 'simple',
            'category_id' => (int)($row['category_id'] ?? 0)
        ];
        
        $total_price += $final_price * $row['quantity'];
        
        if (($row['type'] ?? 'simple') === 'simple' && ($row['category_id'] ?? 0) == 1) {
            $smartphones[] = $row['product_id'];
        }
    }
    
    return [
        'items' => $items,
        'total_price' => $total_price,
        'smartphones' => array_unique($smartphones),
        'count' => count($items)
    ];
}
    
    /**
     * Удалить товар из корзины
     */
    public function removeItem($user_id, $product_id, $variation_id = null) {
        if ($variation_id) {
            $query = $this->db->prepare("
                DELETE ci FROM cart_items ci
                JOIN cart c ON c.id = ci.cart_id
                WHERE c.user_id = ? AND ci.product_id = ? AND ci.variation_id = ?
            ");
            $query->bind_param('iii', $user_id, $product_id, $variation_id);
        } else {
            $query = $this->db->prepare("
                DELETE ci FROM cart_items ci
                JOIN cart c ON c.id = ci.cart_id
                WHERE c.user_id = ? AND ci.product_id = ? AND ci.variation_id IS NULL
            ");
            $query->bind_param('ii', $user_id, $product_id);
        }
        
        return $query->execute();
    }
    
    /**
     * Обновить количество с проверкой stock
     */
    public function updateQuantity($user_id, $product_id, $variation_id, $new_quantity) {
        // Проверяем stock
        $stock_info = $this->getStockInfo($product_id, $variation_id);
        $stock = $stock_info['quantity'] ?? 0;
        
        if ($new_quantity <= 0 || $new_quantity > $stock) {
            return $this->removeItem($user_id, $product_id, $variation_id);
        }
        
        // Обновляем количество
        if ($variation_id) {
            $update = $this->db->prepare("
                UPDATE cart_items ci
                JOIN cart c ON c.id = ci.cart_id
                SET ci.quantity = ?
                WHERE c.user_id = ? AND ci.product_id = ? AND ci.variation_id = ?
            ");
            $update->bind_param('iiii', $new_quantity, $user_id, $product_id, $variation_id);
        } else {
            $update = $this->db->prepare("
                UPDATE cart_items ci
                JOIN cart c ON c.id = ci.cart_id
                SET ci.quantity = ?
                WHERE c.user_id = ? AND ci.product_id = ? AND ci.variation_id IS NULL
            ");
            $update->bind_param('iii', $new_quantity, $user_id, $product_id);
        }
        
        return $update->execute();
    }
    
    /**
     * Слияние корзины гостя с БД
     */
    public function mergeGuestCart($user_id, $session_cart) {
        if (empty($session_cart)) {
            return ['merged' => 0, 'added' => 0];
        }
        
        $cart_id = $this->getOrCreateCart($user_id);
        $merged = 0;
        $added = 0;
        
        $this->db->begin_transaction();
        
        try {
            foreach ($session_cart as $cart_key => $quantity) {
                $cart_key = preg_replace('/[^0-9_]/', '', $cart_key);
                if (!preg_match('/^\d+(_\d+)?$/', $cart_key)) continue;
                
                if (strpos($cart_key, '_') !== false) {
                    list($product_id, $variation_id) = explode('_', $cart_key);
                    $product_id = (int)$product_id;
                    $variation_id = (int)$variation_id;
                } else {
                    $product_id = (int)$cart_key;
                    $variation_id = null;
                }
                
                $stock_info = $this->getStockInfo($product_id, $variation_id);
                if (!$stock_info || $stock_info['quantity'] <= 0) continue;
                
                $quantity = min((int)$quantity, $stock_info['quantity']);
                if ($quantity <= 0) continue;
                
                $existing = $this->getExistingItem($cart_id, $product_id, $variation_id);
                
                if ($existing) {
                    $new_quantity = $existing['quantity'] + $quantity;
                    $new_quantity = min($new_quantity, $stock_info['quantity']);
                    $this->updateItemQuantity($existing['id'], $new_quantity);
                    $merged++;
                } else {
                    $this->insertItem($cart_id, $product_id, $variation_id, $quantity, $stock_info['price']);
                    $added++;
                }
            }
            
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
        
        return ['merged' => $merged, 'added' => $added];
    }
}