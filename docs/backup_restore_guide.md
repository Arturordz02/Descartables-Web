# Guía de Copias de Seguridad, Restauración y Recuperación ante Desastres (F14)

## Plataforma Descartables Peruanos

Esta guía documenta la arquitectura, políticas operativas y procedimientos técnicos para la generación, verificación y restauración de copias de seguridad en la plataforma **Descartables Peruanos**.

---

## 1. Diferencia entre Exportación Administrativa y Backup Real (DR)

| Característica | Exportación Administrativa (`api/backup.php?type=export`) | Backup Real para Disaster Recovery (`scripts/backup.php` / `?type=full`) |
|---|---|---|
| **Propósito** | Extracción puntual de datos de negocio para auditoría o análisis en hojas de cálculo. | Reconstrucción integral del sistema y base de datos tras una pérdida catastrófica. |
| **Formato** | Archivo individual `.json`. | Paquete comprimido `.zip` con `manifest.json` y hashes SHA-256 por componente. |
| **Contenido** | Datos lógicos de configuración, catálogo, cotizaciones y reclamaciones. **Excluye contraseñas y hashes**. | DDL canónico (`schema.sql`), migraciones versionadas (`001` a `006`), datos lógicos y archivos persistentes de imágenes (`storage/productos/`). |
| **Política de Tablas** | Filtra datos sensibles de usuarios (sin passwords ni salts). | Incluye usuarios con sus hashes para permitir autenticación inmediata tras restauración. **Altamente confidencial**. |
| **Verificación** | Conteos informativos en memoria. | Hash criptográfico SHA-256 de cada archivo validado antes de iniciar cualquier restauración. |
| **Modo de Restauración** | Manual. | Automatizado mediante [`scripts/restore.php`](file:///d:/HTML/Web%20-%20Descartables/scripts/restore.php) con protección `--verify-only` y `--force`. |

---

## 2. Política de Exclusión: Tabla `rate_limits`

Por diseño arquitectónico y política de seguridad, la tabla **`rate_limits` NO se incluye en el backup de Disaster Recovery**:
1. **Naturaleza Temporal**: Es una tabla operacional de control de abuso y mitigación de denegación de servicio (DoS/fuerza bruta).
2. **Privacidad de Identificadores**: Almacena direcciones IP y marcas de tiempo efímeras que pierden validez operativa tras el incidente.
3. **Estado Inicial Limpio**: Al restaurar un backup en un entorno nuevo o recuperado, la tabla `rate_limits` se reinicia completamente vacía para evitar bloqueos injustificados a usuarios legítimos.

---

## 3. Parámetros de Recuperación y Estrategia 3-2-1

### 3.1 RPO y RTO
- **RPO (Recovery Point Objective)**:
  - **Catálogo, Banners y Configuración**: Máximo 24 horas (backup diario automatizado).
  - **Cotizaciones y Libro de Reclamaciones**: Máximo 1 hora mediante tarea programada (cron) en horarios comerciales (08:00 a 19:00).
- **RTO (Recovery Time Objective)**: Menos de **15 minutos** para restaurar la base de datos completa y los archivos de medios desde la terminal.

### 3.2 Estrategia de Resguardo 3-2-1
1. **3 Copias de los datos**: Entorno de producción activo, copia local diaria en el servidor (`backups/`) y copia externa cifrada fuera de línea.
2. **2 Medios distintos**: Almacenamiento en disco SSD del servidor web y almacenamiento de objetos en la nube (ej. Amazon S3, Wasabi o Google Cloud Storage).
3. **1 Copia Off-Site**: El archivo ZIP generado debe enviarse mediante script de sincronización segura (ej. `rclone` o SCP) a un centro de datos geográficamente separado.

### 3.3 Política de Retención
- **Copias Diarias**: Retención de 7 días en el directorio `backups/`. Las copias con más de 7 días se purgan automáticamente (`--purge-older-than-days=7`).
- **Copias Semanales**: 4 semanas resguardadas en almacenamiento cloud.
- **Copias Mensuales**: 12 meses para auditoría legal y cumplimiento INDECOPI.

---

## 4. Comandos de Operación por Línea de Comandos (CLI)

### 4.1 Generar Backup de Disaster Recovery

Para generar una copia de seguridad integral comprimida con verificación de integridad:

```powershell
php scripts/backup.php --out=backups
```

Parámetros opcionales:
- `--out=directorio`: Carpeta de destino (por defecto `backups/`).
- `--purge-older-than-days=N`: Purga automática de archivos `.zip` más antiguos de N días.
- `--quiet`: Ejecución silenciosa para crontab o tareas desatendidas.

### 4.2 Validar Integridad de un Backup (Sin Modificar la BD)

Antes de restaurar, siempre valide que el archivo no esté corrupto ni haya sido alterado:

```powershell
php scripts/restore.php --archive=backups/dp_backup_xxx.zip --verify-only
```

### 4.3 Restaurar Copia de Seguridad en Producción o Staging

Por seguridad, la restauración real exige la bandera `--force` para evitar la sobreescritura accidental de la base de datos:

```powershell
php scripts/restore.php --archive=backups/dp_backup_xxx.zip --force
```

Opciones adicionales:
- `--target-db=DSN`: Conecta a una base de datos específica (ej. `sqlite:database/staging.db`).
- `--no-files`: Restaura únicamente las tablas de base de datos sin sobreescribir imágenes en disco.

---

## 5. Estructura del Archivo `manifest.json`

Cada archivo `.zip` incluye un manifiesto raíz con la firma criptográfica y metadatos de los elementos:

```json
{
  "manifest_version": "2.0",
  "backup_type": "full_disaster_recovery",
  "backup_id": "bkp_20260911_143000_a1b2c3d4e5f67890",
  "sistema": "Descartables Peruanos - Sistema B2B e INDECOPI",
  "creado_en": "2026-09-11T14:30:00-05:00",
  "entorno": {
    "php_version": "8.2.12",
    "os": "Windows",
    "db_driver": "mysql"
  },
  "advertencia_seguridad": "Este paquete contiene estructura integral de base de datos y hashes de usuario requeridos para recuperación ante desastres. Debe resguardarse bajo cifrado fuera del webroot público.",
  "tablas": {
    "schema_migrations": { "row_count": 6, "sha256": "..." },
    "secuencias": { "row_count": 2, "sha256": "..." },
    "configuracion": { "row_count": 18, "sha256": "..." },
    "categorias": { "row_count": 6, "sha256": "..." },
    "productos": { "row_count": 18, "sha256": "..." },
    "usuarios": { "row_count": 5, "sha256": "..." },
    "cotizaciones": { "row_count": 12, "sha256": "..." },
    "libro_reclamaciones": { "row_count": 8, "sha256": "..." }
  },
  "tablas_excluidas_politica": {
    "rate_limits": "Tabla operativa temporal de control de abuso por IP. Se reinicia vacía tras la restauración para evitar arrastrar identificadores y contadores obsoletos."
  },
  "archivos_esquema": {
    "schema.sql": { "path": "database/schema.sql", "sha256": "..." },
    "001_initial_schema.sql": { "path": "database/migrations/001_initial_schema.sql", "sha256": "..." }
  },
  "archivos_persistentes": {
    "total_archivos": 4,
    "elementos": [
      {
        "original_rel_path": "assets/images/productos/prod_1788453752.jpg",
        "archive_path": "storage/productos/prod_1788453752.jpg",
        "size_bytes": 11695,
        "sha256": "..."
      }
    ],
    "archivos_faltantes_detectados": []
  },
  "checksum_algoritmo": "sha256",
  "checksums": {
    "data_sql": "...",
    "data_json": "..."
  }
}
```

---

## 6. Seguridad del Directorio `backups/`

1. **Protección Apache (`backups/.htaccess`)**:
   - Contiene reglas `Require all denied` y `Deny from all`. Ningún cliente web puede solicitar directamente archivos `.zip` desde el navegador.
2. **Exclusión de Git (`.gitignore`)**:
   - Las reglas `backups/*` y `!backups/.htaccess` aseguran que ninguna copia de seguridad local sea commiteada accidentalmente al repositorio.
3. **Limpieza en Streaming HTTP**:
   - Si un administrador solicita la descarga directa vía `api/backup.php?type=full&download=1`, el servidor entrega el archivo mediante streaming con cabecera `Content-Disposition` e inmediatamente elimina el archivo físico temporal del disco con `@unlink()`.

