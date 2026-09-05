<?php
/**
 * API REST: Gestión de Usuarios (Módulo Administrativo)
 * Plataforma Descartables Peruanos
 */

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDbConnection();

// 1. LISTAR USUARIOS (GET)
if ($method === 'GET') {
    $search = isset($_GET['q']) ? trim($_GET['q']) : null;
    $rol = isset($_GET['rol']) ? trim($_GET['rol']) : null;

    $sql = "SELECT id, tipo_documento, numero_documento, nombre_razon_social, email, telefono, departamento, provincia, distrito, direccion, rol, creado_en 
            FROM usuarios 
            WHERE 1=1";
    $params = [];

    if ($rol) {
        $sql .= " AND rol = ?";
        $params[] = $rol;
    }

    if ($search) {
        $sql .= " AND (nombre_razon_social LIKE ? OR numero_documento LIKE ? OR email LIKE ? OR telefono LIKE ?)";
        $wildcard = "%$search%";
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
    }

    $sql .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll();

    // Contadores rápidos para el dashboard
    $stmtCount = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN rol = 'cliente' THEN 1 ELSE 0 END) as clientes,
        SUM(CASE WHEN rol = 'admin' THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN tipo_documento = 'RUC' THEN 1 ELSE 0 END) as empresas,
        SUM(CASE WHEN tipo_documento = 'DNI' THEN 1 ELSE 0 END) as naturales
    FROM usuarios");
    $stats = $stmtCount->fetch();

    echo json_encode([
        'success' => true,
        'count'   => count($usuarios),
        'stats'   => $stats,
        'data'    => $usuarios
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!$data || !is_array($data)) {
    $data = $_POST;
}

if ($method === 'POST' && isset($data['action'])) {
    if ($data['action'] === 'delete') {
        $method = 'DELETE';
    } else if ($data['action'] === 'update_role' || $data['action'] === 'update') {
        $method = 'PUT';
    }
}

// 2. ACTUALIZAR ROL O DATOS DE USUARIO (PUT)
if ($method === 'PUT') {
    $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));
    $rol = trim($data['rol'] ?? '');

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID de usuario no válido.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if (!empty($rol) && in_array($rol, ['cliente', 'admin'])) {
        $stmt = $pdo->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
        $stmt->execute([$rol, $id]);

        echo json_encode([
            'success' => true,
            'message' => "Rol de usuario actualizado a '$rol' correctamente."
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos de actualización no válidos.'], JSON_UNESCAPED_UNICODE);
    exit();
}

// 3. ELIMINAR USUARIO (DELETE)
if ($method === 'DELETE') {
    $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID de usuario no válido.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Proteger el admin principal
    if ($id === 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'No se puede eliminar la cuenta principal de administración.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Usuario eliminado correctamente del sistema.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);

