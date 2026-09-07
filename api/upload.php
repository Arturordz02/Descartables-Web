<?php
/**
 * API REST: Subida de Imágenes de Productos
 * Plataforma Descartables Peruanos
 */

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // 1. Verificación estricta de autorización de Administrador
    if (class_exists('Vault')) {
        Vault::requireAdmin();
    }

    $uploadDir = __DIR__ . '/../assets/images/productos/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    // Lista blanca estricta e inmutable de extensiones permitidas
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    // Caso 1: Archivo Multipart ($_FILES)
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['imagen'];
        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension'] ?? '');
        if ($extension === 'jpeg') $extension = 'jpg';

        if (!in_array($extension, $allowedExtensions, true)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Formato no permitido. Solo se aceptan imágenes JPG, PNG o WEBP.'
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

        // Validación binaria profunda de imagen
        $imgInfo = @getimagesize($file['tmp_name']);
        if ($imgInfo === false) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'El archivo suministrado no es una imagen válida o contiene datos corruptos.'
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
        if (preg_match('/^data:image\/([a-zA-Z0-9_-]+);base64,/', $base64, $matches)) {
            $ext = strtolower($matches[1]);
            if ($ext === 'jpeg') $ext = 'jpg';
            if (!in_array($ext, $allowedExtensions, true)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Formato Base64 no permitido. Solo se aceptan JPG, PNG y WEBP.'
                ], JSON_UNESCAPED_UNICODE);
                exit();
            }
            $base64 = substr($base64, strpos($base64, ',') + 1);
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Cabecera de imagen en Base64 no válida.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $decoded = base64_decode($base64);
        if ($decoded !== false) {
            // Verificación binaria de imagen
            $imgInfo = function_exists('getimagesizefromstring') ? @getimagesizefromstring($decoded) : true;
            if ($imgInfo === false) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error'   => 'El contenido binario decodificado no corresponde a una imagen válida.'
                ], JSON_UNESCAPED_UNICODE);
                exit();
            }

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
