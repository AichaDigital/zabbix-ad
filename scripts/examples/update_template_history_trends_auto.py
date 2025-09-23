#!/usr/bin/env python3
"""
Script automático para actualizar los valores de History y Trends en los templates de Zabbix
para entornos de prueba locales.
"""

import json
import sys
import os
from zabbix_utils import ZabbixAPI
from dotenv import load_dotenv

# Cargar variables de entorno desde .env
load_dotenv()

# Configuración desde variables de entorno
ZABBIX_URL = os.getenv('ZABBIX_URL', 'http://localhost:8080')
ZABBIX_TOKEN = os.getenv('ZABBIX_TOKEN')

# Valores recomendados para entornos de prueba
NEW_HISTORY = "7d"   # 7 días en lugar de 31 días
NEW_TRENDS = "30d"   # 30 días en lugar de 365 días

# Límites para ser conservador
MAX_TEMPLATES_TO_UPDATE = 10  # Solo actualizar los 10 templates más problemáticos
MAX_ITEMS_PER_TEMPLATE = 50   # Máximo 50 items por template

def connect_to_zabbix():
    """Conecta a la API de Zabbix usando token"""
    try:
        api = ZabbixAPI(url=ZABBIX_URL)
        api.login(token=ZABBIX_TOKEN)
        print(f"✅ Conectado a Zabbix API versión: {api.api_version()}")
        return api
    except Exception as e:
        print(f"❌ Error conectando a Zabbix: {e}")
        return None

def parse_time_to_days(time_str):
    """Convierte string de tiempo a días (ej: '31d' -> 31)"""
    if not time_str or time_str == '0':
        return 0
    
    time_str = str(time_str).lower()
    
    if time_str.endswith('d'):
        return int(time_str[:-1])
    elif time_str.endswith('w'):
        return int(time_str[:-1]) * 7
    elif time_str.endswith('m'):
        return int(time_str[:-1]) * 30
    elif time_str.endswith('y'):
        return int(time_str[:-1]) * 365
    elif time_str.endswith('h'):
        return int(time_str[:-1]) / 24
    else:
        try:
            return int(time_str)
        except:
            return 0

def get_top_problematic_templates(api):
    """Obtiene los templates más problemáticos limitados"""
    try:
        # Obtener todos los templates con sus items
        templates = api.template.get(
            output=['templateid', 'name'],
            selectItems=['itemid', 'name', 'key_', 'history', 'trends']
        )
        
        templates_with_scores = []
        
        for template in templates:
            items_to_update = []
            
            for item in template.get('items', []):
                history = item.get('history', '')
                trends = item.get('trends', '')
                
                history_days = parse_time_to_days(history)
                trends_days = parse_time_to_days(trends)
                
                # Solo items que realmente necesiten actualización
                needs_history_update = history_days > 7
                needs_trends_update = trends_days > 30
                
                if needs_history_update or needs_trends_update:
                    items_to_update.append({
                        'itemid': item['itemid'],
                        'name': item['name'],
                        'key_': item.get('key_', ''),
                        'current_history': history,
                        'current_trends': trends,
                        'new_history': NEW_HISTORY if needs_history_update else history,
                        'new_trends': NEW_TRENDS if needs_trends_update else trends
                    })
            
            if items_to_update:
                # Limitar items por template
                if len(items_to_update) > MAX_ITEMS_PER_TEMPLATE:
                    items_to_update = items_to_update[:MAX_ITEMS_PER_TEMPLATE]
                
                templates_with_scores.append({
                    'templateid': template['templateid'],
                    'name': template['name'],
                    'items': items_to_update,
                    'score': len(items_to_update)
                })
        
        # Ordenar por score (número de items problemáticos) y tomar solo los top
        templates_with_scores.sort(key=lambda x: x['score'], reverse=True)
        return templates_with_scores[:MAX_TEMPLATES_TO_UPDATE]
        
    except Exception as e:
        print(f"❌ Error obteniendo templates: {e}")
        return []

def update_template_items(api, template):
    """Actualiza los items de un template"""
    print(f"\n📋 Actualizando template: {template['name']}")
    print(f"   Items a actualizar: {len(template['items'])}")
    
    updated_count = 0
    errors = 0
    
    for item in template['items']:
        try:
            # Actualizar el item
            api.item.update(
                itemid=item['itemid'],
                history=item['new_history'],
                trends=item['new_trends']
            )
            
            print(f"   ✅ {item['name'][:60]}")
            if item['current_history'] != item['new_history']:
                print(f"      History: {item['current_history']} → {item['new_history']}")
            if item['current_trends'] != item['new_trends']:
                print(f"      Trends:  {item['current_trends']} → {item['new_trends']}")
            
            updated_count += 1
            
        except Exception as e:
            print(f"   ❌ Error actualizando {item['name'][:60]}: {e}")
            errors += 1
    
    print(f"   📊 Resultado: {updated_count} actualizados, {errors} errores")
    return updated_count, errors

def main():
    """Función principal"""
    print("🔧 Actualizador Automático de History/Trends para Templates de Zabbix")
    print("=" * 70)
    
    if not ZABBIX_TOKEN:
        print("❌ Error: ZABBIX_TOKEN no está configurado")
        sys.exit(1)
    
    print(f"🌐 URL: {ZABBIX_URL}")
    print(f"📅 Nuevos valores: History={NEW_HISTORY}, Trends={NEW_TRENDS}")
    print(f"🎯 Modo conservador: Máximo {MAX_TEMPLATES_TO_UPDATE} templates, {MAX_ITEMS_PER_TEMPLATE} items/template")
    
    # Conectar a Zabbix
    api = connect_to_zabbix()
    if not api:
        sys.exit(1)
    
    # Obtener templates más problemáticos
    print(f"\n🔍 Buscando templates más problemáticos...")
    templates_to_update = get_top_problematic_templates(api)
    
    if not templates_to_update:
        print("✅ No se encontraron templates que necesiten actualización")
        return
    
    print(f"📋 Se seleccionaron {len(templates_to_update)} templates para actualización")
    
    # Mostrar resumen
    total_items = sum(len(t['items']) for t in templates_to_update)
    print(f"📊 Total de items a actualizar: {total_items}")
    
    print(f"\n📋 Templates seleccionados:")
    for i, template in enumerate(templates_to_update, 1):
        print(f"   {i:2d}. {template['name'][:60]:<60} | {template['score']:>3} items")
    
    # Actualizar templates
    print(f"\n🚀 Iniciando actualización...")
    total_updated = 0
    total_errors = 0
    
    for template in templates_to_update:
        updated, errors = update_template_items(api, template)
        total_updated += updated
        total_errors += errors
    
    print(f"\n🎉 Actualización completada!")
    print(f"📊 Total de items actualizados: {total_updated}")
    print(f"❌ Total de errores: {total_errors}")
    print(f"📈 Tasa de éxito: {(total_updated/(total_updated+total_errors)*100):.1f}%")
    
    # Mostrar resumen de cambios
    print(f"\n📋 Resumen de cambios aplicados:")
    print(f"   History: {NEW_HISTORY} (antes hasta 31d)")
    print(f"   Trends:  {NEW_TRENDS} (antes hasta 365d)")
    print(f"\n💡 Esto debería reducir significativamente el uso de espacio en disco")
    print(f"🔍 Puedes ejecutar el script de análisis nuevamente para verificar los cambios")

if __name__ == "__main__":
    main()


