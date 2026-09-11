<?php
/**
 * ====================================================================
 * MANEJO CENTRALIZADO DE RESPUESTAS, LOGGING PRIVADO Y ERRORES (F13)
 * Plataforma Descartables Peruanos
 * ====================================================================
 * 
 * Provee:
 * 1. ApiResponse: Formato JSON unificado con request_id para toda la API.
 * 2. Logger: Registro privado en logs/ con sanitización estricta (sin contraseñas,
 *    tokens ni documentos en texto plano).
 * 3. Captura global de excepciones no controladas para evitar filtraciones HTML / SQLSTATE.
 */

declare(strict_types=1);

if (!class_exists('Logger')) {
    class Logger {
        private const MASKED_STRING = '***';

        /**
         * Retorna la ruta absoluta del directorio de logs.
         */
        public static function getLogDirectory(): string {
            // Prioridad: variable de entorno LOG_DIR -> configuración de Vault -> logs/ en raíz
            $envDir = getenv('LOG_DIR') ?: ($_ENV['LOG_DIR'] ?? null);
            if ($envDir && is_string($envDir)) {
                return rtrim($envDir, '/\\');
            }

            if (class_exists('Vault')) {
                $vaultDir = Vault::get('logging.log_dir');
                if ($vaultDir && is_string($vaultDir)) {
                    return rtrim($vaultDir, '/\\');
                }
            }

            return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
        }

        /**
         * Sanitiza estructuras de datos evitando volcar secretos en logs.
         */
        public static function sanitize($data, int $depth = 0) {
            if ($depth > 5) {
                return '[MAX_DEPTH]';
            }

            if (is_array($data)) {
                $sanitized = [];
                foreach ($data as $k => $v) {
                    $lowerK = strtolower((string)$k);
                    // Claves sensibles bloqueadas incondicionalmente
                    if (preg_match('/(pass|password|pwd|secret|token|salt|auth|cookie|authorization|psig|cvv|tarjeta|card|key)/i', $lowerK)) {
                        $sanitized[$k] = '[REDACTED]';
                    } else {
                        $sanitized[$k] = self::sanitize($v, $depth + 1);
                    }
                }
                return $sanitized;
            }

            if (is_string($data)) {
                // Enmascarar correos electrónicos: j***@dominio.com
                $data = preg_replace_callback('/([a-zA-Z0-9_.+-])[a-zA-Z0-9_.+-]*(@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+)/', function ($m) {
                    return $m[1] . '***' . $m[2];
                }, $data);

                // Enmascarar números de documento (DNI 8 dígitos, RUC 11 dígitos): conserva solo últimos 4
                $data = preg_replace_callback('/\b\d{8,11}\b/', function ($m) {
                    $doc = $m[0];
                    return '***' . substr($doc, -4);
                }, $data);

                // Truncar cadenas excesivamente largas en logs (prevenir saturación)
                if (strlen($data) > 500) {
                    $data = substr($data, 0, 490) . '...[TRUNCATED]';
                }

                return $data;
            }

            return $data;
        }

        /**
         * Escribe una línea estructurada en el log privado del servidor.
         */
        public static function write(string $level, string $message, array $context = []): void {
            $dir = self::getLogDirectory();
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }

            $requestId = ApiResponse::getRequestId();
            $timestamp = date('Y-m-d H:i:s');
            $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
            $uri = $_SERVER['REQUEST_URI'] ?? 'local';

            // Sanitizar contexto
            $sanitizedContext = self::sanitize($context);
            $contextStr = !empty($sanitizedContext) ? ' ' . json_encode($sanitizedContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

            $cleanMessage = self::sanitize($message);
            $line = sprintf(
                "[%s] [%s] [%s] [%s %s] %s%s\n",
                $timestamp,
                strtoupper($level),
                $requestId,
                $method,
                $uri,
                $cleanMessage,
                $contextStr
            );

            $logFile = $dir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m') . '.log';
            $written = @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

            // Respaldo transparente en error_log de PHP si el directorio local no tuviera permisos
            if ($written === false) {
                @error_log("[DP_APP_LOG] " . trim($line));
            }
        }

        public static function info(string $message, array $context = []): void {
            self::write('INFO', $message, $context);
        }

        public static function warning(string $message, array $context = []): void {
            self::write('WARNING', $message, $context);
        }

        public static function error(string $message, array $context = []): void {
            self::write('ERROR', $message, $context);
        }

        public static function critical(string $message, array $context = []): void {
            self::write('CRITICAL', $message, $context);
        }
    }
}

