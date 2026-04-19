<?php
// Завершение контейнера (если открыт в других частях сайта)
?>
    </div> 
    <!-- 🔥 Bootstrap JS уже подключен в header.php, здесь не нужен дубль -->
    <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> -->
    
    <!-- Основные скрипты -->
    <script src="<?= $base_url; ?>inc/scripts.js"></script>

    <!-- 🔥 КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Гарантированная инициализация dropdown -->
    <script>
    (function() {
        'use strict';
        
        console.log('🔧 Footer: Проверка инициализации dropdown...');
        
        // Функция для инициализации всех dropdown
        function initAllDropdowns() {
            if (typeof bootstrap === 'undefined') {
                console.error('❌ Bootstrap не загружен в footer!');
                return;
            }
            
            var dropdownToggles = document.querySelectorAll('[data-bs-toggle="dropdown"]');
            console.log('📍 Footer: Найдено dropdown toggles:', dropdownToggles.length);
            
            var initialized = 0;
            dropdownToggles.forEach(function(toggle, index) {
                try {
                    // Проверяем, не инициализирован ли уже
                    var existing = bootstrap.Dropdown.getInstance(toggle);
                    if (existing) {
                        console.log('✅ Dropdown #' + index + ' уже инициализирован');
                        initialized++;
                        return;
                    }
                    
                    // Создаем новый экземпляр
                    new bootstrap.Dropdown(toggle, {
                        autoClose: true,
                        boundary: 'window'
                    });
                    initialized++;
                    console.log('✅ Dropdown #' + index + ' инициализирован в footer');
                } catch (error) {
                    console.error('❌ Ошибка инициализации dropdown #' + index + ':', error);
                }
            });
            
            console.log('🎉 Footer: Инициализировано dropdown: ' + initialized + '/' + dropdownToggles.length);
        }
        
        // Запускаем сразу
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAllDropdowns);
        } else {
            initAllDropdowns();
        }
        
        // И еще раз через 100мс и 500мс для гарантии
        setTimeout(initAllDropdowns, 100);
        setTimeout(initAllDropdowns, 500);
    })();
    </script>

    <div id="chatbot-widget">
    <div id="chatbot-toggle" class="chatbot-toggle">
        <i class="fas fa-robot"></i>
    </div>
    <div id="chatbot-container" class="chatbot-container">
        <div class="chatbot-header">
            <h6>Помощник магазина</h6>
            <button id="chatbot-close" class="chatbot-close">&times;</button>
        </div>
        <div id="chatbot-messages" class="chatbot-messages">
            <div class="bot-message">
                <div class="message-content">
                    Привет! Я ваш AI-помощник. Могу помочь с подбором техники, сравнением товаров, оформлением заказа, доставкой и другими вопросами. Чем могу помочь?
                </div>
                <div class="message-time"><?= date('H:i') ?></div>
            </div>
        </div>
        <div class="chatbot-input">
            <input type="text" id="chatbot-input" placeholder="Введите ваш вопрос..." maxlength="500">
            <button id="chatbot-send">➤</button>
        </div>
        <div class="chatbot-quick-actions">
            <button class="quick-action-btn" id="compare-start-btn">
                <i class="fas fa-balance-scale"></i> Сравнить товары
            </button>
            <button class="quick-action-btn" id="what-better-btn">
                <i class="fas fa-question"></i> Что лучше?
            </button>
            <button class="quick-action-btn" id="pick-phone-btn">
                <i class="fas fa-search"></i> Подобрать телефон
            </button>
        </div>
    </div>
</div>

<div id="compareMiniPanel" class="compare-mini-panel" style="display: none;">
    <div class="compare-mini-header">
        <span><i class="fas fa-balance-scale"></i> Сравнение товаров (<span id="mini-selected-count">0</span>/4)</span>
        <div class="compare-mini-actions">
            <button id="mini-clear-btn" class="mini-btn" title="Очистить все">
                <i class="fas fa-trash"></i>
            </button>
            <button id="mini-close-btn" class="mini-btn" title="Закрыть">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <div id="mini-products-list" class="mini-products-list">
        <div class="mini-empty">Нажмите на товары для добавления в сравнение</div>
    </div>
    <div class="compare-mini-footer">
        <button id="mini-compare-btn" class="mini-submit-btn" disabled>
            <i class="fas fa-exchange-alt"></i> Сравнить выбранные
        </button>
    </div>
</div>

