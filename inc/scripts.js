// inc/scripts.js - Полный файл со всеми скриптами

// Плавная прокрутка к секциям
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        // Проверяем, что href начинается с # и не является пустым
        const href = this.getAttribute('href');
        if (href && href.startsWith('#')) {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }
    });
});

// Hover эффекты для карточек (добавление класса при наведении)
document.querySelectorAll('.card').forEach(card => {
    card.addEventListener('mouseenter', () => {
        card.classList.add('hovered');
    });
    card.addEventListener('mouseleave', () => {
        card.classList.remove('hovered');
    });
});

// Инициализация слайдера Bootstrap
const carousel = document.querySelector('.carousel');
if (carousel) {
    new bootstrap.Carousel(carousel, {
        interval: 5000,
        ride: 'carousel'
    });
}

function scrollToSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        section.scrollIntoView({ behavior: 'smooth' });
    }
}

window.addEventListener('scroll', () => {
    const scrollUpButton = document.querySelector('.scroll-up');
    if (scrollUpButton) {
        if (window.scrollY > 300) {
            scrollUpButton.classList.add('show');
        } else {
            scrollUpButton.classList.remove('show');
        }
    }
});

// ========== ПЕРЕМЕННЫЕ ДЛЯ СРАВНЕНИЯ ==========
let compareIds = [];

// Загрузка ID сравнения (с приоритетом на БД для авторизованных)
function loadCompareIds() {
    fetch('/mobileshop/pages/update_compare.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'get_ids=1'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.compare_ids) {
            compareIds = data.compare_ids;
            localStorage.setItem('compareIds', JSON.stringify(compareIds));
            updateCompareCount();
            loadCompareStatus();
            console.log('📦 Загружены ID сравнения:', compareIds);
        } else {
            // Fallback на localStorage
            const saved = localStorage.getItem('compareIds');
            if (saved) {
                compareIds = JSON.parse(saved);
                updateCompareCount();
                loadCompareStatus();
            }
        }
    })
    .catch(error => {
        console.error('Ошибка загрузки сравнения:', error);
        const saved = localStorage.getItem('compareIds');
        if (saved) {
            compareIds = JSON.parse(saved);
            updateCompareCount();
            loadCompareStatus();
        }
    });
}

// Сохранение ID сравнения
function saveCompareIds() {
    localStorage.setItem('compareIds', JSON.stringify(compareIds));
    updateCompareCount();
    // Синхронизируем с сервером
    fetch('/mobileshop/pages/update_compare.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ids=' + JSON.stringify(compareIds)
    }).catch(e => console.error('Ошибка синхронизации:', e));
}

// Обновление счетчика сравнения
function updateCompareCount() {
    const countBadge = document.getElementById('compare-count');
    if (countBadge) {
        countBadge.textContent = compareIds.length;
    }
}

// Загрузка статуса сравнения для всех товаров на странице (подсветка иконок)
function loadCompareStatus() {
    compareIds.forEach(productId => {
        const compareIcon = document.getElementById(`compare-icon-${productId}`);
        if (compareIcon) {
            compareIcon.style.color = '#ffc107';
            compareIcon.classList.add('active');
        }
    });
}

// Функция добавления/удаления из сравнения
function toggleCompare(productId) {
    const index = compareIds.indexOf(Number(productId));
    let isAdded = false;
    
    if (index === -1) {
        if (compareIds.length >= 4) {
            showToast('Можно сравнивать не более 4 товаров', 'warning');
            return;
        }
        isAdded = true;
    }
    
    // Отправляем запрос на toggle
    fetch('/mobileshop/pages/update_compare.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'toggle=' + productId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            compareIds = data.compare_ids;
            localStorage.setItem('compareIds', JSON.stringify(compareIds));
            updateCompareCount();
            showToast(data.message, data.action === 'added' ? 'success' : 'info');
            
            const compareIcon = document.getElementById(`compare-icon-${productId}`);
            if (compareIcon) {
                if (data.action === 'added') {
                    compareIcon.style.color = '#ffc107';
                    compareIcon.classList.add('active');
                } else {
                    compareIcon.style.color = '';
                    compareIcon.classList.remove('active');
                }
            }
        } else {
            showToast(data.message || 'Ошибка', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Ошибка соединения', 'error');
    });
}

