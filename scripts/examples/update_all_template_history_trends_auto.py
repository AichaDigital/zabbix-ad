#!/usr/bin/env python3
"""
Script automático completo para actualizar TODOS los valores de History y Trends en TODOS los templates de Zabbix
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

def update_template_items(api, template_id, template_name):
    """Actualiza los items de un template específico"""
    try:
        # Obtener todos los items del template con history=31d y trends=365d
        items = api.item.get(
            templateids=[template_id],
            output=['itemid', 'name', 'key_', 'history', 'trends'],
            filter={'history': '31d', 'trends': '365d'}
        )
        
        if not items:
            return 0, 0
            
        print(f"\n📋 Template: {template_name}")
        print(f"   Items a actualizar: {len(items)}")
        
        updated_count = 0
        failed_count = 0
        
        for item in items:
            try:
                # Actualizar el item
                result = api.item.update(
                    itemid=item['itemid'],
                    history=NEW_HISTORY,
                    trends=NEW_TRENDS
                )
                
                if result:
                    updated_count += 1
                    print(f"   ✅ {item['name']} ({item['key_']})")
                else:
                    failed_count += 1
                    print(f"   ❌ {item['name']} ({item['key_']}) - Error")
                    
            except Exception as e:
                failed_count += 1
                print(f"   ❌ {item['name']} ({item['key_']}) - Error: {e}")
        
        print(f"   📊 Resultado: {updated_count} actualizados, {failed_count} fallidos")
        return updated_count, failed_count
        
    except Exception as e:
        print(f"   ❌ Error procesando template {template_name}: {e}")
        return 0, 0

def main():
    """Función principal"""
    print("🚀 Iniciando actualización automática completa de History y Trends en templates...")
    print(f"📝 Configuración:")
    print(f"   - History: 31d → {NEW_HISTORY}")
    print(f"   - Trends: 365d → {NEW_TRENDS}")
    
    # Conectar a Zabbix
    api = connect_to_zabbix()
    if not api:
        sys.exit(1)
    
    try:
        # Obtener todos los templates
        print("\n🔍 Obteniendo lista de templates...")
        templates = api.template.get(
            output=['templateid', 'name'],
            selectItems=['itemid', 'name', 'key_', 'history', 'trends']
        )
        
        print(f"📊 Encontrados {len(templates)} templates")
        
        # Filtrar templates que tienen items con history=31d y trends=365d
        templates_to_update = []
        total_items_to_update = 0
        
        for template in templates:
            items = template.get('items', [])
            problematic_items = [item for item in items 
                               if item.get('history') == '31d' and item.get('trends') == '365d']
            
            if problematic_items:
                templates_to_update.append({
                    'templateid': template['templateid'],
                    'name': template['name'],
                    'items_count': len(problematic_items)
                })
                total_items_to_update += len(problematic_items)
        
        print(f"🎯 Templates con items problemáticos: {len(templates_to_update)}")
        print(f"📈 Total de items a actualizar: {total_items_to_update}")
        
        if not templates_to_update:
            print("✅ No hay templates que necesiten actualización")
            return
        
        print(f"\n🔄 Iniciando actualización automática...")
        total_updated = 0
        total_failed = 0
        
        for i, template in enumerate(templates_to_update, 1):
            print(f"\n[{i}/{len(templates_to_update)}] Procesando...")
            updated, failed = update_template_items(
                api, 
                template['templateid'], 
                template['name']
            )
            total_updated += updated
            total_failed += failed
        
        print(f"\n🎉 Actualización completada!")
        print(f"📊 Resumen:")
        print(f"   - Templates procesados: {len(templates_to_update)}")
        print(f"   - Items actualizados: {total_updated}")
        print(f"   - Items fallidos: {total_failed}")
        
    except Exception as e:
        print(f"❌ Error durante la actualización: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()


