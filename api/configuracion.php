<?php
/**
 * API REST: Configuración Centralizada de Contacto y Empresa
 * Plataforma Descartables Peruanos
 */

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDbConnection();

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

        echo json_encode([
            'success' => true,
            'data'    => $structured
        ], JSON_UNESCAPED_UNICODE);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al consultar configuración: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// 2. ACTUALIZAR CONFIGURACIÓN (POST o PUT)
if ($method === 'POST' || $method === 'PUT') {
    try {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        if (!$data || !is_array($data)) {
            $data = $_POST;
        }

        // Si envían 'flat' o directamente los campos
        $fieldsToSave = isset($data['flat']) && is_array($data['flat']) ? $data['flat'] : $data;

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


        $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");

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

        echo json_encode([
            'success' => true,
            'message' => 'Configuración de la empresa actualizada correctamente.',
            'data'    => $structured
        ], JSON_UNESCAPED_UNICODE);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al guardar configuración: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);

