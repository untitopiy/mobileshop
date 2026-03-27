<?php
session_start();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/cart/CartHelper.php';

/**
 * ПУНКТ 4: Проверка stock для гостей
 * Функция для получения информации о stock товара из БД
 */
function getGuestStockInfo($db, $product_id, $variation_id = null) {
    if ($variation_id) {
        $stmt = $db->prepare("SELECT quantity, price FROM product_variations WHERE id = ? AND product_id = ?");
        $stmt->bind_param('ii', $variation_id, $product_id);
    } else {
        $stmt = $db->prepare("
            SELECT SUM(quantity) as quantity, MIN(price) as price 
            FROM product_variations 
            WHERE product_id = ?
        ");
        $stmt->bind_param('i', $product_id);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result ?: ['quantity' => 0, 'price' => 0];
}

/**
 * ПУНКТ 4: Проверка stock для гостей
 * Функция для получения полных данных товара по ID (для отображения в корзине гостя)
 */
function getGuestCartItemDetails($db, $product_id, $variation_id = null) {
    $sql = "
        SELECT 
            p.id as product_id,
            p.name,
            p.brand,
            p.model,
            p.type,
            p.category_id,
            COALESCE(pv.price, p.price) as current_price,
            pv.old_price,
            COALESCE(pv.quantity, 
                (SELECT SUM(quantity) FROM product_variations WHERE product_id = p.id)
            ) as stock,
            pv.sku,
            pv.id as variation_id,
            (SELECT image_url FROM product_images 
             WHERE product_id = p.id 
             AND (variation_id = pv.id OR (variation_id IS NULL AND pv.id IS NULL))
             ORDER BY is_primary DESC, sort_order ASC 
             LIMIT 1) as image_url,
            s.shop_name as seller_name,
            prom.discount_percent,
            prom.discount_type,
            prom.discount_value,
            prom.name as promotion_name
        FROM products p
        LEFT JOIN product_variations pv ON pv.product_id = p.id AND (? IS NULL OR pv.id = ?)
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
        WHERE p.id = ?
        LIMIT 1
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param('iii', $variation_id, $variation_id, $product_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$item) return null;
    
    // Расчет цены со скидкой
    $item_price = $item['current_price'];
    $original_price = $item['old_price'] ?: $item_price;
    
    if ($item['discount_percent']) {
        $final_price = $item_price * (100 - $item['discount_percent']) / 100;
    } elseif ($item['discount_type'] === 'fixed' && $item['discount_value'] > 0) {
        $final_price = max(0, $item_price - $item['discount_value']);
    } else {
        $final_price = $item_price;
    }
    
    $item['current_price'] = $final_price;
    $item['original_price'] = $original_price;
    
    return $item;
}

// Обработка POST-формы добавления (редирект на API)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'], $_POST['quantity'])) {
    $user_id = $_SESSION['id'] ?? null;
    
    if ($user_id) {
        // Авторизованный пользователь — используем CartHelper
        $cart = new CartHelper($db);
        $variation_id = isset($_POST['variation_id']) ? (int)$_POST['variation_id'] : null;
        $result = $cart->addItem($user_id, (int)$_POST['product_id'], (int)$_POST['quantity'], $variation_id);
        
        $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];
    } else {
        // 🔥 ПУНКТ 4: ГОСТЬ — проверяем stock перед добавлением
        $product_id = (int)$_POST['product_id'];
        $requested_qty = (int)$_POST['quantity'];
        $variation_id = isset($_POST['variation_id']) ? (int)$_POST['variation_id'] : null;
        
        $cart_key = $variation_id 
            ? "{$product_id}_{$variation_id}" 
            : (string)$product_id;
        
        // Получаем текущее количество в корзине (если уже есть)
        $current_qty = $_SESSION['cart'][$cart_key] ?? 0;
        $total_requested = $current_qty + $requested_qty;
        
        // Проверяем stock
        $stock_info = getGuestStockInfo($db, $product_id, $variation_id);
        $available_stock = (int)($stock_info['quantity'] ?? 0);
        
        if ($available_stock <= 0) {
            $_SESSION['error'] = "Товар временно недоступен (нет на складе)";
        } elseif ($total_requested > $available_stock) {
            // Ограничиваем максимально доступным
            $allowed_qty = $available_stock - $current_qty;
            if ($allowed_qty > 0) {
                if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
                $_SESSION['cart'][$cart_key] = $available_stock;
                $_SESSION['warning'] = "Добавлено только {$allowed_qty} шт. (максимум на складе: {$available_stock})";
            } else {
                $_SESSION['error'] = "Нельзя добавить больше. Уже в корзине: {$current_qty}, на складе: {$available_stock}";
            }
        } else {
            // Всё в порядке — добавляем
            if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
            $_SESSION['cart'][$cart_key] = $total_requested;
            $_SESSION['success'] = "Товар добавлен в корзину ({$total_requested} шт.)";
        }
    }
    
    header("Location: cart.php");
    exit;
}

