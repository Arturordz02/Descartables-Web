<?php
/**
 * ====================================================================
 * TEST SUITE: TAREA 11 - BACKUPS, RESTAURACIÓN Y RECUPERACIÓN REAL (F14)
 * Plataforma Descartables Peruanos
 * ====================================================================
 */

declare(strict_types=1);

putenv("TOKEN_SALT=secure_test_salt_task11_backup_2026_audit_xyz");
putenv("APP_ENV=development");

require_once __DIR__ . '/../api/vault.php';
require_once __DIR__ . '/../api/response.php';
require_once __DIR__ . '/../api/backup_engine.php';

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
echo "EJECUTANDO TESTS DE TAREA 11: BACKUPS Y RESTAURACIÓN (F14)\n";
echo "========================================================\n\n";

// Helper para crear una base de datos SQLite sintética con el esquema canónico completo en archivo o memoria
function createSyntheticDatabase(?string $filePath = null): PDO {
    $dsn = $filePath ? "sqlite:$filePath" : 'sqlite::memory:';
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 1. schema_migrations
    $pdo->exec("CREATE TABLE schema_migrations (
        version VARCHAR(100) PRIMARY KEY,
        applied_at DATETIME
    )");
    $pdo->exec("INSERT INTO schema_migrations (version, applied_at) VALUES ('001_initial_schema.sql', datetime('now'))");
    $pdo->exec("INSERT INTO schema_migrations (version, applied_at) VALUES ('002_add_auth_session_fields.sql', datetime('now'))");

    // 2. secuencias
    $pdo->exec("CREATE TABLE secuencias (
        tipo VARCHAR(50) NOT NULL,
        anio INT NOT NULL,
        ultimo_correlativo INT NOT NULL DEFAULT 0,
        actualizado_en DATETIME,
        PRIMARY KEY (tipo, anio)
    )");
    $pdo->exec("INSERT INTO secuencias (tipo, anio, ultimo_correlativo) VALUES ('COTIZACION', 2026, 15)");
    $pdo->exec("INSERT INTO secuencias (tipo, anio, ultimo_correlativo) VALUES ('RECLAMACION', 2026, 8)");

    // 3. configuracion
    $pdo->exec("CREATE TABLE configuracion (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        clave VARCHAR(100) UNIQUE NOT NULL,
        valor TEXT NOT NULL,
        updated_at DATETIME
    )");
    $pdo->exec("INSERT INTO configuracion (clave, valor) VALUES ('empresa_ruc', '20601234567')");
    $pdo->exec("INSERT INTO configuracion (clave, valor) VALUES ('empresa_razon_social', 'DESCARTABLES PERUANOS S.A.C.')");
    $pdo->exec("INSERT INTO configuracion (clave, valor) VALUES ('telefono_ventas', '+51 900 000 000')");

    // 4. categorias
    $pdo->exec("CREATE TABLE categorias (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre VARCHAR(100) NOT NULL,
        slug VARCHAR(100) UNIQUE NOT NULL,
        icono VARCHAR(100),
        color VARCHAR(50),
        orden INT DEFAULT 0,
        activo INT DEFAULT 1,
        created_at DATETIME
    )");
    $pdo->exec("INSERT INTO categorias (id, nombre, slug, icono, color) VALUES (1, 'Envases Térmicos', 'envases-termicos', 'package', 'bg-amber-50')");
    $pdo->exec("INSERT INTO categorias (id, nombre, slug, icono, color) VALUES (2, 'Biodegradables', 'biodegradables', 'leaf', 'bg-emerald-50')");

    // 5. usuarios
    $pdo->exec("CREATE TABLE usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tipo_documento VARCHAR(20) DEFAULT 'DNI',
        numero_documento VARCHAR(30) UNIQUE NOT NULL,
        nombre_razon_social VARCHAR(150) NOT NULL,
        email VARCHAR(150) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        telefono VARCHAR(30),
        departamento VARCHAR(100) DEFAULT 'Lima',
        provincia VARCHAR(100) DEFAULT 'Lima',
        distrito VARCHAR(100),
        direccion TEXT,
        rol VARCHAR(20) DEFAULT 'cliente',
        activo INT DEFAULT 1,
        creado_en DATETIME,
        actualizado_en DATETIME
    )");
    $adminHash = password_hash('AdminPass2026!', PASSWORD_BCRYPT);
    $clientHash = password_hash('ClientePass2026!', PASSWORD_BCRYPT);
    $pdo->exec("INSERT INTO usuarios (id, tipo_documento, numero_documento, nombre_razon_social, email, password, rol) VALUES (1, 'DNI', '10000001', 'Administrador General', 'admin@descartables.pe', '$adminHash', 'admin')");
    $pdo->exec("INSERT INTO usuarios (id, tipo_documento, numero_documento, nombre_razon_social, email, password, rol) VALUES (2, 'DNI', '20000002', 'Cliente Mayorista SAC', 'cliente@empresa.pe', '$clientHash', 'cliente')");

    // 6. productos
    $pdo->exec("CREATE TABLE productos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sku VARCHAR(50) UNIQUE NOT NULL,
        nombre VARCHAR(200) NOT NULL,
        categoria_id INT,
        precio DECIMAL(10,2) NOT NULL,
        unidad VARCHAR(50) DEFAULT 'Millar',
        descripcion TEXT,
        especificaciones TEXT,
        imagen_url VARCHAR(255),
        biodegradable INT DEFAULT 0,
        stock_estado VARCHAR(20) DEFAULT 'disponible',
        destacado INT DEFAULT 0,
        activo INT DEFAULT 1,
        created_at DATETIME
    )");
    $pdo->exec("INSERT INTO productos (id, sku, nombre, categoria_id, precio, imagen_url) VALUES (1, 'TERM-CT4', 'Contenedor Térmico CT-4', 1, 145.50, 'assets/images/productos/default.png')");
    $pdo->exec("INSERT INTO productos (id, sku, nombre, categoria_id, precio, imagen_url) VALUES (2, 'BIO-BOWL', 'Bowl de Bagazo de Caña', 2, 180.00, 'assets/images/productos/default.png')");

    // 7. cotizaciones
    $pdo->exec("CREATE TABLE cotizaciones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        codigo_cotizacion VARCHAR(50) UNIQUE NOT NULL,
        usuario_id INT,
        tipo_comprobante VARCHAR(20) DEFAULT 'Factura',
        nombre_cliente VARCHAR(150),
        documento VARCHAR(30),
        telefono VARCHAR(30),
        email VARCHAR(150),
        departamento VARCHAR(100),
        provincia VARCHAR(100),
        distrito VARCHAR(100),
        direccion TEXT,
        agencia_envio VARCHAR(100),
        items TEXT,
        subtotal DECIMAL(10,2),
        igv DECIMAL(10,2),
        total DECIMAL(10,2),
        estado VARCHAR(30) DEFAULT 'Pendiente',
        creado_en DATETIME
    )");
    $pdo->exec("INSERT INTO cotizaciones (codigo_cotizacion, usuario_id, nombre_cliente, documento, items, total) VALUES ('COT-2026-00015', 2, 'Cliente Mayorista SAC', '20000002', '[{\"sku\":\"TERM-CT4\",\"cantidad\":10,\"precio\":145.50}]', 1455.00)");

    // 8. libro_reclamaciones
    $pdo->exec("CREATE TABLE libro_reclamaciones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        codigo_hoja VARCHAR(50) UNIQUE NOT NULL,
        tipo_documento VARCHAR(20),
        numero_documento VARCHAR(30),
        nombre_completo VARCHAR(150),
        telefono VARCHAR(30),
        email VARCHAR(150),
        tipo_bien VARCHAR(20) DEFAULT 'Producto',
        tipo_reclamacion VARCHAR(20) DEFAULT 'Reclamo',
        detalle_reclamacion TEXT,
        pedido_consumidor TEXT,
        estado VARCHAR(30) DEFAULT 'Pendiente',
        creado_en DATETIME
    )");
    $pdo->exec("INSERT INTO libro_reclamaciones (codigo_hoja, tipo_documento, numero_documento, nombre_completo, tipo_reclamacion, detalle_reclamacion, estado) VALUES ('REC-2026-00008', 'DNI', '20000002', 'Cliente Mayorista SAC', 'Reclamo', 'Envase con retraso', 'Pendiente')");

    // 9. rate_limits (temporal/operativa)
    $pdo->exec("CREATE TABLE rate_limits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        action VARCHAR(64) NOT NULL,
        identifier VARCHAR(128) NOT NULL,
        attempts INT NOT NULL DEFAULT 1,
        first_attempt INT NOT NULL,
        last_attempt INT NOT NULL,
        blocked_until INT NULL
    )");
    $pdo->exec("INSERT INTO rate_limits (action, identifier, attempts, first_attempt, last_attempt) VALUES ('login', '192.168.1.50', 3, strftime('%s','now'), strftime('%s','now'))");

    return $pdo;
}