<style>
#chatbot-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
}

.chatbot-toggle {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
}

.chatbot-toggle:hover {
    transform: scale(1.1);
}

.chatbot-container {
    position: absolute;
    bottom: 70px;
    right: 0;
    width: 350px;
    height: 550px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    display: none;
    flex-direction: column;
    overflow: hidden;
}

.chatbot-container.active {
    display: flex;
}

.chatbot-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chatbot-header h6 {
    margin: 0;
}

.chatbot-close {
    background: none;
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
}

.chatbot-messages {
    flex: 1;
    padding: 15px;
    overflow-y: auto;
    background: #f8f9fa;
}

.bot-message, .user-message {
    margin-bottom: 15px;
    display: flex;
    flex-direction: column;
}

.user-message {
    align-items: flex-end;
}

.message-content {
    padding: 10px 15px;
    border-radius: 18px;
    max-width: 80%;
    word-wrap: break-word;
    white-space: pre-line;
    background: white;
    border: 1px solid #e9ecef;
}

.user-message .message-content {
    background: #667eea;
    color: white;
}

.message-time {
    font-size: 10px;
    color: #6c757d;
    margin-top: 3px;
}

.user-message .message-time {
    text-align: right;
}

.chatbot-input {
    display: flex;
    padding: 15px;
    border-top: 1px solid #e9ecef;
    background: white;
    gap: 8px;
}

#chatbot-input {
    flex: 1;
    border: 1px solid #ddd;
    border-radius: 20px;
    padding: 10px 15px;
    outline: none;
}

#chatbot-input:focus {
    border-color: #667eea;
}

#chatbot-send {
    background: #667eea;
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    cursor: pointer;
}

.chatbot-quick-actions {
    padding: 10px;
    border-top: 1px solid #e9ecef;
    background: white;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: center;
}

.quick-action-btn {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 20px;
    padding: 5px 10px;
    font-size: 11px;
    cursor: pointer;
    color: #667eea;
    transition: all 0.3s ease;
}

.quick-action-btn:hover {
    background: #667eea;
    color: white;
    transform: translateY(-2px);
}

/* Мини-панель сравнения */
.compare-mini-panel {
    position: fixed;
    bottom: 100px;
    right: 90px;
    width: 300px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.25);
    z-index: 1001;
    border: 1px solid #e9ecef;
    animation: slideIn 0.3s ease;
}

.compare-mini-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 10px 12px;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.compare-mini-header span {
    font-size: 13px;
    font-weight: 500;
}

.compare-mini-actions {
    display: flex;
    gap: 8px;
}

.mini-btn {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    font-size: 12px;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.mini-btn:hover {
    background: rgba(255,255,255,0.4);
    transform: scale(1.05);
}

.mini-products-list {
    max-height: 200px;
    overflow-y: auto;
    padding: 8px;
    background: #f8f9fa;
}

.mini-empty {
    text-align: center;
    color: #999;
    font-size: 11px;
    padding: 15px;
}

.mini-product-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    margin-bottom: 6px;
    background: white;
    border-radius: 6px;
    border-left: 3px solid #667eea;
    font-size: 12px;
    animation: slideIn 0.2s ease;
}

.mini-product-item img {
    width: 35px;
    height: 35px;
    object-fit: contain;
}

.mini-product-info {
    flex: 1;
}

.mini-product-info h6 {
    margin: 0;
    font-size: 11px;
    font-weight: 600;
}

.mini-product-info p {
    margin: 0;
    font-size: 10px;
    color: #666;
}

.mini-remove {
    background: none;
    border: none;
    color: #dc3545;
    cursor: pointer;
    font-size: 12px;
    padding: 4px;
    transition: all 0.2s ease;
}

.mini-remove:hover {
    opacity: 0.7;
    transform: scale(1.1);
}

.compare-mini-footer {
    padding: 8px;
    border-top: 1px solid #e9ecef;
    background: white;
    border-radius: 0 0 12px 12px;
}

.mini-submit-btn {
    width: 100%;
    background: #667eea;
    color: white;
    border: none;
    padding: 8px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s ease;
}

.mini-submit-btn:hover:not(:disabled) {
    background: #5a6fd8;
    transform: translateY(-1px);
}

.mini-submit-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.product-card-compare-mode {
    cursor: pointer;
    transition: all 0.2s ease;
}

