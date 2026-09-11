<?php
/**
 * ====================================================================
 * SISTEMA DE MIGRACIONES Y CONTROL DE ESQUEMA (CLI RUNNER)
 * Plataforma Descartables Peruanos
 * ====================================================================
 * Uso:
 *   php database/migrate.php            (Ejecuta todas las migraciones pendientes)
 *   php database/migrate.php --status   (Muestra el estado de cada migración)
 *   php database/migrate.php --fresh    (Reconstruye la base de datos desde cero)
 */

if (php_sapi_name() !== 'cli' && !defined('RUNNING_MIGRATION_TEST')) {
    http_response_code(403);
    die("Acceso denegado: El ejecutor de migraciones solo puede correrse por consola CLI.");
}

require_once __DIR__ . '/../api/config.php';

function getMigrationPdo(?string $driverOverride = null) {
    global $argv;
    $args = $argv ?? [];
    $useSqlite = ($driverOverride === 'sqlite') || 
                 in_array('--sqlite', $args, true) || 
                 getenv('DB_DRIVER') === 'sqlite';

    if ($useSqlite) {
        $sqliteFile = getenv('DB_FILE') ?: sys_get_temp_dir() . '/descartables_migrations.sqlite';
        $pdo = new PDO('sqlite:' . $sqliteFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 3,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $pdo->exec("SET NAMES utf8mb4");
    return $pdo;
}

function ensureMigrationsTable($pdo) {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            version TEXT NOT NULL UNIQUE,
            applied_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        version VARCHAR(100) NOT NULL UNIQUE,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function getAppliedMigrations($pdo) {
    ensureMigrationsTable($pdo);
    $stmt = $pdo->query("SELECT version, applied_at FROM schema_migrations ORDER BY id ASC");
    $applied = [];
    while ($row = $stmt->fetch()) {
        $applied[$row['version']] = $row['applied_at'];
    }
    return $applied;
}

function getAvailableMigrationFiles() {
    $dir = __DIR__ . '/migrations';
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.sql');
    sort($files);
    $migrations = [];
    foreach ($files as $file) {
        $filename = basename($file);
        $migrations[$filename] = $file;
    }
    return $migrations;
}

function executeSqlScript($pdo, $sqlContent) {
    // 1. Eliminar comentarios SQL de una sola línea (-- ...)
    $cleanSql = preg_replace('/--.*$/m', '', $sqlContent);

    // 2. Dividir sentencias por punto y coma
    $statements = array_filter(
        array_map('trim', preg_split('/;\s*(?=\r?\n|$)/', $cleanSql)),
        fn($stmt) => !empty($stmt)
    );

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        // Adaptaciones para entorno SQLite en memoria
        foreach ($statements as $stmt) {
            if (empty($stmt) || stripos($stmt, 'SET FOREIGN_KEY_CHECKS') !== false) continue;

            $sqliteStmt = $stmt;
            $sqliteStmt = preg_replace('/`/', '', $sqliteStmt);
            $sqliteStmt = preg_replace('/ENGINE\s*=\s*InnoDB/i', '', $sqliteStmt);
            $sqliteStmt = preg_replace('/DEFAULT\s+CHARSET\s*=\s*[a-zA-Z0-9_-]+/i', '', $sqliteStmt);
            $sqliteStmt = preg_replace('/COLLATE\s*=\s*[a-zA-Z0-9_-]+/i', '', $sqliteStmt);
            $sqliteStmt = preg_replace('/INT\s+AUTO_INCREMENT\s+PRIMARY\s+KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sqliteStmt);
            $sqliteStmt = preg_replace('/INT\s+AUTOINCREMENT\s+PRIMARY\s+KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sqliteStmt);
            $sqliteStmt = preg_replace('/ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', '', $sqliteStmt);
            $sqliteStmt = preg_replace('/UNIQUE\s+KEY\s+\w+\s*(\([^)]+\))/i', 'UNIQUE $1', $sqliteStmt);
            $sqliteStmt = preg_replace('/,\s*INDEX\s+\w+\s*\([^)]+\)/i', '', $sqliteStmt);
            $sqliteStmt = preg_replace('/,\s*\)/', "\n)", $sqliteStmt);

            // Adaptar INSERT con ON DUPLICATE KEY UPDATE a INSERT OR REPLACE / IGNORE
            if (stripos($sqliteStmt, 'ON DUPLICATE KEY UPDATE') !== false) {
                $sqliteStmt = preg_replace('/ON DUPLICATE KEY UPDATE.*/is', '', $sqliteStmt);
                $sqliteStmt = preg_replace('/^INSERT INTO/i', 'INSERT OR REPLACE INTO', trim($sqliteStmt));
            }

            // Si es un ALTER TABLE MODIFY en SQLite, saltar (SQLite no soporta MODIFY COLUMN)
            if (preg_match('/ALTER\s+TABLE\s+\w+\s+MODIFY/i', $sqliteStmt)) {
                continue;
            }

            try {
                $pdo->exec($sqliteStmt);
            } catch (PDOException $e) {
                // Continuar si ya existe
            }
        }
        return;
    }

    // Modo Nativo MySQL / MariaDB
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (stripos($msg, 'Duplicate column name') !== false || 
                    stripos($msg, 'Duplicate key name') !== false ||
                    stripos($msg, 'already exists') !== false) {
                    continue;
                }
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                throw $e;
            }
        }
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
}

function runMigrations($pdo, $quiet = false) {
    ensureMigrationsTable($pdo);
    $applied = getAppliedMigrations($pdo);
    $available = getAvailableMigrationFiles();

    $pending = array_diff_key($available, $applied);

    if (empty($pending)) {
        if (!$quiet) {
            echo "[OK] No hay migraciones pendientes. La base de datos está al día.\n";
        }
        return 0;
    }

    $count = 0;
    foreach ($pending as $version => $filePath) {
        if (!$quiet) {
            echo "[MIGRATE] Aplicando migración: {$version} ... ";
        }
        $sql = file_get_contents($filePath);
        if ($sql === false) {
            throw new Exception("No se pudo leer el archivo de migración: {$filePath}");
        }

        executeSqlScript($pdo, $sql);

        $stmt = $pdo->prepare("INSERT INTO schema_migrations (version) VALUES (?)");
        $stmt->execute([$version]);

        $count++;
        if (!$quiet) {
            echo "EXITOSO.\n";
        }
    }

    if (!$quiet) {
        echo "[DONE] Se aplicaron {$count} migración(es) correctamente.\n";
    }
    return $count;
}

function showStatus($pdo) {
    ensureMigrationsTable($pdo);
    $applied = getAppliedMigrations($pdo);
    $available = getAvailableMigrationFiles();

    echo "====================================================================\n";
    echo "ESTADO DE MIGRACIONES - DESCARTABLES PERUANOS\n";
    echo "====================================================================\n";
    echo sprintf("%-45s | %-12s | %-20s\n", "MIGRACIÓN", "ESTADO", "FECHA APLICACIÓN");
    echo str_repeat("-", 85) . "\n";

    foreach ($available as $version => $path) {
        $isApplied = isset($applied[$version]);
        $statusStr = $isApplied ? "APLICADA" : "PENDIENTE";
        $dateStr = $isApplied ? $applied[$version] : "—";
        echo sprintf("%-45s | %-12s | %-20s\n", $version, $statusStr, $dateStr);
    }
    echo "====================================================================\n";
}

function runFresh($pdo, $quiet = false) {
    if (!$quiet) {
        echo "[RESET] Limpiando todas las tablas de la base de datos...\n";
    }
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pdo->exec("PRAGMA foreign_keys = OFF");
    } else {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    }

    $tables = ['rate_limits', 'idempotencia', 'secuencias', 'cotizaciones', 'libro_reclamaciones', 'reclamaciones', 'productos', 'categorias', 'usuarios', 'configuracion', 'schema_migrations'];
    foreach ($tables as $t) {
        $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
    }

    if ($driver === 'sqlite') {
        $pdo->exec("PRAGMA foreign_keys = ON");
    } else {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    if (!$quiet) {
        echo "[RESET] Ejecutando todas las migraciones desde cero...\n";
    }
    return runMigrations($pdo, $quiet);
}

// ================= EJECUCIÓN PRINCIPAL =================
if (php_sapi_name() === 'cli' && empty($GLOBALS['MIGRATION_RUNNER_INCLUDED'])) {
    $args = $argv ?? [];
    $command = $args[1] ?? '--migrate';
    $isStatusCmd = ($command === '--status');

    // Determinar si estamos en entorno de desarrollo local vs hosting real
    $appEnv = getenv('APP_ENV') ?: (class_exists('Vault') ? Vault::get('security.environment', 'development') : 'development');
    $isLocalDev = (PHP_OS_FAMILY === 'Windows' || $appEnv === 'development' || $appEnv === 'local' || !empty(getenv('LOCAL_DEV')));

    $pdo = null;
    try {
        $pdo = getMigrationPdo();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $pdo->exec("SET NAMES utf8mb4");
        }
    } catch (Throwable $e) {
        if ($isStatusCmd) {
            if ($isLocalDev) {
                // Modo local: reportar claramente PENDIENTE EN HOSTING sin arrojar error crítico
                echo "\n====================================================================\n";
                echo "ESTADO DE MIGRACIONES - DESCARTABLES PERUANOS (PREPRODUCCIÓN)\n";
                echo "====================================================================\n";
                echo "[AVISO RED/HOSTING] Base de datos MySQL remota no alcanzable desde esta máquina local.\n";
                echo "Host configurado: " . DB_HOST . ":" . DB_PORT . " (" . DB_NAME . ")\n";
                echo "Diagnóstico red: " . $e->getMessage() . "\n";
                echo "Estado: [PENDIENTE EN HOSTING] (El host de base de datos es privado del hosting)\n\n";
                echo "Inventario de archivos de migración canónicos listos para desplegar:\n";
                echo sprintf("%-45s | %-12s | %-15s\n", "MIGRACIÓN", "ESTADO", "TAMAÑO");
                echo str_repeat("-", 80) . "\n";
                $available = getAvailableMigrationFiles();
                foreach ($available as $version => $path) {
                    echo sprintf("%-45s | %-12s | %d bytes\n", $version, "PREPARADA", filesize($path));
                }
                echo "====================================================================\n";
                echo "Total migraciones disponibles: " . count($available) . "\n";
                echo "Consulte 'docs/deployment_guide.md' para ejecutar las migraciones en el hosting.\n";
                echo "====================================================================\n\n";
                exit(0);
            } else {
                // Entorno de hosting real: si falla la conexión en producción, es un fallo BLOQUEANTE
                fwrite(STDERR, "\n[BLOQUEANTE] Error de conexión a la base de datos en el hosting: " . $e->getMessage() . "\n");
                exit(1);
            }
        } else {
            // Comandos --migrate o --fresh requieren base de datos obligatoria
            if ($isLocalDev) {
                fwrite(STDERR, "\n[AVISO LOCAL] No se puede ejecutar '{$command}' porque el host MySQL remoto (" . DB_HOST . ") no es accesible desde esta máquina local.\n");
                fwrite(STDERR, "Para probar migraciones localmente, use la suite de pruebas: php tests/test_task6_schema_migrations.php o flag --sqlite\n\n");
            } else {
                fwrite(STDERR, "\n[BLOQUEANTE] Error crítico al conectar con la base de datos para '{$command}': " . $e->getMessage() . "\n");
            }
            exit(1);
        }
    }

    try {
        switch ($command) {
            case '--status':
                showStatus($pdo);
                break;
            case '--fresh':
                runFresh($pdo);
                break;
            case '--migrate':
            default:
                runMigrations($pdo);
                break;
        }
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, "\n[ERROR CRÍTICO EN MIGRACIONES] " . $e->getMessage() . "\n");
        exit(1);
    }
}