// Función para invocar el endpoint api/backup.php en proceso PHP aislado usando archivo SQLite temporal
function runBackupEndpoint(string $method = 'GET', array $queryParams = [], ?string $authToken = null, ?string $sqliteDbFile = null): array {
    $runner = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dp_test_backup_' . bin2hex(random_bytes(6)) . '.php';
    $code = "<?php\n" .
        "putenv('TOKEN_SALT=secure_test_salt_task11_backup_2026_audit_xyz');\n" .
        "putenv('APP_ENV=development');\n" .
        "\$_SERVER['REQUEST_METHOD'] = " . var_export($method, true) . ";\n" .
        "\$_GET = " . var_export($queryParams, true) . ";\n";

    if ($sqliteDbFile) {
        $escapedSqlite = str_replace('\\', '/', $sqliteDbFile);
        $code .= "\$testPdo = new PDO('sqlite:" . $escapedSqlite . "');\n" .
                 "\$testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n" .
                 "\$testPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);\n" .
                 "\$GLOBALS['TEST_DB_PDO'] = \$testPdo;\n";
    }

    if ($authToken) {
        $code .= "\$_SERVER['HTTP_AUTHORIZATION'] = " . var_export('Bearer ' . $authToken, true) . ";\n";
    } else {
        $code .= "unset(\$_SERVER['HTTP_AUTHORIZATION'], \$_COOKIE['dp_token']);\n";
    }

    $code .= "ob_start();\n" .
        "require " . var_export(__DIR__ . '/../api/backup.php', true) . ";\n" .
        "\$out = ob_get_clean();\n" .
        "echo \$out;\n";

    file_put_contents($runner, $code);
    $out = shell_exec('php "' . $runner . '"');
    @unlink($runner);
    return json_decode((string)$out, true) ?: ['raw' => $out];
}

