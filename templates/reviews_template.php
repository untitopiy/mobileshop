<?php
// templates/reviews_template.php
// Этот файл будет подключаться в product.php для отображения отзывов
?>

<!-- Форма добавления отзыва -->
<?php if($current_user_id && !$has_reviewed): ?>
<div class="review-form mb-5 p-4 border rounded">
    <h4 class="mb-4">Оставить отзыв</h4>
    <form method="POST" action="add_review.php">
        <input type="hidden" name="product_id" value="<?= $product_id ?>">
        <input type="hidden" name="user_id" value="<?= $current_user_id ?>">
        
        <!-- Оценка -->
        <div class="mb-3">
            <label class="form-label fw-bold">Ваша оценка *</label>
                <div class="rating-input">
                <?php for($i = 1; $i <= 5; $i++): ?>
                    <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" required>
                    <label for="star<?= $i ?>">★</label>
                <?php endfor; ?>
            </div>
        </div>
        
        <!-- Заголовок -->
        <div class="mb-3">
            <label for="title" class="form-label fw-bold">Заголовок отзыва *</label>
            <input type="text" class="form-control" id="title" name="title" 
                   required maxlength="200"
                   placeholder="Кратко опишите ваше впечатление">
        </div>
        
        <!-- Комментарий -->
        <div class="mb-3">
            <label for="comment" class="form-label fw-bold">Подробный отзыв *</label>
            <textarea class="form-control" id="comment" name="comment" rows="4" required 
                      placeholder="Расскажите о вашем опыте использования товара"></textarea>
        </div>
        
        <!-- Плюсы и минусы -->
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="pros" class="form-label fw-bold">Достоинства</label>
                    <textarea class="form-control" id="pros" name="pros" rows="3" 
                              placeholder="Что вам понравилось?"></textarea>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="cons" class="form-label fw-bold">Недостатки</label>
                    <textarea class="form-control" id="cons" name="cons" rows="3" 
                              placeholder="Что можно улучшить?"></textarea>
                </div>
            </div>
        </div>
        
        <!-- Подтверждение покупки -->
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_verified" name="is_verified" value="1">
            <label class="form-check-label" for="is_verified">
                Подтверждаю, что покупал этот товар
                <small class="text-muted d-block">(отметка появится рядом с отзывом)</small>
            </label>
        </div>
        
        <button type="submit" class="btn btn-primary px-4">Опубликовать отзыв</button>
    </form>
</div>
<?php elseif(!$current_user_id): ?>
    <div class="alert alert-info mb-4">
        <i class="fas fa-info-circle me-2"></i>
        <a href="pages/auth.php" class="alert-link">Войдите</a>, чтобы оставить отзыв
    </div>
<?php endif; ?>

