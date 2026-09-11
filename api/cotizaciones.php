<?php
/**
 * API REST: Cotizaciones Corporativas B2B
 * Plataforma Descartables Peruanos
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/concurrency.php';
require_once __DIR__ . '/validator.php';
require_once __DIR__ . '/ratelimit.php';

Vault::handleCors();

$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Parseo seguro de payload con control de tamaño y sintaxis JSON
$data = Validator::parseJsonInput(1048576);

// 1. ACTUALIZAR ESTADO / NOTAS (PUT o POST con action=update_status)
if ($method === 'PUT' || ($method === 'POST' && isset($data['action']) && $data['action'] === 'update_status')) {
    if (class_exists('Vault')) {
        Vault::requireAdmin();
    }

    if (!$pdo) {
        ApiResponse::error('Base de datos no disponible.', 'DB_UNAVAILABLE', 503);
    }

    $id = isset($data['id']) ? (int)$data['id'] : null;
    $codigo = isset($data['codigo']) ? trim((string)$data['codigo']) : null;
    $notas = isset($data['notas']) ? Validator::validateText((string)$data['notas'], 0, 1000, true) : null;

    $validEstados = ['Pendiente', 'En Contacto', 'Cotizado', 'Atendido', 'Despachado', 'Cancelado'];
    if (isset($data['estado'])) {
        $estado = Validator::validateAllowlist((string)$data['estado'], $validEstados);
        if (!$estado) {
            ApiResponse::error("Estado de cotización no válido. Los estados permitidos son: " . implode(', ', $validEstados), 'VALIDATION_ERROR', 400);
        }
    } else {
        $estado = 'Pendiente';
    }

    try {
        if ($id && $id > 0) {
            $stmt = $pdo->prepare("UPDATE cotizaciones SET estado = ?, notas = ? WHERE id = ?");
            $stmt->execute([$estado, $notas, $id]);
        } elseif ($codigo) {
            $stmt = $pdo->prepare("UPDATE cotizaciones SET estado = ?, notas = ? WHERE codigo_cotizacion = ?");
            $stmt->execute([$estado, $notas, $codigo]);
        } else {
            ApiResponse::error('Se requiere ID o Código de cotización.', 'VALIDATION_ERROR', 400);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Estado de cotización actualizado con éxito.',
            'estado'  => $estado,
            'notas'   => $notas
        ], JSON_UNESCAPED_UNICODE);
        exit();
    } catch (Throwable $e) {
        ApiResponse::error('Error al actualizar cotización.', 'UPDATE_ERROR', 500, $e);
    }
}

// 2. ELIMINAR COTIZACIÓN (DELETE o POST con action=delete, individual o por lote)
if ($method === 'DELETE' || ($method === 'POST' && isset($data['action']) && $data['action'] === 'delete')) {
    if (class_exists('Vault')) {
        Vault::requireAdmin();
    }

    if (!$pdo) {
        ApiResponse::error('Base de datos no disponible.', 'DB_UNAVAILABLE', 503);
    }

    $id = isset($data['id']) ? (int)$data['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : null);
    $ids = !empty($data['ids']) && is_array($data['ids']) ? array_filter(array_map('intval', $data['ids'])) : [];
    if ($id && !in_array($id, $ids, true)) {
        $ids[] = $id;
    }

    if (empty($ids)) {
        ApiResponse::error('No se especificaron cotizaciones a eliminar.', 'VALIDATION_ERROR', 400);
    }

    try {
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM cotizaciones WHERE id IN ({$inQuery})");
        $stmt->execute(array_values($ids));
        $deletedCount = $stmt->rowCount();

        echo json_encode([
            'success' => true,
            'message' => "Se eliminaron {$deletedCount} cotización(es) con éxito.",
            'deleted' => array_values($ids)
        ], JSON_UNESCAPED_UNICODE);
        exit();
    } catch (Throwable $e) {
        ApiResponse::error('Error al eliminar cotizaciones.', 'DELETE_ERROR', 500, $e);
    }
}

// 3. REGISTRAR NUEVA COTIZACIÓN (POST)
if ($method === 'POST') {
    // Asignación segura del usuario propietario derivada de la sesión
    $usuario_id = null;
    if (class_exists('Vault')) {
        $token = Vault::extractTokenFromRequest();
        $session = $token ? Vault::validateToken($token) : null;
        if ($session && !empty($session['uid'])) {
            $usuario_id = (int)$session['uid'];
        }
    }

    $documentoRaw = trim((string)($data['documento'] ?? $data['cliente_doc'] ?? ''));
    $nombreRaw = trim((string)($data['nombre_cliente'] ?? $data['cliente_nombre'] ?? ''));

    // Validar nombre del cliente (mínimo 2, máximo 150 caracteres)
    $nombre = Validator::validateText($nombreRaw ?: 'Cliente Web', 2, 150);
    if (!$nombre) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'El nombre del cliente debe tener entre 2 y 150 caracteres.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Validar documento si se proporciona
    if (!empty($documentoRaw) && $documentoRaw !== 'No especificado') {
        $docResult = Validator::validateDocument($documentoRaw);
        if (!$docResult) {
            ApiResponse::error('El documento ingresado no tiene un formato válido (DNI 8 dígitos, RUC 11 dígitos o CE alfanumérico).', 'VALIDATION_ERROR', 400);
        }
        $documento = $docResult['documento'];
    } else {
        $documento = 'No especificado';
    }

    // Validar correo si se proporciona
    $emailRaw = trim((string)($data['email'] ?? $data['cliente_email'] ?? ''));
    if (!empty($emailRaw)) {
        $email = Validator::validateEmail($emailRaw);
        if (!$email) {
            ApiResponse::error('El correo electrónico ingresado no tiene un formato válido.', 'VALIDATION_ERROR', 400);
        }
    } else {
        $email = 'ventas@descartablesperuanos.pe';
    }

    // Validar teléfono si se proporciona
    $telefonoRaw = trim((string)($data['telefono'] ?? $data['cliente_telefono'] ?? ''));
    if (!empty($telefonoRaw) && $telefonoRaw !== 'No especificado') {
        $telefono = Validator::validatePhone($telefonoRaw);
        if (!$telefono) {
            ApiResponse::error('El número telefónico no tiene un formato válido.', 'VALIDATION_ERROR', 400);
        }
    } else {
        $telefono = 'No especificado';
    }

    // Validar tipo de comprobante y destino
    $tipo_comprobante = Validator::validateAllowlist($data['tipo_comprobante'] ?? 'Factura', ['Boleta', 'Factura'], true);
    if ($tipo_comprobante === null) {
        ApiResponse::error('Tipo de comprobante no válido. Opciones permitidas: Boleta, Factura.', 'VALIDATION_ERROR', 400);
    }
    $destino = Validator::validateText($data['destino'] ?? $data['departamento'] ?? 'Lima Metropolitana', 2, 100) ?: 'Lima Metropolitana';
    $notas = isset($data['notas']) ? (Validator::validateText((string)$data['notas'], 0, 500, true) ?: '') : '';

    $items = !empty($data['items']) ? $data['items'] : (!empty($data['detalle_items']) ? $data['detalle_items'] : []);
    if (!is_array($items) || count($items) === 0) {
        ApiResponse::error('Debe incluir al menos un producto para registrar la cotización.', 'VALIDATION_ERROR', 400);
    }

    if (count($items) > 50) {
        ApiResponse::error('La cotización supera el límite máximo de 50 productos por solicitud.', 'VALIDATION_ERROR', 400);
    }

    if (!$pdo) {
        ApiResponse::error('Base de datos no disponible.', 'DB_UNAVAILABLE', 503);
    }

    // =========================================================================
    // VERIFICACIÓN DE IDEMPOTENCIA (ANTES DEL RATE LIMIT)
    // Permite que reintentos idénticos por timeout reciban 200 OK sin consumir quota
    // =========================================================================
    $idempotencyKey = ConcurrencyEngine::extractIdempotencyKey($data);
    $scope = 'cotizacion:create';
    $requestHash = ConcurrencyEngine::normalizePayloadForFingerprint($data);

    if ($idempotencyKey) {
        $checkPre = ConcurrencyEngine::checkIdempotency($pdo, $scope, $idempotencyKey, $requestHash, $usuario_id, $documento);
        if ($checkPre['status'] === 'conflict') {
            ApiResponse::error(
                $checkPre['error'],
                $checkPre['code'] ?? 'IDEMPOTENCY_CONFLICT',
                $checkPre['http_code'] ?? 409
            );
        }
        if ($checkPre['status'] === 'replay') {
            header('X-Idempotent-Replay: true');
            http_response_code(200);
            $replayPayload = $checkPre['response'];
            $replayPayload['idempotent_replay'] = true;
            echo json_encode($replayPayload, JSON_UNESCAPED_UNICODE);
            exit();
        }
    }

    // Control de abuso: Máximo 15 solicitudes de cotización nuevas por hora por IP / usuario
    RateLimiter::enforce($pdo, 'cotizacion:create', 15, 3600, 3600, $usuario_id);

    // =========================================================================
    // VALIDACIÓN DE PRODUCTOS Y RECÁLCULO OFICIAL DE PRECIOS EN SERVIDOR
    // El servidor es la única autoridad de precios, nombres y existencias
    // =========================================================================
    $validatedItems = [];
    $total_items = 0;
    $total_monto = 0.0;

    foreach ($items as $idx => $item) {
        if (!is_array($item)) {
            ApiResponse::error("El item en posición #{$idx} no tiene un formato válido.", 'VALIDATION_ERROR', 400);
        }

        $cantidad = Validator::validatePositiveInt($item['cantidad'] ?? 1, 1, 100000);
        if ($cantidad === null) {
            ApiResponse::error("La cantidad para el producto #{$idx} debe ser un número entero positivo mayor a 0 (máx 100,000).", 'VALIDATION_ERROR', 400);
        }

        $prodId = isset($item['id']) ? (int)$item['id'] : null;
        $prodSku = isset($item['sku']) ? strtoupper(trim((string)$item['sku'])) : null;

        $dbProduct = null;
        if ($prodId && $prodId > 0) {
            $stmtP = $pdo->prepare("SELECT * FROM productos WHERE id = ? LIMIT 1");
            $stmtP->execute([$prodId]);
            $dbProduct = $stmtP->fetch(PDO::FETCH_ASSOC);
        } elseif ($prodSku !== null && $prodSku !== '') {
            $stmtP = $pdo->prepare("SELECT * FROM productos WHERE UPPER(sku) = ? LIMIT 1");
            $stmtP->execute([$prodSku]);
            $dbProduct = $stmtP->fetch(PDO::FETCH_ASSOC);
        }

        if ($dbProduct) {
            if ($dbProduct['stock_estado'] === 'agotado') {
                ApiResponse::error("El producto '{$dbProduct['nombre']}' (SKU {$dbProduct['sku']}) está agotado y no puede ser cotizado.", 'PRODUCT_OUT_OF_STOCK', 400);
            }

            // Recalcular precios desde BD oficial (ignorar datos manipulados por el cliente)
            $precioOficial = $dbProduct['precio'] !== null ? (float)$dbProduct['precio'] : 0.0;
            $subtotalOficial = round($precioOficial * $cantidad, 2);

            $validatedItems[] = [
                'id'           => (int)$dbProduct['id'],
                'sku'          => $dbProduct['sku'],
                'nombre'       => $dbProduct['nombre'],
                'presentacion' => $dbProduct['presentacion'],
                'material'     => $dbProduct['material'],
                'categoria_id' => (int)$dbProduct['categoria_id'],
                'precio'       => $precioOficial,
                'cantidad'     => $cantidad,
                'subtotal'     => $subtotalOficial
            ];
            $total_items += $cantidad;
            $total_monto += $subtotalOficial;
        } else {
            // Producto no encontrado en catálogo oficial
            $itemRef = $prodSku ?: ($prodId ? "ID {$prodId}" : "#{$idx}");
            ApiResponse::error("El producto solicitado ({$itemRef}) no existe en el catálogo activo.", 'PRODUCT_NOT_FOUND', 400);
        }
    }

    $pdo->beginTransaction();

    try {
        // Verificación de idempotencia bajo bloqueo transaccional
        if ($idempotencyKey) {
            $checkLocked = ConcurrencyEngine::checkIdempotency($pdo, $scope, $idempotencyKey, $requestHash, $usuario_id, $documento);
            if ($checkLocked['status'] === 'conflict') {
                $pdo->rollBack();
                ApiResponse::error(
                    $checkLocked['error'],
                    $checkLocked['code'] ?? 'IDEMPOTENCY_CONFLICT',
                    $checkLocked['http_code'] ?? 409
                );
            }
            if ($checkLocked['status'] === 'replay') {
                $pdo->rollBack();
                header('X-Idempotent-Replay: true');
                http_response_code(200);
                $replayPayload = $checkLocked['response'];
                $replayPayload['idempotent_replay'] = true;
                echo json_encode($replayPayload, JSON_UNESCAPED_UNICODE);
                exit();
            }
        }

        $anio = (int)date('Y');
        // Generación de correlativo atómico sin huecos ni colisiones
        $codigo = ConcurrencyEngine::nextCorrelative($pdo, 'COTIZACION', $anio);
        $estado = 'Pendiente';
        $itemsJson = json_encode($validatedItems, JSON_UNESCAPED_UNICODE);

        $sql = "INSERT INTO cotizaciones (
            codigo_cotizacion, usuario_id, tipo_comprobante, documento, 
            nombre_cliente, telefono, email, destino, items, total_items, 
            notas, estado, enviado_whatsapp, creado_en
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $codigo,
            $usuario_id,
            $tipo_comprobante,
            $documento,
            $nombre,
            $telefono,
            $email,
            $destino,
            $itemsJson,
            $total_items,
            $notas,
            $estado
        ]);

        $cotizacionId = (int)$pdo->lastInsertId();

        $responsePayload = [
            'success'            => true,
            'message'            => 'Cotización formal registrada exitosamente.',
            'id'                 => $cotizacionId,
            'codigo_cotizacion'  => $codigo,
            'fecha'              => date('d/m/Y H:i:s'),
            'total_items'        => $total_items,
            'total_monto'        => $total_monto,
            'items'              => $validatedItems
        ];

        // Guardar respuesta cacheada en tabla de idempotencia
        ConcurrencyEngine::saveIdempotency(
            $pdo,
            $scope,
            $idempotencyKey,
            $requestHash,
            $codigo,
            $responsePayload,
            $usuario_id,
            $documento
        );

        $pdo->commit();

        echo json_encode($responsePayload, JSON_UNESCAPED_UNICODE);
        exit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        ApiResponse::error('Error al registrar cotización.', 'REGISTER_ERROR', 500, $e);
    }
}

// 4. CONSULTAR COTIZACIONES (GET)
if ($method === 'GET') {
    $codigo = isset($_GET['codigo']) ? trim($_GET['codigo']) : null;
    $docParam = isset($_GET['documento']) ? trim($_GET['documento']) : null;
    $emailParam = isset($_GET['email']) ? trim($_GET['email']) : null;

    if (!$pdo) {
        ApiResponse::error('Base de datos no disponible.', 'DB_UNAVAILABLE', 503);
    }

    try {
        $token = class_exists('Vault') ? Vault::extractTokenFromRequest() : null;
        $session = ($token && class_exists('Vault')) ? Vault::validateToken($token) : null;

        $isAdmin = ($session && isset($session['rol']) && $session['rol'] === 'admin');
        $isClient = ($session && isset($session['rol']) && $session['rol'] === 'cliente');

        if ($isAdmin) {
            $sql = "SELECT c.*, u.nombre_razon_social as usuario_nombre, u.email as usuario_email 
                    FROM cotizaciones c 
                    LEFT JOIN usuarios u ON c.usuario_id = u.id 
                    WHERE 1=1";
            $params = [];

            if ($codigo) {
                $sql .= " AND c.codigo_cotizacion = ?";
                $params[] = $codigo;
            }
            if ($docParam) {
                $sql .= " AND c.documento = ?";
                $params[] = $docParam;
            }

            $sql .= " ORDER BY c.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $quotes = $stmt->fetchAll();

            foreach ($quotes as &$q) {
                $q['id'] = (int)$q['id'];
                $q['items'] = json_decode($q['items'] ?? '[]', true) ?: [];
            }

            echo json_encode(['success' => true, 'count' => count($quotes), 'data' => $quotes], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if ($isClient) {
            $authId = (int)$session['uid'];
            $sql = "SELECT c.*, u.nombre_razon_social as usuario_nombre 
                    FROM cotizaciones c 
                    JOIN usuarios u ON c.usuario_id = u.id 
                    WHERE c.usuario_id = ?";
            $params = [$authId];

            if ($codigo) {
                $sql .= " AND c.codigo_cotizacion = ?";
                $params[] = $codigo;
            }

            $sql .= " ORDER BY c.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $quotes = $stmt->fetchAll();

            foreach ($quotes as &$q) {
                $q['id'] = (int)$q['id'];
                $q['items'] = json_decode($q['items'] ?? '[]', true) ?: [];
            }

            echo json_encode(['success' => true, 'count' => count($quotes), 'data' => $quotes], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Invitado no autenticado
        if ($codigo && ($docParam || $emailParam)) {
            $sql = "SELECT id, codigo_cotizacion, tipo_comprobante, documento, nombre_cliente, telefono, email, destino, items, total_items, estado, creado_en 
                    FROM cotizaciones 
                    WHERE codigo_cotizacion = ?";
            $params = [$codigo];

            if ($docParam) {
                $sql .= " AND documento = ?";
                $params[] = $docParam;
            } else {
                $sql .= " AND LOWER(email) = LOWER(?)";
                $params[] = $emailParam;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $quote = $stmt->fetch();

            if ($quote) {
                $quote['id'] = (int)$quote['id'];
                $quote['items'] = json_decode($quote['items'] ?? '[]', true) ?: [];
                echo json_encode(['success' => true, 'data' => [$quote]], JSON_UNESCAPED_UNICODE);
                exit();
            }

            ApiResponse::error('No se encontró la cotización con los datos de verificación proporcionados.', 'NOT_FOUND', 404);
        }

        ApiResponse::error('No autorizado: Inicie sesión o proporcione código de cotización y documento/correo de validación.', 'UNAUTHORIZED', 401);
    } catch (Throwable $e) {
        ApiResponse::error('Error al consultar cotizaciones.', 'FETCH_ERROR', 500, $e);
    }
}

ApiResponse::error('Método no permitido.', 'METHOD_NOT_ALLOWED', 405);
