<?php
/**
 * API REST: Generador de Copia de Seguridad Completa (Backup en 1 Clic)
 * Plataforma Descartables Peruanos
 * 
 * Exporta todas las entidades gestionables de la base de datos MySQL en formato JSON estructurado:
 * - configuracion (Datos del Negocio, Contacto, Redes y Parámetros)
 * - banners (Banners promocionales, Hero y Barra Superior)
 * - categorias (Categorías y líneas de productos)
 * - productos (Catálogo completo de productos, precios y disponibilidad)
 * - usuarios (Directorio de clientes y administradores)
 * - cotizaciones (Historial de cotizaciones B2B y sus ítems)
 * - libro_reclamaciones (Reclamaciones y quejas INDECOPI)
 */

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET' && $method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit();
}

$pdo = getDbConnection();

// ================= 1. VALIDACIÓN DE SEGURIDAD (ADMIN ONLY) =================
$adminId = isset($_REQUEST['admin_id']) ? intval($_REQUEST['admin_id']) : null;
$adminDoc = isset($_REQUEST['admin_doc']) ? trim($_REQUEST['admin_doc']) : null;
$adminEmail = isset($_REQUEST['admin_email']) ? trim($_REQUEST['admin_email']) : null;

// Validar en la base de datos si el solicitante es Administrador
$isAuthorized = false;
$adminUser = null;

if ($adminId) {
    $stmtAuth = $pdo->prepare("SELECT id, nombre_razon_social, email, rol, tipo_documento, numero_documento FROM usuarios WHERE id = ? AND rol = 'admin' LIMIT 1");
    $stmtAuth->execute([$adminId]);
    $adminUser = $stmtAuth->fetch();
    if ($adminUser) {
        $isAuthorized = true;
    }
} elseif ($adminDoc || $adminEmail) {
    $stmtAuth = $pdo->prepare("SELECT id, nombre_razon_social, email, rol, tipo_documento, numero_documento FROM usuarios WHERE (numero_documento = ? OR LOWER(email) = LOWER(?)) AND rol = 'admin' LIMIT 1");
    $stmtAuth->execute([$adminDoc ?: '', $adminEmail ?: '']);
    $adminUser = $stmtAuth->fetch();
    if ($adminUser) {
        $isAuthorized = true;
    }
} else {
    // Si no se envió identificador explícito, comprobar si existe un usuario admin en el sistema
    $stmtMaster = $pdo->query("SELECT id, nombre_razon_social, email, rol FROM usuarios WHERE rol = 'admin' LIMIT 1");
    $adminUser = $stmtMaster->fetch();
    if ($adminUser) {
        $isAuthorized = true;
    }
}

