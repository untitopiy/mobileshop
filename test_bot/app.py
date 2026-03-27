import os
import json
import logging
import requests
import re
from flask import Flask, request, jsonify
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
OPENROUTER_MODEL = "stepfun/step-3.5-flash:free"

# Хранилище диалогов и последних сравненных товаров
conversations = {}
last_compared_products = {}  # {session_id: [product1, product2]}


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


def generate_simple_recommendation(products):
    """Генерирует простой совет на основе базовой информации"""
    if len(products) < 2:
        return ""
    
    p1 = products[0]
    p2 = products[1]
    
    # Извлекаем базовые характеристики
    p1_price = p1.get('price_value', 0)
    p2_price = p2.get('price_value', 0)
    p1_rating = p1.get('rating', 0)
    p2_rating = p2.get('rating', 0)
    p1_stock = p1.get('stock', 0)
    p2_stock = p2.get('stock', 0)
    p1_sales = p1.get('sales_count', 0)
    p2_sales = p2.get('sales_count', 0)
    
    # Формируем совет
    recommendation = []
    p1_score = 0
    p2_score = 0
    
    # Сравнение цены
    if p1_price and p2_price:
        if p1_price < p2_price:
            p1_score += 1
            recommendation.append(f"💰 **Цена**: {p1['name']} дешевле на {p2_price - p1_price:.0f} ₽")
        elif p2_price < p1_price:
            p2_score += 1
            recommendation.append(f"💰 **Цена**: {p2['name']} дешевле на {p1_price - p2_price:.0f} ₽")
    
    # Сравнение рейтинга
    if p1_rating > p2_rating:
        p1_score += 1
        recommendation.append(f"⭐ **Рейтинг**: {p1['name']} имеет более высокий рейтинг ({p1_rating} vs {p2_rating})")
    elif p2_rating > p1_rating:
        p2_score += 1
        recommendation.append(f"⭐ **Рейтинг**: {p2['name']} имеет более высокий рейтинг ({p2_rating} vs {p1_rating})")
    
    # Сравнение популярности
    if p1_sales > p2_sales:
        p1_score += 1
        recommendation.append(f"🔥 **Популярность**: {p1['name']} купили больше раз ({p1_sales} vs {p2_sales})")
    elif p2_sales > p1_sales:
        p2_score += 1
        recommendation.append(f"🔥 **Популярность**: {p2['name']} купили больше раз ({p2_sales} vs {p1_sales})")
    
    # Сравнение наличия
    if p1_stock > 0 and p2_stock == 0:
        p1_score += 1
        recommendation.append(f"📦 **Наличие**: {p1['name']} есть в наличии")
    elif p2_stock > 0 and p1_stock == 0:
        p2_score += 1
        recommendation.append(f"📦 **Наличие**: {p2['name']} есть в наличии")
    
    # Определяем победителя
    if p1_score > p2_score:
        winner = p1['name']
        verdict = f"🏆 **Мой вердикт**: **{winner}** выглядит более выгодным выбором! ✅"
        if p1_price and p2_price and p1_price > p2_price:
            verdict += f" Да, он дороже, но его преимущества оправдывают цену."
        elif p1_price and p2_price and p1_price < p2_price:
            verdict += f" При этом он еще и дешевле — отличное соотношение цены и качества!"
    elif p2_score > p1_score:
        winner = p2['name']
        verdict = f"🏆 **Мой вердикт**: **{winner}** выглядит более выгодным выбором! ✅"
        if p1_price and p2_price and p2_price > p1_price:
            verdict += f" Да, он дороже, но его преимущества оправдывают цену."
        elif p1_price and p2_price and p2_price < p1_price:
            verdict += f" При этом он еще и дешевле — отличное соотношение цены и качества!"
    else:
        verdict = f"🤔 **Мой вердикт**: Оба товара примерно равны по характеристикам. Выбор зависит от ваших личных предпочтений!"
        if p1_price and p2_price and p1_price < p2_price:
            verdict += f" Если хотите сэкономить — выбирайте **{p1['name']}**."
        elif p1_price and p2_price and p2_price < p1_price:
            verdict += f" Если хотите сэкономить — выбирайте **{p2['name']}**."
    
    # Формируем итоговый совет
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


