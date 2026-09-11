# Guía Oficial de Despliegue en Producción y Preparación para Nube

## Plataforma Descartables Peruanos S.A.C.

Esta guía técnica describe el procedimiento estándar para desplegar la plataforma **Descartables Peruanos** en servidores de hosting compartido (cPanel, InfinityFree), servidores virtuales privados (VPS) o infraestructura en la nube (AWS, DigitalOcean, Google Cloud).

---

## 1. Requisitos del Sistema

### 1.1 Entorno de Ejecución PHP
- **Versión:** PHP >= 8.0 (Recomendado: PHP 8.1 o PHP 8.2).
- **Extensiones Obligatorias:**
  - `pdo_mysql`: Conexión segura con MySQL mediante sentencias preparadas.
  - `gd` o `imagick`: Procesamiento y optimización de imágenes en el catálogo.
  - `zip`: Empaquetado y descompresión de copias de seguridad de recuperación ante desastres (DR).
  - `json`: Codificación y decodificación estricta de respuestas de la API.
  - `mbstring`: Manejo de caracteres multilingües y normalización UTF-8.
  - `openssl`: Criptografía para verificación de tokens HMAC y firmas seguras.
  - `filter`: Validación y sanitización estricta de entradas de usuario.

### 1.2 Base de Datos MySQL / MariaDB
- **Versión:** MySQL >= 8.0 o MariaDB >= 10.5.
- **Motor Obligatorio:** `ENGINE=InnoDB` en todas las tablas (imprescindible para transacciones ACID, bloqueo de filas `SELECT ... FOR UPDATE` y soporte de secuencias seguras).
- **Juego de Caracteres:** `utf8mb4` con cotejamiento `utf8mb4_unicode_ci` (soporte completo de caracteres peruanos, tildes y símbolos).

---

## 2. Configuración Privada y Variables de Entorno

### 2.1 Principio de Privacidad
**NUNCA exponga credenciales de base de datos, contraseñas maestras ni llaves de firma en archivos públicos del repositorio o dentro de `public_html`.**

### 2.2 Opción Recomendada: `secrets.php` fuera de la raíz pública
Si el hosting lo permite (cPanel, VPS, Cloud), coloque el archivo `secrets.php` **un nivel arriba** de la raíz pública (`public_html` o `htdocs`):

```text
/home/usuario/
├── secrets.php                  <-- UBICACIÓN PRIVADA SEGURA (Permisos 600 o 640)
└── public_html/                 <-- RAÍZ PÚBLICA DE LA WEB (dist/package)
    ├── api/
    ├── assets/
    └── index.html
```

La clase `Vault` de la API buscará automáticamente en:
1. Ruta definida en la variable de entorno `SECRETS_FILE_PATH`.
2. `dirname(__DIR__, 2) . '/secrets.php'` (dos niveles arriba).
3. `dirname(__DIR__) . '/secrets.php'` (un nivel arriba).
4. `api/secrets.php` (protegido por `.htaccess`).

### 2.3 Opción Alternativa: Variables de Entorno del Servidor
En entornos tipo Cloud (Docker, Heroku, AWS ECS, VPS con PHP-FPM o Apache `SetEnv`), declare las siguientes variables de entorno del sistema:

| Variable | Descripción | Ejemplo |
|---|---|---|
| `APP_ENV` | Entorno de la aplicación (`production` o `development`) | `production` |
| `DB_HOST` | Host del servidor MySQL | `localhost` o `sql201.epizy.com` |
| `DB_PORT` | Puerto de conexión MySQL | `3306` |
| `DB_NAME` | Nombre de la base de datos | `empresa_descartables` |
| `DB_USER` | Usuario de MySQL con privilegios mínimos | `usr_descartables` |
| `DB_PASS` | Contraseña robusta del usuario de BD | `SecretPassword#2026` |
| `TOKEN_SALT` | Clave secreta para firma HMAC de tokens de sesión | Clave aleatoria >= 32 caracteres |
| `ALLOWED_ORIGINS` | Lista de orígenes autorizados para CORS (separados por coma) | `https://descartablesperuanos.pe` |
| `LOG_DIR` | Ruta del directorio de logs privados (opcional) | `/var/log/descartables` |
| `HEALTH_KEY` | Llave secreta para healthcheck profundo (opcional) | Clave alfanumérica secreta |

> [!IMPORTANT]
> En hosting de producción, defina siempre `APP_ENV=production`. Nunca active debug detallado en producción para evitar filtraciones de trazas internas o SQL.

---

## 3. Permisos de Archivos en Servidores Linux / cPanel

Aplique la siguiente matriz de permisos para garantizar el principio de mínimo privilegio:

