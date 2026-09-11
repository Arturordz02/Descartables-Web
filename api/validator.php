<?php
/**
 * Motor Centralizado de Validaciones de Negocio y Tipos de Datos (F11)
 * Plataforma Descartables Peruanos
 */

declare(strict_types=1);

require_once __DIR__ . '/response.php';

class Validator {

    /**
     * Valida y normaliza direcciones de correo electrónico.
     */
    public static function validateEmail(?string $email): ?string {
        if ($email === null) {
            return null;
        }
        $clean = strtolower(trim($email));
        $len = strlen($clean);
        if ($len < 5 || $len > 150) {
            return null;
        }
        if (!filter_var($clean, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        // Rechazar inyecciones de saltos de línea o caracteres de control
        if (preg_match('/[\r\n\t\x00-\x1F\x7F]/', $clean)) {
            return null;
        }
        return $clean;
    }

    /**
     * Valida y normaliza documentos de identidad (DNI, RUC, CE, Pasaporte).
     * Normaliza quitando espacios, puntos y guiones sin alterar el valor alfanumérico.
     */
    public static function validateDocument(?string $doc, ?string $tipo = null): ?array {
        if ($doc === null) {
            return null;
        }

        $raw = trim($doc);
        if ($raw === '') {
            return null;
        }

        $tipo = $tipo ? strtoupper(trim($tipo)) : null;

        // Normalización básica no destructiva
        $normalizedDigits = preg_replace('/[\s\.\-]+/', '', $raw);
        $normalizedAlnum = strtoupper(preg_replace('/[\s\.]+/', '', $raw));

        // 1. DNI: exactamente 8 dígitos numéricos
        if ($tipo === 'DNI' || ($tipo === null && preg_match('/^\d{8}$/', $normalizedDigits))) {
            if (preg_match('/^\d{8}$/', $normalizedDigits)) {
                return [
                    'valid'     => true,
                    'tipo'      => 'DNI',
                    'documento' => $normalizedDigits,
                    'original'  => $raw
                ];
            }
            if ($tipo === 'DNI') {
                return null;
            }
        }

        // 2. RUC: 11 dígitos numéricos
        // Prefijos SUNAT comunes: 10 (persona natural), 20 (persona jurídica), 15/17 (otros entes)
        // Se acepta cualquier 11 dígitos numéricos para no excluir casos tributarios especiales
        if ($tipo === 'RUC' || ($tipo === null && preg_match('/^\d{11}$/', $normalizedDigits))) {
            if (preg_match('/^\d{11}$/', $normalizedDigits)) {
                return [
                    'valid'     => true,
                    'tipo'      => 'RUC',
                    'documento' => $normalizedDigits,
                    'original'  => $raw
                ];
            }
            if ($tipo === 'RUC') {
                return null;
            }
        }

        // 3. Carnet de Extranjería / Pasaporte / Otros: 4 a 15 caracteres alfanuméricos
        if (in_array($tipo, ['CE', 'PASAPORTE', 'OTRO'], true) || $tipo === null) {
            if (preg_match('/^[A-Z0-9\-]{4,15}$/', $normalizedAlnum)) {
                return [
                    'valid'     => true,
                    'tipo'      => $tipo ?: 'CE',
                    'documento' => $normalizedAlnum,
                    'original'  => $raw
                ];
            }
        }

        return null;
    }

    /**
     * Valida y normaliza números de teléfono (móvil, fijo e internacional).
     * Acepta formatos peruanos (+51..., 9..., (01)...) e internacionales con prefijo '+'.
     */
    public static function validatePhone(?string $phone): ?string {
        if ($phone === null) {
            return null;
        }

        $raw = trim($phone);
        if ($raw === '') {
            return null;
        }

        // Detectar si incluye código internacional '+'
        $hasPlus = str_starts_with($raw, '+');
        // Extraer únicamente dígitos
        $digits = preg_replace('/\D+/', '', $raw);
        $len = strlen($digits);

        // Longitud mínima razonable: 7 dígitos (fijo local); máxima: 15 dígitos (estándar E.164)
        if ($len < 7 || $len > 15) {
            return null;
        }

        // Si tenía '+', conservar formato internacional estándar E.164 (+XXXXX)
        if ($hasPlus) {
            return '+' . $digits;
        }

        // Si es número móvil peruano de 9 dígitos que empieza con 9
        if ($len === 9 && str_starts_with($digits, '9')) {
            return '+51' . $digits;
        }

        // Si ya incluye el código de Perú '51' delante de 9 dígitos móviles
        if ($len === 11 && str_starts_with($digits, '519')) {
            return '+' . $digits;
        }

        return $digits;
    }

    /**
     * Valida contraseñas de forma consistente sin trim destructivo.
     * Permite espacios si el usuario los escribió, pero rechaza contraseñas compuestas sólo de espacios.
     */
    public static function validatePassword($password, int $min = 8, int $max = 128): ?string {
        if (!is_string($password)) {
            return null;
        }

        $len = strlen($password);
        if ($len < $min || $len > $max) {
            return null;
        }

        // Rechazar contraseña formada exclusivamente por espacios en blanco
        if (trim($password) === '') {
            return null;
        }

        // Retorna la contraseña exacta sin trim para preservar la intención del usuario
        return $password;
    }

    /**
     * Valida cadenas de texto con longitud y remoción de caracteres de control peligrosos.
     */
    public static function validateText(?string $text, int $minLength, int $maxLength, bool $multiline = false): ?string {
        if ($text === null) {
            return null;
        }

        // Remover caracteres de control excepto salto de línea / retorno de carro si multiline
        if ($multiline) {
            $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        } else {
            $cleaned = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
        }

        $trimmed = trim($cleaned);
        $mbLen = mb_strlen($trimmed, 'UTF-8');

        if ($mbLen < $minLength || $mbLen > $maxLength) {
            return null;
        }

        return $trimmed;
    }

    /**
     * Valida enteros positivos en rango estricto. Rechaza <= 0, cadenas no numéricas, flotantes y NaN.
     */
    public static function validatePositiveInt($value, int $min = 1, int $max = 100000): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        // Si es string numérico puro o entero
        if (is_int($value)) {
            $intVal = $value;
        } elseif (is_string($value) && ctype_digit(trim($value))) {
            $intVal = (int)trim($value);
        } else {
            return null;
        }

        if ($intVal < $min || $intVal > $max) {
            return null;
        }

        return $intVal;
    }

    /**
     * Valida precios: positivos, numéricos, <= 1,000,000 y redondeados a 2 decimales.
     */
    public static function validatePrice($value, float $min = 0.0, float $max = 1000000.0): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $floatVal = (float)$value;
        if (is_nan($floatVal) || is_infinite($floatVal)) {
            return null;
        }

        if ($floatVal < $min || $floatVal > $max) {
            return null;
        }

        return round($floatVal, 2);
    }

