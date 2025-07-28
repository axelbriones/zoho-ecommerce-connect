# Zoho Sync Core - Plugin Central del Ecosistema

## 📋 Descripción

Zoho Sync Core es el plugin central del ecosistema de sincronización con Zoho para WordPress. Proporciona servicios compartidos, autenticación, logging centralizado y gestión de configuraciones para todos los plugins del ecosistema Zoho Sync.

## 🏗️ Arquitectura del Sistema

### Componentes Principales

```
zoho-sync-core/
├── zoho-sync-core.php              # Plugin principal
├── uninstall.php                   # Script de desinstalación
├── includes/                       # Lógica principal
│   ├── class-core.php              # Coordinador principal
│   ├── class-auth-manager.php      # Gestión de autenticación OAuth2
│   ├── class-settings-manager.php  # Gestión de configuraciones
│   ├── class-logger.php            # Sistema de logging centralizado
│   ├── class-api-client.php        # Cliente API base para Zoho
│   ├── class-dependency-checker.php # Verificación de dependencias
│   ├── class-cron-manager.php      # Gestión de tareas programadas
│   └── class-webhook-handler.php   # Manejo de webhooks de Zoho
├── database/                       # Gestión de base de datos
│   └── class-database-manager.php  # Creación y gestión de tablas
└── languages/                      # Archivos de traducción
    ├── zoho-sync-core.pot          # Template de traducción
    └── zoho-sync-core-es_ES.po     # Traducción al español
```

## 🚀 Instalación y Configuración

### Requisitos del Sistema

- **PHP:** 7.4 o superior
- **WordPress:** 5.0 o superior
- **Extensiones PHP requeridas:**
  - cURL
  - JSON
  - OpenSSL
- **Extensiones PHP recomendadas:**
  - mbstring
  - GD
  - ZIP

### Instalación

1. Subir el directorio `zoho-sync-core` a `/wp-content/plugins/`
2. Activar el plugin desde el panel de administración de WordPress
3. Configurar las credenciales de Zoho en la página de configuración

### Configuración Inicial

```php
// Configuraciones básicas requeridas
define('ZOHO_CLIENT_ID', 'tu_client_id');
define('ZOHO_CLIENT_SECRET', 'tu_client_secret');
define('ZOHO_REGION', 'com'); // com, eu, in, com.au, jp
```

## 🔧 API para Desarrolladores

### Registro de Módulos

```php
// Registrar un módulo en el ecosistema
ZohoSyncCore::register_module('mi-modulo', array(
    'name' => 'Mi Módulo',
    'version' => '1.0.0',
    'description' => 'Descripción del módulo',
    'dependencies' => array('core'),
    'hooks' => array(
        'zoho_sync_data_updated' => 'mi_callback'
    )
));
```

### Sistema de Logging

```php
// Escribir logs desde cualquier módulo
ZohoSyncCore::log('info', 'Mensaje de información', array(
    'contexto' => 'datos adicionales'
), 'mi-modulo');

// Niveles disponibles: emergency, alert, critical, error, warning, notice, info, debug
```

### Gestión de Configuraciones

```php
// Obtener configuración
$valor = ZohoSyncCore::settings()->get('mi_configuracion', 'valor_por_defecto', 'mi-modulo');

// Establecer configuración
ZohoSyncCore::settings()->set('mi_configuracion', $valor, 'mi-modulo');

// Configuración encriptada
ZohoSyncCore::settings()->set('token_secreto', $token, 'mi-modulo', true);
```

### Cliente API

```php
// Realizar request a Zoho API
$api_client = ZohoSyncCore::api();

// GET request
$response = $api_client->get('crm', 'contacts', array('page' => 1));

// POST request
$response = $api_client->post('crm', 'contacts', array(
    'data' => array(
        'First_Name' => 'Juan',
        'Last_Name' => 'Pérez'
    )
));
```

### Autenticación

```php
// Verificar si un servicio está autenticado
$auth_manager = ZohoSyncCore::auth();
$is_authenticated = $auth_manager->is_authenticated('crm', 'com');

// Obtener token de acceso
$access_token = $auth_manager->get_access_token('crm', 'com');

// Refrescar token
$new_tokens = $auth_manager->refresh_access_token('crm', 'com');
```

### Webhooks

```php
// Registrar handler de webhook
$webhook_handler = ZohoSyncCore::instance()->webhook_handler;
$webhook_handler->register_handler('mi-modulo', array(
    'callback' => array($this, 'handle_webhook'),
    'events' => array('create', 'update', 'delete'),
    'description' => 'Maneja webhooks de mi módulo'
));

// Callback del webhook
public function handle_webhook($event, $data, $module) {
    // Procesar webhook
    return array(
        'success' => true,
        'message' => 'Webhook procesado correctamente'
    );
}
```

