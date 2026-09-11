<?php
/**
 * ====================================================================
 * CAJA FUERTE DE SEGURIDAD & GESTOR CENTRALIZADO DE SECRETOS (VAULT)
 * Plataforma Descartables Peruanos
 * ====================================================================
 * 
 * Este módulo actúa como la "Caja Fuerte" (Secret Vault / Environment Manager)
 * del sistema. Contiene y custodia de forma aislada todos los datos sensibles:
 * - Credenciales de Base de Datos MySQL (Local XAMPP & Producción InfinityFree)
 * - Credenciales maestras de los 3 Administradores (Arturo, Britney, Lenin)
 * - Llaves de cifrado y funciones criptográficas seguras
 * 
 * NINGÚN dato sensible debe estar disperso en otros archivos. Cuando cualquier
 * script de la API necesita un dato confidencial, se lo solicita a esta clase.
 */

require_once __DIR__ . '/response.php';

// Bloqueo de acceso HTTP directo (Nadie puede consultar este archivo desde el navegador)
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    ApiResponse::error(
        'Acceso Denegado: La Caja Fuerte (Vault) es un recurso interno protegido del servidor.',
        'FORBIDDEN',
        403
    );
}

class Vault {

    /**
     * Almacén centralizado de configuración y carga de secretos privados
     */
    private static function getSecretsStorage() {
        static $cached = null;
        if ($cached !== null) return $cached;

        // 1. Valores por defecto genéricos / neutros (Sin credenciales reales)
        $defaults = [
            'db' => [
                'host' => 'localhost',
                'port' => '3306',
                'name' => 'descartables_db',
                'user' => 'db_user',
                'pass' => ''
            ],
            'ftp' => [
                'host' => 'ftp.ejemplo.com',
                'user' => 'ftp_user',
                'pass' => '',
                'root' => '/htdocs'
            ],
            'admins' => [],
            'official_contact' => [
                'whatsapp_principal'  => '+51 900 000 000',
                'whatsapp_secundario' => '+51 900 000 002',
                'telefono_central'    => '(01) 000-0000',
                'email_ventas'        => 'ventas@descartablesperuanos.pe',
                'email_cotizaciones'  => 'cotizaciones@descartablesperuanos.pe',
                'direccion'           => 'Lima, Perú',
                'ruc'                 => '20000000001',
                'razon_social'        => 'DESCARTABLES PERUANOS S.A.C.',
                'nombre_comercial'    => 'Descartables Peruanos'
            ],
            'placeholder_contact' => [
                'whatsapp_principal'  => '+51 900 000 000',
                'whatsapp_secundario' => '+51 900 000 002',
                'telefono_central'    => '(01) 000-0000',
                'email_ventas'        => 'contacto@descartablesperuanos.pe',
                'email_cotizaciones'  => 'cotizaciones@descartablesperuanos.pe'
            ],
            'security' => [
                'token_salt'      => '', // Sin clave predecible por defecto; debe provenir de env o secrets.php
                'system_version'  => '2.5.0-Enterprise',
                'environment'     => 'production',
                'allowed_origins' => []
            ]
        ];

        // 2. Precedencia de carga de archivo de secretos
        // Prioridad de rutas:
        // A) Variable de entorno SECRETS_FILE_PATH
        // B) Fuera de la raíz pública del servidor (un nivel arriba o dos niveles arriba)
        // C) En el directorio de la API (api/secrets.php protegido)
        $potentialSecretsPaths = [];
        $envSecretsPath = getenv('SECRETS_FILE_PATH') ?: ($_ENV['SECRETS_FILE_PATH'] ?? null);
        if ($envSecretsPath) {
            $potentialSecretsPaths[] = $envSecretsPath;
        }
        $potentialSecretsPaths[] = dirname(__DIR__, 2) . '/secrets.php';
        $potentialSecretsPaths[] = dirname(__DIR__) . '/secrets.php';
        $potentialSecretsPaths[] = __DIR__ . '/secrets.php';

        $loadedFileSecrets = [];
        foreach ($potentialSecretsPaths as $path) {
            if (@file_exists($path) && @is_readable($path)) {
                $content = @include $path;
                if (is_array($content)) {
                    $loadedFileSecrets = $content;
                    break;
                }
            }
        }

        if (!empty($loadedFileSecrets)) {
            $defaults = array_replace_recursive($defaults, $loadedFileSecrets);
        }

        // 3. Sobrescritura directa por Variables de Entorno del Sistema (Máxima Prioridad)
        if (getenv('DB_HOST') !== false) $defaults['db']['host'] = getenv('DB_HOST');
        if (getenv('DB_PORT') !== false) $defaults['db']['port'] = getenv('DB_PORT');
        if (getenv('DB_NAME') !== false) $defaults['db']['name'] = getenv('DB_NAME');
        if (getenv('DB_USER') !== false) $defaults['db']['user'] = getenv('DB_USER');
        if (getenv('DB_PASS') !== false) $defaults['db']['pass'] = getenv('DB_PASS');

        if (getenv('FTP_HOST') !== false) $defaults['ftp']['host'] = getenv('FTP_HOST');
        if (getenv('FTP_USER') !== false) $defaults['ftp']['user'] = getenv('FTP_USER');
        if (getenv('FTP_PASS') !== false) $defaults['ftp']['pass'] = getenv('FTP_PASS');

        if (getenv('TOKEN_SALT') !== false) $defaults['security']['token_salt'] = getenv('TOKEN_SALT');
        if (getenv('APP_ENV') !== false) $defaults['security']['environment'] = getenv('APP_ENV');
        if (getenv('ALLOWED_ORIGINS') !== false) {
            $rawOrigins = (string)getenv('ALLOWED_ORIGINS');
            $defaults['security']['allowed_origins'] = array_filter(array_map('trim', explode(',', $rawOrigins)));
        }

        $cached = $defaults;
        return $cached;
    }

