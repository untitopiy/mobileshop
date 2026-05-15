<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

// Конфигурация SMTP — вынесите в отдельный config.php
$config = require __DIR__ . '/../config/mail.php';

function sendNewsletter($db, $subject, $body, $recipient_type = 'all') {
    // SQL-запросы (ваши корректны)
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

    // Проверяем, есть ли получатели
    if ($result->num_rows === 0) {
        return "Нет получателей для выбранного фильтра.";
    }

    $mail = new PHPMailer(true);
    $errors = [];
    $sent = 0;

    try {
        // Настройка SMTP
        $mail->isSMTP();
        $mail->Host = $config['host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // константа вместо строки
        $mail->Port = 587;
        $mail->setFrom($config['username'], 'Магазин');
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->isHTML(true);
        $mail->Subject = $subject;

        // ВАЖНО: отключаем keep-alive для Gmail при массовой рассылке
        $mail->SMTPKeepAlive = false;

        foreach ($result as $user) {
            try {
                $mail->Body = str_replace(
                    '{{full_name}}', 
                    htmlspecialchars($user['full_name'] ?? 'Пользователь'), 
                    $body
                );
                
                $mail->addAddress($user['email'], $user['full_name'] ?? '');
                
                if (!$mail->send()) {
                    $errors[] = "Не отправлено: {$user['email']} — {$mail->ErrorInfo}";
                } else {
                    $sent++;
                }
                
                $mail->clearAddresses();
                
                // Небольшая задержка, чтобы не спамить Gmail
                usleep(100000); // 0.1 секунды
                
            } catch (Exception $e) {
                $errors[] = "Исключение для {$user['email']}: " . $e->getMessage();
                $mail->clearAddresses();
                continue; // продолжаем со следующим пользователем
            }
        }

        $mail->smtpClose();

        if (empty($errors)) {
            return "Новость успешно разослана {$sent} пользователям!";
        } else {
            return "Отправлено: {$sent}. Ошибок: " . count($errors) . ".<br>" . implode("<br>", $errors);
        }

    } catch (Exception $e) {
        return "Критическая ошибка SMTP: " . $e->getMessage();
    }
}