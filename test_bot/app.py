import os
import json
import logging
import requests
import re
import csv
import io
import mysql.connector
from mysql.connector import Error
from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
from dotenv import load_dotenv
from datetime import datetime

# Загружаем переменные окружения
load_dotenv()

# Определяем базовую папку проекта
basedir = os.path.abspath(os.path.dirname(__file__))

# Создаем приложение Flask
app = Flask(__name__)
CORS(app)

app.secret_key = os.urandom(24)

# Настройка логирования
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Конфигурация OpenRouter
OPENROUTER_API_KEY = os.getenv('OPENROUTER_API_KEY')
YOUR_SITE_URL = os.getenv('YOUR_SITE_URL', 'http://localhost')
YOUR_SITE_NAME = os.getenv('YOUR_SITE_NAME', 'AI Консультант магазина')
OPENROUTER_URL = "https://openrouter.ai/api/v1/chat/completions"
OPENROUTER_MODEL = "nvidia/nemotron-3-super-120b-a12b:free"

# Конфигурация MySQL
DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'database': os.getenv('DB_NAME', 'marketplace'),
    'charset': 'utf8mb4',
    'collation': 'utf8mb4_unicode_ci'
}

# Хранилище диалогов и последних сравненных товаров
conversations = {}
last_compared_products = {}

# ========== MySQL ФУНКЦИИ ==========

def get_db_connection():
    """Создаёт подключение к MySQL"""
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        return conn
    except Error as e:
        logger.error(f"❌ Ошибка подключения к MySQL: {e}")
        return None


def save_chat_session(session_id, messages, last_products=None):
    """Сохраняет сессию чата в MySQL"""
    if not session_id:
        return False
    
    conn = get_db_connection()
    if not conn:
        return False
    
    try:
        cursor = conn.cursor()
        messages_json = json.dumps(messages, ensure_ascii=False)
        products_json = json.dumps(last_products, ensure_ascii=False) if last_products else None
        message_count = len(messages)
        
        cursor.execute("""
            INSERT INTO chat_sessions 
                (session_id, messages, last_products, message_count, 
                 user_agent, ip_address, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                messages = VALUES(messages),
                last_products = VALUES(last_products),
                message_count = VALUES(message_count),
                user_agent = VALUES(user_agent),
                ip_address = VALUES(ip_address),
                updated_at = NOW()
        """, (session_id, messages_json, products_json, message_count,
              request.headers.get('User-Agent', '')[:255],
              request.remote_addr[:45]))
        
        conn.commit()
        logger.info(f"💾 Сессия сохранена: {session_id[:8]}... ({message_count} сообщений)")
        return True
    except Error as e:
        logger.error(f"❌ Ошибка сохранения сессии: {e}")
        return False
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()


def load_chat_session(session_id):
    """Загружает сессию чата из MySQL"""
    if not session_id:
        return None, None
    
    conn = get_db_connection()
    if not conn:
        return None, None
    
    try:
        cursor = conn.cursor(dictionary=True)
        # УБРАНО ограничение 24 часа для тестирования
        cursor.execute("""
            SELECT messages, last_products 
            FROM chat_sessions 
            WHERE session_id = %s
        """, (session_id,))
        
        row = cursor.fetchone()
        if row:
            messages = json.loads(row['messages']) if row['messages'] else []
            products = json.loads(row['last_products']) if row['last_products'] else None
            logger.info(f"📥 Сессия загружена из MySQL: {session_id[:8]}... ({len(messages)} сообщений)")
            return messages, products
        logger.info(f"📭 Сессия не найдена: {session_id[:8]}...")
        return None, None
    except Error as e:
        logger.error(f"❌ Ошибка загрузки сессии: {e}")
        return None, None
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()


def safe_float(val, default=0):
    """Безопасное преобразование в float"""
    try:
        return float(val) if val is not None else default
    except (ValueError, TypeError):
        return default


def safe_int(val, default=0):
    """Безопасное преобразование в int"""
    try:
        return int(float(val)) if val is not None else default
    except (ValueError, TypeError):
        return default


def search_products(query):
    """Поиск товаров на сайте"""
    try:
        import urllib.parse
        encoded_query = urllib.parse.quote(query)
        url = f"http://localhost/mobileshop/api_products.php?action=search&q={encoded_query}&limit=5"
        response = requests.get(url, timeout=5)
        if response.status_code == 200:
            data = response.json()
            if data.get('success'):
                return data.get('products', [])
    except Exception as e:
        logger.error(f"Ошибка поиска товаров: {e}")
    return []


def get_products_for_compare(product_ids):
    """Получение детальных характеристик товаров для сравнения"""
    try:
        ids_str = ','.join(map(str, product_ids))
        url = f"http://localhost/mobileshop/api_products.php?action=get_compare&ids={ids_str}"
        logger.info(f"📊 Запрос характеристик: {url}")
        response = requests.get(url, timeout=5)
        if response.status_code == 200:
            data = response.json()
            if data.get('success'):
                return data.get('products', [])
    except Exception as e:
        logger.error(f"Ошибка получения деталей товаров: {e}")
    return []


