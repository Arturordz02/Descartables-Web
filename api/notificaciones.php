<?php
/**
 * ==============================================================================
 * API REST: Polling de Notificaciones en Tiempo Real para el Panel de Administración
 * Descartables Peruanos S.A.C.
 * ==============================================================================
 *
 * 
 * Endpoint ultra-liviano optimizado para chequeos periódicos (cada 2-3 min).
 * Retorna conteos de nuevos registros, IDs máximos y resumen de nuevos elementos
 * sin sobrecargar el servidor ni transferir conjuntos de datos pesados.
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$pdo = getDbConnection();

if (!$pdo) {
    echo json_encode([
        'success' => true,
        'mode' => 'local_fallback',
        'server_time' => time(),
        'cotizaciones' => [
            'max_id' => 0,
            'nuevas_count' => 0,
            'pendientes' => 0,
            'nuevas' => []
        ],
        'reclamaciones' => [
            'max_id' => 0,
            'nuevas_count' => 0,
            'pendientes' => 0,
            'nuevos' => []
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Obtener IDs enviados por el frontend
$lastCotizId = isset($_GET['last_cotizacion_id']) && $_GET['last_cotizacion_id'] !== '' ? (int)$_GET['last_cotizacion_id'] : null;
$lastRecId   = isset($_GET['last_reclamacion_id']) && $_GET['last_reclamacion_id'] !== '' ? (int)$_GET['last_reclamacion_id'] : null;

// Determinar nombre dinámico de la tabla de libro de reclamaciones
$recTable = 'libro_reclamaciones';
try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'libro_reclamaciones'")->fetch();
    if (!$tableExists) {
        $recTable = 'reclamaciones';
    }
} catch (Exception $e) {
    $recTable = 'reclamaciones';
}

// ------------------------------------------------------------------
// 1. CONSULTA DE COTIZACIONES B2B
// ------------------------------------------------------------------
$cotizData = [
    'max_id' => 0,
    'nuevas_count' => 0,
    'pendientes' => 0,
    'nuevas' => []
];

try {
    // Consulta ultra-rápida: Max ID y cantidad de pendientes
    $stmtCotiz = $pdo->query("SELECT MAX(id) as max_id, COUNT(CASE WHEN estado = 'Pendiente' THEN 1 END) as pendientes FROM cotizaciones");
    $rowCotiz = $stmtCotiz->fetch(PDO::FETCH_ASSOC);
    $cotizData['max_id'] = (int)($rowCotiz['max_id'] ?? 0);
    $cotizData['pendientes'] = (int)($rowCotiz['pendientes'] ?? 0);

    // Si el cliente ya tenía un punto de partida y hay cotizaciones más recientes
    if ($lastCotizId !== null && $lastCotizId >= 0 && $cotizData['max_id'] > $lastCotizId) {
        $colsCotiz = $pdo->query("SHOW COLUMNS FROM cotizaciones")->fetchAll(PDO::FETCH_COLUMN);
        $colNombre = in_array('nombre_cliente', $colsCotiz) ? 'nombre_cliente' : (in_array('cliente_nombre', $colsCotiz) ? 'cliente_nombre' : "'Cliente Corporativo'");

        $stmtNuevas = $pdo->prepare("
            SELECT 
                id, 
                codigo_cotizacion, 
                {$colNombre} AS cliente, 
                creado_en 
            FROM cotizaciones 
            WHERE id > ? 
            ORDER BY id DESC 
            LIMIT 5
        ");
        $stmtNuevas->execute([$lastCotizId]);
        $cotizData['nuevas'] = $stmtNuevas->fetchAll(PDO::FETCH_ASSOC);
        $cotizData['nuevas_count'] = count($cotizData['nuevas']);
    }
} catch (Exception $e) {
    // Si la tabla aún no existe o hay error de estructura, retornar seguro
}

// ------------------------------------------------------------------
// 2. CONSULTA DE LIBRO DE RECLAMACIONES INDECOPI
// ------------------------------------------------------------------
$recData = [
    'max_id' => 0,
    'nuevas_count' => 0,
    'pendientes' => 0,
    'nuevos' => []
];

try {
    // Consulta ultra-rápida: Max ID y cantidad de pendientes
    $stmtRec = $pdo->query("SELECT MAX(id) as max_id, COUNT(CASE WHEN estado = 'Pendiente' THEN 1 END) as pendientes FROM {$recTable}");
    $rowRec = $stmtRec->fetch(PDO::FETCH_ASSOC);
    $recData['max_id'] = (int)($rowRec['max_id'] ?? 0);
    $recData['pendientes'] = (int)($rowRec['pendientes'] ?? 0);

    // Si el cliente ya tenía un punto de partida y hay reclamos más recientes
    if ($lastRecId !== null && $lastRecId >= 0 && $recData['max_id'] > $lastRecId) {
        $stmtNuevosRec = $pdo->prepare("
            SELECT 
                id, 
                codigo_hoja, 
                tipo_reclamacion, 
                nombre_completo, 
                creado_en 
            FROM {$recTable} 
            WHERE id > ? 
            ORDER BY id DESC 
            LIMIT 5
        ");
        $stmtNuevosRec->execute([$lastRecId]);
        $recData['nuevos'] = $stmtNuevosRec->fetchAll(PDO::FETCH_ASSOC);
        $recData['nuevas_count'] = count($recData['nuevos']);
    }
} catch (Exception $e) {
    // Fallback silencioso
}

// ------------------------------------------------------------------
// RESPUESTA JSON CONSOLIDADA
// ------------------------------------------------------------------
echo json_encode([
    'success'       => true,
    'server_time'   => time(),
    'cotizaciones'  => $cotizData,
    'reclamaciones' => $recData
], JSON_UNESCAPED_UNICODE);