### Tareas Programadas

```php
// Registrar tarea programada
$cron_manager = ZohoSyncCore::instance()->cron_manager;
$cron_manager->register_task('mi_tarea_hook', array(
    'callback' => array($this, 'ejecutar_tarea'),
    'interval' => 'hourly',
    'description' => 'Mi tarea personalizada',
    'module' => 'mi-modulo'
));
```

## 🗄️ Estructura de Base de Datos

### Tablas Principales

#### `wp_zoho_sync_settings`
Almacena todas las configuraciones del ecosistema.

```sql
CREATE TABLE wp_zoho_sync_settings (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    setting_key varchar(255) NOT NULL,
    setting_value longtext,
    module varchar(100) DEFAULT 'core',
    is_encrypted tinyint(1) DEFAULT 0,
    autoload tinyint(1) DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY setting_key (setting_key)
);
```

#### `wp_zoho_sync_logs`
Sistema de logging centralizado.

```sql
CREATE TABLE wp_zoho_sync_logs (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    module varchar(100) NOT NULL DEFAULT 'core',
    level varchar(20) NOT NULL DEFAULT 'info',
    message text NOT NULL,
    context longtext,
    user_id bigint(20) DEFAULT NULL,
    ip_address varchar(45) DEFAULT NULL,
    user_agent text DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY module_level (module, level),
    KEY created_at (created_at)
);
```

#### `wp_zoho_sync_tokens`
Gestión de tokens OAuth2 de Zoho.

```sql
CREATE TABLE wp_zoho_sync_tokens (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    service varchar(100) NOT NULL,
    region varchar(10) NOT NULL DEFAULT 'com',
    access_token text,
    refresh_token text,
    token_type varchar(50) DEFAULT 'Bearer',
    expires_at datetime,
    scope text,
    is_active tinyint(1) DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY service_region (service, region)
);
```

#### `wp_zoho_sync_modules`
Registro de módulos del ecosistema.

```sql
CREATE TABLE wp_zoho_sync_modules (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    module_name varchar(100) NOT NULL,
    module_slug varchar(100) NOT NULL,
    version varchar(20) DEFAULT '1.0.0',
    is_active tinyint(1) DEFAULT 1,
    last_sync datetime DEFAULT NULL,
    sync_status varchar(50) DEFAULT 'idle',
    error_count int DEFAULT 0,
    last_error text DEFAULT NULL,
    config longtext DEFAULT NULL,
    dependencies text DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY module_slug (module_slug)
);
```

## 🔐 Seguridad

### Características de Seguridad

- **Encriptación de datos sensibles** usando claves únicas
- **Validación CSRF** con nonces en formularios
- **Sanitización** de todos los datos de entrada
- **Control de acceso** basado en capacidades de WordPress
- **Rate limiting** para APIs y webhooks
- **Verificación de firmas** en webhooks
- **Logs de seguridad** para accesos sensibles

### Configuración de Seguridad

```php
// Habilitar verificación de firma en webhooks
ZohoSyncCore::settings()->set('webhook_verify_signature', true);
ZohoSyncCore::settings()->set('webhook_secret_key', 'tu_clave_secreta');

// Configurar IPs permitidas para webhooks
ZohoSyncCore::settings()->set('webhook_allowed_ips', array(
    '192.168.1.100',
    '10.0.0.50'
));

// Configurar rate limiting
ZohoSyncCore::settings()->set('enable_rate_limiting', true);
ZohoSyncCore::settings()->set('rate_limit_requests', 100);
ZohoSyncCore::settings()->set('rate_limit_window', 3600);
```

## 🔄 Hooks y Filtros

### Hooks de Acción

```php
// Sistema completamente inicializado
do_action('zoho_sync_core_system_ready', $system_status);

// Módulo registrado
do_action('zoho_sync_module_registered', $module_slug, $config);

// Token refrescado
do_action('zoho_sync_auth_token_refreshed', $service, $region, $data);

// Webhook procesado
do_action('zoho_sync_webhook_processed', $module, $event, $data, $result);

// Log escrito
do_action('zoho_sync_log_written', $level, $message, $context, $module);
```

### Filtros

```php
// Modificar argumentos de request API
apply_filters('zoho_sync_api_request_args', $args, $service, $endpoint, $method);

// Modificar mensaje de log
apply_filters('zoho_sync_log_message', $message, $level, $module);

// Validar configuración personalizada
apply_filters('zoho_sync_validate_setting', $validation, $key, $value, $module);
```

