# Invoice Tracker — Manual de operación

Sistema local para gestionar tickets de compra y solicitar facturas electrónicas (CFDI) ante los portales de cada establecimiento.

---

## Requisitos instalados

| Herramienta | Versión | Cómo verificar |
|---|---|---|
| Homebrew | 6.x | `brew --version` |
| PHP | 8.5 | `php --version` |
| PostgreSQL | 16 | `psql --version` |
| Node.js | 22.x | `node --version` |
| Playwright + Chromium | 1.45+ | `npx playwright --version` |
| Google Chrome | cualquiera | `/Applications/Google Chrome.app` |

---

## Iniciar el sistema

### 1. Base de datos

PostgreSQL arranca automáticamente con el sistema (servicio de Homebrew). Si no está corriendo:

```bash
# Iniciar
brew services start postgresql@16

# Verificar que está activo
brew services list | grep postgresql

# Detener
brew services stop postgresql@16
```

### 2. Servidor PHP

El servidor PHP **no** arranca automáticamente. Hay que levantarlo cada vez:

```bash
export PATH="/opt/homebrew/bin:$PATH"
php -S localhost:8080 -t /Users/jhano/Desktop/invoice-tracker/ > /tmp/php-server.log 2>&1 &
```

Verifica que esté corriendo:

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/tickets.php
# Debe responder: 200
```

Detenerlo:

```bash
pkill -f "php -S localhost:8080"
```

### 3. Abrir la app

```
http://localhost:8080
```

---

## Reiniciar todo (comando rápido)

Copia y pega esto en la terminal cuando quieras levantar todo desde cero:

```bash
export PATH="/opt/homebrew/bin:/opt/homebrew/opt/postgresql@16/bin:$PATH"
brew services start postgresql@16
pkill -f "php -S localhost:8080" 2>/dev/null
php -S localhost:8080 -t /Users/jhano/Desktop/invoice-tracker/ > /tmp/php-server.log 2>&1 &
echo "✓ Listo en http://localhost:8080"
```

---

## Estructura del proyecto

```
invoice-tracker/
│
├── index.php               → Redirige a tickets.php
├── upload.php              → Subir foto del ticket + extracción con IA
├── tickets.php             → Lista todos los tickets con filtros
├── ticket_detail.php       → Ver un ticket, editar datos, timbrar
├── companies.php           → CRUD de empresas compradoras
├── stamp.php               → Lanza el script de Playwright en background
│
├── api/
│   ├── upload_image.php    → Guarda la imagen subida en /uploads/
│   ├── extract.php         → Llama a Gemini para extraer datos del ticket
│   ├── save_ticket.php     → Inserta el ticket en la base de datos
│   ├── update_ticket.php   → Actualiza los campos de un ticket existente
│   ├── stamp_status.php    → Polling: lee el resultado del script de Playwright
│   └── attach_cfdi.php     → Adjunta XML/PDF manualmente y marca como timbrado
│
├── includes/
│   ├── config.php          → Credenciales DB, API key Gemini, rutas
│   ├── db.php              → Conexión PDO a PostgreSQL
│   ├── layout.php          → Navbar y HTML compartido
│   └── schema.sql          → Esquema de la base de datos (3 tablas)
│
├── scripts/
│   └── stamp_fougasse.js   → Playwright: automatiza el portal de Fougasse
│
├── uploads/                → Imágenes de tickets, screenshots, XML/PDF
├── package.json
└── node_modules/
```

---

## Configuración

### `includes/config.php`

```php
define('DB_HOST',  'localhost');
define('DB_PORT',  '5432');
define('DB_NAME',  'invoice_tracker');
define('DB_USER',  get_current_user());   // usuario del sistema macOS
define('DB_PASS',  '');

define('GEMINI_API_KEY', 'TU_API_KEY_AQUI');
define('GEMINI_MODEL',   'gemini-2.5-flash');

define('UPLOADS_DIR', __DIR__ . '/../uploads/');
define('UPLOADS_URL', '/uploads/');
define('SCRIPTS_DIR', __DIR__ . '/../scripts/');
define('NODE_BIN',    '/usr/local/bin/node');
```

**Gemini API key:** se obtiene en [https://aistudio.google.com/apikey](https://aistudio.google.com/apikey)

---

## Base de datos

### Tablas

**`companies`** — Empresas compradoras (las que solicitan la factura)
- `rfc`, `name`, `email`, `zip_code`, `tax_regime`

**`tickets`** — Un registro por cada ticket de compra
- Datos del establecimiento: `store_name`, `store_rfc`, `ticket_number`, `serie`
- Importes: `subtotal`, `tax`, `total`
- Estado: `status` → `pending` / `stamped` / `failed`
- Archivos: `image_path`, `xml_path`, `pdf_path`

**`stamp_logs`** — Historial de cada intento de timbrado
- `result` → `success` / `error`
- `message`, `screenshot`

### Comandos útiles de base de datos

```bash
export PATH="/opt/homebrew/opt/postgresql@16/bin:$PATH"

