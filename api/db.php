<?php
/**
 * Conexión a la Base de Datos MySQL con PDO
 * Plataforma Descartables Peruanos
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/response.php';

function getDbConnection() {
    if (isset($GLOBALS['TEST_DB_PDO']) && $GLOBALS['TEST_DB_PDO'] instanceof PDO) {
        return $GLOBALS['TEST_DB_PDO'];
    }

    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $pdo->exec("SET NAMES utf8mb4");

        return $pdo;
    } catch (PDOException $e) {
        // En caso de fallo de conexión en ambiente web, retornar código 503 seguro
        if (php_sapi_name() !== 'cli') {
            ApiResponse::error(
                'Error al conectar con la base de datos MySQL.',
                'DB_CONNECTION_FAILED',
                503,
                $e
            );
        }
        throw $e;
    }
}
