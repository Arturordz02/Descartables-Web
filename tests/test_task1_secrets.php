<?php
/**
 * Test Suite: Tarea 1 - Aislamiento de Secretos y Precedencia de Configuración
 */

echo "=== INICIANDO PRUEBAS DE TAREA 1: SECRETOS & VAULT ===\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertTest($description, $condition) {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo " [PASS] $description\n";
        $testsPassed++;
    } else {
        echo " [FAIL] $description\n";
        $testsFailed++;
    }
}

// --- TEST 1: Carga y precedencia de Variables de Entorno ---
putenv("TOKEN_SALT=test_synthetic_secure_salt_2026_x89aF72kL9_32char");
putenv("DB_HOST=127.0.0.1");
putenv("DB_NAME=synthetic_test_db");
putenv("DB_USER=synthetic_test_user");
putenv("DB_PASS=synthetic_secret_password_abc");
putenv("APP_ENV=development");

require_once __DIR__ . '/../api/vault.php';

$db = Vault::getDbCredentials();
assertTest("Vault carga DB_HOST desde variables de entorno", $db['host'] === '127.0.0.1');
assertTest("Vault carga DB_NAME desde variables de entorno", $db['name'] === 'synthetic_test_db');
assertTest("Vault carga DB_USER desde variables de entorno", $db['user'] === 'synthetic_test_user');
assertTest("Vault identifica entorno development", Vault::isLocalEnvironment() === true);

// --- TEST 2: Generación y Validación de Tokens HMAC con clave sintética ---
$syntheticUser = [
    'id' => 42,
    'numero_documento' => '77889900',
    'email' => 'synthetic_admin@test.local',
    'rol' => 'admin'
];

$token = Vault::generateToken($syntheticUser);
assertTest("Vault genera token HMAC firmado no vacío", !empty($token) && strpos($token, '.') !== false);

$payload = Vault::validateToken($token);
assertTest("Vault valida token legítimo y decodifica ID de usuario", $payload !== false && $payload['id'] === 42);
assertTest("Vault valida rol correcto en payload", $payload['rol'] === 'admin');

// --- TEST 3: Rechazo de token manipulado / alterado ---
$tamperedToken = $token . 'tampered';
$invalidPayload = Vault::validateToken($tamperedToken);
assertTest("Vault rechaza token con firma alterada", $invalidPayload === false);

// --- TEST 4: Verificación de plantilla secrets.example.php ---
$exampleFile = __DIR__ . '/../api/secrets.example.php';
assertTest("Archivo api/secrets.example.php existe", file_exists($exampleFile));
$exampleConfig = include $exampleFile;
assertTest("api/secrets.example.php retorna arreglo válido", is_array($exampleConfig));
assertTest("api/secrets.example.php tiene sección db con host localhost", isset($exampleConfig['db']['host']) && $exampleConfig['db']['host'] === 'localhost');
assertTest("api/secrets.example.php no contiene contraseñas reales", $exampleConfig['db']['pass'] === '');

// --- TEST 5: Fallo controlado si salt no está configurado (< 16 caracteres) ---
// Ejecutar un subproceso PHP aislado sin salt para comprobar que devuelve HTTP 500 y aborta limpiamente
$cmd = 'php -r "putenv(\"TOKEN_SALT=\"); require_once \'api/vault.php\'; Vault::generateToken([\'id\' => 1]);" 2>&1';
$output = shell_exec($cmd);
assertTest("Vault responde con error JSON controlado ante ausencia de salt", strpos($output, 'Error de configuración interna del servidor') !== false);

echo "\n=======================================================\n";
echo "RESULTADO: $testsPassed pruebas pasadas, $testsFailed fallidas.\n";
echo "=======================================================\n";

if ($testsFailed > 0) {
    exit(1);
}
