<?php
/**
 * API REST: Gestión de Usuarios (Módulo Administrativo)
 * Plataforma Descartables Peruanos
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validator.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDbConnection();

if (!$pdo) {
    ApiResponse::error('Base de datos no disponible.', 'DB_UNAVAILABLE', 503);
}

// Protección estricta: Solo administradores autorizados pueden acceder o modificar el directorio de usuarios
if (class_exists('Vault')) {
    Vault::requireAdmin();
}

// 1. LISTAR USUARIOS (GET)
if ($method === 'GET') {
    try {
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

        ApiResponse::success($usuarios, [
            'count' => count($usuarios),
            'stats' => $stats
        ]);
    } catch (Throwable $e) {
        ApiResponse::error('Error al consultar usuarios.', 'SERVER_ERROR', 500, $e);
    }
}

$data = Validator::parseJsonInput();
if (empty($data)) {
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
    try {
        $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));
        if ($id <= 0) {
            ApiResponse::error('ID de usuario no válido.', 'VALIDATION_ERROR', 400);
        }

        if (!isset($data['rol'])) {
            ApiResponse::error('El campo rol es requerido.', 'VALIDATION_ERROR', 400);
        }

        $rol = Validator::validateAllowlist($data['rol'], ['cliente', 'admin'], 'Rol');
        if ($rol === null) {
            ApiResponse::error('Rol inválido. Valores permitidos: cliente, admin.', 'VALIDATION_ERROR', 400);
        }

        $currUserStmt = $pdo->prepare("SELECT id, rol FROM usuarios WHERE id = ?");
        $currUserStmt->execute([$id]);
        $targetUser = $currUserStmt->fetch();

        if (!$targetUser) {
            ApiResponse::error('Usuario no encontrado.', 'USER_NOT_FOUND', 404);
        }

        // Impedir que el sistema se quede sin administradores activos
        if ($targetUser['rol'] === 'admin' && $rol !== 'admin') {
            $countAdmins = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn();
            if ($countAdmins <= 1) {
                ApiResponse::error('Operación denegada: No se puede degradar al único administrador activo del sistema.', 'LAST_ADMIN_PROTECTION', 403);
            }
        }

        $stmt = $pdo->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
        $stmt->execute([$rol, $id]);

        ApiResponse::success(null, ['message' => "Rol de usuario actualizado a '$rol' correctamente."]);
    } catch (Throwable $e) {
        ApiResponse::error('Error al actualizar usuario.', 'SERVER_ERROR', 500, $e);
    }
}

// 3. ELIMINAR USUARIO (DELETE)
if ($method === 'DELETE') {
    try {
        $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));
        if ($id <= 0) {
            ApiResponse::error('ID de usuario no válido.', 'VALIDATION_ERROR', 400);
        }

        $currUserStmt = $pdo->prepare("SELECT id, rol FROM usuarios WHERE id = ?");
        $currUserStmt->execute([$id]);
        $targetUser = $currUserStmt->fetch();

        if (!$targetUser) {
            ApiResponse::error('Usuario no encontrado.', 'USER_NOT_FOUND', 404);
        }

        // Regla de último administrador activo: Proteger contra eliminación si es el único admin
        if ($targetUser['rol'] === 'admin') {
            $countAdmins = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn();
            if ($countAdmins <= 1) {
                ApiResponse::error('Operación denegada: No se puede eliminar al único administrador activo del sistema.', 'LAST_ADMIN_PROTECTION', 403);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);

        ApiResponse::success(null, ['message' => 'Usuario eliminado correctamente del sistema.']);
    } catch (Throwable $e) {
        ApiResponse::error('Error al eliminar usuario.', 'SERVER_ERROR', 500, $e);
    }
}

ApiResponse::error('Método no permitido.', 'METHOD_NOT_ALLOWED', 405);