// Обработка удаления
if (isset($_GET['remove'])) {
    $user_id = $_SESSION['id'] ?? null;
    $remove_key = preg_replace('/[^0-9_]/', '', $_GET['remove']);
    
    if (!preg_match('/^\d+(_\d+)?$/', $remove_key)) {
        $_SESSION['error'] = "Некорректный идентификатор";
        header("Location: cart.php");
        exit;
    }
    
    if ($user_id) {
        $cart = new CartHelper($db);
        if (strpos($remove_key, '_') !== false) {
            list($pid, $vid) = explode('_', $remove_key);
            $cart->removeItem($user_id, (int)$pid, (int)$vid);
        } else {
            $cart->removeItem($user_id, (int)$remove_key);
        }
    } else {
        unset($_SESSION['cart'][$remove_key]);
    }
    
    header("Location: cart.php");
    exit;
}

// Обработка обновления количества
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $user_id = $_SESSION['id'] ?? null;
    
    if ($user_id) {
        $cart = new CartHelper($db);
        foreach ($_POST['quantity'] as $key => $qty) {
            $key = preg_replace('/[^0-9_]/', '', $key);
            if (!preg_match('/^\d+(_\d+)?$/', $key)) continue;
            
            if (strpos($key, '_') !== false) {
                list($pid, $vid) = explode('_', $key);
                $cart->updateQuantity($user_id, (int)$pid, (int)$vid, (int)$qty);
            } else {
                $cart->updateQuantity($user_id, (int)$key, null, (int)$qty);
            }
        }
    } else {
        // 🔥 ПУНКТ 4: ГОСТЬ — проверяем stock при обновлении
        foreach ($_POST['quantity'] as $key => $qty) {
            $key = preg_replace('/[^0-9_]/', '', $key);
            $new_qty = (int)$qty;
            
            if (!preg_match('/^\d+(_\d+)?$/', $key)) continue;
            
            // Определяем product_id и variation_id
            if (strpos($key, '_') !== false) {
                list($product_id, $variation_id) = explode('_', $key);
                $product_id = (int)$product_id;
                $variation_id = (int)$variation_id;
            } else {
                $product_id = (int)$key;
                $variation_id = null;
            }
            
            // Проверяем stock
            $stock_info = getGuestStockInfo($db, $product_id, $variation_id);
            $available_stock = (int)($stock_info['quantity'] ?? 0);
            
            if ($new_qty <= 0) {
                unset($_SESSION['cart'][$key]);
            } elseif ($new_qty > $available_stock) {
                // Ограничиваем максимальным stock
                $_SESSION['cart'][$key] = $available_stock;
                $_SESSION['warning'] = "Количество ограничено наличием на складе ({$available_stock} шт.)";
            } else {
                $_SESSION['cart'][$key] = $new_qty;
            }
        }
    }
    
    header("Location: cart.php");
    exit;
}

require_once __DIR__ . '/../inc/header.php';

$user_id = $_SESSION['id'] ?? null;
$cart_data = ['items' => [], 'total_price' => 0, 'smartphones' => [], 'count' => 0];

