<?php
/**
 * Motor de Rate Limiting y Control de Abuso (F11)
 * Plataforma Descartables Peruanos
 */

declare(strict_types=1);

require_once __DIR__ . '/response.php';

class RateLimiter {

    /**
     * Obtiene la IP del cliente de forma segura sin confiar ciegamente en cabeceras falsificables.
     */
    public static function getClientIp(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Solo si la IP remota es loopback/proxy local confiable se permite evaluar cabeceras intermedias
        $trustedProxies = ['127.0.0.1', '::1'];
        if (in_array($ip, $trustedProxies, true)) {
            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                $cfIp = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
                if (filter_var($cfIp, FILTER_VALIDATE_IP)) {
                    return $cfIp;
                }
            }
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $first = trim($parts[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
    }

    /**
     * Verifica si una solicitud excede los límites de velocidad por IP y por Usuario.
     * Retorna ['allowed' => true] o ['allowed' => false, 'retry_after' => $segundos].
     */
    public static function check(
        PDO $pdo,
        string $action,
        int $maxAttempts,
        int $windowSeconds,
        int $blockSeconds = 0,
        ?int $userId = null
    ): array {
        $clientIp = self::getClientIp();
        $now = time();
        $blockDuration = $blockSeconds > 0 ? $blockSeconds : $windowSeconds;

        $identifiers = ["ip:" . $clientIp];
        if ($userId !== null && $userId > 0) {
            $identifiers[] = "usr:" . $userId;
        }

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        foreach ($identifiers as $ident) {
            $stmt = $pdo->prepare("SELECT attempts, window_start, blocked_until FROM rate_limits WHERE identifier = ? AND action = ?");
            $stmt->execute([$ident, $action]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $blockedUntil = (int)$row['blocked_until'];
                $windowStart = (int)$row['window_start'];
                $attempts = (int)$row['attempts'];

                // 1. Si está bloqueado actualmente
                if ($blockedUntil > $now) {
                    $retryAfter = $blockedUntil - $now;
                    return ['allowed' => false, 'retry_after' => max(1, $retryAfter), 'identifier' => $ident];
                }

                // 2. Si la ventana de tiempo ya expiró, reiniciar conteo
                if (($now - $windowStart) > $windowSeconds) {
                    $updateStmt = $pdo->prepare("UPDATE rate_limits SET attempts = 1, window_start = ?, blocked_until = 0 WHERE identifier = ? AND action = ?");
                    $updateStmt->execute([$now, $ident, $action]);
                } else {
                    // 3. Dentro de la ventana activa
                    if ($attempts >= $maxAttempts) {
                        $newBlockedUntil = $now + $blockDuration;
                        $updateStmt = $pdo->prepare("UPDATE rate_limits SET blocked_until = ? WHERE identifier = ? AND action = ?");
                        $updateStmt->execute([$newBlockedUntil, $ident, $action]);
                        return ['allowed' => false, 'retry_after' => $blockDuration, 'identifier' => $ident];
                    }

                    $updateStmt = $pdo->prepare("UPDATE rate_limits SET attempts = attempts + 1 WHERE identifier = ? AND action = ?");
                    $updateStmt->execute([$ident, $action]);
                }
            } else {
                // Nuevo registro
                if ($driver === 'sqlite') {
                    $insertStmt = $pdo->prepare("INSERT INTO rate_limits (identifier, action, attempts, window_start, blocked_until) VALUES (?, ?, 1, ?, 0)");
                } else {
                    $insertStmt = $pdo->prepare("INSERT INTO rate_limits (identifier, action, attempts, window_start, blocked_until) VALUES (?, ?, 1, ?, 0) ON DUPLICATE KEY UPDATE attempts = attempts + 1");
                }
                $insertStmt->execute([$ident, $action, $now]);
            }
        }

        return ['allowed' => true];
    }

    /**
     * Aplica el control de Rate Limit y detiene la ejecución si fue superado.
     */
    public static function enforce(
        PDO $pdo,
        string $action,
        int $maxAttempts,
        int $windowSeconds,
        int $blockSeconds = 0,
        ?int $userId = null
    ): void {
        $result = self::check($pdo, $action, $maxAttempts, $windowSeconds, $blockSeconds, $userId);

        if (!$result['allowed']) {
            $retryAfter = $result['retry_after'] ?? $windowSeconds;
            if (php_sapi_name() !== 'cli' && !headers_sent()) {
                header('Retry-After: ' . $retryAfter);
            }
            ApiResponse::error(
                'Demasiadas solicitudes en poco tiempo. Por favor espere antes de reintentar.',
                'RATE_LIMIT_EXCEEDED',
                429,
                null,
                ['retry_after' => $retryAfter]
            );
        }
    }
}

