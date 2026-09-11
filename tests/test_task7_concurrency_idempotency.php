<?php
/**
 * Test Suite: Tarea 7 - Concurrencia, Correlativos e Idempotencia (F10)
 */

declare(strict_types=1);

define('RUNNING_MIGRATION_TEST', true);
$GLOBALS['MIGRATION_RUNNER_INCLUDED'] = true;

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/vault.php';
require_once __DIR__ . '/../api/concurrency.php';
require_once __DIR__ . '/../database/migrate.php';

echo "========================================================\n";
echo "EJECUTANDO TESTS DE TAREA 7: CONCURRENCIA E IDEMPOTENCIA\n";
echo "========================================================\n\n";

$dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_task7_' . uniqid() . '.sqlite';
$testPdo = new PDO('sqlite:' . $dbPath);
$testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

runFresh($testPdo, true);

$passed = 0;
$total = 0;

function assertTest(bool $condition, string $testName): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$testName}\n";
    } else {
        echo "  [FAIL] {$testName}\n";
    }
}

// -------------------------------------------------------------
// TEST 1: Generación secuencial y atómica de correlativos
// -------------------------------------------------------------
echo "1. Pruebas de generación secuencial atómica (COT y REC)...\n";
$year = (int)date('Y');
$testPdo->beginTransaction();
$cot1 = ConcurrencyEngine::nextCorrelative($testPdo, 'COTIZACION', $year);
$cot2 = ConcurrencyEngine::nextCorrelative($testPdo, 'COTIZACION', $year);
$cot3 = ConcurrencyEngine::nextCorrelative($testPdo, 'COTIZACION', $year);
$testPdo->commit();

assertTest($cot1 === sprintf('COT-%04d-00001', $year), "Primer correlativo de cotización es COT-{$year}-00001");
assertTest($cot2 === sprintf('COT-%04d-00002', $year), "Segundo correlativo de cotización es COT-{$year}-00002");
assertTest($cot3 === sprintf('COT-%04d-00003', $year), "Tercer correlativo de cotización es COT-{$year}-00003");

$testPdo->beginTransaction();
$rec1 = ConcurrencyEngine::nextCorrelative($testPdo, 'RECLAMACION', $year);
$rec2 = ConcurrencyEngine::nextCorrelative($testPdo, 'RECLAMACION', $year);
$testPdo->commit();

assertTest($rec1 === sprintf('REC-%04d-00001', $year), "Primer correlativo de reclamo es REC-{$year}-00001");
assertTest($rec2 === sprintf('REC-%04d-00002', $year), "Segundo correlativo de reclamo es REC-{$year}-00002");

// -------------------------------------------------------------
// TEST 2: Invarianza ante eliminación (no reuso de correlativos)
// -------------------------------------------------------------
echo "\n2. Pruebas de no reuso tras eliminación física...\n";
// Insertamos registros en cotizaciones simulando datos existentes
$stmt = $testPdo->prepare("INSERT INTO cotizaciones (codigo_cotizacion, tipo_comprobante, documento, nombre_cliente, telefono, email, destino, items, total_items, estado, enviado_whatsapp, creado_en) VALUES (?, 'Factura', '20123456789', 'Empresa X', '999888777', 'contacto@empresa.pe', 'Lima', '[]', 10, 'Pendiente', 0, datetime('now'))");
$stmt->execute([$cot1]);
$stmt->execute([$cot2]);
$stmt->execute([$cot3]);

// Eliminamos cotización 2 y 3
$delStmt = $testPdo->prepare("DELETE FROM cotizaciones WHERE codigo_cotizacion IN (?, ?)");
$delStmt->execute([$cot2, $cot3]);

$testPdo->beginTransaction();
$cot4 = ConcurrencyEngine::nextCorrelative($testPdo, 'COTIZACION', $year);
$testPdo->commit();

assertTest($cot4 === sprintf('COT-%04d-00004', $year), "El nuevo correlativo tras eliminar registros previos es COT-{$year}-00004 (no se reutiliza)");

// -------------------------------------------------------------
// TEST 3: Generación masiva consecutiva sin colisiones
// -------------------------------------------------------------
echo "\n3. Pruebas de generación consecutiva masiva (50 códigos sin colisión)...\n";
$generatedCodes = [];
for ($i = 0; $i < 50; $i++) {
    $testPdo->beginTransaction();
    $code = ConcurrencyEngine::nextCorrelative($testPdo, 'COTIZACION', $year);
    $testPdo->commit();
    $generatedCodes[] = $code;
}