def generate_recommendation(products):
    """Генерирует совет на основе характеристик товаров с защитой от ошибок"""
    if not products or len(products) < 2:
        return "<div style='background: #fff3cd; padding: 15px; border-radius: 10px;'>⚠️ Недостаточно данных для сравнения. Выберите 2 товара.</div>"
    
    p1 = products[0] if isinstance(products[0], dict) else {}
    p2 = products[1] if isinstance(products[1], dict) else {}
    
    if not p1 or not p2:
        return "<div style='background: #fff3cd; padding: 15px; border-radius: 10px;'>⚠️ Ошибка загрузки данных товаров.</div>"
    
    p1_price = safe_float(p1.get('price_value'))
    p2_price = safe_float(p2.get('price_value'))
    p1_rating = safe_float(p1.get('rating'))
    p2_rating = safe_float(p2.get('rating'))
    p1_stock = safe_int(p1.get('stock'))
    p2_stock = safe_int(p2.get('stock'))
    p1_sales = safe_int(p1.get('sales_count'))
    p2_sales = safe_int(p2.get('sales_count'))
    
    p1_specs = p1.get('specs') if isinstance(p1.get('specs'), dict) else {}
    p2_specs = p2.get('specs') if isinstance(p2.get('specs'), dict) else {}
    
    p1_ram = None
    p2_ram = None
    
    for key, value in (p1_specs or {}).items():
        if isinstance(value, dict):
            val = value.get('value', '')
        else:
            val = str(value) if value is not None else ''
        
        if val and ('ram' in key.lower() or ('память' in key.lower() and 'оператив' in key.lower())):
            p1_ram = val
    
    for key, value in (p2_specs or {}).items():
        if isinstance(value, dict):
            val = value.get('value', '')
        else:
            val = str(value) if value is not None else ''
        
        if val and ('ram' in key.lower() or ('память' in key.lower() and 'оператив' in key.lower())):
            p2_ram = val
    
    recommendation = []
    p1_score = 0
    p2_score = 0
    
    if p1_price > 0 and p2_price > 0:
        if p1_price < p2_price:
            p1_score += 1
            recommendation.append(f"💰 **Цена**: {p1.get('name', 'Товар 1')} дешевле на {p2_price - p1_price:.0f} ₽")
        elif p2_price < p1_price:
            p2_score += 1
            recommendation.append(f"💰 **Цена**: {p2.get('name', 'Товар 2')} дешевле на {p1_price - p2_price:.0f} ₽")
    
    if p1_rating > 0 or p2_rating > 0:
        if p1_rating > p2_rating:
            p1_score += 1
            recommendation.append(f"⭐ **Рейтинг**: {p1.get('name', 'Товар 1')} имеет более высокий рейтинг ({p1_rating:.1f} vs {p2_rating:.1f})")
        elif p2_rating > p1_rating:
            p2_score += 1
            recommendation.append(f"⭐ **Рейтинг**: {p2.get('name', 'Товар 2')} имеет более высокий рейтинг ({p2_rating:.1f} vs {p1_rating:.1f})")
    
    if p1_ram and p2_ram:
        try:
            p1_ram_num = int(re.search(r'\d+', str(p1_ram)).group())
            p2_ram_num = int(re.search(r'\d+', str(p2_ram)).group())
            if p1_ram_num > p2_ram_num:
                p1_score += 1
                recommendation.append(f"💾 **Оперативная память**: {p1.get('name', 'Товар 1')} имеет больше ОЗУ ({p1_ram})")
            elif p2_ram_num > p1_ram_num:
                p2_score += 1
                recommendation.append(f"💾 **Оперативная память**: {p2.get('name', 'Товар 2')} имеет больше ОЗУ ({p2_ram})")
        except (AttributeError, ValueError):
            pass
    
    if p1_sales > 0 or p2_sales > 0:
        if p1_sales > p2_sales:
            p1_score += 1
            recommendation.append(f"🔥 **Популярность**: {p1.get('name', 'Товар 1')} купили больше раз ({p1_sales} vs {p2_sales})")
        elif p2_sales > p1_sales:
            p2_score += 1
            recommendation.append(f"🔥 **Популярность**: {p2.get('name', 'Товар 2')} купили больше раз ({p2_sales} vs {p1_sales})")
    
    if p1_stock > 0 and p2_stock == 0:
        p1_score += 1
        recommendation.append(f"📦 **Наличие**: {p1.get('name', 'Товар 1')} есть в наличии")
    elif p2_stock > 0 and p1_stock == 0:
        p2_score += 1
        recommendation.append(f"📦 **Наличие**: {p2.get('name', 'Товар 2')} есть в наличии")
    
    if p1_score > p2_score:
        winner = p1.get('name', 'Товар 1')
        verdict = f"🏆 **Мой вердикт**: **{winner}** выглядит более выгодным выбором! ✅"
        if p1_price > p2_price:
            verdict += f" Да, он дороже, но его преимущества оправдывают цену."
        elif p1_price < p2_price:
            verdict += f" При этом он еще и дешевле — отличное соотношение цены и качества!"
    elif p2_score > p1_score:
        winner = p2.get('name', 'Товар 2')
        verdict = f"🏆 **Мой вердикт**: **{winner}** выглядит более выгодным выбором! ✅"
        if p2_price > p1_price:
            verdict += f" Да, он дороже, но его преимущества оправдывают цену."
        elif p2_price < p1_price:
            verdict += f" При этом он еще и дешевле — отличное соотношение цены и качества!"
    else:
        verdict = f"🤔 **Мой вердикт**: Оба товара примерно равны по характеристикам. Выбор зависит от ваших личных предпочтений!"
        if p1_price > 0 and p2_price > 0:
            if p1_price < p2_price:
                verdict += f" Если хотите сэкономить — выбирайте **{p1.get('name', 'Товар 1')}**."
            elif p2_price < p1_price:
                verdict += f" Если хотите сэкономить — выбирайте **{p2.get('name', 'Товар 2')}**."
    
    result = "<div style='background: #f0f7ff; padding: 15px; border-radius: 10px; margin: 10px 0; border-left: 4px solid #667eea;'>"
    result += "<strong>💡 СОВЕТ ЭКСПЕРТА</strong><br><br>"
    
    if recommendation:
        result += "<strong>📊 Почему такой выбор?</strong><br>"
        for rec in recommendation:
            result += f"• {rec}<br>"
        result += "<br>"
    
    result += verdict
    result += "</div>"
    
    return result


