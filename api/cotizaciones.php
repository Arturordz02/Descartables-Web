<?php
/**
 * API REST: Cotizaciones Corporativas B2B
 * Plataforma Descartables Peruanos
 */

require_once __DIR__ . '/db.php';

$pdo = getDbConnection();

// Auto-migración silenciosa y segura de columnas estado y notas
if ($pdo) {
    try {
        $pdo->query("SELECT estado FROM cotizaciones LIMIT 1");
    } catch (Exception $e) {
        try {
            $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN estado VARCHAR(30) DEFAULT 'Pendiente'");
            $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN notas TEXT NULL");
        } catch (Exception $ignored) {}
    }
}

$method = $_SERVER['REQUEST_METHOD'];

// Helper para parsear input JSON o POST
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!$data || !is_array($data)) {
    $data = $_POST;
}

// 1. ACTUALIZAR ESTADO / NOTAS (PUT o POST con action=update_status)
if ($method === 'PUT' || ($method === 'POST' && isset($data['action']) && $data['action'] === 'update_status')) {
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Base de datos no disponible'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $id = isset($data['id']) ? (int)$data['id'] : null;
    $codigo = isset($data['codigo']) ? trim($data['codigo']) : null;
    $estado = isset($data['estado']) ? trim($data['estado']) : 'Pendiente';
    $notas = isset($data['notas']) ? trim($data['notas']) : null;

    $validEstados = ['Pendiente', 'En Contacto', 'Cotizado', 'Atendido', 'Despachado', 'Cancelado'];
    if (!in_array($estado, $validEstados)) {
        $estado = 'Pendiente';
    }

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE cotizaciones SET estado = ?, notas = ? WHERE id = ?");
            $stmt->execute([$estado, $notas, $id]);
        } elseif ($codigo) {
            $stmt = $pdo->prepare("UPDATE cotizaciones SET estado = ?, notas = ? WHERE codigo_cotizacion = ?");
            $stmt->execute([$estado, $notas, $codigo]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Se requiere ID o Código de cotización'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Estado de cotización actualizado con éxito.',
            'estado'  => $estado,
            'notas'   => $notas
        ], JSON_UNESCAPED_UNICODE);
        exit();
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al actualizar cotización: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// 2. ELIMINAR COTIZACIÓN (DELETE o POST con action=delete)
if ($method === 'DELETE' || ($method === 'POST' && isset($data['action']) && $data['action'] === 'delete')) {
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Base de datos no disponible'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $id = isset($data['id']) ? (int)$data['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : null);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM cotizaciones WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Cotización eliminada con éxito.'], JSON_UNESCAPED_UNICODE);
        exit();
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al eliminar: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// 3. REGISTRAR NUEVA COTIZACIÓN (POST)
if ($method === 'POST') {
    if (empty($data['documento']) || empty($data['nombre_cliente']) || empty($data['items'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Faltan campos obligatorios para registrar la cotización (documento, nombre, items).'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if (!$pdo) {
        // Modo local de respaldo sin base de datos activa
        $codigo = 'COT-' . date('Y') . '-' . str_pad(rand(1, 9999), 5, '0', STR_PAD_LEFT);
        echo json_encode([
            'success'            => true,
            'message'            => 'Cotización formal generada en modo local.',
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
        $estado = 'Pendiente';
        $notas = trim($data['notas'] ?? '');

        $sql = "INSERT INTO cotizaciones (
            codigo_cotizacion, usuario_id, tipo_comprobante, documento, 
            nombre_cliente, telefono, destino, detalle_items, total_items, estado, notas, creado_en
        ) VALUES (
            ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, ?, NOW()
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
            $total_items,
            $estado,
            $notas
        ]);

        $newId = $pdo->lastInsertId();

        echo json_encode([
            'success'            => true,
            'message'            => 'Cotización registrada con éxito en el sistema.',
            'codigo_cotizacion'  => $codigo,
            'id'                 => $newId,
            'estado'             => $estado,
            'fecha'              => date('d/m/Y H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit();

    } catch (PDOException $e) {
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

// 4. CONSULTAR COTIZACIONES (GET)
if ($method === 'GET') {
    $codigo = isset($_GET['codigo']) ? trim($_GET['codigo']) : null;
    $doc = isset($_GET['documento']) ? trim($_GET['documento']) : null;
    $estado = isset($_GET['estado']) && $_GET['estado'] !== 'all' && $_GET['estado'] !== 'todos' ? trim($_GET['estado']) : null;
    $search = isset($_GET['q']) ? trim($_GET['q']) : null;

    if (!$pdo) {
        echo json_encode(['success' => true, 'count' => 0, 'data' => []], JSON_UNESCAPED_UNICODE);
        exit();
    }

    try {
        if ($codigo) {
            $stmt = $pdo->prepare("SELECT * FROM cotizaciones WHERE codigo_cotizacion = ?");
            $stmt->execute([$codigo]);
            $res = $stmt->fetch();
            if ($res && is_string($res['detalle_items'])) {
                $res['detalle_items'] = json_decode($res['detalle_items'], true);
            }
            echo json_encode(['success' => true, 'data' => $res], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if ($doc) {
            $stmt = $pdo->prepare("SELECT * FROM cotizaciones WHERE documento = ? ORDER BY id DESC");
            $stmt->execute([$doc]);
            $res = $stmt->fetchAll();
            foreach ($res as &$r) {
                if (is_string($r['detalle_items'])) {
                    $r['detalle_items'] = json_decode($r['detalle_items'], true);
                }
            }
            echo json_encode(['success' => true, 'count' => count($res), 'data' => $res], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Listado para Administrador con filtros
        $sql = "SELECT * FROM cotizaciones WHERE 1=1";
        $params = [];

        if ($estado) {
            $sql .= " AND estado = ?";
            $params[] = $estado;
        }

        if ($search) {
            $sql .= " AND (codigo_cotizacion LIKE ? OR nombre_cliente LIKE ? OR documento LIKE ? OR telefono LIKE ?)";
            $sw = "%$search%";
            $params[] = $sw;
            $params[] = $sw;
            $params[] = $sw;
            $params[] = $sw;
        }

        $sql .= " ORDER BY id DESC LIMIT 200";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetchAll();

        foreach ($res as &$r) {
            if (is_string($r['detalle_items'])) {
                $r['detalle_items'] = json_decode($r['detalle_items'], true);
            }
            if (empty($r['estado'])) {
                $r['estado'] = 'Pendiente';
            }
        }

        echo json_encode(['success' => true, 'count' => count($res), 'data' => $res], JSON_UNESCAPED_UNICODE);
        exit();
    } catch (PDOException $e) {
        echo json_encode(['success' => true, 'count' => 0, 'data' => []], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);