## 🧪 Testing y Debugging

### Modo Debug

```php
// Habilitar logging detallado
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Configurar nivel de log
ZohoSyncCore::settings()->set('log_level', 'debug');

// Habilitar logging a archivo
ZohoSyncCore::settings()->set('log_to_file', true);
```

### Verificación de Salud del Sistema

```php
// Verificar salud del sistema
$core = ZohoSyncCore::instance()->core;
$health_data = $core->check_system_health();

// Verificar dependencias
$dependency_checker = ZohoSyncCore::instance()->dependency_checker;
$results = $dependency_checker->check_all_dependencies();

// Verificar estado de cron
$cron_manager = ZohoSyncCore::instance()->cron_manager;
$cron_health = $cron_manager->check_cron_health();
```

### Testing de Webhooks

```php
// Probar webhook
$webhook_handler = ZohoSyncCore::instance()->webhook_handler;
$result = $webhook_handler->test_webhook('mi-modulo', 'test_event', array(
    'test_data' => 'valor_de_prueba'
));
```

## 📊 Monitoreo y Estadísticas

### Obtener Estadísticas

```php
// Estadísticas de base de datos
$db_stats = ZohoSyncCore::instance()->database_manager->get_database_stats();

// Estadísticas de API
$api_stats = ZohoSyncCore::api()->get_api_stats();

// Estadísticas de cron
$cron_stats = ZohoSyncCore::instance()->cron_manager->get_cron_stats();

// Estadísticas de webhooks
$webhook_stats = ZohoSyncCore::instance()->webhook_handler->get_webhook_stats();
```

### Información del Ecosistema

```php
// Información completa del ecosistema
$ecosystem_info = ZohoSyncCore::instance()->core->get_ecosystem_info();

// Estado del sistema
$system_status = ZohoSyncCore::instance()->core->get_system_status();

// Módulos registrados
$modules = ZohoSyncCore::instance()->core->get_modules();
```

## 🌐 Internacionalización

El plugin está completamente preparado para internacionalización:

- **Dominio de texto:** `zoho-sync-core`
- **Idiomas soportados:** Español (es_ES)
- **Archivos de traducción:** `/languages/`

### Agregar Nuevas Traducciones

1. Usar el archivo `zoho-sync-core.pot` como base
2. Crear archivo `.po` para el idioma deseado
3. Compilar a `.mo` usando herramientas como Poedit

## 🔧 Mantenimiento

### Limpieza Automática

El sistema incluye limpieza automática diaria:

- Logs antiguos (configurables, por defecto 30 días)
- Optimización de tablas de base de datos
- Verificación de salud del sistema
- Limpieza de transients expirados

### Backup y Restauración

```php
// Exportar configuraciones
$settings = ZohoSyncCore::settings()->export_settings();

// Importar configuraciones
$result = ZohoSyncCore::settings()->import_settings($settings, true);
```

## 🚨 Solución de Problemas

### Problemas Comunes

1. **Error de autenticación:**
   - Verificar credenciales de Zoho
   - Comprobar región configurada
   - Revisar tokens expirados

2. **Problemas de sincronización:**
   - Verificar conectividad a internet
   - Comprobar rate limits
   - Revisar logs de errores

3. **Problemas de rendimiento:**
   - Optimizar configuración de cron
   - Ajustar tamaños de lote
   - Revisar uso de memoria

### Logs de Diagnóstico

```php
// Habilitar logging detallado
ZohoSyncCore::settings()->set('log_level', 'debug');
ZohoSyncCore::settings()->set('log_to_file', true);

// Revisar logs
$logs = ZohoSyncCore::logger()->get_logs(array(
    'level' => 'error',
    'limit' => 50,
    'date_from' => date('Y-m-d H:i:s', strtotime('-24 hours'))
));
```

## 📝 Changelog

### Versión 1.0.0
- Implementación inicial del core
- Sistema de autenticación OAuth2
- Logging centralizado
- Gestión de configuraciones
- Cliente API base
- Sistema de webhooks
- Tareas programadas
- Verificación de dependencias
- Internacionalización completa

## 🤝 Contribución

Para contribuir al desarrollo:

1. Fork del repositorio
2. Crear rama para nueva funcionalidad
3. Implementar cambios con tests
4. Enviar Pull Request

## 📄 Licencia

GPL v2 o posterior. Ver archivo LICENSE para más detalles.

## 🆘 Soporte

Para soporte técnico:
- Crear issue en GitHub
- Consultar documentación
- Revisar logs del sistema

---

**Zoho Sync Core** - Plugin central del ecosistema de sincronización con Zoho para WordPress.