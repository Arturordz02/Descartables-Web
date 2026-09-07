<?php
/**
 * API REST: Autenticación de Usuarios (DNI / RUC / Clientes)
 * Endpoints: login, register, update_profile
 */

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!$data || !is_array($data)) {
        $data = $_POST;
    }

    if (empty($data)) {
        echo json_encode([
            'success' => false,
            'error'   => 'Datos de solicitud inválidos o vacíos.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $pdo = getDbConnection();

    // 1. INICIAR SESIÓN (LOGIN)
    if ($action === 'login') {
        $identificador = trim($data['identificador'] ?? '');
        $password = trim($data['password'] ?? '');

        if (empty($identificador) || empty($password)) {
            echo json_encode([
                'success' => false,
                'error'   => 'Debe ingresar su identificador (DNI/RUC/Correo) y su contraseña.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE (LOWER(email) = LOWER(?) OR numero_documento = ?) LIMIT 1");
        $stmt->execute([$identificador, $identificador]);
        $user = $stmt->fetch();

        if ($user) {
            // Verificar contraseña de forma criptográficamente estricta (sin puertas traseras)
            $valid = password_verify($password, $user['password']);
            if ($valid) {
                unset($user['password']);
                $token = class_exists('Vault') ? Vault::generateToken($user) : null;
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

        echo json_encode([
            'success' => false,
            'error'   => 'Credenciales inválidas. Compruebe sus datos o regístrese.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 2. REGISTRO DE USUARIOS (REGISTER)
    if ($action === 'register') {
        $tipo_documento = trim($data['tipo_documento'] ?? 'DNI');
        $numero_documento = trim($data['numero_documento'] ?? '');
        $nombre_razon_social = trim($data['nombre_razon_social'] ?? '');
        $email = trim($data['email'] ?? '');
        $telefono = trim($data['telefono'] ?? '');
        $departamento = trim($data['departamento'] ?? 'Lima');
        $provincia = trim($data['provincia'] ?? 'Lima');
        $distrito = trim($data['distrito'] ?? '');
        $direccion = trim($data['direccion'] ?? '');
        $password = trim($data['password'] ?? '');

        if (empty($numero_documento) || empty($nombre_razon_social) || empty($email) || empty($password)) {
            echo json_encode([
                'success' => false,
                'error'   => 'Los campos Documento, Nombre/Razón Social, Correo y Contraseña son obligatorios.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Verificar existencia previa
        $checkStmt = $pdo->prepare("SELECT id FROM usuarios WHERE numero_documento = ? OR LOWER(email) = LOWER(?) LIMIT 1");
        $checkStmt->execute([$numero_documento, $email]);
        if ($checkStmt->fetch()) {
            echo json_encode([
                'success' => false,
                'error'   => 'El número de documento o correo electrónico ya se encuentra registrado.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
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

        $newId = $pdo->lastInsertId();
        $userStmt = $pdo->prepare("SELECT id, tipo_documento, numero_documento, nombre_razon_social, email, telefono, departamento, provincia, distrito, direccion, rol FROM usuarios WHERE id = ?");
        $userStmt->execute([$newId]);
        $createdUser = $userStmt->fetch();

        $token = class_exists('Vault') ? Vault::generateToken($createdUser) : null;
        $createdUser['token'] = $token;

        echo json_encode([
            'success' => true,
            'message' => 'Usuario registrado exitosamente.',
            'token'   => $token,
            'user'    => $createdUser
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 3. ACTUALIZAR PERFIL (UPDATE_PROFILE)
    if ($action === 'update_profile') {
        $id = isset($data['id']) ? (int)$data['id'] : null;
        $numero_documento = trim($data['numero_documento'] ?? '');
        $nombre_razon_social = trim($data['nombre_razon_social'] ?? '');
        $telefono = trim($data['telefono'] ?? '');
        $departamento = trim($data['departamento'] ?? 'Lima');
        $provincia = trim($data['provincia'] ?? 'Lima');
        $distrito = trim($data['distrito'] ?? '');
        $direccion = trim($data['direccion'] ?? '');

        if ($id) {
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre_razon_social = ?, telefono = ?, departamento = ?, provincia = ?, distrito = ?, direccion = ? WHERE id = ?");
            $stmt->execute([$nombre_razon_social, $telefono, $departamento, $provincia, $distrito, $direccion, $id]);
        } elseif ($numero_documento) {
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre_razon_social = ?, telefono = ?, departamento = ?, provincia = ?, distrito = ?, direccion = ? WHERE numero_documento = ?");
            $stmt->execute([$nombre_razon_social, $telefono, $departamento, $provincia, $distrito, $direccion, $numero_documento]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Perfil actualizado exitosamente.',
            'user'    => $data
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 4. CAMBIO DE CONTRASEÑA (CHANGE_PASSWORD)
    if ($action === 'change_password') {
        $identificador = trim($data['identificador'] ?? $data['email'] ?? $data['numero_documento'] ?? '');
        $currentPassword = trim($data['current_password'] ?? '');
        $newPassword = trim($data['new_password'] ?? '');

        if (empty($currentPassword) || empty($newPassword)) {
            echo json_encode([
                'success' => false,
                'error'   => 'Debe ingresar su contraseña actual y la nueva contraseña.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if (strlen($newPassword) < 6) {
            echo json_encode([
                'success' => false,
                'error'   => 'La nueva contraseña debe tener al menos 6 caracteres.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

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
            echo json_encode([
                'success' => false,
                'error'   => 'Usuario no encontrado en la base de datos.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // Verificar contraseña actual estrictamente
        $valid = password_verify($currentPassword, $user['password']);
        if (!$valid) {
            echo json_encode([
                'success' => false,
                'error'   => 'La contraseña actual ingresada es incorrecta.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $updateStmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $updateStmt->execute([$newHash, $user['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Contraseña actualizada correctamente.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
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

echo json_encode([
    'success' => false,
    'error'   => 'Acción o método no soportado.'
], JSON_UNESCAPED_UNICODE);


