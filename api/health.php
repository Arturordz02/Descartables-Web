<?php
/**
 * API REST: Healthcheck de Aplicación y Base de Datos (F13)
 * Plataforma Descartables Peruanos
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'HEAD') {
    ApiResponse::error('Método no permitido.', 'METHOD_NOT_ALLOWED', 405);
    return;
}

$mode = strtolower(trim((string)($_GET['mode'] ?? 'public')));
$isDeep = ($mode === 'deep' || $mode === 'full');

// 1. CHEQUEO BASE DE DATOS
$dbConnected = false;
$dbLatencyMs = null;
$pdo = null;

try {
    $start = microtime(true);
    $pdo = getDbConnection();
    if ($pdo) {
        $stmt = $pdo->query('SELECT 1');
        if ($stmt && $stmt->fetchColumn() !== false) {
            $dbConnected = true;
            $dbLatencyMs = round((microtime(true) - $start) * 1000, 2);
        }
    }
} catch (Throwable $e) {
    Logger::warning('Healthcheck DB ping failed', ['error' => $e->getMessage()]);
    $dbConnected = false;
}

// 2. MODO PÚBLICO (MÍNIMO Y SEGURO)
// No expone versiones, rutas, hosts, nombres de BD ni credenciales
if (!$isDeep) {
    if (!$dbConnected) {
        ApiResponse::error('Servicio parcialmente no disponible: Base de datos no conectada.', 'DB_DISCONNECTED', 503, null, [
            'status'   => 'error',
            'app'      => 'healthy',
            'database' => 'disconnected'
        ]);
        return;
    }

    ApiResponse::success([
        'status'   => 'ok',
        'app'      => 'healthy',
        'database' => 'connected'
    ], [
        'status'   => 'ok',
        'app'      => 'healthy',
        'database' => 'connected'
    ]);
    return;
}

// 3. MODO PROFUNDO (DEEP) - REQUIERE PRIVILEGIOS DE ADMINISTRADOR
$admin = Vault::requireAdmin($pdo);
if (!$admin) {
    return;
}

$deepDetails = [
    'status'   => $dbConnected ? 'ok' : 'degraded',
    'app'      => 'healthy',
    'database' => [
        'status'     => $dbConnected ? 'connected' : 'disconnected',
        'latency_ms' => $dbLatencyMs
    ],
    'storage'  => [
        'uploads_writable' => is_writable(__DIR__ . '/../assets/images/productos'),
        'logs_writable'    => is_writable(__DIR__ . '/../logs')
    ],
    'migrations' => [
        'applied_count' => 0,
        'total_files'   => 0,
        'synced'        => false
    ]
];

if ($dbConnected && $pdo) {
    try {
        $appliedCount = (int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
        $migrationFiles = glob(__DIR__ . '/../database/migrations/*.sql');
        $totalFiles = $migrationFiles ? count($migrationFiles) : 0;
        $deepDetails['migrations']['applied_count'] = $appliedCount;
        $deepDetails['migrations']['total_files'] = $totalFiles;
        $deepDetails['migrations']['synced'] = ($appliedCount >= $totalFiles && $totalFiles > 0);
    } catch (Throwable $e) {
        $deepDetails['migrations']['error'] = 'No se pudo consultar el estado de migraciones.';
    }
}

if (!$dbConnected) {
    ApiResponse::error('Healthcheck profundo reporta degradación.', 'HEALTH_DEGRADED', 503, null, $deepDetails);
    return;
}

ApiResponse::success($deepDetails, $deepDetails);
return;