def format_compare_response(products, with_recommendation=True):
    """Форматирует ответ с таблицей сравнения"""
    if len(products) < 2:
        return "Недостаточно товаров для сравнения."
    
    priority_specs = [
        'nb_cpu_model', 'nb_cpu_cores', 'nb_cpu_frequency',
        'nb_ram_size', 'nb_ram_type',
        'nb_storage_type', 'nb_ssd_size', 'nb_hdd_size',
        'nb_screen_size', 'nb_screen_type', 'nb_screen_resolution',
        'nb_gpu', 'nb_gpu_type',
        'sm_display_type', 'sm_refresh_rate', 'sm_support_5g',
        'sm_fast_charging', 'sm_wireless_charging',
        'hp_type', 'hp_connection', 'hp_battery_life'
    ]
    
    all_specs = set()
    for p in products:
        specs = p.get('specs', {})
        if isinstance(specs, dict):
            for spec_key in specs:
                all_specs.add(spec_key)
    
    sorted_specs = [s for s in priority_specs if s in all_specs]
    sorted_specs += sorted([s for s in all_specs if s not in priority_specs])
    
    html = '<div style="overflow-x: auto; max-width: 100%;">'
    html += '<table style="width: 100%; border-collapse: collapse; font-size: 13px; background: white; border-radius: 12px; overflow: hidden;">'
    
    html += '<thead><tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">'
    html += '<th style="padding: 12px; border: 1px solid rgba(255,255,255,0.2); text-align: left;">📊 Характеристика</th>'
    for p in products:
        p_url = p.get('url', f"/mobileshop/product.php?id={p['id']}")
        html += f'<th style="padding: 12px; border: 1px solid rgba(255,255,255,0.2); text-align: center; min-width: 180px;">'
        if p.get('image'):
            html += f'<img src="{p["image"]}" style="max-width: 70px; max-height: 70px; object-fit: contain; margin-bottom: 5px;"><br>'
        html += f'<a href="{p_url}" target="_blank" style="color: white; text-decoration: none;"><strong>{p["name"][:35]}</strong></a><br>'
        html += f'<span style="color: #ffd966;">{p["price"]}</span>'
        html += f'</th>'
    html += '     </tr></thead><tbody>'
    
    html += '<tr style="background: #f8f9fa;">'
    html += '<td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">💰 Цена</td>'
    for p in products:
        html += f'<td style="padding: 10px; border: 1px solid #ddd; text-align: center;"><span style="color: #28a745; font-weight: bold;">{p["price"]}</span></td>'
    html += '</tr>'
    
    if any(p.get('rating', 0) > 0 for p in products):
        html += '<tr>'
        html += '<td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">⭐ Рейтинг</td>'
        for p in products:
            stars = ''
            rating = safe_float(p.get('rating'))
            full = int(rating)
            half = 1 if rating - full >= 0.5 else 0
            empty = 5 - full - half
            stars = '★' * full + '½' * half + '☆' * empty
            html += f'<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">{stars}<br><small>{rating}</small></td>'
        html += '</tr>'
    
    html += '<tr style="background: #f8f9fa;">'
    html += '<td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">📦 Наличие</td>'
    for p in products:
        stock = safe_int(p.get('stock'))
        stock_text = f'✅ {stock} шт.' if stock > 0 else '❌ Нет в наличии'
        stock_color = '#28a745' if stock > 0 else '#dc3545'
        html += f'<td style="padding: 10px; border: 1px solid #ddd; text-align: center; color: {stock_color};">{stock_text}</td>'
    html += '</tr>'
    
    html += '<tr>'
    html += '<td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">📈 Продано</td>'
    for p in products:
        html += f'<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">{safe_int(p.get("sales_count"))} шт.</td>'
    html += '</tr>'
    
    for spec_key in sorted_specs:
        spec_name = ''
        for p in products:
            specs = p.get('specs', {})
            if isinstance(specs, dict) and spec_key in specs:
                if isinstance(specs[spec_key], dict):
                    spec_name = specs[spec_key].get('name', spec_key)
                else:
                    spec_name = spec_key.replace('nb_', '').replace('sm_', '').replace('hp_', '').replace('ch_', '').replace('gl_', '')
                    spec_name = spec_name.replace('_', ' ').title()
                break
        
        if not spec_name:
            spec_name = spec_key.replace('_', ' ').title()
        
        html += '<tr style="background: #f8f9fa;">'
        html += f'<td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">📌 {spec_name}</td>'
        for p in products:
            specs = p.get('specs', {})
            if isinstance(specs, dict):
                value = specs.get(spec_key, '—')
            else:
                value = '—'
            if isinstance(value, dict):
                value = value.get('value', '—')
            html += f'<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">{value}</td>'
        html += '</tr>'
    
    html += '</tbody></table></div>'
    
    if with_recommendation:
        recommendation_html = generate_recommendation(products)
        html += recommendation_html
    
    ids = [p['id'] for p in products]
    compare_url = f"/mobileshop/pages/compare.php?ids={','.join(map(str, ids))}"
    export_url = f"/api/export-compare?ids={','.join(map(str, ids))}"
    
    html += f'''
    <div style="margin-top: 15px; text-align: center; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
        <a href="{export_url}" target="_blank" 
           style="display: inline-block; padding: 10px 20px; background: #28a745; color: white; 
                  text-decoration: none; border-radius: 8px; font-weight: bold;">
            📥 Скачать Excel
        </a>
        <a href="{compare_url}" target="_blank"
           style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; 
                  text-decoration: none; border-radius: 8px; font-weight: bold;">
            🔗 Открыть на сайте
        </a>
    </div>
    '''
    
    return html