# Conectarse
psql invoice_tracker

# Ver todos los tickets
psql invoice_tracker -c "SELECT id, store_name, status, total FROM tickets ORDER BY id;"

# Ver logs de timbrado
psql invoice_tracker -c "SELECT * FROM stamp_logs ORDER BY attempted_at DESC LIMIT 10;"

# Recrear las tablas (borra todo)
psql invoice_tracker -f /Users/jhano/Desktop/invoice-tracker/includes/schema.sql

# Backup
pg_dump invoice_tracker > ~/Desktop/backup_invoice_$(date +%Y%m%d).sql

# Restaurar backup
psql invoice_tracker < ~/Desktop/backup_invoice_YYYYMMDD.sql
```

---

## Flujo de trabajo

### 1. Subir un ticket
1. Ir a **Subir ticket** en la barra de navegación
2. Arrastrar la foto del ticket al área de carga
3. Clic en **"Extraer datos con IA"** — Gemini lee la imagen y llena el formulario
4. Corregir cualquier dato incorrecto
5. Seleccionar la empresa que va a facturar
6. Guardar → redirige al detalle del ticket

### 2. Corregir datos antes de timbrar
- En el detalle del ticket, clic en **"Editar"** (botón amarillo)
- Se abre un modal con todos los campos editables
- Guardar cambios antes de timbrar

### 3. Timbrar automáticamente
- Clic en **"Timbrar ahora"**
- Se abre una pestaña nueva en tu Chrome (o lanza Chrome con debug port si no está abierto)
- El script llena el formulario del portal del establecimiento automáticamente
- El estado del ticket cambia a **Timbrado** o **Fallido**
- Se guarda un screenshot de cada paso en `/uploads/`

### 4. Timbrar manualmente / adjuntar archivos
Si tienes el XML y PDF ya descargados (por correo o manualmente):
- En el detalle del ticket → sección **"Archivos CFDI"**
- Clic en **"Adjuntar archivos"**
- Subir XML y/o PDF
- El ticket se marca automáticamente como **Timbrado**

### 5. Filtrar tickets pendientes del mes
- Ir a **Tickets**
- Filtrar por **Estado: Pendiente** y **Mes: YYYY-MM**
- Ordenados por fecha de compra descendente

---

## Automatización por establecimiento

Cada establecimiento tiene su propio script de Playwright en `/scripts/`:

| Establecimiento | Script |
|---|---|
| Fougasse | `stamp_fougasse.js` |
| _(nuevo)_ | `stamp_<nombre>.js` |

Para agregar un nuevo establecimiento:
1. Crear `scripts/stamp_nombre.js` siguiendo el mismo patrón que `stamp_fougasse.js`
2. Agregar una línea en el `$scriptMap` dentro de `stamp.php`:

```php
$scriptMap = [
    'fougasse' => 'stamp_fougasse.js',
    'oxxo'     => 'stamp_oxxo.js',    // ejemplo
];
```

El nombre en el mapa se compara contra el `store_name` del ticket (en minúsculas, búsqueda parcial).

---

## Chrome y automatización

El script de timbrado abre una **pestaña nueva en tu Chrome** existente usando el protocolo CDP:

1. La primera vez, lanza Chrome con `--remote-debugging-port=9222`
2. Las siguientes veces, se conecta al Chrome ya abierto

> **Importante:** mantén Chrome abierto mientras usas el sistema para que la conexión sea instantánea.

Si el script falla, revisa el screenshot guardado en `/uploads/stamp_<id>_<timestamp>.png` — visible desde el historial de intentos en el detalle del ticket.

---

## Logs y diagnóstico

```bash
# Log del servidor PHP (errores, requests)
tail -f /tmp/php-server.log

# Ver el resultado del último intento de timbrado
ls -lt /tmp/stamp_result_*.json 2>/dev/null | head -3
cat /tmp/stamp_result_<id>.json

# Buscar PDFs generados hoy
find ~/Downloads ~/Desktop -name "*.pdf" -newer /tmp 2>/dev/null

# Verificar que el servidor responde
curl -s -o /dev/null -w "HTTP %{http_code}" http://localhost:8080/tickets.php
```

---

## Límites PHP configurados

Editados en `/opt/homebrew/etc/php/8.5/php.ini`:

| Parámetro | Valor |
|---|---|
| `upload_max_filesize` | 20M |
| `post_max_size` | 25M |

Después de cambiar el ini, reiniciar el servidor PHP.