// Сохранение ID сравнения в сессию (для совместимости)
function updateCompareSession() {
    fetch('/mobileshop/pages/update_compare.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ids=' + JSON.stringify(compareIds)
    }).catch(e => console.error('Ошибка синхронизации сессии:', e));
}

// Переход на страницу сравнения
function goToCompare() {
    if (compareIds.length > 0) {
        window.location.href = '/mobileshop/pages/compare.php?ids=' + compareIds.join(',');
    } else {
        window.location.href = '/mobileshop/pages/compare.php';
    }
}

// Загрузка ID сравнения из URL при загрузке страницы
function loadCompareFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    const ids = urlParams.get('ids');
    if (ids) {
        const newIds = ids.split(',').map(Number);
        let changed = false;
        newIds.forEach(id => {
            if (!compareIds.includes(id) && compareIds.length < 4) {
                compareIds.push(id);
                changed = true;
            }
        });
        if (changed) {
            saveCompareIds();
            loadCompareStatus();
        }
    }
}

// ========== ФУНКЦИИ ДЛЯ СТРАНИЦЫ СРАВНЕНИЯ ==========

// Функция для удаления из сравнения на странице сравнения
function removeFromCompare(productId) {
    if (confirm('Удалить товар из сравнения?')) {
        fetch('/mobileshop/pages/update_compare.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'remove=' + productId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                compareIds = data.compare_ids;
                localStorage.setItem('compareIds', JSON.stringify(compareIds));
                updateCompareCount();
                // Обновляем иконку на главной странице (если есть)
                const compareIcon = document.getElementById(`compare-icon-${productId}`);
                if (compareIcon) {
                    compareIcon.style.color = '';
                    compareIcon.classList.remove('active');
                }
                // Перезагружаем страницу с новыми параметрами
                if (compareIds.length > 0) {
                    window.location.href = '/mobileshop/pages/compare.php?ids=' + compareIds.join(',');
                } else {
                    window.location.href = '/mobileshop/pages/compare.php';
                }
            } else {
                alert(data.message || 'Ошибка при удалении');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ошибка при удалении');
        });
    }
}

// Функция для очистки всего сравнения
function clearAllCompare() {
    if (confirm('Очистить все товары из сравнения?')) {
        fetch('/mobileshop/pages/update_compare.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'clear_all=1'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                compareIds = [];
                localStorage.removeItem('compareIds');
                updateCompareCount();
                // Очищаем все иконки на главной странице
                document.querySelectorAll('[id^="compare-icon-"]').forEach(icon => {
                    icon.style.color = '';
                    icon.classList.remove('active');
                });
                showToast('Сравнение очищено', 'success');
                window.location.href = '/mobileshop/pages/compare.php';
            } else {
                alert(data.message || 'Ошибка при очистке');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ошибка при очистке');
        });
    }
}

// ========== ФУНКЦИИ ДЛЯ ИЗБРАННОГО ==========

// Загрузка статуса избранного для всех товаров на странице
function loadWishlistStatus() {
    fetch('/mobileshop/pages/wishlist.php?ajax_list=1')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.wishlist) {
                data.wishlist.forEach(productId => {
                    const icon = document.getElementById(`wishlist-icon-${productId}`);
                    if (icon) {
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                        icon.style.color = '#dc3545';
                    }
                });
            }
        })
        .catch(error => console.error('Error:', error));
}

