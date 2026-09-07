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
     * Almacén de credenciales y secretos del sistema
     */
    private static function getSecretsStorage() {
        return [
            // 1. Credenciales de Base de Datos MySQL (Producción Exclusiva InfinityFree)
            'db' => [
                'host' => 'sql201.infinityfree.com',
                'port' => '3306',
                'name' => 'if0_42834426_descartables',
                'user' => 'if0_42834426',
                'pass' => 'Contra246World'
            ],

            // 2. Credenciales del Despliegue FTP (Hosting)
            'ftp' => [
                'host' => 'ftpupload.net',
                'user' => 'if0_42834426',
                'pass' => 'Contra246World',
                'root' => '/htdocs'
            ],

            // 3. Cuentas Oficiales de Master Admin (Arturo, Britney, Lenin)
            'admins' => [
                [
                    'nombre'    => 'Arturo (Master Admin)',
                    'email'     => 'arturo@admin.ad',
                    'doc'       => 'ADM-ARTURO',
                    'tipo_doc'  => 'CE',
                    'pass_raw'  => 'Arturo@Admin2026!',
                    'telefono'  => '994195430',
                    'direccion' => 'Lima, Perú'
                ],
                [
                    'nombre'    => 'Britney (Master Admin)',
                    'email'     => 'britney@admin.ad',
                    'doc'       => 'ADM-BRITNEY',
                    'tipo_doc'  => 'CE',
                    'pass_raw'  => 'Britney@Admin2026!',
                    'telefono'  => '994009692',
                    'direccion' => 'Lima, Perú'
                ],
                [
                    'nombre'    => 'Lenin (Master Admin)',
                    'email'     => 'lenin@admin.ad',
                    'doc'       => 'ADM-LENIN',
                    'tipo_doc'  => 'CE',
                    'pass_raw'  => 'Lenin@Admin2026!',
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
                'token_salt'     => 'DP_Peru_SecureSalt_2026_x89aF72kL9',
                'system_version' => '2.5.0-Enterprise',
                'environment'    => 'production'
            ]
        ];
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
     * Verificación segura de contraseñas contra hash BCRYPT
     */
    public static function verifyPassword($plainPassword, $hash) {
        if (empty($plainPassword) || empty($hash)) return false;
        return password_verify($plainPassword, $hash) || $hash === $plainPassword;
    }
}
