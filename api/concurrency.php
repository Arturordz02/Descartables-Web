<?php
/**
 * Motor de Concurrencia, Secuencias Atómicas e Idempotencia (F10)
 * Plataforma Descartables Peruanos
 */

class ConcurrencyEngine {

    /**
     * Normaliza un payload para generar un hash reproducible (fingerprint),
     * eliminando campos generados o recalculados por el servidor.
     */
    public static function normalizePayloadForFingerprint(array $data): string {
        $excludedKeys = [
            'id',
            'codigo',
            'codigo_cotizacion',
            'codigo_hoja',
            'codigo_resultado',
            'creado_en',
            'fecha_creacion',
            'fecha',
            'fecha_respuesta',
            'estado',
            'total_items',
            'ip',
            'user_agent',
            'idempotency_key',
            'Idempotency-Key',
            'X-Idempotency-Key'
        ];

        $cleaned = self::recursiveFilterAndSort($data, $excludedKeys);
        return hash('sha256', json_encode($cleaned, JSON_UNESCAPED_UNICODE));
    }

    private static function recursiveFilterAndSort(array $data, array $excludedKeys): array {
        $result = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $excludedKeys, true)) {
                continue;
            }
            if (is_array($v)) {
                $result[$k] = self::recursiveFilterAndSort($v, $excludedKeys);
            } else {
                $result[$k] = is_string($v) ? trim($v) : $v;
            }
        }
        ksort($result);
        return $result;
    }

    /**
     * Extrae la clave de idempotencia desde cabeceras HTTP o cuerpo de la petición.
     */
    public static function extractIdempotencyKey(?array $payload = null): ?string {
        $key = null;
        if (!empty($_SERVER['HTTP_IDEMPOTENCY_KEY'])) {
            $key = trim($_SERVER['HTTP_IDEMPOTENCY_KEY']);
        } elseif (!empty($_SERVER['HTTP_X_IDEMPOTENCY_KEY'])) {
            $key = trim($_SERVER['HTTP_X_IDEMPOTENCY_KEY']);
        } elseif (!empty($payload['idempotency_key'])) {
            $key = trim((string)$payload['idempotency_key']);
        }

        if ($key !== null && $key !== '') {
            // Sanitizar caracteres válidos para la clave (UUID, alfanumérico, guiones)
            $clean = preg_replace('/[^a-zA-Z0-9_\-\.:]/', '', $key);
            return substr($clean, 0, 100);
        }
        return null;
    }

    /**
     * Genera el siguiente correlativo atómico sin huecos ni colisiones para el tipo y año dados.
     * DEBE ejecutarse dentro de una transacción activa ($pdo->beginTransaction()).
     */
    public static function nextCorrelative(PDO $pdo, string $tipo, int $anio): string {
        $tipo = strtoupper(trim($tipo));
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("INSERT OR IGNORE INTO secuencias (tipo, anio, ultimo_numero) VALUES ('{$tipo}', {$anio}, 0)");
            $stmt = $pdo->prepare("SELECT ultimo_numero FROM secuencias WHERE tipo = ? AND anio = ?");
            $stmt->execute([$tipo, $anio]);
            $curr = (int)$stmt->fetchColumn();
            $next = $curr + 1;

            $update = $pdo->prepare("UPDATE secuencias SET ultimo_numero = ? WHERE tipo = ? AND anio = ?");
            $update->execute([$next, $tipo, $anio]);
        } else {
            // MySQL / MariaDB con bloqueo de fila mediante FOR UPDATE
            $stmtInit = $pdo->prepare("INSERT INTO secuencias (tipo, anio, ultimo_numero) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE tipo = tipo");
            $stmtInit->execute([$tipo, $anio]);

            $stmtLock = $pdo->prepare("SELECT ultimo_numero FROM secuencias WHERE tipo = ? AND anio = ? FOR UPDATE");
            $stmtLock->execute([$tipo, $anio]);
            $curr = (int)$stmtLock->fetchColumn();
            $next = $curr + 1;

            $stmtUp = $pdo->prepare("UPDATE secuencias SET ultimo_numero = ? WHERE tipo = ? AND anio = ?");
            $stmtUp->execute([$next, $tipo, $anio]);
        }

        if ($tipo === 'COTIZACION') {
            return sprintf('COT-%04d-%05d', $anio, $next);
        } elseif ($tipo === 'RECLAMACION') {
            return sprintf('REC-%04d-%05d', $anio, $next);
        } else {
            return sprintf('%s-%04d-%05d', $tipo, $anio, $next);
        }
    }

    /**
     * Verifica si una petición ya fue procesada bajo la misma clave de idempotencia.
     */
    public static function checkIdempotency(
        PDO $pdo,
        string $scope,
        ?string $key,
        string $requestHash,
        ?int $userId = null,
        ?string $documento = null
    ): array {
        if (empty($key)) {
            return ['status' => 'new'];
        }

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = "SELECT * FROM idempotencia WHERE scope = ? AND clave = ?";
        if ($driver !== 'sqlite') {
            $sql .= " FOR UPDATE";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$scope, $key]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return ['status' => 'new'];
        }

        // 1. Aislamiento multi-usuario: Validar pertenencia del usuario
        if (!empty($record['usuario_id']) && $userId !== null && (int)$record['usuario_id'] !== (int)$userId) {
            return [
                'status' => 'conflict',
                'http_code' => 403,
                'error' => 'Acceso denegado: La clave de idempotencia pertenece a otra sesión de usuario.'
            ];
        }

        // 2. Aislamiento por documento en usuarios no registrados
        if (!empty($record['documento']) && !empty($documento) && trim($record['documento']) !== trim($documento)) {
            return [
                'status' => 'conflict',
                'http_code' => 409,
                'error' => 'Conflicto de idempotencia: La clave ya fue utilizada con otro documento de identidad.'
            ];
        }

        // 3. Verificación de integridad del payload
        if ($record['request_hash'] !== $requestHash) {
            return [
                'status' => 'conflict',
                'http_code' => 409,
                'error' => 'Conflicto de idempotencia: La clave ya fue utilizada con datos diferentes.',
                'code' => 'IDEMPOTENCY_PAYLOAD_MISMATCH'
            ];
        }

        // 4. Reintento idéntico exitoso
        $cachedPayload = json_decode($record['response_payload'], true);
        return [
            'status' => 'replay',
            'codigo' => $record['codigo_resultado'],
            'response' => $cachedPayload ?: []
        ];
    }

    /**
     * Registra una respuesta oficial en la tabla de idempotencia.
     * DEBE ejecutarse dentro de la misma transacción antes del commit.
     */
    public static function saveIdempotency(
        PDO $pdo,
        string $scope,
        ?string $key,
        string $requestHash,
        string $codigoResultado,
        array $responsePayload,
        ?int $userId = null,
        ?string $documento = null
    ): void {
        if (empty($key)) {
            return;
        }

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO idempotencia (
                scope, clave, usuario_id, documento, request_hash, codigo_resultado, response_payload, creado_en
            ) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'))");
        } else {
            $stmt = $pdo->prepare("INSERT INTO idempotencia (
                scope, clave, usuario_id, documento, request_hash, codigo_resultado, response_payload, creado_en
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        }

        $stmt->execute([
            $scope,
            $key,
            $userId,
            $documento ? substr(trim($documento), 0, 20) : null,
            $requestHash,
            $codigoResultado,
            json_encode($responsePayload, JSON_UNESCAPED_UNICODE)
        ]);
    }
}
