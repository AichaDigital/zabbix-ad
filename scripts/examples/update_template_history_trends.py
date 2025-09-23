#!/usr/bin/env python3
"""
Script para actualizar los valores de History y Trends en los templates de Zabbix
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
NEW_HISTORY = "7d"  # 7 días en lugar de 31 días
NEW_TRENDS = "30d"  # 30 días en lugar de 365 días

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

def get_templates_with_long_history(api):
    """Obtiene templates que tienen history > 7d o trends > 30d"""
    try:
        # Obtener todos los templates con sus items
        templates = api.template.get(
            output=['templateid', 'name'],
            selectItems=['itemid', 'name', 'key_', 'history', 'trends']
        )
        
        templates_to_update = []
        
        for template in templates:
            items_to_update = []
            
            for item in template.get('items', []):
                history = item.get('history', '')
                trends = item.get('trends', '')
                
                # Verificar si necesita actualización
                needs_update = False
                
                # Convertir valores a días para comparación
                history_days = parse_time_to_days(history)
                trends_days = parse_time_to_days(trends)
                
                if history_days > 7 or trends_days > 30:
                    needs_update = True
                
                if needs_update:
                    items_to_update.append({
                        'itemid': item['itemid'],
                        'name': item['name'],
                        'key_': item.get('key_', ''),
                        'current_history': history,
                        'current_trends': trends,
                        'new_history': NEW_HISTORY if history_days > 7 else history,
                        'new_trends': NEW_TRENDS if trends_days > 30 else trends
                    })
            
            if items_to_update:
                templates_to_update.append({
                    'templateid': template['templateid'],
                    'name': template['name'],
                    'items': items_to_update
                })
        
        return templates_to_update
        
    except Exception as e:
        print(f"❌ Error obteniendo templates: {e}")
        return []

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
        # Asumir que es un número de días
        try:
            return int(time_str)
        except:
            return 0

def update_template_items(api, template):
    """Actualiza los items de un template"""
    print(f"\n📋 Actualizando template: {template['name']}")
    print(f"   Items a actualizar: {len(template['items'])}")
    
    updated_count = 0
    
    for item in template['items']:
        try:
            # Actualizar el item
            api.item.update(
                itemid=item['itemid'],
                history=item['new_history'],
                trends=item['new_trends']
            )
            
            print(f"   ✅ {item['name']}")
            print(f"      History: {item['current_history']} → {item['new_history']}")
            print(f"      Trends:  {item['current_trends']} → {item['new_trends']}")
            
            updated_count += 1
            
        except Exception as e:
            print(f"   ❌ Error actualizando {item['name']}: {e}")
    
    print(f"   📊 Actualizados: {updated_count}/{len(template['items'])} items")
    return updated_count

def main():
    """Función principal"""
    print("🔧 Actualizador de History/Trends para Templates de Zabbix")
    print("=" * 60)
    
    if not ZABBIX_TOKEN:
        print("❌ Error: ZABBIX_TOKEN no está configurado")
        print("   Asegúrate de tener la variable de entorno ZABBIX_TOKEN configurada")
        sys.exit(1)
    
    print(f"🌐 URL: {ZABBIX_URL}")
    print(f"📅 Nuevos valores: History={NEW_HISTORY}, Trends={NEW_TRENDS}")
    
    # Conectar a Zabbix
    api = connect_to_zabbix()
    if not api:
        sys.exit(1)
    
    # Obtener templates que necesitan actualización
    print("\n🔍 Buscando templates con valores largos de History/Trends...")
    templates_to_update = get_templates_with_long_history(api)
    
    if not templates_to_update:
        print("✅ No se encontraron templates que necesiten actualización")
        return
    
    print(f"📋 Se encontraron {len(templates_to_update)} templates que necesitan actualización")
    
    # Mostrar resumen
    total_items = sum(len(t['items']) for t in templates_to_update)
    print(f"📊 Total de items a actualizar: {total_items}")
    
    # Confirmar antes de proceder
    print(f"\n⚠️  ¿Continuar con la actualización? (s/N): ", end="")
    response = input().strip().lower()
    
    if response not in ['s', 'sí', 'si', 'yes', 'y']:
        print("❌ Operación cancelada")
        return
    
    # Actualizar templates
    print(f"\n🚀 Iniciando actualización...")
    total_updated = 0
    
    for template in templates_to_update:
        updated = update_template_items(api, template)
        total_updated += updated
    
    print(f"\n🎉 Actualización completada!")
    print(f"📊 Total de items actualizados: {total_updated}/{total_items}")
    
    # Mostrar resumen de cambios
    print(f"\n📋 Resumen de cambios:")
    print(f"   History: máximo {NEW_HISTORY} (antes hasta 31d)")
    print(f"   Trends:  máximo {NEW_TRENDS} (antes hasta 365d)")
    print(f"\n💡 Esto debería reducir significativamente el uso de espacio en disco")

if __name__ == "__main__":
    main()
