<?php
/**
 * API REST: Autenticación de Usuarios (DNI / RUC / Clientes)
 * Endpoints: login, register, update_profile, change_password, refresh_token
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validator.php';
require_once __DIR__ . '/ratelimit.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if ($method === 'POST') {
    $data = Validator::parseJsonInput(1048576);

    if (empty($data)) {
        ApiResponse::error('Datos de solicitud inválidos o vacíos.', 'INVALID_INPUT', 400);
    }

    $pdo = getDbConnection();
    if (!$pdo) {
        ApiResponse::error('Base de datos no disponible.', 'DB_UNAVAILABLE', 503);
    }

    // 1. INICIAR SESIÓN (LOGIN)
    if ($action === 'login') {
        // Control de abuso: Máximo 5 intentos fallidos/solicitudes por cada 15 minutos por IP
        RateLimiter::enforce($pdo, 'auth:login', 5, 900, 900);

        $identificador = trim((string)($data['identificador'] ?? ''));
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';

        if (empty($identificador) || $password === '') {
            ApiResponse::error('Debe ingresar su identificador (DNI/RUC/Correo) y su contraseña.', 'VALIDATION_ERROR', 400);
        }

        try {
            // Búsqueda normalizada por email o documento sin revelar cuál coincide
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE (LOWER(email) = LOWER(?) OR numero_documento = ?) LIMIT 1");
            $stmt->execute([$identificador, $identificador]);
            $user = $stmt->fetch();

            if ($user) {
                // Verificar contraseña preservando espacios exactos sin trim destructivo
                $valid = password_verify($password, $user['password']);
                if ($valid) {
                    $token = class_exists('Vault') ? Vault::generateToken($user) : null;
                    unset($user['password']);
                    $user['token'] = $token;
                    echo json_encode([
                        'success' => true,
                        'message' => 'Inicio de sesión exitoso.',
                        'token'   => $token,
                        'user'    => $user
                    ], JSON_UNESCAPED_UNICODE);
                    exit();
                }
            }

            ApiResponse::error('Credenciales inválidas. Compruebe sus datos o regístrese.', 'AUTH_FAILED', 401);
        } catch (Throwable $e) {
            ApiResponse::error('Error interno durante el inicio de sesión.', 'AUTH_INTERNAL_ERROR', 500, $e);
        }
    }

    // 2. REGISTRO DE USUARIOS (REGISTER)
    if ($action === 'register') {
        // Control de abuso: Máximo 5 registros por hora por IP
        RateLimiter::enforce($pdo, 'auth:register', 5, 3600, 3600);

        $tipo_documento = trim((string)($data['tipo_documento'] ?? 'DNI'));
        $numero_documento_raw = (string)($data['numero_documento'] ?? '');
        $docResult = Validator::validateDocument($numero_documento_raw, $tipo_documento);

        if (!$docResult) {
            ApiResponse::error('Número de documento no válido. El DNI requiere 8 dígitos numéricos y el RUC 11 dígitos.', 'VALIDATION_ERROR', 400);
        }

        $numero_documento = $docResult['documento'];
        $tipo_documento = $docResult['tipo'];

        $nombre_razon_social = Validator::validateText($data['nombre_razon_social'] ?? '', 2, 150);
        if (!$nombre_razon_social) {
            ApiResponse::error('El Nombre o Razón Social debe tener entre 2 y 150 caracteres.', 'VALIDATION_ERROR', 400);
        }

        $email = Validator::validateEmail($data['email'] ?? '');
        if (!$email) {
            ApiResponse::error('El correo electrónico ingresado no tiene un formato válido.', 'VALIDATION_ERROR', 400);
        }

        $passwordRaw = is_string($data['password'] ?? null) ? $data['password'] : '';
        $password = Validator::validatePassword($passwordRaw, 8, 128);
        if (!$password) {
            ApiResponse::error('La contraseña debe tener entre 8 y 128 caracteres y no puede consistir únicamente en espacios.', 'VALIDATION_ERROR', 400);
        }

        $telefonoRaw = trim((string)($data['telefono'] ?? ''));
        $telefono = !empty($telefonoRaw) ? Validator::validatePhone($telefonoRaw) : null;
        if (!empty($telefonoRaw) && $telefono === null) {
            ApiResponse::error('El número de teléfono no tiene un formato válido (entre 7 y 15 dígitos).', 'VALIDATION_ERROR', 400);
        }

        $departamento = Validator::validateText($data['departamento'] ?? 'Lima', 2, 100) ?: 'Lima';
        $provincia = Validator::validateText($data['provincia'] ?? 'Lima', 2, 100) ?: 'Lima';
        $distrito = Validator::validateText($data['distrito'] ?? '', 0, 100) ?: '';
        $direccion = Validator::validateText($data['direccion'] ?? '', 0, 255) ?: '';

        try {
            // Verificar existencia previa
            $checkStmt = $pdo->prepare("SELECT id FROM usuarios WHERE numero_documento = ? OR LOWER(email) = LOWER(?) LIMIT 1");
            $checkStmt->execute([$numero_documento, $email]);
            if ($checkStmt->fetch()) {
                ApiResponse::error('El número de documento o correo electrónico ya se encuentra registrado.', 'USER_ALREADY_EXISTS', 409);
            }

            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $insertSql = "INSERT INTO usuarios (tipo_documento, numero_documento, nombre_razon_social, email, password, telefono, departamento, provincia, distrito, direccion, rol) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'cliente')";
            $stmt = $pdo->prepare($insertSql);
            $stmt->execute([
                $tipo_documento,
                $numero_documento,
                $nombre_razon_social,
                $email,
                $passwordHash,
                $telefono,
                $departamento,
                $provincia,
                $distrito,
                $direccion
            ]);

            $newId = (int)$pdo->lastInsertId();
            $userStmt = $pdo->prepare("SELECT id, tipo_documento, numero_documento, nombre_razon_social, email, telefono, departamento, provincia, distrito, direccion, rol FROM usuarios WHERE id = ?");
            $userStmt->execute([$newId]);
            $createdUser = $userStmt->fetch();

            $createdUser['password'] = $passwordHash;
            $token = class_exists('Vault') ? Vault::generateToken($createdUser) : null;
            unset($createdUser['password']);
            $createdUser['token'] = $token;

            echo json_encode([
                'success' => true,
                'message' => 'Usuario registrado exitosamente.',
                'token'   => $token,
                'user'    => $createdUser
            ], JSON_UNESCAPED_UNICODE);
            exit();
        } catch (Throwable $e) {
            ApiResponse::error('Error interno al registrar el usuario.', 'REGISTER_INTERNAL_ERROR', 500, $e);
        }
    }

    // 3. ACTUALIZAR PERFIL (UPDATE_PROFILE) - Requiere sesión autenticada
    if ($action === 'update_profile') {
        if (!class_exists('Vault')) {
            require_once __DIR__ . '/vault.php';
        }
        
        $authUser = Vault::requireAuth($pdo);
        $authId = (int)$authUser['id'];

        // Control de abuso por usuario autenticado
        RateLimiter::enforce($pdo, 'auth:update_profile', 30, 3600, 3600, $authId);

        $nombre_razon_social = Validator::validateText($data['nombre_razon_social'] ?? '', 2, 150);
        if (!$nombre_razon_social) {
            ApiResponse::error('El nombre o razón social debe tener entre 2 y 150 caracteres.', 'VALIDATION_ERROR', 400);
        }

        $telefonoRaw = trim((string)($data['telefono'] ?? ($authUser['telefono'] ?? '')));
        $telefono = !empty($telefonoRaw) ? Validator::validatePhone($telefonoRaw) : null;
        if (!empty($telefonoRaw) && $telefono === null) {
            ApiResponse::error('El número de teléfono ingresado no tiene un formato válido.', 'VALIDATION_ERROR', 400);
        }

        $departamento = Validator::validateText($data['departamento'] ?? 'Lima', 2, 100) ?: 'Lima';
        $provincia = Validator::validateText($data['provincia'] ?? 'Lima', 2, 100) ?: 'Lima';
        $distrito = Validator::validateText($data['distrito'] ?? '', 0, 100) ?: '';
        $direccion = Validator::validateText($data['direccion'] ?? '', 0, 255) ?: '';

        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre_razon_social = ?, telefono = ?, departamento = ?, provincia = ?, distrito = ?, direccion = ? WHERE id = ?");
            $stmt->execute([$nombre_razon_social, $telefono, $departamento, $provincia, $distrito, $direccion, $authId]);

            // Consultar registro canónico actualizado directamente de la BD
            $freshStmt = $pdo->prepare("SELECT id, tipo_documento, numero_documento, nombre_razon_social, email, telefono, departamento, provincia, distrito, direccion, rol, creado_en FROM usuarios WHERE id = ? LIMIT 1");
            $freshStmt->execute([$authId]);
            $updatedUser = $freshStmt->fetch();

            // Conservar el token activo en la respuesta
            $currentToken = Vault::extractTokenFromRequest();
            $updatedUser['token'] = $currentToken;

            echo json_encode([
                'success' => true,
                'message' => 'Perfil actualizado exitosamente.',
                'user'    => $updatedUser
            ], JSON_UNESCAPED_UNICODE);
            exit();
        } catch (Throwable $e) {
            ApiResponse::error('Error interno al actualizar el perfil.', 'UPDATE_PROFILE_INTERNAL_ERROR', 500, $e);
        }
    }

    // 4. CAMBIO DE CONTRASEÑA (CHANGE_PASSWORD)
    if ($action === 'change_password') {
        RateLimiter::enforce($pdo, 'auth:change_password', 5, 900, 900);

        $identificador = trim((string)($data['identificador'] ?? $data['email'] ?? $data['numero_documento'] ?? ''));
        $currentPassword = is_string($data['current_password'] ?? null) ? $data['current_password'] : '';
        $newPasswordRaw = is_string($data['new_password'] ?? null) ? $data['new_password'] : '';

        if ($currentPassword === '' || $newPasswordRaw === '') {
            ApiResponse::error('Debe ingresar su contraseña actual y la nueva contraseña.', 'VALIDATION_ERROR', 400);
        }

        $newPassword = Validator::validatePassword($newPasswordRaw, 8, 128);
        if (!$newPassword) {
            ApiResponse::error('La nueva contraseña debe tener entre 8 y 128 caracteres y no puede consistir solo de espacios.', 'VALIDATION_ERROR', 400);
        }

        try {
            // Si no se envía identificador, buscar el usuario admin
            if (empty($identificador)) {
                $stmt = $pdo->query("SELECT * FROM usuarios WHERE rol = 'admin' LIMIT 1");
                $user = $stmt->fetch();
            } else {
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE (LOWER(email) = LOWER(?) OR numero_documento = ?) LIMIT 1");
                $stmt->execute([$identificador, $identificador]);
                $user = $stmt->fetch();
            }

            if (!$user) {
                ApiResponse::error('Usuario no encontrado en la base de datos.', 'USER_NOT_FOUND', 404);
            }

            // Verificar contraseña actual estrictamente sin trim destructivo
            $valid = password_verify($currentPassword, $user['password']);
            if (!$valid) {
                ApiResponse::error('La contraseña actual ingresada es incorrecta.', 'INVALID_CREDENTIALS', 401);
            }

            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $updateStmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $updateStmt->execute([$newHash, $user['id']]);

            echo json_encode([
                'success' => true,
                'message' => 'Contraseña actualizada correctamente.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        } catch (Throwable $e) {
            ApiResponse::error('Error interno al actualizar la contraseña.', 'CHANGE_PASSWORD_INTERNAL_ERROR', 500, $e);
        }
    }

    // 5. RENOVAR TOKEN DE SESIÓN (REFRESH_TOKEN)
    if ($action === 'refresh_token') {
        $userPayload = class_exists('Vault') ? Vault::requireAdmin() : null;
        if ($userPayload) {
            $stmt = $pdo->prepare("SELECT id, tipo_documento, numero_documento, nombre_razon_social, email, rol FROM usuarios WHERE id = ? LIMIT 1");
            $stmt->execute([$userPayload['uid']]);
            $u = $stmt->fetch();
            if ($u) {
                $newToken = Vault::generateToken($u);
                $u['token'] = $newToken;
                echo json_encode([
                    'success' => true,
                    'token'   => $newToken,
                    'user'    => $u
                ], JSON_UNESCAPED_UNICODE);
                exit();
            }
        }
    }
}

ApiResponse::error('Acción o método no soportado.', 'ACTION_NOT_SUPPORTED', 400);
