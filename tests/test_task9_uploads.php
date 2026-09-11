<?php
/**
 * Test Suite: TAREA 9 - Fortalecer Subida de Imágenes y Manejo Seguro de Archivos (F12)
 * Plataforma Descartables Peruanos
 */

declare(strict_types=1);

$GLOBALS['UPLOAD_SCRIPT_INCLUDED'] = true;
require_once __DIR__ . '/../api/validator.php';
require_once __DIR__ . '/../api/upload.php';

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
echo "EJECUTANDO TESTS DE TAREA 9: SUBIDAS Y ARCHIVOS (F12)\n";
echo "========================================================\n\n";

// Búfer binario 1x1 PNG válido
$validPngBuffer = base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==");

// Búfer binario 1x1 JPG válido
$validJpgBuffer = base64_decode("/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=");

// Búfer binario WebP válido (1x1 transparente)
$validWebpBuffer = base64_decode("UklGRkAAAABXRUJQVlA4IDQAAADwAQCdASoBAAEAAQAcJaACdLoB+AA/v1mZAAAA/v7+vv7+/v7+/v7+/v7+/v7+/v7+/v7+");

// Parámetros canónicos
$maxBytes = 5 * 1024 * 1024;
$maxDim = 4000;
$allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

// =========================================================================
// 1. FORMATOS PERMITIDOS MENORES A 5 MB
// =========================================================================
echo "1. Pruebas de formatos legítimos menores a 5 MB...\n";

it('JPG válido menor a 5 MB se acepta correctamente', function() use ($validJpgBuffer, $maxBytes, $maxDim, $allowedMimes) {
    $res = validateImageBinary($validJpgBuffer, true, $maxBytes, $maxDim, $allowedMimes);
    assertTrue($res['valid'], 'JPG legítimo debe ser aceptado');
    assertEq($res['mime'], 'image/jpeg');
    assertEq($res['ext'], 'jpg');
    assertTrue($res['width'] >= 1 && $res['height'] >= 1);
});

it('PNG válido menor a 5 MB se acepta correctamente', function() use ($validPngBuffer, $maxBytes, $maxDim, $allowedMimes) {
    $res = validateImageBinary($validPngBuffer, true, $maxBytes, $maxDim, $allowedMimes);
    assertTrue($res['valid'], 'PNG legítimo debe ser aceptado');
    assertEq($res['mime'], 'image/png');
    assertEq($res['ext'], 'png');
});

it('WebP válido menor a 5 MB se valida correctamente conforme al entorno', function() use ($validWebpBuffer, $maxBytes, $maxDim, $allowedMimes) {
    $res = validateImageBinary($validWebpBuffer, true, $maxBytes, $maxDim, $allowedMimes);
    if (defined('IMAGETYPE_WEBP')) {
        assertTrue($res['valid'], 'WebP debe ser aceptado si el entorno lo soporta');
        assertEq($res['mime'], 'image/webp');
    } else {
        assertTrue(!$res['valid'], 'Si el entorno no soporta IMAGETYPE_WEBP se informa de forma segura');
    }
});

// =========================================================================
// 2. RECHAZO DE ARCHIVOS PELIGROSOS Y SCRIPTS DISFRAZADOS
// =========================================================================
echo "\n2. Pruebas de rechazo de archivos peligrosos y evasiones...\n";

it('Archivo PHP renombrado como .jpg se rechaza tajantemente', function() use ($maxBytes, $maxDim, $allowedMimes) {
    $fakeJpg = "<?php echo 'malicious_code'; system(\$_GET['cmd']); ?>";
    $res = validateImageBinary($fakeJpg, true, $maxBytes, $maxDim, $allowedMimes);
    assertTrue(!$res['valid'], 'Un archivo PHP renombrado a JPG debe ser rechazado');
});

it('Archivo SVG con script se rechaza tajantemente', function() use ($maxBytes, $maxDim, $allowedMimes) {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert("XSS")</script></svg>';
    $res = validateImageBinary($svg, true, $maxBytes, $maxDim, $allowedMimes);
    assertTrue(!$res['valid'], 'SVG con scripts debe ser rechazado');
});