# 🌟 СИСТЕМНЫЙ ПРОМТ — УСИЛЕННЫЙ ЧТОБЫ ИЗБЕЖАТЬ JSON-ОТВЕТОВ
SYSTEM_PROMPT = """Ты — профессиональный консультант интернет-магазина техники. 

ВАЖНЫЕ ПРАВИЛА:
1. Отвечай ТОЛЬКО простым текстом на русском языке
2. НИКОГДА не используй JSON, XML, markdown-код или структурированные форматы
3. НИКОГДА не пиши {"tool": ...} или похожие технические конструкции
4. Отвечай как человек: коротко, дружелюбно, по делу
5. Если не знаешь ответ — честно скажи об этом

Ты можешь:
- Искать товары по названию и бренду
- Сравнивать товары по характеристикам
- Рекомендовать товары под бюджет
- Давать советы по выбору

Всегда будь полезным и помогай сделать правильный выбор!"""


def get_openrouter_response(messages, session_id=None):
    """Отправка запроса к OpenRouter API"""
    if not OPENROUTER_API_KEY:
        logger.error("API ключ не настроен")
        return None, "API ключ не настроен. Проверьте файл .env"
    
    headers = {
        "Authorization": f"Bearer {OPENROUTER_API_KEY}",
        "Content-Type": "application/json",
        "HTTP-Referer": YOUR_SITE_URL,
        "X-Title": YOUR_SITE_NAME,
    }
    
    api_messages = []
    api_messages.append({
        "role": "system",
        "content": SYSTEM_PROMPT
    })
    
    if session_id and session_id in conversations:
        history = conversations[session_id][-10:]
        for msg in history:
            if msg["role"] != "system":
                api_messages.append({
                    "role": msg["role"],
                    "content": msg["content"]
                })
    else:
        for msg in messages:
            api_messages.append(msg)
    
    data = {
        "model": OPENROUTER_MODEL,
        "messages": api_messages,
        "temperature": 0.7,
        "max_tokens": 1000,
        "top_p": 0.9
    }
    
    try:
        logger.info(f"📤 Отправка запроса к OpenRouter")
        
        response = requests.post(
            OPENROUTER_URL,
            headers=headers,
            json=data,
            timeout=30
        )
        
        if response.status_code == 200:
            result = response.json()
            if 'choices' in result and len(result['choices']) > 0:
                content = result['choices'][0]['message']['content']
                
                # ЗАЩИТА: если AI вернул JSON — заменяем на человеческий ответ
                if content.strip().startswith('{') and '"tool"' in content:
                    logger.warning("⚠️ AI вернул JSON, заменяем на заглушку")
                    return "Извините, я не совсем понял вопрос. Можете переформулировать? Я могу помочь с поиском товаров, сравнением или подбором по бюджету.", None
                
                return content, None
            else:
                return None, "Неожиданный формат ответа от API"
        else:
            error_msg = f"Ошибка API: {response.status_code}"
            try:
                error_data = response.json()
                if 'error' in error_data:
                    error_msg += f" - {error_data['error'].get('message', '')}"
            except:
                pass
            logger.error(f"❌ {error_msg}")
            return None, error_msg
            
    except requests.exceptions.Timeout:
        return None, "Превышено время ожидания ответа"
    except requests.exceptions.ConnectionError:
        return None, "Ошибка соединения с API"
    except Exception as e:
        logger.error(f"⚠️ Ошибка: {str(e)}")
        return None, f"Внутренняя ошибка: {str(e)}"


