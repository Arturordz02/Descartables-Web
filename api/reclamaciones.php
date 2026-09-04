<?php
/**
 * API REST: Libro de Reclamaciones Virtual
 * Normativa INDECOPI (D.S. 011-2011-PCM)
 */

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $inputJSON = file_get_contents('php://input');
    $data = json_decode($inputJSON, true);

    if (!$data) {
        $data = $_POST;
    }

    // Validar campos obligatorios
    $requiredFields = [
        'tipo_documento', 'numero_documento', 'nombre_completo', 
        'telefono', 'email', 'departamento', 'provincia', 'distrito', 
        'direccion', 'tipo_bien', 'monto_reclamado', 'descripcion_bien', 
        'tipo_reclamacion', 'detalle_reclamacion', 'pedido_consumidor'
    ];

    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => "El campo '{$field}' es obligatorio según normativa INDECOPI."
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }
    }

    $pdo = getDbConnection();

    // Generar código correlativo de hoja de reclamación (Ej: REC-2026-00001)
    $anio = date('Y');
    $stmtCount = $pdo->prepare("SELECT COUNT(*) as total FROM libro_reclamaciones WHERE codigo_hoja LIKE ?");
    $stmtCount->execute(["REC-{$anio}-%"]);
    $row = $stmtCount->fetch();
    $nextNumber = ((int)$row['total']) + 1;
    $codigo_hoja = sprintf('REC-%s-%05d', $anio, $nextNumber);

    $sql = "INSERT INTO libro_reclamaciones (
        codigo_hoja, tipo_documento, numero_documento, nombre_completo, 
        telefono, email, departamento, provincia, distrito, direccion, 
        es_menor, nombre_tutor, tipo_bien, monto_reclamado, descripcion_bien, 
        tipo_reclamacion, detalle_reclamacion, pedido_consumidor, estado
    ) VALUES (
        ?, ?, ?, ?, 
        ?, ?, ?, ?, ?, ?, 
        ?, ?, ?, ?, ?, 
        ?, ?, ?, 'Pendiente'
    )";

    $stmt = $pdo->prepare($sql);
    $es_menor = !empty($data['es_menor']) ? 1 : 0;
    $nombre_tutor = $es_menor && !empty($data['nombre_tutor']) ? trim($data['nombre_tutor']) : null;
    $monto = floatval($data['monto_reclamado']);

    $stmt->execute([
        $codigo_hoja,
        $data['tipo_documento'],
        trim($data['numero_documento']),
        trim($data['nombre_completo']),
        trim($data['telefono']),
        trim($data['email']),
        trim($data['departamento']),
        trim($data['provincia']),
        trim($data['distrito']),
        trim($data['direccion']),
        $es_menor,
        $nombre_tutor,
        $data['tipo_bien'],
        $monto,
        trim($data['descripcion_bien']),
        $data['tipo_reclamacion'],
        trim($data['detalle_reclamacion']),
        trim($data['pedido_consumidor'])
    ]);

    $reclamacion_id = $pdo->lastInsertId();

    echo json_encode([
        'success'      => true,
        'message'      => 'Su Hoja de Reclamación ha sido registrada exitosamente conforme a la normativa INDECOPI.',
        'codigo_hoja'  => $codigo_hoja,
        'fecha'        => date('d/m/Y H:i:s'),
        'empresa'      => [
            'razon_social' => EMPRESA_RAZON_SOCIAL,
            'ruc'          => EMPRESA_RUC,
            'direccion'    => EMPRESA_DIRECCION,
            'telefono'     => EMPRESA_TELEFONO,
            'email'        => EMPRESA_EMAIL
        ],
        'plazo_legal'  => '15 días hábiles conforme a la Ley N° 31435 que modifica el Código de Protección y Defensa del Consumidor.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($method === 'GET') {
    $codigo = isset($_GET['codigo']) ? trim($_GET['codigo']) : null;
    $doc = isset($_GET['documento']) ? trim($_GET['documento']) : null;

    $pdo = getDbConnection();
    if (!$pdo) {
        echo json_encode(['success' => true, 'data' => [], 'stats' => ['total' => 0, 'pendientes' => 0, 'atendidos' => 0]]);
        exit();
    }

    if ($codigo) {
        $stmt = $pdo->prepare("SELECT * FROM libro_reclamaciones WHERE codigo_hoja = ?");
        $stmt->execute([$codigo]);
        $resultados = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $resultados], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if ($doc) {
        $stmt = $pdo->prepare("SELECT * FROM libro_reclamaciones WHERE numero_documento = ? ORDER BY id DESC");
        $stmt->execute([$doc]);
        $resultados = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $resultados], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Listado general para Panel Administrativo
    $stmt = $pdo->query("SELECT * FROM libro_reclamaciones ORDER BY id DESC");
    $resultados = $stmt->fetchAll();

    // Estadísticas agregadas
    $stmtStats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'En Proceso' THEN 1 ELSE 0 END) as en_proceso,
            SUM(CASE WHEN estado = 'Atendido' THEN 1 ELSE 0 END) as atendidos,
            SUM(CASE WHEN tipo_reclamacion = 'Reclamo' THEN 1 ELSE 0 END) as reclamos,
            SUM(CASE WHEN tipo_reclamacion = 'Queja' THEN 1 ELSE 0 END) as quejas
        FROM libro_reclamaciones
    ");
    $stats = $stmtStats->fetch() ?: [
        'total' => count($resultados),
        'pendientes' => 0,
        'en_proceso' => 0,
        'atendidos' => 0,
        'reclamos' => 0,
        'quejas' => 0
    ];

    echo json_encode([
        'success' => true,
        'count'   => count($resultados),
        'stats'   => $stats,
        'data'    => $resultados
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($method === 'PUT') {
    $inputJSON = file_get_contents('php://input');
    $data = json_decode($inputJSON, true);

    if (!$data || (empty($data['id']) && empty($data['codigo_hoja']))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Se requiere el ID o código de la hoja de reclamación.']);
        exit();
    }

    $pdo = getDbConnection();
    if (!$pdo) {
        echo json_encode(['success' => true, 'message' => 'Actualizado en modo local.']);
        exit();
    }

    $id = !empty($data['id']) ? (int)$data['id'] : null;
    $codigo = !empty($data['codigo_hoja']) ? trim($data['codigo_hoja']) : null;
    $estado = in_array($data['estado'] ?? '', ['Pendiente', 'En Proceso', 'Atendido']) ? $data['estado'] : 'Atendido';
    $respuesta = trim($data['respuesta_proveedor'] ?? '');

    if ($id) {
        $stmt = $pdo->prepare("UPDATE libro_reclamaciones SET estado = ?, respuesta_proveedor = ?, fecha_respuesta = NOW() WHERE id = ?");
        $stmt->execute([$estado, $respuesta, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE libro_reclamaciones SET estado = ?, respuesta_proveedor = ?, fecha_respuesta = NOW() WHERE codigo_hoja = ?");
        $stmt->execute([$estado, $respuesta, $codigo]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Hoja de reclamación actualizada exitosamente conforme a INDECOPI.',
        'data'    => [
            'id'                  => $id,
            'estado'              => $estado,
            'respuesta_proveedor' => $respuesta,
            'fecha_respuesta'     => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);