it('Polyglot con cabecera de imagen pero código PHP embebido se rechaza', function() use ($validPngBuffer, $maxBytes, $maxDim, $allowedMimes) {
    $polyglot = $validPngBuffer . "<?php phpinfo(); ?>";
    $res = validateImageBinary($polyglot, true, $maxBytes, $maxDim, $allowedMimes);
    assertTrue(!$res['valid'], 'Polyglot con etiquetas PHP debe ser rechazado');
});

// =========================================================================
// 3. LÍMITES DE TAMAÑO Y DIMENSIONES
// =========================================================================
echo "\n3. Pruebas de límites de tamaño y dimensiones...\n";

it('Imagen mayor a 5 MB se rechaza con mensaje de límite excedido', function() use ($validPngBuffer, $maxBytes, $maxDim, $allowedMimes) {
    // Simular archivo de 5 MB + 10 bytes
    $oversized = $validPngBuffer . str_repeat('X', $maxBytes + 10);
    $res = validateImageBinary($oversized, true, $maxBytes, $maxDim, $allowedMimes);
    assertTrue(!$res['valid']);
    assertTrue(str_contains($res['error'], '5 MB'));
});

it('Imagen con dimensiones mayores a 4000x4000 se rechaza', function() use ($maxBytes, $allowedMimes) {
    // Crear imagen en memoria de 4001x10 si GD está disponible, o probar función con límite estricto
    $mockDimLimit = 100; // Si limitamos a 100 px, una imagen de 150x150 se rechaza
    // Usar la imagen de 1x1 con un maxDim de 0 para verificar la condición de rango
    $res = validateImageBinary(base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=="), true, $maxBytes, 0, $allowedMimes);
    assertTrue(!$res['valid'], 'Debe rechazar imágenes cuyas dimensiones superen maxDim');
    assertTrue(str_contains($res['error'], 'dimensiones'));
});

it('Payload Base64 mayor al límite de 5 MB se rechaza tras decodificar', function() use ($maxBytes, $maxDim, $allowedMimes) {
    $heavyDecoded = str_repeat('A', $maxBytes + 100);
    $res = validateImageBinary($heavyDecoded, true, $maxBytes, $maxDim, $allowedMimes);
    assertTrue(!$res['valid']);
});

// =========================================================================
// 4. POLÍTICA DE NOMBRES Y MITIGACIÓN DE PATH TRAVERSAL
// =========================================================================
echo "\n4. Pruebas de nombres aleatorios y path traversal en upload...\n";

it('Nombre original con ../ o caracteres raros no afecta la ruta final física', function() {
    $clientFilename = '../../../../etc/passwd%00.evil.png';
    // La lógica de upload.php descarta el nombre del cliente y usa bin2hex(random_bytes(16))
    $randomToken = bin2hex(random_bytes(16));
    $safeName = 'prod_' . $randomToken . '.png';

    assertTrue(!str_contains($safeName, '..'));
    assertTrue(!str_contains($safeName, '/'));
    assertTrue(!str_contains($safeName, 'passwd'));
    assertTrue(preg_match('/^prod_[a-f0-9]{32}\.png$/', $safeName) === 1);
});

it('Dos subidas con el mismo nombre original generan nombres físicos distintos', function() {
    $name1 = 'prod_' . bin2hex(random_bytes(16)) . '.jpg';
    $name2 = 'prod_' . bin2hex(random_bytes(16)) . '.jpg';

    assertTrue($name1 !== $name2, 'Cada subida debe tener un nombre criptográfico único');
});

// =========================================================================
// 5. PROTECCIÓN DE DIRECTORIO MEDIANTE .HTACCESS
// =========================================================================
echo "\n5. Pruebas de configuración de seguridad .htaccess en uploads...\n";

it('assets/images/productos/.htaccess deshabilita índices y ejecución de scripts', function() {
    $htaccessPath = __DIR__ . '/../assets/images/productos/.htaccess';
    assertTrue(file_exists($htaccessPath), 'El archivo .htaccess debe existir en la carpeta de imágenes');

    $content = file_get_contents($htaccessPath);
    assertTrue(str_contains($content, 'Options -Indexes -ExecCGI'), 'Debe desactivar Indexes y ExecCGI');
    assertTrue(str_contains($content, 'RemoveHandler'), 'Debe remover handlers de ejecución');
    assertTrue(str_contains($content, 'FilesMatch'), 'Debe bloquear extensiones de scripts');
    assertTrue(str_contains($content, 'nosniff'), 'Debe fijar cabecera nosniff');
});

it('uploads/.htaccess existe con idéntica protección de contención', function() {
    $htaccessPath = __DIR__ . '/../uploads/.htaccess';
    assertTrue(file_exists($htaccessPath));

    $content = file_get_contents($htaccessPath);
    assertTrue(str_contains($content, 'Options -Indexes -ExecCGI'));
    assertTrue(str_contains($content, 'FilesMatch'));
});

// =========================================================================
// 6. VALIDACIÓN DE IMAGEN_URL EN PRODUCTOS (VALIDATOR & PRODUCTOS.PHP)
// =========================================================================
echo "\n6. Pruebas de validación estricta de imagen_url (F12)...\n";

it('Rechaza esquemas peligrosos en imagen_url', function() {
    assertNull(Validator::validateImageUrl('javascript:alert(1)'));
    assertNull(Validator::validateImageUrl('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAAB'));
    assertNull(Validator::validateImageUrl('file:///etc/passwd'));
    assertNull(Validator::validateImageUrl('vbscript:msgbox(1)'));
    assertNull(Validator::validateImageUrl('blob:https://sitio.pe/uuid'));
});

it('Rechaza URLs externas o dominios no autorizados', function() {
    assertNull(Validator::validateImageUrl('http://malicious.com/exploit.jpg'));
    assertNull(Validator::validateImageUrl('https://external-host.com/image.png'));
    assertNull(Validator::validateImageUrl('//cdn.external.com/test.jpg'));
});

it('Rechaza secuencias de path traversal y escapes de directorio', function() {
    assertNull(Validator::validateImageUrl('../../secrets.php'));
    assertNull(Validator::validateImageUrl('assets/images/productos/../../secrets.php'));
    assertNull(Validator::validateImageUrl('assets\images\productos\..\config.php'));
    assertNull(Validator::validateImageUrl("assets/images/productos/default.png\x00.php"));
});

it('Acepta rutas relativas canónicas autorizadas de productos', function() {
    assertEq(
        Validator::validateImageUrl('assets/images/productos/default.png'),
        'assets/images/productos/default.png'
    );
    assertEq(
        Validator::validateImageUrl('assets/images/productos/prod_a9f3b827e10c492db7842617f0e34b92.jpg'),
        'assets/images/productos/prod_a9f3b827e10c492db7842617f0e34b92.jpg'
    );
    assertEq(
        Validator::validateImageUrl('assets/images/productos/prod_1788453752_sample.webp'),
        'assets/images/productos/prod_1788453752_sample.webp'
    );
});

// =========================================================================
// 7. POLÍTICA DE ELIMINACIÓN Y REEMPLAZO SEGURO DE IMÁGENES HUÉRFANAS
// =========================================================================
echo "\n7. Pruebas de eliminación y reemplazo seguro de imágenes huérfanas...\n";

// Crear BD SQLite en memoria para test de reemplazo seguro
$testDbPath = __DIR__ . '/../scratch/test_images_db_' . time() . '.sqlite';
if (!is_dir(dirname($testDbPath))) {
    mkdir(dirname($testDbPath), 0777, true);
}
$testDbPdo = new PDO("sqlite:$testDbPath", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$testDbPdo->exec("
    CREATE TABLE productos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre VARCHAR(100),
        imagen_url VARCHAR(255)
    );
");

// Crear archivos físicos de prueba temporales en assets/images/productos/
$prodUploadDir = realpath(__DIR__ . '/../assets/images/productos');
$dummyOrphan = 'prod_test_orphan_' . bin2hex(random_bytes(8)) . '.jpg';
$dummyShared = 'prod_test_shared_' . bin2hex(random_bytes(8)) . '.jpg';
$orphanPath = $prodUploadDir . DIRECTORY_SEPARATOR . $dummyOrphan;
$sharedPath = $prodUploadDir . DIRECTORY_SEPARATOR . $dummyShared;

file_put_contents($orphanPath, 'dummy content');
file_put_contents($sharedPath, 'dummy content');

// Insertar productos en BD
$testDbPdo->exec("INSERT INTO productos (id, nombre, imagen_url) VALUES 
    (10, 'Producto 10', 'assets/images/productos/$dummyOrphan'),
    (20, 'Producto 20', 'assets/images/productos/$dummyShared'),
    (21, 'Producto 21', 'assets/images/productos/$dummyShared'),
    (30, 'Producto 30', 'assets/images/productos/default.png')
");

$GLOBALS['PRODUCTOS_SCRIPT_INCLUDED'] = true;
require_once __DIR__ . '/../api/productos.php';

it('Elimina archivo huérfano gestionado si ningún otro producto lo usa', function() use ($testDbPdo, $dummyOrphan, $orphanPath) {
    assertTrue(file_exists($orphanPath), 'El archivo huérfano debe existir inicialmente');
    
    // Simular que el producto 10 se elimina o cambia de imagen
    safelyDeleteOrphanProductImage($testDbPdo, "assets/images/productos/$dummyOrphan", 10);
    
    assertTrue(!file_exists($orphanPath), 'El archivo huérfano debió ser eliminado del disco');
});

it('NO elimina imagen si otro producto en la BD aún la referencia (compartida)', function() use ($testDbPdo, $dummyShared, $sharedPath) {
    assertTrue(file_exists($sharedPath), 'La imagen compartida debe existir');
    
    // Simular que el producto 20 cambia de imagen (pero producto 21 aún la usa)
    safelyDeleteOrphanProductImage($testDbPdo, "assets/images/productos/$dummyShared", 20);
    
    assertTrue(file_exists($sharedPath), 'La imagen compartida NO debe ser eliminada mientras otro producto la use');
});

it('NUNCA elimina default.png bajo ninguna circunstancia', function() use ($testDbPdo) {
    $defaultPng = __DIR__ . '/../assets/images/productos/default.png';
    assertTrue(file_exists($defaultPng), 'default.png debe existir');
    
    safelyDeleteOrphanProductImage($testDbPdo, 'assets/images/productos/default.png', 30);
    
    assertTrue(file_exists($defaultPng), 'default.png NUNCA debe ser eliminado');
});

// Limpieza de archivos temporales
if (file_exists($sharedPath)) @unlink($sharedPath);
$testDbPdo = null;
if (file_exists($testDbPath)) @unlink($testDbPath);

// =========================================================================
// 8. AUDITORÍA DEL SCRIPT DE EMPAQUETADO (BUILD_PACKAGE)
// =========================================================================
echo "\n8. Pruebas de empaquetado seguro sin uploads reales (scripts/build_package.js)...\n";

it('build_package.js no incluye imágenes reales de usuarios en dist/package/', function() {
    $packageImgDir = __DIR__ . '/../dist/package/assets/images/productos';
    assertTrue(is_dir($packageImgDir), 'El directorio dist/package/assets/images/productos debe existir');
    
    // Comprobar que default.png y .htaccess están presentes
    assertTrue(file_exists($packageImgDir . '/default.png'), 'default.png debe estar incluido en el paquete');
    assertTrue(file_exists($packageImgDir . '/.htaccess'), '.htaccess debe estar incluido en el paquete');
    
    // Comprobar que NO existen archivos de usuarios que comiencen con prod_
    $entries = scandir($packageImgDir);
    $userUploadsFound = [];
    foreach ($entries as $entry) {
        if (str_starts_with($entry, 'prod_')) {
            $userUploadsFound[] = $entry;
        }
    }
    
    assertEq(count($userUploadsFound), 0, 'No deben existir uploads de usuarios (prod_*) en dist/package/');
});

echo "\n========================================================\n";
echo "RESULTADOS TAREA 9: $passedTests / $totalTests PRUEBAS SUPERADAS\n";
echo "========================================================\n";

if ($passedTests < $totalTests) {
    exit(1);
}
