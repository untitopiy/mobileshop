<?php
/**
 * mobileshop/pages/cart/cart_view.php
 * View-шаблон для отображения корзины
 */

// ===== ИСПРАВЛЕНИЕ: Генерируем CSRF-токен ДО подключения header.php =====
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/mobileshop/inc/header.php';

$items = $cartData['items'] ?? [];
$totalPrice = $cartData['total_price'] ?? 0;
$count = $cartData['count'] ?? 0;

$formatPrice = function (float $price): string {
    return number_format($price, 0, ',', ' ') . ' руб.';
};

// Защита: преобразуем объекты в массивы если нужно
$normalizedItems = [];
foreach ($items as $item) {
    if (is_object($item) && $item instanceof CartItemDTO) {
        $normalizedItems[] = $item->toArray();
    } else {
        $normalizedItems[] = $item;
    }
}
$items = $normalizedItems;

// Определяем выбранный способ доставки
$selectedShippingId = $preselectedShipping ?? 1;
$selectedShippingPrice = $preselectedShippingPrice ?? 0;

// ===== ИСПРАВЛЕНИЕ: Получаем актуальное количество товаров в сравнении =====
$compareCount = 0;
if (isset($_SESSION['compare_ids']) && is_array($_SESSION['compare_ids'])) {
    $compareCount = count($_SESSION['compare_ids']);
}
?>

<!-- ===== ИСПРАВЛЕНИЕ: Добавляем CSRF-токен в meta-тег для AJAX ===== -->
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

