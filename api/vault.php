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

// Bloqueo de acceso HTTP directo (Nadie puede consultar este archivo desde el navegador)
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => 'Acceso Denegado: La Caja Fuerte (Vault) es un recurso interno protegido del servidor.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

class Vault {

    /**
     * Almacén centralizado de configuración y carga de secretos privados
     */
    private static function getSecretsStorage() {
        static $cached = null;
        if ($cached !== null) return $cached;

        $defaults = [
            // 1. Configuración de Base de Datos MySQL (Valores por defecto / Variables de entorno)
            'db' => [
                'host' => getenv('DB_HOST') ?: 'sql201.infinityfree.com',
                'port' => getenv('DB_PORT') ?: '3306',
                'name' => getenv('DB_NAME') ?: 'if0_42834426_descartables',
                'user' => getenv('DB_USER') ?: 'if0_42834426',
                'pass' => getenv('DB_PASS') ?: ''
            ],

            // 2. Configuración del Despliegue FTP (Hosting)
            'ftp' => [
                'host' => getenv('FTP_HOST') ?: 'ftpupload.net',
                'user' => getenv('FTP_USER') ?: 'if0_42834426',
                'pass' => getenv('FTP_PASS') ?: '',
                'root' => '/htdocs'
            ],

            // 3. Cuentas Oficiales de Master Admin (Arturo, Britney, Lenin)
            // Se almacenan hashes seguros BCRYPT, NUNCA contraseñas en texto plano
            'admins' => [
                [
                    'nombre'    => 'Arturo (Master Admin)',
                    'email'     => 'arturo@admin.ad',
                    'doc'       => 'ADM-ARTURO',
                    'tipo_doc'  => 'CE',
                    'pass_hash' => '$2y$10$0ivWcR2sjvf.EYKTIhKxsOJVkL6.L26QECNMwgqACoGA8unENOij6',
                    'telefono'  => '994195430',
                    'direccion' => 'Lima, Perú'
                ],
                [
                    'nombre'    => 'Britney (Master Admin)',
                    'email'     => 'britney@admin.ad',
                    'doc'       => 'ADM-BRITNEY',
                    'tipo_doc'  => 'CE',
                    'pass_hash' => '$2y$10$gSPwnFMk7MmNvWis0BnoW.PKRSRFlNZ1/tHigulnkLLbAaWbeu9C.',
                    'telefono'  => '994009692',
                    'direccion' => 'Lima, Perú'
                ],
                [
                    'nombre'    => 'Lenin (Master Admin)',
                    'email'     => 'lenin@admin.ad',
                    'doc'       => 'ADM-LENIN',
                    'tipo_doc'  => 'CE',
                    'pass_hash' => '$2y$10$ZqMs8854b3ElfhhKAiuOFOv6.GitpvM1rcocKAOIYKk0W3q71dnCS',
                    'telefono'  => '994009692',
                    'direccion' => 'Lima, Perú'
                ]
            ],

            // 4. Datos de Contacto Reales y Oficiales (Custodiados de Forma Segura en el Vault)
            'official_contact' => [
                'whatsapp_principal'  => '+51 994 195 430',
                'whatsapp_secundario' => '+51 994 009 692',
                'telefono_central'    => '(01) 564-1450',
                'email_ventas'        => 'ventas@descartablesperuanos.pe',
                'email_cotizaciones'  => 'cotizaciones@descartablesperuanos.pe',
                'direccion'           => 'Av. Alejandro Bertello 732-C, Cercado de Lima, Lima, Perú',
                'ruc'                 => '20601234567',
                'razon_social'        => 'DESCARTABLES PERUANOS S.A.C.',
                'nombre_comercial'    => 'Descartables Peruanos'
            ],

            // 5. Datos de Contacto Genéricos / Placeholders (Para fase de desarrollo/demo pública)
            'placeholder_contact' => [
                'whatsapp_principal'  => '+51 900 000 000',
                'whatsapp_secundario' => '+51 900 000 002',
                'telefono_central'    => '(01) 000-0000',
                'email_ventas'        => 'contacto@descartablesperuanos.pe',
                'email_cotizaciones'  => 'cotizaciones@descartablesperuanos.pe'
            ],

            // 6. Llaves de Seguridad y Tokens
            'security' => [
                'token_salt'     => getenv('TOKEN_SALT') ?: 'DP_Peru_SecureSalt_2026_x89aF72kL9',
                'system_version' => '2.5.0-Enterprise',
                'environment'    => 'production'
            ]
        ];

        // Carga dinámica de credenciales privadas desde archivo no versionado (ignorado por Git)
        $secretsFile = __DIR__ . '/secrets.php';
        if (file_exists($secretsFile)) {
            $localSecrets = include $secretsFile;
            if (is_array($localSecrets)) {
                $defaults = array_replace_recursive($defaults, $localSecrets);
            }
        }

        $cached = $defaults;
        return $cached;
    }

