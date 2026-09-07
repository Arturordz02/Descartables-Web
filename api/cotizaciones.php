<?php
/**
 * API REST: Cotizaciones Corporativas B2B
 * Plataforma Descartables Peruanos
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDbConnection();

// Auto-migración silenciosa y segura de columnas de cotizaciones
if ($pdo) {
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM cotizaciones")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('estado', $cols)) {
            $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN estado VARCHAR(30) DEFAULT 'Pendiente'");
        }
        if (!in_array('notas', $cols)) {
            $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN notas TEXT NULL");
        }
        if (!in_array('total_items', $cols)) {
            $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN total_items INT NOT NULL DEFAULT 0");
        }
    } catch (Exception $ignored) {}
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
    $items = !empty($data['items']) ? $data['items'] : (!empty($data['detalle_items']) ? $data['detalle_items'] : []);
    $documento = trim($data['documento'] ?? $data['cliente_doc'] ?? '');
    $nombre = trim($data['nombre_cliente'] ?? $data['cliente_nombre'] ?? '');

    if (empty($items)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Faltan productos/items para registrar la cotización.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if (empty($documento)) $documento = 'No especificado';
    if (empty($nombre)) $nombre = 'Cliente Web';

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
        $telefono = trim($data['telefono'] ?? $data['cliente_telefono'] ?? '');
        $email = trim($data['email'] ?? $data['cliente_email'] ?? '');
        if (empty($email)) {
            $email = 'ventas@descartablesperuanos.pe';
        }
        $destino = trim($data['destino'] ?? $data['departamento'] ?? 'Lima Metropolitana');
        $total_items = is_array($items) ? array_reduce($items, fn($carry, $i) => $carry + (int)($i['cantidad'] ?? 1), 0) : 0;
        $estado = 'Pendiente';
        $notas = trim($data['notas'] ?? '');
        $itemsJson = is_string($items) ? $items : json_encode($items, JSON_UNESCAPED_UNICODE);

        // Detectar columnas existentes en la tabla cotizaciones
        $cols = $pdo->query("SHOW COLUMNS FROM cotizaciones")->fetchAll(PDO::FETCH_COLUMN);

        $insertCols = ['codigo_cotizacion'];
        $insertVals = [$codigo];
        $placeholders = ['?'];

        if (in_array('usuario_id', $cols)) { $insertCols[] = 'usuario_id'; $insertVals[] = $usuario_id; $placeholders[] = '?'; }
        if (in_array('tipo_comprobante', $cols)) { $insertCols[] = 'tipo_comprobante'; $insertVals[] = $tipo_comprobante; $placeholders[] = '?'; }

        // Nombre
        if (in_array('nombre_cliente', $cols)) { $insertCols[] = 'nombre_cliente'; $insertVals[] = $nombre; $placeholders[] = '?'; }
        elseif (in_array('cliente_nombre', $cols)) { $insertCols[] = 'cliente_nombre'; $insertVals[] = $nombre; $placeholders[] = '?'; }

        // Documento
        if (in_array('documento', $cols)) { $insertCols[] = 'documento'; $insertVals[] = $documento; $placeholders[] = '?'; }
        elseif (in_array('cliente_doc', $cols)) { $insertCols[] = 'cliente_doc'; $insertVals[] = $documento; $placeholders[] = '?'; }

        // Teléfono
        if (in_array('telefono', $cols)) { $insertCols[] = 'telefono'; $insertVals[] = $telefono; $placeholders[] = '?'; }
        elseif (in_array('cliente_telefono', $cols)) { $insertCols[] = 'cliente_telefono'; $insertVals[] = $telefono; $placeholders[] = '?'; }

        // Email
        if (in_array('email', $cols)) { $insertCols[] = 'email'; $insertVals[] = $email; $placeholders[] = '?'; }
        elseif (in_array('cliente_email', $cols)) { $insertCols[] = 'cliente_email'; $insertVals[] = $email; $placeholders[] = '?'; }

        // Destino / Departamento
        if (in_array('destino', $cols)) { $insertCols[] = 'destino'; $insertVals[] = $destino; $placeholders[] = '?'; }
        elseif (in_array('departamento', $cols)) { $insertCols[] = 'departamento'; $insertVals[] = $destino; $placeholders[] = '?'; }

        // Items
        if (in_array('detalle_items', $cols)) { $insertCols[] = 'detalle_items'; $insertVals[] = $itemsJson; $placeholders[] = '?'; }
        elseif (in_array('items', $cols)) { $insertCols[] = 'items'; $insertVals[] = $itemsJson; $placeholders[] = '?'; }

        // Total items
        if (in_array('total_items', $cols)) { $insertCols[] = 'total_items'; $insertVals[] = $total_items; $placeholders[] = '?'; }

        // Estado
        if (in_array('estado', $cols)) { $insertCols[] = 'estado'; $insertVals[] = $estado; $placeholders[] = '?'; }

        // Notas
        if (in_array('notas', $cols)) { $insertCols[] = 'notas'; $insertVals[] = $notas; $placeholders[] = '?'; }

        // Creado en
        if (in_array('creado_en', $cols)) { $insertCols[] = 'creado_en'; $insertVals[] = date('Y-m-d H:i:s'); $placeholders[] = '?'; }

        $sql = "INSERT INTO cotizaciones (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($insertVals);

        $newId = (int)$pdo->lastInsertId();

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
        http_response_code(500);
        echo json_encode([
            'success'            => false,
            'error'              => 'Error al registrar cotización en base de datos: ' . $e->getMessage()
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
        $cols = $pdo->query("SHOW COLUMNS FROM cotizaciones")->fetchAll(PDO::FETCH_COLUMN);

        $colNombre = in_array('nombre_cliente', $cols) ? 'nombre_cliente' : (in_array('cliente_nombre', $cols) ? 'cliente_nombre' : "'Cliente'");
        $colDoc = in_array('documento', $cols) ? 'documento' : (in_array('cliente_doc', $cols) ? 'cliente_doc' : "''");
        $colTel = in_array('telefono', $cols) ? 'telefono' : (in_array('cliente_telefono', $cols) ? 'cliente_telefono' : "''");
        $colDest = in_array('destino', $cols) ? 'destino' : (in_array('departamento', $cols) ? 'departamento' : "'Lima'");

        $sql = "SELECT * FROM cotizaciones WHERE 1=1";
        $params = [];

        if ($codigo) {
            $sql .= " AND codigo_cotizacion = ?";
            $params[] = $codigo;
        } elseif ($doc) {
            $sql .= " AND ({$colDoc} = ?)";
            $params[] = $doc;
        }

        if ($estado) {
            $sql .= " AND estado = ?";
            $params[] = $estado;
        }

        if ($search) {
            $sql .= " AND (codigo_cotizacion LIKE ? OR {$colNombre} LIKE ? OR {$colDoc} LIKE ? OR {$colTel} LIKE ?)";
            $sw = "%$search%";
            $params[] = $sw;
            $params[] = $sw;
            $params[] = $sw;
            $params[] = $sw;
        }

        $sql .= " ORDER BY id DESC LIMIT 200";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalizar estructura de retorno
        foreach ($res as &$r) {
            $rawItems = $r['detalle_items'] ?? $r['items'] ?? '[]';
            $r['items'] = is_string($rawItems) ? json_decode($rawItems, true) : $rawItems;
            $r['detalle_items'] = $r['items'];
            $r['nombre_cliente'] = $r['nombre_cliente'] ?? $r['cliente_nombre'] ?? 'Cliente';
            $r['cliente_nombre'] = $r['nombre_cliente'];
            $r['documento'] = $r['documento'] ?? $r['cliente_doc'] ?? '—';
            $r['cliente_doc'] = $r['documento'];
            $r['telefono'] = $r['telefono'] ?? $r['cliente_telefono'] ?? '';
            $r['cliente_telefono'] = $r['telefono'];
            $r['destino'] = $r['destino'] ?? $r['departamento'] ?? 'Lima Metropolitana';
            $r['departamento'] = $r['destino'];
            $r['codigo'] = $r['codigo_cotizacion'] ?? ('COT-' . $r['id']);
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
