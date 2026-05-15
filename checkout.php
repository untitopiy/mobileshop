<?php
/**
 * mobileshop/checkout.php
 * Оформление заказа
 */

// ВАЖНО: Вся логика с session_start() и header() ДО ЛЮБОГО ВЫВОДА
session_start();

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

// Проверка авторизации пользователя — ДО подключения header.php
if (!isset($_SESSION['id'])) {
    $_SESSION['redirect_after_login'] = 'checkout.php';
    header("Location: pages/auth.php");
    exit;
}

$user_id = $_SESSION['id'];

// Получаем корзину пользователя из БД
$cart_query = $db->prepare("
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
        p.seller_id,
        pv.price,
        pv.old_price,
        pv.quantity as stock,
        pv.sku,
        pv.image_url as variation_image,
        (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as product_image,
        s.shop_name as seller_name,
        CASE 
            WHEN prom.discount_type = 'percent' THEN prom.discount_value
            ELSE NULL
        END as discount_percent,
        prom.discount_type,
        prom.discount_value,
        prom.name as promotion_name
    FROM cart c
    JOIN cart_items ci ON ci.cart_id = c.id
    JOIN products p ON p.id = ci.product_id
    LEFT JOIN product_variations pv ON pv.id = ci.variation_id
    LEFT JOIN sellers s ON s.id = p.seller_id
    LEFT JOIN promotion_products pp ON pp.product_id = p.id
    LEFT JOIN promotions prom ON prom.id = pp.promotion_id 
        AND prom.is_active = 1 
        AND prom.starts_at <= NOW() 
        AND (prom.expires_at >= NOW() OR prom.expires_at IS NULL)
    WHERE c.user_id = ?
    GROUP BY ci.id
    ORDER BY ci.created_at DESC
");
$cart_query->bind_param('i', $user_id);
$cart_query->execute();
$cart_result = $cart_query->get_result();

$cart_items = [];
$total_price = 0;
$sellers = [];

while ($row = $cart_result->fetch_assoc()) {
    $item_price = $row['price'] ?: $row['cart_price'];
    
    // Применяем скидку акции
    if ($row['discount_percent']) {
        $final_price = $item_price * (100 - $row['discount_percent']) / 100;
    } elseif ($row['discount_type'] === 'fixed' && $row['discount_value'] > 0) {
        $final_price = max(0, $item_price - $row['discount_value']);
    } else {
        $final_price = $item_price;
    }
    
    $row['current_price'] = $final_price;
    $row['subtotal'] = $final_price * $row['quantity'];
    $row['image_url'] = $row['variation_image'] ?: $row['product_image'];
    
    $cart_items[] = $row;
    $total_price += $row['subtotal'];
    
    // Группируем по продавцам
    if (!isset($sellers[$row['seller_id']])) {
        $sellers[$row['seller_id']] = [
            'name' => $row['seller_name'],
            'items' => [],
            'subtotal' => 0
        ];
    }
    $sellers[$row['seller_id']]['items'][] = $row;
    $sellers[$row['seller_id']]['subtotal'] += $row['subtotal'];
}

// Если корзина пуста, перенаправляем — ДО header.php
if (empty($cart_items)) {
    header("Location: pages/cart/cart.php");
    exit;
}

// ИСПРАВЛЕНО: Унифицированные способы доставки (те же что и в корзине)
function getUnifiedShippingMethods($totalPrice = 0) {
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

$shipping_methods = getUnifiedShippingMethods($total_price);
$payment_methods = getPaymentMethods($db);

// Получаем информацию о пользователе
$user_query = $db->prepare("SELECT full_name, phone, email FROM users WHERE id = ?");
$user_query->bind_param('i', $user_id);
$user_query->execute();
$user = $user_query->get_result()->fetch_assoc();

// Обработка оформления заказа
$errors = [];
$selected_shipping = null;
$selected_payment = null;
$coupon_discount = 0;
$coupon_type = '';
$coupon_id = null;
$shipping_price = 0;

// Значения по умолчанию для полей формы
$form_data = [
    'full_name' => $_POST['full_name'] ?? $user['full_name'] ?? '',
    'phone' => $_POST['phone'] ?? $user['phone'] ?? '',
    'email' => $_POST['email'] ?? $user['email'] ?? '',
    'city' => $_POST['city'] ?? '',
    'street' => $_POST['street'] ?? '',
    'house' => $_POST['house'] ?? '',
    'apartment' => $_POST['apartment'] ?? '',
    'entrance' => $_POST['entrance'] ?? '',
    'floor' => $_POST['floor'] ?? '',
    'shipping_method' => $_POST['shipping_method'] ?? '',
    'payment_method' => $_POST['payment_method'] ?? '',
    'coupon_code' => $_POST['coupon_code'] ?? '',
    'notes' => $_POST['notes'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $full_name = trim($form_data['full_name']);
    $phone = trim($form_data['phone']);
    $email = trim($form_data['email']);
    
    // Новые поля адреса
    $city = trim($form_data['city']);
    $street = trim($form_data['street']);
    $house = trim($form_data['house']);
    $apartment = trim($form_data['apartment']);
    $entrance = trim($form_data['entrance']);
    $floor = trim($form_data['floor']);
    
    $shipping_method_id = (int)$form_data['shipping_method'];
    $payment_method_id = (int)$form_data['payment_method'];
    $coupon_code = trim($form_data['coupon_code']);
    $notes = trim($form_data['notes']);
    
    // Валидация
    if (empty($full_name)) {
        $errors[] = "Укажите ФИО получателя.";
    }
    if (empty($phone)) {
        $errors[] = "Укажите номер телефона.";
    } elseif (!preg_match('/^\+?[0-9\s\-\(\)]+$/', $phone)) {
        $errors[] = "Некорректный номер телефона.";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Некорректный email адрес.";
    }
    
    // Валидация адреса
    if (empty($city)) {
        $errors[] = "Укажите город.";
    }
    if (empty($street)) {
        $errors[] = "Укажите улицу.";
    }
    if (empty($house)) {
        $errors[] = "Укажите номер дома.";
    }
    
    // Проверка способа доставки
    if (empty($shipping_method_id)) {
        $errors[] = "Выберите способ доставки.";
    } else {
        $shipping_valid = false;
        foreach ($shipping_methods as $method) {
            if ($method['id'] == $shipping_method_id) {
                $selected_shipping = $method;
                // ИСПРАВЛЕНО: Расчет цены доставки с учетом бесплатной доставки
                if ($method['free_from'] && $total_price >= $method['free_from']) {
                    $shipping_price = 0;
                } else {
                    $shipping_price = $method['price'];
                }
                $shipping_valid = true;
                break;
            }
        }
        if (!$shipping_valid) {
            $errors[] = "Выберите способ доставки.";
        }
    }
    
    // Проверка способа оплаты
    if (empty($payment_method_id)) {
        $errors[] = "Выберите способ оплаты.";
    } else {
        $payment_valid = false;
        foreach ($payment_methods as $method) {
            if ($method['id'] == $payment_method_id) {
                $selected_payment = $method;
                $payment_valid = true;
                break;
            }
        }
        if (!$payment_valid) {
            $errors[] = "Выберите способ оплаты.";
        }
    }

    // Проверка купона
    if (!empty($coupon_code)) {
        // Проверяем существование купона
        $stmt_coupon = $db->prepare("
            SELECT id, code, name, discount_type, discount_value, 
                   min_order_amount, max_discount_amount, type,
                   expires_at, status
            FROM coupons
            WHERE code = ? 
              AND status = 'active' 
              AND expires_at >= NOW()
              AND starts_at <= NOW()
            LIMIT 1
        ");
        $stmt_coupon->bind_param("s", $coupon_code);
        $stmt_coupon->execute();
        $result_coupon = $stmt_coupon->get_result()->fetch_assoc();
        $stmt_coupon->close();

        if ($result_coupon) {
            // Проверяем, не использовал ли уже пользователь этот купон
            $usage_check = $db->prepare("
                SELECT COUNT(*) as used_count 
                FROM coupon_usage 
                WHERE coupon_id = ? AND user_id = ?
            ");
            $usage_check->bind_param("ii", $result_coupon['id'], $user_id);
            $usage_check->execute();
            $usage_result = $usage_check->get_result()->fetch_assoc();
            $usage_check->close();
            
            if ($usage_result['used_count'] > 0) {
                $errors[] = "Вы уже использовали этот купон.";
            }
            // Проверяем лимит использований
            elseif ($result_coupon['type'] === 'global') {
                $total_usage = $db->prepare("
                    SELECT COUNT(*) as total_used 
                    FROM coupon_usage 
                    WHERE coupon_id = ?
                ");
                $total_usage->bind_param("i", $result_coupon['id']);
                $total_usage->execute();
                $total_result = $total_usage->get_result()->fetch_assoc();
                $total_usage->close();
            }
            
            // Проверяем минимальную сумму заказа
            if ($result_coupon['min_order_amount'] && $total_price < $result_coupon['min_order_amount']) {
                $errors[] = "Минимальная сумма заказа для этого купона: " . formatPrice($result_coupon['min_order_amount']);
            } else {
                $coupon_type = $result_coupon['discount_type'];
                $coupon_discount = (float)$result_coupon['discount_value'];
                $coupon_id = (int)$result_coupon['id'];
            }
        } else {
            $errors[] = "Купон недействителен или просрочен.";
        }
    }

    if (empty($errors)) {
        // Рассчитываем итоговую сумму
        $subtotal = $total_price;
        $discount_amount = 0;
        $final_total = $subtotal + $shipping_price; // Значение по умолчанию
        
        if ($coupon_discount > 0) {
            if ($coupon_type === 'percent') {
                $discount_amount = $subtotal * $coupon_discount / 100;
                // Проверяем максимальную сумму скидки, если есть
                if (isset($result_coupon['max_discount_amount']) && $result_coupon['max_discount_amount'] > 0 && $discount_amount > $result_coupon['max_discount_amount']) {
                    $discount_amount = $result_coupon['max_discount_amount'];
                }
            } else { // fixed
                $discount_amount = min($coupon_discount, $subtotal);
            }
            $final_total = $subtotal + $shipping_price - $discount_amount;
        } else {
            $final_total = $subtotal + $shipping_price;
        }

        // Генерируем уникальный номер заказа
        $unique = false;
        $max_attempts = 10;
        $attempt = 0;
        
        while (!$unique && $attempt < $max_attempts) {
            // Формируем номер: ORD-YYYYMMDD-USERID-RANDOM
            $order_number = 'ORD-' . date('Ymd') . '-' . 
                           $user_id . '-' . 
                           str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            
            // Проверяем, существует ли уже такой номер
            $check_query = $db->prepare("SELECT id FROM orders WHERE order_number = ?");
            $check_query->bind_param("s", $order_number);
            $check_query->execute();
            $check_result = $check_query->get_result();
            
            if ($check_result->num_rows === 0) {
                $unique = true;
            }
            
            $check_query->close();
            $attempt++;
        }
        
        // Если не удалось сгенерировать уникальный номер за 10 попыток
        if (!$unique) {
            // Используем timestamp с микросекундами как последнее средство
            $order_number = 'ORD-' . date('YmdHis') . '-' . substr(uniqid(), -8);
        }
        
        // Формируем полный адрес из отдельных полей
        $full_address = "г. {$city}, ул. {$street}, д. {$house}";
        if (!empty($apartment)) {
            $full_address .= ", кв. {$apartment}";
        }
        if (!empty($entrance)) {
            $full_address .= ", подъезд {$entrance}";
        }
        if (!empty($floor)) {
            $full_address .= ", этаж {$floor}";
        }

        // Начинаем транзакцию
        $db->begin_transaction();

        try {
            // Создаем заказ
            $order_query = $db->prepare("
                INSERT INTO orders (order_number, user_id, status_id, shipping_method_id, payment_method_id,
                                   subtotal, shipping_price, discount_amount, total_price, coupon_code, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            // Проверяем переменные
            $status_id = 1; // Статус "Ожидает оплаты"
            $coupon_code_for_db = !empty($coupon_code) ? $coupon_code : null;
            $notes_for_db = !empty($notes) ? $notes : null;

            // Приводим все числа к правильному типу float
            $subtotal_float = (float)$subtotal;
            $shipping_price_float = (float)$shipping_price;
            $discount_amount_float = (float)$discount_amount;
            $final_total_float = (float)$final_total;

            // 11 переменных - 11 символов в строке типов
            $order_query->bind_param("siiiiddddss", 
                $order_number,           // string - 1 (s)
                $user_id,                // integer - 2 (i)
                $status_id,              // integer - 3 (i)
                $shipping_method_id,     // integer - 4 (i)
                $payment_method_id,      // integer - 5 (i)
                $subtotal_float,         // double - 6 (d)
                $shipping_price_float,   // double - 7 (d)
                $discount_amount_float,  // double - 8 (d)
                $final_total_float,      // double - 9 (d)
                $coupon_code_for_db,     // string - 10 (s)
                $notes_for_db            // string - 11 (s)
            );
            
            // Выполняем запрос ТОЛЬКО ОДИН РАЗ
            $order_query->execute();
            $order_id = $order_query->insert_id;

            // Добавляем адрес доставки с новыми полями
            $address_query = $db->prepare("
                INSERT INTO order_addresses (
                    order_id, type, full_name, phone, email, country, city, address, 
                    postal_code, created_at
                ) VALUES (
                    ?, 'shipping', ?, ?, ?, 'Беларусь', ?, ?, '', NOW()
                )
            ");
            $address_query->bind_param("isssss", $order_id, $full_name, $phone, $email, $city, $full_address);
            $address_query->execute();

            // Создаем записи для каждого продавца
            foreach ($sellers as $seller_id => $seller_data) {
                $seller_subtotal = $seller_data['subtotal'];
                $seller_discount = $seller_subtotal * ($discount_amount / $subtotal);
                $seller_total = $seller_subtotal - $seller_discount;
                
                // Рассчитываем комиссию (10% по умолчанию)
                $commission_rate = 10.00;
                $commission_amount = $seller_total * $commission_rate / 100;
                $seller_payout = $seller_total - $commission_amount;

                $order_seller_query = $db->prepare("
                    INSERT INTO order_sellers (order_id, seller_id, status_id, subtotal, shipping_price,
                                              discount_amount, total_price, commission_amount, seller_payout)
                    VALUES (?, ?, 1, ?, 0, ?, ?, ?, ?)
                ");
                $order_seller_query->bind_param("iiddddd", $order_id, $seller_id, $seller_subtotal, 
                                               $seller_discount, $seller_total, $commission_amount, $seller_payout);
                $order_seller_query->execute();
                $order_seller_id = $order_seller_query->insert_id;

                // Добавляем товары для этого продавца
                foreach ($seller_data['items'] as $item) {
                    $item_discount = $item['subtotal'] * ($discount_amount / $subtotal);
                    $item_total = $item['subtotal'] - $item_discount;
                    
                    $item_query = $db->prepare("
                        INSERT INTO order_items (order_seller_id, product_id, variation_id, product_name, product_sku,
                                               quantity, price, total_price)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $product_name = $item['brand'] . ' ' . $item['name'];
                    $sku = $item['sku'] ?? '';
                    $item_query->bind_param("iiissidd", $order_seller_id, $item['product_id'], $item['variation_id'],
                                          $product_name, $sku, $item['quantity'], $item['current_price'], $item_total);
                    $item_query->execute();

                    // Обновляем количество товара на складе
                    if ($item['variation_id']) {
                        $update_stock = $db->prepare("
                            UPDATE product_variations SET quantity = quantity - ? WHERE id = ?
                        ");
                        $update_stock->bind_param("ii", $item['quantity'], $item['variation_id']);
                    } else {
                        $update_stock = $db->prepare("
                            UPDATE product_variations SET quantity = quantity - ? 
                            WHERE product_id = ? ORDER BY id LIMIT 1
                        ");
                        $update_stock->bind_param("ii", $item['quantity'], $item['product_id']);
                    }
                    $update_stock->execute();

                    // Увеличиваем счетчик продаж товара
                    $update_sales = $db->prepare("
                        UPDATE products SET sales_count = sales_count + 1 WHERE id = ?
                    ");
                    $update_sales->bind_param("i", $item['product_id']);
                    $update_sales->execute();
                }
            }

            // Если использован купон, отмечаем его использование
            if ($coupon_id) {
                $usage_query = $db->prepare("
                    INSERT INTO coupon_usage (coupon_id, user_id, order_id, discount_amount, used_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $usage_query->bind_param("iiid", $coupon_id, $user_id, $order_id, $discount_amount);
                $usage_query->execute();
            }

            // Очищаем корзину
            $clear_cart = $db->prepare("
                DELETE ci FROM cart_items ci
                JOIN cart c ON c.id = ci.cart_id
                WHERE c.user_id = ?
            ");
            $clear_cart->bind_param("i", $user_id);
            $clear_cart->execute();

            // Добавляем запись в историю статусов
            $history_query = $db->prepare("
                INSERT INTO order_status_history (order_id, status_id, comment, created_by, created_at)
                VALUES (?, 1, 'Заказ создан', ?, NOW())
            ");
            $history_query->bind_param("ii", $order_id, $user_id);
            $history_query->execute();

            // Подтверждаем транзакцию
            $db->commit();

            // Очищаем сессионную корзину и данные о доставке
            $_SESSION['cart'] = [];
            // Очищаем сохраненный способ доставки после успешного заказа
            unset($_SESSION['selected_shipping_method']);
            unset($_SESSION['selected_shipping_price']);
            
            // Перенаправляем на страницу успеха — ДО header.php!
            header("Location: success.php?order_id=$order_id");
            exit;

        } catch (Exception $e) {
            $db->rollback();
            $errors[] = "Ошибка при оформлении заказа: " . $e->getMessage();
        }
    }
}

// ТОЛЬКО ТЕПЕРЬ подключаем header.php, когда все header() вызваны
require_once __DIR__ . '/inc/header.php';
?>

<div class="container checkout-container py-4">
    <h1 class="mb-4">Оформление заказа</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Форма оформления -->
        <div class="col-md-8">
            <form method="POST" id="checkout-form">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Контактная информация</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label">ФИО получателя *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?= htmlspecialchars($form_data['full_name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Телефон *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?= htmlspecialchars($form_data['phone']) ?>" 
                                       placeholder="+375 XX XXX-XX-XX" required>
                            </div>
                            <div class="col-12">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($form_data['email']) ?>">
                                <small class="text-muted">Для отправки подтверждения заказа</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Адрес доставки</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="city" class="form-label">Город *</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?= htmlspecialchars($form_data['city']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="street" class="form-label">Улица *</label>
                                <input type="text" class="form-control" id="street" name="street" 
                                       value="<?= htmlspecialchars($form_data['street']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="house" class="form-label">Дом *</label>
                                <input type="text" class="form-control" id="house" name="house" 
                                       value="<?= htmlspecialchars($form_data['house']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="apartment" class="form-label">Квартира/офис</label>
                                <input type="text" class="form-control" id="apartment" name="apartment" 
                                       value="<?= htmlspecialchars($form_data['apartment']) ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="entrance" class="form-label">Подъезд</label>
                                <input type="text" class="form-control" id="entrance" name="entrance" 
                                       value="<?= htmlspecialchars($form_data['entrance']) ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="floor" class="form-label">Этаж</label>
                                <input type="text" class="form-control" id="floor" name="floor" 
                                       value="<?= htmlspecialchars($form_data['floor']) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== СПОСОБЫ ДОСТАВКИ (ИСПРАВЛЕННЫЙ ДИНАМИЧЕСКИЙ БЛОК) ===== -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-truck me-2"></i>Способы доставки</h5>
                    </div>
                    <div class="card-body" id="shipping-methods-container">
                        <?php foreach ($shipping_methods as $method): ?>
                            <?php 
                                $isFree = $method['free_from'] && $total_price >= $method['free_from'];
                                $displayPrice = $isFree ? 0 : $method['price'];
                                $badgeClass = $isFree ? 'success' : 'primary';
                                $badgeText = $isFree 
                                    ? 'Бесплатно' . ($method['free_from'] ? ' (от ' . number_format($method['free_from'], 0, ',', ' ') . ' руб.)' : '')
                                    : number_format($method['price'], 0, ',', ' ') . ' руб.';
                                $daysText = $method['estimated_days_min'] == $method['estimated_days_max'] 
                                    ? $method['estimated_days_min'] . ' день' 
                                    : $method['estimated_days_min'] . '-' . $method['estimated_days_max'] . ' дней';
                            ?>
                            <div class="form-check mb-3 shipping-option <?= ($form_data['shipping_method'] == $method['id']) ? 'border-primary bg-light' : '' ?>" 
                                 data-price="<?= $displayPrice ?>"
                                 data-method-id="<?= $method['id'] ?>"
                                 data-free-from="<?= $method['free_from'] ?? 0 ?>">
                                <input class="form-check-input shipping-radio" 
                                       type="radio" 
                                       name="shipping_method" 
                                       id="shipping_<?= $method['id'] ?>" 
                                       value="<?= $method['id'] ?>"
                                       data-price="<?= $method['price'] ?>"
                                       data-free-from="<?= $method['free_from'] ?? 0 ?>"
                                       <?= ($form_data['shipping_method'] == $method['id']) ? 'checked' : '' ?>>
                                <label class="form-check-label w-100" for="shipping_<?= $method['id'] ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?= htmlspecialchars($method['name']) ?></strong>
                                            <small class="text-muted d-block"><?= $daysText ?></small>
                                            <small class="text-muted d-block"><?= htmlspecialchars($method['description']) ?></small>
                                        </div>
                                        <span class="badge bg-<?= $badgeClass ?> ms-2 shipping-badge" id="badge-<?= $method['id'] ?>">
                                            <?= $badgeText ?>
                                        </span>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- ===== КОНЕЦ СПОСОБОВ ДОСТАВКИ ===== -->

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Способ оплаты</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($payment_methods as $method): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_method" 
                                       id="payment_<?= $method['id'] ?>" value="<?= $method['id'] ?>"
                                       <?= ($form_data['payment_method'] == $method['id']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="payment_<?= $method['id'] ?>">
                                    <?= htmlspecialchars($method['name']) ?>
                                    <?php if ($method['commission'] > 0): ?>
                                        <small class="text-muted">(комиссия <?= $method['commission'] ?>%)</small>
                                    <?php endif; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tag me-2"></i>Купон</h5>
                    </div>
                    <div class="card-body">
                        <div class="input-group">
                            <input type="text" class="form-control" name="coupon_code" 
                                   placeholder="Введите код купона" 
                                   value="<?= htmlspecialchars($form_data['coupon_code']) ?>">
                            <button class="btn btn-outline-primary" type="button" id="apply-coupon">Применить</button>
                        </div>
                        <div id="coupon-message" class="mt-2"></div>
                        <div id="coupon-details" class="mt-2 d-none"></div>
                        <div id="coupon-spinner" class="mt-2 d-none">
                            <i class="fas fa-spinner fa-spin"></i> Проверка купона...
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-comment me-2"></i>Комментарий к заказу</h5>
                    </div>
                    <div class="card-body">
                        <textarea class="form-control" name="notes" rows="3" 
                                  placeholder="Дополнительная информация по заказу"><?= htmlspecialchars($form_data['notes']) ?></textarea>
                    </div>
                </div>

                <button type="submit" name="checkout" class="btn btn-success btn-lg w-100 mb-4">
                    <i class="fas fa-check-circle me-2"></i>Подтвердить заказ
                </button>
            </form>
        </div>

        <!-- Сводка заказа -->
        <div class="col-md-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header">
                    <h5 class="mb-0">Ваш заказ</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($sellers as $seller_id => $seller_data): ?>
                        <div class="seller-group mb-3">
                            <h6 class="mb-2">
                                <i class="fas fa-store me-1 text-primary"></i>
                                <?= htmlspecialchars($seller_data['name'] ?? 'Продавец') ?>
                            </h6>
                            <?php foreach ($seller_data['items'] as $item): ?>
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>
                                        <?= htmlspecialchars($item['brand'] . ' ' . $item['name']) ?>
                                        <span class="text-muted">x<?= $item['quantity'] ?></span>
                                    </span>
                                    <span><?= formatPrice($item['subtotal']) ?></span>
                                </div>
                            <?php endforeach; ?>
                            <div class="border-top pt-1 mt-1">
                                <div class="d-flex justify-content-between">
                                    <strong>Итого:</strong>
                                    <strong><?= formatPrice($seller_data['subtotal']) ?></strong>
                                </div>
                            </div>
                        </div>
                        <?php if (next($sellers) !== false): ?>
                            <hr>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <hr>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Товары:</span>
                        <span id="items-total" data-raw-price="<?= $total_price ?>"><?= formatPrice($total_price) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Доставка:</span>
                        <span class="shipping-price" id="shipping-price-display">Бесплатно</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 coupon-row" style="display: none;">
                        <span>Скидка по купону:</span>
                        <span class="coupon-discount text-danger">0</span>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <h5 class="mb-0">Итого:</h5>
                        <h5 class="text-primary mb-0" id="final-total"><?= formatPrice($total_price) ?></h5>
                    </div>

                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-info-circle"></i>
                        Нажимая "Подтвердить заказ", вы соглашаетесь с условиями обработки персональных данных
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Передаем сохраненный способ доставки из сессии в JavaScript
const preselectedShippingFromSession = <?= json_encode($_SESSION['selected_shipping_method'] ?? null) ?>;
const preselectedShippingPriceFromSession = <?= json_encode($_SESSION['selected_shipping_price'] ?? null) ?>;
const shippingMethodsData = <?= json_encode($shipping_methods) ?>;

document.addEventListener('DOMContentLoaded', function() {
    const shippingInputs = document.querySelectorAll('.shipping-radio');
    const shippingPriceSpan = document.getElementById('shipping-price-display');
    const finalTotalSpan = document.getElementById('final-total');
    const itemsTotalEl = document.getElementById('items-total');
    const couponRow = document.querySelector('.coupon-row');
    const couponDiscountSpan = document.querySelector('.coupon-discount');
    const applyCouponBtn = document.getElementById('apply-coupon');
    const couponInput = document.querySelector('input[name="coupon_code"]');
    
    const totalPrice = parseFloat(itemsTotalEl.dataset.rawPrice) || 0;
    let shippingPrice = 0;
    let couponDiscount = 0;
    
    // Форматирование цены
    function formatPrice(price) {
        return Math.round(price).toLocaleString('ru-RU') + ' руб.';
    }
    
    // Обновление итоговых сумм
    function updateTotals() {
        const finalTotal = totalPrice + shippingPrice - couponDiscount;
        
        // Обновляем отображение доставки
        if (shippingPriceSpan) {
            shippingPriceSpan.textContent = shippingPrice === 0 ? 'Бесплатно' : formatPrice(shippingPrice);
            shippingPriceSpan.className = shippingPrice === 0 ? 'shipping-price text-success' : 'shipping-price';
        }
        
        // Обновляем итог с анимацией
        if (finalTotalSpan) {
            finalTotalSpan.style.transform = 'scale(1.05)';
            finalTotalSpan.textContent = formatPrice(finalTotal);
            setTimeout(() => {
                finalTotalSpan.style.transform = 'scale(1)';
            }, 200);
        }
        
        // Показываем/скрываем скидку
        if (couponRow && couponDiscountSpan) {
            if (couponDiscount > 0) {
                couponRow.style.display = 'flex';
                couponDiscountSpan.textContent = '-' + formatPrice(couponDiscount);
            } else {
                couponRow.style.display = 'none';
            }
        }
    }
    
    // Визуальное выделение выбранного способа доставки
    function highlightSelectedShipping(selectedRadio) {
        document.querySelectorAll('.shipping-option').forEach(opt => {
            opt.classList.remove('border-primary', 'bg-light');
        });
        
        const selectedOption = selectedRadio.closest('.shipping-option');
        if (selectedOption) {
            selectedOption.classList.add('border-primary', 'bg-light');
        }
    }
    
    // Расчет цены доставки с учетом бесплатной доставки
    function calculateShippingPrice(methodId) {
        const method = shippingMethodsData.find(m => m.id == methodId);
        if (!method) return 0;
        
        if (method.free_from && totalPrice >= method.free_from) {
            return 0;
        }
        return method.price;
    }
    
    // Обновление бейджа цены доставки
    function updateShippingBadge(methodId, price) {
        const method = shippingMethodsData.find(m => m.id == methodId);
        if (!method) return;
        
        const badge = document.getElementById('badge-' + methodId);
        if (!badge) return;
        
        const isFree = price === 0 && method.free_from;
        badge.className = 'badge bg-' + (isFree ? 'success' : 'primary') + ' ms-2 shipping-badge';
        badge.textContent = isFree 
            ? 'Бесплатно (от ' + method.free_from.toLocaleString('ru-RU') + ' руб.)'
            : method.price.toLocaleString('ru-RU') + ' руб.';
    }
    
    // Восстановление выбора доставки из корзины или хранилищ
    function restoreShippingFromCart() {
        let restored = false;
        
        // Приоритет 0: Сессия PHP (самый надежный - сохранено при выборе в корзине)
        if (preselectedShippingFromSession) {
            const radio = document.getElementById('shipping_' + preselectedShippingFromSession);
            if (radio) {
                radio.checked = true;
                shippingPrice = calculateShippingPrice(preselectedShippingFromSession);
                
                highlightSelectedShipping(radio);
                updateTotals();
                updateShippingBadge(preselectedShippingFromSession, shippingPrice);
                restored = true;
                
                // Синхронизируем во все хранилища
                localStorage.setItem('selected_shipping_method', preselectedShippingFromSession);
                localStorage.setItem('selected_shipping_price', shippingPrice);
                sessionStorage.setItem('cart_shipping_method', preselectedShippingFromSession);
                sessionStorage.setItem('cart_shipping_price', shippingPrice);
            }
        }
        
        if (restored) return;
        
        // Приоритет 1: sessionStorage из корзины
        const cartMethod = sessionStorage.getItem('cart_shipping_method');
        const cartPrice = sessionStorage.getItem('cart_shipping_price');
        
        if (cartMethod) {
            const radio = document.getElementById('shipping_' + cartMethod);
            if (radio) {
                radio.checked = true;
                shippingPrice = calculateShippingPrice(cartMethod);
                
                highlightSelectedShipping(radio);
                updateTotals();
                updateShippingBadge(cartMethod, shippingPrice);
                restored = true;
                
                // Синхронизируем в localStorage
                localStorage.setItem('selected_shipping_method', cartMethod);
                localStorage.setItem('selected_shipping_price', shippingPrice);
            }
        }
        
        if (restored) return;
        
        // Приоритет 2: localStorage
        const savedMethod = localStorage.getItem('selected_shipping_method');
        const savedPrice = localStorage.getItem('selected_shipping_price');
        
        if (savedMethod) {
            const radio = document.getElementById('shipping_' + savedMethod);
            if (radio) {
                radio.checked = true;
                shippingPrice = calculateShippingPrice(savedMethod);
                
                highlightSelectedShipping(radio);
                updateTotals();
                updateShippingBadge(savedMethod, shippingPrice);
                restored = true;
                
                // Синхронизируем обратно в sessionStorage
                sessionStorage.setItem('cart_shipping_method', savedMethod);
                sessionStorage.setItem('cart_shipping_price', shippingPrice);
            }
        }
        
        if (restored) return;
        
        // По умолчанию выбираем первый способ (самовывоз)
        const defaultShipping = document.querySelector('.shipping-radio');
        if (defaultShipping) {
            defaultShipping.checked = true;
            shippingPrice = calculateShippingPrice(defaultShipping.value);
            highlightSelectedShipping(defaultShipping);
            updateTotals();
            updateShippingBadge(defaultShipping.value, shippingPrice);
            
            // Сохраняем значения по умолчанию
            const defaultId = defaultShipping.value;
            localStorage.setItem('selected_shipping_method', defaultId);
            localStorage.setItem('selected_shipping_price', shippingPrice);
            sessionStorage.setItem('cart_shipping_method', defaultId);
            sessionStorage.setItem('cart_shipping_price', shippingPrice);
        }
    }
    
    // Обработчик изменения способа доставки
    shippingInputs.forEach(input => {
        input.addEventListener('change', function() {
            shippingPrice = calculateShippingPrice(this.value);
            
            highlightSelectedShipping(this);
            updateTotals();
            updateShippingBadge(this.value, shippingPrice);
            
            // Синхронизируем изменения во все хранилища
            localStorage.setItem('selected_shipping_method', this.value);
            localStorage.setItem('selected_shipping_price', shippingPrice);
            sessionStorage.setItem('cart_shipping_method', this.value);
            sessionStorage.setItem('cart_shipping_price', shippingPrice);
        });
    });
    
    // Обработка клика по всему блоку для удобства
    document.querySelectorAll('.shipping-option').forEach(option => {
        option.addEventListener('click', function(e) {
            if (e.target.type !== 'radio' && !e.target.closest('.form-check-input')) {
                const radio = this.querySelector('.shipping-radio');
                if (radio && !radio.checked) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                }
            }
        });
    });
    
    // Обработчик применения купона
    if (applyCouponBtn) {
        applyCouponBtn.addEventListener('click', function() {
            const couponCode = couponInput.value.trim();
            if (!couponCode) {
                const msgEl = document.getElementById('coupon-message');
                if (msgEl) msgEl.innerHTML = '<span class="text-warning">⚠ Введите код купона</span>';
                return;
            }
            
            const spinner = document.getElementById('coupon-spinner');
            const msgEl = document.getElementById('coupon-message');
            const detailsEl = document.getElementById('coupon-details');
            
            if (spinner) spinner.classList.remove('d-none');
            applyCouponBtn.disabled = true;
            if (msgEl) msgEl.innerHTML = '';
            if (detailsEl) detailsEl.classList.add('d-none');
            
            // Отправляем AJAX-запрос для проверки купона
            fetch('/mobileshop/check_coupon.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'coupon_code=' + encodeURIComponent(couponCode) + '&total_price=' + totalPrice
            })
            .then(response => response.json())
            .then(data => {
                if (spinner) spinner.classList.add('d-none');
                applyCouponBtn.disabled = false;
                
                if (data.success) {
                    // Рассчитываем скидку
                    let discount = 0;
                    if (data.coupon.discount_type === 'percent') {
                        discount = totalPrice * data.coupon.discount_value / 100;
                        if (data.coupon.max_discount_amount && discount > data.coupon.max_discount_amount) {
                            discount = data.coupon.max_discount_amount;
                        }
                    } else {
                        discount = Math.min(data.coupon.discount_value, totalPrice);
                    }
                    couponDiscount = discount;
                    
                    // Показываем информацию о купоне
                    let discountText = data.coupon.discount_type === 'percent' 
                        ? data.coupon.discount_value + '%' 
                        : formatPrice(data.coupon.discount_value);
                    
                    let detailsText = 'Купон применен: скидка ' + discountText;
                    if (data.coupon.max_discount_amount) {
                        detailsText += ', макс. скидка ' + formatPrice(data.coupon.max_discount_amount);
                    }
                    
                    if (detailsEl) {
                        detailsEl.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i>' + detailsText;
                        detailsEl.classList.remove('d-none');
                    }
                    if (msgEl) {
                        msgEl.innerHTML = '<span class="text-success">✓ Купон успешно применен</span>';
                    }
                    
                    // Обновляем итоговую сумму
                    updateTotals();
                    
                    // Добавляем скрытое поле с ID купона для отправки формы
                    let hiddenInput = document.getElementById('applied_coupon_id');
                    if (!hiddenInput) {
                        hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'applied_coupon_id';
                        hiddenInput.id = 'applied_coupon_id';
                        document.getElementById('checkout-form').appendChild(hiddenInput);
                    }
                    hiddenInput.value = data.coupon.id;
                    
                } else {
                    couponDiscount = 0;
                    if (msgEl) {
                        msgEl.innerHTML = '<span class="text-danger">✗ ' + data.message + '</span>';
                    }
                    if (detailsEl) detailsEl.classList.add('d-none');
                    
                    // Удаляем скрытое поле, если оно есть
                    let hiddenInput = document.getElementById('applied_coupon_id');
                    if (hiddenInput) {
                        hiddenInput.remove();
                    }
                    
                    updateTotals();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (spinner) spinner.classList.add('d-none');
                applyCouponBtn.disabled = false;
                if (msgEl) {
                    msgEl.innerHTML = '<span class="text-danger">✗ Ошибка при проверке купона</span>';
                }
            });
        });
    }
    
    // Синхронизация при возврате на страницу (кнопка "назад")
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            restoreShippingFromCart();
        }
    });
    
    // Инициализация при загрузке
    restoreShippingFromCart();
});
</script>

<style>
.checkout-container .card {
    margin-bottom: 1rem;
}
.checkout-container .form-label {
    font-weight: 500;
}
.sticky-top {
    z-index: 100;
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

.seller-group {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
}
</style>

<?php require_once __DIR__ . '/inc/footer.php'; ?>