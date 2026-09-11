<?php
/**
 * ====================================================================
 * CLI DISASTER RECOVERY BACKUP GENERATOR (F14)
 * Plataforma Descartables Peruanos
 * ====================================================================
 * 
 * Uso por línea de comandos / cron:
 *   php scripts/backup.php [--out=backups] [--purge-older-than-days=7] [--quiet]
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/backup_engine.php';

// Parsear argumentos CLI
$options = getopt('', ['out::', 'purge-older-than-days::', 'db::', 'source-db::', 'quiet', 'help']);

if (isset($options['help'])) {
    echo "Uso:\n";
    echo "  php scripts/backup.php [--out=directorio] [--purge-older-than-days=7] [--db=DSN] [--quiet]\n\n";
    echo "Opciones:\n";
    echo "  --out                     Directorio de destino para el archivo .zip (por defecto 'backups/')\n";
    echo "  --purge-older-than-days   Días de retención para eliminar backups antiguos (por defecto 7)\n";
    echo "  --db                      DSN alternativo de PDO (ej: sqlite:database/test.db)\n";
    echo "  --quiet                   Modo silencioso, omite salida visual salvo errores\n";
    exit(0);
}

$isQuiet = isset($options['quiet']);
$outDir = isset($options['out']) ? (string)$options['out'] : null;
$purgeDays = isset($options['purge-older-than-days']) ? (int)$options['purge-older-than-days'] : 7;

if (!$isQuiet) {
    echo "========================================================\n";
    echo "GENERADOR DE BACKUP DISASTER RECOVERY - DESCARTABLES PERÚ\n";
    echo "========================================================\n";
    echo "[INFO] Iniciando extracción de base de datos y archivos persistentes...\n";
}

try {
    if (!empty($options['db']) || !empty($options['source-db'])) {
        $dsn = (string)($options['db'] ?? $options['source-db']);
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } else {
        $pdo = getDbConnection();
    }

    if (!$pdo) {
        throw new RuntimeException("No se pudo conectar a la base de datos.");
    }

    $result = BackupEngine::createDisasterRecoveryArchive($pdo, $outDir, [
        'purge_days' => $purgeDays
    ]);

    if (!$isQuiet) {
        $manifest = $result['manifest'];
        echo "[OK] Paquete de Disaster Recovery generado exitosamente.\n";
        echo "--------------------------------------------------------\n";
        echo "Archivo:           " . $result['archive_name'] . "\n";
        echo "Ruta física:       " . $result['archive_path'] . "\n";
        echo "Tamaño:            " . number_format($result['size_bytes'] / 1024, 2) . " KB\n";
        echo "Checksum SHA-256:  " . $result['sha256'] . "\n";
        echo "Fecha creación:    " . $manifest['creado_en'] . "\n";
        echo "Tablas incluidas:\n";
        foreach ($manifest['tablas'] as $tbl => $meta) {
            echo "  - $tbl: " . $meta['row_count'] . " registros (SHA-256: " . substr($meta['sha256'], 0, 12) . "...)\n";
        }
        echo "Tablas excluidas por política:\n";
        foreach ($manifest['tablas_excluidas_politica'] as $tbl => $motivo) {
            echo "  - $tbl (excluida: $motivo)\n";
        }
        echo "Archivos persistentes: " . $manifest['archivos_persistentes']['total_archivos'] . " archivos empaquetados.\n";
        if (!empty($manifest['archivos_persistentes']['archivos_faltantes_detectados'])) {
            echo "[ALERTA] Archivos referenciados en BD pero faltantes en disco: " . count($manifest['archivos_persistentes']['archivos_faltantes_detectados']) . "\n";
        }
        echo "========================================================\n";
    }

    exit(0);

} catch (Throwable $e) {
    fwrite(STDERR, "[ERROR FATAL] Fallo al generar copia de seguridad: " . $e->getMessage() . "\n");
    if (!$isQuiet) {
        fwrite(STDERR, $e->getTraceAsString() . "\n");
    }
    exit(1);
}
