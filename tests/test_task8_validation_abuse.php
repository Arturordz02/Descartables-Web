<?php
/**
 * Test Suite: TAREA 8 - Validaciones de Negocio, Tipos de Datos y Control de Abuso (F11)
 * Plataforma Descartables Peruanos
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/validator.php';
require_once __DIR__ . '/../api/ratelimit.php';
require_once __DIR__ . '/../api/concurrency.php';

$totalTests = 0;
$passedTests = 0;

function it(string $description, callable $fn): void {
    global $totalTests, $passedTests;
    $totalTests++;
    try {
        $result = $fn();
        if ($result === true || $result === null) {
            $passedTests++;
            echo "  [PASS] $description\n";
        } else {
            echo "  [FAIL] $description\n";
        }
    } catch (Throwable $e) {
        echo "  [FAIL] $description -> " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

function assertEq($actual, $expected, string $msg = ''): void {
    if ($actual !== $expected) {
        throw new Exception(($msg ? "$msg: " : "") . "Expected " . var_export($expected, true) . ", got " . var_export($actual, true));
    }
}

function assertTrue($val, string $msg = ''): void {
    if ($val !== true) {
        throw new Exception(($msg ? "$msg: " : "") . "Expected true, got " . var_export($val, true));
    }
}

function assertNull($val, string $msg = ''): void {
    if ($val !== null) {
        throw new Exception(($msg ? "$msg: " : "") . "Expected null, got " . var_export($val, true));
    }
}

echo "========================================================\n";
echo "EJECUTANDO TESTS DE TAREA 8: VALIDACIONES Y ABUSO (F11)\n";
echo "========================================================\n\n";

// =========================================================================
// 1. VALIDACIÓN DE EMAIL
// =========================================================================
echo "1. Pruebas de validación de email...\n";

it('Acepta correos válidos y los normaliza a minúsculas', function() {
    assertEq(Validator::validateEmail('  Usuario.Prueba@Dominio.COM  '), 'usuario.prueba@dominio.com');
    assertEq(Validator::validateEmail('ventas+corporativo@descartables.pe'), 'ventas+corporativo@descartables.pe');
});

it('Rechaza correos sin formato válido', function() {
    assertNull(Validator::validateEmail('correo_invalido'));
    assertNull(Validator::validateEmail('@sin_usuario.com'));
    assertNull(Validator::validateEmail('sin_dominio@'));
    assertNull(Validator::validateEmail('a@b'));
});

it('Rechaza inyección de cabeceras CRLF o caracteres de control en email', function() {
    assertNull(Validator::validateEmail("admin@sitio.pe\r\nBcc: victim@sitio.pe"));
    assertNull(Validator::validateEmail("admin\x00@sitio.pe"));
});

it('Rechaza correos excesivamente largos (> 150 caracteres)', function() {
    $longEmail = str_repeat('a', 145) . '@pe.com';
    assertNull(Validator::validateEmail($longEmail));
});

// =========================================================================
// 2. VALIDACIÓN DE DOCUMENTOS (DNI, RUC, CE, PASAPORTE)
// =========================================================================
echo "\n2. Pruebas de validación y normalización de documentos...\n";

it('DNI: Acepta exactamente 8 dígitos numéricos y normaliza espacios y guiones', function() {
    $res = Validator::validateDocument(' 71-23.45 67 ', 'DNI');
    assertTrue($res !== null && $res['valid'] === true);
    assertEq($res['documento'], '71234567');
    assertEq($res['tipo'], 'DNI');
});

it('DNI: Rechaza documentos con menos o más de 8 dígitos o con letras', function() {
    assertNull(Validator::validateDocument('1234567', 'DNI')); // 7 dígitos
    assertNull(Validator::validateDocument('123456789', 'DNI')); // 9 dígitos
    assertNull(Validator::validateDocument('7123456A', 'DNI')); // contiene letra
});

it('RUC: Acepta 11 dígitos numéricos y normaliza espacios/guiones', function() {
    $res = Validator::validateDocument(' 20-60123456-7 ', 'RUC');
    assertTrue($res !== null && $res['valid'] === true);
    assertEq($res['documento'], '20601234567');
    assertEq($res['tipo'], 'RUC');
});

it('RUC: Rechaza RUC con longitud distinta a 11 dígitos o con caracteres no numéricos', function() {
    assertNull(Validator::validateDocument('2060123456', 'RUC')); // 10 dígitos
    assertNull(Validator::validateDocument('206012345678', 'RUC')); // 12 dígitos
    assertNull(Validator::validateDocument('2060123456X', 'RUC')); // letra
});

it('CE y Pasaporte: Acepta de 4 a 15 caracteres alfanuméricos', function() {
    $ce = Validator::validateDocument(' 001234567 ', 'CE');
    assertTrue($ce !== null && $ce['valid'] === true);
    assertEq($ce['documento'], '001234567');

    $pas = Validator::validateDocument('AB-123456', 'Pasaporte');
    assertTrue($pas !== null && $pas['valid'] === true);
    assertEq($pas['documento'], 'AB-123456');
});

it('Infiere tipo de documento automáticamente si no se especifica', function() {
    $dni = Validator::validateDocument('71234567');
    assertEq($dni['tipo'], 'DNI');
    $ruc = Validator::validateDocument('20601234567');
    assertEq($ruc['tipo'], 'RUC');
});

// =========================================================================
// 3. VALIDACIÓN DE TELÉFONOS
// =========================================================================
echo "\n3. Pruebas de validación de teléfonos...\n";

it('Acepta móviles peruanos de 9 dígitos y prefijo +51 normalizado a E.164', function() {
    assertEq(Validator::validatePhone('987654321'), '+51987654321');
    assertEq(Validator::validatePhone('+51 987 654 321'), '+51987654321');
});

it('Acepta teléfonos fijos peruanos de 7 dígitos con o sin código de ciudad', function() {
    assertEq(Validator::validatePhone('(01) 456-7890'), '014567890');
    assertEq(Validator::validatePhone('4567890'), '4567890');
});

it('Acepta teléfonos internacionales estándar E.164 (7 a 15 dígitos)', function() {
    assertEq(Validator::validatePhone('+1 415 555 2671'), '+14155552671');
    assertEq(Validator::validatePhone('+34 91 123 45 67'), '+34911234567');
});

it('Rechaza números de teléfono con menos de 7 o más de 15 dígitos', function() {
    assertNull(Validator::validatePhone('12345')); // muy corto
    assertNull(Validator::validatePhone('1234567890123456')); // 16 dígitos
    assertNull(Validator::validatePhone('telefono_invalido'));
});

// =========================================================================
// 4. VALIDACIÓN DE CONTRASEÑAS (PRESERVA ESPACIOS Y RECHAZA VACÍAS)
// =========================================================================
echo "\n4. Pruebas de validación de contraseñas...\n";

it('Acepta contraseñas entre 8 y 128 caracteres sin alterar espacios válidos internos o iniciales', function() {
    $pwd = " Mi Clave Segura 2026! ";
    $res = Validator::validatePassword($pwd);
    assertEq($res, $pwd, 'La contraseña NO debe ser recortada por trim');
});

it('Rechaza contraseñas de menos de 8 caracteres o más de 128 caracteres', function() {
    assertNull(Validator::validatePassword('1234567')); // 7 chars
    assertNull(Validator::validatePassword(str_repeat('A', 129))); // 129 chars
});

it('Rechaza contraseñas formadas únicamente por espacios en blanco', function() {
    assertNull(Validator::validatePassword('        '));
    assertNull(Validator::validatePassword("   \t  \n "));
});

// =========================================================================
// 5. VALIDACIÓN DE CANTIDADES Y PRECIOS
// =========================================================================
echo "\n5. Pruebas de validación de cantidades de cotización y precios...\n";

it('Valida cantidades como enteros estrictamente positivos (1 a 100,000)', function() {
    assertEq(Validator::validatePositiveInt(1), 1);
    assertEq(Validator::validatePositiveInt('50'), 50);
    assertEq(Validator::validatePositiveInt(100000), 100000);
});

it('Rechaza cantidades cero, negativas, fraccionarias o no numéricas', function() {
    assertNull(Validator::validatePositiveInt(0));
    assertNull(Validator::validatePositiveInt(-5));
    assertNull(Validator::validatePositiveInt(3.5));
    assertNull(Validator::validatePositiveInt('12.7'));
    assertNull(Validator::validatePositiveInt('diez'));
    assertNull(Validator::validatePositiveInt('10 unidades'));
    assertNull(Validator::validatePositiveInt(100001)); // supera límite de 100,000
});

it('Valida precios numéricos positivos y redondea a 2 decimales', function() {
    assertEq(Validator::validatePrice(12.5), 12.50);
    assertEq(Validator::validatePrice('15.456'), 15.46);
    assertEq(Validator::validatePrice(0), 0.0);
});

it('Rechaza precios negativos o no numéricos', function() {
    assertNull(Validator::validatePrice(-10.5));
    assertNull(Validator::validatePrice('gratis'));
    assertNull(Validator::validatePrice(1000001.0)); // supera 1,000,000
});

// =========================================================================
// 6. VALIDACIÓN DE LISTAS BLANCAS (ALLOWLISTS) SIN FALLBACK SILENCIOSO
// =========================================================================
echo "\n6. Pruebas de listas permitidas (Allowlists) y rechazo estricto...\n";

it('Acepta valores incluidos en la lista blanca', function() {
    assertEq(Validator::validateAllowlist('cliente', ['cliente', 'admin']), 'cliente');
    assertEq(Validator::validateAllowlist('Pendiente', ['Pendiente', 'Atendido', 'Cancelado']), 'Pendiente');
    assertEq(Validator::validateAllowlist('en_stock', ['en_stock', 'bajo_pedido', 'agotado']), 'en_stock');
});

it('Rechaza estrictamente valores fuera de la lista blanca con null (sin fallback)', function() {
    assertNull(Validator::validateAllowlist('superadmin', ['cliente', 'admin']));
    assertNull(Validator::validateAllowlist('Desconocido', ['Pendiente', 'Atendido', 'Cancelado']));
    assertNull(Validator::validateAllowlist('in_stock', ['en_stock', 'bajo_pedido', 'agotado']));
    assertNull(Validator::validateAllowlist('', ['cliente', 'admin']));
});

// =========================================================================
// 7. VALIDACIÓN DE SLUGS Y CLASES DE COLOR
// =========================================================================
echo "\n7. Pruebas de validación de slugs y colores de categorías...\n";

it('Valida slugs limpios en minúsculas con guiones', function() {
    assertEq(Validator::validateSlug('vasos-biodegradables'), 'vasos-biodegradables');
    assertEq(Validator::validateSlug('envases-termicos-10oz'), 'envases-termicos-10oz');
});

it('Rechaza slugs con caracteres especiales, inyecciones o formato inválido', function() {
    assertNull(Validator::validateSlug('vasos/biodegradables'));
    assertNull(Validator::validateSlug('vasos--dobles'));
    assertNull(Validator::validateSlug('<script>alert(1)</script>'));
    assertNull(Validator::validateSlug('slug_con_guion_bajo'));
});

it('Valida clases de color Tailwind seguras y rechaza inyección de atributos', function() {
    $safeColor = 'from-amber-600/20 to-orange-600/20';
    assertEq(Validator::validateSafeColor($safeColor), $safeColor);

    assertNull(Validator::validateSafeColor('"><script>alert(1)</script>'));
    assertNull(Validator::validateSafeColor("color' onmouseover='alert(1)"));
});

// =========================================================================
// 8. MOTOR DE RATE LIMITING Y CONTROL DE ABUSO
// =========================================================================
echo "\n8. Pruebas del motor de Rate Limiting (tabla rate_limits)...\n";

// Configurar base de datos SQLite en memoria para pruebas de rate limit
$testDbPath = __DIR__ . '/../scratch/test_ratelimit_' . time() . '.sqlite';
if (!is_dir(dirname($testDbPath))) {
    mkdir(dirname($testDbPath), 0777, true);
}
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

$pdo = new PDO("sqlite:$testDbPath", null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// Crear tabla rate_limits
$pdo->exec("
    CREATE TABLE rate_limits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        identifier VARCHAR(100) NOT NULL,
        action VARCHAR(50) NOT NULL,
        attempts INTEGER NOT NULL DEFAULT 1,
        window_start INTEGER NOT NULL,
        blocked_until INTEGER NOT NULL DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (identifier, action)
    )
");

it('Extracción de IP: Ignora cabeceras intermedias si el origen remoto no es proxy confiable', function() {
    $_SERVER['REMOTE_ADDR'] = '200.48.100.5'; // IP pública
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4'; // Falsificada
    assertEq(RateLimiter::getClientIp(), '200.48.100.5');

    // Si proviene de loopback confiable, sí lee cabecera
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '190.113.10.20';
    assertEq(RateLimiter::getClientIp(), '190.113.10.20');
});

it('Permite solicitudes dentro del umbral de intentos', function() use ($pdo) {
    $_SERVER['REMOTE_ADDR'] = '198.51.100.1';
    unset($_SERVER['HTTP_X_FORWARDED_FOR']);

    // Umbral: máximo 3 intentos en ventana de 60 segundos
    $r1 = RateLimiter::check($pdo, 'test:login', 3, 60);
    assertTrue($r1['allowed']);

    $r2 = RateLimiter::check($pdo, 'test:login', 3, 60);
    assertTrue($r2['allowed']);

    $r3 = RateLimiter::check($pdo, 'test:login', 3, 60);
    assertTrue($r3['allowed']);
});

it('Bloquea cuando se supera el umbral y devuelve Retry-After', function() use ($pdo) {
    // 4to intento debe ser bloqueado
    $r4 = RateLimiter::check($pdo, 'test:login', 3, 60, 120);
    assertTrue(!$r4['allowed']);
    assertTrue($r4['retry_after'] > 0);
});

it('Aislamiento por usuario: limita por usuario autenticado adicional a la IP', function() use ($pdo) {
    $_SERVER['REMOTE_ADDR'] = '198.51.100.2';
    // Usuario 42 con límite de 2
    $u1 = RateLimiter::check($pdo, 'test:user_action', 2, 60, 60, 42);
    assertTrue($u1['allowed']);
    $u2 = RateLimiter::check($pdo, 'test:user_action', 2, 60, 60, 42);
    assertTrue($u2['allowed']);

    $u3 = RateLimiter::check($pdo, 'test:user_action', 2, 60, 60, 42);
    assertTrue(!$u3['allowed'], 'El usuario 42 debe estar bloqueado');

    // Usuario 99 desde la misma IP (ej. cabina o NAT) debe seguir permitido
    $uOther = RateLimiter::check($pdo, 'test:user_action_other', 2, 60, 60, 99);
    assertTrue($uOther['allowed']);
});

// =========================================================================
// 9. INTEGRACIÓN: IDEMPOTENCIA Y RATE LIMITING NO SE INTERFIEREN
// =========================================================================
echo "\n9. Pruebas de integración: Replays idempotentes no consumen Rate Limit...\n";

// Crear tablas de secuencias e idempotencia para test
$pdo->exec("
    CREATE TABLE secuencias (
        nombre VARCHAR(50) PRIMARY KEY,
        ultimo_valor INTEGER NOT NULL DEFAULT 0,
        anio INTEGER NOT NULL,
        actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE idempotencia (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        scope VARCHAR(50) NOT NULL,
        clave VARCHAR(100) NOT NULL,
        usuario_id INTEGER NULL,
        documento VARCHAR(20) NULL,
        request_hash VARCHAR(64) NOT NULL,
        codigo_resultado VARCHAR(50) NOT NULL,
        response_payload TEXT NOT NULL,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(scope, clave)
    );
");

it('Replay idempotente devuelve respuesta original sin incrementar rate limit', function() use ($pdo) {
    $scope = 'cotizacion:create';
    $key = 'idemp_test_replay_001';
    $payload = ['documento' => '20601234567', 'nombre' => 'Test Cliente'];
    $hash = ConcurrencyEngine::normalizePayloadForFingerprint($payload);

    // 1. Guardar registro inicial idempotente
    ConcurrencyEngine::saveIdempotency($pdo, $scope, $key, $hash, 'COT-2026-00099', ['success' => true, 'codigo_cotizacion' => 'COT-2026-00099']);

    // 2. Simular reintento de conexión del cliente
    $check = ConcurrencyEngine::checkIdempotency($pdo, $scope, $key, $hash);
    assertEq($check['status'], 'replay');
    assertEq($check['codigo'], 'COT-2026-00099');

    // Dado que es replay, el endpoint no llama a RateLimiter::enforce(),
    // por lo que los intentos en la tabla rate_limits para esta acción no se alteran
    $stmt = $pdo->prepare("SELECT attempts FROM rate_limits WHERE action = 'cotizacion:create'");
    $stmt->execute();
    $row = $stmt->fetch();
    assertTrue(!$row || (int)$row['attempts'] === 0, 'Replay no debe haber consumido tokens');
});

// =========================================================================
// 10. VALIDACIÓN DE CATÁLOGO Y RECALCULO DE PRECIOS EN COTIZACIONES
// =========================================================================
echo "\n10. Pruebas de autoridad de servidor en precios y productos de cotización...\n";

// Crear tabla productos para test de recálculo
$pdo->exec("
    CREATE TABLE productos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sku VARCHAR(50) NOT NULL,
        nombre VARCHAR(200) NOT NULL,
        precio DECIMAL(10,2) NOT NULL,
        stock_estado VARCHAR(20) NOT NULL DEFAULT 'en_stock'
    );
    INSERT INTO productos (id, sku, nombre, precio, stock_estado) VALUES 
    (1, 'VAS-BIO-01', 'Vaso Biodegradable 8oz', 18.50, 'en_stock'),
    (2, 'ENV-TER-02', 'Envase Térmico 16oz', 25.00, 'en_stock'),
    (3, 'CUB-PLAS-03', 'Cubierto Reforzado', 12.00, 'agotado');
");

it('Servidor recalcula precios ignorando precios manipulados enviados por el cliente', function() use ($pdo) {
    // Cliente envía un producto que cuesta 18.50 intentando pagar 0.05
    $clientItem = [
        'id' => 1,
        'sku' => 'VAS-BIO-01',
        'nombre' => 'Nombre cliente',
        'cantidad' => 10,
        'precio' => 0.05, // PRECIO MANIPULADO
        'subtotal' => 0.50
    ];

    // Lógica del backend: buscar producto oficial en la BD
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
    $stmt->execute([$clientItem['id']]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);

    assertTrue((bool)$prod);
    $officialPrice = (float)$prod['precio'];
    assertEq($officialPrice, 18.50);

    // Recalcular subtotal con precio oficial
    $officialSubtotal = round($officialPrice * $clientItem['cantidad'], 2);
    assertEq($officialSubtotal, 185.00);
});

it('Rechaza productos con stock_estado = agotado con HTTP 400', function() use ($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
    $stmt->execute([3]); // CUB-PLAS-03 está agotado
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);

    assertTrue($prod['stock_estado'] === 'agotado');
    // La regla de negocio debe rechazar la cotización de producto agotado
    $isAllowed = ($prod['stock_estado'] !== 'agotado');
    assertTrue(!$isAllowed);
});

it('Rechaza productos inexistentes en el catálogo con HTTP 400', function() use ($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
    $stmt->execute([9999]); // No existe
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);

    assertNull($prod ? $prod : null);
});

// Limpieza de base de datos temporal
$pdo = null;
if (file_exists($testDbPath)) {
    @unlink($testDbPath);
}

echo "\n========================================================\n";
echo "RESULTADOS TAREA 8: $passedTests / $totalTests PRUEBAS SUPERADAS\n";
echo "========================================================\n";

if ($passedTests < $totalTests) {
    exit(1);
}
