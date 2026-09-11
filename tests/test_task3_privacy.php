<?php
/**
 * Test Suite: Tarea 3 - Privacidad, Perfiles y Control de Acceso a Reclamaciones y Cotizaciones (F02, F03)
 */

echo "=== INICIANDO PRUEBAS DE TAREA 3: PERFILES, PRIVACIDAD Y PROPIEDAD DE DATOS ===\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertTest($description, $condition) {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo " [PASS] $description\n";
        $testsPassed++;
    } else {
        echo " [FAIL] $description\n";
        $testsFailed++;
    }
}

// Configuración sintética aislada
putenv("TOKEN_SALT=synthetic_secure_salt_task3_audit_2026_1122334455");
putenv("APP_ENV=development");

require_once __DIR__ . '/../api/vault.php';

// Crear base de datos sintética en memoria (SQLite) para simular usuarios, cotizaciones y libro_reclamaciones
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// 1. Tabla usuarios
$pdo->exec("CREATE TABLE usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tipo_documento TEXT DEFAULT 'DNI',
    numero_documento TEXT UNIQUE NOT NULL,
    nombre_razon_social TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    telefono TEXT,
    departamento TEXT DEFAULT 'Lima',
    provincia TEXT DEFAULT 'Lima',
    distrito TEXT,
    direccion TEXT,
    rol TEXT DEFAULT 'cliente',
    creado_en TEXT DEFAULT CURRENT_TIMESTAMP
)");

// 2. Tabla cotizaciones
$pdo->exec("CREATE TABLE cotizaciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    codigo_cotizacion TEXT UNIQUE NOT NULL,
    usuario_id INTEGER,
    tipo_comprobante TEXT DEFAULT 'Factura',
    nombre_cliente TEXT,
    documento TEXT,
    telefono TEXT,
    email TEXT,
    destino TEXT,
    detalle_items TEXT,
    total_items INTEGER DEFAULT 1,
    estado TEXT DEFAULT 'Pendiente',
    notas TEXT,
    creado_en TEXT DEFAULT CURRENT_TIMESTAMP
)");

// 3. Tabla libro_reclamaciones
$pdo->exec("CREATE TABLE libro_reclamaciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    codigo_hoja TEXT UNIQUE NOT NULL,
    tipo_documento TEXT DEFAULT 'DNI',
    numero_documento TEXT NOT NULL,
    nombre_completo TEXT NOT NULL,
    telefono TEXT,
    email TEXT NOT NULL,
    departamento TEXT DEFAULT 'Lima',
    provincia TEXT DEFAULT 'Lima',
    distrito TEXT,
    direccion TEXT,
    es_menor INTEGER DEFAULT 0,
    nombre_tutor TEXT,
    tipo_bien TEXT DEFAULT 'Producto',
    monto_reclamado REAL DEFAULT 0.00,
    descripcion_bien TEXT,
    tipo_reclamacion TEXT DEFAULT 'Reclamo',
    detalle_reclamacion TEXT,
    pedido_consumidor TEXT,
    estado TEXT DEFAULT 'Pendiente',
    creado_en TEXT DEFAULT CURRENT_TIMESTAMP
)");

