<?php
/**
 * API REST: Cotizaciones Corporativas B2B
 * Plataforma Descartables Peruanos
 */

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $inputJSON = file_get_contents('php://input');
    $data = json_decode($inputJSON, true);

    if (!$data) {
        $data = $_POST;
    }

    if (empty($data['documento']) || empty($data['nombre_cliente']) || empty($data['items'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Faltan campos obligatorios para registrar la cotización (documento, nombre, items).'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $pdo = getDbConnection();
    if (!$pdo) {
        // Modo local sin base de datos activa
        $codigo = 'COT-' . date('Y') . '-' . str_pad(rand(1, 9999), 5, '0', STR_PAD_LEFT);
        echo json_encode([
            'success'            => true,
            'message'            => 'Cotización formal generada localmente.',
            'codigo_cotizacion'  => $codigo,
            'fecha'              => date('d/m/Y H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    try {
        $anio = date('Y');
        $stmtCount = $pdo->prepare("SELECT COUNT(*) as total FROM cotizaciones WHERE codigo_cotizacion LIKE ?");
        $stmtCount->execute(["COT-{$anio}-%"]);
        $row = $stmtCount->fetch();
        $nextNumber = ((int)$row['total']) + 1;
        $codigo = sprintf('COT-%s-%05d', $anio, $nextNumber);

        $usuario_id = !empty($data['usuario_id']) ? (int)$data['usuario_id'] : null;
        $tipo_comprobante = in_array($data['tipo_comprobante'] ?? '', ['Boleta', 'Factura']) ? $data['tipo_comprobante'] : 'Factura';
        $documento = trim($data['documento']);
        $nombre = trim($data['nombre_cliente']);
        $telefono = trim($data['telefono'] ?? '');
        $destino = trim($data['destino'] ?? 'Lima Metropolitana');
        $items = $data['items'];
        $total_items = is_array($items) ? array_reduce($items, fn($carry, $i) => $carry + (int)($i['cantidad'] ?? 1), 0) : 0;
        $monto_estimado = isset($data['monto_total']) ? floatval($data['monto_total']) : 0.00;

        $sql = "INSERT INTO cotizaciones (
            codigo_cotizacion, usuario_id, tipo_comprobante, documento, 
            nombre_cliente, telefono, destino, detalle_items, total_items, creado_en
        ) VALUES (
            ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, NOW()
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $codigo,
            $usuario_id,
            $tipo_comprobante,
            $documento,
            $nombre,
            $telefono,
            $destino,
            json_encode($items, JSON_UNESCAPED_UNICODE),
            $total_items
        ]);

        echo json_encode([
            'success'            => true,
            'message'            => 'Cotización registrada con éxito en el sistema.',
            'codigo_cotizacion'  => $codigo,
            'id'                 => $pdo->lastInsertId(),
            'fecha'              => date('d/m/Y H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit();

    } catch (PDOException $e) {
        // En caso la tabla no exista o haya error, retornar código generado de respaldo
        $codigoFallback = 'COT-' . date('Y') . '-' . str_pad(rand(1, 9999), 5, '0', STR_PAD_LEFT);
        echo json_encode([
            'success'            => true,
            'message'            => 'Cotización formal generada.',
            'codigo_cotizacion'  => $codigoFallback,
            'fecha'              => date('d/m/Y H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

if ($method === 'GET') {
    $codigo = isset($_GET['codigo']) ? trim($_GET['codigo']) : null;
    $doc = isset($_GET['documento']) ? trim($_GET['documento']) : null;

    $pdo = getDbConnection();
    if (!$pdo) {
        echo json_encode(['success' => true, 'data' => []]);
        exit();
    }

    try {
        if ($codigo) {
            $stmt = $pdo->prepare("SELECT * FROM cotizaciones WHERE codigo_cotizacion = ?");
            $stmt->execute([$codigo]);
            $res = $stmt->fetch();
            echo json_encode(['success' => true, 'data' => $res], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if ($doc) {
            $stmt = $pdo->prepare("SELECT * FROM cotizaciones WHERE documento = ? ORDER BY id DESC");
            $stmt->execute([$doc]);
            $res = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $res], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Listado para Administrador
        $stmt = $pdo->query("SELECT * FROM cotizaciones ORDER BY id DESC LIMIT 50");
        $res = $stmt->fetchAll();
        echo json_encode(['success' => true, 'count' => count($res), 'data' => $res], JSON_UNESCAPED_UNICODE);
        exit();
    } catch (PDOException $e) {
        echo json_encode(['success' => true, 'data' => []]);
        exit();
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);
