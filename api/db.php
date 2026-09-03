<?php
/**
 * Conexión a la Base de Datos MySQL con PDO
 */

require_once __DIR__ . '/config.php';

function getDbConnection() {
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
        return $pdo;
    } catch (PDOException $e) {
        // En caso de que la BD no esté iniciada o configurada, respondemos con código 503
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'error'   => 'Error al conectar con la base de datos MySQL.',
            'detail'  => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

