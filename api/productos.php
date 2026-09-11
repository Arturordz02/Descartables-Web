<?php
/**
 * API REST: Productos y Categorías
 * Plataforma Descartables Peruanos
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validator.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (empty($GLOBALS['PRODUCTOS_SCRIPT_INCLUDED'])) {
    $pdo = getDbConnection();
}

// Resuelve la categoría (existente o nueva)
function resolveCategoryId($pdo, &$data) {
    $categoria_id = $data['categoria_id'] ?? null;
    $categoria_nueva = trim($data['categoria_nueva'] ?? '');

    // Si se especificó una categoría nueva o "Otros"
    if ($categoria_id === '__otra__' || $categoria_id === '__nueva__' || !empty($categoria_nueva) || (!is_numeric($categoria_id) && !empty($categoria_id))) {
        $catName = !empty($categoria_nueva) ? $categoria_nueva : trim((string)$categoria_id);
        if (empty($catName) || $catName === '__otra__' || $catName === '__nueva__') {
            $catName = trim($data['categoria_nombre'] ?? 'Otros');
        }

        if (!empty($catName)) {
            // Verificar si ya existe en la BD por nombre
            $checkCat = $pdo->prepare("SELECT id FROM categorias WHERE LOWER(nombre) = LOWER(?) LIMIT 1");
            $checkCat->execute([$catName]);
            $found = $checkCat->fetch();
            if ($found) {
                return (int)$found['id'];
            }

            // Crear slug seguro
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $catName), '-'));
            if (empty($slug)) {
                $slug = 'cat-' . time();
            }

            // Verificar si slug ya existe
            $checkSlug = $pdo->prepare("SELECT id FROM categorias WHERE slug = ? LIMIT 1");
            $checkSlug->execute([$slug]);
            if ($checkSlug->fetch()) {
                $slug .= '-' . rand(10, 99);
            }

            $insertCat = $pdo->prepare("INSERT INTO categorias (nombre, slug, descripcion, icono, color) VALUES (?, ?, ?, 'box', 'from-amber-600/20 to-orange-600/20')");
            $insertCat->execute([
                $catName,
                $slug,
                "Línea especializada: {$catName}"
            ]);
            return (int)$pdo->lastInsertId();
        }
    }

    $id = (int)$categoria_id;
    return $id > 0 ? $id : 1;
}

/**
 * Elimina de forma segura una imagen huérfana gestionada (F12).
 * Protecciones estrictas:
 * 1. NUNCA elimina default.png.
 * 2. Solo elimina archivos gestionados cuyo nombre inicie con 'prod_'.
 * 3. Valida que ningún otro producto en la base de datos use la misma imagen.
 * 4. Valida mediante realpath que el archivo físico resida estrictamente dentro de assets/images/productos/.
 */
function safelyDeleteOrphanProductImage(PDO $pdo, ?string $oldImageUrl, int $excludeProductId = 0): void {
    if (empty($oldImageUrl)) {
        return;
    }

    $cleanUrl = trim($oldImageUrl);
    if ($cleanUrl === 'assets/images/productos/default.png' || basename($cleanUrl) === 'default.png') {
        return;
    }

    $baseName = basename($cleanUrl);
    if (!str_starts_with($baseName, 'prod_')) {
        return;
    }

    // Verificar si otro producto referencia la imagen
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE imagen_url = ? AND id != ?");
    $checkStmt->execute([$cleanUrl, $excludeProductId]);
    if ((int)$checkStmt->fetchColumn() > 0) {
        return;
    }

    $uploadDir = realpath(__DIR__ . '/../assets/images/productos');
    if (!$uploadDir || !is_dir($uploadDir)) {
        return;
    }

    $targetFile = $uploadDir . DIRECTORY_SEPARATOR . $baseName;
    $realTarget = realpath($targetFile);

    // Garantizar que reside dentro de assets/images/productos sin path traversal
    if ($realTarget && is_file($realTarget) && str_starts_with($realTarget, $uploadDir)) {
        @unlink($realTarget);
    }
}