@app.route('/api/save-compare-products', methods=['POST'])
def save_compare_products():
    """Сохранение выбранных для сравнения товаров"""
    try:
        data = request.json
        session_id = data.get('session_id', request.remote_addr)
        products = data.get('products', [])
        
        if products and len(products) >= 2:
            product_ids = [p['id'] for p in products]
            detailed_products = get_products_for_compare(product_ids)
            
            if detailed_products and len(detailed_products) >= 2:
                last_compared_products[session_id] = detailed_products
                save_chat_session(session_id, conversations.get(session_id, []), detailed_products)
                logger.info(f"💾 СОХРАНЕНИЕ из мини-панели для сессии {session_id}: {len(detailed_products)} товаров")
                return jsonify({'status': 'success', 'message': 'Товары сохранены'})
            else:
                last_compared_products[session_id] = products
                save_chat_session(session_id, conversations.get(session_id, []), products)
                logger.info(f"💾 СОХРАНЕНИЕ (базовое) для сессии {session_id}: {len(products)} товаров")
                return jsonify({'status': 'success', 'message': 'Товары сохранены (базовая информация)'})
        else:
            return jsonify({'status': 'error', 'message': 'Недостаточно товаров'})
            
    except Exception as e:
        logger.error(f"❌ Ошибка сохранения товаров: {str(e)}")
        return jsonify({'status': 'error', 'message': str(e)}), 500


@app.route('/api/wizard', methods=['POST'])
def wizard_step():
    """Многошаговый мастер подбора товаров"""
    try:
        data = request.json
        session_id = data.get('session_id', request.remote_addr)
        step = data.get('step')
        user_choice = data.get('choice')
        
        wizard_key = f"wizard_{session_id}"
        
        if step == 'start':
            return jsonify({
                'step': 'category',
                'message': '🎯 <b>Мастер подбора товаров</b><br><br>Выберите категорию:',
                'options': [
                    {'id': 'smartfony', 'name': '📱 Смартфоны'},
                    {'id': 'notebooks', 'name': '💻 Ноутбуки'},
                    {'id': 'naushniki', 'name': '🎧 Наушники'},
                    {'id': 'aksessuary', 'name': '🔌 Аксессуары'}
                ],
                'type': 'single_select'
            })
        
        elif step == 'category':
            last_compared_products[wizard_key] = {'category': user_choice}
            
            return jsonify({
                'step': 'budget',
                'message': f'✅ Категория: <b>{user_choice}</b><br><br>💰 Какой у вас бюджет?',
                'options': [
                    {'id': '20000', 'name': 'До 20 000 ₽'},
                    {'id': '40000', 'name': '20 000 - 40 000 ₽'},
                    {'id': '60000', 'name': '40 000 - 60 000 ₽'},
                    {'id': '100000', 'name': '60 000 - 100 000 ₽'},
                    {'id': 'unlimited', 'name': 'Более 100 000 ₽'}
                ],
                'type': 'single_select'
            })
        
        elif step == 'budget':
            state = last_compared_products.get(wizard_key, {})
            state['budget'] = user_choice
            last_compared_products[wizard_key] = state
            
            category = state['category']
            
            if category == 'smartfony':
                options = [
                    {'id': 'battery', 'name': '🔋 Батарея (автономность)'},
                    {'id': 'camera', 'name': '📷 Камера (фото/видео)'},
                    {'id': 'performance', 'name': '⚡ Производительность (игры/работа)'},
                    {'id': 'screen', 'name': '📺 Экран (качество)'},
                    {'id': 'compact', 'name': '🤏 Компактность'},
                    {'id': 'brand', 'name': '🏷️ Бренд'}
                ]
            elif category == 'notebooks':
                options = [
                    {'id': 'cpu', 'name': '⚡ Процессор'},
                    {'id': 'ram', 'name': '💾 Оперативная память'},
                    {'id': 'ssd', 'name': '💽 Накопитель (SSD)'},
                    {'id': 'screen', 'name': '📺 Экран'},
                    {'id': 'battery', 'name': '🔋 Автономность'},
                    {'id': 'weight', 'name': '🪶 Лёгкость'}
                ]
            else:
                options = [
                    {'id': 'price', 'name': '💰 Цена'},
                    {'id': 'quality', 'name': '⭐ Качество'},
                    {'id': 'brand', 'name': '🏷️ Бренд'}
                ]
            
            return jsonify({
                'step': 'priorities',
                'message': '⚙️ <b>Выберите 1-3 главных приоритета</b> (важнее сверху):',
                'options': options,
                'type': 'multi_select',
                'max_select': 3
            })
        
        elif step == 'priorities':
            state = last_compared_products.get(wizard_key, {})
            state['priorities'] = user_choice if isinstance(user_choice, list) else [user_choice]
            
            category = state['category']
            budget_map = {
                '20000': 20000,
                '40000': 40000,
                '60000': 60000,
                '100000': 100000,
                'unlimited': 999999
            }
            budget = budget_map.get(state['budget'], 50000)
            
            products = search_products(category.replace('-', ' '))
            filtered = [p for p in products if safe_float(p.get('price_value', 999999)) <= budget][:3]
            
            if len(filtered) >= 2:
                last_compared_products[session_id] = filtered[:2]
                save_chat_session(session_id, conversations.get(session_id, []), filtered[:2])
            
            if filtered:
                result_html = f"🎯 <b>Подобрано {len(filtered)} варианта:</b><br><br>"
                for i, p in enumerate(filtered, 1):
                    p_url = p.get('url', f"/mobileshop/product.php?id={p['id']}")
                    result_html += f"""
                    <div style='border: 1px solid #ddd; border-radius: 8px; padding: 10px; margin: 5px 0;'>
                        <b>{i}. {p['name']}</b><br>
                        💰 {p['price']}<br>
                        ⭐ Рейтинг: {p.get('rating', 'Н/Д')}<br>
                        🔗 <a href="{p_url}" target="_blank">Подробнее →</a>
                    </div>
                    """
                
                result_html += "<br>❓ <b>Что дальше?</b>"
                if len(filtered) >= 2:
                    result_html += '<br>💡 Напишите "что лучше?" — я сравню первые два товара'
            else:
                result_html = "😕 К сожалению, не нашёл товаров по вашим критериям. Попробуйте увеличить бюджет или сменить категорию."
            
            if wizard_key in last_compared_products:
                del last_compared_products[wizard_key]
            
            return jsonify({
                'step': 'result',
                'message': result_html,
                'products': filtered
            })
        
        return jsonify({'error': 'Неизвестный шаг'}), 400
        
    except Exception as e:
        logger.error(f"❌ Ошибка мастера: {str(e)}")
        return jsonify({'error': 'Ошибка сервера'}), 500