def generate_recommendation(products):
    """Генерирует совет на основе характеристик товаров"""
    if len(products) < 2:
        return ""
    
    p1 = products[0]
    p2 = products[1]
    
    # Извлекаем ключевые характеристики
    p1_price = p1.get('price_value', 0)
    p2_price = p2.get('price_value', 0)
    p1_rating = p1.get('rating', 0)
    p2_rating = p2.get('rating', 0)
    p1_stock = p1.get('stock', 0)
    p2_stock = p2.get('stock', 0)
    p1_sales = p1.get('sales_count', 0)
    p2_sales = p2.get('sales_count', 0)
    
    # Получаем характеристики из specs
    p1_specs = p1.get('specs', {})
    p2_specs = p2.get('specs', {})
    
    # Для смартфонов
    p1_ram = None
    p2_ram = None
    
    # Извлекаем значения из specs
    for key, value in p1_specs.items():
        if isinstance(value, dict):
            val = value.get('value', '')
        else:
            val = str(value)
        
        if 'ram' in key.lower() or ('память' in key.lower() and 'оператив' in key.lower()):
            p1_ram = val
    
    for key, value in p2_specs.items():
        if isinstance(value, dict):
            val = value.get('value', '')
        else:
            val = str(value)
        
        if 'ram' in key.lower() or ('память' in key.lower() and 'оператив' in key.lower()):
            p2_ram = val
    
    # Формируем совет
    recommendation = []
    p1_score = 0
    p2_score = 0
    
    # Сравнение цены
    if p1_price < p2_price:
        p1_score += 1
        recommendation.append(f"💰 **Цена**: {p1['name']} дешевле на {p2_price - p1_price:.0f} ₽")
    elif p2_price < p1_price:
        p2_score += 1
        recommendation.append(f"💰 **Цена**: {p2['name']} дешевле на {p1_price - p2_price:.0f} ₽")
    
    # Сравнение рейтинга
    if p1_rating > p2_rating:
        p1_score += 1
        recommendation.append(f"⭐ **Рейтинг**: {p1['name']} имеет более высокий рейтинг ({p1_rating} vs {p2_rating})")
    elif p2_rating > p1_rating:
        p2_score += 1
        recommendation.append(f"⭐ **Рейтинг**: {p2['name']} имеет более высокий рейтинг ({p2_rating} vs {p1_rating})")
    
    # Сравнение ОЗУ
    if p1_ram and p2_ram:
        try:
            p1_ram_num = int(re.search(r'\d+', str(p1_ram)).group())
            p2_ram_num = int(re.search(r'\d+', str(p2_ram)).group())
            if p1_ram_num > p2_ram_num:
                p1_score += 1
                recommendation.append(f"💾 **Оперативная память**: {p1['name']} имеет больше ОЗУ ({p1_ram})")
            elif p2_ram_num > p1_ram_num:
                p2_score += 1
                recommendation.append(f"💾 **Оперативная память**: {p2['name']} имеет больше ОЗУ ({p2_ram})")
        except:
            pass
    
    # Сравнение продаж (популярность)
    if p1_sales > p2_sales:
        p1_score += 1
        recommendation.append(f"🔥 **Популярность**: {p1['name']} купили больше раз ({p1_sales} vs {p2_sales})")
    elif p2_sales > p1_sales:
        p2_score += 1
        recommendation.append(f"🔥 **Популярность**: {p2['name']} купили больше раз ({p2_sales} vs {p1_sales})")
    
    # Сравнение наличия
    if p1_stock > 0 and p2_stock == 0:
        p1_score += 1
        recommendation.append(f"📦 **Наличие**: {p1['name']} есть в наличии, а {p2['name']} отсутствует")
    elif p2_stock > 0 and p1_stock == 0:
        p2_score += 1
        recommendation.append(f"📦 **Наличие**: {p2['name']} есть в наличии, а {p1['name']} отсутствует")
    
    # Определяем победителя
    if p1_score > p2_score:
        winner = p1['name']
        verdict = f"🏆 **Мой вердикт**: **{winner}** выглядит более выгодным выбором! ✅"
        if p1_price > p2_price:
            verdict += f" Да, он дороже, но его преимущества оправдывают цену."
        elif p1_price < p2_price:
            verdict += f" При этом он еще и дешевле — отличное соотношение цены и качества!"
    elif p2_score > p1_score:
        winner = p2['name']
        verdict = f"🏆 **Мой вердикт**: **{winner}** выглядит более выгодным выбором! ✅"
        if p2_price > p1_price:
            verdict += f" Да, он дороже, но его преимущества оправдывают цену."
        elif p2_price < p1_price:
            verdict += f" При этом он еще и дешевле — отличное соотношение цены и качества!"
    else:
        verdict = f"🤔 **Мой вердикт**: Оба товара примерно равны по характеристикам. Выбор зависит от ваших личных предпочтений!"
        if p1_price < p2_price:
            verdict += f" Если хотите сэкономить — выбирайте **{p1['name']}**."
        elif p2_price < p1_price:
            verdict += f" Если хотите сэкономить — выбирайте **{p2['name']}**."
    
    # Формируем итоговый совет
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
    """Форматирует ответ с таблицей сравнения на основе характеристик из БД"""
    if len(products) < 2:
        return "Недостаточно товаров для сравнения."
    
    # Важные характеристики для отображения (приоритетные)
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
    
    # Собираем все уникальные характеристики из товаров
    all_specs = set()
    for p in products:
        for spec_key in p.get('specs', {}):
            all_specs.add(spec_key)
    
    # Сортируем: сначала приоритетные, затем остальные
    sorted_specs = [s for s in priority_specs if s in all_specs]
    sorted_specs += sorted([s for s in all_specs if s not in priority_specs])
    
    # Формируем HTML таблицу
    html = '<div style="overflow-x: auto; max-width: 100%;">'
    html += '<table style="width: 100%; border-collapse: collapse; font-size: 13px; background: white; border-radius: 12px; overflow: hidden;">'
    
    # Заголовок таблицы
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
    
    # Цена
    html += '<tr style="background: #f8f9fa;">'
    html += '<td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">💰 Цена</td>'
    for p in products:
        html += f'<td style="padding: 10px; border: 1px solid #ddd; text-align: center;"><span style="color: #28a745; font-weight: bold;">{p["price"]}</span></td>'
    html += '</tr>'
    
    # Рейтинг
    if any(p.get('rating', 0) > 0 for p in products):
        html += '<tr>'
        html += '<td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">⭐ Рейтинг</td>'
        for p in products:
            stars = ''
            rating = p.get('rating', 0)
            full = int(rating)
            half = 1 if rating - full >= 0.5 else 0
            empty = 5 - full - half
            stars = '★' * full + '½' * half + '☆' * empty
            html += f'<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">{stars}<br><small>{rating}</small></td>'
        html += '</tr>'
    
    # Наличие
    html += '<tr style="background: #f8f9fa;">'
    html += '<td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">📦 Наличие</td>'
    for p in products:
        stock = p.get('stock', 0)
        stock_text = f'✅ {stock} шт.' if stock > 0 else '❌ Нет в наличии'
        stock_color = '#28a745' if stock > 0 else '#dc3545'
        html += f'<td style="padding: 10px; border: 1px solid #ddd; text-align: center; color: {stock_color};">{stock_text}</td>'
    html += '</tr>'
    
    # Продажи
    html += '<tr>'
    html += '<td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">📈 Продано</td>'
    for p in products:
        html += f'<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">{p.get("sales_count", 0)} шт.</td>'
    html += '</tr>'
    
    # Характеристики из БД
    for spec_key in sorted_specs:
        spec_name = ''
        for p in products:
            if spec_key in p.get('specs', {}):
                if isinstance(p['specs'][spec_key], dict):
                    spec_name = p['specs'][spec_key].get('name', spec_key)
                else:
                    spec_name = spec_key.replace('nb_', '').replace('sm_', '').replace('hp_', '').replace('ch_', '').replace('gl_', '')
                    spec_name = spec_name.replace('_', ' ').title()
                break
        
        if not spec_name:
            spec_name = spec_key.replace('_', ' ').title()
        
        html += '<tr style="background: #f8f9fa;">'
        html += f'<td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">📌 {spec_name}</td>'
        for p in products:
            value = p.get('specs', {}).get(spec_key, '—')
            if isinstance(value, dict):
                value = value.get('value', '—')
            html += f'<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">{value}</td>'
        html += '</tr>'
    
    html += '</tbody></table></div>'
    
    # Добавляем совет эксперта
    if with_recommendation:
        recommendation_html = generate_recommendation(products)
        html += recommendation_html
    
    # Добавляем кнопку для перехода к полному сравнению
    ids = [p['id'] for p in products]
    compare_url = f"/mobileshop/pages/compare.php?ids={','.join(map(str, ids))}"
    html += f'<br><div style="text-align: center;"><a href="{compare_url}" target="_blank" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;">📊 Открыть полное сравнение на сайте</a></div>'
    
    return html


