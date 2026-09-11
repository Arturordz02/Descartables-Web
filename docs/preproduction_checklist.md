# Checklist de Preproducción y Preparación para Nube (F15 + Cierre General)

## Plataforma Descartables Peruanos S.A.C.

Este documento consolida la matriz de verificación técnica previa al pase a producción definitiva. Cada control cuenta con un estado operativo estandarizado:
- **`[LISTO]`**: Implementado y verificado rigurosamente en el entorno de desarrollo mediante pruebas automatizadas y auditoría de código.
- **`[PENDIENTE EN HOSTING]`**: Requiere activación, configuración o comprobación en el servidor real de producción (hosting cPanel, InfinityFree, VPS o Nube).
- **`[BLOQUEANTE]`**: Condición crítica que impide la apertura al público si no está satisfecha en el servidor destino.

---

## 1. Seguridad de Secretos y Configuración Privada (F04, F15)

| Control | Estado | Observaciones Técnicas |
|---|---|---|
| Repositorio libre de secretos reales | `[LISTO]` | No existen contraseñas, salts ni credenciales maestras en Git ni en `dist/package`. |
| Carga jerárquica de configuración en `Vault` | `[LISTO]` | Soporta `SECRETS_FILE_PATH`, `../secrets.php`, `../../secrets.php` y variables de entorno del sistema. |
| Validación de entropía en `TOKEN_SALT` | `[LISTO]` | Falla de manera controlada y sin filtrar trazas si la sal tiene menos de 16 caracteres. |
| Ubicación de `secrets.php` fuera de `public_html` | `[PENDIENTE EN HOSTING]` | En el hosting, mover `secrets.php` un nivel arriba de `public_html/` y fijar permisos `600`. |
| Configuración de `APP_ENV=production` | `[PENDIENTE EN HOSTING]` | Asegurar que la variable de entorno o `secrets.php` defina `environment: production`. |

---

## 2. Autenticación, Sesiones y Privacidad (F01, F02, F03, F08)

| Control | Estado | Observaciones Técnicas |
|---|---|---|
| Tokens HMAC con firma criptográfica de sesión | `[LISTO]` | Validados con SHA-256 e invalidación automática al modificar contraseña. |
| Control estricto de roles (`Vault::requireAdmin`) | `[LISTO]` | La API deniega el acceso a cotizaciones ajenas y administración a usuarios no autorizados. |
| Inmutabilidad de identidad en perfil (`api/usuarios.php`) | `[LISTO]` | Clientes no pueden alterar su propio rol, número de documento ni identificador. |
| Privacidad en Libro de Reclamaciones | `[LISTO]` | Consulta pública filtrada exclusivamente por correlativo y código de verificación. |
| No dependencia de `localStorage` como autoridad de rol | `[LISTO]` | El backend `Vault` gobierna todas las autorizaciones; la manipulación del storage local no eleva privilegios. |

---

## 3. Concurrencia, Idempotencia y Resiliencia (F05, F07, F10)

| Control | Estado | Observaciones Técnicas |
|---|---|---|
| Generación concurrente y segura de correlativos | `[LISTO]` | Transacciones InnoDB con `SELECT ... FOR UPDATE` en tabla `secuencias`. |
| Motor de idempotencia B2B (`api/concurrency.php`) | `[LISTO]` | Bloqueo transaccional de reintentos concurrentes con cabecera `X-Idempotent-Replay`. |
| Prevención de falsos éxitos offline | `[LISTO]` | La UI informa claramente cuando el servidor no responde y no finge grabaciones remotas. |
| Motor de almacenamiento InnoDB en base de datos | `[LISTO]` | Todas las tablas de `schema.sql` y migraciones `001` a `006` declaran `ENGINE=InnoDB`. |

---

## 4. Validaciones, Rate Limiting y Abuso (F11)

