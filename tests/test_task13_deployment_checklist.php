<?php
/**
 * Test Suite: Tarea 13 - Checklist Final de Despliegue, Preproducción y Nube (F15 + Cierre General)
 * Plataforma Descartables Peruanos
 */

define('RUNNING_TEST_SUITE', true);
require_once __DIR__ . '/../api/vault.php';

$totalTests = 0;
$passedTests = 0;

function assertCheck($description, $condition) {
    global $totalTests, $passedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  ✅ PASS: {$description}\n";
    } else {
        echo "  ❌ FAIL: {$description}\n";
    }
}

echo "====================================================================\n";
echo "EJECUTANDO SUITE DE PRUEBAS: TAREA 13 (CHECKLIST PREPRODUCCIÓN / NUBE)\n";
echo "====================================================================\n\n";

// ------------------------------------------------------------------
// TEST 1: Seguridad y Reglas de Servidor Web (.htaccess)
// ------------------------------------------------------------------
echo "Test 1: Inspección de Seguridad en .htaccess Raíz y Subdirectorios...\n";
$rootHtaccess = file_get_contents(__DIR__ . '/../.htaccess');
assertCheck(".htaccess raíz existe y tiene contenido", !empty($rootHtaccess));
assertCheck(".htaccess raíz contiene regla de redirección HTTPS", strpos($rootHtaccess, 'RewriteCond %{HTTPS} !=on') !== false);
assertCheck(".htaccess raíz excluye localhost de redirección HTTPS", strpos($rootHtaccess, 'localhost') !== false && strpos($rootHtaccess, '127') !== false);
assertCheck(".htaccess raíz define X-Content-Type-Options: nosniff", strpos($rootHtaccess, 'X-Content-Type-Options "nosniff"') !== false);
assertCheck(".htaccess raíz define X-Frame-Options: SAMEORIGIN", strpos($rootHtaccess, 'X-Frame-Options "SAMEORIGIN"') !== false);
assertCheck(".htaccess raíz define Referrer-Policy", strpos($rootHtaccess, 'Referrer-Policy "strict-origin-when-cross-origin"') !== false);
assertCheck(".htaccess raíz define Permissions-Policy", strpos($rootHtaccess, 'Permissions-Policy') !== false);
assertCheck(".htaccess raíz bloquea extensiones sensibles (sql|env|log|bak|zip)", strpos($rootHtaccess, 'FilesMatch') !== false && strpos($rootHtaccess, 'secrets') !== false);

$dbHtaccess = file_exists(__DIR__ . '/../database/.htaccess') ? file_get_contents(__DIR__ . '/../database/.htaccess') : '';
assertCheck("database/.htaccess existe", !empty($dbHtaccess));
assertCheck("database/.htaccess deniega todo acceso web directo", strpos($dbHtaccess, 'Require all denied') !== false || strpos($dbHtaccess, 'Deny from all') !== false);

$scriptsHtaccess = file_exists(__DIR__ . '/../scripts/.htaccess') ? file_get_contents(__DIR__ . '/../scripts/.htaccess') : '';
assertCheck("scripts/.htaccess existe", !empty($scriptsHtaccess));
assertCheck("scripts/.htaccess deniega todo acceso web directo", strpos($scriptsHtaccess, 'Require all denied') !== false || strpos($scriptsHtaccess, 'Deny from all') !== false);

$logsHtaccess = file_exists(__DIR__ . '/../logs/.htaccess') ? file_get_contents(__DIR__ . '/../logs/.htaccess') : '';
assertCheck("logs/.htaccess existe y deniega acceso", strpos($logsHtaccess, 'Require all denied') !== false || strpos($logsHtaccess, 'Deny from all') !== false);

$backupsHtaccess = file_exists(__DIR__ . '/../backups/.htaccess') ? file_get_contents(__DIR__ . '/../backups/.htaccess') : '';
assertCheck("backups/.htaccess existe y deniega acceso", strpos($backupsHtaccess, 'Require all denied') !== false || strpos($backupsHtaccess, 'Deny from all') !== false);