// Функция добавления/удаления из избранного
function toggleWishlist(productId) {
    // Проверяем авторизацию через AJAX
    fetch('/mobileshop/pages/wishlist.php?check_auth=1')
        .then(response => response.json())
        .then(data => {
            if (!data.authenticated) {
                showToast('Необходимо авторизоваться', 'warning');
                setTimeout(() => {
                    window.location.href = '/mobileshop/pages/auth.php';
                }, 1500);
                return;
            }
            
            // Отправляем запрос на добавление/удаление
            fetch('/mobileshop/pages/wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax_add_wishlist=1&product_id=' + productId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Обновляем иконку сразу
                    const icon = document.getElementById(`wishlist-icon-${productId}`);
                    if (icon) {
                        if (data.action === 'added') {
                            icon.classList.remove('far');
                            icon.classList.add('fas');
                            icon.style.color = '#dc3545';
                        } else {
                            icon.classList.remove('fas');
                            icon.classList.add('far');
                            icon.style.color = '';
                        }
                    }
                    showToast(data.message, 'success');
                    updateWishlistCount();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Ошибка при добавлении в избранное', 'error');
            });
        });
}

// Обновление счетчика избранного
function updateWishlistCount() {
    fetch('/mobileshop/pages/wishlist.php?ajax_count=1')
        .then(response => response.json())
        .then(data => {
            const badge = document.getElementById('wishlist-count');
            if (badge) {
                badge.textContent = data.count;
            }
        })
        .catch(error => console.error('Error:', error));
}

// ========== 🔥 ИСПРАВЛЕННЫЕ ФУНКЦИИ ДЛЯ КОРЗИНЫ ==========

/**
 * Добавление товара в корзину
 */
function addToCart(productId, variationId = null, quantity = 1) {
    console.log('🛒 addToCart:', {productId, variationId, quantity});
    
    // Формируем данные
    const formData = new URLSearchParams();
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    if (variationId && variationId !== 'null' && variationId !== '') {
        formData.append('variation_id', variationId);
    }
    
    // Отправляем запрос
    fetch('/mobileshop/pages/cart/Api_Cart.php?action=add', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData.toString()
    })
    .then(response => {
        console.log('📡 Response status:', response.status);
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        console.log('✅ Response data:', data);
        
        if (data.success) {
            // Обновляем счётчик
            updateCartCountDisplay(data.count);
            showToast(data.message, 'success');
        } else {
            showToast(data.message || 'Ошибка добавления', 'error');
        }
    })
    .catch(error => {
        console.error('❌ Error:', error);
        showToast('Ошибка соединения с сервером', 'error');
    });
}

/**
 * Обновление отображения счётчика корзины
 */
function updateCartCountDisplay(count) {
    console.log('🔢 Updating cart count:', count);
    
    // Ищем все возможные элементы
    const selectors = [
        '#cart-count',
        '#cart-badge', 
        '.cart-count',
        '.header-cart-count'
    ];
    
    selectors.forEach(selector => {
        const elements = document.querySelectorAll(selector);
        elements.forEach(el => {
            el.textContent = count;
            // Добавляем анимацию
            el.style.transform = 'scale(1.3)';
            setTimeout(() => el.style.transform = 'scale(1)', 200);
        });
    });
}

/**
 * Получение количества товаров в корзине
 */
function updateCartCount() {
    fetch('/mobileshop/pages/cart/Api_Cart.php?action=count')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.count !== undefined) {
            updateCartCountDisplay(data.count);
        }
    })
    .catch(error => console.error('Error updating cart count:', error));
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    updateCartCount();
});

// Общая функция для действий (используется в index.php)
function toggleAction(type, productId) {
    if (type === 'wishlist') {
        toggleWishlist(productId);
    } else if (type === 'compare') {
        toggleCompare(productId);
    }
}

// Сброс фильтров
function resetFilters() {
    const priceMin = document.getElementById('price-min');
    const priceMax = document.getElementById('price-max');
    if (priceMin) priceMin.value = '';
    if (priceMax) priceMax.value = '';
    showToast('Фильтры сброшены', 'info');
}

// ========== ФУНКЦИИ ДЛЯ ОТЗЫВОВ ==========

