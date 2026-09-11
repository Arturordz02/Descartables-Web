<?php
/**
 * ====================================================================
 * MOTOR CENTRALIZADO DE BACKUP Y RECUPERACIÓN ANTE DESASTRES (F14)
 * Plataforma Descartables Peruanos
 * ====================================================================
 * 
 * Gestiona:
 * 1. Generación de paquetes integrales de Disaster Recovery (.zip) con manifest y SHA-256.
 * 2. Exportación lógica de datos y esquemas DDL versionados.
 * 3. Copia segura de archivos de imágenes persistentes gestionadas.
 * 4. Exclusión por política de datos temporales (rate_limits) y secretos del servidor.
 * 5. Verificación de integridad y restauración controlada (--verify-only / --force).
 */

declare(strict_types=1);

require_once __DIR__ . '/response.php';

class BackupEngine {
    public const FORMAT_VERSION = '2.0';
    public const BACKUP_TYPE_DR = 'full_disaster_recovery';
    public const BACKUP_TYPE_EXPORT = 'exportacion_administrativa_json';

    /**
     * Tablas canónicas operativas del sistema para Disaster Recovery.
     */
    public const CANONICAL_TABLES = [
        'schema_migrations',
        'secuencias',
        'configuracion',
        'categorias',
        'productos',
        'usuarios',
        'cotizaciones',
        'libro_reclamaciones'
    ];

    /**
     * Tablas excluidas por política de persistencia (temporales / operativas).
     */
    public const EXCLUDED_TABLES_BY_POLICY = [
        'rate_limits' => 'Tabla operativa temporal de control de abuso por IP. Se reinicia vacía tras la restauración para evitar arrastrar identificadores y contadores obsoletos.'
    ];

