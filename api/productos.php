<?php
/**
 * API REST: Productos y Categorías
 */

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $pdo = getDbConnection();
    
    // Obtener categorías si se solicita específicamente
    if (isset($_GET['tipo']) && $_GET['tipo'] === 'categorias') {
        $stmt = $pdo->query("SELECT * FROM categorias ORDER BY id ASC");
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
    $biodegradable = isset($_GET['biodegradable']) ? (int)$_GET['biodegradable'] : null;
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

    if ($slug) {
        $sql .= " AND c.slug = ?";
        $params[] = $slug;
    }

    if ($material) {
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
        $sql .= " AND (p.nombre LIKE ? OR p.sku LIKE ? OR p.descripcion LIKE ?)";
        $searchWildcard = "%$search%";
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
    }

    $sql .= " ORDER BY p.destacado DESC, p.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll();

    // Mapear booleanos adecuadamente
    foreach ($productos as &$prod) {
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

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);