function toggleLike(reviewId, button) {
    fetch(`pages/like_review.php?review_id=${reviewId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const likesCount = button.querySelector('.likes-count');
                const currentLikes = parseInt(likesCount.textContent);
                
                if (data.action === 'liked') {
                    likesCount.textContent = currentLikes + 1;
                    button.classList.add('liked');
                } else {
                    likesCount.textContent = currentLikes - 1;
                    button.classList.remove('liked');
                }
            } else {
                alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ошибка при оценке отзыва');
        });
}

// Инициализация лайков дизлайков для отзывов
function initReviewLikes() {
    document.querySelectorAll('.btn-like-review, .btn-dislike-review').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const reviewId = this.dataset.reviewId;
            const action = this.dataset.action;
            
            const container = this.closest('.review-actions') || this.parentElement;
            const likesCountElement = container.querySelector('.likes-count');
            const dislikesCountElement = container.querySelector('.dislikes-count');
            
            const originalHTML = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            this.disabled = true;
            
            fetch(`pages/like_review.php?review_id=${reviewId}&action=${action}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        if (likesCountElement) likesCountElement.textContent = data.likes_count;
                        if (dislikesCountElement) dislikesCountElement.textContent = data.dislikes_count;

                        container.querySelectorAll('.btn-like-review, .btn-dislike-review').forEach(btn => {
                            btn.classList.remove('active');
                        });

                        if (data.action !== 'removed') {
                            this.classList.add('active');
                        }

                        const msg = data.action === 'disliked' ? 'Дизлайк засчитан' : 'Спасибо за оценку!';
                        showToast(msg, 'success');
                    } else {
                        showToast('Ошибка: ' + (data.message || 'Неизвестная ошибка'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Ошибка соединения с сервером', 'error');
                })
                .finally(() => {
                    this.innerHTML = originalHTML;
                    this.disabled = false;
                });
        });
    });
}

// Инициализация звездного рейтинга в форме
function initRatingInputs() {
    document.querySelectorAll('.rating-input').forEach(container => {
        const stars = container.querySelectorAll('input[type="radio"]');
        const labels = container.querySelectorAll('label');
        
        stars.forEach((star, index) => {
            star.addEventListener('change', function() {
                labels.forEach(label => label.style.color = '#ddd');
                for (let i = 0; i <= index; i++) {
                    labels[i].style.color = '#ffc107';
                }
            });
            
            labels[index].addEventListener('mouseenter', function() {
                for (let i = 0; i <= index; i++) {
                    labels[i].style.color = '#ffc107';
                }
                for (let i = index + 1; i < labels.length; i++) {
                    labels[i].style.color = '#ddd';
                }
            });
        });
        
        container.addEventListener('mouseleave', function() {
            const checkedStar = container.querySelector('input:checked');
            if (checkedStar) {
                const index = Array.from(stars).indexOf(checkedStar);
                for (let i = 0; i <= index; i++) {
                    labels[i].style.color = '#ffc107';
                }
                for (let i = index + 1; i < labels.length; i++) {
                    labels[i].style.color = '#ddd';
                }
            } else {
                labels.forEach(label => label.style.color = '#ddd');
            }
        });
    });
}

// Инициализация галереи изображений
function initImageGallery() {
    document.querySelectorAll('.thumbnail-gallery img').forEach((thumb, index) => {
        thumb.setAttribute('tabindex', '0');
        thumb.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (typeof changeImage === 'function') {
                    changeImage(this);
                }
            }
        });
        
        thumb.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowRight') {
                e.preventDefault();
                const next = this.parentElement.nextElementSibling;
                if (next && next.querySelector('img')) {
                    next.querySelector('img').focus();
                }
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                const prev = this.parentElement.previousElementSibling;
                if (prev && prev.querySelector('img')) {
                    prev.querySelector('img').focus();
                }
            }
        });
    });
}

// ========== ФУНКЦИЯ ДЛЯ УВЕДОМЛЕНИЙ (TOAST) ==========