<div class="container cart-container py-4">
    <h1 class="mb-4">
        <i class="fas fa-shopping-cart me-2"></i>Корзина
        <?php if ($count > 0): ?>
            <span class="badge bg-primary ms-2" id="cart-badge"><?= $count ?> товаров</span>
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

    <?php if (empty($items)): ?>
        <div class="text-center py-5">
            <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
            <h3 class="text-muted">Корзина пуста</h3>
            <p class="mb-4">Добавьте товары, чтобы оформить заказ</p>
            <a href="<?= $base_url ?>index.php" class="btn btn-primary">Перейти к покупкам</a>
        </div>
    <?php else: ?>
        <form method="POST" id="cart-form" action="cart.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="shipping_method" id="selected-shipping-method" value="<?= $selectedShippingId ?>">
            <input type="hidden" name="shipping_price" id="selected-shipping-price" value="<?= $selectedShippingPrice ?>">

            <div class="table-responsive">
                <table class="table cart-table">
                    <thead class="table-light">
                        <tr>
                            <th>Товар</th>
                            <th>Продавец</th>
                            <th>Цена</th>
                            <th class="text-center" style="width: 150px;">Количество</th>
                            <th>Сумма</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $uniqueItems = [];
                        foreach ($items as $item):
                            $cartKey = $item['cart_key'] ?? '';
                            if (in_array($cartKey, $uniqueItems)) continue;
                            $uniqueItems[] = $cartKey;

                            $productId = $item['product_id'] ?? 0;
                            $quantity = (int)($item['quantity'] ?? 0);
                            $stock = (int)($item['stock'] ?? 0);
                            $name = $item['name'] ?? '';
                            $brand = $item['brand'] ?? '';
                            $sku = $item['sku'] ?? '';
                            $currentPrice = (float)($item['current_price'] ?? 0);
                            $originalPrice = isset($item['original_price']) ? (float)$item['original_price'] : null;
                            $subtotal = (float)($item['subtotal'] ?? ($currentPrice * $quantity));
                            $imageUrl = $item['image_url'] ?? '';
                            $sellerName = $item['seller_name'] ?? 'Admin Shop';
                            $discountPercent = $item['discount_percent'] ?? null;
                            $promotionName = $item['promotion_name'] ?? '';
                        ?>
                            <tr data-cart-key="<?= htmlspecialchars($cartKey) ?>">
                                <td class="cart-item">
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($imageUrl)): ?>
                                            <img src="<?= $base_url . ltrim($imageUrl, '/'); ?>"
                                                alt="<?= htmlspecialchars($name); ?>"
                                                class="me-3 cart-item-image"
                                                style="width: 60px; height: 60px; object-fit: contain; border: 1px solid #eee; border-radius: 4px;">
                                        <?php else: ?>
                                            <div class="me-3 cart-item-image" style="width: 60px; height: 60px; background: #f8f9fa; border: 1px solid #eee; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <a href="<?= $base_url ?>product.php?id=<?= (int)$productId ?>" class="text-decoration-none fw-bold">
                                                <?= htmlspecialchars($brand . ' ' . $name); ?>
                                            </a>
                                            <?php if (!empty($sku)): ?>
                                                <div class="small text-muted">Артикул: <?= htmlspecialchars($sku) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($promotionName)): ?>
                                                <span class="badge bg-danger mt-1"><?= htmlspecialchars($promotionName) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <?php if (!empty($sellerName)): ?>
                                        <span class="small">
                                            <i class="fas fa-store me-1 text-primary"></i>
                                            <?= htmlspecialchars($sellerName) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="price-cell align-middle">
                                    <?php if ($discountPercent && $discountPercent > 0): ?>
                                        <span class="text-danger fw-bold item-price" data-price="<?= $currentPrice ?>">
                                            <?= $formatPrice($currentPrice) ?>
                                        </span>
                                        <?php if ($originalPrice && $originalPrice > $currentPrice): ?>
                                            <span class="text-muted ms-2"><del><?= $formatPrice($originalPrice) ?></del></span>
                                        <?php endif; ?>
                                        <span class="badge bg-danger ms-1">-<?= (int)$discountPercent ?>%</span>
                                    <?php else: ?>
                                        <span class="fw-bold item-price" data-price="<?= $currentPrice ?>">
                                            <?= $formatPrice($currentPrice) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="quantity-cell align-middle">
                                    <div class="input-group input-group-sm justify-content-center" style="max-width: 140px; margin: 0 auto;">
                                        <button class="btn btn-outline-secondary quantity-decrease" type="button" style="width: 32px; padding: 0.25rem 0;">-</button>
                                        <input type="number"
                                            name="quantity[<?= htmlspecialchars($cartKey) ?>]"
                                            value="<?= $quantity ?>"
                                            min="1"
                                            max="<?= $stock ?>"
                                            class="form-control text-center quantity-input"
                                            data-cart-key="<?= htmlspecialchars($cartKey) ?>"
                                            style="width: 50px; min-width: 50px; padding: 0.25rem;">
                                        <button class="btn btn-outline-secondary quantity-increase" type="button" style="width: 32px; padding: 0.25rem 0;">+</button>
                                    </div>
                                    <small class="text-muted d-block mt-1 text-center">доступно: <?= $stock ?> шт.</small>
                                </td>
                                <td class="fw-bold item-subtotal align-middle"><?= $formatPrice($subtotal) ?></td>
                                <td class="align-middle">
                                    <button type="button"
                                        class="btn btn-sm btn-outline-danger btn-remove"
                                        data-cart-key="<?= htmlspecialchars($cartKey) ?>"
                                        title="Удалить из корзины">
                                        <i class="fas fa-trash"></i>
                                    </button>
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
                        <div class="card-body" id="shipping-methods">
                            <!-- Самовывоз из магазина -->
                            <div class="form-check mb-3 shipping-option <?= ($selectedShippingId == 1) ? 'border-primary bg-light' : '' ?>" 
                                 data-price="0"
                                 data-method-id="1"
                                 data-free-from="0">
                                <input class="form-check-input shipping-radio" 
                                       type="radio" 
                                       name="shipping_method_radio"
                                       id="shipping_1" 
                                       value="1"
                                       data-price="0"
                                       <?= ($selectedShippingId == 1) ? 'checked' : '' ?>>
                                <label class="form-check-label w-100" for="shipping_1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>Самовывоз из магазина</strong>
                                            <small class="text-muted d-block">1 день</small>
                                            <small class="text-muted d-block">Заберите заказ в нашем магазине</small>
                                        </div>
                                        <span class="badge bg-success ms-2 shipping-badge">Бесплатно</span>
                                    </div>
                                </label>
                            </div>

                            <!-- Курьер по Минску -->
                            <div class="form-check mb-3 shipping-option <?= ($selectedShippingId == 2) ? 'border-primary bg-light' : '' ?>" 
                                 data-price="<?= ($totalPrice >= 500) ? 0 : 10 ?>"
                                 data-method-id="2"
                                 data-free-from="500">
                                <input class="form-check-input shipping-radio" 
                                       type="radio" 
                                       name="shipping_method_radio"
                                       id="shipping_2" 
                                       value="2"
                                       data-price="10"
                                       data-free-from="500"
                                       <?= ($selectedShippingId == 2) ? 'checked' : '' ?>>
                                <label class="form-check-label w-100" for="shipping_2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>Курьер по Минску</strong>
                                            <small class="text-muted d-block">1-2 дней</small>
                                            <small class="text-muted d-block">Доставка курьером по Минску</small>
                                        </div>
                                        <span class="badge bg-<?= ($totalPrice >= 500) ? 'success' : 'primary' ?> ms-2 shipping-badge" id="badge-2">
                                            <?= ($totalPrice >= 500) ? 'Бесплатно (от 500 руб.)' : '10 руб.' ?>
                                        </span>
                                    </div>
                                </label>
                            </div>

                            <!-- Белпочта -->
                            <div class="form-check mb-3 shipping-option <?= ($selectedShippingId == 3) ? 'border-primary bg-light' : '' ?>" 
                                 data-price="15"
                                 data-method-id="3"
                                 data-free-from="0">
                                <input class="form-check-input shipping-radio" 
                                       type="radio" 
                                       name="shipping_method_radio"
                                       id="shipping_3" 
                                       value="3"
                                       data-price="15"
                                       <?= ($selectedShippingId == 3) ? 'checked' : '' ?>>
                                <label class="form-check-label w-100" for="shipping_3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>Белпочта</strong>
                                            <small class="text-muted d-block">3-7 дней</small>
                                            <small class="text-muted d-block">Доставка в отделение Белпочты</small>
                                        </div>
                                        <span class="badge bg-primary ms-2 shipping-badge">15 руб.</span>
                                    </div>
                                </label>
                            </div>

                            <!-- Европочта -->
                            <div class="form-check mb-3 shipping-option <?= ($selectedShippingId == 4) ? 'border-primary bg-light' : '' ?>" 
                                 data-price="<?= ($totalPrice >= 300) ? 0 : 12 ?>"
                                 data-method-id="4"
                                 data-free-from="300">
                                <input class="form-check-input shipping-radio" 
                                       type="radio" 
                                       name="shipping_method_radio"
                                       id="shipping_4" 
                                       value="4"
                                       data-price="12"
                                       data-free-from="300"
                                       <?= ($selectedShippingId == 4) ? 'checked' : '' ?>>
                                <label class="form-check-label w-100" for="shipping_4">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>Европочта</strong>
                                            <small class="text-muted d-block">2-4 дней</small>
                                            <small class="text-muted d-block">Доставка в отделение Европочты</small>
                                        </div>
                                        <span class="badge bg-<?= ($totalPrice >= 300) ? 'success' : 'primary' ?> ms-2 shipping-badge" id="badge-4">
                                            <?= ($totalPrice >= 300) ? 'Бесплатно (от 300 руб.)' : '12 руб.' ?>
                                        </span>
                                    </div>
                                </label>
                            </div>

                            <!-- DHL Express -->
                            <div class="form-check mb-3 shipping-option <?= ($selectedShippingId == 5) ? 'border-primary bg-light' : '' ?>" 
                                 data-price="50"
                                 data-method-id="5"
                                 data-free-from="0">
                                <input class="form-check-input shipping-radio" 
                                       type="radio" 
                                       name="shipping_method_radio"
                                       id="shipping_5" 
                                       value="5"
                                       data-price="50"
                                       <?= ($selectedShippingId == 5) ? 'checked' : '' ?>>
                                <label class="form-check-label w-100" for="shipping_5">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>DHL Express</strong>
                                            <small class="text-muted d-block">1-3 дней</small>
                                            <small class="text-muted d-block">Экспресс-доставка по всему миру</small>
                                        </div>
                                        <span class="badge bg-primary ms-2 shipping-badge">50 руб.</span>
                                    </div>
                                </label>
                            </div>
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
                                <span>Товары (<span id="total-count"><?= $count ?></span>):</span>
                                <strong id="items-total" data-raw-price="<?= $totalPrice ?>"><?= $formatPrice($totalPrice) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Доставка:</span>
                                <strong class="shipping-price" id="shipping-price-display">
                                    <?= ($selectedShippingPrice == 0 || ($selectedShippingId == 2 && $totalPrice >= 500) || ($selectedShippingId == 4 && $totalPrice >= 300)) ? 'Бесплатно' : $formatPrice($selectedShippingPrice) ?>
                                </strong>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="h5">Общая сумма:</span>
                                <span class="h5 text-primary" id="final-total">
                                    <?php
                                    $displayShippingPrice = $selectedShippingPrice;
                                    if ($selectedShippingId == 2 && $totalPrice >= 500) $displayShippingPrice = 0;
                                    if ($selectedShippingId == 4 && $totalPrice >= 300) $displayShippingPrice = 0;
                                    echo $formatPrice($totalPrice + $displayShippingPrice);
                                    ?>
                                </span>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="update_cart" class="btn btn-outline-primary">
                                    <i class="fas fa-sync-alt me-2"></i>Обновить корзину
                                </button>
                                <a href="<?= $base_url ?>checkout.php" 
                                   class="btn btn-success btn-lg" 
                                   id="checkout-btn">
                                    <i class="fas fa-credit-card me-2"></i>Оформить заказ
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<style>
    .cart-container {
        max-width: 1200px;
        margin: 0 auto;
    }

    .cart-item-image {
        width: 60px;
        height: 60px;
        object-fit: contain;
        background: #f8f9fa;
    }

    .quantity-cell {
        vertical-align: middle;
    }

    .quantity-cell .input-group {
        flex-wrap: nowrap;
    }

    .quantity-input {
        text-align: center;
        -moz-appearance: textfield;
    }

    .quantity-input::-webkit-outer-spin-button,
    .quantity-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .quantity-decrease,
    .quantity-increase {
        font-weight: bold;
        line-height: 1;
    }

    /* Стили для способов доставки */
    .shipping-option {
        padding: 12px;
        border: 2px solid transparent;
        border-radius: 8px;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .shipping-option:hover {
        border-color: #dee2e6;
        background-color: #f8f9fa;
    }

    .shipping-option.border-primary {
        border-color: #0d6efd !important;
    }

    .shipping-option.bg-light {
        background-color: #e9ecef !important;
    }

    .shipping-option .form-check-input {
        margin-top: 0.3rem;
    }

    .shipping-price {
        transition: all 0.3s ease;
    }

    .shipping-price.text-success {
        color: #198754 !important;
        font-weight: 600;
    }

    #final-total {
        transition: all 0.3s ease;
    }

    @media (max-width: 768px) {
        .cart-item {
            min-width: 200px;
        }

        .cart-item-image {
            width: 50px;
            height: 50px;
        }

        .quantity-cell .input-group {
            max-width: 120px;
        }
    }
</style>

<script src="cart.js"></script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/mobileshop/inc/footer.php'; ?>