@app.route('/api/export-compare')
def export_compare():
    """Экспорт сравнения товаров в CSV"""
    try:
        ids = request.args.get('ids', '').split(',')
        ids = [int(id) for id in ids if id.strip().isdigit()]
        
        if len(ids) < 2:
            return jsonify({'error': 'Нужно минимум 2 товара'}), 400
        
        products = get_products_for_compare(ids)
        if not products or len(products) < 2:
            return jsonify({'error': 'Не удалось загрузить товары'}), 400
        
        output = io.StringIO()
        writer = csv.writer(output, delimiter=';', lineterminator='\n')
        
        headers = ['Характеристика'] + [p.get('name', f'Товар {i+1}')[:40] for i, p in enumerate(products)]
        writer.writerow(headers)
        
        rows = [
            ['Цена'] + [p.get('price', '—') for p in products],
            ['Рейтинг'] + [str(p.get('rating', '—')) for p in products],
            ['Наличие'] + [str(safe_int(p.get('stock'))) + ' шт.' for p in products],
            ['Продано'] + [str(safe_int(p.get('sales_count'))) for p in products],
        ]
        
        all_specs = set()
        for p in products:
            specs = p.get('specs', {})
            if isinstance(specs, dict):
                all_specs.update(specs.keys())
        
        for spec in sorted(all_specs):
            row = [spec.replace('nb_', '').replace('sm_', '').replace('_', ' ').title()]
            for p in products:
                specs = p.get('specs', {}) if isinstance(p.get('specs'), dict) else {}
                val = specs.get(spec, '—')
                if isinstance(val, dict):
                    val = val.get('value', '—')
                row.append(str(val))
            rows.append(row)
        
        for row in rows:
            writer.writerow(row)
        
        writer.writerow([])
        writer.writerow(['РЕКОМЕНДАЦИЯ'])
        rec = generate_recommendation(products)
        rec_text = re.sub(r'<[^>]+>', ' ', rec).replace('&nbsp;', ' ').strip()
        writer.writerow([rec_text])
        
        output.seek(0)
        
        bom = '\ufeff'
        final_output = io.BytesIO((bom + output.getvalue()).encode('utf-8-sig'))
        
        return send_file(
            final_output,
            mimetype='text/csv',
            as_attachment=True,
            download_name=f'compare_{datetime.now().strftime("%Y%m%d_%H%M")}.csv'
        )
        
    except Exception as e:
        logger.error(f"❌ Ошибка экспорта: {str(e)}")
        return jsonify({'error': 'Ошибка при создании файла'}), 500


