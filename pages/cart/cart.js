/**
 * mobileshop/pages/cart/cart.js
 * JavaScript для работы с корзиной
 */

const CartAPI = {
    baseUrl: window.BASE_URL + 'pages/cart/Api_Cart.php',
    
    getCsrfToken() {
        const metaToken = document.querySelector('meta[name="csrf-token"]');
        if (metaToken) return metaToken.content;
        const inputToken = document.querySelector('input[name="csrf_token"]');
        if (inputToken) return inputToken.value;
        return '';
    },
    
    async request(action, data = {}, method = 'POST') {
        const url = method === 'GET' 
            ? `${this.baseUrl}?action=${action}` 
            : this.baseUrl;
            
        const options = {
            method: method,
            headers: {
                'X-CSRF-TOKEN': this.getCsrfToken()
            }
        };
        
        if (method === 'POST') {
            const formData = new URLSearchParams();
            formData.append('action', action);
            
            Object.keys(data).forEach(key => {
                if (Array.isArray(data[key]) || typeof data[key] === 'object') {
                    formData.append(key, JSON.stringify(data[key]));
                } else {
                    formData.append(key, data[key]);
                }
            });
            
            options.body = formData.toString();
            options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }
        
        try {
            const response = await fetch(url, options);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },
    
    add(productId, quantity = 1, variationId = null) {
        return this.request('add', { 
            product_id: productId, 
            quantity: quantity, 
            variation_id: variationId 
        });
    },
    
    remove(cartKey) {
        return this.request('remove', { cart_key: cartKey });
    },
    
    update(cartKey, quantity) {
        const updates = {};
        updates[cartKey] = quantity;
        return this.request('update', { updates });
    },
    
    saveShipping(methodId, price) {
        return this.request('save_shipping', { 
            shipping_method: methodId, 
            shipping_price: price 
        });
    },
    
    getCount() {
        return this.request('count', {}, 'GET');
    },
    
    getCart() {
        return this.request('get', {}, 'GET');
    }
};

// ========== UI ОБРАБОТЧИКИ ==========

document.addEventListener('DOMContentLoaded', function() {
    initQuantityButtons();
    initRemoveButtons();
    initShippingMethods();
    
    // Обновляем счётчик при загрузке
    CartAPI.getCount().then(data => {
        if (data.success) {
            updateCartBadge(data.count);
        }
    }).catch(err => console.error('Error loading cart count:', err));
});

function initQuantityButtons() {
    // Уменьшение
    document.querySelectorAll('.quantity-decrease').forEach(btn => {
        btn.removeEventListener('click', handleDecrease);
        btn.addEventListener('click', handleDecrease);
    });
    
    // Увеличение
    document.querySelectorAll('.quantity-increase').forEach(btn => {
        btn.removeEventListener('click', handleIncrease);
        btn.addEventListener('click', handleIncrease);
    });
    
    // Прямой ввод
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.removeEventListener('change', handleQuantityChange);
        input.addEventListener('change', handleQuantityChange);
    });
}

function handleDecrease(e) {
    e.preventDefault();
    const input = this.parentNode.querySelector('.quantity-input');
    const val = parseInt(input.value);
    if (val > 1) {
        input.value = val - 1;
        updateCartItem(input.dataset.cartKey, val - 1);
    }
}

function handleIncrease(e) {
    e.preventDefault();
    const input = this.parentNode.querySelector('.quantity-input');
    const val = parseInt(input.value);
    const max = parseInt(input.max);
    if (val < max) {
        input.value = val + 1;
        updateCartItem(input.dataset.cartKey, val + 1);
    }
}

function handleQuantityChange(e) {
    const val = parseInt(this.value);
    const max = parseInt(this.max);
    const min = parseInt(this.min);
    
    let newVal = val;
    if (isNaN(val)) newVal = min;
    if (val > max) newVal = max;
    if (val < min) newVal = min;
    
    this.value = newVal;
    updateCartItem(this.dataset.cartKey, newVal);
}

function initRemoveButtons() {
    document.querySelectorAll('.btn-remove').forEach(btn => {
        btn.removeEventListener('click', handleRemove);
        btn.addEventListener('click', handleRemove);
    });
}

async function handleRemove(e) {
    e.preventDefault();
    
    if (!confirm('Удалить товар из корзины?')) return;
    
    const key = this.dataset.cartKey;
    const row = this.closest('tr');
    
    // Блокируем кнопку на время запроса
    this.disabled = true;
    const originalHtml = this.innerHTML;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    try {
        const data = await CartAPI.remove(key);
        
        if (data.success) {
            // Анимация удаления строки
            if (row) {
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    updateTotals();
                    updateCartBadge(data.count);
                    
                    // Если корзина пуста, перезагружаем страницу
                    if (data.count === 0) {
                        location.reload();
                    }
                }, 300);
            } else {
                location.reload();
            }
        } else {
            showToast(data.message || 'Ошибка при удалении', 'error');
            this.innerHTML = originalHtml;
            this.disabled = false;
        }
    } catch (err) {
        console.error('Ошибка удаления:', err);
        showToast('Не удалось удалить товар', 'error');
        this.innerHTML = originalHtml;
        this.disabled = false;
    }
}

