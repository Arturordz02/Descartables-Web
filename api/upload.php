<?php
/**
 * API REST: Subida Segura de Imágenes de Productos (F12)
 * Plataforma Descartables Peruanos
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validator.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (empty($GLOBALS['UPLOAD_SCRIPT_INCLUDED'])) {
    if ($method !== 'POST') {
        ApiResponse::error('Método no permitido.', 'METHOD_NOT_ALLOWED', 405);
        return;
    }

    // 1. Verificación estricta de autorización de Administrador
    if (class_exists('Vault')) {
        $admin = Vault::requireAdmin();
        if (!$admin) {
            return;
        }
    }
}

$uploadDir = realpath(__DIR__ . '/../assets/images/productos');
if (!$uploadDir || !is_dir($uploadDir)) {
    $targetDir = __DIR__ . '/../assets/images/productos';
    @mkdir($targetDir, 0755, true);
    $uploadDir = realpath($targetDir);
}

if (!$uploadDir || !is_writable($uploadDir)) {
    ApiResponse::error('El directorio de almacenamiento no está disponible para escritura.', 'STORAGE_UNAVAILABLE', 500);
    return;
}

// Parámetros de seguridad estrictos
$maxSizeBytes = 5 * 1024 * 1024; // Máximo 5 MB
$maxDimension = 4000;            // Máximo 4000x4000 px
$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
];

/**
 * Valida de forma exhaustiva una imagen (tamaño, MIME real, dimensiones, magic bytes y polyglots).
 * Retorna un arreglo con ['valid' => true, 'mime' => ..., 'ext' => ..., 'width' => ..., 'height' => ...] o ['valid' => false, 'error' => ...].
 */
function validateImageBinary(string $filePathOrBuffer, bool $isBuffer, int $maxBytes, int $maxDim, array $allowedMimes): array {
    $size = $isBuffer ? strlen($filePathOrBuffer) : @filesize($filePathOrBuffer);

    // 1. Validar tamaño máximo (5 MB)
    if ($size === false || $size <= 0 || $size > $maxBytes) {
        return ['valid' => false, 'error' => 'La imagen supera el límite máximo permitido de 5 MB.'];
    }

    $bufferHead = $isBuffer ? substr($filePathOrBuffer, 0, 1024) : @file_get_contents($filePathOrBuffer, false, null, 0, 1024);
    if ($bufferHead === false || strlen($bufferHead) < 12) {
        return ['valid' => false, 'error' => 'El archivo no contiene datos de imagen válidos.'];
    }

    // 2. Rechazar SVG explícitamente (prevención XSS/XML)
    if (stripos($bufferHead, '<svg') !== false || stripos($bufferHead, '<?xml') !== false || stripos($bufferHead, '<!doctype svg') !== false) {
        return ['valid' => false, 'error' => 'Formato SVG no permitido por políticas de seguridad. Solo se aceptan JPG, PNG y WebP.'];
    }

    // 3. Inspección MIME profunda con finfo
    $realMime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = $isBuffer ? finfo_buffer($finfo, $filePathOrBuffer) : finfo_file($finfo, $filePathOrBuffer);
        finfo_close($finfo);
    }

    if (!$realMime || !array_key_exists($realMime, $allowedMimes)) {
        return ['valid' => false, 'error' => 'Formato de imagen no permitido. Solo se aceptan imágenes JPG, PNG o WebP reales.'];
    }

    // 4. Verificación estricta de Magic Bytes
    $isMagicValid = false;
    if ($realMime === 'image/jpeg' && str_starts_with($bufferHead, "\xFF\xD8\xFF")) {
        $isMagicValid = true;
    } elseif ($realMime === 'image/png' && str_starts_with($bufferHead, "\x89PNG\r\n\x1a\n")) {
        $isMagicValid = true;
    } elseif ($realMime === 'image/webp') {
        if (str_starts_with($bufferHead, 'RIFF') && substr($bufferHead, 8, 4) === 'WEBP') {
            $isMagicValid = true;
        }
    }

    if (!$isMagicValid) {
        return ['valid' => false, 'error' => 'La cabecera binaria del archivo no corresponde a una imagen JPG, PNG o WebP válida.'];
    }

    // 5. Verificación de dimensiones con getimagesize
    $imgInfo = $isBuffer ? @getimagesizefromstring($filePathOrBuffer) : @getimagesize($filePathOrBuffer);
    if ($imgInfo === false) {
        return ['valid' => false, 'error' => 'El archivo suministrado no es una imagen válida o contiene datos corruptos.'];
    }

    $width = (int)$imgInfo[0];
    $height = (int)$imgInfo[1];
    $imgType = (int)$imgInfo[2];

    if ($width < 1 || $height < 1 || $width > $maxDim || $height > $maxDim) {
        return ['valid' => false, 'error' => "Las dimensiones de la imagen ({$width}x{$height} px) superan el máximo permitido de {$maxDim}x{$maxDim} píxeles."];
    }

    // Validar tipo interno de imagen
    if ($realMime === 'image/jpeg' && $imgType !== IMAGETYPE_JPEG) {
        return ['valid' => false, 'error' => 'El tipo interno del archivo no corresponde a JPEG.'];
    }
    if ($realMime === 'image/png' && $imgType !== IMAGETYPE_PNG) {
        return ['valid' => false, 'error' => 'El tipo interno del archivo no corresponde a PNG.'];
    }
    if ($realMime === 'image/webp') {
        if (!defined('IMAGETYPE_WEBP') || $imgType !== IMAGETYPE_WEBP) {
            return ['valid' => false, 'error' => 'El entorno del servidor no puede validar imágenes WebP de forma segura.'];
        }
    }

    // 6. Detección de Polyglot / Scripts en contenido binario
    $rawAll = $isBuffer ? $filePathOrBuffer : @file_get_contents($filePathOrBuffer);
    if ($rawAll !== false) {
        $suspiciousTags = ['<?php', '<?=', '<script', '<html', '<head', '<body', 'onload=', 'onerror='];
        foreach ($suspiciousTags as $tag) {
            if (stripos($rawAll, $tag) !== false) {
                return ['valid' => false, 'error' => 'Contenido sospechoso o script detectado en el archivo de imagen.'];
            }
        }
    }

    return [
        'valid'  => true,
        'mime'   => $realMime,
        'ext'    => $allowedMimes[$realMime],
        'width'  => $width,
        'height' => $height
    ];
}