| Control | Estado | Observaciones Técnicas |
|---|---|---|
| Validación estricta de entradas (`api/validator.php`) | `[LISTO]` | Formatos válidos de RUC (11 dígitos), DNI (8 dígitos), correos RFC 5322 y teléfonos. |
| Prevención de inyecciones XSS / DOM | `[LISTO]` | Sanitización de caracteres peligrosos y renderizado seguro mediante `escapeHtml`. |
| Rate limiting por endpoint (`api/ratelimit.php`) | `[LISTO]` | Control de frecuencia para login (5/min), cotizaciones (10/min) y reclamaciones (5/min). |
| Exclusión de `rate_limits` en copias de seguridad DR | `[LISTO]` | La tabla operacional se reinicia vacía tras una restauración para evitar falsos bloqueos. |

---

## 5. Uploads Seguros y Almacenamiento Local (F12)

| Control | Estado | Observaciones Técnicas |
|---|---|---|
| Validación de cabeceras mágicas de imagen | `[LISTO]` | Verificación estricta de firmas JPG, PNG y WEBP; SVG bloqueado por riesgo XSS. |
| Nombres aleatorios impredecibles con prefijo `prod_` | `[LISTO]` | Archivos renombrados mediante hash criptográfico SHA-256. |
| Bloqueo de ejecución en directorio de subidas | `[LISTO]` | `assets/images/productos/.htaccess` deshabilita mod_php y deniega extensiones ejecutables. |
| Permisos de escritura en directorio de uploads | `[PENDIENTE EN HOSTING]` | Verificar en hosting que el usuario del servidor web (`www-data` / cPanel) tenga permisos `755`. |

---

## 6. Errores, Logs Privados y Healthcheck (F13)

| Control | Estado | Observaciones Técnicas |
|---|---|---|
| Respuestas de error JSON unificadas con `request_id` | `[LISTO]` | Supresión absoluta de SQLSTATE, rutas absolutas internas o claves de sistema en errores cliente. |
| Logging privado con sanitización de credenciales | `[LISTO]` | Registro en `logs/` enmascarando contraseñas, tokens y documentos. |
| Directorio `logs/` protegido contra acceso HTTP | `[LISTO]` | `logs/.htaccess` deniega acceso incondicional (`Require all denied`). |
| Healthcheck público mínimo seguro (`api/health.php`) | `[LISTO]` | Reporta `status: ok` sin exponer versiones, hosts ni nombres de base de datos. |
| Healthcheck profundo administrativo | `[LISTO]` | Protegido con `Vault::requireAdmin` para verificar base de datos, almacenamiento y migraciones. |

---

## 7. Copias de Seguridad y Recuperación Real (F14)

| Control | Estado | Observaciones Técnicas |
|---|---|---|
| Backup integral para Disaster Recovery (`scripts/backup.php`) | `[LISTO]` | Genera archivo ZIP con manifest SHA-256, schema, migraciones, datos y medios. |
| Restauración verificable con `--verify-only` | `[LISTO]` | Comprueba sumas de verificación criptográficas sin tocar datos de producción. |
| Directorio `backups/` protegido contra acceso HTTP | `[LISTO]` | `backups/.htaccess` deniega todo acceso web directo. |
| Programación de copias automáticas (Cron Job) | `[PENDIENTE EN HOSTING]` | Configurar tarea cron diaria en el hosting: `php /path/to/scripts/backup.php`. |
| Copia externa fuera de línea (Estrategia 3-2-1) | `[PENDIENTE EN HOSTING]` | Sincronizar los archivos ZIP con almacenamiento en nube externo (S3 / Wasabi / Google Drive). |

---

## 8. Frontend de Producción, Tailwind Compilado y Caché (F16, F17)

