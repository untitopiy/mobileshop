<?php
// Функция для безопасного вывода данных
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Функция генерации случайного кода купона (например, для будущей интеграции)
function generateCouponCode($length = 8) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $coupon = '';
    for ($i = 0; $i < $length; $i++) {
        $coupon .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $coupon;
}