# 🌟 СИСТЕМНЫЙ ПРОМТ
SYSTEM_PROMPT = """Ты — профессиональный и дружелюбный консультант интернет-магазина техники. Твоя специализация — помощь покупателям в выборе товаров и их сравнении.

Ты имеешь доступ к реальному каталогу товаров магазина. Ты можешь:
1. Искать товары по названию, бренду
2. Сравнивать товары по характеристикам из базы данных
3. Рекомендовать товары под бюджет и потребности
4. Давать честные советы какой товар лучше купить

Всегда будь полезным и помогай пользователю сделать правильный выбор!"""


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
                return result['choices'][0]['message']['content'], None
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
            # Получаем детальные характеристики товаров из БД
            product_ids = [p['id'] for p in products]
            detailed_products = get_products_for_compare(product_ids)
            
            if detailed_products and len(detailed_products) >= 2:
                last_compared_products[session_id] = detailed_products
                logger.info(f"💾 СОХРАНЕНИЕ из мини-панели для сессии {session_id}: {len(detailed_products)} товаров")
                return jsonify({'status': 'success', 'message': 'Товары сохранены'})
            else:
                # Если не удалось получить детали, сохраняем базовую информацию
                last_compared_products[session_id] = products
                logger.info(f"💾 СОХРАНЕНИЕ (базовое) для сессии {session_id}: {len(products)} товаров")
                return jsonify({'status': 'success', 'message': 'Товары сохранены (базовая информация)'})
        else:
            return jsonify({'status': 'error', 'message': 'Недостаточно товаров'})
            
    except Exception as e:
        logger.error(f"❌ Ошибка сохранения товаров: {str(e)}")
        return jsonify({'status': 'error', 'message': str(e)}), 500


