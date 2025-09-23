#!/usr/bin/env python3
"""
Script para analizar los valores de History y Trends en los templates de Zabbix
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
        # Asumir que es un número de días
        try:
            return int(time_str)
        except:
            return 0

def analyze_templates(api):
    """Analiza los templates y sus valores de History/Trends"""
    try:
        # Obtener todos los templates con sus items
        templates = api.template.get(
            output=['templateid', 'name'],
            selectItems=['itemid', 'name', 'key_', 'history', 'trends']
        )
        
        stats = {
            'total_templates': len(templates),
            'templates_with_long_history': 0,
            'templates_with_long_trends': 0,
            'total_items': 0,
            'items_with_long_history': 0,
            'items_with_long_trends': 0,
            'history_values': {},
            'trends_values': {},
            'templates_summary': []
        }
        
        for template in templates:
            template_stats = {
                'name': template['name'],
                'templateid': template['templateid'],
                'total_items': len(template.get('items', [])),
                'long_history_items': 0,
                'long_trends_items': 0
            }
            
            stats['total_items'] += template_stats['total_items']
            
            template_has_long_values = False
            
            for item in template.get('items', []):
                history = item.get('history', '')
                trends = item.get('trends', '')
                
                # Contar valores de history
                if history in stats['history_values']:
                    stats['history_values'][history] += 1
                else:
                    stats['history_values'][history] = 1
                
                # Contar valores de trends
                if trends in stats['trends_values']:
                    stats['trends_values'][trends] += 1
                else:
                    stats['trends_values'][trends] = 1
                
                # Verificar si necesita actualización
                history_days = parse_time_to_days(history)
                trends_days = parse_time_to_days(trends)
                
                if history_days > 7:
                    stats['items_with_long_history'] += 1
                    template_stats['long_history_items'] += 1
                    template_has_long_values = True
                
                if trends_days > 30:
                    stats['items_with_long_trends'] += 1
                    template_stats['long_trends_items'] += 1
                    template_has_long_values = True
            
            if template_has_long_values:
                stats['templates_summary'].append(template_stats)
                
                if template_stats['long_history_items'] > 0:
                    stats['templates_with_long_history'] += 1
                if template_stats['long_trends_items'] > 0:
                    stats['templates_with_long_trends'] += 1
        
        return stats
        
    except Exception as e:
        print(f"❌ Error analizando templates: {e}")
        return None

def main():
    """Función principal"""
    print("📊 Analizador de History/Trends para Templates de Zabbix")
    print("=" * 60)
    
    if not ZABBIX_TOKEN:
        print("❌ Error: ZABBIX_TOKEN no está configurado")
        sys.exit(1)
    
    print(f"🌐 URL: {ZABBIX_URL}")
    
    # Conectar a Zabbix
    api = connect_to_zabbix()
    if not api:
        sys.exit(1)
    
    # Analizar templates
    print("\n🔍 Analizando templates...")
    stats = analyze_templates(api)
    
    if not stats:
        sys.exit(1)
    
    # Mostrar resumen
    print(f"\n📋 RESUMEN GENERAL")
    print(f"   Total de templates: {stats['total_templates']}")
    print(f"   Total de items: {stats['total_items']}")
    print(f"   Templates con History > 7d: {stats['templates_with_long_history']}")
    print(f"   Templates con Trends > 30d: {stats['templates_with_long_trends']}")
    print(f"   Items con History > 7d: {stats['items_with_long_history']}")
    print(f"   Items con Trends > 30d: {stats['items_with_long_trends']}")
    
    # Mostrar distribución de valores de History
    print(f"\n📅 DISTRIBUCIÓN DE VALORES DE HISTORY")
    for value, count in sorted(stats['history_values'].items()):
        percentage = (count / stats['total_items']) * 100
        print(f"   {value:>6}: {count:>6} items ({percentage:>5.1f}%)")
    
    # Mostrar distribución de valores de Trends
    print(f"\n📈 DISTRIBUCIÓN DE VALORES DE TRENDS")
    for value, count in sorted(stats['trends_values'].items()):
        percentage = (count / stats['total_items']) * 100
        print(f"   {value:>6}: {count:>6} items ({percentage:>5.1f}%)")
    
    # Mostrar templates más problemáticos
    print(f"\n⚠️  TOP 10 TEMPLATES CON MÁS ITEMS PROBLEMÁTICOS")
    sorted_templates = sorted(stats['templates_summary'], 
                             key=lambda x: x['long_history_items'] + x['long_trends_items'], 
                             reverse=True)
    
    for i, template in enumerate(sorted_templates[:10]):
        total_problematic = template['long_history_items'] + template['long_trends_items']
        print(f"   {i+1:2d}. {template['name'][:50]:<50} | "
              f"H:{template['long_history_items']:>3} T:{template['long_trends_items']:>3} "
              f"(Total: {total_problematic})")
    
    # Recomendaciones
    print(f"\n💡 RECOMENDACIONES")
    print(f"   Para entornos de prueba, considera cambiar:")
    print(f"   • History: 31d → 7d (reducción de ~78% en almacenamiento)")
    print(f"   • Trends: 365d → 30d (reducción de ~92% en almacenamiento)")
    print(f"   • Esto afectaría a {stats['items_with_long_history']} items de history")
    print(f"   • Y a {stats['items_with_long_trends']} items de trends")

if __name__ == "__main__":
    main()


