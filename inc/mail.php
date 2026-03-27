<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php'; // PHPMailer

function sendNewsletter($db, $subject, $body, $recipient_type = 'all') {
    // Формируем SQL по типу получателей
    switch ($recipient_type) {
        case 'with_orders':
            $sql = "SELECT DISTINCT u.email, u.full_name 
                    FROM users u 
                    JOIN orders o ON o.user_id = u.id 
                    WHERE u.is_admin = 0";
            break;
        case 'no_orders':
            $sql = "SELECT u.email, u.full_name 
                    FROM users u 
                    LEFT JOIN orders o ON o.user_id = u.id 
                    WHERE u.is_admin = 0 AND o.id IS NULL";
            break;
        case 'active_recent':
            $sql = "SELECT u.email, u.full_name 
                    FROM users u 
                    WHERE u.is_admin = 0 AND u.last_login >= NOW() - INTERVAL 30 DAY";
            break;
        case 'subscribed':
            $sql = "SELECT u.email, u.full_name 
                    FROM users u 
                    WHERE u.is_admin = 0 AND u.is_subscribed = 1";
            break;
        case 'all':
        default:
            $sql = "SELECT u.email, u.full_name 
                    FROM users u 
                    WHERE u.is_admin = 0";
            break;
    }

    $result = $db->query($sql);
    if (!$result) return "Ошибка базы данных: " . $db->error;

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'mobileshopyou84@gmail.com';
        $mail->Password = 'pljm jyam lfqf bgyb'; // пароль приложения
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->setFrom('mobileshopyou84@gmail.com', 'Магазин');
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = $subject;

        foreach ($result as $user) {
            $mail->Body = str_replace('{{full_name}}', htmlspecialchars($user['full_name']), $body);
            $mail->addAddress($user['email'], $user['full_name']);
            $mail->send();
            $mail->clearAddresses();
        }

        return "Новость успешно разослана выбранным пользователям!";
    } catch (Exception $e) {
        return "Ошибка при отправке: {$mail->ErrorInfo}";
    }
}