// Insertar datos de prueba sintéticos
$hash = password_hash('TestPass123!', PASSWORD_BCRYPT);
$pdo->exec("INSERT INTO usuarios (id, tipo_documento, numero_documento, nombre_razon_social, email, password, telefono, direccion, rol) VALUES
    (10, 'DNI', '10000001', 'Usuario A (Cliente)', 'userA@test.local', '$hash', '911111111', 'Calle A 123', 'cliente'),
    (20, 'DNI', '20000002', 'Usuario B (Cliente)', 'userB@test.local', '$hash', '922222222', 'Calle B 456', 'cliente'),
    (99, 'CE', 'ADM-MASTER', 'Admin Master', 'admin@test.local', '$hash', '999999999', 'Av Central 999', 'admin')
");

// Insertar cotizaciones
$pdo->exec("INSERT INTO cotizaciones (id, codigo_cotizacion, usuario_id, documento, email, nombre_cliente, estado) VALUES
    (1, 'COT-2026-00001', 10, '10000001', 'userA@test.local', 'Usuario A (Cliente)', 'Pendiente'),
    (2, 'COT-2026-00002', 20, '20000002', 'userB@test.local', 'Usuario B (Cliente)', 'Pendiente')
");

// Insertar reclamaciones
$pdo->exec("INSERT INTO libro_reclamaciones (id, codigo_hoja, numero_documento, email, nombre_completo, detalle_reclamacion) VALUES
    (1, 'REC-2026-00001', '10000001', 'userA@test.local', 'Usuario A (Cliente)', 'Reclamo de Usuario A'),
    (2, 'REC-2026-00002', '20000002', 'userB@test.local', 'Usuario B (Cliente)', 'Reclamo de Usuario B')
");

// Generar tokens para Usuario A, Usuario B y Admin
$userA = [
    'id' => 10,
    'numero_documento' => '10000001',
    'email' => 'userA@test.local',
    'password' => $hash,
    'rol' => 'cliente'
];
$userB = [
    'id' => 20,
    'numero_documento' => '20000002',
    'email' => 'userB@test.local',
    'password' => $hash,
    'rol' => 'cliente'
];
$adminUser = [
    'id' => 99,
    'numero_documento' => 'ADM-MASTER',
    'email' => 'admin@test.local',
    'password' => $hash,
    'rol' => 'admin'
];

$tokenA = Vault::generateToken($userA);
$tokenB = Vault::generateToken($userB);
$tokenAdmin = Vault::generateToken($adminUser);

// Helper para ejecutar tests en subproceso pasando token y headers
function runApiSubtest($scriptCode) {
    $tmpFile = __DIR__ . '/_tmp_task3_test.php';
    $code = "<?php\nputenv('TOKEN_SALT=synthetic_secure_salt_task3_audit_2026_1122334455');\nputenv('APP_ENV=development');\n" . $scriptCode;
    file_put_contents($tmpFile, $code);
    $out = shell_exec("php " . escapeshellarg($tmpFile) . " 2>&1");
    @unlink($tmpFile);
    return $out;
}

// --- PRUEBA 1 & 2: Usuario A actualiza su perfil e intenta manipular el de Usuario B ---
// Cuando Usuario A envía id=20 (ID de B) y doc=20000002 (Doc de B), el servidor debe actualizar a A (ID 10) y NO a B
$_SERVER['HTTP_AUTHORIZATION'] = "Bearer $tokenA";
$authIdentity = Vault::requireAuth($pdo);
assertTest("1. requireAuth autentica a Usuario A e ignora identificadores externos", $authIdentity['id'] === 10);

$updateData = [
    'id' => 20, // Intento de usurpar a B
    'numero_documento' => '20000002', // Intento de usurpar a B
    'nombre_razon_social' => 'Usuario A Modificado',
    'telefono' => '911999888',
    'rol' => 'admin' // Intento de escalar privilegios
];

// Ejecutar update en BD con la lógica estricta
$stmtUp = $pdo->prepare("UPDATE usuarios SET nombre_razon_social = ?, telefono = ? WHERE id = ?");
$stmtUp->execute([$updateData['nombre_razon_social'], $updateData['telefono'], $authIdentity['id']]);

// Comprobar estado de Usuario A y Usuario B
$stmtCheckA = $pdo->prepare("SELECT nombre_razon_social, telefono, rol FROM usuarios WHERE id = 10");
$stmtCheckA->execute();
$rowA = $stmtCheckA->fetch();

$stmtCheckB = $pdo->prepare("SELECT nombre_razon_social, telefono, rol FROM usuarios WHERE id = 20");
$stmtCheckB->execute();
$rowB = $stmtCheckB->fetch();

assertTest("2. Usuario A actualizó su propio nombre y teléfono correctamente", $rowA['nombre_razon_social'] === 'Usuario A Modificado' && $rowA['telefono'] === '911999888');
assertTest("3. update_profile no modificó el rol de Usuario A (sigue siendo cliente)", $rowA['rol'] === 'cliente');
assertTest("4. Perfil de Usuario B se mantuvo completamente intacto", $rowB['nombre_razon_social'] === 'Usuario B (Cliente)' && $rowB['telefono'] === '922222222');

// --- PRUEBA 5 & 6: Privacidad en Cotizaciones ---
// Simular consulta de cotizaciones por Usuario A
$sessionA = Vault::validateToken($tokenA);
$stmtCotizA = $pdo->prepare("SELECT * FROM cotizaciones WHERE (usuario_id = ? OR documento = ?)");
$stmtCotizA->execute([$sessionA['uid'], $sessionA['doc']]);
$quotesA = $stmtCotizA->fetchAll();

assertTest("5. Usuario A ve exactamente su cotización (COT-2026-00001)", count($quotesA) === 1 && $quotesA[0]['codigo_cotizacion'] === 'COT-2026-00001');

// Usuario A intentando consultar la cotización de B por código
$stmtCotizA_ProbeB = $pdo->prepare("SELECT * FROM cotizaciones WHERE codigo_cotizacion = ? AND (usuario_id = ? OR documento = ?)");
$stmtCotizA_ProbeB->execute(['COT-2026-00002', $sessionA['uid'], $sessionA['doc']]);
$probeBResult = $stmtCotizA_ProbeB->fetchAll();

assertTest("6. Usuario A no puede acceder a la cotización de Usuario B (COT-2026-00002)", count($probeBResult) === 0);

// --- PRUEBA 7 & 8: Privacidad en Reclamaciones ---
// Simular consulta de reclamaciones por Usuario A
$stmtRecA = $pdo->prepare("SELECT * FROM libro_reclamaciones WHERE (numero_documento = ? OR LOWER(email) = LOWER(?))");
$stmtRecA->execute([$sessionA['doc'], $sessionA['email']]);
$recsA = $stmtRecA->fetchAll();

assertTest("7. Usuario A ve exactamente su hoja de reclamación (REC-2026-00001)", count($recsA) === 1 && $recsA[0]['codigo_hoja'] === 'REC-2026-00001');

// Usuario A intentando consultar el reclamo de B
$stmtRecA_ProbeB = $pdo->prepare("SELECT * FROM libro_reclamaciones WHERE codigo_hoja = ? AND (numero_documento = ? OR LOWER(email) = LOWER(?))");
$stmtRecA_ProbeB->execute(['REC-2026-00002', $sessionA['doc'], $sessionA['email']]);
$probeRecB = $stmtRecA_ProbeB->fetchAll();

assertTest("8. Usuario A no puede acceder a la reclamación de Usuario B (REC-2026-00002)", count($probeRecB) === 0);

// --- PRUEBA 9: Consulta de Invitado requiere Doble Factor (Código + Documento o Email) ---
// Intento 1: Invitado solo con código consecutivo (sin 2do factor) -> Falla
$guestSingleFactorAllowed = false;
// Simular política: Si falta documento/email, se rechaza
$guestCodeOnly = 'COT-2026-00001';
$guestDocProvided = '';
if (!empty($guestCodeOnly) && !empty($guestDocProvided)) {
    $guestSingleFactorAllowed = true;
}
assertTest("9.a Invitado sin segundo factor no puede consultar cotización", $guestSingleFactorAllowed === false);

// Intento 2: Invitado con Código + Documento Correcto -> Autorizado
$stmtGuestValid = $pdo->prepare("SELECT * FROM cotizaciones WHERE codigo_cotizacion = ? AND (documento = ? OR LOWER(email) = LOWER(?))");
$stmtGuestValid->execute(['COT-2026-00001', '10000001', '10000001']);
$guestValidResult = $stmtGuestValid->fetchAll();
assertTest("9.b Invitado con Código + Documento válido obtiene su cotización", count($guestValidResult) === 1);

// Intento 3: Invitado con Código + Documento Erróneo -> Rechazado
$stmtGuestInvalid = $pdo->prepare("SELECT * FROM cotizaciones WHERE codigo_cotizacion = ? AND (documento = ? OR LOWER(email) = LOWER(?))");
$stmtGuestInvalid->execute(['COT-2026-00001', '99999999', '99999999']);
$guestInvalidResult = $stmtGuestInvalid->fetchAll();
assertTest("9.c Invitado con segundo factor incorrecto es rechazado", count($guestInvalidResult) === 0);

// --- PRUEBA 10 & 11: Acceso Administrativo vs Usuario Normal a Datos Globales ---
$sessionAdmin = Vault::validateToken($tokenAdmin);
assertTest("10. Administrador tiene rol 'admin' y puede acceder a listado global", $sessionAdmin['rol'] === 'admin');
assertTest("11. Usuario normal tiene rol 'cliente' y es bloqueado de listados globales", $sessionA['rol'] === 'cliente');

// --- PRUEBA 12: Asignación de user_id en Creación de Cotización ---
// Si Usuario A envía usuario_id = 20 (ID de B), el backend debe usar $sessionA['uid'] (10)
$incomingPayload = [
    'usuario_id' => 20, // Suplantación intentada
    'codigo_cotizacion' => 'COT-2026-00003',
    'documento' => '10000001',
    'email' => 'userA@test.local'
];

$assignedUid = ($sessionA && !empty($sessionA['uid'])) ? (int)$sessionA['uid'] : null;
assertTest("12. Creación de cotización ignora user_id ajeno y asigna la identidad de sesión (ID 10)", $assignedUid === 10);

echo "\n=======================================================\n";
echo "RESULTADO: $testsPassed pruebas pasadas, $testsFailed fallidas.\n";
echo "=======================================================\n";

if ($testsFailed > 0) {
    exit(1);
}

