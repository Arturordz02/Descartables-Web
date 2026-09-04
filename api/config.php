<?php
/**
 * Configuración General y Base de Datos MySQL
 * Plataforma Descartables Peruanos
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Access-Control-Request-Private-Network');
header('Access-Control-Allow-Private-Network: true');
header('Content-Type: application/json; charset=utf-8');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Parámetros de Conexión a MySQL con detección automática de entorno
$is_remote = (isset($_SERVER['HTTP_HOST']) && (
    strpos($_SERVER['HTTP_HOST'], 'free.nf') !== false || 
    strpos($_SERVER['HTTP_HOST'], 'infinityfree') !== false ||
    strpos($_SERVER['HTTP_HOST'], 'epizy') !== false
));

if ($is_remote) {
    // Entorno Nube: InfinityFree
    define('DB_HOST', 'sql201.infinityfree.com');
    define('DB_PORT', '3306');
    define('DB_NAME', 'if0_42834426_descartables');
    define('DB_USER', 'if0_42834426');
    define('DB_PASS', 'Contra246World');
} else {
    // Entorno Local: XAMPP / MariaDB
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', '3306');
    define('DB_NAME', 'descartables_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

// Datos Oficiales de la Empresa en Perú (INDECOPI / SUNAT)
define('EMPRESA_RAZON_SOCIAL', 'DESCARTABLES PERUANOS S.A.C.');
define('EMPRESA_RUC', '20601234567');
define('EMPRESA_DIRECCION', 'Av. Alejandro Bertello 732-C, Cercado de Lima');
define('EMPRESA_TELEFONO', '(01) 564-1450');
define('EMPRESA_WHATSAPP_1', '+51 994 195 430');
define('EMPRESA_WHATSAPP_2', '+51 994 009 692');
define('EMPRESA_EMAIL', 'ventas@descartablesperuanos.pe');