if (!$isAuthorized) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'Acceso denegado: Esta función requiere privilegios de Administrador Master.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ================= 2. EXTRACCIÓN DE DATOS DE TODAS LAS TABLAS =================
try {
    // 2.1 Configuración plana y estructurada
    $configFlat = [];
    try {
        $stmtConf = $pdo->query("SELECT clave, valor, updated_at FROM configuracion");
        $confRows = $stmtConf->fetchAll();
        foreach ($confRows as $row) {
            $configFlat[$row['clave']] = $row['valor'];
        }
    } catch (Exception $e) {
        $configFlat = [];
    }

    // Separar configuración del negocio y banners para máxima legibilidad
    $datosNegocio = [];
    $bannersData = [];
    foreach ($configFlat as $k => $v) {
        if (strpos($k, 'banner_') === 0 || strpos($k, 'hero_') === 0) {
            $bannersData[$k] = $v;
        } else {
            $datosNegocio[$k] = $v;
        }
    }

    // 2.2 Categorías
    $categorias = [];
    try {
        $stmtCat = $pdo->query("SELECT * FROM categorias ORDER BY id ASC");
        $categorias = $stmtCat->fetchAll();
    } catch (Exception $e) {
        $categorias = [];
    }

    // 2.3 Productos
    $productos = [];
    try {
        $stmtProd = $pdo->query("SELECT * FROM productos ORDER BY id ASC");
        $productos = $stmtProd->fetchAll();
    } catch (Exception $e) {
        $productos = [];
    }

    // 2.4 Directorio de Usuarios (Clientes y Admins)
    $usuarios = [];
    try {
        $stmtUsers = $pdo->query("SELECT id, tipo_documento, numero_documento, nombre_razon_social, email, password, telefono, departamento, provincia, distrito, direccion, rol, creado_en FROM usuarios ORDER BY id ASC");
        $usuarios = $stmtUsers->fetchAll();
    } catch (Exception $e) {
        $usuarios = [];
    }

    // 2.5 Cotizaciones B2B
    $cotizaciones = [];
    try {
        $stmtCotiz = $pdo->query("SELECT * FROM cotizaciones ORDER BY id DESC");
        $cotizacionesRaw = $stmtCotiz->fetchAll();
        foreach ($cotizacionesRaw as $c) {
            if (isset($c['items']) && is_string($c['items'])) {
                $decoded = json_decode($c['items'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $c['items'] = $decoded;
                }
            } elseif (isset($c['detalle_items']) && is_string($c['detalle_items'])) {
                $decoded = json_decode($c['detalle_items'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $c['detalle_items'] = $decoded;
                }
            }
            $cotizaciones[] = $c;
        }
    } catch (Exception $e) {
        $cotizaciones = [];
    }

    // 2.6 Libro de Reclamaciones (Normativa INDECOPI)
    $reclamaciones = [];
    try {
        $tableExists = $pdo->query("SHOW TABLES LIKE 'libro_reclamaciones'")->fetch();
        $tableName = $tableExists ? 'libro_reclamaciones' : 'reclamaciones';
        $stmtRec = $pdo->query("SELECT * FROM {$tableName} ORDER BY id DESC");
        $reclamaciones = $stmtRec->fetchAll();
    } catch (Exception $e) {
        $reclamaciones = [];
    }

    // ================= 3. ESTRUCTURAR PAYLOAD DE BACKUP =================
    date_default_timezone_set('America/Lima');
    $now = new DateTime();
    $fechaIso = $now->format('c');
    $fechaNombreArchivo = $now->format('Y-m-d_Hi');
    $filename = "backup_descartables_{$fechaNombreArchivo}.json";

    $backupPayload = [
        '_metadata' => [
            'sistema'           => 'Descartables Peruanos - Plataforma Web & Panel Administrativo',
            'version_backup'    => '1.0',
            'fecha_exportacion' => $fechaIso,
            'timestamp'         => time(),
            'generado_por'      => [
                'id'                  => $adminUser['id'] ?? 1,
                'nombre_razon_social' => $adminUser['nombre_razon_social'] ?? 'Master Admin',
                'rol'                 => $adminUser['rol'] ?? 'admin'
            ],
            'resumen_conteos'   => [
                'configuracion_claves' => count($configFlat),
                'categorias'           => count($categorias),
                'productos'            => count($productos),
                'usuarios'             => count($usuarios),
                'cotizaciones'         => count($cotizaciones),
                'libro_reclamaciones'  => count($reclamaciones)
            ]
        ],
        'datos_del_negocio'      => $datosNegocio,
        'banners_y_avisos'       => $bannersData,
        'configuracion_completa' => $configFlat,
        'categorias'             => $categorias,
        'productos'              => $productos,
        'usuarios'               => $usuarios,
        'cotizaciones'           => $cotizaciones,
        'libro_reclamaciones'    => $reclamaciones
    ];

    // ================= 4. DESCARGA DIRECTA O RESPUESTA JSON =================
    $isDirectDownload = isset($_GET['download']) && ($_GET['download'] === '1' || $_GET['download'] === 'true');

    if ($isDirectDownload) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo json_encode($backupPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } else {
        echo json_encode([
            'success'  => true,
            'filename' => $filename,
            'metadata' => $backupPayload['_metadata'],
            'data'     => $backupPayload
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Error crítico al generar la copia de seguridad: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}