.product-card-compare-mode:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.highlight-for-compare {
    animation: pulse 0.3s ease;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); box-shadow: 0 0 0 2px #667eea; }
    100% { transform: scale(1); }
}

@keyframes typingPulse {
    0%, 100% { transform: translateY(0); opacity: 0.4; }
    50% { transform: translateY(-5px); opacity: 1; }
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>

<script>
// ========== ПЕРЕМЕННЫЕ ==========
let compareSelectedProducts = [];
let compareModeActive = false;

// ========== ФУНКЦИЯ ДЛЯ ОТПРАВКИ ЗАПРОСА НА СРАВНЕНИЕ В БОТ ==========
function sendCompareToBot() {
    if (compareSelectedProducts.length < 2) {
        showToast('Выберите минимум 2 товара для сравнения', 'warning');
        return;
    }
    
    // Показываем индикатор загрузки
    showToast('📊 Сохраняем товары и готовим сравнение...', 'info');
    
    // Сначала сохраняем товары на сервере
    fetch('http://localhost:5000/api/save-compare-products', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            session_id: '<?= session_id() ?>',
            products: compareSelectedProducts 
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Формируем сообщение для бота
            const productNames = compareSelectedProducts.map(p => p.name).join(' и ');
            const compareMessage = `сравни товары: ${productNames}`;
            
            // Отправляем сообщение в чат
            const chatbotInput = document.getElementById('chatbot-input');
            const chatbotSend = document.getElementById('chatbot-send');
            
            if (chatbotInput && chatbotSend) {
                chatbotInput.value = compareMessage;
                chatbotSend.click();
            }
            
            // Закрываем мини-панель и завершаем режим сравнения
            stopCompareMode();
            
            // Открываем чат, если он закрыт
            const chatbotContainer = document.getElementById('chatbot-container');
            if (chatbotContainer && !chatbotContainer.classList.contains('active')) {
                chatbotContainer.classList.add('active');
            }
        } else {
            showToast('Ошибка сохранения товаров: ' + (data.message || 'неизвестная ошибка'), 'error');
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showToast('Ошибка соединения с сервером', 'error');
    });
}

// ========== ФУНКЦИЯ ДЛЯ ЗАПРОСА "ЧТО ЛУЧШЕ?" ==========
function askWhatBetter() {
    const chatbotInput = document.getElementById('chatbot-input');
    const chatbotSend = document.getElementById('chatbot-send');
    
    if (chatbotInput && chatbotSend) {
        // Проверяем, есть ли "новые" не отправленные товары в мини-панели
        if (compareSelectedProducts.length >= 2) {
            showToast('💾 Сохраняем выбранные товары...', 'info');
            
            fetch('http://localhost:5000/api/save-compare-products', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    session_id: '<?= session_id() ?>',
                    products: compareSelectedProducts 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    chatbotInput.value = 'что лучше';
                    chatbotSend.click();
                    
                    // Открываем чат, если он закрыт
                    const chatbotContainer = document.getElementById('chatbot-container');
                    if (chatbotContainer && !chatbotContainer.classList.contains('active')) {
                        chatbotContainer.classList.add('active');
                    }
                } else {
                    showToast('Ошибка сохранения товаров', 'error');
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                showToast('Ошибка соединения', 'error');
            });
        } else {
            // Если панель пуста (мы уже сравнивали товары и она очистилась), 
            // просто отправляем сообщение боту. Сервер сам достанет 
            // товары из last_compared_products и выдаст совет!
            chatbotInput.value = 'что лучше';
            chatbotSend.click();
            
            const chatbotContainer = document.getElementById('chatbot-container');
            if (chatbotContainer && !chatbotContainer.classList.contains('active')) {
                chatbotContainer.classList.add('active');
            }
        }
    }
}

// ========== ФУНКЦИЯ ДЛЯ ПОДБОРА ТЕЛЕФОНА ==========
function pickPhone() {
    const chatbotInput = document.getElementById('chatbot-input');
    const chatbotSend = document.getElementById('chatbot-send');
    
    if (chatbotInput && chatbotSend) {
        chatbotInput.value = 'подбери телефон до 30000';
        chatbotSend.click();
        
        // Открываем чат, если он закрыт
        const chatbotContainer = document.getElementById('chatbot-container');
        if (chatbotContainer && !chatbotContainer.classList.contains('active')) {
            chatbotContainer.classList.add('active');
        }
    }
}