// ================= EJECUCIÓN PRINCIPAL =================
if (empty($GLOBALS['PRODUCTOS_SCRIPT_INCLUDED'])) {

// 1. LISTAR PRODUCTOS O CATEGORÍAS (GET)
if ($method === 'GET') {
    try {
        // Obtener categorías si se solicita específicamente
        if (isset($_GET['tipo']) && $_GET['tipo'] === 'categorias') {
            $stmt = $pdo->query("SELECT c.*, COUNT(p.id) as total_productos 
                                 FROM categorias c 
                                 LEFT JOIN productos p ON c.id = p.categoria_id 
                                 GROUP BY c.id 
                                 ORDER BY c.id ASC");
            $categorias = $stmt->fetchAll();
            echo json_encode([
                'success' => true,
                'data'    => $categorias
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Filtros de productos
        $categoria_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;
        $slug = isset($_GET['categoria']) ? trim($_GET['categoria']) : null;
        $material = isset($_GET['material']) ? trim($_GET['material']) : null;
        $biodegradable = (isset($_GET['biodegradable']) && $_GET['biodegradable'] !== '') ? (int)$_GET['biodegradable'] : null;
        $destacado = isset($_GET['destacado']) ? (int)$_GET['destacado'] : null;
        $search = isset($_GET['q']) ? trim($_GET['q']) : null;

        $sql = "SELECT p.*, c.nombre as categoria_nombre, c.slug as categoria_slug 
                FROM productos p 
                LEFT JOIN categorias c ON p.categoria_id = c.id 
                WHERE 1=1";
        $params = [];

        if ($categoria_id) {
            $sql .= " AND p.categoria_id = ?";
            $params[] = $categoria_id;
        }

        if ($slug && $slug !== 'todos') {
            $sql .= " AND c.slug = ?";
            $params[] = $slug;
        }

        if ($material && $material !== 'todos') {
            $sql .= " AND p.material LIKE ?";
            $params[] = "%$material%";
        }

        if ($biodegradable !== null) {
            $sql .= " AND p.biodegradable = ?";
            $params[] = $biodegradable;
        }

        if ($destacado !== null) {
            $sql .= " AND p.destacado = ?";
            $params[] = $destacado;
        }

        if ($search) {
            $sql .= " AND (p.nombre LIKE ? OR p.sku LIKE ? OR p.descripcion LIKE ? OR p.material LIKE ? OR p.presentacion LIKE ? OR c.nombre LIKE ?)";
            $searchWildcard = "%$search%";
            $params[] = $searchWildcard;
            $params[] = $searchWildcard;
            $params[] = $searchWildcard;
            $params[] = $searchWildcard;
            $params[] = $searchWildcard;
            $params[] = $searchWildcard;
        }

        $sql .= " ORDER BY p.destacado DESC, p.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $productos = $stmt->fetchAll();

        // Mapear booleanos y tipos numéricos adecuadamente
        foreach ($productos as &$prod) {
            $prod['id'] = (int)$prod['id'];
            $prod['categoria_id'] = (int)$prod['categoria_id'];
            $prod['precio'] = ($prod['precio'] !== null && $prod['precio'] !== '') ? (float)$prod['precio'] : null;
            $prod['stock_estado'] = !empty($prod['stock_estado']) ? $prod['stock_estado'] : 'en_stock';
            $prod['biodegradable'] = (bool)$prod['biodegradable'];
            $prod['destacado'] = (bool)$prod['destacado'];
        }

        echo json_encode([
            'success' => true,
            'count'   => count($productos),
            'data'    => $productos
        ], JSON_UNESCAPED_UNICODE);
        exit();
    } catch (Exception $e) {
        ApiResponse::error('Error al consultar productos del catálogo.', 'FETCH_ERROR', 500, $e);
    }
}

// Control de Acceso: Todas las modificaciones de catálogo requieren privilegios de Administrador
if (class_exists('Vault')) {
    Vault::requireAdmin();
}

$data = Validator::parseJsonInput(1048576);

// 2. CREAR PRODUCTO (POST)
if ($method === 'POST') {
    try {
        $nombre = Validator::validateText($data['nombre'] ?? '', 2, 200);
        $sku = Validator::validateText(strtoupper((string)($data['sku'] ?? '')), 2, 50);

        if (!$nombre || !$sku) {
            ApiResponse::error('El Nombre (2-200 caracteres) y el Código SKU (2-50 caracteres) son obligatorios.', 'VALIDATION_ERROR', 400);
        }

        $categoria_id = resolveCategoryId($pdo, $data);
        $descripcion = Validator::validateText($data['descripcion'] ?? '', 0, 2000, true) ?: '';
        $presentacion = Validator::validateText($data['presentacion'] ?? 'Unidad', 1, 100) ?: 'Unidad';
        $material = Validator::validateText($data['material'] ?? 'Polipropileno', 1, 100) ?: 'Polipropileno';

        $precio = null;
        if (isset($data['precio']) && $data['precio'] !== '' && $data['precio'] !== null) {
            $precio = Validator::validatePrice($data['precio'], 0.0, 1000000.0);
            if ($precio === null) {
                ApiResponse::error('El precio debe ser un número positivo válido (máximo 1,000,000).', 'VALIDATION_ERROR', 400);
            }
        }

        $stock_estado = 'en_stock';
        if (isset($data['stock_estado'])) {
            $stock_estado = Validator::validateAllowlist($data['stock_estado'], ['en_stock', 'bajo_pedido', 'agotado']);
            if (!$stock_estado) {
                ApiResponse::error('Estado de stock no válido. Permitidos: en_stock, bajo_pedido, agotado.', 'VALIDATION_ERROR', 400);
            }
        }

        $biodegradable = (isset($data['biodegradable']) && ($data['biodegradable'] === true || $data['biodegradable'] === 1 || $data['biodegradable'] === '1' || $data['biodegradable'] === 'true')) ? 1 : 0;
        $destacado = (isset($data['destacado']) && ($data['destacado'] === true || $data['destacado'] === 1 || $data['destacado'] === '1' || $data['destacado'] === 'true')) ? 1 : 0;
        $rawImgUrl = isset($data['imagen_url']) && trim((string)$data['imagen_url']) !== ''
            ? trim((string)$data['imagen_url'])
            : 'assets/images/productos/default.png';

        $imagen_url = Validator::validateImageUrl($rawImgUrl);
        if (!$imagen_url) {
            ApiResponse::error('Ruta de imagen de producto no válida o no permitida.', 'VALIDATION_ERROR', 400);
        }

        // Verificar si el SKU ya existe
        $check = $pdo->prepare("SELECT id FROM productos WHERE UPPER(sku) = UPPER(?) LIMIT 1");
        $check->execute([$sku]);
        if ($check->fetch()) {
            ApiResponse::error("El código SKU '$sku' ya existe en el catálogo.", 'SKU_ALREADY_EXISTS', 400);
        }

        $insertSql = "INSERT INTO productos (categoria_id, sku, nombre, descripcion, presentacion, material, precio, stock_estado, biodegradable, imagen_url, destacado) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($insertSql);
        $stmt->execute([
            $categoria_id,
            $sku,
            $nombre,
            $descripcion,
            $presentacion,
            $material,
            $precio,
            $stock_estado,
            $biodegradable,
            $imagen_url,
            $destacado
        ]);

        $newId = (int)$pdo->lastInsertId();
        $fetchStmt = $pdo->prepare("SELECT p.*, c.nombre as categoria_nombre, c.slug as categoria_slug 
                                    FROM productos p 
                                    LEFT JOIN categorias c ON p.categoria_id = c.id 
                                    WHERE p.id = ?");
        $fetchStmt->execute([$newId]);
        $created = $fetchStmt->fetch();
        $created['id'] = (int)$created['id'];
        $created['categoria_id'] = (int)$created['categoria_id'];
        $created['precio'] = ($created['precio'] !== null && $created['precio'] !== '') ? (float)$created['precio'] : null;
        $created['stock_estado'] = !empty($created['stock_estado']) ? $created['stock_estado'] : 'en_stock';
        $created['biodegradable'] = (bool)$created['biodegradable'];
        $created['destacado'] = (bool)$created['destacado'];

        echo json_encode([
            'success' => true,
            'message' => 'Producto agregado exitosamente al catálogo.',
            'data'    => $created
        ], JSON_UNESCAPED_UNICODE);
        exit();
    } catch (Exception $e) {
        ApiResponse::error('Error al crear el producto.', 'CREATE_ERROR', 500, $e);
    }
}

// 3. EDITAR PRODUCTO (PUT o PATCH)
if ($method === 'PUT' || $method === 'PATCH') {
    try {
        $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID de producto no válido.'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Detección de edición rápida (cuando solo se actualiza precio o disponibilidad)
        $isQuickUpdate = isset($data['quick_update']) || (empty($data['nombre']) && empty($data['sku']) && (array_key_exists('stock_estado', $data) || array_key_exists('precio', $data)));

        if ($isQuickUpdate) {
            $fieldsToUpdate = [];
            $params = [];

            if (array_key_exists('stock_estado', $data)) {
                $stock_estado = Validator::validateAllowlist((string)$data['stock_estado'], ['en_stock', 'bajo_pedido', 'agotado']);
                if (!$stock_estado) {
                    ApiResponse::error('Estado de stock no válido. Permitidos: en_stock, bajo_pedido, agotado.', 'VALIDATION_ERROR', 400);
                }
                $fieldsToUpdate[] = "stock_estado = ?";
                $params[] = $stock_estado;
            }

            if (array_key_exists('precio', $data)) {
                $precio = null;
                if ($data['precio'] !== '' && $data['precio'] !== null) {
                    $precio = Validator::validatePrice($data['precio'], 0.0, 1000000.0);
                    if ($precio === null) {
                        ApiResponse::error('El precio debe ser un número positivo válido (máx 1,000,000).', 'VALIDATION_ERROR', 400);
                    }
                }
                $fieldsToUpdate[] = "precio = ?";
                $params[] = $precio;
            }

            if (empty($fieldsToUpdate)) {
                ApiResponse::error('No se proporcionaron campos válidos para actualizar.', 'VALIDATION_ERROR', 400);
            }

            $params[] = $id;
            $updateSql = "UPDATE productos SET " . implode(', ', $fieldsToUpdate) . " WHERE id = ?";
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute($params);

            $fetchStmt = $pdo->prepare("SELECT p.*, c.nombre as categoria_nombre, c.slug as categoria_slug 
                                        FROM productos p 
                                        LEFT JOIN categorias c ON p.categoria_id = c.id 
                                        WHERE p.id = ?");
            $fetchStmt->execute([$id]);
            $updated = $fetchStmt->fetch();
            $updated['id'] = (int)$updated['id'];
            $updated['categoria_id'] = (int)$updated['categoria_id'];
            $updated['precio'] = ($updated['precio'] !== null && $updated['precio'] !== '') ? (float)$updated['precio'] : null;
            $updated['stock_estado'] = !empty($updated['stock_estado']) ? $updated['stock_estado'] : 'en_stock';
            $updated['biodegradable'] = (bool)$updated['biodegradable'];
            $updated['destacado'] = (bool)$updated['destacado'];

            echo json_encode([
                'success' => true,
                'message' => 'Producto actualizado correctamente.',
                'data'    => $updated
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $nombre = Validator::validateText($data['nombre'] ?? '', 2, 200);
        $sku = Validator::validateText(strtoupper((string)($data['sku'] ?? '')), 2, 50);

        if (!$nombre || !$sku) {
            ApiResponse::error('El Nombre (2-200 caracteres) y el Código SKU (2-50 caracteres) son obligatorios.', 'VALIDATION_ERROR', 400);
        }

        $categoria_id = resolveCategoryId($pdo, $data);
        $descripcion = Validator::validateText($data['descripcion'] ?? '', 0, 2000, true) ?: '';
        $presentacion = Validator::validateText($data['presentacion'] ?? 'Unidad', 1, 100) ?: 'Unidad';
        $material = Validator::validateText($data['material'] ?? 'Polipropileno', 1, 100) ?: 'Polipropileno';

        $precio = null;
        if (isset($data['precio']) && $data['precio'] !== '' && $data['precio'] !== null) {
            $precio = Validator::validatePrice($data['precio'], 0.0, 1000000.0);
            if ($precio === null) {
                ApiResponse::error('El precio debe ser un número positivo válido (máximo 1,000,000).', 'VALIDATION_ERROR', 400);
            }
        }

        $stock_estado = 'en_stock';
        if (isset($data['stock_estado'])) {
            $stock_estado = Validator::validateAllowlist($data['stock_estado'], ['en_stock', 'bajo_pedido', 'agotado']);
            if (!$stock_estado) {
                ApiResponse::error('Estado de stock no válido. Permitidos: en_stock, bajo_pedido, agotado.', 'VALIDATION_ERROR', 400);
            }
        }

        $biodegradable = (isset($data['biodegradable']) && ($data['biodegradable'] === true || $data['biodegradable'] === 1 || $data['biodegradable'] === '1' || $data['biodegradable'] === 'true')) ? 1 : 0;
        $destacado = (isset($data['destacado']) && ($data['destacado'] === true || $data['destacado'] === 1 || $data['destacado'] === '1' || $data['destacado'] === 'true')) ? 1 : 0;

        $hasNewImage = false;
        $imagen_url = null;
        if (isset($data['imagen_url']) && trim((string)$data['imagen_url']) !== '') {
            $rawImg = trim((string)$data['imagen_url']);
            $imagen_url = Validator::validateImageUrl($rawImg);
            if (!$imagen_url) {
                ApiResponse::error('Ruta de imagen de producto no válida o no permitida.', 'VALIDATION_ERROR', 400);
            }
            $hasNewImage = true;
        }

        // Consultar imagen actual para gestionar reemplazo seguro
        $currStmt = $pdo->prepare("SELECT imagen_url FROM productos WHERE id = ?");
        $currStmt->execute([$id]);
        $oldImageUrl = $currStmt->fetchColumn();

        if (empty($nombre) || empty($sku)) {
            ApiResponse::error('El Nombre y el Código SKU son obligatorios.', 'VALIDATION_ERROR', 400);
        }

        // Verificar SKU repetido en otro producto
        $check = $pdo->prepare("SELECT id FROM productos WHERE UPPER(sku) = UPPER(?) AND id != ? LIMIT 1");
        $check->execute([$sku, $id]);
        if ($check->fetch()) {
            ApiResponse::error("El código SKU '$sku' ya pertenece a otro producto.", 'SKU_ALREADY_EXISTS', 400);
        }

        $updateSql = "UPDATE productos SET 
                        categoria_id = ?, 
                        sku = ?, 
                        nombre = ?, 
                        descripcion = ?, 
                        presentacion = ?, 
                        material = ?, 
                        precio = ?, 
                        stock_estado = ?, 
                        biodegradable = ?, 
                        destacado = ?" . ($hasNewImage ? ", imagen_url = ?" : "") . "
                      WHERE id = ?";
        
        $params = [
            $categoria_id,
            $sku,
            $nombre,
            $descripcion,
            $presentacion,
            $material,
            $precio,
            $stock_estado,
            $biodegradable,
            $destacado
        ];
        if ($hasNewImage) {
            $params[] = $imagen_url;
        }
        $params[] = $id;

        $stmt = $pdo->prepare($updateSql);
        $stmt->execute($params);

        // Si se actualizó la imagen, limpiar la imagen anterior si quedó huérfana
        if ($hasNewImage && $oldImageUrl && $oldImageUrl !== $imagen_url) {
            safelyDeleteOrphanProductImage($pdo, (string)$oldImageUrl, $id);
        }

        $fetchStmt = $pdo->prepare("SELECT p.*, c.nombre as categoria_nombre, c.slug as categoria_slug 
                                    FROM productos p 
                                    LEFT JOIN categorias c ON p.categoria_id = c.id 
                                    WHERE p.id = ?");
        $fetchStmt->execute([$id]);
        $updated = $fetchStmt->fetch();
        $updated['id'] = (int)$updated['id'];
        $updated['categoria_id'] = (int)$updated['categoria_id'];
        $updated['precio'] = ($updated['precio'] !== null && $updated['precio'] !== '') ? (float)$updated['precio'] : null;
        $updated['stock_estado'] = !empty($updated['stock_estado']) ? $updated['stock_estado'] : 'en_stock';
        $updated['biodegradable'] = (bool)$updated['biodegradable'];
        $updated['destacado'] = (bool)$updated['destacado'];

        echo json_encode([
            'success' => true,
            'message' => 'Producto actualizado correctamente.',
            'data'    => $updated
        ], JSON_UNESCAPED_UNICODE);
        exit();
    } catch (Exception $e) {
        ApiResponse::error('Error al actualizar el producto.', 'UPDATE_ERROR', 500, $e);
    }
}

// 4. ELIMINAR PRODUCTO (DELETE)
if ($method === 'DELETE') {
    try {
        $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));
        if ($id <= 0) {
            ApiResponse::error('ID de producto no válido para eliminar.', 'VALIDATION_ERROR', 400);
        }

        // Obtener imagen del producto antes de eliminar
        $currStmt = $pdo->prepare("SELECT imagen_url FROM productos WHERE id = ?");
        $currStmt->execute([$id]);
        $currentImg = $currStmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
        $stmt->execute([$id]);

        // Limpiar imagen si quedó huérfana
        if ($currentImg) {
            safelyDeleteOrphanProductImage($pdo, (string)$currentImg, $id);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Producto eliminado correctamente del catálogo.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    } catch (Exception $e) {
        ApiResponse::error('Error al eliminar el producto.', 'DELETE_ERROR', 500, $e);
    }
}

ApiResponse::error('Método no permitido.', 'METHOD_NOT_ALLOWED', 405);
}
