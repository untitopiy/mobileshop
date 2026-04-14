// inc/scripts.js - Полный файл со всеми скриптами

// Глобальная конфигурация API
const API_CONFIG = {
    cartBaseUrl: window.BASE_URL + 'pages/cart/Api_Cart.php'
};

// Получение CSRF-токена
function getCsrfToken() {
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) return metaToken.content;
    const inputToken = document.querySelector('input[name="csrf_token"]');
    if (inputToken) return inputToken.value;
    return '';
}

// Плавная прокрутка к секциям
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
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

// Hover эффекты для карточек
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

// ===== ИСПРАВЛЕНИЕ: Инициализация из window.INITIAL_COMPARE_IDS (переданных PHP) =====
function initCompareIds() {
    // Приоритет 1: данные из PHP (через window.INITIAL_COMPARE_IDS)
    if (typeof window !== 'undefined' && window.INITIAL_COMPARE_IDS && Array.isArray(window.INITIAL_COMPARE_IDS)) {
        compareIds = window.INITIAL_COMPARE_IDS;
        // Синхронизируем с localStorage для консистентности
        localStorage.setItem('compareIds', JSON.stringify(compareIds));
        return;
    }
    
    // Приоритет 2: localStorage (fallback)
    const saved = localStorage.getItem('compareIds');
    if (saved) {
        try {
            const parsed = JSON.parse(saved);
            if (Array.isArray(parsed)) {
                compareIds = parsed;
                return;
            }
        } catch (e) {
            console.error('Error parsing compareIds from localStorage:', e);
        }
    }
    
    // Приоритет 3: пустой массив
    compareIds = [];
}

// Инициализируем немедленно при загрузке скрипта
initCompareIds();

// Загрузка ID сравнения из БД (только для синхронизации, не перезаписываем если пусто)
function loadCompareIds() {
    // Если у нас уже есть данные из PHP — не делаем лишний запрос сразу
    if (typeof window !== 'undefined' && window.INITIAL_COMPARE_IDS && window.INITIAL_COMPARE_IDS.length > 0) {
        // Проверим актуальность через 2 секунды (отложенная синхронизация)
        setTimeout(doLoadCompareIds, 2000);
        return;
    }
    
    doLoadCompareIds();
}

function doLoadCompareIds() {
    fetch(window.BASE_URL + 'pages/update_compare.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'get_ids=1'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.compare_ids && Array.isArray(data.compare_ids)) {
            // Обновляем только если сервер вернул данные
            if (data.compare_ids.length > 0) {
                compareIds = data.compare_ids;
                localStorage.setItem('compareIds', JSON.stringify(compareIds));
                updateCompareCount();
                loadCompareStatus();
                console.log('📦 Синхронизированы ID сравнения из БД:', compareIds);
            }
            // Если сервер вернул пусто, но у нас есть данные — сохраняем наши
        }
    })
    .catch(error => {
        console.error('Ошибка синхронизации сравнения:', error);
    });
}

// Сохранение ID сравнения
function saveCompareIds() {
    localStorage.setItem('compareIds', JSON.stringify(compareIds));
    updateCompareCount();
    fetch(window.BASE_URL + 'pages/update_compare.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ids=' + JSON.stringify(compareIds)
    }).catch(e => console.error('Ошибка синхронизации:', e));
}

// Обновление счетчика сравнения
function updateCompareCount() {
    const countBadge = document.getElementById('compare-count');
    if (countBadge) {
        const newCount = compareIds.length;
        const oldCount = parseInt(countBadge.textContent) || 0;
        
        countBadge.textContent = newCount;
        
        // Анимация только при изменении и не при первой загрузке
        if (oldCount !== newCount && oldCount !== 0) {
            countBadge.style.transform = 'scale(1.3)';
            setTimeout(() => countBadge.style.transform = 'scale(1)', 200);
        }
    }
}