// ========== ОБНОВЛЕНИЕ МИНИ-ПАНЕЛИ ==========
function updateMiniPanel() {
    const count = compareSelectedProducts.length;
    document.getElementById('mini-selected-count').innerText = count;
    
    const compareBtn = document.getElementById('mini-compare-btn');
    if (compareBtn) compareBtn.disabled = count < 2;
    
    const container = document.getElementById('mini-products-list');
    if (count === 0) {
        container.innerHTML = '<div class="mini-empty">Нажмите на товары для добавления в сравнение</div>';
    } else {
        let html = '';
        compareSelectedProducts.forEach((p, i) => {
            html += `<div class="mini-product-item">
                <img src="${p.image}" alt="${escapeHtml(p.name)}" onerror="this.src='https://placehold.co/100x100?text=No+Image'">
                <div class="mini-product-info">
                    <h6>${escapeHtml(p.name.substring(0, 30))}</h6>
                    <p>${escapeHtml(p.price)}</p>
                </div>
                <button class="mini-remove" onclick="removeFromCompare(${i})"><i class="fas fa-times"></i></button>
            </div>`;
        });
        container.innerHTML = html;
    }
}

// ========== ДОБАВЛЕНИЕ ТОВАРА ==========
function addToCompare(productId, name, price, image) {
    if (compareSelectedProducts.some(p => p.id == productId)) {
        showToast('Товар уже добавлен', 'warning');
        return false;
    }
    if (compareSelectedProducts.length >= 4) {
        showToast('Можно сравнивать не более 4 товаров', 'warning');
        return false;
    }
    
    compareSelectedProducts.push({
        id: parseInt(productId),
        name: name,
        price: price,
        image: image
    });
    updateMiniPanel();
    showToast(`✅ Добавлено: ${name.substring(0, 30)}`, 'success');
    return true;
}

// ========== УДАЛЕНИЕ ТОВАРА ==========
function removeFromCompare(index) {
    compareSelectedProducts.splice(index, 1);
    updateMiniPanel();
    showToast('Товар удален', 'info');
}

// ========== ОЧИСТКА ==========
function clearCompare() {
    compareSelectedProducts = [];
    updateMiniPanel();
    showToast('Все товары удалены', 'info');
}

// ========== ЗАВЕРШЕНИЕ РЕЖИМА СРАВНЕНИЯ ==========
function stopCompareMode() {
    compareModeActive = false;
    document.getElementById('compareMiniPanel').style.display = 'none';
    document.querySelectorAll('.product-card-compare-mode').forEach(card => {
        card.classList.remove('product-card-compare-mode');
        card.onclick = null;
    });
    compareSelectedProducts = [];
    updateMiniPanel();
    showToast('Режим сравнения завершен', 'info');
}

// ========== ЗАПУСК РЕЖИМА СРАВНЕНИЯ ==========
function startCompareMode() {
    if (compareModeActive) {
        showToast('Режим сравнения уже активен', 'info');
        return;
    }
    
    compareModeActive = true;
    compareSelectedProducts = [];
    updateMiniPanel();
    
    const miniPanel = document.getElementById('compareMiniPanel');
    miniPanel.style.display = 'block';
    
    document.querySelectorAll('.product-card').forEach(card => {
        card.classList.add('product-card-compare-mode');
        
        if (!card.dataset.productId) {
            const link = card.querySelector('a[href*="product.php?id="]');
            if (link) {
                const match = link.href.match(/id=(\d+)/);
                if (match) card.dataset.productId = match[1];
            }
        }
        
        const productId = card.dataset.productId;
        const productName = card.querySelector('.product-title, h6')?.innerText || 'Товар';
        const productPrice = card.querySelector('.current-price')?.innerText || '';
        const productImage = card.querySelector('.product-image')?.src || 'https://placehold.co/100x100?text=No+Image';
        
        card.setAttribute('data-name', productName);
        card.setAttribute('data-price', productPrice);
        card.setAttribute('data-image', productImage);
        
        card.onclick = function(e) {
            if (e.target.closest('button') || e.target.closest('a')) return;
            const pid = this.dataset.productId;
            if (!pid) return;
            const name = this.getAttribute('data-name');
            const price = this.getAttribute('data-price');
            const image = this.getAttribute('data-image');
            
            if (addToCompare(pid, name, price, image)) {
                this.classList.add('highlight-for-compare');
                setTimeout(() => this.classList.remove('highlight-for-compare'), 300);
            }
        };
    });
    
    showToast('🖱️ Режим сравнения активирован! Нажимайте на товары для добавления.', 'success');
}