    /**
     * Retorna o crea el directorio privado de almacenamiento de backups.
     */
    public static function getBackupDirectory(): string {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        // Asegurar protección estricta con .htaccess
        $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccess)) {
            $rules = "# Protección estricta de copias de seguridad privadas\n" .
                     "<IfModule mod_authz_core.c>\n" .
                     "    Require all denied\n" .
                     "</IfModule>\n" .
                     "<IfModule !mod_authz_core.c>\n" .
                     "    Order allow,deny\n" .
                     "    Deny from all\n" .
                     "</IfModule>\n";
            @file_put_contents($htaccess, $rules);
        }

        return $dir;
    }

    /**
     * Genera un paquete integral comprimido de Disaster Recovery (.zip).
     *
     * @param PDO $pdo Conexión a la base de datos origen
     * @param string|null $outputDir Directorio de salida (por defecto 'backups/')
     * @param array $options Opciones de empaquetado (purge_days, metadata_extra)
     * @return array Resumen del backup con ruta física, manifest y checksums
     * @throws Exception Si falla la extracción de cualquier tabla o archivo
     */
    public static function createDisasterRecoveryArchive(PDO $pdo, ?string $outputDir = null, array $options = []): array {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('La extensión PHP ZipArchive es requerida para generar backups de Disaster Recovery.');
        }

        $baseDir = dirname(__DIR__);
        $targetDir = $outputDir ? rtrim($outputDir, '/\\') : self::getBackupDirectory();
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0750, true);
        }

        // Nombre de archivo seguro e impredecible: dp_backup_YYYYMMDD_HHMMSS_<16_hex>.zip
        date_default_timezone_set('America/Lima');
        $now = new DateTime();
        $dateStr = $now->format('Ymd_His');
        $randomHex = bin2hex(random_bytes(8));
        $archiveName = "dp_backup_{$dateStr}_{$randomHex}.zip";
        $archivePath = $targetDir . DIRECTORY_SEPARATOR . $archiveName;

        $zip = new ZipArchive();
        $res = $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($res !== true) {
            throw new RuntimeException("No se pudo crear el archivo ZIP de backup en '$archivePath'. Código de error ZipArchive: $res");
        }

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $manifestTables = [];
        $databaseRows = [];
        $sqlInserts = "-- ====================================================\n" .
                      "-- DUMP LÓGICO DE DATOS - PLATAFORMA DESCARTABLES PERUANOS\n" .
                      "-- Generado: " . $now->format('c') . "\n" .
                      "-- ====================================================\n\n";

        try {
            // 1. Extracción estricta de tablas canónicas (Fail-Fast)
            foreach (self::CANONICAL_TABLES as $table) {
                try {
                    $stmt = $pdo->query("SELECT * FROM {$table}");
                    if ($stmt === false) {
                        throw new RuntimeException("Error en consulta SELECT sobre tabla '$table'.");
                    }
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Throwable $e) {
                    throw new RuntimeException("Fallo al extraer datos de la tabla obligatoria '$table': " . $e->getMessage(), 0, $e);
                }

                $rowCount = count($rows);
                $databaseRows[$table] = $rows;

                // Generar sentencias INSERT SQL para compatibilidad directa
                $sqlInserts .= "-- Tabla: {$table} ({$rowCount} registros)\n";
                if ($rowCount > 0) {
                    $columns = array_keys($rows[0]);
                    $colsEscaped = implode(', ', array_map(function($c) use ($driver) {
                        return $driver === 'mysql' ? "`$c`" : "\"$c\"";
                    }, $columns));

                    foreach ($rows as $row) {
                        $valuesEscaped = [];
                        foreach ($columns as $c) {
                            $v = $row[$c];
                            if ($v === null) {
                                $valuesEscaped[] = 'NULL';
                            } elseif (is_numeric($v) && !is_string($v)) {
                                $valuesEscaped[] = $v;
                            } else {
                                $valuesEscaped[] = $pdo->quote((string)$v);
                            }
                        }
                        $sqlInserts .= "INSERT INTO {$table} ({$colsEscaped}) VALUES (" . implode(', ', $valuesEscaped) . ");\n";
                    }
                }
                $sqlInserts .= "\n";

                // Hash individual para la tabla
                $tableJson = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $manifestTables[$table] = [
                    'row_count' => $rowCount,
                    'sha256'    => hash('sha256', $tableJson)
                ];
            }

            // 2. Agregar dump SQL y datos JSON al ZIP
            $zip->addFromString('database/data.sql', $sqlInserts);
            $jsonData = json_encode($databaseRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $zip->addFromString('database/data.json', $jsonData);

            // 3. Empaquetar Schema y Migraciones
            $schemaFiles = [];
            $schemaRoot = $baseDir . DIRECTORY_SEPARATOR . 'schema.sql';
            if (file_exists($schemaRoot)) {
                $schemaContent = file_get_contents($schemaRoot);
                $zip->addFromString('database/schema.sql', $schemaContent);
                $schemaFiles['schema.sql'] = [
                    'path'   => 'database/schema.sql',
                    'sha256' => hash('sha256', $schemaContent)
                ];
            }

            $migrationsDir = $baseDir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
            if (is_dir($migrationsDir)) {
                $migrationEntries = scandir($migrationsDir) ?: [];
                foreach ($migrationEntries as $mFile) {
                    if (str_ends_with($mFile, '.sql')) {
                        $mFullPath = $migrationsDir . DIRECTORY_SEPARATOR . $mFile;
                        $mContent = file_get_contents($mFullPath);
                        $zipPath = 'database/migrations/' . $mFile;
                        $zip->addFromString($zipPath, $mContent);
                        $schemaFiles[$mFile] = [
                            'path'   => $zipPath,
                            'sha256' => hash('sha256', $mContent)
                        ];
                    }
                }
            }

            // 4. Empaquetar Archivos Persistentes Gestionados (Imágenes de productos y uploads)
            $persistentFiles = [];
            $missingFiles = [];

            // Directorio A: assets/images/productos/
            $prodImagesDir = $baseDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'productos';
            if (is_dir($prodImagesDir)) {
                $files = scandir($prodImagesDir) ?: [];
                foreach ($files as $f) {
                    if ($f === '.' || $f === '..' || $f === '.htaccess') {
                        continue;
                    }
                    $fPath = $prodImagesDir . DIRECTORY_SEPARATOR . $f;
                    if (is_file($fPath)) {
                        $content = file_get_contents($fPath);
                        if ($content === false) {
                            throw new RuntimeException("No se pudo leer el archivo persistente '$fPath'");
                        }
                        $zipRelPath = 'storage/productos/' . $f;
                        $zip->addFromString($zipRelPath, $content);
                        $persistentFiles[] = [
                            'original_rel_path' => 'assets/images/productos/' . $f,
                            'archive_path'      => $zipRelPath,
                            'size_bytes'        => filesize($fPath),
                            'sha256'            => hash('sha256', $content)
                        ];
                    }
                }
            }

            // Directorio B: uploads/
            $uploadsDir = $baseDir . DIRECTORY_SEPARATOR . 'uploads';
            if (is_dir($uploadsDir)) {
                $uFiles = scandir($uploadsDir) ?: [];
                foreach ($uFiles as $uf) {
                    if ($uf === '.' || $uf === '..' || $uf === '.htaccess' || $uf === '.gitkeep') {
                        continue;
                    }
                    $ufPath = $uploadsDir . DIRECTORY_SEPARATOR . $uf;
                    if (is_file($ufPath)) {
                        $content = file_get_contents($ufPath);
                        if ($content === false) {
                            throw new RuntimeException("No se pudo leer el archivo de upload '$ufPath'");
                        }
                        $zipRelPath = 'storage/uploads/' . $uf;
                        $zip->addFromString($zipRelPath, $content);
                        $persistentFiles[] = [
                            'original_rel_path' => 'uploads/' . $uf,
                            'archive_path'      => $zipRelPath,
                            'size_bytes'        => filesize($ufPath),
                            'sha256'            => hash('sha256', $content)
                        ];
                    }
                }
            }

            // Comprobar si algún producto en la base de datos referencia una imagen que no existe
            if (!empty($databaseRows['productos'])) {
                foreach ($databaseRows['productos'] as $prod) {
                    $imgUrl = $prod['imagen_url'] ?? null;
                    if ($imgUrl && is_string($imgUrl) && !str_starts_with($imgUrl, 'http')) {
                        $localPath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($imgUrl, '/\\'));
                        if (!file_exists($localPath)) {
                            $missingFiles[] = [
                                'sku'        => $prod['sku'] ?? 'N/A',
                                'imagen_url' => $imgUrl,
                                'esperado'   => $localPath
                            ];
                        }
                    }
                }
            }

            // 5. Construir y Agregar el manifest.json
            $manifest = [
                'manifest_version' => self::FORMAT_VERSION,
                'backup_type'      => self::BACKUP_TYPE_DR,
                'backup_id'        => 'bkp_' . $dateStr . '_' . $randomHex,
                'sistema'          => 'Descartables Peruanos - Sistema B2B e INDECOPI',
                'creado_en'        => $now->format('c'),
                'entorno'          => [
                    'php_version' => PHP_VERSION,
                    'os'          => PHP_OS_FAMILY,
                    'db_driver'   => $driver
                ],
                'advertencia_seguridad' => 'Este paquete contiene estructura integral de base de datos y hashes de usuario requeridos para recuperación ante desastres. Debe resguardarse bajo cifrado fuera del webroot público.',
                'tablas'           => $manifestTables,
                'tablas_excluidas_politica' => self::EXCLUDED_TABLES_BY_POLICY,
                'archivos_esquema' => $schemaFiles,
                'archivos_persistentes' => [
                    'total_archivos' => count($persistentFiles),
                    'elementos'      => $persistentFiles,
                    'archivos_faltantes_detectados' => $missingFiles
                ],
                'checksum_algoritmo' => 'sha256',
                'checksums'        => [
                    'data_sql'  => hash('sha256', $sqlInserts),
                    'data_json' => hash('sha256', $jsonData)
                ]
            ];

            $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $zip->addFromString('manifest.json', $manifestJson);

            // Cerrar y escribir archivo ZIP
            $zip->close();

        } catch (Throwable $e) {
            // Limpieza inmediata en caso de error para evitar artefactos truncados
            @$zip->close();
            if (file_exists($archivePath)) {
                @unlink($archivePath);
            }
            throw $e;
        }

        $finalZipSha256 = hash_file('sha256', $archivePath);

        // Limpieza automática de backups antiguos si se solicita (por defecto > 7 días)
        $purgeDays = isset($options['purge_days']) ? (int)$options['purge_days'] : 7;
        self::purgeOldBackups($targetDir, $purgeDays);

        return [
            'success'          => true,
            'archive_name'     => $archiveName,
            'archive_path'     => $archivePath,
            'size_bytes'       => filesize($archivePath),
            'sha256'           => $finalZipSha256,
            'manifest'         => $manifest
        ];
    }

    /**
     * Valida la integridad de un archivo de backup ZIP contra su manifest.json.
     *
     * @param string $archiveZipPath Ruta al archivo .zip
     * @return array Manifest decodificado y estado de verificación
     * @throws Exception Si el archivo está corrupto o los checksums no coinciden
     */
    public static function verifyArchive(string $archiveZipPath): array {
        if (!file_exists($archiveZipPath)) {
            throw new InvalidArgumentException("El archivo de backup '$archiveZipPath' no existe.");
        }

        $zip = new ZipArchive();
        if ($zip->open($archiveZipPath) !== true) {
            throw new RuntimeException("No se pudo abrir el archivo ZIP '$archiveZipPath' para verificación.");
        }

        $manifestContent = $zip->getFromName('manifest.json');
        if ($manifestContent === false) {
            $zip->close();
            throw new RuntimeException("CORRUPTED_BACKUP: El archivo no contiene 'manifest.json'.");
        }

        $manifest = json_decode($manifestContent, true);
        if (!$manifest || !isset($manifest['checksum_algoritmo'])) {
            $zip->close();
            throw new RuntimeException("CORRUPTED_BACKUP: 'manifest.json' está dañado o no tiene formato válido.");
        }

        $algo = $manifest['checksum_algoritmo'];

        // 1. Verificar checksums de datos base (data.sql y data.json)
        if (isset($manifest['checksums']['data_sql'])) {
            $dataSql = $zip->getFromName('database/data.sql');
            if ($dataSql === false || hash($algo, $dataSql) !== $manifest['checksums']['data_sql']) {
                $zip->close();
                throw new RuntimeException("CORRUPTED_BACKUP_CHECKSUM_MISMATCH: Checksum no coincide para database/data.sql.");
            }
        }

        if (isset($manifest['checksums']['data_json'])) {
            $dataJson = $zip->getFromName('database/data.json');
            if ($dataJson === false || hash($algo, $dataJson) !== $manifest['checksums']['data_json']) {
                $zip->close();
                throw new RuntimeException("CORRUPTED_BACKUP_CHECKSUM_MISMATCH: Checksum no coincide para database/data.json.");
            }
        }

        // 2. Verificar archivos de esquema y migraciones
        if (!empty($manifest['archivos_esquema'])) {
            foreach ($manifest['archivos_esquema'] as $name => $meta) {
                $content = $zip->getFromName($meta['path']);
                if ($content === false || hash($algo, $content) !== $meta['sha256']) {
                    $zip->close();
                    throw new RuntimeException("CORRUPTED_BACKUP_CHECKSUM_MISMATCH: Checksum alterado o archivo faltante en {$meta['path']}.");
                }
            }
        }

        // 3. Verificar archivos persistentes
        if (!empty($manifest['archivos_persistentes']['elementos'])) {
            foreach ($manifest['archivos_persistentes']['elementos'] as $item) {
                $fileContent = $zip->getFromName($item['archive_path']);
                if ($fileContent === false || hash($algo, $fileContent) !== $item['sha256']) {
                    $zip->close();
                    throw new RuntimeException("CORRUPTED_BACKUP_CHECKSUM_MISMATCH: Imagen o archivo persistente corrupto en {$item['archive_path']}.");
                }
            }
        }

        $zip->close();

        return [
            'verified' => true,
            'manifest' => $manifest
        ];
    }

    /**
     * Restaura una base de datos y archivos persistentes desde un archivo de backup verificado.
     *
     * @param string $archiveZipPath Ruta física al archivo de backup
     * @param PDO $targetPdo Conexión PDO a la base de datos destino
     * @param array $options Opciones de restauración (force, verify_only, restore_files)
     * @return array Resumen de la restauración realizada
     */
    public static function restoreArchive(string $archiveZipPath, ?PDO $targetPdo = null, array $options = []): array {
        // 1. Verificación previa obligatoria de checksums e integridad
        $verification = self::verifyArchive($archiveZipPath);
        $manifest = $verification['manifest'];

        $isVerifyOnly = !empty($options['verify_only']);
        $isForce = !empty($options['force']);

        if ($isVerifyOnly) {
            return [
                'success'     => true,
                'mode'        => 'verify_only',
                'message'     => 'Integridad del paquete de backup y checksums SHA-256 verificados exitosamente.',
                'manifest'    => $manifest
            ];
        }

        if (!$isForce) {
            throw new RuntimeException('CONFIRMATION_REQUIRED: Debe especificar la opción --force para proceder con la restauración de la base de datos.');
        }

        if (!$targetPdo) {
            throw new InvalidArgumentException('Se requiere una conexión válida a la base de datos para ejecutar la restauración.');
        }

        $zip = new ZipArchive();
        if ($zip->open($archiveZipPath) !== true) {
            throw new RuntimeException("No se pudo abrir el archivo ZIP para restauración.");
        }

        $driver = $targetPdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $baseDir = dirname(__DIR__);

        // 2. Extraer datos JSON de todas las tablas
        $dataJsonContent = $zip->getFromName('database/data.json');
        if ($dataJsonContent === false) {
            $zip->close();
            throw new RuntimeException("No se pudo leer database/data.json dentro del archivo.");
        }
        $tablesData = json_decode($dataJsonContent, true);

        // Desactivar temporalmente validación de claves foráneas
        if ($driver === 'sqlite') {
            $targetPdo->exec('PRAGMA foreign_keys = OFF');
        } elseif ($driver === 'mysql') {
            $targetPdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        }

        $restoredCounts = [];

        try {
            // Orden de inserción para respetar dependencias
            $order = [
                'schema_migrations',
                'secuencias',
                'configuracion',
                'categorias',
                'usuarios',
                'productos',
                'cotizaciones',
                'libro_reclamaciones'
            ];

            // Limpiar y restaurar tabla por tabla
            foreach ($order as $table) {
                if (!isset($tablesData[$table])) {
                    continue;
                }

                // Truncar o vaciar tabla existente
                try {
                    $targetPdo->exec("DELETE FROM {$table}");
                } catch (Throwable $e) {
                    // Si la tabla no existe en la BD destino y estamos en SQLite de prueba, crearla si es necesario
                }

                $rows = $tablesData[$table];
                $inserted = 0;

                if (!empty($rows)) {
                    $cols = array_keys($rows[0]);
                    $colNames = implode(', ', array_map(function($c) use ($driver) {
                        return $driver === 'mysql' ? "`$c`" : "\"$c\"";
                    }, $cols));
                    $placeholders = implode(', ', array_fill(0, count($cols), '?'));

                    $insertSql = "INSERT INTO {$table} ({$colNames}) VALUES ({$placeholders})";
                    $stmt = $targetPdo->prepare($insertSql);

                    foreach ($rows as $row) {
                        $vals = [];
                        foreach ($cols as $c) {
                            $vals[] = $row[$c];
                        }
                        $stmt->execute($vals);
                        $inserted++;
                    }
                }

                $restoredCounts[$table] = $inserted;

                // Verificar que el número de registros insertados coincida con el manifest
                $expectedCount = $manifest['tablas'][$table]['row_count'] ?? null;
                if ($expectedCount !== null && $inserted !== $expectedCount) {
                    throw new RuntimeException("Discrepancia en restauración de '$table': Se esperaban $expectedCount filas, se restauraron $inserted.");
                }
            }

            // Tabla rate_limits por política se limpia y reinicia vacía
            try {
                $targetPdo->exec("DELETE FROM rate_limits");
            } catch (Throwable $ignore) {}

            // 3. Restaurar archivos persistentes gestionados
            $restoredFilesCount = 0;
            if (!empty($options['restore_files']) && !empty($manifest['archivos_persistentes']['elementos'])) {
                foreach ($manifest['archivos_persistentes']['elementos'] as $item) {
                    $archivePath = $item['archive_path'];
                    $destRel = $item['original_rel_path'];

                    // Prevenir Path Traversal en descompresión
                    if (str_contains($destRel, '..') || str_starts_with($destRel, '/') || str_starts_with($destRel, '\\')) {
                        throw new RuntimeException("Ruta insegura detectada en manifest: '$destRel'");
                    }

                    $destFull = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $destRel);
                    $destFolder = dirname($destFull);
                    if (!is_dir($destFolder)) {
                        @mkdir($destFolder, 0750, true);
                    }

                    $content = $zip->getFromName($archivePath);
                    if ($content !== false) {
                        file_put_contents($destFull, $content);
                        $restoredFilesCount++;
                    }
                }
            }

        } finally {
            // Re-activar validación de claves foráneas
            if ($driver === 'sqlite') {
                $targetPdo->exec('PRAGMA foreign_keys = ON');
            } elseif ($driver === 'mysql') {
                $targetPdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
            $zip->close();
        }

        return [
            'success'               => true,
            'mode'                  => 'restored',
            'tablas_restauradas'    => $restoredCounts,
            'archivos_restaurados'  => $restoredFilesCount,
            'manifest'              => $manifest
        ];
    }

    /**
     * Elimina backups antiguos en el directorio para evitar saturación de disco.
     */
    public static function purgeOldBackups(string $directory, int $days = 7): int {
        if (!is_dir($directory) || $days <= 0) {
            return 0;
        }

        $thresholdTime = time() - ($days * 86400);
        $purged = 0;

        $files = scandir($directory) ?: [];
        foreach ($files as $f) {
            if (str_starts_with($f, 'dp_backup_') && str_ends_with($f, '.zip')) {
                $fullPath = $directory . DIRECTORY_SEPARATOR . $f;
                if (is_file($fullPath) && filemtime($fullPath) < $thresholdTime) {
                    if (@unlink($fullPath)) {
                        $purged++;
                    }
                }
            }
        }

        return $purged;
    }
}
