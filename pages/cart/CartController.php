<?php
/**
 * mobileshop/pages/cart/CartController.php
 * Контроллер для обработки запросов корзины
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/mobileshop/inc/functions.php';

class CartController {
    private $db;
    private $cartHelper;
    private $guestService;
    private $userId;
    
    public function __construct($db) {
        $this->db = $db;
        $this->cartHelper = new CartHelper($db);
        $this->guestService = new GuestCartService($db, $_SESSION);
        $this->userId = $_SESSION['id'] ?? null;
        
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
    
    public function handleRequest(): void {
        // AJAX-запросы обрабатываем отдельно
        if ($this->isAjaxRequest()) {
            $this->handleAjaxRequest();
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePostRequest();
            return;
        }
        
        if (isset($_GET['remove'])) {
            $this->handleRemove($_GET['remove']);
            return;
        }
        
        $this->renderCart();
    }
    
    /**
     * Обработка AJAX-запросов
     */
    private function handleAjaxRequest(): void {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        
        switch ($action) {
            case 'remove':
                $this->handleAjaxRemove();
                break;
            case 'update':
                $this->handleAjaxUpdate();
                break;
            case 'save_shipping':
                $this->handleAjaxSaveShipping();
                break;
            case 'add':
                $this->handleAddFromForm();
                break;
            default:
                $this->jsonResponse(['success' => false, 'message' => 'Unknown action']);
        }
    }
    
    /**
     * AJAX-сохранение выбранного способа доставки
     */
    private function handleAjaxSaveShipping(): void {
        $shipping_method = (int)($_POST['shipping_method'] ?? 0);
        $shipping_price = (float)($_POST['shipping_price'] ?? 0);
        
        if ($shipping_method > 0) {
            $_SESSION['selected_shipping_method'] = $shipping_method;
            $_SESSION['selected_shipping_price'] = $shipping_price;
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Способ доставки сохранен',
                'method' => $shipping_method,
                'price' => $shipping_price
            ]);
        } else {
            $this->jsonResponse(['success' => false, 'message' => 'Некорректный способ доставки']);
        }
    }
    
    /**
     * AJAX-удаление товара
     */
    private function handleAjaxRemove(): void {
        $cartKey = $_POST['cart_key'] ?? '';
        $cartKey = preg_replace('/[^0-9_]/', '', $cartKey);
        
        if (!preg_match('/^\d+(_\d+)?$/', $cartKey)) {
            $this->jsonResponse(['success' => false, 'message' => 'Некорректный идентификатор']);
            return;
        }
        
        list($productId, $variationId) = $this->parseCartKey($cartKey);
        
        if ($this->userId) {
            $this->cartHelper->removeItem($this->userId, $productId, $variationId);
            $count = $this->cartHelper->getCartCount($this->userId);
        } else {
            $this->guestService->removeItem($cartKey);
            $count = $this->guestService->getCartCount();
        }
        
        $this->jsonResponse([
            'success' => true, 
            'message' => 'Товар удален', 
            'count' => $count
        ]);
    }
    
    /**
     * AJAX-обновление количества
     */
    private function handleAjaxUpdate(): void {
        $updates = $_POST['updates'] ?? [];
        $hasWarning = false;
        
        foreach ($updates as $cartKey => $newQty) {
            $cartKey = preg_replace('/[^0-9_]/', '', $cartKey);
            $newQty = (int)$newQty;
            
            if (!preg_match('/^\d+(_\d+)?$/', $cartKey)) continue;
            
            list($productId, $variationId) = $this->parseCartKey($cartKey);
            
            if ($this->userId) {
                $this->cartHelper->updateQuantity($this->userId, $productId, $variationId, $newQty);
            } else {
                $result = $this->guestService->updateQuantity($cartKey, $newQty);
                if (strpos($result['message'], 'ограничено') !== false) {
                    $hasWarning = true;
                }
            }
        }
        
        $count = $this->userId 
            ? $this->cartHelper->getCartCount($this->userId) 
            : $this->guestService->getCartCount();
        
        $this->jsonResponse([
            'success' => true,
            'message' => $hasWarning ? 'Количество ограничено наличием' : 'Обновлено',
            'count' => $count
        ]);
    }
    
    /**
     * JSON-ответ для AJAX
     */
    private function jsonResponse(array $data): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    private function verifyCsrf(): bool {
        $token = $_POST['csrf_token'] ?? '';
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
    
    private function handlePostRequest(): void {
        if (!$this->isAjaxRequest() && !$this->verifyCsrf()) {
            $_SESSION['error'] = "Недействительный CSRF-токен";
            header("Location: cart.php");
            exit;
        }
        
        // Сохраняем выбранный способ доставки в сессию
        if (isset($_POST['shipping_method'])) {
            $_SESSION['selected_shipping_method'] = (int)$_POST['shipping_method'];
            $_SESSION['selected_shipping_price'] = (float)($_POST['shipping_price'] ?? 0);
        }
        
        if (isset($_POST['product_id'], $_POST['quantity'])) {
            $this->handleAddFromForm();
            return;
        }
        
        if (isset($_POST['update_cart']) && isset($_POST['quantity'])) {
            $this->handleUpdateQuantities();
            return;
        }
        
        header("Location: cart.php");
        exit;
    }
    
    private function isAjaxRequest(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
    
    private function handleAddFromForm(): void {
        $productId = (int)$_POST['product_id'];
        $quantity = (int)$_POST['quantity'];
        $variationId = !empty($_POST['variation_id']) ? (int)$_POST['variation_id'] : null;
        
        if ($this->userId) {
            $result = $this->cartHelper->addItem($this->userId, $productId, $quantity, $variationId);
        } else {
            $result = $this->guestService->addItem($productId, $quantity, $variationId);
        }
        
        $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
        header("Location: cart.php");
        exit;
    }
    
    private function handleUpdateQuantities(): void {
        $updates = $_POST['quantity'] ?? [];
        $hasWarning = false;
        
        foreach ($updates as $cartKey => $newQty) {
            $cartKey = preg_replace('/[^0-9_]/', '', $cartKey);
            $newQty = (int)$newQty;
            
            if (!preg_match('/^\d+(_\d+)?$/', $cartKey)) continue;
            
            list($productId, $variationId) = $this->parseCartKey($cartKey);
            
            if ($this->userId) {
                $this->cartHelper->updateQuantity($this->userId, $productId, $variationId, $newQty);
            } else {
                $result = $this->guestService->updateQuantity($cartKey, $newQty);
                if (strpos($result['message'], 'ограничено') !== false) {
                    $hasWarning = true;
                }
            }
        }
        
        if ($hasWarning) {
            $_SESSION['warning'] = "Количество некоторых товаров ограничено наличием на складе";
        }
        
        header("Location: cart.php");
        exit;
    }
    
    private function handleRemove(string $cartKey): void {
        $cartKey = preg_replace('/[^0-9_]/', '', $cartKey);
        
        if (!preg_match('/^\d+(_\d+)?$/', $cartKey)) {
            $_SESSION['error'] = "Некорректный идентификатор";
            header("Location: cart.php");
            exit;
        }
        
        list($productId, $variationId) = $this->parseCartKey($cartKey);
        
        if ($this->userId) {
            $this->cartHelper->removeItem($this->userId, $productId, $variationId);
        } else {
            $this->guestService->removeItem($cartKey);
        }
        
        header("Location: cart.php");
        exit;
    }
    
    private function renderCart(): void {
        if ($this->userId) {
            $cartData = $this->cartHelper->getCartItems($this->userId);
            $_SESSION['cart_count'] = $cartData['count'];
        } else {
            $cartData = $this->guestService->getCartItems();
            $_SESSION['cart_count'] = $cartData['count'];
        }
        
        $shippingMethods = $this->getShippingMethods();
        
        // Передаем сохраненный способ доставки в шаблон
        $preselectedShipping = $_SESSION['selected_shipping_method'] ?? null;
        $preselectedShippingPrice = $_SESSION['selected_shipping_price'] ?? null;
        
        require_once __DIR__ . '/cart_view.php';
    }
    
    private function parseCartKey(string $cartKey): array {
        if (strpos($cartKey, '_') !== false) {
            list($pid, $vid) = explode('_', $cartKey);
            return [(int)$pid, (int)$vid];
        }
        return [(int)$cartKey, null];
    }
    
    /**
     * ИСПРАВЛЕНО: Унифицированные способы доставки (те же что и в checkout.php)
     */
    public function getShippingMethods(): array {
        return [
            [
                'id' => 1,
                'name' => 'Самовывоз из магазина',
                'code' => 'pickup',
                'price' => 0.00,
                'free_from' => null,
                'estimated_days_min' => 1,
                'estimated_days_max' => 1,
                'description' => 'Заберите заказ в нашем магазине'
            ],
            [
                'id' => 2,
                'name' => 'Курьер по Минску',
                'code' => 'courier_minsk',
                'price' => 10.00,
                'free_from' => 500.00,
                'estimated_days_min' => 1,
                'estimated_days_max' => 2,
                'description' => 'Доставка курьером по Минску'
            ],
            [
                'id' => 3,
                'name' => 'Белпочта',
                'code' => 'belpost',
                'price' => 15.00,
                'free_from' => null,
                'estimated_days_min' => 3,
                'estimated_days_max' => 7,
                'description' => 'Доставка в отделение Белпочты'
            ],
            [
                'id' => 4,
                'name' => 'Европочта',
                'code' => 'europost',
                'price' => 12.00,
                'free_from' => 300.00,
                'estimated_days_min' => 2,
                'estimated_days_max' => 4,
                'description' => 'Доставка в отделение Европочты'
            ],
            [
                'id' => 5,
                'name' => 'DHL Express',
                'code' => 'dhl',
                'price' => 50.00,
                'free_from' => null,
                'estimated_days_min' => 1,
                'estimated_days_max' => 3,
                'description' => 'Экспресс-доставка по всему миру'
            ]
        ];
    }
    
    public function mergeGuestCartOnLogin(int $userId): array {
        if (empty($_SESSION['cart'])) {
            return ['merged' => 0, 'added' => 0];
        }
        
        return $this->guestService->mergeToUserCart($userId, $this->cartHelper);
    }
}