// =========================================================================
// 1. PRUEBAS DE SEGURIDAD Y CONTROL DE ACCESO
// =========================================================================
echo "1. Pruebas de control de acceso en api/backup.php...\n";

$tempSqliteFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dp_test_db_' . bin2hex(random_bytes(6)) . '.db';
$db = createSyntheticDatabase($tempSqliteFile);

// Crear tokens con el salt de prueba
$adminUser = $db->query("SELECT * FROM usuarios WHERE id = 1")->fetch();
$clientUser = $db->query("SELECT * FROM usuarios WHERE id = 2")->fetch();

$adminToken = Vault::generateToken($adminUser);
$clientToken = Vault::generateToken($clientUser);

it('Usuario no autenticado no puede generar backup (HTTP 401)', function() use ($tempSqliteFile) {
    $res = runBackupEndpoint('GET', ['type' => 'export'], null, $tempSqliteFile);

    assertEq($res['success'], false);
    assertEq($res['code'], 'AUTH_REQUIRED');
});

it('Usuario normal con rol cliente no puede generar backup (HTTP 403)', function() use ($tempSqliteFile, $clientToken) {
    $res = runBackupEndpoint('GET', ['type' => 'export'], $clientToken, $tempSqliteFile);

    assertEq($res['success'], false);
    assertEq($res['code'], 'FORBIDDEN');
});