async function updateCartItem(key, quantity) {
    try {
        const data = await CartAPI.update(key, quantity);
        if (data.success) {
            updateTotals();
            updateCartBadge(data.count);
        } else {
            showToast(data.message || 'Ошибка обновления', 'error');
            // Перезагружаем страницу чтобы синхронизировать данные
            location.reload();
        }
    } catch (err) {
        console.error('Ошибка обновления:', err);
        showToast('Ошибка обновления количества', 'error');
        location.reload();
    }
}

function updateTotals() {
    let total = 0;
    let count = 0;
    
    document.querySelectorAll('tr[data-cart-key]').forEach(row => {
        const priceEl = row.querySelector('.item-price');
        const qtyInput = row.querySelector('.quantity-input');
        const subtotalEl = row.querySelector('.item-subtotal');
        
        if (!priceEl || !qtyInput || !subtotalEl) return;
        
        const price = parseFloat(priceEl.dataset.price);
        const qty = parseInt(qtyInput.value);
        const subtotal = price * qty;
        
        if (!isNaN(subtotal)) {
            subtotalEl.textContent = formatPrice(subtotal);
            total += subtotal;
            count++;
        }
    });
    
    const itemsTotalEl = document.getElementById('items-total');
    const finalTotalEl = document.getElementById('final-total');
    const totalCountEl = document.getElementById('total-count');
    
    if (itemsTotalEl) {
        itemsTotalEl.textContent = formatPrice(total);
        itemsTotalEl.dataset.rawPrice = total;
    }
    if (finalTotalEl) {
        // Пересчитываем с учетом текущей доставки
        const selectedShipping = document.querySelector('input[name="shipping_method_radio"]:checked');
        if (selectedShipping) {
            let price = parseFloat(selectedShipping.dataset.price) || 0;
            const freeFrom = parseFloat(selectedShipping.dataset.freeFrom) || 0;
            if (freeFrom > 0 && total >= freeFrom) {
                price = 0;
            }
            finalTotalEl.textContent = formatPrice(total + price);
        } else {
            finalTotalEl.textContent = formatPrice(total);
        }
    }
    if (totalCountEl) totalCountEl.textContent = count;
    
    // Обновляем бейджи доставки в зависимости от новой суммы
    updateShippingBadges(total);
}

function updateShippingBadges(total) {
    // Курьер по Минску (бесплатно от 500)
    const courierRadio = document.getElementById('shipping_2');
    if (courierRadio) {
        const badge = document.getElementById('badge-2');
        const freeFrom = 500;
        if (total >= freeFrom) {
            badge.classList.remove('bg-primary');
            badge.classList.add('bg-success');
            badge.textContent = 'Бесплатно (от 500 руб.)';
            courierRadio.closest('.shipping-option').dataset.price = '0';
        } else {
            badge.classList.remove('bg-success');
            badge.classList.add('bg-primary');
            badge.textContent = '10 руб.';
            courierRadio.closest('.shipping-option').dataset.price = '10';
        }
    }
    
    // Европочта (бесплатно от 300)
    const europostRadio = document.getElementById('shipping_4');
    if (europostRadio) {
        const badge = document.getElementById('badge-4');
        const freeFrom = 300;
        if (total >= freeFrom) {
            badge.classList.remove('bg-primary');
            badge.classList.add('bg-success');
            badge.textContent = 'Бесплатно (от 300 руб.)';
            europostRadio.closest('.shipping-option').dataset.price = '0';
        } else {
            badge.classList.remove('bg-success');
            badge.classList.add('bg-primary');
            badge.textContent = '12 руб.';
            europostRadio.closest('.shipping-option').dataset.price = '12';
        }
    }
    
    // Обновляем отображение если выбран один из этих методов
    const selected = document.querySelector('input[name="shipping_method_radio"]:checked');
    if (selected) {
        const selectedId = selected.value;
        if ((selectedId === '2' && total >= 500) || (selectedId === '4' && total >= 300)) {
            document.getElementById('shipping-price-display').textContent = 'Бесплатно';
            document.getElementById('shipping-price-display').classList.add('text-success');
        } else {
            const price = parseFloat(selected.dataset.price) || 0;
            document.getElementById('shipping-price-display').textContent = formatPrice(price);
            document.getElementById('shipping-price-display').classList.remove('text-success');
        }
    }
}

function updateCartBadge(count) {
    // Обновляем на странице корзины
    const badge = document.getElementById('cart-badge');
    if (badge) {
        badge.textContent = count + ' ' + declension(count, ['товар', 'товара', 'товаров']);
    }
    
    // Обновляем счётчик в шапке сайта
    const headerCount = document.getElementById('cart-count');
    if (headerCount) {
        headerCount.textContent = count;
    }
}

