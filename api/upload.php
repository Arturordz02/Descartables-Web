<?php
/**
 * API REST: Subida de Imágenes de Productos
 * Plataforma Descartables Peruanos
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $uploadDir = __DIR__ . '/../assets/images/productos/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    // Caso 1: Archivo Multipart ($_FILES)
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['imagen'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'];
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

        if ($file['size'] > 10 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'La imagen supera el límite máximo permitido de 10 MB.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
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
        }
    }

    // Caso 2: Payload JSON con Base64
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    if ($jsonData && !empty($jsonData['imagen_base64'])) {
        $base64 = $jsonData['imagen_base64'];
        $ext = 'jpg';
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
            $ext = strtolower($matches[1]);
            if ($ext === 'jpeg') $ext = 'jpg';
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }
        $decoded = base64_decode($base64);
        if ($decoded !== false) {
            $uniqueFileName = 'prod_' . time() . '_' . uniqid() . '.' . $ext;
            $targetPath = $uploadDir . $uniqueFileName;
            if (file_put_contents($targetPath, $decoded)) {
                $publicUrl = 'assets/images/productos/' . $uniqueFileName;
                echo json_encode([
                    'success'   => true,
                    'message'   => 'Imagen guardada correctamente.',
                    'url'       => $publicUrl,
                    'file_name' => $uniqueFileName
                ], JSON_UNESCAPED_UNICODE);
                exit();
            }
        }
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'No se recibió un archivo o formato de imagen válido.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);