@app.route('/api/chat', methods=['POST'])
def chat():
    """API endpoint для чат-бота"""
    try:
        data = request.json
        user_message = data.get('message', '').strip()
        session_id = data.get('session_id', request.remote_addr)
        
        logger.info(f"💬 [CHAT] session_id={session_id[:20] if session_id else 'NONE'}...")
        
        # ========== ЗАГРУЗКА ИЗ MYSQL ==========
        if session_id not in conversations:
            history, products = load_chat_session(session_id)
            if history is not None:
                conversations[session_id] = history
                logger.info(f"📥 История загружена: {len(history)} сообщений")
            if products:
                last_compared_products[session_id] = products
                logger.info(f"📥 Товары загружены: {len(products)} шт.")
        # ======================================
        
        if not user_message:
            return jsonify({'response': 'Пожалуйста, введите ваш вопрос.'})
        
        # ========== ОБРАБОТКА ЗАПРОСА "ЧТО ЛУЧШЕ?" ==========
        user_message_lower = user_message.lower().strip()
        is_recommend_request = user_message_lower in [
            'что лучше', 'что лучше?', 'какой лучше', 'какой лучше?', 
            'рекомендуй', 'посоветуй', 'что выбрать', 'что выбрать?',
            'какой выбрать', 'какой выбрать?', 'посоветуй товар'
        ]
        
        if is_recommend_request:
            logger.info(f"💡 Запрос совета для сессии {session_id}")
            
            if session_id in last_compared_products and last_compared_products[session_id]:
                products = last_compared_products[session_id]
                if len(products) >= 2:
                    logger.info(f"💡 Генерирую совет: {products[0].get('name', 'N/A')} vs {products[1].get('name', 'N/A')}")
                    
                    recommendation = generate_recommendation(products)
                    
                    ids = [p['id'] for p in products]
                    compare_url = f"/mobileshop/pages/compare.php?ids={','.join(map(str, ids))}"
                    recommendation += f'<br><br><div style="text-align: center;"><a href="{compare_url}" target="_blank" style="display: inline-block; padding: 8px 15px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;">🔄 Показать полное сравнение</a></div>'
                    
                    return jsonify({'response': recommendation, 'status': 'success'})
                else:
                    return jsonify({'response': 'Сначала выберите товары для сравнения (минимум 2), нажав на кнопку "Сравнить товары" и выбрав их на странице каталога. Затем спросите "что лучше?" 😊', 'status': 'success'})
            else:
                return jsonify({'response': 'Сначала выберите товары для сравнения (минимум 2), нажав на кнопку "Сравнить товары" и выбрав их на странице каталога. Затем спросите "что лучше?" 😊', 'status': 'success'})
        
        # ========== ОБРАБОТКА ЗАПРОСОВ НА СРАВНЕНИЕ ==========
        compare_patterns = [
            r'(?:сравни|сравнить)\s+(.+?)\s+(?:и|с)\s+(.+)',
            r'(?:что лучше|какой лучше)\s+(.+?)\s+(?:или|против)\s+(.+)'
        ]
        
        for pattern in compare_patterns:
            match = re.search(pattern, user_message, re.IGNORECASE | re.UNICODE)
            if match:
                product1_query = match.group(1).strip()
                product2_query = match.group(2).strip()
                
                logger.info(f"🔍 Найдена команда сравнения: '{product1_query}' vs '{product2_query}'")
                
                products1 = search_products(product1_query)
                products2 = search_products(product2_query)
                
                if products1 and products2:
                    p1 = products1[0]
                    p2 = products2[0]
                    
                    products = get_products_for_compare([p1['id'], p2['id']])
                    
                    if products and len(products) >= 2:
                        last_compared_products[session_id] = products
                        save_chat_session(session_id, conversations.get(session_id, []), products)
                        logger.info(f"💾 СОХРАНЕНИЕ: {products[0].get('name', 'N/A')} vs {products[1].get('name', 'N/A')}")
                        
                        response_text = format_compare_response(products, with_recommendation=True)
                        return jsonify({'response': response_text, 'status': 'success'})
                    else:
                        p1_url = f"/mobileshop/product.php?id={p1['id']}"
                        p2_url = f"/mobileshop/product.php?id={p2['id']}"
                        response_text = f"""🔍 **Найденные товары для сравнения:**

<b>📱 {p1['name']}</b> — {p1['price']}
🔗 <a href="{p1_url}" target="_blank">Ссылка на товар</a>

<b>📱 {p2['name']}</b> — {p2['price']}
🔗 <a href="{p2_url}" target="_blank">Ссылка на товар</a>

---
<a href="/mobileshop/pages/compare.php?ids={p1['id']},{p2['id']}" target="_blank" style="display: inline-block; padding: 8px 15px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;">
    📊 Перейти к полному сравнению
</a>"""
                        return jsonify({'response': response_text, 'status': 'success'})
        
        # ========== ОБРАБОТКА ЗАПРОСОВ НА ПОДБОР ==========
        pick_pattern = r'(?:подбери|найди|подобрать|найти)\s+(.+?)\s+до\s+(\d+)'
        match = re.search(pick_pattern, user_message, re.IGNORECASE | re.UNICODE)
        
        if match:
            category = match.group(1).strip()
            budget = int(match.group(2))
            
            products = search_products(category)
            
            if products:
                filtered = [p for p in products if safe_float(p.get('price_value', 0)) <= budget][:3]
                
                if filtered:
                    response_text = f"💰 **Подбор {category} до {budget} ₽:**<br><br>"
                    for p in filtered:
                        p_url = f"/mobileshop/product.php?id={p['id']}"
                        response_text += f"""
<b>📱 {p['name']}</b><br>
💰 Цена: {p['price']}<br>
🔗 <a href="{p_url}" target="_blank">Подробнее</a><br><br>
"""
                    return jsonify({'response': response_text, 'status': 'success'})
        
        # ========== ОБЫЧНАЯ ОБРАБОТКА ЧЕРЕЗ AI ==========
        if session_id not in conversations:
            conversations[session_id] = []
        
        conversations[session_id].append({
            'role': 'user',
            'content': user_message,
            'timestamp': datetime.now().isoformat()
        })
        
        messages = [{"role": "user", "content": user_message}]
        bot_response, error = get_openrouter_response(messages, session_id)
        
        if error:
            bot_response = f"❌ {error}\n\n💡 Проверьте настройки API ключа в файле .env"
        
        conversations[session_id].append({
            'role': 'assistant',
            'content': bot_response,
            'timestamp': datetime.now().isoformat()
        })
        
        if len(conversations[session_id]) > 20:
            conversations[session_id] = conversations[session_id][-20:]
        
        # ========== СОХРАНЕНИЕ В MYSQL ==========
        save_chat_session(session_id, conversations[session_id], last_compared_products.get(session_id))
        # ======================================
        
        return jsonify({
            'response': bot_response,
            'session_id': session_id,
            'status': 'success'
        })
        
    except Exception as e:
        logger.error(f"❌ Ошибка: {str(e)}")
        return jsonify({'response': 'Произошла ошибка. Попробуйте позже.'})