function formatPrice(price) {
    return Math.round(price).toLocaleString('ru-RU') + ' руб.';
}

function declension(number, titles) {
    const cases = [2, 0, 1, 1, 1, 2];
    return titles[
        (number % 100 > 4 && number % 100 < 20) 
            ? 2 
            : cases[(number % 10 < 5) ? number % 10 : 5]
    ];
}

function showToast(message, type = 'success') {
    // Удаляем старые тосты
    const oldToast = document.querySelector('.cart-toast');
    if (oldToast) oldToast.remove();
    
    const toast = document.createElement('div');
    toast.className = 'cart-toast';
    
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background-color: ${colors[type] || colors.success};
        color: ${type === 'warning' ? '#333' : 'white'};
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 10000;
        font-size: 14px;
        animation: slideInRight 0.3s ease;
        cursor: pointer;
    `;
    
    toast.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    toast.addEventListener('click', () => {
        toast.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    });
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }
    }, 3000);
}

// ========== ОБРАБОТКА СПОСОБОВ ДОСТАВКИ ==========

function initShippingMethods() {
    const shippingOptions = document.querySelectorAll('input[name="shipping_method_radio"]');
    const itemsTotalEl = document.getElementById('items-total');
    
    shippingOptions.forEach(radio => {
        radio.addEventListener('change', async function() {
            const basePrice = parseFloat(this.dataset.price) || 0;
            const freeFrom = parseFloat(this.dataset.freeFrom) || 0;
            const total = parseFloat(itemsTotalEl?.dataset.rawPrice) || 0;
            
            // Проверяем бесплатную доставку от суммы
            let finalPrice = basePrice;
            if (freeFrom > 0 && total >= freeFrom) {
                finalPrice = 0;
            }
            
            // Обновляем скрытые поля формы
            const methodInput = document.getElementById('selected-shipping-method');
            const priceInput = document.getElementById('selected-shipping-price');
            if (methodInput) methodInput.value = this.value;
            if (priceInput) priceInput.value = finalPrice;
            
            // Сохраняем в localStorage и sessionStorage
            localStorage.setItem('selected_shipping_method', this.value);
            localStorage.setItem('selected_shipping_price', finalPrice);
            sessionStorage.setItem('cart_shipping_method', this.value);
            sessionStorage.setItem('cart_shipping_price', finalPrice);
            
            // Отправляем на сервер через AJAX
            try {
                await CartAPI.saveShipping(this.value, finalPrice);
            } catch (err) {
                console.error('Ошибка сохранения способа доставки:', err);
            }
            
            // Обновляем отображение итоговой суммы
            updateTotalWithShipping(finalPrice);
            
            // Визуально выделяем выбранный пункт
            highlightSelectedShipping(this);
        });
    });
    
    // Обработка клика по всему блоку для удобства
    document.querySelectorAll('.shipping-option').forEach(option => {
        option.addEventListener('click', function(e) {
            if (e.target.type !== 'radio' && !e.target.closest('.form-check-input')) {
                const radio = this.querySelector('input[type="radio"]');
                if (radio && !radio.checked) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                }
            }
        });
    });
}

function highlightSelectedShipping(selectedRadio) {
    // Убираем выделение со всех пунктов
    document.querySelectorAll('.shipping-option').forEach(opt => {
        opt.classList.remove('border-primary', 'bg-light');
    });
    
    // Добавляем выделение выбранному
    const selectedOption = selectedRadio.closest('.shipping-option');
    if (selectedOption) {
        selectedOption.classList.add('border-primary', 'bg-light');
    }
}

function updateTotalWithShipping(shippingPrice) {
    const itemsTotalEl = document.getElementById('items-total');
    const finalTotalEl = document.getElementById('final-total');
    const shippingPriceEl = document.getElementById('shipping-price-display');
    
    if (!itemsTotalEl || !finalTotalEl) return;
    
    // Получаем сумму товаров
    const itemsTotal = parseFloat(itemsTotalEl.dataset.rawPrice) || 0;
    
    const finalTotal = itemsTotal + shippingPrice;
    
    // Обновляем отображение доставки
    if (shippingPriceEl) {
        shippingPriceEl.textContent = shippingPrice === 0 ? 'Бесплатно' : formatPrice(shippingPrice);
        shippingPriceEl.className = shippingPrice === 0 ? 'shipping-price text-success' : 'shipping-price';
    }
    
    // Обновляем итог с анимацией
    if (finalTotalEl) {
        finalTotalEl.style.transform = 'scale(1.05)';
        finalTotalEl.textContent = formatPrice(finalTotal);
        setTimeout(() => {
            finalTotalEl.style.transform = 'scale(1)';
        }, 200);
    }
}

// Добавляем стили для анимации
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

window.CartAPI = CartAPI;