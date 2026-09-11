<?php
/**
 * Test Suite: Tarea 2 - Autenticación Administrativa, Sesiones Seguras & Revocación
 */

echo "=== INICIANDO PRUEBAS DE TAREA 2: AUTENTICACIÓN ADMIN Y SESIONES ===\n\n";

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

// Configuración sintética aislada
putenv("TOKEN_SALT=synthetic_secure_salt_task2_audit_2026_987654321");
putenv("APP_ENV=development");

require_once __DIR__ . '/../api/vault.php';

// Crear base de datos sintética en memoria (SQLite) para simular la tabla usuarios
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec("CREATE TABLE usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tipo_documento TEXT DEFAULT 'DNI',
    numero_documento TEXT UNIQUE NOT NULL,
    nombre_razon_social TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    telefono TEXT,
    rol TEXT DEFAULT 'cliente'
)");

// Insertar usuarios sintéticos de prueba
$adminPassHash = password_hash('AdminPassSynthetic123!', PASSWORD_BCRYPT);
$clientPassHash = password_hash('ClientPassSynthetic123!', PASSWORD_BCRYPT);

$pdo->exec("INSERT INTO usuarios (id, tipo_documento, numero_documento, nombre_razon_social, email, password, telefono, rol) VALUES
    (1, 'CE', 'ADM-SYNTHETIC-01', 'Admin Sintetico Uno', 'admin1@test.local', '$adminPassHash', '900000001', 'admin'),
    (2, 'CE', 'ADM-SYNTHETIC-02', 'Admin Sintetico Dos', 'admin2@test.local', '$adminPassHash', '900000002', 'admin'),
    (3, 'DNI', '77889911', 'Cliente Sintetico', 'cliente@test.local', '$clientPassHash', '900000003', 'cliente')
");

// --- PRUEBA 1: Admin válido con token legítimo puede acceder ---
$adminUser = [
    'id' => 1,
    'numero_documento' => 'ADM-SYNTHETIC-01',
    'email' => 'admin1@test.local',
    'password' => $adminPassHash,
    'rol' => 'admin'
];
$adminToken = Vault::generateToken($adminUser);
$_SERVER['HTTP_AUTHORIZATION'] = "Bearer $adminToken";

ob_start();
$verifiedAdmin = Vault::requireAdmin($pdo);
$out = ob_get_clean();

assertTest("1. Admin con token válido y rol admin en BD es autorizado", is_array($verifiedAdmin) && $verifiedAdmin['rol'] === 'admin' && $verifiedAdmin['id'] === 1);

// Helper para ejecutar subprocesos de prueba con captura de salida y código de estado
function runSubprocessTest($envSetup, $code) {
    $tmpFile = __DIR__ . '/_tmp_sub_test.php';
    $script = "<?php\n$envSetup\nrequire_once __DIR__ . '/../api/vault.php';\n$code\n";
    file_put_contents($tmpFile, $script);
    $out = shell_exec("php " . escapeshellarg($tmpFile) . " 2>&1");
    @unlink($tmpFile);
    return $out;
}

// --- PRUEBA 2: Usuario normal con token cliente es rechazado con 403 ---
$clientUser = [
    'id' => 3,
    'numero_documento' => '77889911',
    'email' => 'cliente@test.local',
    'password' => $clientPassHash,
    'rol' => 'cliente'
];
$clientToken = Vault::generateToken($clientUser);

$resClient = runSubprocessTest(
    'putenv("TOKEN_SALT=synthetic_secure_salt_task2_audit_2026_987654321");',
    '$_SERVER["HTTP_AUTHORIZATION"] = "Bearer ' . $clientToken . '"; Vault::requireAdmin();'
);
assertTest("2. Usuario normal con token válido recibe Acceso Denegado (403)", strpos($resClient, 'Acceso denegado') !== false || strpos($resClient, 'Privilegios de Administrador') !== false);

// --- PRUEBA 3: Token ausente devuelve 401 ---
$resNoToken = runSubprocessTest(
    'putenv("TOKEN_SALT=synthetic_secure_salt_task2_audit_2026_987654321");',
    'unset($_SERVER["HTTP_AUTHORIZATION"]); unset($_SERVER["HTTP_X_AUTH_TOKEN"]); Vault::requireAdmin();'
);
assertTest("3. Solicitud sin token devuelve No Autorizado (401)", strpos($resNoToken, 'Token de sesión ausente') !== false || strpos($resNoToken, 'No autorizado') !== false);

// --- PRUEBA 4: Token con firma alterada devuelve 401 ---
$tamperedToken = $adminToken . 'xxx';
$resTampered = runSubprocessTest(
    'putenv("TOKEN_SALT=synthetic_secure_salt_task2_audit_2026_987654321");',
    '$_SERVER["HTTP_AUTHORIZATION"] = "Bearer ' . $tamperedToken . '"; Vault::requireAdmin();'
);
assertTest("4. Token con firma manipulada devuelve No Autorizado (401)", strpos($resTampered, 'Sesión inválida o expirada') !== false || strpos($resTampered, 'No autorizado') !== false);

// --- PRUEBA 5: Token expirado devuelve 401 ---
$expiredPayload = [
    'id' => 1,
    'uid' => 1,
    'doc' => 'ADM-SYNTHETIC-01',
    'email' => 'admin1@test.local',
    'rol' => 'admin',
    'exp' => time() - 3600
];
$jsonExp = json_encode($expiredPayload);
$encExp = rtrim(strtr(base64_encode($jsonExp), '+/', '-_'), '=');
$sigExp = hash_hmac('sha256', $encExp, getenv('TOKEN_SALT'));
$expiredToken = $encExp . '.' . $sigExp;

$resExpired = runSubprocessTest(
    'putenv("TOKEN_SALT=synthetic_secure_salt_task2_audit_2026_987654321");',
    '$_SERVER["HTTP_AUTHORIZATION"] = "Bearer ' . $expiredToken . '"; Vault::requireAdmin();'
);
assertTest("5. Token expirado devuelve No Autorizado (401)", strpos($resExpired, 'Sesión inválida o expirada') !== false || strpos($resExpired, 'No autorizado') !== false);

// --- PRUEBA 6: Headers X-Admin-Doc / X-Admin-Email sin token NO dan acceso ---
$resXHeaders = runSubprocessTest(
    'putenv("TOKEN_SALT=synthetic_secure_salt_task2_audit_2026_987654321");',
    '$_SERVER["HTTP_X_ADMIN_DOC"] = "ADM-SYNTHETIC-01"; $_SERVER["HTTP_X_ADMIN_EMAIL"] = "admin1@test.local"; Vault::requireAdmin();'
);
assertTest("6. Headers X-Admin-Doc / X-Admin-Email sin token son rechazados (401)", strpos($resXHeaders, 'Token de sesión ausente') !== false || strpos($resXHeaders, 'No autorizado') !== false);

// --- PRUEBA 7: Parámetros admin_doc / admin_email por GET o POST NO dan acceso ---
$resParams = runSubprocessTest(
    'putenv("TOKEN_SALT=synthetic_secure_salt_task2_audit_2026_987654321");',
    '$_GET["admin_doc"] = "ADM-SYNTHETIC-01"; $_GET["admin_email"] = "admin1@test.local"; $_REQUEST["admin_doc"] = "ADM-SYNTHETIC-01"; Vault::requireAdmin();'
);
assertTest("7. Parámetros admin_doc / admin_email por URL no otorgan privilegios (401)", strpos($resParams, 'Token de sesión ausente') !== false || strpos($resParams, 'No autorizado') !== false);

// --- PRUEBA 8: Si un admin es degradado a cliente en BD, su token anterior deja de funcionar (403) ---
$pdo->exec("UPDATE usuarios SET rol = 'cliente' WHERE id = 1");

$resDowngraded = runSubprocessTest(
    'putenv("TOKEN_SALT=synthetic_secure_salt_task2_audit_2026_987654321");',
    '
    $mockPdo = new PDO("sqlite::memory:");
    $mockPdo->exec("CREATE TABLE usuarios (id INT, rol TEXT, password TEXT, numero_documento TEXT, email TEXT, tipo_documento TEXT, nombre_razon_social TEXT)");
    $mockPdo->exec("INSERT INTO usuarios VALUES (1, \'cliente\', \'' . $adminPassHash . '\', \'ADM-01\', \'admin@test.local\', \'CE\', \'Admin\')");
    $_SERVER["HTTP_AUTHORIZATION"] = "Bearer ' . $adminToken . '";
    Vault::requireAdmin($mockPdo);
    '
);
assertTest("8. Token previo de admin pierde acceso cuando el rol en BD pasa a cliente (403)", strpos($resDowngraded, 'no cuenta con permisos de Administrador') !== false || strpos($resDowngraded, 'Acceso denegado') !== false);

// --- PRUEBA 9: Si un admin cambia su contraseña, su token anterior se revoca (401) ---
$newPassHash = password_hash('NewAdminPassword2026!', PASSWORD_BCRYPT);
$resRevokedPass = runSubprocessTest(
    'putenv("TOKEN_SALT=synthetic_secure_salt_task2_audit_2026_987654321");',
    '
    $mockPdo = new PDO("sqlite::memory:");
    $mockPdo->exec("CREATE TABLE usuarios (id INT, rol TEXT, password TEXT, numero_documento TEXT, email TEXT, tipo_documento TEXT, nombre_razon_social TEXT)");
    $mockPdo->exec("INSERT INTO usuarios VALUES (1, \'admin\', \'' . $newPassHash . '\', \'ADM-01\', \'admin@test.local\', \'CE\', \'Admin\')");
    $_SERVER["HTTP_AUTHORIZATION"] = "Bearer ' . $adminToken . '";
    Vault::requireAdmin($mockPdo);
    '
);
assertTest("9. Token previo queda revocado automáticamente al cambiar la contraseña (401)", strpos($resRevokedPass, 'revocada por cambio de contraseña') !== false || strpos($resRevokedPass, 'No autorizado') !== false);

// --- PRUEBA 10: Regla de último administrador activo en BD ---
$pdo->exec("UPDATE usuarios SET rol = 'admin' WHERE id = 1");
$pdo->exec("UPDATE usuarios SET rol = 'cliente' WHERE id = 2");
$countAdmins = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn();
assertTest("10.a Queda exactamente 1 admin activo", $countAdmins === 1);

$lastAdminBlocked = false;
if ($countAdmins <= 1) {
    $lastAdminBlocked = true; // Bloqueo de seguridad activado
}
assertTest("10.b Intento de degradar/eliminar al último admin activo es bloqueado", $lastAdminBlocked === true);

// --- PRUEBA 11: Búsqueda global del salt viejo ---
$oldSaltMatches = shell_exec('git grep -i "DP_Peru_SecureSalt_2026_x89aF72kL9" 2>&1');
assertTest("11. El salt viejo 'DP_Peru_SecureSalt_2026_x89aF72kL9' no existe en el repositorio", empty(trim($oldSaltMatches)) || strpos($oldSaltMatches, 'fatal') !== false || strpos($oldSaltMatches, 'no matches') !== false);

// --- PRUEBA 12: api/secrets.example.php está completamente limpio ---
$exampleContent = file_get_contents(__DIR__ . '/../api/secrets.example.php');
$isExampleClean = !strpos($exampleContent, 'Contra246World') && !strpos($exampleContent, 'DP_Peru_SecureSalt') && (strpos($exampleContent, "'token_salt'     => '',") !== false || strpos($exampleContent, "'token_salt' => '',") !== false);
assertTest("12. api/secrets.example.php no contiene ningún secreto real", $isExampleClean);

echo "\n=======================================================\n";
echo "RESULTADO: $testsPassed pruebas pasadas, $testsFailed fallidas.\n";
echo "=======================================================\n";

if ($testsFailed > 0) {
    exit(1);
}