@app.route('/api/clear', methods=['POST'])
def clear_history():
    """Очистка истории"""
    try:
        data = request.json
        session_id = data.get('session_id', request.remote_addr)
        
        logger.info(f"🗑️ Очистка истории для: {session_id[:20] if session_id else 'NONE'}...")
        
        if session_id in conversations:
            conversations[session_id] = []
            logger.info(f"🗑️ История очищена в памяти")
        
        if session_id in last_compared_products:
            del last_compared_products[session_id]
            logger.info(f"🗑️ Товары удалены из памяти")
        
        conn = get_db_connection()
        if conn:
            try:
                cursor = conn.cursor()
                cursor.execute("DELETE FROM chat_sessions WHERE session_id = %s", (session_id,))
                conn.commit()
                logger.info(f"🗑️ Запись удалена из БД")
            finally:
                if conn.is_connected():
                    cursor.close()
                    conn.close()
        
        return jsonify({'status': 'success'})
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/api/test-key')
def test_api_key():
    """Тестирование API ключа"""
    if not OPENROUTER_API_KEY:
        return jsonify({
            'status': 'error',
            'message': 'API ключ не настроен'
        })
    
    test_messages = [{"role": "user", "content": "Привет"}]
    response, error = get_openrouter_response(test_messages)
    
    if error:
        return jsonify({
            'status': 'error',
            'message': error
        })
    else:
        return jsonify({
            'status': 'success',
            'message': 'API ключ работает!',
            'response': response[:100]
        })


@app.route('/api/chat-history', methods=['GET'])
def get_chat_history():
    """Возвращает историю сообщений для сессии"""
    session_id = request.args.get('session_id')
    logger.info(f"📜 Запрос истории для: {session_id[:20] if session_id else 'NONE'}...")

    if not session_id:
        logger.warning("❌ session_id не передан")
        return jsonify({'error': 'session_id required'}), 400

    # Загружаем из MySQL
    messages, products = load_chat_session(session_id)

    if messages is None:
        logger.info(f"📭 История не найдена в БД")
        return jsonify({'messages': []})

    # Фильтруем system-сообщения
    filtered = [m for m in messages if m.get('role') != 'system']

    logger.info(f"📜 Найдено {len(filtered)} сообщений")

    return jsonify({
        'messages': filtered,
        'last_products': products,
        'session_id': session_id[:20] + '...'
    })


if __name__ == '__main__':
    print("=" * 60)
    print("🛍️  ЗАПУСК КОНСУЛЬТАНТА ИНТЕРНЕТ-МАГАЗИНА")
    print("=" * 60)
    print(f"📁 Базовая папка: {basedir}")
    print(f"🔑 API Key: {'✅ Найден' if OPENROUTER_API_KEY else '❌ Не найден'}")
    if OPENROUTER_API_KEY:
        print(f"   Preview: {OPENROUTER_API_KEY[:10]}...")
    print(f"🤖 Модель: {OPENROUTER_MODEL}")
    print(f"🗄️  База данных: MySQL ({DB_CONFIG['database']})")
    print("-" * 60)
    print("🌐 API endpoint: http://localhost:5000/api/chat")
    print("🔑 Тест API: http://localhost:5000/api/test-key")
    print("🎯 Мастер подбора: POST /api/wizard")
    print("📥 Экспорт: GET /api/export-compare?ids=1,2")
    print("=" * 60)
    
    app.run(debug=True, host='0.0.0.0', port=5000)