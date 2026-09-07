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
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/vault.php';

// Obtención de credenciales seguras desde la Caja Fuerte (Vault)
$dbConfig = Vault::getDbCredentials();

define('DB_HOST', $dbConfig['host']);
define('DB_PORT', $dbConfig['port']);
define('DB_NAME', $dbConfig['name']);
define('DB_USER', $dbConfig['user']);
define('DB_PASS', $dbConfig['pass']);

// Datos Oficiales de la Empresa en Perú (INDECOPI / SUNAT)
define('EMPRESA_RAZON_SOCIAL', 'DESCARTABLES PERUANOS S.A.C.');
define('EMPRESA_RUC', '20601234567');
define('EMPRESA_DIRECCION', 'Av. Alejandro Bertello 732-C, Cercado de Lima');
define('EMPRESA_TELEFONO', '(01) 000-0000');
define('EMPRESA_WHATSAPP_1', '+51 900 000 000');
define('EMPRESA_WHATSAPP_2', '+51 900 000 002');
define('EMPRESA_EMAIL', 'ventas@descartablesperuanos.pe');