    /**
     * Retorna la configuración de MySQL
     */
    public static function getDbCredentials() {
        $secrets = self::getSecretsStorage();
        return $secrets['db'];
    }

    /**
     * Retorna la clave secreta de firma criptográfica (salt)
     * Si no está configurada o es insegura (< 16 caracteres), aborta de forma controlada sin exponer secretos.
     */
    public static function getTokenSalt() {
        $salt = self::get('security.token_salt');
        if (empty($salt) || !is_string($salt) || strlen(trim($salt)) < 16) {
            Logger::critical('[VAULT SECURITY ALERT] Clave de firma criptográfica (token_salt) no configurada o insuficiente (< 16 caracteres).');
            ApiResponse::error(
                'Error de configuración interna del servidor. Contacte al administrador.',
                'SALT_CONFIGURATION_ERROR',
                500
            );
        }
        return trim($salt);
    }

    /**
     * Compatibilidad de entorno
     */
    public static function isLocalEnvironment() {
        $env = self::get('security.environment', 'production');
        return ($env === 'development' || $env === 'local');
    }

    /**
     * Retorna la lista de administradores maestros autorizados
     */
    public static function getMasterAdmins() {
        $secrets = self::getSecretsStorage();
        return $secrets['admins'];
    }

    /**
     * Retorna los datos de contacto oficiales custodiados
     */
    public static function getOfficialContact() {
        $secrets = self::getSecretsStorage();
        return $secrets['official_contact'];
    }

    /**
     * Retorna los datos de contacto genéricos / placeholders
     */
    public static function getPlaceholderContact() {
        $secrets = self::getSecretsStorage();
        return $secrets['placeholder_contact'];
    }

    /**
     * Retorna un secreto específico por su ruta (ej: 'security.token_salt')
     */
    public static function get($keyPath, $default = null) {
        $secrets = self::getSecretsStorage();
        $keys = explode('.', $keyPath);
        $curr = $secrets;

        foreach ($keys as $k) {
            if (is_array($curr) && isset($curr[$k])) {
                $curr = $curr[$k];
            } else {
                return $default;
            }
        }

        return $curr;
    }