it('Administrador válido sí puede generar export/backup', function() use ($tempSqliteFile, $adminToken) {
    $res = runBackupEndpoint('GET', ['type' => 'export'], $adminToken, $tempSqliteFile);

    assertEq($res['success'], true);
    assertTrue(isset($res['data']['_metadata']));
    assertEq($res['data']['_metadata']['tipo_operacion'], BackupEngine::BACKUP_TYPE_EXPORT);
    assertTrue(isset($res['data']['productos']));
});

// =========================================================================
// 2. PRUEBA DE POLÍTICA FAIL-FAST (NO TRAGARSE ERRORES)
// =========================================================================
echo "\n2. Pruebas de detección de errores (Fail-Fast)...\n";

it('Error de una tabla provoca fallo visible con HTTP 500, no backup exitoso', function() use ($db, $tempSqliteFile, $adminToken) {
    // Simular error destruyendo una tabla obligatoria
    $db->exec("DROP TABLE cotizaciones");

    $res = runBackupEndpoint('GET', ['type' => 'export'], $adminToken, $tempSqliteFile);

    assertEq($res['success'], false, "Debe reportar fallo si falta una tabla");
    assertEq($res['code'], 'BACKUP_TABLE_EXPORT_FAILED');
    assertTrue(!empty($res['error']));
});

// Restaurar tabla cotizaciones para siguientes pruebas
$db->exec("CREATE TABLE cotizaciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    codigo_cotizacion VARCHAR(50) UNIQUE NOT NULL,
    usuario_id INT,
    tipo_comprobante VARCHAR(20) DEFAULT 'Factura',
    nombre_cliente VARCHAR(150),
    documento VARCHAR(30),
    telefono VARCHAR(30),
    email VARCHAR(150),
    departamento VARCHAR(100),
    provincia VARCHAR(100),
    distrito VARCHAR(100),
    direccion TEXT,
    agencia_envio VARCHAR(100),
    items TEXT,
    subtotal DECIMAL(10,2),
    igv DECIMAL(10,2),
    total DECIMAL(10,2),
    estado VARCHAR(30) DEFAULT 'Pendiente',
    creado_en DATETIME
)");
$db->exec("INSERT INTO cotizaciones (codigo_cotizacion, usuario_id, nombre_cliente, documento, items, total) VALUES ('COT-2026-00015', 2, 'Cliente Mayorista SAC', '20000002', '[{\"sku\":\"TERM-CT4\",\"cantidad\":10,\"precio\":145.50}]', 1455.00)");

// =========================================================================
// 3. GENERACIÓN DE PAQUETE DISASTER RECOVERY (.ZIP) Y EXCLUSIONES
// =========================================================================
echo "\n3. Pruebas de empaquetado de Disaster Recovery (BackupEngine)...\n";

$tempBackupDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dp_test_backups_' . bin2hex(random_bytes(4));
mkdir($tempBackupDir, 0750, true);

$backupResult = BackupEngine::createDisasterRecoveryArchive($db, $tempBackupDir);