$uniqueCount = count(array_unique($generatedCodes));
assertTest($uniqueCount === 50, "50 códigos generados secuencialmente son estrictamente únicos (obtenidos: {$uniqueCount})");
assertTest(end($generatedCodes) === sprintf('COT-%04d-00054', $year), "El último código generado alcanza exactamente COT-{$year}-00054");

// -------------------------------------------------------------
// TEST 4: Normalización de payload y cálculo de fingerprint SHA-256
// -------------------------------------------------------------
echo "\n4. Pruebas de normalización y fingerprint de payloads...\n";
$payloadA = [
    'documento' => ' 12345678 ',
    'nombre' => 'Juan Perez',
    'items' => [['id' => 1, 'cantidad' => 5], ['id' => 2, 'cantidad' => 10]],
    'id' => 999, // Servidor
    'codigo_cotizacion' => 'COT-2026-99999', // Servidor
    'creado_en' => '2026-09-11 12:00:00' // Servidor
];

$payloadB = [
    'nombre' => 'Juan Perez',
    'items' => [['cantidad' => 5, 'id' => 1], ['cantidad' => 10, 'id' => 2]], // Reordenado internamente
    'documento' => '12345678', // Sin espacios
    'id' => 1000, // Servidor diferente
    'creado_en' => '2026-09-11 12:05:00' // Servidor diferente
];

$payloadDifferentData = [
    'documento' => '12345678',
    'nombre' => 'Juan Perez',
    'items' => [['id' => 1, 'cantidad' => 6]] // Cantidad modificada
];

$hashA = ConcurrencyEngine::normalizePayloadForFingerprint($payloadA);
$hashB = ConcurrencyEngine::normalizePayloadForFingerprint($payloadB);
$hashDiff = ConcurrencyEngine::normalizePayloadForFingerprint($payloadDifferentData);

assertTest($hashA === $hashB, "Payloads con los mismos datos sustanciales pero diferente orden/metadatos de servidor producen el mismo hash");
assertTest($hashA !== $hashDiff, "Payload con cantidades modificadas produce un hash diferente");

// -------------------------------------------------------------
// TEST 5: Idempotencia en Cotizaciones (Nuevo, Replay, Conflicto)
// -------------------------------------------------------------
echo "\n5. Pruebas de Idempotencia en cotizaciones...\n";
$scopeCot = 'cotizacion:create';
$keyCot = 'idemp_test_cot_001';
$docCliente = '77889900';
$userId = 12;

// Primer intento: nuevo
$check1 = ConcurrencyEngine::checkIdempotency($testPdo, $scopeCot, $keyCot, $hashA, $userId, $docCliente);
assertTest($check1['status'] === 'new', "Primer intento con clave nueva retorna status='new'");

// Simulamos guardado en DB dentro de transacción
$testPdo->beginTransaction();
$codigoCot = ConcurrencyEngine::nextCorrelative($testPdo, 'COTIZACION', $year);
$respOriginal = [
    'success' => true,
    'codigo_cotizacion' => $codigoCot,
    'id' => 150,
    'fecha' => '11/09/2026 12:30:00'
];
ConcurrencyEngine::saveIdempotency($testPdo, $scopeCot, $keyCot, $hashA, $codigoCot, $respOriginal, $userId, $docCliente);
$testPdo->commit();

// Segundo intento (Replay idéntico por timeout de red):
$check2 = ConcurrencyEngine::checkIdempotency($testPdo, $scopeCot, $keyCot, $hashA, $userId, $docCliente);
assertTest($check2['status'] === 'replay', "Reintento idéntico retorna status='replay'");
assertTest($check2['codigo'] === $codigoCot, "Reintento devuelve exactamente el mismo código de cotización original ({$codigoCot})");
assertTest($check2['response']['id'] === 150, "Reintento devuelve exactamente el mismo recurso ID (150)");

// Tercer intento: Misma clave pero payload alterado (Conflicto 409)
$check3 = ConcurrencyEngine::checkIdempotency($testPdo, $scopeCot, $keyCot, $hashDiff, $userId, $docCliente);
assertTest($check3['status'] === 'conflict', "Reintento con payload alterado retorna status='conflict'");
assertTest(($check3['code'] ?? '') === 'IDEMPOTENCY_PAYLOAD_MISMATCH', "Código de conflicto es IDEMPOTENCY_PAYLOAD_MISMATCH");

// -------------------------------------------------------------
// TEST 6: Aislamiento por Scope (cotizacion vs reclamacion)
// -------------------------------------------------------------
echo "\n6. Pruebas de separación por tipo de operación (scope)...\n";
$scopeRec = 'reclamacion:create';
$keyCompartida = 'clave_compartida_123';

