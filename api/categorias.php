<?php
/**
 * API REST: Gestión Completa de Categorías
 * Plataforma Descartables Peruanos
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validator.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDbConnection();

if (!$pdo) {
    ApiResponse::error('Base de datos no disponible.', 'DB_UNAVAILABLE', 503);
}

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
                ApiResponse::error('Categoría no encontrada.', 'CATEGORY_NOT_FOUND', 404);
            }
            $cat['id'] = (int)$cat['id'];
            $cat['total_productos'] = (int)($cat['total_productos'] ?? 0);
            ApiResponse::success($cat);
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

        ApiResponse::success($categorias, [
            'count' => count($categorias)
        ]);
    } catch (Throwable $e) {
        ApiResponse::error('Error al listar categorías.', 'SERVER_ERROR', 500, $e);
    }
}

// Control de Acceso: La gestión y alteración de categorías requiere privilegios de Administrador
if (class_exists('Vault')) {
    Vault::requireAdmin();
}

$data = Validator::parseJsonInput(1048576);

// 2. CREAR CATEGORÍA (POST)
if ($method === 'POST') {
    try {
        $nombre = Validator::validateText($data['nombre'] ?? '', 2, 100);
        if (!$nombre) {
            ApiResponse::error('El nombre de la categoría debe tener entre 2 y 100 caracteres.', 'VALIDATION_ERROR', 400);
        }

        $slugRaw = trim((string)($data['slug'] ?? ''));
        if (!empty($slugRaw)) {
            $slug = Validator::validateSlug($slugRaw);
            if (!$slug) {
                ApiResponse::error('El slug debe contener solo letras minúsculas, números y guiones (2-100 caracteres).', 'VALIDATION_ERROR', 400);
            }
        } else {
            $slug = Validator::validateSlug(strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nombre), '-')));
            if (!$slug) $slug = 'cat-' . time();
        }

        $colorRaw = trim((string)($data['color'] ?? 'from-amber-600/20 to-orange-600/20'));
        $color = Validator::validateSafeColor($colorRaw);
        if (!$color) {
            ApiResponse::error('La clase de color contiene caracteres no permitidos.', 'VALIDATION_ERROR', 400);
        }

        $descripcion = Validator::validateText($data['descripcion'] ?? '', 0, 1000, true) ?: "Línea especializada: {$nombre}";
        $icono = Validator::validateText($data['icono'] ?? 'box', 1, 50) ?: 'box';

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
            ApiResponse::error("Ya existe una categoría con el nombre '$nombre'.", 'CATEGORY_EXISTS', 400);
        }

        $stmt = $pdo->prepare("INSERT INTO categorias (nombre, slug, descripcion, icono, color) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $nombre,
            $slug,
            $descripcion,
            $icono,
            $color
        ]);

        $newId = (int)$pdo->lastInsertId();

        $fetchStmt = $pdo->prepare("SELECT c.*, 0 as total_productos FROM categorias c WHERE c.id = ?");
        $fetchStmt->execute([$newId]);
        $created = $fetchStmt->fetch();
        $created['id'] = (int)$created['id'];
        $created['total_productos'] = 0;

        ApiResponse::success($created, ['message' => 'Categoría creada exitosamente.']);
    } catch (Throwable $e) {
        ApiResponse::error('Error al crear categoría.', 'SERVER_ERROR', 500, $e);
    }
}

// 3. EDITAR CATEGORÍA (PUT)
if ($method === 'PUT') {
    try {
        $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));
        if ($id <= 0) {
            ApiResponse::error('ID de categoría no válido.', 'VALIDATION_ERROR', 400);
        }

        $nombre = Validator::validateText($data['nombre'] ?? '', 2, 100);
        if (!$nombre) {
            ApiResponse::error('El nombre de la categoría debe tener entre 2 y 100 caracteres.', 'VALIDATION_ERROR', 400);
        }

        $slugRaw = trim((string)($data['slug'] ?? ''));
        if (!empty($slugRaw)) {
            $slug = Validator::validateSlug($slugRaw);
            if (!$slug) {
                ApiResponse::error('El slug debe contener solo letras minúsculas, números y guiones (2-100 caracteres).', 'VALIDATION_ERROR', 400);
            }
        } else {
            $slug = Validator::validateSlug(strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nombre), '-')));
            if (!$slug) $slug = 'cat-' . $id;
        }

        $colorRaw = trim((string)($data['color'] ?? 'from-amber-600/20 to-orange-600/20'));
        $color = Validator::validateSafeColor($colorRaw);
        if (!$color) {
            ApiResponse::error('La clase de color contiene caracteres no permitidos.', 'VALIDATION_ERROR', 400);
        }

        $descripcion = Validator::validateText($data['descripcion'] ?? '', 0, 1000, true) ?: "Línea especializada: {$nombre}";
        $icono = Validator::validateText($data['icono'] ?? 'box', 1, 50) ?: 'box';

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
            ApiResponse::error("Ya existe otra categoría con el nombre '$nombre'.", 'CATEGORY_EXISTS', 400);
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

        ApiResponse::success($updated, ['message' => 'Categoría actualizada correctamente.']);
    } catch (Throwable $e) {
        ApiResponse::error('Error al actualizar categoría.', 'SERVER_ERROR', 500, $e);
    }
}

// 4. ELIMINAR CATEGORÍA (DELETE)
if ($method === 'DELETE') {
    try {
        $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));
        if ($id <= 0) {
            ApiResponse::error('ID de categoría no válido.', 'VALIDATION_ERROR', 400);
        }

        // Verificar si tiene productos asociados
        $checkProds = $pdo->prepare("SELECT COUNT(*) as c FROM productos WHERE categoria_id = ?");
        $checkProds->execute([$id]);
        $prodCount = (int)($checkProds->fetch()['c'] ?? 0);

        if ($prodCount > 0) {
            ApiResponse::error("No se puede eliminar la categoría porque contiene {$prodCount} producto(s) asignado(s). Reasigna o elimina los productos antes de borrar la categoría.", 'CATEGORY_HAS_PRODUCTS', 400);
        }

        $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
        $stmt->execute([$id]);

        ApiResponse::success(null, ['message' => 'Categoría eliminada exitosamente.']);
    } catch (Throwable $e) {
        ApiResponse::error('Error al eliminar categoría.', 'SERVER_ERROR', 500, $e);
    }
}

ApiResponse::error('Método no permitido.', 'METHOD_NOT_ALLOWED', 405);