<!-- Список отзывов -->
<div class="reviews-list">
    <h4 class="mb-4">Отзывы покупателей (<?= $average_rating['total_reviews'] ?? 0 ?>)</h4>
    
    <?php if(empty($reviews)): ?>
        <div class="text-center py-5 bg-light rounded">
            <i class="fas fa-star fa-3x text-muted mb-3"></i>
            <p class="text-muted mb-0">Пока нет отзывов на этот товар. Будьте первым!</p>
        </div>
    <?php else: ?>
        <?php foreach($reviews as $review): ?>
        <div class="review-item border rounded p-4 mb-3" id="review-<?= $review['id'] ?>">
            <!-- Шапка отзыва -->
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <div class="d-flex align-items-center mb-2">
                        <div class="reviewer-avatar me-2">
                            <?php if (!empty($review['user_photo'])): ?>
                                <img src="pages/photo/<?= htmlspecialchars($review['user_photo']) ?>" 
                                     alt="Avatar" class="rounded-circle" width="40" height="40">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" 
                                     style="width: 40px; height: 40px;">
                                    <?= strtoupper(substr($review['full_name'] ?: $review['login'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <strong><?= htmlspecialchars($review['full_name'] ?: $review['login']) ?></strong>
                            <?php if($review['is_verified_purchase']): ?>
                                <span class="badge bg-success ms-2" title="Подтвержденная покупка">
                                    <i class="fas fa-check-circle"></i> Проверено
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="rating-stars small">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= $review['rating'] ? 'filled' : '' ?>">★</span>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <small class="text-muted" title="<?= date('d.m.Y H:i', strtotime($review['created_at'])) ?>">
                    <?= date('d.m.Y', strtotime($review['created_at'])) ?>
                </small>
            </div>
            
            <!-- Заголовок и текст отзыва -->
            <h6 class="fw-bold mb-2"><?= htmlspecialchars($review['title']) ?></h6>
            <p class="mb-3"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
            
            <!-- Плюсы и минусы -->
            <?php if(!empty($review['pros']) || !empty($review['cons'])): ?>
            <div class="row mt-2 mb-3">
                <?php if(!empty($review['pros'])): ?>
                <div class="col-md-6">
                    <div class="pros-box p-3 bg-success bg-opacity-10 rounded">
                        <strong class="text-success">
                            <i class="fas fa-plus-circle me-1"></i>Достоинства:
                        </strong>
                        <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($review['pros'])) ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($review['cons'])): ?>
                <div class="col-md-6">
                    <div class="cons-box p-3 bg-danger bg-opacity-10 rounded">
                        <strong class="text-danger">
                            <i class="fas fa-minus-circle me-1"></i>Недостатки:
                        </strong>
                        <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($review['cons'])) ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Блок полезности отзыва -->
            <div class="mt-3 d-flex align-items-center">
                <small class="text-muted me-3">Был ли этот отзыв полезен?</small>
                
                <?php if($current_user_id): ?>
                    <button class="btn btn-sm btn-outline-success me-2 btn-like-review <?= $review['user_liked'] ? 'active' : '' ?>" 
                            data-review-id="<?= $review['id'] ?>"
                            data-action="helpful">
                        <i class="fas fa-thumbs-up me-1"></i>
                        Да (<span class="likes-count"><?= $review['likes_count'] ?></span>)
                    </button>
                    
                    <button class="btn btn-sm btn-outline-danger btn-dislike-review <?= $review['user_disliked'] ? 'active' : '' ?>" 
                            data-review-id="<?= $review['id'] ?>"
                            data-action="not-helpful">
                        <i class="fas fa-thumbs-down me-1"></i>
                        Нет (<span class="dislikes-count"><?= $review['dislikes_count'] ?></span>)
                    </button>
                <?php else: ?>
                    <span class="me-2">
                        <i class="fas fa-thumbs-up text-muted"></i> <?= $review['likes_count'] ?>
                    </span>
                    <span>
                        <i class="fas fa-thumbs-down text-muted"></i> <?= $review['dislikes_count'] ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <!-- Ответ продавца (если есть) -->
            <?php if (!empty($review['seller_reply'])): ?>
            <div class="seller-reply mt-3 p-3 bg-light rounded">
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-store me-2 text-primary"></i>
                    <strong>Ответ продавца:</strong>
                </div>
                <p class="mb-0"><?= nl2br(htmlspecialchars($review['seller_reply'])) ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <!-- Пагинация для отзывов (если нужно) -->
        <?php if ($average_rating['total_reviews'] > 10): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center" id="reviews-pagination">
                <!-- JavaScript будет добавлять пагинацию -->
            </ul>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Toast для уведомлений (если еще не добавлен) -->
<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto">Уведомление</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastMessage">
            Сообщение
        </div>
    </div>
</div>

<style>
.rating-stars .star {
    color: #ddd;
    font-size: 1.2em;
}
.rating-stars .star.filled {
    color: #ffc107;
}
.rating-input {
    display: flex;
    flex-direction: row; /* Обычный порядок */
    gap: 5px;
}
/* Скрываем стандартные кружки */
.rating-input input {
    display: none;
}
/* Звезды по умолчанию */
.rating-input label {
    font-size: 30px;
    color: #ddd;
    cursor: pointer;
}

/* Закрашивание при наведении и выборе */
.rating-input:has(label:hover) label:not(:hover):not(:hover ~ label),
.rating-input label:hover,
.rating-input:has(input:checked) label:not(input:checked ~ label) {
    color: #ffca08;
}

.btn-like-review.active,
.btn-dislike-review.active {
    background-color: #0d6efd;
    color: white;
    border-color: #0d6efd;
}
.review-item {
    transition: all 0.2s;
}
.review-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
</style>