$testPdo->beginTransaction();
$codigoCotScope = ConcurrencyEngine::nextCorrelative($testPdo, 'COTIZACION', $year);
ConcurrencyEngine::saveIdempotency($testPdo, $scopeCot, $keyCompartida, $hashA, $codigoCotScope, ['tipo' => 'cotizacion'], 1, '11111111');
$testPdo->commit();

// Comprobamos que el scope reclamacion con la misma clave no colisiona
$checkRecScope = ConcurrencyEngine::checkIdempotency($testPdo, $scopeRec, $keyCompartida, $hashA, 1, '11111111');
assertTest($checkRecScope['status'] === 'new', "La misma clave bajo el scope 'reclamacion:create' es independiente y está disponible ('new')");

// -------------------------------------------------------------
// TEST 7: Aislamiento multi-usuario y protección contra suplantación
// -------------------------------------------------------------
echo "\n7. Pruebas de aislamiento multi-usuario en claves de idempotencia...\n";
// Usuario 99 intenta usar la clave registrada por el usuario 12
$checkHijackUser = ConcurrencyEngine::checkIdempotency($testPdo, $scopeCot, $keyCot, $hashA, 99, $docCliente);
assertTest($checkHijackUser['status'] === 'conflict', "Usuario no propietario es rechazado");
assertTest(($checkHijackUser['http_code'] ?? 0) === 403, "Rechazo por sesión ajena retorna código HTTP 403");

// Documento diferente en usuario invitado
$keyGuest = 'guest_idemp_abc';
$testPdo->beginTransaction();
ConcurrencyEngine::saveIdempotency($testPdo, $scopeCot, $keyGuest, $hashA, 'COT-GUEST', ['ok' => true], null, '11223344');
$testPdo->commit();

$checkHijackDoc = ConcurrencyEngine::checkIdempotency($testPdo, $scopeCot, $keyGuest, $hashA, null, '99887766');
assertTest($checkHijackDoc['status'] === 'conflict', "Invitado con documento distinto no puede reclamar clave ajena");
assertTest(($checkHijackDoc['http_code'] ?? 0) === 409, "Conflicto de documento retorna HTTP 409");

// -------------------------------------------------------------
// TEST 8: Manejo de rollback (sin claves bloqueadas ni corrupción)
// -------------------------------------------------------------
echo "\n8. Pruebas de rollback transaccional...\n";
$keyRollback = 'idemp_rollback_key';
$testPdo->beginTransaction();
$tempCode = ConcurrencyEngine::nextCorrelative($testPdo, 'COTIZACION', $year);
ConcurrencyEngine::saveIdempotency($testPdo, $scopeCot, $keyRollback, $hashA, $tempCode, ['dummy' => true]);
// Simular excepción y rollback
$testPdo->rollBack();

$checkAfterRollback = ConcurrencyEngine::checkIdempotency($testPdo, $scopeCot, $keyRollback, $hashA);
assertTest($checkAfterRollback['status'] === 'new', "Clave de idempotencia tras rollback queda libre ('new') y no bloquea futuros reintentos");

// -------------------------------------------------------------
// TEST 9: Extracción de Idempotency-Key desde Headers y Payload
// -------------------------------------------------------------
echo "\n9. Pruebas de extracción de cabecera Idempotency-Key...\n";
$_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'uuid-1234-5678-abcd';
$extractedHeader = ConcurrencyEngine::extractIdempotencyKey([]);
assertTest($extractedHeader === 'uuid-1234-5678-abcd', "Extrae Idempotency-Key de cabecera HTTP estándar");

unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);
$_SERVER['HTTP_X_IDEMPOTENCY_KEY'] = 'x-idemp-999';
$extractedXHeader = ConcurrencyEngine::extractIdempotencyKey([]);
assertTest($extractedXHeader === 'x-idemp-999', "Extrae X-Idempotency-Key de cabecera alternativa");

unset($_SERVER['HTTP_X_IDEMPOTENCY_KEY']);
$extractedBody = ConcurrencyEngine::extractIdempotencyKey(['idempotency_key' => 'body-key-456']);
assertTest($extractedBody === 'body-key-456', "Extrae idempotency_key desde cuerpo de payload");

$extractedMalicious = ConcurrencyEngine::extractIdempotencyKey(['idempotency_key' => 'key<script>alert(1)</script>; DROP TABLE--']);
assertTest(strpos($extractedMalicious, '<script>') === false && strpos($extractedMalicious, ';') === false, "Sanitiza caracteres extraños en clave de idempotencia");

// Cleanup
$testPdo = null;
if (file_exists($dbPath)) {
    @unlink($dbPath);
}

echo "\n========================================================\n";
echo "RESULTADOS TAREA 7: {$passed} de {$total} pruebas superadas\n";
echo "========================================================\n";

if ($passed !== $total) {
    exit(1);
}
exit(0);