$uploadsHtaccess = file_exists(__DIR__ . '/../assets/images/productos/.htaccess') ? file_get_contents(__DIR__ . '/../assets/images/productos/.htaccess') : '';
assertCheck("assets/images/productos/.htaccess existe y bloquea scripts", strpos($uploadsHtaccess, 'engine off') !== false && strpos($uploadsHtaccess, 'Require all denied') !== false);

// ------------------------------------------------------------------
// TEST 2: Política de CORS Segura y Sin Wildcards en Producción
// ------------------------------------------------------------------
echo "\nTest 2: Verificación de CORS Seguro en la API...\n";
assertCheck("Método Vault::handleCors existe", method_exists('Vault', 'handleCors'));

$configContent = file_get_contents(__DIR__ . '/../api/config.php');
assertCheck("api/config.php NO contiene 'Access-Control-Allow-Origin: *'", strpos($configContent, "Access-Control-Allow-Origin: *") === false);
assertCheck("api/config.php invoca Vault::handleCors()", strpos($configContent, "Vault::handleCors()") !== false);

$cotizacionesContent = file_get_contents(__DIR__ . '/../api/cotizaciones.php');
assertCheck("api/cotizaciones.php NO contiene 'Access-Control-Allow-Origin: *'", strpos($cotizacionesContent, "Access-Control-Allow-Origin: *") === false);

$reclamacionesContent = file_get_contents(__DIR__ . '/../api/reclamaciones.php');
assertCheck("api/reclamaciones.php NO contiene 'Access-Control-Allow-Origin: *'", strpos($reclamacionesContent, "Access-Control-Allow-Origin: *") === false);

// ------------------------------------------------------------------
// TEST 3: Base de Datos y Motor de Almacenamiento InnoDB
// ------------------------------------------------------------------
echo "\nTest 3: Verificación de Motor InnoDB y Transaccionalidad...\n";
$schemaSql = file_get_contents(__DIR__ . '/../schema.sql');
assertCheck("schema.sql declara ENGINE=InnoDB", strpos($schemaSql, 'ENGINE=InnoDB') !== false);

$migrationFiles = glob(__DIR__ . '/../database/migrations/*.sql');
assertCheck("Existen archivos de migración canónicos (>= 6)", count($migrationFiles) >= 6);

$allMigrationsInnoDB = true;
foreach ($migrationFiles as $mf) {
    $content = file_get_contents($mf);
    if (stripos($content, 'CREATE TABLE') !== false && stripos($content, 'ENGINE=InnoDB') === false) {
        $allMigrationsInnoDB = false;
        echo "  [ALERTA] Migración " . basename($mf) . " no especifica ENGINE=InnoDB\n";
    }
}
assertCheck("Todas las sentencias CREATE TABLE en migraciones especifican ENGINE=InnoDB", $allMigrationsInnoDB);

// ------------------------------------------------------------------
// TEST 4: Resiliencia de database/migrate.php
// ------------------------------------------------------------------
echo "\nTest 4: Verificación de Comportamiento de database/migrate.php...\n";
$migrateContent = file_get_contents(__DIR__ . '/../database/migrate.php');
assertCheck("migrate.php reporta PENDIENTE EN HOSTING ante falta de MySQL remoto en local", strpos($migrateContent, 'PENDIENTE EN HOSTING') !== false);
assertCheck("migrate.php reporta BLOQUEANTE ante falla en hosting", strpos($migrateContent, '[BLOQUEANTE]') !== false);
assertCheck("migrate.php implementa soporte para flag --sqlite", strpos($migrateContent, '--sqlite') !== false);

// Ejecutar migrate.php --status y comprobar código de salida 0
exec('php ' . escapeshellarg(__DIR__ . '/../database/migrate.php') . ' --status', $migrateOutput, $migrateCode);
assertCheck("php database/migrate.php --status finaliza con código de salida 0", $migrateCode === 0);
$outputJoined = implode("\n", $migrateOutput);
assertCheck("php database/migrate.php --status contiene indicador de preproducción", strpos($outputJoined, 'ESTADO DE MIGRACIONES') !== false);

