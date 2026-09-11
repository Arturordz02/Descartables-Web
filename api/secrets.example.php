<?php
/**
 * ====================================================================
 * PLANTILLA DE SECRETOS Y CONFIGURACIÓN PRIVADA
 * Plataforma Descartables Peruanos
 * ====================================================================
 * 
 * GUÍA DE SEGURIDAD Y PRECEDENCIA DE CONFIGURACIÓN:
 * 
 * El sistema carga los secretos y parámetros sensibles siguiendo este
 * orden estricto de precedencia (el nivel superior sobrescribe al inferior):
 * 
 * 1. VARIABLES DE ENTORNO DEL SISTEMA / CONTENEDOR (Máxima Prioridad)
 *    - DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 *    - FTP_HOST, FTP_USER, FTP_PASS
 *    - TOKEN_SALT (Clave criptográfica para firma de tokens HMAC-SHA256)
 *    - APP_ENV ('production' o 'development')
 * 
 * 2. ARCHIVO DE SECRETOS INDICADO POR VARIABLE DE ENTORNO
 *    - Ruta configurada en SECRETS_FILE_PATH (ej: /etc/descartables/secrets.php)
 * 
 * 3. ARCHIVO DE SECRETOS FUERA DE LA RAÍZ PÚBLICA (Recomendado en Hosting)
 *    - ../secrets.php o ../../secrets.php (ubicado fuera de public_html / htdocs)
 * 
 * 4. ARCHIVO DE SECRETOS LOCAL PROTEGIDO (Fallback)
 *    - api/secrets.php (Ignorado estrictamente en .gitignore y bloqueado vía HTTP)
 * 
 * --------------------------------------------------------------------
 * INSTRUCCIONES DE DESPLIEGUE:
 * 1. Copia este archivo como 'secrets.php' en tu ubicación elegida.
 * 2. Rellena los valores reales de conexión y genera una clave de firma
 *    aleatoria y segura para 'token_salt' (mínimo 32 caracteres).
 * 3. Asigna permisos restrictivos de lectura (ej: chmod 600 o 640).
 * 4. NUNCA agregues 'secrets.php' a Git ni a paquetes distribuibles públicos.
 * ====================================================================
 */

// Bloqueo de acceso HTTP directo
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(403);
    exit('Acceso directo denegado.');
}

return [
    // 1. Conexión a Base de Datos MySQL
    'db' => [
        'host' => 'localhost',
        'port' => '3306',
        'name' => 'descartables_db_prod',
        'user' => 'descartables_user',
        'pass' => '' // Coloca aquí la contraseña de base de datos
    ],

    // 2. Parámetros de Despliegue FTP / SFTP (Opcional - solo para herramientas de despliegue)
    'ftp' => [
        'host' => 'ftp.tudominio.pe',
        'user' => 'tu_usuario_ftp',
        'pass' => '', // Coloca aquí la contraseña de FTP si aplica
        'root' => '/htdocs'
    ],

    // 3. Claves de Firma y Seguridad Criptográfica
    'security' => [
        // Clave secreta para tokens de sesión HMAC-SHA256 (MÍNIMO 32 CARACTERES ALEATORIOS)
        // Ejemplo de generación en terminal: openssl rand -hex 32
        'token_salt'     => '', // Coloca aquí una cadena aleatoria de al menos 32 caracteres
        'system_version' => '2.5.0-Enterprise',
        'environment'    => 'production'
    ]
];