function showToast(message, type = 'success') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.position = 'fixed';
        toastContainer.style.bottom = '20px';
        toastContainer.style.right = '20px';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `custom-toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    toast.style.backgroundColor = colors[type] || colors.info;
    toast.style.color = type === 'warning' ? '#333' : 'white';
    toast.style.padding = '12px 20px';
    toast.style.borderRadius = '8px';
    toast.style.marginBottom = '10px';
    toast.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
    toast.style.animation = 'slideIn 0.3s ease';
    toast.style.cursor = 'pointer';
    
    toast.addEventListener('click', () => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    });
    
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }
    }, 3000);
}

// ========== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ==========

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function initFormConfirmations() {
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', function(e) {
            const message = this.getAttribute('data-confirm');
            if (message && !confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

function removeFromWishlist(productId) {
    if (confirm('Удалить товар из избранного?')) {
        fetch('/mobileshop/pages/wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'ajax_add_wishlist=1&product_id=' + productId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Ошибка при удалении');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ошибка при удалении');
        });
    }
}

function clearAllWishlist() {
    if (confirm('Вы уверены, что хотите очистить всё избранное?')) {
        const removeButtons = document.querySelectorAll('.remove-from-wishlist');
        let count = removeButtons.length;
        let completed = 0;
        
        if (count === 0) {
            location.reload();
            return;
        }
        
        removeButtons.forEach(btn => {
            const productId = btn.dataset.productId;
            fetch('/mobileshop/pages/wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax_add_wishlist=1&product_id=' + productId
            })
            .then(response => response.json())
            .then(data => {
                completed++;
                if (completed === count) {
                    location.reload();
                }
            })
            .catch(error => {
                completed++;
                if (completed === count) {
                    location.reload();
                }
            });
        });
    }
}

// ========== ЖИВОЙ ПОИСК (AJAX) ==========
function initLiveSearch() {
    const searchInput = document.getElementById('global-search');
    const searchResults = document.getElementById('search-results');

    if (!searchInput || !searchResults) return;

    searchInput.addEventListener('input', function() {
        const query = this.value.trim();

        if (query.length >= 2) {
            fetch('search_ajax.php?q=' + encodeURIComponent(query))
                .then(response => {
                    if (!response.ok) throw new Error('Ошибка сети');
                    return response.text();
                })
                .then(data => {
                    searchResults.innerHTML = data;
                    searchResults.style.display = 'block';
                })
                .catch(error => {
                    console.error('Ошибка поиска:', error);
                });
        } else {
            searchResults.style.display = 'none';
        }
    });

    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });
}

// ========== СИНХРОНИЗАЦИЯ СРАВНЕНИЯ ПРИ ЗАГРУЗКЕ ==========
function syncCompareOnLoad() {
    const localIds = localStorage.getItem('compareIds');
    const localCompareIds = localIds ? JSON.parse(localIds) : [];
    
    if (localCompareIds.length > 0 && window.location.pathname.includes('compare.php') && !window.location.search.includes('ids=')) {
        window.location.href = '/mobileshop/pages/compare.php?ids=' + localCompareIds.join(',');
    }
}

// ========== ИНИЦИАЛИЗАЦИЯ ПРИ ЗАГРУЗКЕ ==========

document.addEventListener('DOMContentLoaded', function() {
    // Инициализация отзывов
    initReviewLikes();
    initRatingInputs();
    initImageGallery();
    initFormConfirmations();
    initLiveSearch();
    
    // Инициализация избранного и сравнения
    loadCompareIds();
    loadCompareStatus();
    loadWishlistStatus();
    updateWishlistCount();
    updateCartCount();
    loadCompareFromUrl();
    
    // Синхронизация для страницы сравнения
    syncCompareOnLoad();
    
    // Каталог-меню
    const trigger = document.getElementById('catalog-trigger');
    const menu = document.getElementById('catalog-menu');
    if (trigger && menu) {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            menu.classList.toggle('active');
        });
        document.addEventListener('click', function(e) {
            if (!menu.contains(e.target) && e.target !== trigger) {
                menu.classList.remove('active');
            }
        });
    }
    
    console.log('✅ Скрипты загружены, ID сравнения:', compareIds);
});

// Добавляем стили для анимации уведомлений
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    .custom-toast {
        font-family: inherit;
        font-size: 14px;
        min-width: 200px;
        max-width: 300px;
        word-wrap: break-word;
    }
    .custom-toast .toast-content {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .custom-toast .toast-content i {
        font-size: 18px;
    }
`;
document.head.appendChild(style);