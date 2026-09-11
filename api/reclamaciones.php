<?php
/**
 * API REST: Libro de Reclamaciones Virtual
 * Normativa INDECOPI (D.S. 011-2011-PCM / Ley N° 31435)
 * Plataforma Descartables Peruanos
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vault.php';
require_once __DIR__ . '/concurrency.php';
require_once __DIR__ . '/validator.php';
require_once __DIR__ . '/ratelimit.php';
require_once __DIR__ . '/response.php';

Vault::handleCors();

$method = $_SERVER['REQUEST_METHOD'];

// =========================================================================
// 1. REGISTRAR HOJA DE RECLAMACIÓN (POST)
// =========================================================================
if ($method === 'POST') {
    $data = Validator::parseJsonInput(1048576);

    // 1. Validar Tipo y Número de Documento de Identidad
    $tipoDoc = Validator::validateAllowlist($data['tipo_documento'] ?? 'DNI', ['DNI', 'RUC', 'CE', 'Pasaporte'], true);
    if ($tipoDoc === null) {
        ApiResponse::error('Tipo de documento no válido. Tipos aceptados: DNI, RUC, CE, Pasaporte.', 'VALIDATION_ERROR', 400);
        return;
    }

    $docResult = Validator::validateDocument((string)($data['numero_documento'] ?? ''), $tipoDoc);
    if (!$docResult) {
        ApiResponse::error('Número de documento no válido para el tipo seleccionado (DNI requiere 8 dígitos numéricos y RUC 11 dígitos).', 'VALIDATION_ERROR', 400);
        return;
    }
    $numDoc = $docResult['documento'];

    // 2. Validar Nombre Completo
    $nombre = Validator::validateText($data['nombre_completo'] ?? '', 2, 150);
    if (!$nombre) {
        ApiResponse::error('El nombre completo debe tener entre 2 y 150 caracteres.', 'VALIDATION_ERROR', 400);
        return;
    }

    // 3. Validar Teléfono
    $telefono = Validator::validatePhone((string)($data['telefono'] ?? ''));
    if (!$telefono) {
        ApiResponse::error('El número de teléfono ingresado no tiene un formato válido (entre 7 y 15 dígitos).', 'VALIDATION_ERROR', 400);
        return;
    }

    // 4. Validar Correo Electrónico
    $email = Validator::validateEmail((string)($data['email'] ?? ''));
    if (!$email) {
        ApiResponse::error('El correo electrónico ingresado no tiene un formato válido.', 'VALIDATION_ERROR', 400);
        return;
    }

    // 5. Validar Datos Geográficos y Dirección
    $departamento = Validator::validateText($data['departamento'] ?? 'Lima', 2, 100) ?: 'Lima';
    $provincia = Validator::validateText($data['provincia'] ?? 'Lima', 2, 100) ?: 'Lima';
    $distrito = Validator::validateText($data['distrito'] ?? '', 2, 100);
    $direccion = Validator::validateText($data['direccion'] ?? '', 5, 255);

    if (!$distrito || !$direccion) {
        ApiResponse::error('El distrito y la dirección domiciliaria (mínimo 5 caracteres) son obligatorios.', 'VALIDATION_ERROR', 400);
        return;
    }

    // 6. Validar Menor de Edad y Tutor
    $es_menor = !empty($data['es_menor']) ? 1 : 0;
    $nombre_tutor = null;
    if ($es_menor) {
        $nombre_tutor = Validator::validateText((string)($data['nombre_tutor'] ?? ''), 2, 150);
        if (!$nombre_tutor) {
            ApiResponse::error('Al indicar que es menor de edad, el nombre del padre, madre o tutor es obligatorio (2-150 caracteres).', 'VALIDATION_ERROR', 400);
            return;
        }
    }

    // 7. Validar Datos del Bien Reclamado
    $tipo_bien = Validator::validateAllowlist($data['tipo_bien'] ?? 'Producto', ['Producto', 'Servicio'], true);
    $monto = Validator::validatePrice($data['monto_reclamado'] ?? 0.0, 0.0, 1000000.0);
    $descripcion_bien = Validator::validateText($data['descripcion_bien'] ?? '', 3, 500, true);

    if (!$tipo_bien || $monto === null || !$descripcion_bien) {
        ApiResponse::error('Datos del bien contratado no válidos (tipo: Producto/Servicio, descripción 3-500 caracteres, monto positivo válido).', 'VALIDATION_ERROR', 400);
        return;
    }

    // 8. Validar Reclamación y Pedido
    $tipo_reclamacion = Validator::validateAllowlist($data['tipo_reclamacion'] ?? 'Reclamo', ['Reclamo', 'Queja'], true);
    $detalle_reclamacion = Validator::validateText($data['detalle_reclamacion'] ?? '', 10, 3000, true);
    $pedido_consumidor = Validator::validateText($data['pedido_consumidor'] ?? '', 5, 1000, true);

    if (!$tipo_reclamacion || !$detalle_reclamacion || !$pedido_consumidor) {
        ApiResponse::error('El detalle de la reclamación debe tener entre 10 y 3,000 caracteres, y el pedido del consumidor entre 5 y 1,000 caracteres.', 'VALIDATION_ERROR', 400);
        return;
    }

    $pdo = getDbConnection();
    if (!$pdo) {
        ApiResponse::error('Base de datos no disponible para registro oficial.', 'DB_UNAVAILABLE', 503);
        return;
    }

    // Identificar sesión de usuario autenticado si existe
    $usuario_id = null;
    if (class_exists('Vault')) {
        $token = Vault::extractTokenFromRequest();
        $session = $token ? Vault::validateToken($token) : null;
        if ($session && !empty($session['uid'])) {
            $usuario_id = (int)$session['uid'];
        }
    }

    // =========================================================================
    // VERIFICACIÓN DE IDEMPOTENCIA (ANTES DEL RATE LIMIT)
    // =========================================================================
    $idempotencyKey = ConcurrencyEngine::extractIdempotencyKey($data);
    $scope = 'reclamacion:create';
    $requestHash = ConcurrencyEngine::normalizePayloadForFingerprint($data);

    if ($idempotencyKey) {
        $checkPre = ConcurrencyEngine::checkIdempotency($pdo, $scope, $idempotencyKey, $requestHash, $usuario_id, $numDoc);
        if ($checkPre['status'] === 'conflict') {
            ApiResponse::error(
                $checkPre['error'],
                $checkPre['code'] ?? 'IDEMPOTENCY_CONFLICT',
                $checkPre['http_code'] ?? 409
            );
            return;
        }
        if ($checkPre['status'] === 'replay') {
            header('X-Idempotent-Replay: true');
            $replayPayload = $checkPre['response'];
            $replayPayload['idempotent_replay'] = true;
            ApiResponse::success($replayPayload, $replayPayload);
            return;
        }
    }

    // Control de abuso: Máximo 10 reclamaciones nuevas por hora por IP / usuario
    RateLimiter::enforce($pdo, 'reclamacion:create', 10, 3600, 3600, $usuario_id);

    $pdo->beginTransaction();

    try {
        // Verificación de idempotencia bajo bloqueo transaccional
        if ($idempotencyKey) {
            $checkLocked = ConcurrencyEngine::checkIdempotency($pdo, $scope, $idempotencyKey, $requestHash, $usuario_id, $numDoc);
            if ($checkLocked['status'] === 'conflict') {
                $pdo->rollBack();
                ApiResponse::error(
                    $checkLocked['error'],
                    $checkLocked['code'] ?? 'IDEMPOTENCY_CONFLICT',
                    $checkLocked['http_code'] ?? 409
                );
                return;
            }
            if ($checkLocked['status'] === 'replay') {
                $pdo->rollBack();
                header('X-Idempotent-Replay: true');
                $replayPayload = $checkLocked['response'];
                $replayPayload['idempotent_replay'] = true;
                ApiResponse::success($replayPayload, $replayPayload);
                return;
            }
        }

        // Generar código correlativo de hoja de reclamación atómico (Ej: REC-2026-00001)
        $anio = (int)date('Y');
        $codigo_hoja = ConcurrencyEngine::nextCorrelative($pdo, 'RECLAMACION', $anio);

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowSql = $driver === 'sqlite' ? "datetime('now')" : "NOW()";

        $sql = "INSERT INTO libro_reclamaciones (
            codigo_hoja, tipo_documento, numero_documento, nombre_completo, 
            telefono, email, departamento, provincia, distrito, direccion, 
            es_menor, nombre_tutor, tipo_bien, monto_reclamado, descripcion_bien, 
            tipo_reclamacion, detalle_reclamacion, pedido_consumidor, estado, creado_en
        ) VALUES (
            ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, 
            ?, ?, ?, 'Pendiente', $nowSql
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $codigo_hoja,
            $tipoDoc,
            $numDoc,
            $nombre,
            $telefono,
            $email,
            $departamento,
            $provincia,
            $distrito,
            $direccion,
            $es_menor,
            $nombre_tutor,
            $tipo_bien,
            $monto,
            $descripcion_bien,
            $tipo_reclamacion,
            $detalle_reclamacion,
            $pedido_consumidor
        ]);

        $reclamacion_id = (int)$pdo->lastInsertId();

        $responsePayload = [
            'success'      => true,
            'message'      => 'Su Hoja de Reclamación ha sido registrada exitosamente conforme a la normativa INDECOPI.',
            'codigo_hoja'  => $codigo_hoja,
            'id'           => $reclamacion_id,
            'fecha'        => date('d/m/Y H:i:s'),
            'empresa'      => [
                'razon_social' => defined('EMPRESA_RAZON_SOCIAL') ? EMPRESA_RAZON_SOCIAL : 'DESCARTABLES PERUANOS S.A.C.',
                'ruc'          => defined('EMPRESA_RUC') ? EMPRESA_RUC : '20601234567',
                'direccion'    => defined('EMPRESA_DIRECCION') ? EMPRESA_DIRECCION : 'Av. Alejandro Bertello 732-C, Cercado de Lima',
                'telefono'     => defined('EMPRESA_TELEFONO') ? EMPRESA_TELEFONO : '(01) 000-0000',
                'email'        => defined('EMPRESA_EMAIL') ? EMPRESA_EMAIL : 'ventas@descartablesperuanos.pe'
            ],
            'plazo_legal'  => '15 días hábiles conforme a la Ley N° 31435 que modifica el Código de Protección y Defensa del Consumidor.'
        ];

        // Guardar respuesta de idempotencia en la misma transacción
        ConcurrencyEngine::saveIdempotency(
            $pdo,
            $scope,
            $idempotencyKey,
            $requestHash,
            $codigo_hoja,
            $responsePayload,
            $usuario_id,
            $numDoc
        );

        $pdo->commit();

        ApiResponse::success($responsePayload, $responsePayload);
        return;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        ApiResponse::error('Error interno al registrar reclamación.', 'RECLAMACION_CREATE_FAILED', 500, $e);
        return;
    }
}

// =========================================================================
// 2. CONSULTAR HOJAS DE RECLAMACIÓN (GET)
// =========================================================================
if ($method === 'GET') {
    $codigo = isset($_GET['codigo']) ? trim((string)$_GET['codigo']) : (isset($_GET['codigo_hoja']) ? trim((string)$_GET['codigo_hoja']) : null);
    $docParam = isset($_GET['documento']) ? trim((string)$_GET['documento']) : (isset($_GET['numero_documento']) ? trim((string)$_GET['numero_documento']) : null);
    $emailParam = isset($_GET['email']) ? trim((string)$_GET['email']) : null;

    $pdo = getDbConnection();
    if (!$pdo) {
        ApiResponse::error('Base de datos no disponible.', 'DB_UNAVAILABLE', 503);
        return;
    }

    try {
        // Verificar sesión y rol
        $token = class_exists('Vault') ? Vault::extractTokenFromRequest() : null;
        $session = ($token && class_exists('Vault')) ? Vault::validateToken($token) : null;

        $isAdmin = ($session && isset($session['rol']) && $session['rol'] === 'admin');
        $isClient = ($session && isset($session['rol']) && $session['rol'] === 'cliente');

        // CASO A: Administrador autorizado (Listado global, búsqueda y estadísticas)
        if ($isAdmin) {
            $sql = "SELECT * FROM libro_reclamaciones WHERE 1=1";
            $params = [];

            if ($codigo) {
                $sql .= " AND codigo_hoja = ?";
                $params[] = $codigo;
            }
            if ($docParam) {
                $sql .= " AND numero_documento = ?";
                $params[] = $docParam;
            }

            $sql .= " ORDER BY id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $resultados = $stmt->fetchAll();

            $stmtStats = $pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN estado = 'Atendido' THEN 1 ELSE 0 END) as atendidos,
                    SUM(CASE WHEN tipo_reclamacion = 'Reclamo' THEN 1 ELSE 0 END) as reclamos,
                    SUM(CASE WHEN tipo_reclamacion = 'Queja' THEN 1 ELSE 0 END) as quejas
                FROM libro_reclamaciones
            ");
            $stats = $stmtStats->fetch() ?: ['total' => 0, 'pendientes' => 0, 'atendidos' => 0, 'reclamos' => 0, 'quejas' => 0];

            ApiResponse::success($resultados, [
                'count' => count($resultados),
                'stats' => $stats
            ]);
            return;
        }

        // CASO B: Cliente autenticado consultando su historial
        if ($isClient) {
            $authDoc = $session['doc'] ?? null;
            $authEmail = $session['email'] ?? null;

            $sql = "SELECT id, codigo_hoja, tipo_documento, numero_documento, nombre_completo, tipo_bien, monto_reclamado, descripcion_bien, tipo_reclamacion, detalle_reclamacion, pedido_consumidor, estado, respuesta_proveedor, fecha_respuesta, creado_en 
                    FROM libro_reclamaciones 
                    WHERE (numero_documento = ? OR LOWER(email) = LOWER(?))";
            $params = [$authDoc, $authEmail];

            if ($codigo) {
                $sql .= " AND codigo_hoja = ?";
                $params[] = $codigo;
            }

            $sql .= " ORDER BY id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $resultados = $stmt->fetchAll();

            ApiResponse::success($resultados, [
                'count' => count($resultados)
            ]);
            return;
        }

        // CASO C: Consulta de Invitado con Doble Factor (Código + Documento o Correo)
        if ($codigo) {
            if (!$docParam && !$emailParam) {
                ApiResponse::error('No autorizado: Se requiere el número de documento o correo electrónico de verificación junto al código de reclamación.', 'VERIFICATION_REQUIRED', 401);
                return;
            }

            $sql = "SELECT id, codigo_hoja, tipo_documento, numero_documento, nombre_completo, 
                           telefono, email, departamento, provincia, distrito, direccion,
                           tipo_bien, monto_reclamado, descripcion_bien, tipo_reclamacion, 
                           detalle_reclamacion, pedido_consumidor, estado, respuesta_proveedor, 
                           fecha_respuesta, creado_en 
                    FROM libro_reclamaciones 
                    WHERE codigo_hoja = ?";
            $params = [$codigo];

            if ($docParam) {
                $sql .= " AND numero_documento = ?";
                $params[] = $docParam;
            } else {
                $sql .= " AND LOWER(email) = LOWER(?)";
                $params[] = $emailParam;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $reclamacion = $stmt->fetch();

            if ($reclamacion) {
                ApiResponse::success([$reclamacion], [
                    'count' => 1
                ]);
                return;
            }

            ApiResponse::error('No se encontró ninguna hoja de reclamación con los datos de verificación proporcionados.', 'NOT_FOUND', 404);
            return;
        }

        // Si se consulta sin código pero con documento/email en público (ej: login o perfil)
        if ($docParam || $emailParam) {
            // Requiere autenticación activa para listar por documento sin código específico
            ApiResponse::error('No autorizado: Se requiere una sesión activa para consultar hojas de reclamación por documento.', 'AUTH_REQUIRED', 401);
            return;
        }

        ApiResponse::error('No autorizado: Se requiere iniciar sesión o proporcionar el código de hoja y número de documento/correo de verificación.', 'AUTH_REQUIRED', 401);
        return;

    } catch (Throwable $e) {
        ApiResponse::error('Error al consultar reclamaciones.', 'FETCH_ERROR', 500, $e);
        return;
    }
}

// =========================================================================
// 3. ACTUALIZAR ESTADO Y RESPUESTA (PUT - SOLO ADMINISTRADOR)
// =========================================================================
if ($method === 'PUT') {
    if (class_exists('Vault')) {
        $admin = Vault::requireAdmin();
        if (!$admin) {
            return;
        }
    }

    $data = Validator::parseJsonInput(1048576);

    if (!$data || (empty($data['id']) && empty($data['codigo_hoja']))) {
        ApiResponse::error('Se requiere el ID o código de la hoja de reclamación.', 'VALIDATION_ERROR', 400);
        return;
    }

    $pdo = getDbConnection();
    if (!$pdo) {
        ApiResponse::error('Base de datos no disponible.', 'DB_UNAVAILABLE', 503);
        return;
    }

    $id = !empty($data['id']) ? (int)$data['id'] : null;
    $codigo = !empty($data['codigo_hoja']) ? trim((string)$data['codigo_hoja']) : null;

    $validEstados = ['Pendiente', 'En Proceso', 'Atendido', 'Cerrado'];
    if (isset($data['estado'])) {
        $estado = Validator::validateAllowlist((string)$data['estado'], $validEstados, 'Estado de Reclamación');
        if (!$estado) {
            ApiResponse::error('Estado de reclamación no válido. Permitidos: ' . implode(', ', $validEstados), 'VALIDATION_ERROR', 400);
            return;
        }
    } else {
        $estado = 'Atendido';
    }

    $respuesta = isset($data['respuesta_proveedor']) ? (Validator::validateText((string)$data['respuesta_proveedor'], 0, 3000, true) ?: '') : '';

    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowSql = $driver === 'sqlite' ? "datetime('now')" : "NOW()";

        if ($id) {
            $stmt = $pdo->prepare("UPDATE libro_reclamaciones SET estado = ?, respuesta_proveedor = ?, fecha_respuesta = $nowSql WHERE id = ?");
            $stmt->execute([$estado, $respuesta, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE libro_reclamaciones SET estado = ?, respuesta_proveedor = ?, fecha_respuesta = $nowSql WHERE codigo_hoja = ?");
            $stmt->execute([$estado, $respuesta, $codigo]);
        }

        $updatedData = [
            'id'                  => $id,
            'codigo_hoja'         => $codigo,
            'estado'              => $estado,
            'respuesta_proveedor' => $respuesta,
            'fecha_respuesta'     => date('Y-m-d H:i:s')
        ];

        ApiResponse::success($updatedData, [
            'message' => 'Hoja de reclamación actualizada exitosamente conforme a INDECOPI.'
        ]);
        return;

    } catch (Throwable $e) {
        ApiResponse::error('Error al actualizar la hoja de reclamación.', 'UPDATE_ERROR', 500, $e);
        return;
    }
}

ApiResponse::error('Método no permitido.', 'METHOD_NOT_ALLOWED', 405);