```bash
# 1. Permisos base para archivos y directorios del aplicativo
find public_html -type d -exec chmod 755 {} \;
find public_html -type f -exec chmod 644 {} \;

# 2. Archivo de configuración privada (solo lectura para el usuario web)
chmod 600 secrets.php

# 3. Directorio de subida de imágenes (escritura para PHP, ejecución deshabilitada)
chmod 755 public_html/assets/images/productos

# 4. Directorio de logs privados (escritura para PHP, bloqueado por .htaccess)
chmod 750 logs/

# 5. Directorio de copias de seguridad (fuera de public_html o bloqueado)
chmod 700 backups/
```

---

## 4. Pipeline de Compilación y Generación del Paquete

Antes de subir al servidor de producción, compile los assets de Tailwind CSS y genere el paquete limpio distribuible:

```powershell
# 1. Instalar dependencias locales de desarrollo
npm install

# 2. Compilar Tailwind CSS minificado para producción
npm run build:css

# 3. Ejecutar pruebas automatizadas locales
npm test

# 4. Generar paquete de despliegue en dist/package/
node scripts/build_package.js
```

El script `scripts/build_package.js` genera una carpeta limpia `dist/package/` que:
- **Incluye:** `assets/css/tailwind.css` compilado (607 KB), HTMLs con enlaces locales, `api/` seguro y migraciones protegidas.
- **Excluye:** `node_modules/`, `package.json`, `package-lock.json`, `tailwind.config.js`, `tailwind-input.css`, `logs/`, `backups/`, `docs/`, `scripts/`, subidas reales de prueba (`prod_*`) y archivos de secretos (`secrets.php`, `.env`).

---

## 5. Ejecución de Migraciones en la Base de Datos

### Opción A: Mediante Línea de Comandos (CLI en VPS / SSH)
Si cuenta con acceso SSH al servidor:

```bash
# Consultar el estado de las migraciones
php database/migrate.php --status

# Aplicar migraciones pendientes secuencialmente
php database/migrate.php
```

### Opción B: Mediante phpMyAdmin / Interfaz Web de Hosting
En hostings compartidos sin acceso SSH:
1. Inicie sesión en **phpMyAdmin**.
2. Seleccione su base de datos.
3. Importe secuencialmente los archivos ubicados en `database/migrations/`:
   - `001_initial_schema.sql`
   - `002_add_auth_session_fields.sql`
   - `003_privacy_indexes_and_constraints.sql`
   - `004_seed_initial_data.sql`
   - `005_idempotency_and_sequences.sql`
   - `006_rate_limits.sql`
4. O alternativamente, importe el archivo canónico consolidado [`schema.sql`](file:///d:/HTML/Web%20-%20Descartables/schema.sql).

---

## 6. Configuración del Servidor Web Apache (`.htaccess`)

El archivo `.htaccess` provisto en la raíz del proyecto implementa:
1. **Redirección HTTPS en Producción:** Redirige tráfico no seguro a HTTPS, excluyendo `localhost` y `127.0.0.1`.
   *Si su hosting aún no emite el certificado SSL, puede comentar temporalmente el bloque `<IfModule mod_rewrite.c>` correspondiente.*
2. **Cabeceras de Seguridad Reforzadas:**
   - `X-Content-Type-Options: "nosniff"` (previene MIME sniffing).
   - `X-Frame-Options: "SAMEORIGIN"` (protección anti-Clickjacking).
   - `X-XSS-Protection: "1; mode=block"`.
   - `Referrer-Policy: "strict-origin-when-cross-origin"`.
   - `Permissions-Policy: "camera=(), microphone=(), geolocation=()"`.
3. **Bloqueo en Profundidad:** Deniega acceso directo a `.sql`, `.env`, `.log`, `.bak`, `.zip`, `secrets.php`, `vault.php`, `db.php` y carpetas `.git`.
4. **Protección en Directorios Críticos:**
   - `database/.htaccess`: Bloqueo total `Require all denied`.
   - `logs/.htaccess`: Bloqueo total `Require all denied`.
   - `backups/.htaccess`: Bloqueo total `Require all denied`.
   - `assets/images/productos/.htaccess`: Bloqueo de ejecución de scripts (`php_flag engine off`, remoción de handlers CGI/PHP y denegación de extensiones ejecutables).

---

## 7. Verificación Post-Despliegue (Healthcheck)

Una vez subidos los archivos a producción y configuradas las credenciales de base de datos:

### 7.1 Healthcheck Público Mínimo
Consulte vía navegador o cURL:
```text
GET https://su-dominio.pe/api/health.php
```
**Respuesta esperada (200 OK):**
```json
{
  "success": true,
  "request_id": "req_...",
  "data": {
    "status": "ok",
    "app": "healthy",
    "database": "connected"
  }
}
```
*Nota: Este modo no expone versiones de PHP/MySQL, rutas del sistema ni nombres de base de datos.*

### 7.2 Healthcheck Profundo de Administrador
Para diagnosticar permisos de escritura en almacenamiento y sincronización de migraciones:
```text
GET https://su-dominio.pe/api/health.php?mode=deep
Header: Authorization: Bearer <TOKEN_ADMIN>
```
