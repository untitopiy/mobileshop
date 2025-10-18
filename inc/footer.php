<?php
// Завершение контейнера (если открыт в других частях сайта)
?>
    </div> <!-- Закрытие div.container, если он открыт в страницах -->

    <!-- Подключение JS-скриптов -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $base_url; ?>inc/scripts.js"></script>

    <!-- Чат-бот -->
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
                    Привет! Я ваш помощник. Могу помочь с подбором телефона, оформлением заказа, доставкой и другими вопросами. Чем могу помочь?
                </div>
                <div class="message-time"><?= date('H:i') ?></div>
            </div>
        </div>
        <div class="chatbot-input">
            <input type="text" id="chatbot-input" placeholder="Введите ваш вопрос..." maxlength="500">
            <button id="chatbot-send">➤</button>
        </div>
    </div>
</div>

<style>
/* Стили чат-бота */
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
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
}

.chatbot-container {
    position: absolute;
    bottom: 70px;
    right: 0;
    width: 350px;
    height: 500px;
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
    font-weight: 600;
}

.chatbot-close {
    background: none;
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
    padding: 0;
    width: 25px;
    height: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
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
    padding: 12px 15px;
    border-radius: 18px;
    max-width: 80%;
    word-wrap: break-word;
    white-space: pre-line;
}

.bot-message .message-content {
    background: white;
    border: 1px solid #e9ecef;
    border-bottom-left-radius: 5px;
}

.user-message .message-content {
    background: #667eea;
    color: white;
    border-bottom-right-radius: 5px;
}

.message-time {
    font-size: 11px;
    color: #6c757d;
    margin-top: 5px;
}

.user-message .message-time {
    text-align: right;
}

.chatbot-input {
    display: flex;
    padding: 15px;
    border-top: 1px solid #e9ecef;
    background: white;
}

#chatbot-input {
    flex: 1;
    border: 1px solid #ddd;
    border-radius: 20px;
    padding: 10px 15px;
    outline: none;
    margin-right: 10px;
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
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.3s;
}

#chatbot-send:hover {
    background: #5a6fd8;
}

/* Адаптивность */
@media (max-width: 768px) {
    #chatbot-widget {
        bottom: 10px;
        right: 10px;
    }
    
    .chatbot-container {
        width: 300px;
        height: 400px;
    }
    
    .chatbot-toggle {
        width: 50px;
        height: 50px;
        font-size: 20px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatbotToggle = document.getElementById('chatbot-toggle');
    const chatbotContainer = document.getElementById('chatbot-container');
    const chatbotClose = document.getElementById('chatbot-close');
    const chatbotInput = document.getElementById('chatbot-input');
    const chatbotSend = document.getElementById('chatbot-send');
    const chatbotMessages = document.getElementById('chatbot-messages');
    
    // Открытие/закрытие чата
    chatbotToggle.addEventListener('click', function() {
        chatbotContainer.classList.toggle('active');
    });
    
    chatbotClose.addEventListener('click', function() {
        chatbotContainer.classList.remove('active');
    });
    
    // Отправка сообщения
    function sendMessage() {
        const message = chatbotInput.value.trim();
        if (!message) return;
        
        // Добавляем сообщение пользователя
        addMessage(message, 'user');
        chatbotInput.value = '';
        
        // Отправляем запрос боту
        fetch('chatbot.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'message=' + encodeURIComponent(message)
        })
        .then(response => response.json())
        .then(data => {
            addMessage(data.response, 'bot');
        })
        .catch(error => {
            addMessage('Извините, произошла ошибка. Попробуйте позже.', 'bot');
            console.error('Error:', error);
        });
    }
    
    // Добавление сообщения в чат
    function addMessage(text, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.className = sender + '-message';
        
        const time = new Date().toLocaleTimeString('ru-RU', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        
        messageDiv.innerHTML = `
            <div class="message-content">${text}</div>
            <div class="message-time">${time}</div>
        `;
        
        chatbotMessages.appendChild(messageDiv);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }
    
    // Обработчики событий
    chatbotSend.addEventListener('click', sendMessage);
    
    chatbotInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
    
    // Предлагаемые вопросы
    const quickQuestions = [
        'Как подобрать телефон?',
        'Как оформить заказ?',
        'Какие способы доставки?',
        'Сколько стоит доставка?',
        'Какой срок гарантии?'
    ];
    
    // Добавляем быстрые вопросы (опционально)
    setTimeout(() => {
        const quickQuestionsDiv = document.createElement('div');
        quickQuestionsDiv.className = 'quick-questions';
        quickQuestionsDiv.style.marginTop = '10px';
        quickQuestionsDiv.style.padding = '10px';
        quickQuestionsDiv.style.background = '#f1f3f4';
        quickQuestionsDiv.style.borderRadius = '10px';
        quickQuestionsDiv.style.fontSize = '12px';
        
        quickQuestionsDiv.innerHTML = '<strong>Частые вопросы:</strong><br>' +
            quickQuestions.map(q => 
                `<div style="cursor:pointer; padding:5px 0; color:#667eea;" onclick="document.getElementById('chatbot-input').value='${q}'; document.getElementById('chatbot-send').click();">${q}</div>`
            ).join('');
        
        chatbotMessages.appendChild(quickQuestionsDiv);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }, 2000);
});
</script>
</body>
</html>
