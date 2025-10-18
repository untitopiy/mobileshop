<?php
class Review {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Получение среднего рейтинга и количества отзывов для смартфона
     */
    public function getAverageRating($smartphone_id) {
        $query = $this->db->prepare("
            SELECT 
                AVG(rating) as avg_rating,
                COUNT(*) as total_reviews,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1
            FROM reviews 
            WHERE smartphone_id = ? AND is_approved = 1
        ");
        $query->bind_param('i', $smartphone_id);
        $query->execute();
        return $query->get_result()->fetch_assoc();
    }

    /**
     * Получение отзывов для смартфона
     */
    public function getSmartphoneReviews($smartphone_id) {
        $query = $this->db->prepare("
            SELECT r.*, u.login, u.full_name 
            FROM reviews r 
            LEFT JOIN users u ON r.user_id = u.id 
            WHERE r.smartphone_id = ? AND r.is_approved = 1 
            ORDER BY r.created_at DESC
        ");
        $query->bind_param('i', $smartphone_id);
        $query->execute();
        return $query->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Проверяет, оставлял ли пользователь отзыв на товар
     */
    public function hasUserReviewed($smartphone_id, $user_id) {
        $query = $this->db->prepare("
            SELECT id FROM reviews 
            WHERE smartphone_id = ? AND user_id = ?
        ");
        $query->bind_param('ii', $smartphone_id, $user_id);
        $query->execute();
        $result = $query->get_result();
        
        return $result->num_rows > 0;
    }

    /**
     * Добавление нового отзыва
     */
    public function addReview($data) {
        $query = $this->db->prepare("
            INSERT INTO reviews 
            (user_id, smartphone_id, rating, title, comment, pros, cons, is_verified_purchase, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        // Исправляем названия полей согласно вашей таблице
        $query->bind_param(
            'iiissssi',
            $data['user_id'],
            $data['smartphone_id'],
            $data['rating'],
            $data['title'],
            $data['comment'],
            $data['pros'],    // pros
            $data['cons'],    // cons
            $data['is_verified_purchase']
        );
        
        return $query->execute();
    }

    /**
     * Увеличение счетчика лайков
     */
    public function incrementLikes($review_id) {
        $query = $this->db->prepare("
            UPDATE reviews 
            SET likes_count = likes_count + 1 
            WHERE id = ?
        ");
        $query->bind_param('i', $review_id);
        return $query->execute();
    }

    // Добавьте эти методы в класс Review если хотите расширенную статистику
public function getChatStats($user_id = null) {
    if ($user_id) {
        $query = $this->db->prepare("SELECT COUNT(*) as total_chats FROM chat_logs WHERE user_id = ?");
        $query->bind_param('i', $user_id);
    } else {
        $query = $this->db->prepare("SELECT COUNT(*) as total_chats FROM chat_logs");
    }
    
    $query->execute();
    return $query->get_result()->fetch_assoc();
}
}
?>