| Control | Estado | Observaciones Técnicas |
|---|---|---|
| Eliminación total del CDN Play de Tailwind | `[LISTO]` | 0 coincidencias de `cdn.tailwindcss.com` en todos los archivos HTML. |
| Tailwind CSS compilado localmente (`tailwind.css`) | `[LISTO]` | Archivo de 607 KB minificado con paletas completas (`warm`, `admin`, `safelist`). |
| Resiliencia ante JSON corrupto en `localStorage` | `[LISTO]` | `StorageHelper` purga automáticamente claves corruptas sin lanzar excepciones `SyntaxError`. |
| Deduplicación y aliases en `ApiService` | `[LISTO]` | `registerQuote` y `getQuotes` interoperables con `saveCotizacion` y `getCotizaciones`. |

---

## 9. Servidor Web, Redes, HTTPS y CORS (F15)

| Control | Estado | Observaciones Técnicas |
|---|---|---|
| Cabeceras de seguridad reforzadas en `.htaccess` | `[LISTO]` | `nosniff`, `SAMEORIGIN`, `strict-origin-when-cross-origin` y `Permissions-Policy`. |
| Regla de redirección HTTP -> HTTPS | `[LISTO]` | Configurada condicionalmente excluyendo entornos locales `localhost`/`127.0.0.1`. |
| Certificado SSL activo en el dominio | `[BLOQUEANTE]` | En el hosting, instalar certificado Let's Encrypt o SSL comercial antes de abrir al público. |
| Eliminación de comodín peligroso en CORS (`*`) | `[LISTO]` | `Vault::handleCors()` evalúa el origen y no expone `*` en producción. |
| Configuración de dominios autorizados (`ALLOWED_ORIGINS`) | `[PENDIENTE EN HOSTING]` | Registrar el dominio o subdominio real en `secrets.php` o variable `ALLOWED_ORIGINS`. |
| Bloqueo web en `database/` y `scripts/` | `[LISTO]` | Archivos `.htaccess` dedicados impiden la lectura o descarga de scripts SQL y utilitarios CLI. |

---

## 10. Despliegue en Hosting y Validación en Vivo

| Control | Estado | Observaciones Técnicas |
|---|---|---|
| Generación limpia del paquete `dist/package/` | `[LISTO]` | Auditoría de empaquetado superada con 0 secretos, 0 logs, 0 git y 0 node_modules. |
| Subida de archivos a `public_html/` | `[PENDIENTE EN HOSTING]` | Desplegar el contenido de `dist/package/` vía SFTP o cPanel File Manager. |
| Creación de base de datos MySQL en hosting | `[PENDIENTE EN HOSTING]` | Crear base de datos y usuario MySQL con privilegios estrictos sobre las tablas. |
| Ejecución de migraciones en producción | `[PENDIENTE EN HOSTING]` | Ejecutar `php database/migrate.php` o importar `schema.sql` en phpMyAdmin. |
| Prueba de compra / cotización de extremo a extremo | `[PENDIENTE EN HOSTING]` | Enviar una cotización de prueba verificando recepción en base de datos y WhatsApp. |
| Prueba de registro en Libro de Reclamaciones | `[PENDIENTE EN HOSTING]` | Registrar una reclamación de prueba y verificar generación de hoja imprimible en A4. |
| Verificación de healthcheck público en vivo | `[PENDIENTE EN HOSTING]` | Confirmar respuesta HTTP 200 `{"status":"ok","database":"connected"}` desde el dominio real. |

---

## Dictamen Global de Preproducción

```text
========================================================================================
ESTADO GENERAL: LISTO LOCALMENTE, PENDIENTE DE VALIDACIÓN EN HOSTING/PREPRODUCCIÓN
========================================================================================
- Controles Locales Superados: 100% (Tareas 1 a 13 validadas con suites automatizadas).
- Acciones Pendientes: Requieren acceso al panel del hosting (SSL, DB host y secrets.php).
- Puntos Bloqueantes para Salida a Producción:
  1. Activación de Certificado SSL / HTTPS en el servidor.
  2. Conexión exitosa a la base de datos MySQL de producción y ejecución de migraciones.
  3. Ubicación segura de secrets.php fuera de public_html con permisos 600.
========================================================================================
```
