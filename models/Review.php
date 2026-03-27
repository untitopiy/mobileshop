<?php
// models/Review.php

class Review {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Получение среднего рейтинга и количества отзывов для товара
     */
    public function getAverageRating($product_id) {
        $query = $this->db->prepare("
            SELECT 
                COALESCE(AVG(rating), 0) as avg_rating,
                COUNT(*) as total_reviews,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1
            FROM reviews 
            WHERE product_id = ? AND status = 'approved'
        ");
        $query->bind_param('i', $product_id);
        $query->execute();
        $result = $query->get_result()->fetch_assoc();
        $query->close();
        
        // Устанавливаем значения по умолчанию, если нет отзывов
        if (!$result['total_reviews']) {
            return [
                'avg_rating' => 0,
                'total_reviews' => 0,
                'rating_5' => 0,
                'rating_4' => 0,
                'rating_3' => 0,
                'rating_2' => 0,
                'rating_1' => 0
            ];
        }
        
        return $result;
    }

    /**
     * Получение отзывов для товара с информацией о лайках
     */
    public function getProductReviews($product_id, $current_user_id = null) {
        $sql = "
            SELECT 
                r.*, 
                u.login, 
                u.full_name,
                u.photo,
                COALESCE(r.likes_count, 0) as likes_count,
                COALESCE(r.dislikes_count, 0) as dislikes_count,
                " . ($current_user_id ? "EXISTS(
                    SELECT 1 FROM review_helpful 
                    WHERE review_id = r.id AND user_id = ? AND is_helpful = 1
                ) as is_liked_by_user" : "0 as is_liked_by_user") . ",
                " . ($current_user_id ? "EXISTS(
                    SELECT 1 FROM review_helpful 
                    WHERE review_id = r.id AND user_id = ? AND is_helpful = 0
                ) as is_disliked_by_user" : "0 as is_disliked_by_user") . "
            FROM reviews r 
            LEFT JOIN users u ON r.user_id = u.id 
            WHERE r.product_id = ? AND r.status = 'approved' 
            ORDER BY r.created_at DESC
        ";
        
        if ($current_user_id) {
            $query = $this->db->prepare($sql);
            $query->bind_param('iii', $current_user_id, $current_user_id, $product_id);
        } else {
            $query = $this->db->prepare($sql);
            $query->bind_param('i', $product_id);
        }
        
        $query->execute();
        $result = $query->get_result();
        $reviews = [];
        
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
        
        $query->close();
        return $reviews;
    }

    /**
     * Проверяет, оставлял ли пользователь отзыв на товар
     */
    public function hasUserReviewed($product_id, $user_id) {
        $query = $this->db->prepare("
            SELECT id FROM reviews 
            WHERE product_id = ? AND user_id = ?
        ");
        $query->bind_param('ii', $product_id, $user_id);
        $query->execute();
        $result = $query->get_result();
        $exists = $result->num_rows > 0;
        $query->close();
        
        return $exists;
    }

    /**
     * Добавление нового отзыва
     */
    public function addReview($data) {
        // Проверяем, был ли товар куплен пользователем
        $is_verified = 0;
        if (isset($data['order_item_id']) && $data['order_item_id']) {
            $is_verified = 1;
        }
        
        $query = $this->db->prepare("
            INSERT INTO reviews 
            (user_id, product_id, order_item_id, rating, title, comment, pros, cons, is_verified_purchase, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $query->bind_param(
            'iiiissssi',
            $data['user_id'],
            $data['product_id'],
            $data['order_item_id'] ?? null,
            $data['rating'],
            $data['title'],
            $data['comment'],
            $data['pros'] ?? null,
            $data['cons'] ?? null,
            $is_verified
        );
        
        $result = $query->execute();
        $query->close();
        
        return $result;
    }

    /**
     * Переключение лайка (добавить/удалить)
     */
    public function toggleLike($review_id, $user_id) {
        // Проверяем, есть ли уже оценка
        $checkQuery = $this->db->prepare("
            SELECT id, is_helpful FROM review_helpful 
            WHERE review_id = ? AND user_id = ?
        ");
        $checkQuery->bind_param('ii', $review_id, $user_id);
        $checkQuery->execute();
        $result = $checkQuery->get_result();
        $existing = $result->fetch_assoc();
        $checkQuery->close();
        
        if ($existing) {
            if ($existing['is_helpful'] == 1) {
                // Удаляем лайк
                $deleteQuery = $this->db->prepare("
                    DELETE FROM review_helpful 
                    WHERE id = ?
                ");
                $deleteQuery->bind_param('i', $existing['id']);
                $deleteQuery->execute();
                $deleteQuery->close();
                
                // Обновляем счетчик
                $this->updateLikesCount($review_id, -1);
                return 'unliked';
            } else {
                // Меняем дизлайк на лайк
                $updateQuery = $this->db->prepare("
                    UPDATE review_helpful 
                    SET is_helpful = 1 
                    WHERE id = ?
                ");
                $updateQuery->bind_param('i', $existing['id']);
                $updateQuery->execute();
                $updateQuery->close();
                
                $this->updateLikesDislikes($review_id);
                return 'changed_to_like';
            }
        } else {
            // Добавляем лайк
            $insertQuery = $this->db->prepare("
                INSERT INTO review_helpful (review_id, user_id, is_helpful, created_at) 
                VALUES (?, ?, 1, NOW())
            ");
            $insertQuery->bind_param('ii', $review_id, $user_id);
            $insertQuery->execute();
            $insertQuery->close();
            
            $this->updateLikesCount($review_id, 1);
            return 'liked';
        }
    }
    
    /**
     * Переключение дизлайка (добавить/удалить)
     */
    public function toggleDislike($review_id, $user_id) {
        // Проверяем, есть ли уже оценка
        $checkQuery = $this->db->prepare("
            SELECT id, is_helpful FROM review_helpful 
            WHERE review_id = ? AND user_id = ?
        ");
        $checkQuery->bind_param('ii', $review_id, $user_id);
        $checkQuery->execute();
        $result = $checkQuery->get_result();
        $existing = $result->fetch_assoc();
        $checkQuery->close();
        
        if ($existing) {
            if ($existing['is_helpful'] == 0) {
                // Удаляем дизлайк
                $deleteQuery = $this->db->prepare("
                    DELETE FROM review_helpful 
                    WHERE id = ?
                ");
                $deleteQuery->bind_param('i', $existing['id']);
                $deleteQuery->execute();
                $deleteQuery->close();
                
                $this->updateDislikesCount($review_id, -1);
                return 'undisliked';
            } else {
                // Меняем лайк на дизлайк
                $updateQuery = $this->db->prepare("
                    UPDATE review_helpful 
                    SET is_helpful = 0 
                    WHERE id = ?
                ");
                $updateQuery->bind_param('i', $existing['id']);
                $updateQuery->execute();
                $updateQuery->close();
                
                $this->updateLikesDislikes($review_id);
                return 'changed_to_dislike';
            }
        } else {
            // Добавляем дизлайк
            $insertQuery = $this->db->prepare("
                INSERT INTO review_helpful (review_id, user_id, is_helpful, created_at) 
                VALUES (?, ?, 0, NOW())
            ");
            $insertQuery->bind_param('ii', $review_id, $user_id);
            $insertQuery->execute();
            $insertQuery->close();
            
            $this->updateDislikesCount($review_id, 1);
            return 'disliked';
        }
    }

    /**
     * Обновить счетчик лайков
     */
    private function updateLikesCount($review_id, $change) {
        $query = $this->db->prepare("
            UPDATE reviews 
            SET likes_count = likes_count + ? 
            WHERE id = ?
        ");
        $query->bind_param('ii', $change, $review_id);
        $query->execute();
        $query->close();
    }
    
    /**
     * Обновить счетчик дизлайков
     */
    private function updateDislikesCount($review_id, $change) {
        $query = $this->db->prepare("
            UPDATE reviews 
            SET dislikes_count = dislikes_count + ? 
            WHERE id = ?
        ");
        $query->bind_param('ii', $change, $review_id);
        $query->execute();
        $query->close();
    }
    
    /**
     * Обновить оба счетчика после изменения типа оценки
     */
    private function updateLikesDislikes($review_id) {
        $query = $this->db->prepare("
            UPDATE reviews r
            SET 
                likes_count = (SELECT COUNT(*) FROM review_helpful WHERE review_id = r.id AND is_helpful = 1),
                dislikes_count = (SELECT COUNT(*) FROM review_helpful WHERE review_id = r.id AND is_helpful = 0)
            WHERE r.id = ?
        ");
        $query->bind_param('i', $review_id);
        $query->execute();
        $query->close();
    }

    /**
     * Получение количества лайков для отзыва
     */
    public function getLikesCount($review_id) {
        $query = $this->db->prepare("
            SELECT likes_count 
            FROM reviews 
            WHERE id = ?
        ");
        $query->bind_param('i', $review_id);
        $query->execute();
        $result = $query->get_result()->fetch_assoc();
        $query->close();
        
        return $result['likes_count'] ?? 0;
    }

    /**
     * Проверяет, лайкнул ли пользователь отзыв
     */
    public function isLikedByUser($review_id, $user_id) {
        $query = $this->db->prepare("
            SELECT id FROM review_helpful 
            WHERE review_id = ? AND user_id = ? AND is_helpful = 1
        ");
        $query->bind_param('ii', $review_id, $user_id);
        $query->execute();
        $result = $query->get_result();
        $exists = $result->num_rows > 0;
        $query->close();
        
        return $exists;
    }
    
    /**
     * Проверяет, дизлайкнул ли пользователь отзыв
     */
    public function isDislikedByUser($review_id, $user_id) {
        $query = $this->db->prepare("
            SELECT id FROM review_helpful 
            WHERE review_id = ? AND user_id = ? AND is_helpful = 0
        ");
        $query->bind_param('ii', $review_id, $user_id);
        $query->execute();
        $result = $query->get_result();
        $exists = $result->num_rows > 0;
        $query->close();
        
        return $exists;
    }
}
?>