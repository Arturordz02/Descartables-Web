<?php
/**
 * API REST: Configuración Centralizada de Contacto y Empresa
 * Plataforma Descartables Peruanos
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validator.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDbConnection();

if (!$pdo) {
    ApiResponse::error('Base de datos no disponible.', 'DB_UNAVAILABLE', 503);
}

// Formateador de respuesta estructurada compatible con COMPANY_CONTACT
function buildStructuredConfig($flatConfig) {
    $enableRedirects = isset($flatConfig['enable_redirects']) 
        ? ($flatConfig['enable_redirects'] === 'true' || $flatConfig['enable_redirects'] === true || $flatConfig['enable_redirects'] === '1' || $flatConfig['enable_redirects'] === 1)
        : false;

    $waPrincipal = $flatConfig['whatsapp_principal'] ?? '+51 900 000 000';
    $waSecundario = $flatConfig['whatsapp_secundario'] ?? '+51 900 000 002';
    $telCentral = $flatConfig['telefono_central'] ?? '(01) 000-0000';

    $waPrinRaw = preg_replace('/[^0-9]/', '', $waPrincipal);
    if (strlen($waPrinRaw) === 9) $waPrinRaw = '51' . $waPrinRaw;

    $waSecRaw = preg_replace('/[^0-9]/', '', $waSecundario);
    if (strlen($waSecRaw) === 9) $waSecRaw = '51' . $waSecRaw;

    $telRaw = preg_replace('/[^0-9]/', '', $telCentral);

    return [
        'ENABLE_REDIRECTS' => $enableRedirects,
        'empresa' => [
            'razon_social'     => $flatConfig['razon_social'] ?? 'DESCARTABLES PERUANOS S.A.C.',
            'nombre_comercial' => $flatConfig['nombre_comercial'] ?? 'Descartables Peruanos',
            'ruc'              => $flatConfig['ruc'] ?? '20601234567',
            'direccion'        => $flatConfig['direccion'] ?? 'Av. Alejandro Bertello 732-C, Cercado de Lima, Lima, Perú',
            'horario'          => $flatConfig['horario'] ?? 'Lunes a Viernes: 8:00 AM - 6:00 PM | Sábados: 8:30 AM - 1:00 PM'
        ],
        'whatsapp' => [
            'principal'      => $waPrincipal,
            'principal_raw'  => $waPrinRaw,
            'url_principal'  => "https://wa.me/{$waPrinRaw}",
            'secundario'     => $waSecundario,
            'secundario_raw' => $waSecRaw,
            'url_secundario' => "https://wa.me/{$waSecRaw}"
        ],
        'telefonos' => [
            'central'     => $telCentral,
            'central_raw' => $telRaw,
            'tel_link'    => "tel:+511" . ltrim($telRaw, '0')
        ],
        'emails' => [
            'ventas'       => $flatConfig['email_ventas'] ?? 'ventas@descartablesperuanos.pe',
            'cotizaciones' => $flatConfig['email_cotizaciones'] ?? 'cotizaciones@descartablesperuanos.pe'
        ],
        'redes' => [
            'facebook'  => $flatConfig['facebook_url'] ?? 'https://facebook.com/descartablesperuanos',
            'instagram' => $flatConfig['instagram_url'] ?? 'https://instagram.com/descartablesperuanos'
        ],
        'banners' => [
            'top' => [
                'enabled' => isset($flatConfig['banner_top_enabled']) ? ($flatConfig['banner_top_enabled'] === 'true' || $flatConfig['banner_top_enabled'] === '1' || $flatConfig['banner_top_enabled'] === true) : true,
                'texto'   => $flatConfig['banner_top_texto'] ?? 'Envíos a todo el Perú por agencias • Atención mayorista directa',
                'badge'   => $flatConfig['banner_top_badge'] ?? 'Envíos a Todo el Perú',
                'link'    => $flatConfig['banner_top_link'] ?? 'catalogo.html'
            ],
            'hero' => [
                'badge'              => $flatConfig['hero_badge'] ?? 'Venta al por Mayor y Menor • Envíos a todo el Perú',
                'titulo'             => $flatConfig['hero_titulo'] ?? 'Envases y Descartables para el Sector Gastronómico e Industrial',
                'subtitulo'          => $flatConfig['hero_subtitulo'] ?? 'Abastecemos a restaurantes, pollerías, cafeterías, empresas de catering y distribuidores con productos de primera calidad: Pamolsa, Proplas, cubiertos reforzados y empaques 100% biodegradables.',
                'btn_primary_text'   => $flatConfig['hero_btn_primary_text'] ?? 'Explorar Catálogo',
                'btn_primary_link'   => $flatConfig['hero_btn_primary_link'] ?? 'catalogo.html',
                'btn_secondary_text' => $flatConfig['hero_btn_secondary_text'] ?? 'Asesoría Comercial',
                'btn_secondary_link' => $flatConfig['hero_btn_secondary_link'] ?? 'contacto.html'
            ],
            'promo' => [
                'enabled'   => isset($flatConfig['banner_promo_enabled']) ? ($flatConfig['banner_promo_enabled'] === 'true' || $flatConfig['banner_promo_enabled'] === '1' || $flatConfig['banner_promo_enabled'] === true) : true,
                'badge'     => $flatConfig['banner_promo_badge'] ?? 'OFERTA DE TEMPORADA',
                'titulo'    => $flatConfig['banner_promo_titulo'] ?? 'Precios Especiales por Cajón y Millar para Restaurantes',
                'subtitulo' => $flatConfig['banner_promo_subtitulo'] ?? 'Cotiza directamente por volumen y accede a descuentos exclusivos con despacho inmediato a nivel nacional.',
                'btn_text'  => $flatConfig['banner_promo_btn_text'] ?? 'Solicitar Cotización Mayorista',
                'btn_link'  => $flatConfig['banner_promo_btn_link'] ?? 'catalogo.html'
            ]
        ],
        'flat' => $flatConfig
    ];
}

// 1. OBTENER CONFIGURACIÓN (GET)
if ($method === 'GET') {
    try {
        $stmt = $pdo->query("SELECT clave, valor FROM configuracion");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $structured = buildStructuredConfig($rows);

        ApiResponse::success($structured);
    } catch (Throwable $e) {
        ApiResponse::error('Error al consultar configuración.', 'SERVER_ERROR', 500, $e);
    }
}

// 2. ACTUALIZAR CONFIGURACIÓN (POST o PUT)
if ($method === 'POST' || $method === 'PUT') {
    // Protección estricta: Solo administradores autorizados pueden modificar la configuración o teléfonos
    if (class_exists('Vault')) {
        Vault::requireAdmin();
    }

    try {
        $data = Validator::parseJsonInput();
        if (empty($data)) {
            $data = $_POST;
        }

        // Si envían 'flat' o directamente los campos
        $fieldsToSave = isset($data['flat']) && is_array($data['flat']) ? $data['flat'] : $data;

        // Validaciones de negocio en campos sensibles si están presentes
        if (isset($fieldsToSave['ruc']) && trim((string)$fieldsToSave['ruc']) !== '') {
            $rucVal = Validator::validateDocument((string)$fieldsToSave['ruc'], 'RUC');
            if ($rucVal === null) {
                ApiResponse::error('RUC de empresa no válido. Debe contener 11 dígitos numéricos.', 'VALIDATION_ERROR', 400);
            }
            $fieldsToSave['ruc'] = $rucVal['documento'];
        }

        if (isset($fieldsToSave['email_ventas']) && trim((string)$fieldsToSave['email_ventas']) !== '') {
            $emVal = Validator::validateEmail((string)$fieldsToSave['email_ventas']);
            if ($emVal === null) {
                ApiResponse::error('Email de ventas no válido.', 'VALIDATION_ERROR', 400);
            }
            $fieldsToSave['email_ventas'] = $emVal;
        }

        if (isset($fieldsToSave['email_cotizaciones']) && trim((string)$fieldsToSave['email_cotizaciones']) !== '') {
            $emVal = Validator::validateEmail((string)$fieldsToSave['email_cotizaciones']);
            if ($emVal === null) {
                ApiResponse::error('Email de cotizaciones no válido.', 'VALIDATION_ERROR', 400);
            }
            $fieldsToSave['email_cotizaciones'] = $emVal;
        }

        $allowedKeys = [
            'enable_redirects',
            'razon_social',
            'nombre_comercial',
            'ruc',
            'direccion',
            'horario',
            'whatsapp_principal',
            'whatsapp_secundario',
            'telefono_central',
            'email_ventas',
            'email_cotizaciones',
            'facebook_url',
            'instagram_url',
            'banner_top_enabled',
            'banner_top_texto',
            'banner_top_badge',
            'banner_top_link',
            'hero_badge',
            'hero_titulo',
            'hero_subtitulo',
            'hero_btn_primary_text',
            'hero_btn_primary_link',
            'hero_btn_secondary_text',
            'hero_btn_secondary_link',
            'banner_promo_enabled',
            'banner_promo_badge',
            'banner_promo_titulo',
            'banner_promo_subtitulo',
            'banner_promo_btn_text',
            'banner_promo_btn_link'
        ];

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON CONFLICT(clave) DO UPDATE SET valor = excluded.valor");
        } else {
            $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        }

        foreach ($fieldsToSave as $k => $v) {
            if (in_array($k, $allowedKeys)) {
                $valStr = is_bool($v) ? ($v ? 'true' : 'false') : trim((string)$v);
                $stmt->execute([$k, $valStr]);
            }
        }

        // Obtener la configuración actualizada
        $fetchStmt = $pdo->query("SELECT clave, valor FROM configuracion");
        $allRows = $fetchStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $structured = buildStructuredConfig($allRows);

        ApiResponse::success($structured, ['message' => 'Configuración de la empresa actualizada correctamente.']);
    } catch (Throwable $e) {
        ApiResponse::error('Error al guardar configuración.', 'SERVER_ERROR', 500, $e);
    }
}

ApiResponse::error('Método no permitido.', 'METHOD_NOT_ALLOWED', 405);

