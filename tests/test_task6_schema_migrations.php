<?php
/**
 * Test Suite: Tarea 6 - Unificación de Schema, Migraciones y Estructura BD (F09)
 * Plataforma Descartables Peruanos
 */

define('RUNNING_MIGRATION_TEST', true);
$GLOBALS['MIGRATION_RUNNER_INCLUDED'] = true;

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../database/migrate.php';

$totalTests = 0;
$passedTests = 0;

function assertTest($description, $condition) {
    global $totalTests, $passedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
    }
}

echo "====================================================================\n";
echo "EJECUTANDO SUITE DE PRUEBAS: TAREA 6 (SCHEMA Y MIGRACIONES - F09)\n";
echo "====================================================================\n\n";

// Crear conexión sintética aislada para pruebas
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Test 1: Comprobar existencia de archivos de migración y herramientas
echo "[1] Comprobando estructura de archivos de migración y esquema canónico...\n";
$migrationFiles = getAvailableMigrationFiles();
assertTest("Existen archivos de migración en database/migrations/", count($migrationFiles) >= 4);
assertTest("Existe migración 001_initial_schema.sql", isset($migrationFiles['001_initial_schema.sql']));
assertTest("Existe migración 002_add_auth_session_fields.sql", isset($migrationFiles['002_add_auth_session_fields.sql']));
assertTest("Existe migración 003_privacy_indexes_and_constraints.sql", isset($migrationFiles['003_privacy_indexes_and_constraints.sql']));
assertTest("Existe migración 004_seed_initial_data.sql", isset($migrationFiles['004_seed_initial_data.sql']));
assertTest("Existe migración 005_idempotency_and_sequences.sql", isset($migrationFiles['005_idempotency_and_sequences.sql']));
assertTest("Existe migración 006_rate_limits.sql", isset($migrationFiles['006_rate_limits.sql']));
assertTest("Existe archivo schema.sql en la raíz", file_exists(__DIR__ . '/../schema.sql'));
assertTest("Existe ejecutador database/migrate.php", file_exists(__DIR__ . '/../database/migrate.php'));

// Test 2: Ejecución de migración fresh
echo "\n[2] Probando ejecución de migraciones en modo fresh...\n";
$migratedCount = runFresh($pdo, true);
assertTest("runFresh ejecutó las migraciones correctamente", $migratedCount >= 6);

// Test 3: Verificar registro de versiones en schema_migrations
echo "\n[3] Verificando tabla schema_migrations...\n";
$applied = getAppliedMigrations($pdo);
assertTest("La tabla schema_migrations contiene 001_initial_schema.sql", isset($applied['001_initial_schema.sql']));
assertTest("La tabla schema_migrations contiene 002_add_auth_session_fields.sql", isset($applied['002_add_auth_session_fields.sql']));
assertTest("La tabla schema_migrations contiene 003_privacy_indexes_and_constraints.sql", isset($applied['003_privacy_indexes_and_constraints.sql']));
assertTest("La tabla schema_migrations contiene 004_seed_initial_data.sql", isset($applied['004_seed_initial_data.sql']));
assertTest("La tabla schema_migrations contiene 005_idempotency_and_sequences.sql", isset($applied['005_idempotency_and_sequences.sql']));
assertTest("La tabla schema_migrations contiene 006_rate_limits.sql", isset($applied['006_rate_limits.sql']));

// Test 4: Idempotencia - Re-ejecutar migraciones sin errores
echo "\n[4] Probando idempotencia de migraciones...\n";
$reRunCount = runMigrations($pdo, true);
assertTest("Segunda ejecución de runMigrations no realiza cambios innecesarios (0 pendientes)", $reRunCount === 0);

// Test 5: Verificación de estructura canónica de tablas y columnas
echo "\n[5] Verificando columnas de tablas canónicas...\n";
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
assertTest("Existe tabla usuarios", in_array('usuarios', $tables));
assertTest("Existe tabla categorias", in_array('categorias', $tables));
assertTest("Existe tabla productos", in_array('productos', $tables));
assertTest("Existe tabla libro_reclamaciones", in_array('libro_reclamaciones', $tables));
assertTest("Existe tabla cotizaciones", in_array('cotizaciones', $tables));
assertTest("Existe tabla configuracion", in_array('configuracion', $tables));
assertTest("Existe tabla secuencias", in_array('secuencias', $tables));
assertTest("Existe tabla idempotencia", in_array('idempotencia', $tables));
assertTest("Existe tabla rate_limits", in_array('rate_limits', $tables));