if ($user_id) {
    // Авторизованный пользователь — данные из БД
    $cart = new CartHelper($db);
    $cart_data = $cart->getCartItems($user_id);
    $_SESSION['cart_count'] = $cart_data['count'];
} elseif (!empty($_SESSION['cart'])) {
    // 🔥 ПУНКТ 4: ГОСТЬ — получаем полные данные товаров с проверкой stock
    $cart_data['items'] = [];
    $cart_data['total_price'] = 0;
    $cart_data['count'] = 0;
    
    foreach ($_SESSION['cart'] as $cart_key => $quantity) {
        // Парсим ключ
        if (strpos($cart_key, '_') !== false) {
            list($product_id, $variation_id) = explode('_', $cart_key);
            $product_id = (int)$product_id;
            $variation_id = (int)$variation_id;
        } else {
            $product_id = (int)$cart_key;
            $variation_id = null;
        }
        
        // Получаем данные товара
        $item = getGuestCartItemDetails($db, $product_id, $variation_id);
        
        if (!$item) {
            // Товар не найден — удаляем из корзины
            unset($_SESSION['cart'][$cart_key]);
            continue;
        }
        
        // 🔥 ПУНКТ 4: Проверяем и корректируем количество по stock
        $available_stock = (int)($item['stock'] ?? 0);
        $actual_qty = min($quantity, $available_stock);
        
        if ($actual_qty <= 0) {
            // Товар закончился — удаляем
            unset($_SESSION['cart'][$cart_key]);
            $_SESSION['warning'] = "Некоторые товары удалены из корзины (закончились на складе)";
            continue;
        }
        
        if ($actual_qty < $quantity) {
            // Уменьшаем количество в сессии
            $_SESSION['cart'][$cart_key] = $actual_qty;
            $_SESSION['warning'] = "Количество некоторых товаров скорректировано (максимум на складе)";
        }
        
        // Формируем структуру как у авторизованного пользователя
        $cart_item = [
            'cart_key' => $cart_key,
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'quantity' => $actual_qty,
            'stock' => $available_stock,
            'name' => $item['name'],
            'brand' => $item['brand'],
            'model' => $item['model'],
            'sku' => $item['sku'],
            'current_price' => $item['current_price'],
            'original_price' => $item['original_price'],
            'subtotal' => $item['current_price'] * $actual_qty,
            'image_url' => $item['image_url'],
            'seller_name' => $item['seller_name'],
            'discount_percent' => $item['discount_percent'],
            'promotion_name' => $item['promotion_name']
        ];
        
        $cart_data['items'][] = $cart_item;
        $cart_data['total_price'] += $cart_item['subtotal'];
        $cart_data['count']++;
        
        // Для рекомендаций
        if ($item['type'] === 'simple' && $item['category_id'] == 1) {
            $cart_data['smartphones'][] = $product_id;
        }
    }
    
    $_SESSION['cart_count'] = $cart_data['count'];
}

$shipping_methods = getShippingMethods($db);
?>

