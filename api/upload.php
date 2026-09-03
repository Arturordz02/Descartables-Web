<?php
/**
 * API REST: Subida de Imágenes de Productos
 * Plataforma Descartables Peruanos
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'No se recibió ningún archivo de imagen válido.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $file = $_FILES['imagen'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension'] ?? '');

    if (!in_array($extension, $allowedExtensions)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Formato no permitido. Solo se aceptan imágenes JPG, PNG, WEBP o SVG.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'La imagen supera el límite máximo permitido de 5 MB.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $uploadDir = __DIR__ . '/../assets/images/productos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fileInfo['filename']);
    $uniqueFileName = 'prod_' . time() . '_' . substr($safeName, 0, 20) . '.' . $extension;
    $targetPath = $uploadDir . $uniqueFileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $publicUrl = 'assets/images/productos/' . $uniqueFileName;
        echo json_encode([
            'success'   => true,
            'message'   => 'Imagen subida correctamente.',
            'url'       => $publicUrl,
            'file_name' => $uniqueFileName
        ], JSON_UNESCAPED_UNICODE);
        exit();
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => 'Error al guardar el archivo en el servidor.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);