if (!class_exists('ApiResponse')) {
    class ApiResponse {
        private static ?string $requestId = null;

        /**
         * Inicializa o reinicia el Request ID de la solicitud actual.
         */
        public static function initRequestId(?string $override = null): string {
            self::$requestId = $override;
            return self::getRequestId();
        }

        /**
         * Obtiene o genera el Request ID único de la solicitud actual.
         */
        public static function getRequestId(): string {
            if (self::$requestId !== null) {
                return self::$requestId;
            }

            // Validar cabecera X-Request-Id entrante bajo un patrón estricto alfanumérico
            $incoming = null;
            if (!empty($_SERVER['HTTP_X_REQUEST_ID'])) {
                $incoming = trim((string)$_SERVER['HTTP_X_REQUEST_ID']);
            } elseif (function_exists('getallheaders')) {
                $headers = getallheaders();
                if (!empty($headers['X-Request-Id'])) {
                    $incoming = trim((string)$headers['X-Request-Id']);
                } elseif (!empty($headers['x-request-id'])) {
                    $incoming = trim((string)$headers['x-request-id']);
                }
            }

            if ($incoming && preg_match('/^[a-zA-Z0-9_\-]{8,64}$/', $incoming)) {
                self::$requestId = $incoming;
            } else {
                try {
                    self::$requestId = 'req_' . bin2hex(random_bytes(12));
                } catch (Throwable $e) {
                    self::$requestId = 'req_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 24);
                }
            }

            // Inyectar en encabezado de salida HTTP si no está en modo CLI y cabeceras no fueron enviadas
            if (php_sapi_name() !== 'cli' && !headers_sent()) {
                header('X-Request-Id: ' . self::$requestId);
            }

            return self::$requestId;
        }

        /**
         * Construye el arreglo estructurado de error para inspección o respuesta.
         */
        public static function buildErrorPayload(
            string $userMessage,
            string $code = 'ERROR',
            ?Throwable $exception = null,
            array $extra = []
        ): array {
            $reqId = self::getRequestId();
            $payload = [
                'success'    => false,
                'error'      => $userMessage,
                'code'       => $code,
                'request_id' => $reqId
            ];
            foreach ($extra as $k => $v) {
                $payload[$k] = $v;
            }
            return $payload;
        }

        /**
         * Construye el arreglo estructurado de éxito para inspección o respuesta.
         */
        public static function buildSuccessPayload($data = null, array $extra = []): array {
            $reqId = self::getRequestId();
            $payload = ['success' => true];
            if ($data !== null) {
                $payload['data'] = $data;
            }
            foreach ($extra as $k => $v) {
                $payload[$k] = $v;
            }
            $payload['request_id'] = $reqId;
            return $payload;
        }

        /**
         * Emite una respuesta de éxito JSON estandarizada.
         */
        public static function success($data = null, $messageOrExtra = null, int $httpCode = 200, array $extra = []): void {
            if (php_sapi_name() !== 'cli' && !headers_sent()) {
                http_response_code($httpCode);
                header('Content-Type: application/json; charset=utf-8');
                header('X-Request-Id: ' . self::getRequestId());
            }

            $response = [
                'success'    => true,
                'request_id' => self::getRequestId()
            ];
            $msg = null;
            $extraFields = $extra;

            if (is_string($messageOrExtra)) {
                $msg = $messageOrExtra;
            } elseif (is_array($messageOrExtra)) {
                $extraFields = array_merge($messageOrExtra, $extra);
            }

            if ($msg !== null) {
                $response['message'] = $msg;
            }
            if ($data !== null) {
                $response['data'] = $data;
            }
            foreach ($extraFields as $key => $val) {
                $response[$key] = $val;
            }

            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (php_sapi_name() !== 'cli') {
                exit();
            }
        }

        /**
         * Emite una respuesta de error JSON uniforme, protegiendo información interna.
         */
        public static function error(
            string $userMessage,
            string $code = 'ERROR',
            int $httpCode = 400,
            ?Throwable $exception = null,
            array $extra = []
        ): void {
            $reqId = self::getRequestId();

            // Registrar en log técnico privado
            if ($exception) {
                Logger::error($userMessage, [
                    'code'      => $code,
                    'http_code' => $httpCode,
                    'exception' => get_class($exception),
                    'file'      => $exception->getFile(),
                    'line'      => $exception->getLine(),
                    'detail'    => $exception->getMessage()
                ]);
            } else {
                if ($httpCode >= 500) {
                    Logger::error($userMessage, ['code' => $code, 'http_code' => $httpCode]);
                } else {
                    Logger::warning($userMessage, ['code' => $code, 'http_code' => $httpCode]);
                }
            }

            if (php_sapi_name() !== 'cli' && !headers_sent()) {
                http_response_code($httpCode);
                header('Content-Type: application/json; charset=utf-8');
                header('X-Request-Id: ' . $reqId);
            }

            $response = [
                'success'    => false,
                'error'      => $userMessage,
                'code'       => $code,
                'request_id' => $reqId
            ];

            foreach ($extra as $k => $v) {
                $response[$k] = $v;
            }

            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (php_sapi_name() !== 'cli') {
                exit();
            }
        }

        /**
         * Registra manejadores globales de excepciones no capturadas para solicitudes Web.
         */
        public static function registerGlobalHandlers(): void {
            static $registered = false;
            if ($registered || php_sapi_name() === 'cli') {
                return;
            }
            $registered = true;

            set_exception_handler(function (Throwable $e) {
                if ($e instanceof PDOException) {
                    self::error(
                        'Servicio de base de datos no disponible temporalmente.',
                        'DB_UNAVAILABLE',
                        503,
                        $e
                    );
                } else {
                    self::error(
                        'Ha ocurrido un error interno en el servidor.',
                        'INTERNAL_SERVER_ERROR',
                        500,
                        $e
                    );
                }
            });

            set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
                // Si el error fue silenciado con @ (error_reporting === 0)
                if ((error_reporting() & $errno) === 0) {
                    return false;
                }

                $fatalErrors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
                if (in_array($errno, $fatalErrors, true)) {
                    Logger::critical("PHP Fatal Error: $errstr in $errfile:$errline");
                    self::error(
                        'Error crítico interno del servidor.',
                        'FATAL_SERVER_ERROR',
                        500
                    );
                }

                // Errores menores / warnings solo se registran en log
                Logger::warning("PHP Notice/Warning [$errno]: $errstr in $errfile:$errline");
                return true;
            });
        }
    }
}

// Inicializar automáticamente Request ID y manejadores si se ejecuta en entorno web
if (php_sapi_name() !== 'cli') {
    ApiResponse::getRequestId();
    ApiResponse::registerGlobalHandlers();
}