it('Backup no contiene secrets.php, .env, TOKEN_SALT, logs, .git ni dist', function() use ($backupResult) {
    $zip = new ZipArchive();
    $openRes = $zip->open($backupResult['archive_path']);
    assertTrue($openRes === true, "Debe abrir archivo ZIP");

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        assertTrue(!str_contains($name, 'secrets.php'), "No debe incluir secrets.php");
        assertTrue(!str_contains($name, '.env'), "No debe incluir .env");
        assertTrue(!str_contains($name, 'logs/'), "No debe incluir logs/");
        assertTrue(!str_contains($name, '.git'), "No debe incluir .git");
        assertTrue(!str_contains($name, 'dist/'), "No debe incluir dist/");

        // Verificar que el contenido de ningún archivo incluya TOKEN_SALT ni passwords reales
        if (str_ends_with($name, '.json') || str_ends_with($name, '.sql')) {
            $content = $zip->getFromIndex($i);
            assertTrue(!str_contains($content, 'TOKEN_SALT'), "No debe contener texto TOKEN_SALT");
            assertTrue(!str_contains($content, 'Contra246World'), "No debe contener contraseñas por defecto");
        }
    }
    $zip->close();
});

it('Backup incluye manifest.json', function() use ($backupResult) {
    $zip = new ZipArchive();
    $zip->open($backupResult['archive_path']);
    $manifestRaw = $zip->getFromName('manifest.json');
    $zip->close();

    assertTrue($manifestRaw !== false, "manifest.json debe existir en el ZIP");
    $manifest = json_decode($manifestRaw, true);
    assertEq($manifest['manifest_version'], '2.0');
    assertEq($manifest['backup_type'], 'full_disaster_recovery');
});

it('Manifest contiene conteos de filas por tabla y excluye rate_limits por política', function() use ($backupResult) {
    $manifest = $backupResult['manifest'];

    assertTrue(isset($manifest['tablas']['usuarios']));
    assertEq($manifest['tablas']['usuarios']['row_count'], 2);
    assertEq($manifest['tablas']['productos']['row_count'], 2);
    assertEq($manifest['tablas']['cotizaciones']['row_count'], 1);
    assertEq($manifest['tablas']['libro_reclamaciones']['row_count'], 1);

    // Verificar exclusión de rate_limits por política
    assertTrue(!isset($manifest['tablas']['rate_limits']), "rate_limits no debe estar en tablas a restaurar");
    assertTrue(isset($manifest['tablas_excluidas_politica']['rate_limits']), "rate_limits debe estar documentada como excluida por política");
});

it('Manifest contiene checksums SHA-256 de archivos incluidos', function() use ($backupResult) {
    $manifest = $backupResult['manifest'];

    assertEq($manifest['checksum_algoritmo'], 'sha256');
    assertTrue(!empty($manifest['checksums']['data_sql']));
    assertEq(strlen($manifest['checksums']['data_sql']), 64);
    assertTrue(!empty($manifest['checksums']['data_json']));
    assertEq(strlen($manifest['checksums']['data_json']), 64);

    foreach ($manifest['tablas'] as $tbl => $info) {
        assertEq(strlen($info['sha256']), 64, "Hash de tabla $tbl debe ser SHA-256 (64 hex)");
    }
});

it('Backup incluye migraciones y esquema canónico necesarios', function() use ($backupResult) {
    $zip = new ZipArchive();
    $zip->open($backupResult['archive_path']);

    $schema = $zip->getFromName('database/schema.sql');
    assertTrue($schema !== false, "database/schema.sql debe existir en el ZIP");
    assertTrue(str_contains($schema, 'CREATE TABLE'), "schema.sql debe tener DDL");

    $migration001 = $zip->getFromName('database/migrations/001_initial_schema.sql');
    assertTrue($migration001 !== false, "Migración 001 debe existir en el ZIP");
    $zip->close();
});

it('Backup incluye imágenes persistentes gestionadas necesarias', function() use ($backupResult) {
    $manifest = $backupResult['manifest'];
    $zip = new ZipArchive();
    $zip->open($backupResult['archive_path']);

    $defaultImg = $zip->getFromName('storage/productos/default.png');
    assertTrue($defaultImg !== false, "storage/productos/default.png debe estar empaquetado");

    $zip->close();
    assertTrue($manifest['archivos_persistentes']['total_archivos'] > 0);
});