/**
 * Guarda o recodifica la imagen de forma segura hacia el archivo de destino.
 */
function saveSanitizedImage(string $srcPathOrBuffer, bool $isBuffer, string $destPath, string $mime): bool {
    // Si la extensión GD está disponible, recodificar para limpiar metadatos residuales
    if (extension_loaded('gd')) {
        $img = null;
        if ($mime === 'image/jpeg') {
            $img = $isBuffer ? @imagecreatefromstring($srcPathOrBuffer) : @imagecreatefromjpeg($srcPathOrBuffer);
            if ($img !== false && $img !== null) {
                $saved = imagejpeg($img, $destPath, 90);
                imagedestroy($img);
                if ($saved) {
                    @chmod($destPath, 0644);
                    return true;
                }
            }
        } elseif ($mime === 'image/png') {
            $img = $isBuffer ? @imagecreatefromstring($srcPathOrBuffer) : @imagecreatefrompng($srcPathOrBuffer);
            if ($img !== false && $img !== null) {
                imagealphablending($img, false);
                imagesavealpha($img, true);
                $saved = imagepng($img, $destPath, 8);
                imagedestroy($img);
                if ($saved) {
                    @chmod($destPath, 0644);
                    return true;
                }
            }
        } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
            $img = $isBuffer ? @imagecreatefromstring($srcPathOrBuffer) : @imagecreatefromwebp($srcPathOrBuffer);
            if ($img !== false && $img !== null) {
                $saved = imagewebp($img, $destPath, 90);
                imagedestroy($img);
                if ($saved) {
                    @chmod($destPath, 0644);
                    return true;
                }
            }
        }
    }

    // Si GD no está disponible o la recodificación no aplica, escribir archivo directamente tras validación binaria
    if ($isBuffer) {
        $saved = (file_put_contents($destPath, $srcPathOrBuffer) !== false);
    } else {
        $saved = move_uploaded_file($srcPathOrBuffer, $destPath);
    }

    if ($saved) {
        @chmod($destPath, 0644);
        return true;
    }

    return false;
}

