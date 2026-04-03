<?php

/**
 * mobileshop/pages/cart/cart_view.php
 * View-шаблон для отображения корзины
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/mobileshop/inc/header.php';

$items = $cartData['items'] ?? [];
$totalPrice = $cartData['total_price'] ?? 0;
$count = $cartData['count'] ?? 0;

$formatPrice = function (float $price): string {
    return number_format($price, 0, ',', ' ') . ' руб.';
};
?>

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
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

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
                        <?php
                        $uniqueItems = [];
                        foreach ($items as $item):
                            // Создаем уникальный ключ для товара
                            $cartKey = $item['cart_key'] ?? '';

                            // Пропускаем дубликаты
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
                                    <div class="input-group" style="max-width: 140px;">
                                        <button class="btn btn-outline-secondary btn-sm quantity-decrease" type="button">-</button>
                                        <input type="number"
                                            name="quantity[<?= htmlspecialchars($cartKey) ?>]"
                                            value="<?= $quantity ?>"
                                            min="1"
                                            max="<?= $stock ?>"
                                            class="form-control form-control-sm text-center quantity-input"
                                            data-cart-key="<?= htmlspecialchars($cartKey) ?>"
                                            style="width: 60px;">
                                        <button class="btn btn-outline-secondary btn-sm quantity-increase" type="button">+</button>
                                    </div>
                                    <small class="text-muted d-block mt-1">доступно: <?= $stock ?> шт.</small>
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
                        <div class="card-body">
                            <?php foreach ($shippingMethods as $method): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="shipping_method"
                                        id="shipping_<?= (int)$method['id'] ?>" value="<?= (int)$method['id'] ?>">
                                    <label class="form-check-label" for="shipping_<?= (int)$method['id'] ?>">
                                        <strong><?= htmlspecialchars($method['name']) ?></strong>
                                        <?php if ($method['price'] > 0): ?>
                                            - <?= $formatPrice($method['price']) ?>
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
                                <span>Товары (<span id="total-count"><?= $count ?></span>):</span>
                                <strong id="items-total"><?= $formatPrice($totalPrice) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Доставка:</span>
                                <strong class="shipping-price">будет рассчитана</strong>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="h5">Общая сумма:</span>
                                <span class="h5 text-primary" id="final-total"><?= $formatPrice($totalPrice) ?></span>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="update_cart" class="btn btn-outline-primary">
                                    <i class="fas fa-sync-alt me-2"></i>Обновить корзину
                                </button>
                                <a href="<?= $base_url ?>checkout.php" class="btn btn-success btn-lg">
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

    .quantity-cell .input-group {
        width: auto;
    }

    .quantity-input {
        text-align: center;
    }

    .quantity-input::-webkit-outer-spin-button,
    .quantity-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .quantity-input[type=number] {
        -moz-appearance: textfield;
    }

    @media (max-width: 768px) {
        .cart-item {
            min-width: 200px;
        }

        .cart-item-image {
            width: 50px;
            height: 50px;
        }
    }
</style>

<script src="cart.js"></script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/mobileshop/inc/footer.php'; ?>