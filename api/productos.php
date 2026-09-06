<?php
/**
 * API REST: Productos y Categorías
 * Plataforma Descartables Peruanos
 */

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDbConnection();

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

// 1. LISTAR PRODUCTOS O CATEGORÍAS (GET)
if ($method === 'GET') {
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
        $prod['biodegradable'] = (bool)$prod['biodegradable'];
        $prod['destacado'] = (bool)$prod['destacado'];
    }

    echo json_encode([
        'success' => true,
        'count'   => count($productos),
        'data'    => $productos
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!$data || !is_array($data)) {
    $data = $_POST;
}

// 2. CREAR PRODUCTO (POST)
if ($method === 'POST') {
    $nombre = trim($data['nombre'] ?? '');
    $sku = strtoupper(trim($data['sku'] ?? ''));
    $categoria_id = resolveCategoryId($pdo, $data);
    $descripcion = trim($data['descripcion'] ?? '');
    $presentacion = trim($data['presentacion'] ?? 'Unidad');
    $material = trim($data['material'] ?? 'Polipropileno');
    $precio = (isset($data['precio']) && $data['precio'] !== '' && $data['precio'] !== null) ? (float)$data['precio'] : null;
    $biodegradable = (isset($data['biodegradable']) && ($data['biodegradable'] === true || $data['biodegradable'] === 1 || $data['biodegradable'] === '1' || $data['biodegradable'] === 'true')) ? 1 : 0;
    $destacado = (isset($data['destacado']) && ($data['destacado'] === true || $data['destacado'] === 1 || $data['destacado'] === '1' || $data['destacado'] === 'true')) ? 1 : 0;
    $imagen_url = trim($data['imagen_url'] ?? 'assets/images/productos/default.png');

    if (empty($nombre) || empty($sku)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El Nombre y el Código SKU son obligatorios.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Verificar si el SKU ya existe
    $check = $pdo->prepare("SELECT id FROM productos WHERE UPPER(sku) = UPPER(?) LIMIT 1");
    $check->execute([$sku]);
    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "El código SKU '$sku' ya existe en el catálogo."], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $insertSql = "INSERT INTO productos (categoria_id, sku, nombre, descripcion, presentacion, material, precio, biodegradable, imagen_url, destacado) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($insertSql);
    $stmt->execute([
        $categoria_id,
        $sku,
        $nombre,
        $descripcion,
        $presentacion,
        $material,
        $precio,
        $biodegradable,
        $imagen_url,
        $destacado
    ]);

    $newId = $pdo->lastInsertId();
    $fetchStmt = $pdo->prepare("SELECT p.*, c.nombre as categoria_nombre, c.slug as categoria_slug 
                                FROM productos p 
                                LEFT JOIN categorias c ON p.categoria_id = c.id 
                                WHERE p.id = ?");
    $fetchStmt->execute([$newId]);
    $created = $fetchStmt->fetch();
    $created['id'] = (int)$created['id'];
    $created['categoria_id'] = (int)$created['categoria_id'];
    $created['precio'] = ($created['precio'] !== null && $created['precio'] !== '') ? (float)$created['precio'] : null;
    $created['biodegradable'] = (bool)$created['biodegradable'];
    $created['destacado'] = (bool)$created['destacado'];

    echo json_encode([
        'success' => true,
        'message' => 'Producto agregado exitosamente al catálogo.',
        'data'    => $created
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// 3. EDITAR PRODUCTO (PUT)
if ($method === 'PUT') {
    $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID de producto no válido.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $nombre = trim($data['nombre'] ?? '');
    $sku = strtoupper(trim($data['sku'] ?? ''));
    $categoria_id = resolveCategoryId($pdo, $data);
    $descripcion = trim($data['descripcion'] ?? '');
    $presentacion = trim($data['presentacion'] ?? 'Unidad');
    $material = trim($data['material'] ?? 'Polipropileno');
    $precio = (isset($data['precio']) && $data['precio'] !== '' && $data['precio'] !== null) ? (float)$data['precio'] : null;
    $biodegradable = (isset($data['biodegradable']) && ($data['biodegradable'] === true || $data['biodegradable'] === 1 || $data['biodegradable'] === '1' || $data['biodegradable'] === 'true')) ? 1 : 0;
    $destacado = (isset($data['destacado']) && ($data['destacado'] === true || $data['destacado'] === 1 || $data['destacado'] === '1' || $data['destacado'] === 'true')) ? 1 : 0;
    $imagen_url = trim($data['imagen_url'] ?? '');

    if (empty($nombre) || empty($sku)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El Nombre y el Código SKU son obligatorios.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Verificar SKU repetido en otro producto
    $check = $pdo->prepare("SELECT id FROM productos WHERE UPPER(sku) = UPPER(?) AND id != ? LIMIT 1");
    $check->execute([$sku, $id]);
    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "El código SKU '$sku' ya pertenece a otro producto."], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $updateSql = "UPDATE productos SET 
                    categoria_id = ?, 
                    sku = ?, 
                    nombre = ?, 
                    descripcion = ?, 
                    presentacion = ?, 
                    material = ?, 
                    precio = ?, 
                    biodegradable = ?, 
                    destacado = ?" . (!empty($imagen_url) ? ", imagen_url = ?" : "") . "
                  WHERE id = ?";
    
    $params = [
        $categoria_id,
        $sku,
        $nombre,
        $descripcion,
        $presentacion,
        $material,
        $precio,
        $biodegradable,
        $destacado
    ];
    if (!empty($imagen_url)) {
        $params[] = $imagen_url;
    }
    $params[] = $id;

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
    $updated['biodegradable'] = (bool)$updated['biodegradable'];
    $updated['destacado'] = (bool)$updated['destacado'];

    echo json_encode([
        'success' => true,
        'message' => 'Producto actualizado correctamente.',
        'data'    => $updated
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// 4. ELIMINAR PRODUCTO (DELETE)
if ($method === 'DELETE') {
    $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID de producto no válido para eliminar.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Producto eliminado correctamente del catálogo.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