$colsCotiz = $pdo->query("PRAGMA table_info(cotizaciones)")->fetchAll(PDO::FETCH_COLUMN, 1);
assertTest("cotizaciones tiene columna codigo_cotizacion", in_array('codigo_cotizacion', $colsCotiz));
assertTest("cotizaciones tiene columna usuario_id", in_array('usuario_id', $colsCotiz));
assertTest("cotizaciones tiene columna documento", in_array('documento', $colsCotiz));
assertTest("cotizaciones tiene columna nombre_cliente", in_array('nombre_cliente', $colsCotiz));
assertTest("cotizaciones tiene columna items", in_array('items', $colsCotiz));
assertTest("cotizaciones tiene columna total_items", in_array('total_items', $colsCotiz));
assertTest("cotizaciones tiene columna estado", in_array('estado', $colsCotiz));
assertTest("cotizaciones tiene columna notas", in_array('notas', $colsCotiz));

$colsRec = $pdo->query("PRAGMA table_info(libro_reclamaciones)")->fetchAll(PDO::FETCH_COLUMN, 1);
assertTest("libro_reclamaciones tiene columna codigo_hoja", in_array('codigo_hoja', $colsRec));
assertTest("libro_reclamaciones tiene columna numero_documento", in_array('numero_documento', $colsRec));
assertTest("libro_reclamaciones tiene columna tipo_reclamacion", in_array('tipo_reclamacion', $colsRec));
assertTest("libro_reclamaciones tiene columna respuesta_proveedor", in_array('respuesta_proveedor', $colsRec));

$colsProd = $pdo->query("PRAGMA table_info(productos)")->fetchAll(PDO::FETCH_COLUMN, 1);
assertTest("productos tiene columna sku", in_array('sku', $colsProd));
assertTest("productos tiene columna categoria_id", in_array('categoria_id', $colsProd));
assertTest("productos tiene columna precio", in_array('precio', $colsProd));
assertTest("productos tiene columna stock_estado", in_array('stock_estado', $colsProd));
assertTest("productos tiene columna biodegradable", in_array('biodegradable', $colsProd));

// Test 6: Verificación de datos iniciales sembrados
echo "\n[6] Verificando datos iniciales sembrados por las migraciones...\n";
$catCount = (int)$pdo->query("SELECT COUNT(*) FROM categorias")->fetchColumn();
assertTest("Categorías iniciales sembradas correctamente (6)", $catCount >= 6);

$prodCount = (int)$pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
assertTest("Productos iniciales sembrados correctamente (18)", $prodCount >= 18);

$confCount = (int)$pdo->query("SELECT COUNT(*) FROM configuracion")->fetchColumn();
assertTest("Parámetros de configuración sembrados correctamente", $confCount >= 10);

// Test 7: Comprobar que api/db.php no ejecuta DDL en requests ordinarios
echo "\n[7] Verificando que api/db.php está libre de DDL per-request...\n";
$dbPhpCode = file_get_contents(__DIR__ . '/../api/db.php');
assertTest("api/db.php no contiene CREATE TABLE", strpos($dbPhpCode, 'CREATE TABLE') === false);
assertTest("api/db.php no contiene ALTER TABLE", strpos($dbPhpCode, 'ALTER TABLE') === false);
assertTest("api/db.php no contiene SHOW COLUMNS", strpos($dbPhpCode, 'SHOW COLUMNS') === false);

// Test 8: Comprobar schema.sql y ausencia de credenciales inseguras
echo "\n[8] Verificando integridad de schema.sql...\n";
$schemaSql = file_get_contents(__DIR__ . '/../schema.sql');
assertTest("schema.sql contiene tabla schema_migrations", strpos($schemaSql, 'schema_migrations') !== false);
assertTest("schema.sql contiene tabla libro_reclamaciones", strpos($schemaSql, 'libro_reclamaciones') !== false);
assertTest("schema.sql contiene tabla cotizaciones con columna items", strpos($schemaSql, 'items` LONGTEXT') !== false || strpos($schemaSql, 'items LONGTEXT') !== false);
assertTest("schema.sql no contiene salt obsoleto", strpos($schemaSql, 'DP_Peru_SecureSalt_2026_x89aF72kL9') === false);

echo "\n====================================================================\n";
echo "RESULTADOS TAREA 6: {$passedTests} / {$totalTests} PRUEBAS SUPERADAS\n";
echo "====================================================================\n";

if ($passedTests === $totalTests) {
    exit(0);
} else {
    exit(1);
}