// Загрузка статуса сравнения для всех товаров на странице
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
    fetch(window.BASE_URL + 'pages/update_compare.php', {
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

// Переход на страницу сравнения
function goToCompare() {
    if (compareIds.length > 0) {
        window.location.href = window.BASE_URL + 'pages/compare.php?ids=' + compareIds.join(',');
    } else {
        window.location.href = window.BASE_URL + 'pages/compare.php';
    }
}

// Загрузка ID сравнения из URL
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

function removeFromCompare(productId) {
    if (confirm('Удалить товар из сравнения?')) {
        fetch(window.BASE_URL + 'pages/update_compare.php', {
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
                const compareIcon = document.getElementById(`compare-icon-${productId}`);
                if (compareIcon) {
                    compareIcon.style.color = '';
                    compareIcon.classList.remove('active');
                }
                if (compareIds.length > 0) {
                    window.location.href = window.BASE_URL + 'pages/compare.php?ids=' + compareIds.join(',');
                } else {
                    window.location.href = window.BASE_URL + 'pages/compare.php';
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

function clearAllCompare() {
    if (confirm('Очистить все товары из сравнения?')) {
        fetch(window.BASE_URL + 'pages/update_compare.php', {
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
                document.querySelectorAll('[id^="compare-icon-"]').forEach(icon => {
                    icon.style.color = '';
                    icon.classList.remove('active');
                });
                showToast('Сравнение очищено', 'success');
                window.location.href = window.BASE_URL + 'pages/compare.php';
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

function loadWishlistStatus() {
    fetch(window.BASE_URL + 'pages/wishlist.php?ajax_list=1')
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

function toggleWishlist(productId) {
    fetch(window.BASE_URL + 'pages/wishlist.php?check_auth=1')
        .then(response => response.json())
        .then(data => {
            if (!data.authenticated) {
                showToast('Необходимо авторизоваться', 'warning');
                setTimeout(() => {
                    window.location.href = window.BASE_URL + 'pages/auth.php';
                }, 1500);
                return;
            }
            
            fetch(window.BASE_URL + 'pages/wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax_add_wishlist=1&product_id=' + productId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
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

function updateWishlistCount() {
    fetch(window.BASE_URL + 'pages/wishlist.php?ajax_count=1')
        .then(response => response.json())
        .then(data => {
            const badge = document.getElementById('wishlist-count');
            if (badge) {
                badge.textContent = data.count;
            }
        })
        .catch(error => console.error('Error:', error));
}

// ========== ФУНКЦИИ ДЛЯ КОРЗИНЫ ==========

function addToCart(productId, variationId = null, quantity = 1) {
    console.log('🛒 addToCart called:', {productId, variationId, quantity});
    
    const params = new URLSearchParams();
    params.append('action', 'add');
    params.append('product_id', productId);
    params.append('quantity', quantity);
    if (variationId && variationId !== 'null' && variationId !== '') {
        params.append('variation_id', variationId);
    }
    
    fetch(API_CONFIG.cartBaseUrl + '?action=add', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-TOKEN': getCsrfToken()
        },
        body: params.toString()
    })
    .then(response => {
        console.log('📡 Response status:', response.status);
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        console.log('✅ Response data:', data);
        
        if (data.success) {
            updateCartCountDisplay(data.count);
            showToast(data.message || 'Товар добавлен в корзину', 'success');
        } else {
            showToast(data.message || 'Ошибка при добавлении', 'error');
        }
    })
    .catch(error => {
        console.error('❌ Error:', error);
        showToast('Ошибка соединения с сервером', 'error');
    });
}

function updateCartCountDisplay(count) {
    console.log('🔢 Updating cart count:', count);
    
    const selectors = ['#cart-count', '#cart-badge', '.cart-count', '.header-cart-count'];
    
    selectors.forEach(selector => {
        const elements = document.querySelectorAll(selector);
        elements.forEach(el => {
            el.textContent = count;
            el.style.transform = 'scale(1.3)';
            setTimeout(() => el.style.transform = 'scale(1)', 200);
        });
    });
}

function updateCartCount() {
    fetch(API_CONFIG.cartBaseUrl + '?action=count', {
        headers: {
            'X-CSRF-TOKEN': getCsrfToken()
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.count !== undefined) {
            updateCartCountDisplay(data.count);
        }
    })
    .catch(error => console.error('Error updating cart count:', error));
}

// ========== ФУНКЦИЯ ДЛЯ УВЕДОМЛЕНИЙ (TOAST) ==========

function showToast(message, type = 'success') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 9999;';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    
    toast.style.cssText = `
        background-color: ${colors[type] || colors.success};
        color: ${type === 'warning' ? '#333' : 'white'};
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        animation: slideIn 0.3s ease;
        cursor: pointer;
    `;
    
    toast.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
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

// ========== ОБЩАЯ ФУНКЦИЯ ДЛЯ ДЕЙСТВИЙ ==========

function toggleAction(type, productId) {
    if (type === 'wishlist') {
        toggleWishlist(productId);
    } else if (type === 'compare') {
        toggleCompare(productId);
    }
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
        fetch(window.BASE_URL + 'pages/wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
            fetch(window.BASE_URL + 'pages/wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
            fetch(window.BASE_URL + 'search_ajax.php?q=' + encodeURIComponent(query))
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
    // Не перенаправляем если уже на compare.php
    if (window.location.pathname.includes('compare.php')) {
        return;
    }
}

// ========== ИНИЦИАЛИЗАЦИЯ ПРИ ЗАГРУЗКЕ ==========

document.addEventListener('DOMContentLoaded', function() {
    // ===== ИСПРАВЛЕНИЕ: Порядок инициализации =====
    
    // 1. Сначала обновляем счетчик сравнения (данные уже загружены из PHP)
    updateCompareCount();
    loadCompareStatus();
    
    // 2. Инициализируем корзину
    updateCartCount();
    
    // 3. Отложенная синхронизация с БД (не блокирует отображение)
    loadCompareIds();
    
    // 4. Остальные инициализации
    loadWishlistStatus();
    updateWishlistCount();
    loadCompareFromUrl();
    syncCompareOnLoad();
    
    // Инициализация отзывов
    initFormConfirmations();
    initLiveSearch();
    
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
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);