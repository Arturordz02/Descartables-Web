<?php
/**
 * ====================================================================
 * CLI DISASTER RECOVERY RESTORATION ENGINE (F14)
 * Plataforma Descartables Peruanos
 * ====================================================================
 * 
 * Uso por línea de comandos:
 *   php scripts/restore.php --archive=backups/dp_backup_xxx.zip --verify-only
 *   php scripts/restore.php --archive=backups/dp_backup_xxx.zip --force [--target-db=sqlite:memory:]
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/backup_engine.php';

$options = getopt('', ['archive:', 'verify-only', 'force', 'target-db::', 'no-files', 'quiet', 'help']);

if (isset($options['help']) || empty($options['archive'])) {
    echo "Uso:\n";
    echo "  php scripts/restore.php --archive=ruta/al/backup.zip [opciones]\n\n";
    echo "Opciones Obligatorias (seleccione al menos una):\n";
    echo "  --verify-only         Inspecciona el manifest y valida todos los checksums SHA-256 sin modificar nada\n";
    echo "  --force               Aplica la restauración completa en la base de datos reemplazando registros\n\n";
    echo "Opciones Adicionales:\n";
    echo "  --target-db=DSN       DSN alternativo de PDO (ej: sqlite:database/test.db o sqlite::memory:)\n";
    echo "  --no-files            No sobreescribir archivos persistentes en disco (solo restaurar base de datos)\n";
    echo "  --quiet               Modo silencioso, omite salida detallada salvo errores\n";
    exit(empty($options['archive']) ? 1 : 0);
}

$archivePath = (string)$options['archive'];
$isVerifyOnly = isset($options['verify-only']);
$isForce = isset($options['force']);
$isQuiet = isset($options['quiet']);
$restoreFiles = !isset($options['no-files']);

if (!$isVerifyOnly && !$isForce) {
    fwrite(STDERR, "[ERROR DE SEGURIDAD] Por precaución, este comando requiere confirmación explícita.\n");
    fwrite(STDERR, "Use --verify-only para validar el archivo o --force para autorizar la restauración real de la base de datos.\n");
    exit(1);
}

if (!$isQuiet) {
    echo "========================================================\n";
    echo "RESTAURADOR DE DISASTER RECOVERY - DESCARTABLES PERÚ\n";
    echo "========================================================\n";
    echo "Archivo: $archivePath\n";
    echo "Modo:    " . ($isVerifyOnly ? "VALIDACIÓN DE INTEGRIDAD (--verify-only)" : "RESTAURACIÓN REAL (--force)") . "\n";
    echo "--------------------------------------------------------\n";
}

try {
    if (!$isQuiet) {
        echo "[1/3] Verificando checksums SHA-256 y estructura del manifest.json...\n";
    }

    $targetPdo = null;
    if ($isForce) {
        if (!empty($options['target-db'])) {
            $dsn = (string)$options['target-db'];
            $targetPdo = new PDO($dsn);
            $targetPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } else {
            $targetPdo = getDbConnection();
            if (!$targetPdo) {
                throw new RuntimeException("No se pudo conectar a la base de datos por defecto.");
            }
        }
    }

    // 2. Ejecutar restauración o verificación
    $result = BackupEngine::restoreArchive($archivePath, $targetPdo, [
        'verify_only'   => $isVerifyOnly,
        'force'         => $isForce,
        'restore_files' => $restoreFiles
    ]);

    if (!$isQuiet) {
        $manifest = $result['manifest'];
        echo "[OK] Verificación de integridad superada. Todos los hashes SHA-256 coinciden.\n";
        echo "ID de Backup:    " . $manifest['backup_id'] . "\n";
        echo "Fecha Origen:    " . $manifest['creado_en'] . "\n";
        echo "Versión Formato: " . $manifest['manifest_version'] . "\n";

        if ($isForce) {
            echo "\n[2/3] Restauración de tablas en base de datos completada:\n";
            foreach ($result['tablas_restauradas'] as $tbl => $cnt) {
                echo "  - $tbl: $cnt filas restauradas correctamente.\n";
            }

            echo "\n[3/3] Archivos persistentes gestionados:\n";
            echo "  - Archivos de imágenes restaurados físicamente: " . $result['archivos_restaurados'] . "\n";
            echo "\n[ÉXITO] Restauración completada y verificada al 100%.\n";
        } else {
            echo "\n[MODO VERIFY-ONLY] El paquete es íntegro, válido y puede restaurarse con seguridad usando --force.\n";
        }
        echo "========================================================\n";
    }

    exit(0);

} catch (Throwable $e) {
    fwrite(STDERR, "\n[ERROR DE RESTAURACIÓN] " . $e->getMessage() . "\n");
    if (!$isQuiet) {
        fwrite(STDERR, $e->getTraceAsString() . "\n");
    }
    exit(1);
}