    /**
     * Gestión Centralizada y Segura de Cabeceras CORS (F15)
     * Protege contra accesos no autorizados sin exponer wildcards (*) peligrosos en producción.
     * Permite orígenes configurados en secrets.php o variable de entorno ALLOWED_ORIGINS.
     */
    public static function handleCors(): void {
        if (php_sapi_name() === 'cli' || headers_sent()) {
            return;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $isAllowed = false;

        if (!empty($origin)) {
            $parsedOrigin = parse_url($origin);
            $originHost = strtolower($parsedOrigin['host'] ?? '');
            $currentHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
            if (strpos($currentHost, ':') !== false) {
                $currentHost = explode(':', $currentHost)[0];
            }

            // 1. Mismo host de la solicitud (Same-Origin explícito)
            if (!empty($originHost) && !empty($currentHost) && $originHost === $currentHost) {
                $isAllowed = true;
            }

            // 2. Entornos de desarrollo local (solo si isLocalEnvironment())
            if (!$isAllowed && self::isLocalEnvironment()) {
                if (in_array($originHost, ['localhost', '127.0.0.1', '::1'], true)) {
                    $isAllowed = true;
                }
            }

            // 3. Orígenes permitidos configurados en secrets.php o variable ALLOWED_ORIGINS
            if (!$isAllowed) {
                $configuredOrigins = self::get('security.allowed_origins', []);
                if (is_string($configuredOrigins)) {
                    $configuredOrigins = array_filter(array_map('trim', explode(',', $configuredOrigins)));
                }
                if (is_array($configuredOrigins)) {
                    foreach ($configuredOrigins as $allowed) {
                        $allowed = trim(strtolower((string)$allowed));
                        if (empty($allowed)) continue;
                        $allowedHost = parse_url($allowed, PHP_URL_HOST) ?: $allowed;
                        if ($originHost === $allowedHost || rtrim($origin, '/') === rtrim($allowed, '/')) {
                            $isAllowed = true;
                            break;
                        }
                    }
                }
            }

            if ($isAllowed) {
                header("Access-Control-Allow-Origin: {$origin}");
                header('Access-Control-Allow-Credentials: true');
                header('Vary: Origin');
            }
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Idempotency-Key, X-Idempotency-Key, X-Health-Key, X-Request-Id');
        header('Access-Control-Expose-Headers: X-Idempotent-Replay, X-Request-Id');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    /**
     * Hasheo seguro de contraseñas mediante algoritmo BCRYPT estándar
     */
    public static function hashPassword($plainPassword) {
        return password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    /**
     * Verificación segura de contraseñas contra hash BCRYPT (sin puertas traseras)
     */
    public static function verifyPassword($plainPassword, $hash) {
        if (empty($plainPassword) || empty($hash)) return false;
        return password_verify($plainPassword, $hash);
    }

    /**
     * Genera un token HMAC criptográficamente firmado para la sesión del usuario
     */
    public static function generateToken($user) {
        $secret = self::getTokenSalt();
        
        // Firma corta del hash de contraseña actual para invalidación automática si la contraseña cambia
        $passSignature = '';
        if (!empty($user['password'])) {
            $passSignature = substr(hash('sha256', (string)$user['password']), 0, 16);
        }

        $payload = [
            'id'    => (int)($user['id'] ?? 0),
            'uid'   => (int)($user['id'] ?? 0),
            'doc'   => (string)($user['numero_documento'] ?? ''),
            'email' => strtolower((string)($user['email'] ?? '')),
            'rol'   => (string)($user['rol'] ?? 'cliente'),
            'psig'  => $passSignature,
            'iat'   => time(),
            'exp'   => time() + (86400 * 30) // Vigencia: 30 días
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $encoded = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encoded, $secret);
        return $encoded . '.' . $signature;
    }

    /**
     * Valida un token HMAC y retorna el payload decodificado o false
     */
    public static function validateToken($token) {
        if (empty($token) || !is_string($token)) return false;
        $parts = explode('.', $token);
        if (count($parts) !== 2) return false;

        list($encoded, $signature) = $parts;
        $secret = self::getTokenSalt();
        $expectedSig = hash_hmac('sha256', $encoded, $secret);

        if (!hash_equals($expectedSig, $signature)) {
            return false;
        }

        $decodedJson = base64_decode(strtr($encoded, '-_', '+/'));
        if (!$decodedJson) return false;

        $payload = json_decode($decodedJson, true);
        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
            return false;
        }

        return $payload;
    }

    /**
     * Extrae el token enviado exclusivamente a través de cabeceras HTTP seguras
     * (Authorization: Bearer <token> o X-Auth-Token: <token>).
     * NO acepta tokens en parámetros GET / POST / URL.
     */
    public static function extractTokenFromRequest() {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($headers['Authorization'] ?? ($headers['authorization'] ?? ''));
        if (!empty($authHeader) && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }

        $xAuth = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? ($headers['X-Auth-Token'] ?? ($headers['x-auth-token'] ?? ''));
        if (!empty($xAuth)) {
            return trim($xAuth);
        }

        return null;
    }

    /**
     * Guardián de seguridad de sesión general: Valida que exista una sesión activa
     */
    public static function requireAuth($pdo = null) {
        $token = self::extractTokenFromRequest();
        if (!$token) {
            ApiResponse::error('No autorizado: Se requiere una sesión activa.', 'AUTH_REQUIRED', 401);
            return null;
        }

        $payload = self::validateToken($token);
        if (!$payload) {
            ApiResponse::error('No autorizado: Sesión inválida o expirada.', 'TOKEN_INVALID', 401);
            return null;
        }

        if (!$pdo) {
            try {
                if (function_exists('getDbConnection')) {
                    $pdo = getDbConnection();
                } else {
                    require_once __DIR__ . '/db.php';
                    $pdo = getDbConnection();
                }
            } catch (Throwable $e) {
                $pdo = null;
            }
        }

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT id, tipo_documento, numero_documento, nombre_razon_social, email, password, rol FROM usuarios WHERE id = ? LIMIT 1");
                $stmt->execute([(int)($payload['uid'] ?? $payload['id'])]);
                $dbUser = $stmt->fetch();

                if (!$dbUser) {
                    ApiResponse::error('No autorizado: Cuenta de usuario no encontrada.', 'USER_NOT_FOUND', 401);
                    return null;
                }

                // Revocación por cambio de contraseña
                if (!empty($payload['psig']) && !empty($dbUser['password'])) {
                    $currentPassSig = substr(hash('sha256', (string)$dbUser['password']), 0, 16);
                    if (!hash_equals($currentPassSig, $payload['psig'])) {
                        ApiResponse::error('No autorizado: La sesión ha sido revocada por cambio de credenciales.', 'SESSION_REVOKED', 401);
                        return null;
                    }
                }

                return [
                    'id'                  => (int)$dbUser['id'],
                    'uid'                 => (int)$dbUser['id'],
                    'tipo_documento'      => $dbUser['tipo_documento'] ?? 'DNI',
                    'numero_documento'    => $dbUser['numero_documento'],
                    'nombre_razon_social' => $dbUser['nombre_razon_social'],
                    'email'               => $dbUser['email'],
                    'rol'                 => $dbUser['rol']
                ];
            } catch (Throwable $e) {
                Logger::error('[VAULT DB ERROR] Error consultando estado de usuario en requireAuth: ' . $e->getMessage());
            }
        }

        return $payload;
    }

    /**
     * Guardián de seguridad administrativo: Valida sesión HMAC estricta y vigencia del rol admin en BD
     * NUNCA confía en cabeceras o parámetros no autenticados (X-Admin-Doc, admin_email, etc.)
     */
    public static function requireAdmin($pdo = null) {
        $token = self::extractTokenFromRequest();
        if (!$token) {
            ApiResponse::error('No autorizado: Token de sesión ausente o no proporcionado.', 'AUTH_REQUIRED', 401);
            return null;
        }

        $payload = self::validateToken($token);
        if (!$payload) {
            ApiResponse::error('No autorizado: Sesión inválida o expirada.', 'TOKEN_INVALID', 401);
            return null;
        }

        // Verificación preliminar en token
        if (!isset($payload['rol']) || $payload['rol'] !== 'admin') {
            ApiResponse::error('Acceso denegado: Privilegios de Administrador requeridos.', 'FORBIDDEN', 403);
            return null;
        }

        // Verificación estricta en Base de Datos (Estado en tiempo real)
        if (!$pdo) {
            try {
                if (function_exists('getDbConnection')) {
                    $pdo = getDbConnection();
                } else {
                    require_once __DIR__ . '/db.php';
                    $pdo = getDbConnection();
                }
            } catch (Throwable $e) {
                $pdo = null;
            }
        }

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT id, tipo_documento, numero_documento, nombre_razon_social, email, password, rol FROM usuarios WHERE id = ? LIMIT 1");
                $stmt->execute([(int)($payload['uid'] ?? $payload['id'])]);
                $dbUser = $stmt->fetch();

                if (!$dbUser || $dbUser['rol'] !== 'admin') {
                    ApiResponse::error('Acceso denegado: Privilegios de Administrador revocados o inexistentes.', 'FORBIDDEN', 403);
                    return null;
                }

                // Revocación por cambio de contraseña
                if (!empty($payload['psig']) && !empty($dbUser['password'])) {
                    $currentPassSig = substr(hash('sha256', (string)$dbUser['password']), 0, 16);
                    if (!hash_equals($currentPassSig, $payload['psig'])) {
                        ApiResponse::error('No autorizado: La sesión de administrador ha sido revocada por cambio de credenciales.', 'SESSION_REVOKED', 401);
                        return null;
                    }
                }

                return [
                    'id'                  => (int)$dbUser['id'],
                    'uid'                 => (int)$dbUser['id'],
                    'tipo_documento'      => $dbUser['tipo_documento'] ?? 'DNI',
                    'numero_documento'    => $dbUser['numero_documento'],
                    'nombre_razon_social' => $dbUser['nombre_razon_social'],
                    'email'               => $dbUser['email'],
                    'rol'                 => $dbUser['rol']
                ];
            } catch (Throwable $e) {
                Logger::error('[VAULT DB ERROR] Error consultando rol de admin en requireAdmin: ' . $e->getMessage());
            }
        }

        return [
            'id'                  => (int)($payload['uid'] ?? $payload['id']),
            'email'               => $payload['email'] ?? '',
            'rol'                 => 'admin',
            'nombre_razon_social' => $payload['nombre'] ?? 'Administrador'
        ];
    }
}
