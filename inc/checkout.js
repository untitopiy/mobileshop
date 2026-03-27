
document.addEventListener('DOMContentLoaded', function() {
    // Получаем ссылки на элементы DOM
    const shippingInputs = document.querySelectorAll('input[name="shipping_method"]');
    const shippingPriceSpan = document.querySelector('.shipping-price');
    const finalTotalSpan = document.getElementById('final-total');
    const couponRow = document.querySelector('.coupon-row');
    const couponDiscountSpan = document.querySelector('.coupon-discount');
    const applyCouponBtn = document.getElementById('apply-coupon');
    const couponInput = document.getElementById('coupon_code');
    const couponMessage = document.getElementById('coupon-message');
    const couponDetails = document.getElementById('coupon-details');
    const couponSpinner = document.getElementById('coupon-spinner');
    
    // Получаем данные из скрытых полей
    const totalPriceElement = document.getElementById('total-price-data');
    const shippingMethodsElement = document.getElementById('shipping-methods-data');
    
    if (!totalPriceElement || !shippingMethodsElement) {
        console.error('Не найдены скрытые поля с данными');
        return;
    }
    
    const totalPrice = parseFloat(totalPriceElement.value);
    let shippingMethods = [];
    
    try {
        shippingMethods = JSON.parse(shippingMethodsElement.value);
    } catch (e) {
        console.error('Ошибка парсинга способов доставки:', e);
    }
    
    let shippingPrice = 0;
    let couponDiscount = 0;
    let appliedCoupon = {
        id: null,
        code: '',
        type: '',
        value: 0,
        max_discount: null,
        min_order: null
    };
    
    // Обновление стоимости доставки
    function updateTotals() {
        const finalTotal = totalPrice + shippingPrice - couponDiscount;
        if (finalTotalSpan) {
            finalTotalSpan.textContent = formatPrice(finalTotal);
        }
        
        if (couponDiscount > 0) {
            if (couponRow) couponRow.style.display = 'flex';
            if (couponDiscountSpan) couponDiscountSpan.textContent = '-' + formatPrice(couponDiscount);
        } else {
            if (couponRow) couponRow.style.display = 'none';
        }
    }
    
    // Форматирование цены
    function formatPrice(price) {
        return price.toLocaleString('ru-RU') + ' руб.';
    }
    
    // Показать сообщение о купоне
    function showCouponMessage(message, type) {
        if (!couponMessage) return;
        const alertClass = type === 'warning' ? 'text-warning' : 'text-danger';
        couponMessage.innerHTML = '<span class="' + alertClass + '">⚠ ' + message + '</span>';
    }
    
    // Обработчик изменения способа доставки
    if (shippingInputs.length > 0) {
        shippingInputs.forEach(input => {
            input.addEventListener('change', function() {
                const methodId = parseInt(this.value);
                const method = shippingMethods.find(m => m.id === methodId);
                
                if (method) {
                    if (method.free_from && totalPrice >= method.free_from) {
                        shippingPrice = 0;
                        if (shippingPriceSpan) shippingPriceSpan.textContent = 'Бесплатно';
                    } else {
                        shippingPrice = method.price;
                        if (shippingPriceSpan) shippingPriceSpan.textContent = formatPrice(method.price);
                    }
                    updateTotals();
                }
            });
        });
    }
    
    // Обработчик применения купона
    if (applyCouponBtn && couponInput) {
        applyCouponBtn.addEventListener('click', function() {
            const couponCode = couponInput.value.trim();
            if (!couponCode) {
                showCouponMessage('Введите код купона', 'warning');
                return;
            }
            
            // Показываем спиннер
            if (couponSpinner) couponSpinner.classList.remove('d-none');
            applyCouponBtn.disabled = true;
            if (couponMessage) couponMessage.innerHTML = '';
            if (couponDetails) couponDetails.classList.add('d-none');
            
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
                if (couponSpinner) couponSpinner.classList.add('d-none');
                applyCouponBtn.disabled = false;
                
                if (data.success) {
                    // Купон действителен
                    appliedCoupon = {
                        id: data.coupon.id,
                        code: data.coupon.code,
                        type: data.coupon.discount_type,
                        value: parseFloat(data.coupon.discount_value),
                        max_discount: data.coupon.max_discount_amount ? parseFloat(data.coupon.max_discount_amount) : null,
                        min_order: data.coupon.min_order_amount ? parseFloat(data.coupon.min_order_amount) : null
                    };
                    
                    // Рассчитываем скидку
                    if (appliedCoupon.type === 'percent') {
                        couponDiscount = totalPrice * appliedCoupon.value / 100;
                        if (appliedCoupon.max_discount && couponDiscount > appliedCoupon.max_discount) {
                            couponDiscount = appliedCoupon.max_discount;
                        }
                    } else {
                        couponDiscount = Math.min(appliedCoupon.value, totalPrice);
                    }
                    
                    // Показываем информацию о купоне
                    let discountText = appliedCoupon.type === 'percent' 
                        ? appliedCoupon.value + '%' 
                        : formatPrice(appliedCoupon.value);
                    
                    let detailsText = 'Купон применен: скидка ' + discountText;
                    if (appliedCoupon.max_discount) {
                        detailsText += ', макс. скидка ' + formatPrice(appliedCoupon.max_discount);
                    }
                    
                    if (couponDetails) {
                        couponDetails.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i>' + detailsText;
                        couponDetails.classList.remove('d-none');
                    }
                    if (couponMessage) {
                        couponMessage.innerHTML = '<span class="text-success">✓ Купон успешно применен</span>';
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
                    hiddenInput.value = appliedCoupon.id;
                    
                } else {
                    // Купон недействителен
                    appliedCoupon = { id: null, code: '', type: '', value: 0 };
                    couponDiscount = 0;
                    if (couponMessage) {
                        couponMessage.innerHTML = '<span class="text-danger">✗ ' + data.message + '</span>';
                    }
                    if (couponDetails) couponDetails.classList.add('d-none');
                    
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
                if (couponSpinner) couponSpinner.classList.add('d-none');
                applyCouponBtn.disabled = false;
                if (couponMessage) {
                    couponMessage.innerHTML = '<span class="text-danger">✗ Ошибка при проверке купона</span>';
                }
            });
        });
    }
    
    // Обновление итоговой суммы при загрузке
    updateTotals();
});