// =========================================================================
// 4. PRUEBAS DE RESTAURACIÓN Y VERIFICACIÓN DE INTEGRIDAD
// =========================================================================
echo "\n4. Pruebas de restauración y verificación de integridad (scripts/restore.php)...\n";

it('Restore verifica checksums (modo verify-only)', function() use ($backupResult) {
    $verify = BackupEngine::verifyArchive($backupResult['archive_path']);
    assertTrue($verify['verified'], "Verificación de archivo íntegro debe retornar true");
});

it('Restore en entorno limpio recupera tablas principales con --force', function() use ($backupResult) {
    // Base de datos limpia de destino (SQLite en memoria)
    $cleanDb = new PDO('sqlite::memory:');
    $cleanDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $cleanDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Crear tablas canónicas vacías en la BD de destino
    $cleanDb->exec("CREATE TABLE schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at DATETIME)");
    $cleanDb->exec("CREATE TABLE secuencias (tipo VARCHAR(50), anio INT, ultimo_correlativo INT, actualizado_en DATETIME, PRIMARY KEY (tipo, anio))");
    $cleanDb->exec("CREATE TABLE configuracion (id INTEGER PRIMARY KEY, clave VARCHAR(100), valor TEXT, updated_at DATETIME)");
    $cleanDb->exec("CREATE TABLE categorias (id INTEGER PRIMARY KEY, nombre VARCHAR(100), slug VARCHAR(100), icono VARCHAR(100), color VARCHAR(50), orden INT, activo INT, created_at DATETIME)");
    $cleanDb->exec("CREATE TABLE productos (id INTEGER PRIMARY KEY, sku VARCHAR(50), nombre VARCHAR(200), categoria_id INT, precio DECIMAL(10,2), unidad VARCHAR(50), descripcion TEXT, especificaciones TEXT, imagen_url VARCHAR(255), biodegradable INT, stock_estado VARCHAR(20), destacado INT, activo INT, created_at DATETIME)");
    $cleanDb->exec("CREATE TABLE usuarios (id INTEGER PRIMARY KEY, tipo_documento VARCHAR(20), numero_documento VARCHAR(30), nombre_razon_social VARCHAR(150), email VARCHAR(150), password VARCHAR(255), telefono VARCHAR(30), departamento VARCHAR(100), provincia VARCHAR(100), distrito VARCHAR(100), direccion TEXT, rol VARCHAR(20), activo INT, creado_en DATETIME, actualizado_en DATETIME)");
    $cleanDb->exec("CREATE TABLE cotizaciones (id INTEGER PRIMARY KEY, codigo_cotizacion VARCHAR(50), usuario_id INT, tipo_comprobante VARCHAR(20), nombre_cliente VARCHAR(150), documento VARCHAR(30), telefono VARCHAR(30), email VARCHAR(150), departamento VARCHAR(100), provincia VARCHAR(100), distrito VARCHAR(100), direccion TEXT, agencia_envio VARCHAR(100), items TEXT, subtotal DECIMAL(10,2), igv DECIMAL(10,2), total DECIMAL(10,2), estado VARCHAR(30), creado_en DATETIME)");
    $cleanDb->exec("CREATE TABLE libro_reclamaciones (id INTEGER PRIMARY KEY, codigo_hoja VARCHAR(50), tipo_documento VARCHAR(20), numero_documento VARCHAR(30), nombre_completo VARCHAR(150), telefono VARCHAR(30), email VARCHAR(150), tipo_bien VARCHAR(20), tipo_reclamacion VARCHAR(20), detalle_reclamacion TEXT, pedido_consumidor TEXT, estado VARCHAR(30), creado_en DATETIME)");
    $cleanDb->exec("CREATE TABLE rate_limits (id INTEGER PRIMARY KEY, action VARCHAR(64), identifier VARCHAR(128), attempts INT, first_attempt INT, last_attempt INT, blocked_until INT)");

    // Ejecutar restauración forzada
    $res = BackupEngine::restoreArchive($backupResult['archive_path'], $cleanDb, [
        'force'         => true,
        'restore_files' => false
    ]);

    assertEq($res['success'], true);
    assertEq($res['tablas_restauradas']['usuarios'], 2);
    assertEq($res['tablas_restauradas']['productos'], 2);
    assertEq($res['tablas_restauradas']['cotizaciones'], 1);
    assertEq($res['tablas_restauradas']['libro_reclamaciones'], 1);

    // Verificar en la BD limpia que los registros existen
    $stmtUsers = $cleanDb->query("SELECT COUNT(*) FROM usuarios");
    assertEq((int)$stmtUsers->fetchColumn(), 2);

    $stmtProd = $cleanDb->query("SELECT COUNT(*) FROM productos");
    assertEq((int)$stmtProd->fetchColumn(), 2);

    // Verificar que rate_limits está vacía por política
    $stmtRL = $cleanDb->query("SELECT COUNT(*) FROM rate_limits");
    assertEq((int)$stmtRL->fetchColumn(), 0);
});