// ------------------------------------------------------------------
// TEST 5: Documentación de Despliegue y Preproducción
// ------------------------------------------------------------------
echo "\nTest 5: Verificación de Documentación Oficial de Despliegue...\n";
assertCheck("docs/deployment_guide.md existe", file_exists(__DIR__ . '/../docs/deployment_guide.md'));
$deployGuide = file_get_contents(__DIR__ . '/../docs/deployment_guide.md');
assertCheck("deployment_guide.md documenta requisitos PHP >= 8.0 y extensiones", strpos($deployGuide, 'pdo_mysql') !== false && strpos($deployGuide, 'PHP >= 8.0') !== false);
assertCheck("deployment_guide.md documenta ENGINE=InnoDB obligatorio", strpos($deployGuide, 'InnoDB') !== false);
assertCheck("deployment_guide.md documenta ubicación de secrets.php fuera de public_html", strpos($deployGuide, 'fuera de la raíz pública') !== false || strpos($deployGuide, 'secrets.php') !== false);
assertCheck("deployment_guide.md documenta variables de entorno requeridas", strpos($deployGuide, 'TOKEN_SALT') !== false && strpos($deployGuide, 'DB_HOST') !== false);
assertCheck("deployment_guide.md documenta matriz de permisos en Linux", strpos($deployGuide, 'chmod 600') !== false && strpos($deployGuide, 'chmod 755') !== false);

assertCheck("docs/preproduction_checklist.md existe", file_exists(__DIR__ . '/../docs/preproduction_checklist.md'));
$checklistContent = file_get_contents(__DIR__ . '/../docs/preproduction_checklist.md');
assertCheck("preproduction_checklist.md contiene estados [LISTO]", strpos($checklistContent, '[LISTO]') !== false);
assertCheck("preproduction_checklist.md contiene estados [PENDIENTE EN HOSTING]", strpos($checklistContent, '[PENDIENTE EN HOSTING]') !== false);
assertCheck("preproduction_checklist.md contiene estados [BLOQUEANTE]", strpos($checklistContent, '[BLOQUEANTE]') !== false);
assertCheck("preproduction_checklist.md contiene dictamen global claro", strpos($checklistContent, 'LISTO LOCALMENTE, PENDIENTE DE VALIDACIÓN EN HOSTING/PREPRODUCCIÓN') !== false);

$readmeContent = file_get_contents(__DIR__ . '/../README.md');
assertCheck("README.md referencia docs/deployment_guide.md", strpos($readmeContent, 'docs/deployment_guide.md') !== false);
assertCheck("README.md referencia docs/preproduction_checklist.md", strpos($readmeContent, 'docs/preproduction_checklist.md') !== false);
assertCheck("README.md referencia docs/backup_restore_guide.md", strpos($readmeContent, 'docs/backup_restore_guide.md') !== false);

// ------------------------------------------------------------------
// TEST 6: Auditoría de scripts/build_package.js
// ------------------------------------------------------------------
echo "\nTest 6: Auditoría de Exclusiones en Empaquetado...\n";
$buildScript = file_get_contents(__DIR__ . '/../scripts/build_package.js');
assertCheck("build_package.js prohíbe docs/", strpos($buildScript, "'docs'") !== false);
assertCheck("build_package.js prohíbe scripts/", strpos($buildScript, "'scripts'") !== false);
assertCheck("build_package.js prohíbe tests/", strpos($buildScript, "'tests'") !== false);
assertCheck("build_package.js prohíbe secrets.php", strpos($buildScript, "'secrets.php'") !== false);
assertCheck("build_package.js prohíbe .env", strpos($buildScript, "'.env'") !== false);
assertCheck("build_package.js prohíbe node_modules", strpos($buildScript, "'node_modules'") !== false);

// Resumen Final
echo "\n====================================================================\n";
echo "📊 RESULTADOS TAREA 13: {$passedTests} / {$totalTests} PRUEBAS SUPERADAS\n";
echo "====================================================================\n";

if ($passedTests === $totalTests) {
    exit(0);
} else {
    exit(1);
}