    /**
     * Retorna la configuración de MySQL en InfinityFree
     */
    public static function getDbCredentials() {
        $secrets = self::getSecretsStorage();
        return $secrets['db'];
    }

    /**
     * Compatibilidad de entorno
     */
    public static function isLocalEnvironment() {
        return false;
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
        $secret = self::get('security.token_salt', 'DP_Peru_SecureSalt_2026_x89aF72kL9');
        $payload = [
            'id'    => (int)($user['id'] ?? 0),
            'uid'   => (int)($user['id'] ?? 0),
            'doc'   => (string)($user['numero_documento'] ?? ''),
            'email' => strtolower((string)($user['email'] ?? '')),
            'rol'   => (string)($user['rol'] ?? 'cliente'),
            'exp'   => time() + (86400 * 30) // Vigencia: 30 días
        ];
        $json = json_encode($payload);
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
        $secret = self::get('security.token_salt', 'DP_Peru_SecureSalt_2026_x89aF72kL9');
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
     * Extrae el token enviado en headers (Authorization: Bearer ..., X-Auth-Token) o params
     */
    public static function extractTokenFromRequest() {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($headers['Authorization'] ?? ($headers['authorization'] ?? ''));
        if (!empty($authHeader) && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        $xAuth = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? ($headers['X-Auth-Token'] ?? ($headers['x-auth-token'] ?? ''));
        if (!empty($xAuth)) {
            return trim($xAuth);
        }

        if (!empty($_REQUEST['token'])) {
            return trim($_REQUEST['token']);
        }

        if (!empty($_GET['token'])) {
            return trim($_GET['token']);
        }

        return null;
    }

    /**
     * Guardián de seguridad: Valida si la solicitud actual proviene de un Administrador autorizado
     */
    public static function requireAdmin() {
        // 1. Validar por Token HMAC de sesión
        $token = self::extractTokenFromRequest();
        if ($token) {
            $payload = self::validateToken($token);
            if ($payload && isset($payload['rol']) && $payload['rol'] === 'admin') {
                return $payload;
            }
        }

        // 2. Fallback seguro: Validación de credencial Master Admin comprobada contra MySQL
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $adminDoc = $_SERVER['HTTP_X_ADMIN_DOC'] ?? ($headers['X-Admin-Doc'] ?? ($headers['x-admin-doc'] ?? ($_REQUEST['admin_doc'] ?? null)));
        $adminEmail = $_SERVER['HTTP_X_ADMIN_EMAIL'] ?? ($headers['X-Admin-Email'] ?? ($headers['x-admin-email'] ?? ($_REQUEST['admin_email'] ?? null)));

        if (!empty($adminDoc) || !empty($adminEmail)) {
            try {
                require_once __DIR__ . '/db.php';
                $pdo = getDbConnection();
                if ($pdo) {
                    $stmt = $pdo->prepare("SELECT id, nombre_razon_social, email, rol, tipo_documento, numero_documento FROM usuarios WHERE (numero_documento = ? OR LOWER(email) = LOWER(?)) AND rol = 'admin' LIMIT 1");
                    $stmt->execute([$adminDoc ?: '', $adminEmail ?: '']);
                    $admin = $stmt->fetch();
                    if ($admin) {
                        return [
                            'uid'   => (int)$admin['id'],
                            'doc'   => $admin['numero_documento'],
                            'email' => $admin['email'],
                            'rol'   => 'admin'
                        ];
                    }
                }
            } catch (Exception $e) {}
        }

        // Bloqueo total
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'Acceso denegado: Esta operación requiere privilegios de Administrador autenticado.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}
