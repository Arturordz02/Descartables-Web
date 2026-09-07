<?php
/**
 * API REST: Gestión Completa de Categorías
 * Plataforma Descartables Peruanos
 */

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDbConnection();

// Asegurar que las columnas icono y color existan
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM categorias LIKE 'color'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE categorias ADD COLUMN icono VARCHAR(50) DEFAULT 'box'");
        $pdo->exec("ALTER TABLE categorias ADD COLUMN color VARCHAR(100) DEFAULT 'from-amber-600/20 to-orange-600/20'");
    }
} catch (Exception $ignored) {}

// 1. LISTAR CATEGORÍAS (GET)
if ($method === 'GET') {
    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if ($id) {
            $stmt = $pdo->prepare("SELECT c.*, COUNT(p.id) as total_productos 
                                   FROM categorias c 
                                   LEFT JOIN productos p ON c.id = p.categoria_id 
                                   WHERE c.id = ? 
                                   GROUP BY c.id");
            $stmt->execute([$id]);
            $cat = $stmt->fetch();
            if (!$cat) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Categoría no encontrada.'], JSON_UNESCAPED_UNICODE);
                exit();
            }
            $cat['id'] = (int)$cat['id'];
            $cat['total_productos'] = (int)($cat['total_productos'] ?? 0);
            echo json_encode(['success' => true, 'data' => $cat], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $stmt = $pdo->query("SELECT c.*, COUNT(p.id) as total_productos 
                             FROM categorias c 
                             LEFT JOIN productos p ON c.id = p.categoria_id 
                             GROUP BY c.id 
                             ORDER BY c.id ASC");
        $categorias = $stmt->fetchAll();

        foreach ($categorias as &$c) {
            $c['id'] = (int)$c['id'];
            $c['total_productos'] = (int)($c['total_productos'] ?? 0);
            $c['icono'] = !empty($c['icono']) ? $c['icono'] : 'box';
            $c['color'] = !empty($c['color']) ? $c['color'] : 'from-amber-600/20 to-orange-600/20';
        }

        echo json_encode([
            'success' => true,
            'count'   => count($categorias),
            'data'    => $categorias
        ], JSON_UNESCAPED_UNICODE);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al listar categorías: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// Control de Acceso: La gestión y alteración de categorías requiere privilegios de Administrador
if (class_exists('Vault')) {
    Vault::requireAdmin();
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!$data || !is_array($data)) {
    $data = $_POST;
}

// 2. CREAR CATEGORÍA (POST)
if ($method === 'POST') {
    try {
        $nombre = trim($data['nombre'] ?? '');
        $descripcion = trim($data['descripcion'] ?? '');
        $icono = trim($data['icono'] ?? 'box');
        $color = trim($data['color'] ?? 'from-amber-600/20 to-orange-600/20');
        $slug = trim($data['slug'] ?? '');

        if (empty($nombre)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El nombre de la categoría es obligatorio.'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Generar slug si no se proporcionó
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nombre), '-'));
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slug), '-'));
        }
        if (empty($slug)) $slug = 'cat-' . time();

        // Verificar unicidad de slug
        $checkSlug = $pdo->prepare("SELECT id FROM categorias WHERE slug = ? LIMIT 1");
        $checkSlug->execute([$slug]);
        if ($checkSlug->fetch()) {
            $slug .= '-' . rand(10, 99);
        }

        // Verificar si ya existe categoría con el mismo nombre
        $checkName = $pdo->prepare("SELECT id FROM categorias WHERE LOWER(nombre) = LOWER(?) LIMIT 1");
        $checkName->execute([$nombre]);
        if ($checkName->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Ya existe una categoría con el nombre '$nombre'."], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO categorias (nombre, slug, descripcion, icono, color) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $nombre,
            $slug,
            !empty($descripcion) ? $descripcion : "Línea especializada: {$nombre}",
            !empty($icono) ? $icono : 'box',
            !empty($color) ? $color : 'from-amber-600/20 to-orange-600/20'
        ]);

        $newId = (int)$pdo->lastInsertId();

        $fetchStmt = $pdo->prepare("SELECT c.*, 0 as total_productos FROM categorias c WHERE c.id = ?");
        $fetchStmt->execute([$newId]);
        $created = $fetchStmt->fetch();
        $created['id'] = (int)$created['id'];
        $created['total_productos'] = 0;

        echo json_encode([
            'success' => true,
            'message' => 'Categoría creada exitosamente.',
            'data'    => $created
        ], JSON_UNESCAPED_UNICODE);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al crear categoría: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// 3. EDITAR CATEGORÍA (PUT)
if ($method === 'PUT') {
    try {
        $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID de categoría no válido.'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $nombre = trim($data['nombre'] ?? '');
        $descripcion = trim($data['descripcion'] ?? '');
        $icono = trim($data['icono'] ?? 'box');
        $color = trim($data['color'] ?? 'from-amber-600/20 to-orange-600/20');
        $slug = trim($data['slug'] ?? '');

        if (empty($nombre)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El nombre de la categoría es obligatorio.'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Generar o limpiar slug
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nombre), '-'));
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slug), '-'));
        }
        if (empty($slug)) $slug = 'cat-' . $id;

        // Verificar si el slug ya pertenece a otra categoría
        $checkSlug = $pdo->prepare("SELECT id FROM categorias WHERE slug = ? AND id != ? LIMIT 1");
        $checkSlug->execute([$slug, $id]);
        if ($checkSlug->fetch()) {
            $slug .= '-' . rand(10, 99);
        }

        // Verificar si el nombre ya pertenece a otra categoría
        $checkName = $pdo->prepare("SELECT id FROM categorias WHERE LOWER(nombre) = LOWER(?) AND id != ? LIMIT 1");
        $checkName->execute([$nombre, $id]);
        if ($checkName->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Ya existe otra categoría con el nombre '$nombre'."], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $stmt = $pdo->prepare("UPDATE categorias SET nombre = ?, slug = ?, descripcion = ?, icono = ?, color = ? WHERE id = ?");
        $stmt->execute([
            $nombre,
            $slug,
            $descripcion,
            $icono,
            $color,
            $id
        ]);

        $fetchStmt = $pdo->prepare("SELECT c.*, COUNT(p.id) as total_productos 
                                    FROM categorias c 
                                    LEFT JOIN productos p ON c.id = p.categoria_id 
                                    WHERE c.id = ? 
                                    GROUP BY c.id");
        $fetchStmt->execute([$id]);
        $updated = $fetchStmt->fetch();
        $updated['id'] = (int)$updated['id'];
        $updated['total_productos'] = (int)($updated['total_productos'] ?? 0);

        echo json_encode([
            'success' => true,
            'message' => 'Categoría actualizada correctamente.',
            'data'    => $updated
        ], JSON_UNESCAPED_UNICODE);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al actualizar categoría: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// 4. ELIMINAR CATEGORÍA (DELETE)
if ($method === 'DELETE') {
    try {
        $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID de categoría no válido.'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Verificar si tiene productos asociados
        $checkProds = $pdo->prepare("SELECT COUNT(*) as c FROM productos WHERE categoria_id = ?");
        $checkProds->execute([$id]);
        $prodCount = (int)($checkProds->fetch()['c'] ?? 0);

        if ($prodCount > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'error' => "No se puede eliminar la categoría porque contiene {$prodCount} producto(s) asignado(s). Reasigna o elimina los productos antes de borrar la categoría."
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'Categoría eliminada exitosamente.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al eliminar categoría: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);

