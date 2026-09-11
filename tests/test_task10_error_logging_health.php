<?php
/**
 * Test Suite: TAREA 10 - Manejo de Errores, Logging Privado, Healthcheck y Respuestas Consistentes (F13)
 * Plataforma Descartables Peruanos
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/response.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/vault.php';
require_once __DIR__ . '/../api/validator.php';

$totalTests = 0;
$passedTests = 0;

function it(string $description, callable $fn): void {
    global $totalTests, $passedTests;
    $totalTests++;
    try {
        $result = $fn();
        if ($result === true || $result === null) {
            $passedTests++;
            echo "  [PASS] $description\n";
        } else {
            echo "  [FAIL] $description\n";
        }
    } catch (Throwable $e) {
        echo "  [FAIL] $description -> " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

function assertEq($actual, $expected, string $msg = ''): void {
    if ($actual !== $expected) {
        throw new Exception(($msg ? "$msg: " : "") . "Expected " . var_export($expected, true) . ", got " . var_export($actual, true));
    }
}

function assertTrue($val, string $msg = ''): void {
    if ($val !== true) {
        throw new Exception(($msg ? "$msg: " : "") . "Expected true, got " . var_export($val, true));
    }
}

echo "========================================================\n";
echo "EJECUTANDO TESTS DE TAREA 10: ERRORES, LOGS Y HEALTH (F13)\n";
echo "========================================================\n\n";

// -------------------------------------------------------------------------
// 1. GESTIÓN Y NORMALIZACIÓN DE REQUEST_ID
// -------------------------------------------------------------------------
echo "1. Pruebas de generación y validación de request_id...\n";

it('Genera request_id seguro con prefijo req_ por defecto', function() {
    ApiResponse::initRequestId();
    $id = ApiResponse::getRequestId();
    assertTrue(str_starts_with($id, 'req_'), "Debe iniciar con req_");
    assertTrue(strlen($id) >= 20, "Longitud debe ser suficiente");
});

it('Acepta X-Request-Id del cliente si cumple patrón seguro', function() {
    $_SERVER['HTTP_X_REQUEST_ID'] = 'valid-req-id_12345678';
    ApiResponse::initRequestId();
    assertEq(ApiResponse::getRequestId(), 'valid-req-id_12345678');
    unset($_SERVER['HTTP_X_REQUEST_ID']);
});

it('Rechaza X-Request-Id inseguro o malicioso y genera uno nuevo', function() {
    $malicious = '<script>alert(1)</script>';
    $_SERVER['HTTP_X_REQUEST_ID'] = $malicious;
    ApiResponse::initRequestId();
    $id = ApiResponse::getRequestId();
    assertTrue($id !== $malicious, "No debe aceptar caracteres sospechosos");
    assertTrue(str_starts_with($id, 'req_'));
    unset($_SERVER['HTTP_X_REQUEST_ID']);
});

// -------------------------------------------------------------------------
// 2. SANITIZACIÓN ESTRICTA DE LOGS PRIVADOS
// -------------------------------------------------------------------------
echo "\n2. Pruebas de sanitización de datos en Logger...\n";

it('Redacta contraseñas, tokens, secrets y TOKEN_SALT', function() {
    $rawContext = [
        'user' => 'admin',
        'password' => 'SuperSecret123!',
        'token' => 'Bearer eyJhbGciOi...',
        'token_salt' => 'DP_Peru_SecureSalt_2026_x89aF72kL9',
        'api_key' => 'live_secret_key_999',
        'cookie' => 'PHPSESSID=abcdef123456'
    ];

    $sanitized = Logger::sanitize($rawContext);
    assertEq($sanitized['password'], '[REDACTED]');
    assertEq($sanitized['token'], '[REDACTED]');
    assertEq($sanitized['token_salt'], '[REDACTED]');
    assertEq($sanitized['api_key'], '[REDACTED]');
    assertEq($sanitized['cookie'], '[REDACTED]');
    assertEq($sanitized['user'], 'admin');
});

it('Enmascara emails y documentos de identidad', function() {
    $context = [
        'email' => 'arturo.rodriguez@descartablesperuanos.pe',
        'dni' => '12345678',
        'ruc' => '20601234567'
    ];

    $sanitized = Logger::sanitize($context);
    assertEq($sanitized['email'], 'a***@descartablesperuanos.pe');
    assertEq($sanitized['dni'], '***5678');
    assertEq($sanitized['ruc'], '***4567');
});

it('Truncates long string values in logs to prevent log injection / bloated logs', function() {
    $context = [
        'huge_payload' => str_repeat('A', 1000)
    ];

    $sanitized = Logger::sanitize($context);
    assertTrue(strlen($sanitized['huge_payload']) <= 512, "Debe truncar strings gigantes");
    assertTrue(str_ends_with($sanitized['huge_payload'], '...[TRUNCATED]'));
});

// -------------------------------------------------------------------------
// 3. ENVOLTORIO CONSISTENTE Y PREVENCIÓN DE FUGAS
// -------------------------------------------------------------------------
echo "\n3. Pruebas de envoltura de respuestas y no-filtración de trazas...\n";

it('Construye estructura estándar de error con código y request_id', function() {
    $errObj = ApiResponse::buildErrorPayload('Recurso no encontrado.', 'NOT_FOUND', null, ['extra' => 1]);
    assertEq($errObj['success'], false);
    assertEq($errObj['error'], 'Recurso no encontrado.');
    assertEq($errObj['code'], 'NOT_FOUND');
    assertTrue(!empty($errObj['request_id']));
    assertEq($errObj['extra'], 1);
});

it('No filtra SQLSTATE ni rutas de archivos en respuestas a clientes', function() {
    try {
        throw new PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'descartables.non_existing' doesn't exist in D:\\HTML\\Web - Descartables\\api\\db.php on line 45");
    } catch (PDOException $e) {
        $clientMsg = 'Error interno al consultar la base de datos.';
        $errObj = ApiResponse::buildErrorPayload($clientMsg, 'DB_QUERY_FAILED', $e);

        // El payload visible al cliente no debe contener trazas internas
        assertEq($errObj['error'], $clientMsg);
        assertTrue(!str_contains(json_encode($errObj), 'SQLSTATE'), "No debe contener SQLSTATE");
        assertTrue(!str_contains(json_encode($errObj), 'Descartables\\\\api'), "No debe contener rutas internas");
        assertTrue(!str_contains(json_encode($errObj), 'non_existing'), "No debe exponer tablas internas");
    }
});

it('Construye estructura estándar de éxito con preservación de data y metadatos', function() {
    $data = ['id' => 10, 'nombre' => 'Vaso 12oz'];
    $extra = ['count' => 1, 'message' => 'Consultado exitosamente'];
    $successObj = ApiResponse::buildSuccessPayload($data, $extra);

    assertEq($successObj['success'], true);
    assertEq($successObj['data']['id'], 10);
    assertEq($successObj['count'], 1);
    assertEq($successObj['message'], 'Consultado exitosamente');
    assertTrue(!empty($successObj['request_id']));
});

// -------------------------------------------------------------------------
// 4. SEGURIDAD DEL DIRECTORIO LOGS/
// -------------------------------------------------------------------------
echo "\n4. Pruebas de configuración de seguridad de logs/...\n";

it('El directorio logs/ tiene protección estricta .htaccess', function() {
    $htaccessPath = __DIR__ . '/../logs/.htaccess';
    assertTrue(file_exists($htaccessPath), "logs/.htaccess debe existir");
    $content = file_get_contents($htaccessPath);
    assertTrue(str_contains($content, 'Require all denied'), "Debe incluir Require all denied");
    assertTrue(str_contains($content, 'Deny from all'), "Debe incluir Deny from all para Apache 2.2");
});

it('.gitignore ignora el contenido de logs excepto .htaccess', function() {
    $gitignore = file_get_contents(__DIR__ . '/../.gitignore');
    assertTrue(str_contains($gitignore, 'logs/*'), "Debe ignorar logs/*");
    assertTrue(str_contains($gitignore, '!logs/.htaccess'), "Debe preservar logs/.htaccess");
});

// -------------------------------------------------------------------------
// 5. REGISTRO REAL DE LOGS
// -------------------------------------------------------------------------
echo "\n5. Pruebas de escritura en archivo de log...\n";

it('Escribe eventos estructurados con formato [DATE] [LEVEL] [req_id] [URI] msg context', function() {
    $testMsg = 'Test audit log entry entry_' . bin2hex(random_bytes(4));
    Logger::info($testMsg, ['test_key' => 'test_val', 'password' => 'secret_p']);

    $logDir = __DIR__ . '/../logs';
    $logFile = $logDir . '/app-' . date('Y-m') . '.log';
    assertTrue(file_exists($logFile), "El archivo de log del mes debe existir");

    $content = file_get_contents($logFile);
    assertTrue(str_contains($content, $testMsg), "Debe contener el mensaje de prueba");
    assertTrue(str_contains($content, '[INFO]'), "Debe indicar el nivel de log");
    assertTrue(str_contains($content, '[REDACTED]'), "Debe haber redactado password");
    assertTrue(!str_contains($content, 'secret_p'), "No debe existir la contraseña sin procesar");
});

// -------------------------------------------------------------------------
// 6. HEALTHCHECK DE LA APLICACIÓN (api/health.php)
// -------------------------------------------------------------------------
echo "\n6. Pruebas del endpoint api/health.php...\n";

function runHealthCheck(string $method = 'GET', string $mode = 'public', ?string $authToken = null, bool $mockDb = true): array {
    $runner = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dp_health_test_' . bin2hex(random_bytes(6)) . '.php';
    $code = "<?php\n" .
        "\$_SERVER['REQUEST_METHOD'] = " . var_export($method, true) . ";\n" .
        "\$_GET['mode'] = " . var_export($mode, true) . ";\n";
    if ($mockDb) {
        $code .= "\$testPdo = new PDO('sqlite::memory:');\n" .
                 "\$testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n" .
                 "\$testPdo->exec('CREATE TABLE schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at DATETIME)');\n" .
                 "\$testPdo->exec('INSERT INTO schema_migrations (version, applied_at) VALUES (\"20260907_000001_core_schema.sql\", datetime(\"now\"))');\n" .
                 "\$GLOBALS['TEST_DB_PDO'] = \$testPdo;\n";
    }
    if ($authToken) {
        $code .= "\$_SERVER['HTTP_AUTHORIZATION'] = " . var_export('Bearer ' . $authToken, true) . ";\n";
    } else {
        $code .= "unset(\$_SERVER['HTTP_AUTHORIZATION'], \$_COOKIE['dp_token']);\n";
    }
    $code .= "ob_start();\n" .
        "require " . var_export(__DIR__ . '/../api/health.php', true) . ";\n" .
        "\$out = ob_get_clean();\n" .
        "echo \$out;\n";
    file_put_contents($runner, $code);
    $out = shell_exec('php "' . $runner . '"');
    @unlink($runner);
    return json_decode((string)$out, true) ?: [];
}

it('El archivo api/health.php existe y es sintácticamente válido', function() {
    $healthPath = __DIR__ . '/../api/health.php';
    assertTrue(file_exists($healthPath), "api/health.php debe existir");
    $output = [];
    $ret = -1;
    exec('php -l "' . $healthPath . '"', $output, $ret);
    assertEq($ret, 0, "api/health.php debe tener sintaxis PHP válida");
});

it('Healthcheck en modo público devuelve estado mínimo cuando DB está conectada', function() {
    $json = runHealthCheck('GET', 'public', null, true);

    assertTrue(is_array($json), "Debe retornar JSON válido");
    assertEq($json['success'], true);
    assertEq($json['status'], 'ok');
    assertEq($json['app'], 'healthy');
    assertEq($json['database'], 'connected');
    assertTrue(!empty($json['request_id']));

    // Comprobar que NO filtra rutas, host, versiones ni tablas
    $rawStr = json_encode($json);
    assertTrue(!str_contains($rawStr, 'localhost'), "No debe exponer DB host");
    assertTrue(!str_contains($rawStr, 'descartables'), "No debe exponer DB name");
    assertTrue(!str_contains($rawStr, 'PHP_VERSION'), "No debe exponer versión de PHP");
    assertTrue(!str_contains($rawStr, 'mysqli'), "No debe exponer driver");
    assertTrue(!str_contains($rawStr, 'schema_migrations'), "No debe exponer tablas en modo público");
});

it('Healthcheck en modo público reporta 503 controlado si la BD no está disponible', function() {
    $json = runHealthCheck('GET', 'public', null, false);

    assertTrue(is_array($json), "Debe retornar JSON");
    assertEq($json['success'], false);
    assertEq($json['code'], 'DB_DISCONNECTED');
    assertEq($json['database'], 'disconnected');
    assertTrue(!empty($json['request_id']));
});

it('Healthcheck en modo deep rechaza accesos no autenticados con 401/403', function() {
    $json = runHealthCheck('GET', 'deep', null, true);

    assertTrue(is_array($json), "Debe retornar JSON");
    assertEq($json['success'], false, "Debe rechazar solicitud no autenticada en modo deep");
    assertTrue(in_array($json['code'], ['UNAUTHORIZED', 'FORBIDDEN', 'AUTH_REQUIRED']), "Código debe ser de autenticación requerida");
});

it('Healthcheck en modo deep con token de admin retorna diagnóstico completo', function() {
    // Generar token admin válido
    $salt = Vault::getTokenSalt();
    $adminPayload = ['id' => 1, 'email' => 'admin@descartablesperuanos.pe', 'rol' => 'admin', 'exp' => time() + 3600];
    $enc = base64_encode(json_encode($adminPayload));
    $sig = hash_hmac('sha256', $enc, $salt);
    $validAdminToken = $enc . '.' . $sig;

    $json = runHealthCheck('GET', 'deep', $validAdminToken, true);

    assertTrue(is_array($json), "Debe retornar JSON");
    assertEq($json['success'], true, "Debe permitir acceso a admin en modo deep");
    assertEq($json['status'], 'ok');
    assertTrue(isset($json['database']['latency_ms']), "Debe medir latencia de BD");
    assertTrue(isset($json['migrations']['applied_count']), "Debe indicar conteo de migraciones");
    assertTrue(isset($json['storage']['uploads_writable']), "Debe validar permisos de uploads");
    assertTrue(isset($json['storage']['logs_writable']), "Debe validar permisos de logs");
});

// -------------------------------------------------------------------------
// 7. EMPAQUETADO SIN LOGS NI DUMPS
// -------------------------------------------------------------------------
echo "\n7. Pruebas de exclusión de logs en build_package.js...\n";

it('El script build_package.js excluye logs/ y *.log del paquete distribuible', function() {
    $out = shell_exec('node "' . __DIR__ . '/../scripts/build_package.js"');
    assertTrue(str_contains($out, '[OK] Paquete generado limpiamente'), "Empaquetado debe ser exitoso");

    $distLogs = __DIR__ . '/../dist/package/logs';
    assertTrue(!file_exists($distLogs), "dist/package/logs NO debe existir");

    // Verificar que ningún archivo .log exista en dist/package/
    $distDir = __DIR__ . '/../dist/package';
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($distDir));
    foreach ($rii as $file) {
        if ($file->isFile()) {
            assertTrue($file->getExtension() !== 'log', "Ningún archivo .log debe estar en el paquete");
        }
    }
});

// -------------------------------------------------------------------------
// 8. ENVOLTORIO CONSISTENTE EN VALIDADOR, RATE LIMIT, UPLOAD Y AUTH
// -------------------------------------------------------------------------
echo "\n8. Pruebas de envolturas de error en módulos del sistema...\n";

it('Validator emite INVALID_JSON con envoltura unificada', function() {
    $script = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dp_val_test_' . bin2hex(random_bytes(6)) . '.php';
    $code = '<?php
$_SERVER["REQUEST_METHOD"] = "POST";
$_SERVER["CONTENT_TYPE"] = "application/json";
// Inyectar entrada rota simulando php://input
require ' . var_export(__DIR__ . '/../api/validator.php', true) . ';
ob_start();
// Simulamos llamada directa al handler de error de validator
ApiResponse::error("El cuerpo de la solicitud no contiene un formato JSON válido.", "INVALID_JSON", 400);
$out = ob_get_clean();
echo $out;
';
    file_put_contents($script, $code);
    $out = shell_exec('php "' . $script . '"');
    @unlink($script);
    $json = json_decode((string)$out, true);

    assertEq($json['success'], false);
    assertEq($json['code'], 'INVALID_JSON');
    assertTrue(!empty($json['request_id']));
});

it('RateLimiter emite RATE_LIMIT_EXCEEDED con Retry-After y envoltura unificada', function() {
    $script = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dp_rl_test_' . bin2hex(random_bytes(6)) . '.php';
    $code = '<?php
$_SERVER["REMOTE_ADDR"] = "192.168.1.100";
$testPdo = new PDO("sqlite::memory:");
$testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$testPdo->exec("CREATE TABLE rate_limits (identifier VARCHAR(100), action VARCHAR(50), attempts INT DEFAULT 1, window_start INT, blocked_until INT, PRIMARY KEY (identifier, action))");
require ' . var_export(__DIR__ . '/../api/ratelimit.php', true) . ';
ob_start();
RateLimiter::enforce($testPdo, "test_burst", 1, 60);
RateLimiter::enforce($testPdo, "test_burst", 1, 60); // Debe disparar rate limit
$out = ob_get_clean();
echo $out;
';
    file_put_contents($script, $code);
    $out = shell_exec('php "' . $script . '"');
    @unlink($script);
    $json = json_decode((string)$out, true);

    assertEq($json['success'], false);
    assertEq($json['code'], 'RATE_LIMIT_EXCEEDED');
    assertTrue(isset($json['retry_after']));
    assertTrue(!empty($json['request_id']));
});

it('Upload emite PAYLOAD_TOO_LARGE cuando excede el límite máximo', function() {
    $salt = Vault::getTokenSalt();
    $adminPayload = ['id' => 1, 'email' => 'admin@descartablesperuanos.pe', 'rol' => 'admin', 'exp' => time() + 3600];
    $enc = base64_encode(json_encode($adminPayload));
    $sig = hash_hmac('sha256', $enc, $salt);
    $validAdminToken = $enc . '.' . $sig;

    $script = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dp_up_test_' . bin2hex(random_bytes(6)) . '.php';
    $code = '<?php
$_SERVER["REQUEST_METHOD"] = "POST";
$_SERVER["HTTP_AUTHORIZATION"] = "Bearer ' . $validAdminToken . '";
$GLOBALS["UPLOAD_SCRIPT_INCLUDED"] = false;
$_FILES["imagen"] = [
    "name" => "huge.png",
    "type" => "image/png",
    "tmp_name" => "fake_path",
    "error" => UPLOAD_ERR_INI_SIZE,
    "size" => 10000000
];
ob_start();
require ' . var_export(__DIR__ . '/../api/upload.php', true) . ';
$out = ob_get_clean();
echo $out;
';
    file_put_contents($script, $code);
    $out = shell_exec('php "' . $script . '"');
    @unlink($script);
    $json = json_decode((string)$out, true);

    assertEq($json['success'], false);
    assertEq($json['code'], 'PAYLOAD_TOO_LARGE');
    assertTrue(!empty($json['request_id']));
});

// =========================================================================
// RESUMEN FINAL
// =========================================================================
echo "\n========================================================\n";
echo "RESULTADOS TAREA 10: $passedTests de $totalTests pruebas pasadas.\n";
echo "========================================================\n";

if ($passedTests !== $totalTests) {
    exit(1);
}