@app.route('/api/chat', methods=['POST'])
def chat():
    """API endpoint для чат-бота"""
    try:
        data = request.json
        user_message = data.get('message', '').strip()
        session_id = data.get('session_id', request.remote_addr)
        
        # Логируем session_id для отладки
        logger.info(f"💬 Сессия: {session_id}, Сообщение: {user_message[:50]}...")
        
        # ========== ОТЛАДКА ==========
        logger.info(f"🔍 Содержимое last_compared_products: {last_compared_products}")
        logger.info(f"🔍 Текущая сессия в last_compared_products: {session_id in last_compared_products}")
        if session_id in last_compared_products:
            logger.info(f"🔍 Сохраненные товары: {last_compared_products[session_id][0]['name']} vs {last_compared_products[session_id][1]['name']}")
        # ==========================
        
        if not user_message:
            return jsonify({'response': 'Пожалуйста, введите ваш вопрос.'})
        
        # ========== ОБРАБОТКА ЗАПРОСА "ЧТО ЛУЧШЕ?" (без указания товаров) ==========
        # Проверяем разные варианты запроса
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
                    logger.info(f"💡 Генерирую совет по товарам: {products[0]['name']} vs {products[1]['name']}")
                    
                    # Проверяем, есть ли у товаров детальные характеристики
                    if len(products) >= 2 and 'specs' in products[0]:
                        # Используем детальный анализ
                        recommendation = generate_recommendation(products)
                    else:
                        # Используем простой анализ
                        recommendation = generate_simple_recommendation(products)
                    
                    # Добавляем кнопку для повторного сравнения
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
                    
                    # Получаем детальные характеристики из БД
                    products = get_products_for_compare([p1['id'], p2['id']])
                    
                    if products and len(products) >= 2:
                        # Сохраняем последние сравненные товары для этой сессии
                        last_compared_products[session_id] = products
                        logger.info(f"💾 СОХРАНЕНИЕ: Товары для сессии {session_id}: {products[0]['name']} vs {products[1]['name']}")
                        logger.info(f"💾 Всего сохранено: {len(last_compared_products)} сессий")
                        
                        # Формируем ответ с таблицей и советом
                        response_text = format_compare_response(products, with_recommendation=True)
                        return jsonify({'response': response_text, 'status': 'success'})
                    else:
                        # Если не удалось получить детали, показываем простую ссылку
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
                filtered = [p for p in products if p.get('price_value', 0) <= budget][:3]
                
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
        
        if session_id in conversations:
            conversations[session_id] = []
            logger.info(f"🗑️ История очищена для {session_id}")
        
        if session_id in last_compared_products:
            del last_compared_products[session_id]
            logger.info(f"🗑️ Удалены сохраненные товары для сессии {session_id}")
        
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


if __name__ == '__main__':
    print("=" * 60)
    print("🛍️  ЗАПУСК КОНСУЛЬТАНТА ИНТЕРНЕТ-МАГАЗИНА")
    print("=" * 60)
    print(f"📁 Базовая папка: {basedir}")
    print(f"🔑 API Key: {'✅ Найден' if OPENROUTER_API_KEY else '❌ Не найден'}")
    if OPENROUTER_API_KEY:
        print(f"   Preview: {OPENROUTER_API_KEY[:10]}...")
    print(f"🤖 Модель: {OPENROUTER_MODEL}")
    print("-" * 60)
    print("🌐 API endpoint: http://localhost:5000/api/chat")
    print("🔑 Тест API: http://localhost:5000/api/test-key")
    print("=" * 60)
    
    app.run(debug=True, host='0.0.0.0', port=5000)