    /**
     * Valida pertenencia estricta en una lista permitida (Allowlist).
     */
    public static function validateAllowlist(?string $value, array $allowed, bool $caseInsensitive = false): ?string {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($caseInsensitive) {
            foreach ($allowed as $item) {
                if (strcasecmp($trimmed, $item) === 0) {
                    return $item;
                }
            }
            return null;
        }

        return in_array($trimmed, $allowed, true) ? $trimmed : null;
    }

    /**
     * Valida tamaño máximo del payload y sintaxis JSON válida.
     */
    public static function parseJsonInput(int $maxBytes = 1048576): array {
        $raw = file_get_contents('php://input');

        if (strlen($raw) > $maxBytes) {
            ApiResponse::error(
                'El tamaño de la solicitud excede el límite permitido (1 MB).',
                'PAYLOAD_TOO_LARGE',
                413
            );
        }

        if (trim($raw) === '') {
            return is_array($_POST) ? $_POST : [];
        }

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            ApiResponse::error(
                'El formato JSON de la solicitud es inválido o está malformado.',
                'INVALID_JSON',
                400
            );
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Valida slugs para categorías o URLs (alfanumérico y guiones).
     */
    public static function validateSlug(?string $slug): ?string {
        if ($slug === null) {
            return null;
        }
        $clean = strtolower(trim($slug));
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $clean)) {
            return null;
        }
        $len = strlen($clean);
        return ($len >= 2 && $len <= 100) ? $clean : null;
    }

    /**
     * Valida clases de color seguras para Tailwind (evita inyecciones de atributos).
     */
    public static function validateSafeColor(?string $color): ?string {
        if ($color === null) {
            return null;
        }
        $clean = trim($color);
        if (preg_match('/^[a-zA-Z0-9\/\-\s_:]+$/', $clean) && strlen($clean) <= 100) {
            return $clean;
        }
        return null;
    }

    /**
     * Valida rutas de imágenes de productos (F12).
     * Rechaza esquemas peligrosos (javascript:, data:, file:, vbscript:, blob:),
     * URLs externas no autorizadas, secuencias de escape de directorio (../, ..\)
     * y restringe a rutas relativas autorizadas de productos.
     */
    public static function validateImageUrl(?string $url): ?string {
        if ($url === null) {
            return null;
        }

        $clean = trim($url);
        if ($clean === '' || strlen($clean) > 255) {
            return null;
        }

        // Rechazar caracteres de control y bytes nulos
        if (preg_match('/[\x00-\x1F\x7F]/', $clean)) {
            return null;
        }

        // Rechazar esquemas peligrosos (javascript:, data:, file:, vbscript:, blob:)
        if (preg_match('/^(?:javascript|data|file|vbscript|blob):/i', $clean)) {
            return null;
        }

        // Rechazar URLs externas (http://, https://, //)
        if (preg_match('/^(?:https?:|\/\/)/i', $clean)) {
            return null;
        }

        // Rechazar secuencias de path traversal (../, ..\, backslashes)
        if (str_contains($clean, '..') || str_contains($clean, '\\')) {
            return null;
        }

        // Normalizar barras
        $normalized = str_replace('\\', '/', $clean);

        // Lista blanca estricta de rutas de imágenes autorizadas
        // 1. Placeholder oficial por defecto
        if ($normalized === 'assets/images/productos/default.png') {
            return $normalized;
        }

        // 2. Archivos gestionados dentro de assets/images/productos/
        // Formato seguro: prod_[hex/alphanumeric].[jpg|jpeg|png|webp]
        if (preg_match('#^assets/images/productos/prod_[a-zA-Z0-9_\-]+\.(jpg|jpeg|png|webp)$#i', $normalized)) {
            return $normalized;
        }

        // 3. Archivos gestionados dentro de uploads/ (si aplica)
        if (preg_match('#^uploads/(?:productos/)?prod_[a-zA-Z0-9_\-]+\.(jpg|jpeg|png|webp)$#i', $normalized)) {
            return $normalized;
        }

        return null;
    }
}