// =========================================================================
// PROCESAMIENTO DE SOLICITUDES: MULTIPART O JSON BASE64
// =========================================================================
if (empty($GLOBALS['UPLOAD_SCRIPT_INCLUDED'])) {
    // CASO 1: Archivo Multipart ($_FILES['imagen'])
    if (isset($_FILES['imagen'])) {
        $file = $_FILES['imagen'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = 'Error al recibir el archivo en el servidor.';
            $errCode = 'UPLOAD_ERROR';
            $statusCode = 400;
            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                $errorMsg = 'La imagen supera el límite máximo permitido de 5 MB.';
                $errCode = 'PAYLOAD_TOO_LARGE';
                $statusCode = 413;
            }
            ApiResponse::error($errorMsg, $errCode, $statusCode);
            return;
        }

        $validation = validateImageBinary($file['tmp_name'], false, $maxSizeBytes, $maxDimension, $allowedMimes);
        if (!$validation['valid']) {
            $isOversized = str_contains($validation['error'], '5 MB');
            ApiResponse::error($validation['error'], $isOversized ? 'PAYLOAD_TOO_LARGE' : 'INVALID_IMAGE', $isOversized ? 413 : 400);
            return;
        }

        // Generar nombre físico criptográfico e impredecible (descarta nombre original)
        $randomToken = bin2hex(random_bytes(16));
        $uniqueFileName = 'prod_' . $randomToken . '.' . $validation['ext'];
        $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $uniqueFileName;

        if (saveSanitizedImage($file['tmp_name'], false, $targetPath, $validation['mime'])) {
            $publicUrl = 'assets/images/productos/' . $uniqueFileName;
            ApiResponse::success(null, [
                'message'   => 'Imagen subida correctamente.',
                'url'       => $publicUrl,
                'file_name' => $uniqueFileName
            ]);
            return;
        }

        ApiResponse::error('Error al guardar la imagen en el servidor.', 'STORAGE_WRITE_FAILED', 500);
        return;
    }

    // CASO 2: Payload JSON con Base64
    $jsonData = Validator::parseJsonInput(7 * 1024 * 1024); // Permite overhead de Base64
    if ($jsonData && !empty($jsonData['imagen_base64'])) {
        $base64 = trim((string)$jsonData['imagen_base64']);

        if (preg_match('/^data:image\/([a-zA-Z0-9_\-\+]+);base64,/', $base64, $matches)) {
            $declaredFormat = strtolower($matches[1]);
            if (!in_array($declaredFormat, ['jpeg', 'jpg', 'png', 'webp'], true)) {
                ApiResponse::error('Formato Base64 no permitido. Solo se aceptan JPG, PNG y WebP.', 'INVALID_IMAGE_FORMAT', 400);
                return;
            }
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }

        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            ApiResponse::error('Datos Base64 corruptos o inválidos.', 'INVALID_BASE64', 400);
            return;
        }

        $validation = validateImageBinary($decoded, true, $maxSizeBytes, $maxDimension, $allowedMimes);
        if (!$validation['valid']) {
            $isOversized = str_contains($validation['error'], '5 MB');
            ApiResponse::error($validation['error'], $isOversized ? 'PAYLOAD_TOO_LARGE' : 'INVALID_IMAGE', $isOversized ? 413 : 400);
            return;
        }

        // Generar nombre físico criptográfico e impredecible
        $randomToken = bin2hex(random_bytes(16));
        $uniqueFileName = 'prod_' . $randomToken . '.' . $validation['ext'];
        $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $uniqueFileName;

        if (saveSanitizedImage($decoded, true, $targetPath, $validation['mime'])) {
            $publicUrl = 'assets/images/productos/' . $uniqueFileName;
            ApiResponse::success(null, [
                'message'   => 'Imagen guardada correctamente.',
                'url'       => $publicUrl,
                'file_name' => $uniqueFileName
            ]);
            return;
        }

        ApiResponse::error('Error al escribir el archivo de imagen en el servidor.', 'STORAGE_WRITE_FAILED', 500);
        return;
    }

    ApiResponse::error('No se recibió un archivo o formato de imagen válido.', 'INVALID_REQUEST', 400);
    return;
}