<div class="container cart-container py-4">
    <h1 class="mb-4">
        <i class="fas fa-shopping-cart me-2"></i>Корзина
        <?php if ($cart_data['count'] > 0): ?>
            <span class="badge bg-primary ms-2" id="cart-badge"><?= $cart_data['count'] ?> товаров</span>
        <?php endif; ?>
    </h1>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['warning'])): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($_SESSION['warning']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['warning']); ?>
    <?php endif; ?>

    <?php if (empty($cart_data['items'])): ?>
        <div class="text-center py-5">
            <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
            <h3 class="text-muted">Корзина пуста</h3>
            <p class="mb-4">Добавьте товары, чтобы оформить заказ</p>
            <a href="../index.php" class="btn btn-primary">Перейти к покупкам</a>
        </div>
    <?php else: ?>
        <form method="POST" id="cart-form">
            <div class="table-responsive">
                <table class="table cart-table">
                    <thead class="table-light">
                        <tr>
                            <th>Товар</th>
                            <th>Продавец</th>
                            <th>Цена</th>
                            <th>Количество</th>
                            <th>Сумма</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_data['items'] as $item): ?>
                            <tr data-cart-key="<?= htmlspecialchars($item['cart_key']) ?>">
                                <td class="cart-item">
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($item['image_url'])): ?>
                                            <img src="<?= htmlspecialchars($item['image_url']); ?>" 
                                                 alt="<?= htmlspecialchars($item['name']); ?>"
                                                 class="me-3" style="width: 60px; height: 60px; object-fit: contain;">
                                        <?php endif; ?>
                                        <div>
                                            <a href="../product.php?id=<?= $item['product_id'] ?>" class="text-decoration-none">
                                                <strong><?= htmlspecialchars($item['brand'] . ' ' . $item['name']); ?></strong>
                                            </a>
                                            <?php if (!empty($item['sku'])): ?>
                                                <div class="small text-muted">Артикул: <?= htmlspecialchars($item['sku']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($item['promotion_name'])): ?>
                                                <span class="badge bg-danger"><?= htmlspecialchars($item['promotion_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($item['seller_name'])): ?>
                                        <span class="small">
                                            <i class="fas fa-store me-1 text-primary"></i>
                                            <?= htmlspecialchars($item['seller_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="price-cell">
                                    <?php if ($item['current_price'] < $item['original_price']): ?>
                                        <span class="text-danger fw-bold item-price" data-price="<?= $item['current_price'] ?>">
                                            <?= formatPrice($item['current_price']); ?>
                                        </span>
                                        <span class="text-muted ms-2"><del><?= formatPrice($item['original_price']); ?></del></span>
                                        <?php if ($item['discount_percent']): ?>
                                            <span class="badge bg-danger ms-1">-<?= $item['discount_percent'] ?>%</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="fw-bold item-price" data-price="<?= $item['current_price'] ?>">
                                            <?= formatPrice($item['current_price']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="width: 150px;">
                                    <div class="input-group">
                                        <button class="btn btn-outline-secondary btn-sm quantity-decrease" type="button">-</button>
                                        <input type="number" 
                                               name="quantity[<?= $item['cart_key'] ?>]"
                                               value="<?= $item['quantity']; ?>"
                                               min="1"
                                               max="<?= $item['stock']; ?>"
                                               class="form-control form-control-sm text-center quantity-input"
                                               style="width: 60px;"
                                               data-cart-key="<?= htmlspecialchars($item['cart_key']) ?>">
                                        <button class="btn btn-outline-secondary btn-sm quantity-increase" type="button">+</button>
                                    </div>
                                    <small class="text-muted">доступно: <?= $item['stock'] ?> шт.</small>
                                </td>
                                <td class="fw-bold item-subtotal"><?= formatPrice($item['subtotal']); ?></td>
                                <td>
                                    <a href="cart.php?remove=<?= urlencode($item['cart_key']) ?>" 
                                       class="btn btn-sm btn-outline-danger btn-remove"
                                       data-cart-key="<?= htmlspecialchars($item['cart_key']) ?>">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-truck me-2"></i>Способы доставки</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($shipping_methods as $method): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="shipping_method" 
                                           id="shipping_<?= $method['id'] ?>" value="<?= $method['id'] ?>">
                                    <label class="form-check-label" for="shipping_<?= $method['id'] ?>">
                                        <strong><?= htmlspecialchars($method['name']) ?></strong>
                                        <?php if ($method['price'] > 0): ?>
                                            - <?= formatPrice($method['price']) ?>
                                        <?php else: ?>
                                            - Бесплатно
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Итого</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Товары (<span id="total-count"><?= $cart_data['count'] ?></span>):</span>
                                <strong id="items-total"><?= formatPrice($cart_data['total_price']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Доставка:</span>
                                <strong class="shipping-price">будет рассчитана</strong>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="h5">Общая сумма:</span>
                                <span class="h5 text-primary" id="final-total"><?= formatPrice($cart_data['total_price']) ?></span>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="update_cart" class="btn btn-outline-primary">
                                    <i class="fas fa-sync-alt me-2"></i>Обновить корзину
                                </button>
                                <a href="../checkout.php" class="btn btn-success btn-lg">
                                    <i class="fas fa-credit-card me-2"></i>Оформить заказ
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <?php if (!empty($cart_data['smartphones'])): ?>
            <!-- Рекомендации -->
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.cart-item img { border-radius: 4px; border: 1px solid #eee; }
.quantity-input { text-align: center; }
</style>

<script>
const API_URL = '/mobileshop/pages/cart/Api_Cart.php';

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.quantity-decrease').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentNode.querySelector('.quantity-input');
            const val = parseInt(input.value);
            if (val > 1) {
                input.value = val - 1;
                updateCartItem(input.dataset.cartKey, val - 1);
            }
        });
    });
    
    document.querySelectorAll('.quantity-increase').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentNode.querySelector('.quantity-input');
            const val = parseInt(input.value);
            const max = parseInt(input.max);
            if (val < max) {
                input.value = val + 1;
                updateCartItem(input.dataset.cartKey, val + 1);
            }
        });
    });
    
    document.querySelectorAll('.btn-remove').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!confirm('Удалить товар?')) return;
            
            const key = this.dataset.cartKey;
            fetch(API_URL + '?action=remove', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'cart_key=' + encodeURIComponent(key)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.closest('tr').remove();
                    updateTotals();
                    document.getElementById('cart-badge').textContent = data.count + ' товаров';
                }
            });
        });
    });
});

function updateCartItem(key, quantity) {
    fetch(API_URL + '?action=update', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'updates[' + encodeURIComponent(key) + ']=' + quantity
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            updateTotals();
        }
    });
}

function updateTotals() {
    let total = 0;
    document.querySelectorAll('tr[data-cart-key]').forEach(row => {
        const price = parseFloat(row.querySelector('.item-price').dataset.price);
        const qty = parseInt(row.querySelector('.quantity-input').value);
        const subtotal = price * qty;
        row.querySelector('.item-subtotal').textContent = formatPrice(subtotal);
        total += subtotal;
    });
    
    document.getElementById('items-total').textContent = formatPrice(total);
    document.getElementById('final-total').textContent = formatPrice(total);
}

function formatPrice(price) {
    return price.toLocaleString('ru-RU') + ' руб.';
}
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>