// ========== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ==========
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.position = 'fixed';
        container.style.bottom = '20px';
        container.style.right = '20px';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    const colors = { success: '#28a745', error: '#dc3545', warning: '#ffc107', info: '#17a2b8' };
    toast.style.backgroundColor = colors[type] || colors.info;
    toast.style.color = type === 'warning' ? '#333' : 'white';
    toast.style.padding = '10px 15px';
    toast.style.borderRadius = '8px';
    toast.style.marginBottom = '8px';
    toast.style.fontSize = '13px';
    toast.style.animation = 'slideIn 0.3s ease';
    toast.style.cursor = 'pointer';
    toast.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i> ${message}`;
    
    toast.onclick = () => toast.remove();
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// ========== ЧАТ-БОТ ==========
document.addEventListener('DOMContentLoaded', function() {
    console.log('📄 DOM загружен');
    
    const toggle = document.getElementById('chatbot-toggle');
    const container = document.getElementById('chatbot-container');
    const close = document.getElementById('chatbot-close');
    const input = document.getElementById('chatbot-input');
    const send = document.getElementById('chatbot-send');
    const messages = document.getElementById('chatbot-messages');
    
    if (!toggle || !container) return;
    
    toggle.onclick = function(e) {
        e.preventDefault();
        container.classList.toggle('active');
    };
    
    if (close) close.onclick = function() { container.classList.remove('active'); };
    
    function addMessage(text, sender) {
        const div = document.createElement('div');
        div.className = sender + '-message';
        const time = new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
        div.innerHTML = `<div class="message-content">${text.replace(/\n/g, '<br>')}</div><div class="message-time">${time}</div>`;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
        div.querySelectorAll('a').forEach(a => a.target = '_blank');
    }
    
    function showTyping() {
        const typing = document.getElementById('typing-indicator');
        if (typing) typing.remove();
        const div = document.createElement('div');
        div.className = 'bot-message';
        div.id = 'typing-indicator';
        div.innerHTML = `<div class="message-content"><div style="display:flex; gap:4px;"><span style="width:6px;height:6px;background:#667eea;border-radius:50%;animation:typingPulse 1s infinite;"></span><span style="width:6px;height:6px;background:#667eea;border-radius:50%;animation:typingPulse 1s infinite 0.2s;"></span><span style="width:6px;height:6px;background:#667eea;border-radius:50%;animation:typingPulse 1s infinite 0.4s;"></span></div></div>`;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }
    
    function hideTyping() {
        const typing = document.getElementById('typing-indicator');
        if (typing) typing.remove();
    }
    
    function sendMessage() {
        const msg = input.value.trim();
        if (!msg) return;
        addMessage(msg, 'user');
        input.value = '';
        showTyping();
        
        fetch('http://localhost:5000/api/chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: msg, session_id: '<?= session_id() ?>' })
        })
        .then(r => r.json())
        .then(data => {
            hideTyping();
            addMessage(data.response, 'bot');
        })
        .catch(() => {
            hideTyping();
            addMessage('❌ Ошибка соединения с сервером. Убедитесь, что бот запущен.', 'bot');
        });
    }
    
    if (send) send.onclick = sendMessage;
    if (input) input.onkeypress = function(e) { if (e.key === 'Enter') sendMessage(); };
    
    // Обработчики для кнопок быстрых действий
    const compareStartBtn = document.getElementById('compare-start-btn');
    if (compareStartBtn) {
        compareStartBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            startCompareMode();
        };
    }
    
    const whatBetterBtn = document.getElementById('what-better-btn');
    if (whatBetterBtn) {
        whatBetterBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            askWhatBetter();
        };
    }
    
    const pickPhoneBtn = document.getElementById('pick-phone-btn');
    if (pickPhoneBtn) {
        pickPhoneBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            pickPhone();
        };
    }
    
    // Кнопки мини-панели
    const miniClear = document.getElementById('mini-clear-btn');
    if (miniClear) miniClear.onclick = clearCompare;
    
    const miniClose = document.getElementById('mini-close-btn');
    if (miniClose) miniClose.onclick = stopCompareMode;
    
    const miniCompare = document.getElementById('mini-compare-btn');
    if (miniCompare) miniCompare.onclick = sendCompareToBot;
});
</script>
</body>
</html>