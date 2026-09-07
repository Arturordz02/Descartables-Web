<?php
/**
 * ====================================================================
 * PLANTILLA DE SECRETOS Y CREDENCIALES DEL SISTEMA
 * Plataforma Descartables Peruanos
 * ====================================================================
 * INSTRUCCIONES:
 * 1. Copia este archivo con el nombre 'secrets.php' en esta misma carpeta (api/secrets.php).
 * 2. Rellena los datos de tu base de datos MySQL y entorno.
 * 3. El archivo 'api/secrets.php' está incluido en .gitignore y NUNCA se subirá a Git.
 */

return [
    // 1. Configuración de Base de Datos MySQL
    'db' => [
        'host' => 'localhost',
        'port' => '3306',
        'name' => 'tu_base_de_datos',
        'user' => 'tu_usuario',
        'pass' => 'tu_contraseña'
    ],

    // 2. Configuración de Despliegue FTP (Opcional)
    'ftp' => [
        'host' => 'ftpupload.net',
        'user' => 'tu_usuario_ftp',
        'pass' => 'tu_contraseña_ftp',
        'root' => '/htdocs'
    ],

    // 3. Sal Criptográfica para Tokens HMAC-SHA256
    'security' => [
        'token_salt'     => 'coloca_aqui_una_cadena_larga_aleatoria_para_firma_de_tokens',
        'system_version' => '2.5.0-Enterprise',
        'environment'    => 'development'
    ]
];
