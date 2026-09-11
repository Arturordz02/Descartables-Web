<?php
/**
 * API REST: Generador de Copias de Seguridad y Exportaciones Administrativas (F14)
 * Plataforma Descartables Peruanos
 * 
 * Modos Soportados:
 * 1. type=export (Por defecto): Exportación administrativa rápida en JSON para el panel admin.
 *    - Excluye hashes de contraseñas y tokens.
 *    - Política Fail-Fast: Si una sola tabla falla, aborta con HTTP 500 (sin falsos éxitos).
 * 2. type=full / type=dr: Paquete comprimido integral de Disaster Recovery (.zip)
 *    - Incluye manifest.json con conteo de registros y hashes SHA-256 por archivo.
 *    - Incluye schema canónico, migraciones versionadas y datos lógicos.
 *    - Incluye imágenes persistentes gestionadas.
 *    - Excluye por política rate_limits (temporal/operativa) y secretos del servidor.
 *    - En descarga directa (?download=1), elimina el archivo temporal tras el streaming.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vault.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/backup_engine.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'GET' && $method !== 'POST') {
    ApiResponse::error('Método no permitido.', 'METHOD_NOT_ALLOWED', 405);
    return;
}

// 1. Control de Acceso Estricto: Administrador Obligatorio
$adminUser = Vault::requireAdmin();
if (!$adminUser) {
    return;
}

$pdo = getDbConnection();
if (!$pdo) {
    ApiResponse::error('Base de datos no disponible.', 'DB_UNAVAILABLE', 503);
    return;
}

$type = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : 'export';
if (isset($_GET['action']) && $_GET['action'] === 'full_backup') {
    $type = 'full';
}
$isDirectDownload = isset($_GET['download']) && ($_GET['download'] === '1' || $_GET['download'] === 'true');

// =========================================================================
// MODO 1: PAQUETE INTEGRAL DE DISASTER RECOVERY (ZIP CON MANIFEST Y SHA-256)
// =========================================================================
if ($type === 'full' || $type === 'dr') {
    try {
        $result = BackupEngine::createDisasterRecoveryArchive($pdo, null, ['purge_days' => 7]);

        if ($isDirectDownload) {
            $filePath = $result['archive_path'];
            if (!file_exists($filePath)) {
                ApiResponse::error('El archivo de backup generado no se encuentra disponible para descarga.', 'BACKUP_NOT_FOUND', 500);
                return;
            }

            // Headers seguros para streaming de archivo comprimido
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $result['archive_name'] . '"');
            header('Content-Length: ' . (string)$result['size_bytes']);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('X-Content-Type-Options: nosniff');

            readfile($filePath);

            // Eliminar copia temporal tras la descarga para no dejar archivos permanentes en webroot
            @unlink($filePath);
            exit();
        } else {
            ApiResponse::success([
                'tipo'             => 'full_disaster_recovery',
                'archivo'          => $result['archive_name'],
                'tamanio_bytes'    => $result['size_bytes'],
                'sha256'           => $result['sha256'],
                'tablas_incluidas' => array_keys($result['manifest']['tablas']),
                'total_archivos'   => $result['manifest']['archivos_persistentes']['total_archivos'] ?? 0,
                'manifest'         => $result['manifest']
            ], [
                'message' => 'Paquete de Disaster Recovery generado exitosamente en almacenamiento privado.'
            ]);
            return;
        }

    } catch (Throwable $e) {
        Logger::error('Error crítico al generar backup Disaster Recovery', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        ApiResponse::error('Fallo crítico al generar el paquete de recuperación: ' . $e->getMessage(), 'BACKUP_FAILED', 500, $e);
        return;
    }
}

// =========================================================================
// MODO 2: EXPORTACIÓN ADMINISTRATIVA JSON (FAIL-FAST Y SIN SECRETOS)
// =========================================================================
try {
    // 2.1 Configuración
    try {
        $stmtConf = $pdo->query("SELECT clave, valor, updated_at FROM configuracion");
        if ($stmtConf === false) {
            throw new RuntimeException("Error al ejecutar SELECT en 'configuracion'.");
        }
        $confRows = $stmtConf->fetchAll(PDO::FETCH_ASSOC);
        $configFlat = [];
        foreach ($confRows as $row) {
            $configFlat[$row['clave']] = $row['valor'];
        }
    } catch (Throwable $e) {
        Logger::error('Backup: Error al extraer configuracion', ['error' => $e->getMessage()]);
        ApiResponse::error('Fallo al exportar tabla configuracion: ' . $e->getMessage(), 'BACKUP_TABLE_EXPORT_FAILED', 500, $e);
        return;
    }

    $datosNegocio = [];
    $bannersData = [];
    foreach ($configFlat as $k => $v) {
        if (str_starts_with($k, 'banner_') || str_starts_with($k, 'hero_')) {
            $bannersData[$k] = $v;
        } else {
            $datosNegocio[$k] = $v;
        }
    }

    // 2.2 Categorías
    try {
        $stmtCat = $pdo->query("SELECT * FROM categorias ORDER BY id ASC");
        if ($stmtCat === false) {
            throw new RuntimeException("Error al ejecutar SELECT en 'categorias'.");
        }
        $categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        Logger::error('Backup: Error al extraer categorias', ['error' => $e->getMessage()]);
        ApiResponse::error('Fallo al exportar tabla categorias: ' . $e->getMessage(), 'BACKUP_TABLE_EXPORT_FAILED', 500, $e);
        return;
    }

    // 2.3 Productos
    try {
        $stmtProd = $pdo->query("SELECT * FROM productos ORDER BY id ASC");
        if ($stmtProd === false) {
            throw new RuntimeException("Error al ejecutar SELECT en 'productos'.");
        }
        $productos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        Logger::error('Backup: Error al extraer productos', ['error' => $e->getMessage()]);
        ApiResponse::error('Fallo al exportar tabla productos: ' . $e->getMessage(), 'BACKUP_TABLE_EXPORT_FAILED', 500, $e);
        return;
    }

    // 2.4 Usuarios (ESTRICTAMENTE SIN HASHES DE PASSWORD NI SECRETOS)
    try {
        $stmtUsers = $pdo->query("SELECT id, tipo_documento, numero_documento, nombre_razon_social, email, telefono, departamento, provincia, distrito, direccion, rol, creado_en FROM usuarios ORDER BY id ASC");
        if ($stmtUsers === false) {
            throw new RuntimeException("Error al ejecutar SELECT en 'usuarios'.");
        }
        $usuarios = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        Logger::error('Backup: Error al extraer usuarios', ['error' => $e->getMessage()]);
        ApiResponse::error('Fallo al exportar tabla usuarios: ' . $e->getMessage(), 'BACKUP_TABLE_EXPORT_FAILED', 500, $e);
        return;
    }

    // 2.5 Cotizaciones B2B
    try {
        $stmtCotiz = $pdo->query("SELECT * FROM cotizaciones ORDER BY id DESC");
        if ($stmtCotiz === false) {
            throw new RuntimeException("Error al ejecutar SELECT en 'cotizaciones'.");
        }
        $cotizacionesRaw = $stmtCotiz->fetchAll(PDO::FETCH_ASSOC);
        $cotizaciones = [];
        foreach ($cotizacionesRaw as $c) {
            if (isset($c['items']) && is_string($c['items'])) {
                $decoded = json_decode($c['items'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $c['items'] = $decoded;
                }
            }
            $cotizaciones[] = $c;
        }
    } catch (Throwable $e) {
        Logger::error('Backup: Error al extraer cotizaciones', ['error' => $e->getMessage()]);
        ApiResponse::error('Fallo al exportar tabla cotizaciones: ' . $e->getMessage(), 'BACKUP_TABLE_EXPORT_FAILED', 500, $e);
        return;
    }

    // 2.6 Libro de Reclamaciones
    try {
        $stmtRec = $pdo->query("SELECT * FROM libro_reclamaciones ORDER BY id DESC");
        if ($stmtRec === false) {
            throw new RuntimeException("Error al ejecutar SELECT en 'libro_reclamaciones'.");
        }
        $reclamaciones = $stmtRec->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        Logger::error('Backup: Error al extraer libro_reclamaciones', ['error' => $e->getMessage()]);
        ApiResponse::error('Fallo al exportar tabla libro_reclamaciones: ' . $e->getMessage(), 'BACKUP_TABLE_EXPORT_FAILED', 500, $e);
        return;
    }

    // Estructurar Payload de Exportación Administrativa
    date_default_timezone_set('America/Lima');
    $now = new DateTime();
    $fechaIso = $now->format('c');
    $fechaNombreArchivo = $now->format('Y-m-d_Hi');
    $filename = "export_descartables_{$fechaNombreArchivo}.json";

    $backupPayload = [
        '_metadata' => [
            'sistema'           => 'Descartables Peruanos - Plataforma Web & Panel Administrativo',
            'tipo_operacion'    => BackupEngine::BACKUP_TYPE_EXPORT,
            'advertencia'       => 'Esta es una exportación lógica administrativa en formato JSON para el panel de control. No sustituye un paquete de recuperación ante desastres (Disaster Recovery).',
            'version'           => '2.0',
            'fecha_exportacion' => $fechaIso,
            'timestamp'         => time(),
            'generado_por'      => [
                'id'                  => $adminUser['id'] ?? 1,
                'nombre_razon_social' => $adminUser['nombre_razon_social'] ?? 'Master Admin',
                'rol'                 => $adminUser['rol'] ?? 'admin'
            ],
            'resumen_conteos'   => [
                'configuracion_claves' => count($configFlat),
                'categorias'           => count($categorias),
                'productos'            => count($productos),
                'usuarios'             => count($usuarios),
                'cotizaciones'         => count($cotizaciones),
                'libro_reclamaciones'  => count($reclamaciones)
            ]
        ],
        'datos_del_negocio'      => $datosNegocio,
        'banners_y_avisos'       => $bannersData,
        'configuracion_completa' => $configFlat,
        'categorias'             => $categorias,
        'productos'              => $productos,
        'usuarios'               => $usuarios,
        'cotizaciones'           => $cotizaciones,
        'libro_reclamaciones'    => $reclamaciones
    ];

    if ($isDirectDownload) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($backupPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } else {
        ApiResponse::success($backupPayload, [
            'filename' => $filename,
            'metadata' => $backupPayload['_metadata']
        ]);
        return;
    }

} catch (Throwable $e) {
    Logger::error('Error general durante la exportación administrativa', [
        'error' => $e->getMessage()
    ]);
    ApiResponse::error('Error crítico al procesar la exportación administrativa: ' . $e->getMessage(), 'BACKUP_FAILED', 500, $e);
    return;
}
