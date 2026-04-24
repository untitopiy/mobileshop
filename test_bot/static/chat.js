// ============================================
// chat.js — AI Консультант (исправленная версия)
// ============================================

// ИСПРАВЛЕНИЕ: Стабильный session_id в localStorage
let sessionId = localStorage.getItem('chat_session_id');
if (!sessionId) {
    sessionId = 'session_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
    localStorage.setItem('chat_session_id', sessionId);
    console.log('[CHAT] Создан новый sessionId:', sessionId);
} else {
    console.log('[CHAT] Восстановлен sessionId:', sessionId);
}

const messagesArea = document.getElementById('messagesArea');
const userInput = document.getElementById('userInput');
const sendBtn = document.getElementById('sendBtn');
const clearBtn = document.getElementById('clearChatBtn');
const suggestions = document.querySelectorAll('.suggestion-chip');

let isWaitingForResponse = false;

function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
}
window.autoResize = autoResize;

function scrollToBottom() {
    messagesArea.scrollTo({
        top: messagesArea.scrollHeight,
        behavior: 'smooth'
    });
}

function addMessage(text, type) {
    const messageDiv = document.createElement('div');
    messageDiv.classList.add('message', type);

    const avatarDiv = document.createElement('div');
    avatarDiv.classList.add('avatar');
    avatarDiv.innerHTML = type === 'user' ? '<i class="fas fa-user"></i>' : '<i class="fas fa-robot"></i>';

    const bubbleDiv = document.createElement('div');
    bubbleDiv.classList.add('bubble');
    bubbleDiv.innerText = text;

    messageDiv.appendChild(avatarDiv);
    messageDiv.appendChild(bubbleDiv);
    messagesArea.appendChild(messageDiv);
    scrollToBottom();
}

function removeTypingIndicator() {
    const typingElem = document.getElementById('typing-indicator');
    if (typingElem) typingElem.remove();
}

function showTypingIndicator() {
    removeTypingIndicator();
    const indicatorDiv = document.createElement('div');
    indicatorDiv.classList.add('message', 'bot');
    indicatorDiv.id = 'typing-indicator';
    indicatorDiv.innerHTML = `
        <div class="avatar"><i class="fas fa-robot"></i></div>
        <div class="typing-indicator">
            <span></span><span></span><span></span>
        </div>
    `;
    messagesArea.appendChild(indicatorDiv);
    scrollToBottom();
}

// ============================================
// ИСПРАВЛЕНИЕ: Загрузка истории при старте
// ============================================
async function loadChatHistory() {
    console.log('[CHAT] Загрузка истории для:', sessionId);

    try {
        const response = await fetch('/api/chat-history?session_id=' + encodeURIComponent(sessionId));
        console.log('[CHAT] Статус ответа:', response.status);

        if (!response.ok) {
            console.log('[CHAT] Ошибка загрузки истории');
            return;
        }

        const data = await response.json();
        console.log('[CHAT] Получено сообщений:', data.messages?.length || 0);

        if (!data.messages || data.messages.length === 0) {
            console.log('[CHAT] История пустая');
            return;
        }

        // Очищаем текущие сообщения (кроме приветствия)
        const existingMessages = messagesArea.querySelectorAll('.message');
        for (let i = 1; i < existingMessages.length; i++) {
            existingMessages[i].remove();
        }

        // Выводим историю
        for (const msg of data.messages) {
            if (msg.role === 'system') continue;
            const type = msg.role === 'user' ? 'user' : 'bot';
            addMessage(msg.content, type);
        }

        console.log('[CHAT] История загружена успешно');

    } catch (error) {
        console.error('[CHAT] Ошибка загрузки истории:', error);
    }
}

async function sendMessage() {
    const messageText = userInput.value.trim();
    if (messageText === '' || isWaitingForResponse) return;

    addMessage(messageText, 'user');
    userInput.value = '';
    userInput.style.height = 'auto';
    isWaitingForResponse = true;
    sendBtn.disabled = true;
    showTypingIndicator();

    try {
        const response = await fetch('/api/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                message: messageText,
                session_id: sessionId
            })
        });

        const data = await response.json();

        removeTypingIndicator();

        if (data.error) {
            addMessage('Ошибка: ' + data.error, 'bot');
        } else {
            addMessage(data.response, 'bot');
        }
    } catch (error) {
        console.error('Error:', error);
        removeTypingIndicator();
        addMessage('Ошибка соединения с сервером', 'bot');
    } finally {
        isWaitingForResponse = false;
        sendBtn.disabled = false;
        userInput.focus();
    }
}

// Обработчики событий
sendBtn.addEventListener('click', sendMessage);

userInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

clearBtn.addEventListener('click', async () => {
    try {
        await fetch('/api/clear', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: sessionId })
        });

        // Очищаем сообщения, оставляя только первое
        const messages = messagesArea.querySelectorAll('.message');
        for (let i = 1; i < messages.length; i++) {
            messages[i].remove();
        }
        removeTypingIndicator();
        isWaitingForResponse = false;
        sendBtn.disabled = false;

        // ИСПРАВЛЕНИЕ: Сбрасываем session_id при очистке
        localStorage.removeItem('chat_session_id');
        sessionId = 'session_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
        localStorage.setItem('chat_session_id', sessionId);
        console.log('[CHAT] Новый sessionId после очистки:', sessionId);

    } catch (error) {
        console.error('Error clearing chat:', error);
    }
});

suggestions.forEach(chip => {
    chip.addEventListener('click', () => {
        const text = chip.getAttribute('data-text');
        if (text) {
            userInput.value = text;
            autoResize(userInput);
            userInput.focus();
        }
    });
});

// ИСПРАВЛЕНИЕ: Загрузка истории при загрузке страницы
window.addEventListener('load', () => {
    console.log('[CHAT] Страница загружена, sessionId:', sessionId);
    userInput.focus();
    loadChatHistory();
});