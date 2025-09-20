# Zabbix Management Platform - Documento de Alcance del Proyecto

## 📋 Información del Proyecto

**Nombre:** Zabbix Management Platform  
**Versión:** 1.0.0  
**Framework:** Laravel 12 + Filament 4  
**Fecha:** Septiembre 2024  

## 🎯 Objetivo del Proyecto

Crear una plataforma web moderna para la gestión centralizada de múltiples instancias de Zabbix, aprovechando las capacidades del MCP Zabbix Server existente y proporcionando una interfaz intuitiva para operaciones complejas.

## 🏗️ Arquitectura del Sistema

### Componentes Principales

1. **Frontend Web** - Filament 4 (Laravel 12)
2. **Backend API** - Laravel 12 con servicios especializados
3. **MCP Zabbix Server** - Integración con servidor MCP existente
4. **Base de Datos** - Configuración y auditoría
5. **Agentes en Background** - Procesamiento asíncrono
6. **Testing with Pest 4** - Testing
7. **Coverage** - Coverage minimo 85%
8. **Larastan** - Coe analisis
9. **Github** - Como repositorio git
10. **Redis** - Usando phpredis con socket 

### Entornos Soportados

- **Local** - Zabbix Docker para pruebas (http://localhost:8080)
- **Producción 1** - Servidor Zabbix principal
- **Producción 2** - Servidor Zabbix secundario  
- **Preparación** - Servidor Zabbix en desarrollo

## 📁 Estructura del Proyecto

```
zabbix-management-platform/
├── app/
│   ├── Models/
│   │   ├── ZabbixConnection.php
│   │   ├── ZabbixTemplate.php
│   │   ├── ZabbixHost.php
│   │   └── AuditLog.php
│   ├── Services/
│   │   ├── ZabbixConnectionManager.php
│   │   ├── TemplateOptimizer.php
│   │   ├── HostManager.php
│   │   ├── BackupManager.php
│   │   └── MCPClient.php
│   ├── Jobs/
│   │   ├── OptimizeTemplatesJob.php
│   │   ├── BackupConfigurationJob.php
│   │   └── SyncHostsJob.php
│   ├── Filament/
│   │   ├── Resources/
│   │   │   ├── ZabbixConnectionResource.php
│   │   │   ├── ZabbixTemplateResource.php
│   │   │   └── ZabbixHostResource.php
│   │   ├── Pages/
│   │   │   ├── Dashboard.php
│   │   │   ├── TemplateOptimizer.php
│   │   │   └── BulkOperations.php
│   │   └── Widgets/
│   │       ├── ZabbixStatusWidget.php
│   │       └── TemplateStatsWidget.php
│   └── Console/
│       └── Commands/
│           ├── TestConnectionsCommand.php
│           └── SyncTemplatesCommand.php
├── config/
│   ├── zabbix.php
│   └── mcp.php
├── database/
│   ├── migrations/
│   │   ├── 2024_09_20_000001_create_zabbix_connections_table.php
│   │   ├── 2024_09_20_000002_create_zabbix_templates_table.php
│   │   ├── 2024_09_20_000003_create_zabbix_hosts_table.php
│   │   └── 2024_09_20_000004_create_audit_logs_table.php
│   └── seeders/
│       ├── ZabbixConnectionSeeder.php
│       └── DefaultTemplatesSeeder.php
├── resources/
│   ├── views/
│   └── js/
└── storage/
    ├── backups/
    └── logs/
```

## 🗄️ Modelo de Datos (DBML)

```dbml
Table zabbix_connections {
  id bigint [pk, increment]
  name varchar(255) [not null]
  description text
  url varchar(500) [not null]
  encrypted_token text [not null]
  environment enum('local', 'production', 'staging') [default: 'production']
  is_active boolean [default: true]
  max_requests_per_minute integer [default: 60]
  timeout_seconds integer [default: 30]
  last_connection_test timestamp
  connection_status enum('active', 'inactive', 'error') [default: 'active']
  created_at timestamp
  updated_at timestamp
  
  indexes {
    (environment, is_active)
    (connection_status)
  }
}

Table zabbix_templates {
  id bigint [pk, increment]
  zabbix_connection_id bigint [ref: > zabbix_connections.id]
  template_id varchar(50) [not null]
  name varchar(500) [not null]
  description text
  template_type enum('system', 'custom', 'imported') [default: 'custom']
  items_count integer [default: 0]
  triggers_count integer [default: 0]
  history_retention varchar(20) [default: '7d']
  trends_retention varchar(20) [default: '30d']
  is_optimized boolean [default: false]
  last_sync timestamp
  created_at timestamp
  updated_at timestamp
  
  indexes {
    (zabbix_connection_id, template_id)
    (is_optimized)
    (template_type)
  }
}

Table zabbix_hosts {
  id bigint [pk, increment]
  zabbix_connection_id bigint [ref: > zabbix_connections.id]
  host_id varchar(50) [not null]
  host_name varchar(255) [not null]
  visible_name varchar(255)
  ip_address varchar(45)
  status enum('enabled', 'disabled', 'maintenance') [default: 'enabled']
  available enum('unknown', 'available', 'unavailable') [default: 'unknown']
  templates_count integer [default: 0]
  items_count integer [default: 0]
  last_check timestamp
  last_sync timestamp
  created_at timestamp
  updated_at timestamp
  
  indexes {
    (zabbix_connection_id, host_id)
    (status)
    (available)
  }
}

Table audit_logs {
  id bigint [pk, increment]
  user_id bigint [ref: > users.id]
  zabbix_connection_id bigint [ref: > zabbix_connections.id]
  action varchar(100) [not null]
  resource_type varchar(50) [not null]
  resource_id varchar(100)
  old_values json
  new_values json
  status enum('success', 'failed', 'partial') [not null]
  error_message text
  execution_time_ms integer
  ip_address varchar(45)
  user_agent text
  created_at timestamp
  
  indexes {
    (user_id, created_at)
    (zabbix_connection_id, action)
    (resource_type, resource_id)
    (status)
    (created_at)
  }
}

Table background_jobs {
  id bigint [pk, increment]
  job_type varchar(100) [not null]
  zabbix_connection_id bigint [ref: > zabbix_connections.id]
  parameters json
  status enum('pending', 'running', 'completed', 'failed', 'cancelled') [default: 'pending']
  progress_percentage integer [default: 0]
  started_at timestamp
  completed_at timestamp
  error_message text
  result_data json
  created_at timestamp
  updated_at timestamp
  
  indexes {
    (job_type, status)
    (zabbix_connection_id)
    (created_at)
  }
}

Table template_optimization_rules {
  id bigint [pk, increment]
  name varchar(255) [not null]
  description text
  environment enum('all', 'local', 'production', 'staging') [default: 'all']
  template_pattern varchar(255)
  history_from varchar(20)
  history_to varchar(20)
  trends_from varchar(20)
  trends_to varchar(20)
  is_active boolean [default: true]
  created_at timestamp
  updated_at timestamp
  
  indexes {
    (environment, is_active)
    (template_pattern)
  }
}
```

## 🔧 Funcionalidades Principales

### 1. Gestión de Conexiones Zabbix

**Características:**
- Configuración en base de datos
- Tokens encriptados con setter/getter automáticos
- Test de conectividad automático
- Gestión de múltiples entornos
- Rate limiting configurable

**Modelo de Seguridad:**
```php
class ZabbixConnection extends Model
{
    protected $casts = [
        'encrypted_token' => 'encrypted',
    ];
    
    public function setTokenAttribute($value)
    {
        $this->attributes['encrypted_token'] = encrypt($value);
    }
    
    public function getTokenAttribute()
    {
        return decrypt($this->attributes['encrypted_token']);
    }
}
```

### 2. Optimizador de Templates

**Funcionalidades:**
- Análisis automático de templates
- Reglas de optimización configurables
- Procesamiento en background
- Rollback automático en caso de error
- Reportes detallados

**Reglas de Optimización:**
- History: 31d → 7d (reducción ~78%)
- Trends: 365d → 30d (reducción ~92%)
- Patrones personalizables por entorno

### 3. Gestión de Hosts

**Capacidades:**
- Sincronización masiva
- Creación de hosts en lote
- Aplicación de templates
- Monitoreo de estado
- Agrupación automática

### 4. Dashboard de Monitoreo

**Widgets:**
- Estado de conexiones
- Estadísticas de templates
- Métricas de performance
- Alertas en tiempo real
- Gráficos de tendencias

### 5. Sistema de Auditoría

**Tracking:**
- Todas las operaciones
- Cambios de configuración
- Usuarios y timestamps
- Rollback automático
- Reportes de auditoría

## 🤖 Agentes en Background

### Jobs Implementados

1. **TemplateOptimizerJob**
   - Optimización masiva de templates
   - Procesamiento por lotes
   - Progress tracking
   - Rollback automático

2. **BackupConfigurationJob**
   - Backup automático antes de cambios
   - Compresión y almacenamiento
   - Rotación de backups
   - Verificación de integridad

3. **SyncHostsJob**
   - Sincronización de hosts
   - Detección de cambios
   - Actualización incremental
   - Notificaciones de cambios

4. **ConnectionTestJob**
   - Test periódico de conexiones
   - Detección de problemas
   - Alertas automáticas
   - Métricas de performance

### Configuración de Queue

```php
// config/queue.php
'connections' => [
    'zabbix' => [
        'driver' => 'database',
        'table' => 'background_jobs',
        'queue' => 'zabbix',
        'retry_after' => 300,
        'block_for' => 0,
    ],
],
```

## 🔌 Integración con MCP

### Servicio MCPClient

```php
class MCPClient
{
    public function __construct(private ZabbixConnection $connection) {}
    
    public function analyzeTemplates(): array
    {
        // Llamada al MCP para análisis
    }
    
    public function optimizeTemplates(array $templateIds): array
    {
        // Llamada al MCP para optimización
    }
    
    public function getHosts(): array
    {
        // Llamada al MCP para obtener hosts
    }
}
```

### Path del Proyecto MCP Existente

**Ubicación:** `/Users/abkrim/development/zabbix-mcp-server/`

**Funcionalidades a Integrar:**
- `analyze_template_history_trends.py`
- `update_all_template_history_trends_auto.py`
- API del servidor MCP
- Configuración de conexión

## 🎨 Interfaz de Usuario (Filament)

### Páginas Principales

1. **Dashboard**
   - Vista general del sistema
   - Widgets de estado
   - Métricas clave
   - Alertas recientes

2. **Conexiones Zabbix**
   - CRUD de conexiones
   - Test de conectividad
   - Configuración de entornos
   - Gestión de tokens

3. **Templates**
   - Lista de templates
   - Análisis de optimización
   - Aplicación de reglas
   - Historial de cambios

4. **Hosts**
   - Gestión de hosts
   - Aplicación de templates
   - Monitoreo de estado
   - Operaciones masivas

5. **Optimizador**
   - Configuración de reglas
   - Ejecución de optimizaciones
   - Monitoreo de progreso
   - Resultados detallados

6. **Auditoría**
   - Log de operaciones
   - Filtros avanzados
   - Exportación de reportes
   - Análisis de tendencias

### Componentes Personalizados

- **ZabbixStatusWidget** - Estado de conexiones
- **TemplateStatsWidget** - Estadísticas de templates
- **ProgressTracker** - Seguimiento de jobs
- **ConnectionTester** - Test de conectividad

## 🚀 Comandos Artisan

```bash
# Test de conexiones
php artisan zabbix:test-connections

# Sincronización de templates
php artisan zabbix:sync-templates {connection}

# Optimización masiva
php artisan zabbix:optimize-templates {connection} --dry-run

# Backup de configuración
php artisan zabbix:backup {connection}

# Limpieza de logs
php artisan zabbix:cleanup-logs --days=30
```

## 🔒 Seguridad

### Encriptación de Tokens
- Tokens encriptados en base de datos
- Claves de encriptación rotables
- Setter/Getter automáticos
- Logging de acceso

### Autenticación y Autorización
- Integración con Laravel Auth
- Roles y permisos por entorno
- API tokens para integraciones
- Rate limiting por usuario

### Auditoría
- Log de todas las operaciones
- Tracking de cambios
- IP y User-Agent logging
- Retención configurable

## 📊 Métricas y Monitoreo

### Métricas del Sistema
- Tiempo de respuesta de conexiones
- Tasa de éxito de operaciones
- Uso de recursos
- Errores y excepciones

### Alertas
- Conexiones caídas
- Operaciones fallidas
- Uso excesivo de recursos
- Errores críticos

## 🧪 Testing

### Estrategia de Testing
- Unit tests para servicios
- Feature tests para endpoints
- Integration tests con MCP
- Browser tests para Filament

### Entornos de Testing
- Local con Zabbix Docker
- Staging con datos de prueba
- Mock del MCP para tests unitarios

## 📈 Roadmap

### Fase 1 (MVP)
- [ ] Estructura base del proyecto
- [ ] Gestión de conexiones
- [ ] Optimizador básico de templates
- [ ] Dashboard simple

### Fase 2
- [ ] Sistema completo de auditoría
- [ ] Agentes en background
- [ ] Gestión avanzada de hosts
- [ ] Reportes y exportación

### Fase 3
- [ ] API REST completa
- [ ] Integraciones externas
- [ ] Métricas avanzadas
- [ ] Optimizaciones de performance

## 🛠️ Tecnologías

- **Backend:** Laravel 12, PHP 8.3+
- **Frontend:** Filament 4, Alpine.js, Tailwind CSS
- **Base de Datos:** MySQL 8.0+
- **Queue:** Database Queue con Redis opcional
- **Cache:** Redis
- **Storage:** Local con soporte para S3
- **Testing:** PHPUnit, Pest 4.0
- **CI/CD:** GitHub Actions

## 📝 Consideraciones de Implementación

### Desarrollo Desatendido
- Configuración completa via comandos
- Seeders para datos iniciales
- Migraciones automáticas
- Configuración de entorno simplificada

### Documentación
- README completo
- Documentación de API
- Guías de usuario
- Videos tutoriales

### Mantenimiento
- Logging estructurado
- Monitoreo de errores
- Backup automático
- Actualizaciones seguras

---

**Este documento servirá como base para el desarrollo del proyecto Zabbix Management Platform, proporcionando una guía completa para la implementación con Claude o Cursor de forma desatendida.**