it('Restore detecta backup corrupto y aborta con error de checksum', function() use ($backupResult, $tempBackupDir) {
    // Clonar el backup original
    $corruptPath = $tempBackupDir . DIRECTORY_SEPARATOR . 'corrupt_' . basename($backupResult['archive_path']);
    copy($backupResult['archive_path'], $corruptPath);

    // Corromper intencionalmente database/data.sql
    $zip = new ZipArchive();
    $zip->open($corruptPath);
    $zip->addFromString('database/data.sql', '-- CORRUPTED DATA CONTENT');
    $zip->close();

    $threw = false;
    try {
        BackupEngine::verifyArchive($corruptPath);
    } catch (Throwable $e) {
        $threw = true;
        assertTrue(str_contains($e->getMessage(), 'CORRUPTED_BACKUP_CHECKSUM_MISMATCH'), "Debe fallar por checksum mismatch");
    }

    assertTrue($threw, "Debe lanzar excepción ante backup alterado");
    @unlink($corruptPath);
});

// =========================================================================
// 5. PRUEBAS DE EMPAQUETADO Y AUDITORÍA DE SEGURIDAD (BUILD_PACKAGE.JS)
// =========================================================================
echo "\n5. Pruebas de exclusión en build_package.js...\n";

it('build_package excluye backups/ y *.zip generados en dist/package/', function() {
    $script = __DIR__ . '/../scripts/build_package.js';
    $output = [];
    $returnCode = -1;
    exec("node \"$script\"", $output, $returnCode);

    assertEq($returnCode, 0, "build_package.js debe completarse exitosamente");

    $distPackage = __DIR__ . '/../dist/package';
    assertTrue(!file_exists($distPackage . '/backups'), "dist/package/ no debe contener backups/");

    // Verificar que no haya archivos .zip en dist/package/
    $hasZip = false;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($distPackage));
    foreach ($iterator as $f) {
        if ($f->isFile() && strtolower($f->getExtension()) === 'zip') {
            $hasZip = true;
            break;
        }
    }
    assertTrue(!$hasZip, "dist/package/ no debe contener archivos .zip");
});

// Limpieza de archivos temporales de prueba
$files = scandir($tempBackupDir) ?: [];
foreach ($files as $f) {
    if ($f !== '.' && $f !== '..') {
        @unlink($tempBackupDir . DIRECTORY_SEPARATOR . $f);
    }
}
@rmdir($tempBackupDir);
@unlink($tempSqliteFile);

echo "\n========================================================\n";
echo "RESULTADOS TAREA 11: $passedTests / $totalTests PRUEBAS SUPERADAS\n";
echo "========================================================\n";

if ($passedTests < $totalTests) {
    exit(1);
}
exit(0);

