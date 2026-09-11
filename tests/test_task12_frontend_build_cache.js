/**
 * ====================================================================
 * TEST SUITE TAREA 12: FRONTEND, TAILWIND LOCAL Y CACHÉ RESILIENTE (F16, F17)
 * Plataforma Descartables Peruanos
 * ====================================================================
 * Valida:
 * 1. 0 ocurrencias de cdn.tailwindcss.com en todos los HTML oficiales.
 * 2. Enlace a assets/css/tailwind.css en los 10 archivos HTML.
 * 3. Compilación reproducible de Tailwind CSS vía package.json y npm run build:css.
 * 4. Calidad, minificación y presencia de clases de marca en tailwind.css.
 * 5. Resiliencia de almacenamiento local (StorageHelper / F17): auto-purgado y tolerancia a fallos.
 * 6. Deduplicación y aliases canónicos en ApiService (registerQuote, getQuotes, F16).
 * 7. Empaquetado seguro en scripts/build_package.js (inclusión de CSS compilado, exclusión de dev).
 * 8. Integridad estructural y de estilos en las páginas principales.
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const rootDir = path.resolve(__dirname, '..');

let totalTests = 0;
let passedTests = 0;

function assert(condition, message) {
  totalTests++;
  if (condition) {
    console.log(`  ✅ PASS: ${message}`);
    passedTests++;
  } else {
    console.error(`  ❌ FAIL: ${message}`);
  }
}

// Mock de localStorage para simular corrupción y auto-recuperación
class MockLocalStorage {
  constructor() {
    this.store = {};
  }
  getItem(key) {
    return Object.prototype.hasOwnProperty.call(this.store, key) ? this.store[key] : null;
  }
  setItem(key, value) {
    this.store[key] = String(value);
  }
  removeItem(key) {
    delete this.store[key];
  }
  clear() {
    this.store = {};
  }
}

console.log('====================================================');
console.log('🚀 INICIANDO TEST SUITE TAREA 12: FRONTEND & STORAGE (F16, F17)');
console.log('====================================================\n');

// ----------------------------------------------------
// GRUPO 1: Eliminación del CDN de Tailwind en todos los HTML
// ----------------------------------------------------
console.log('Test 1: 0 ocurrencias de cdn.tailwindcss.com en los 10 HTML');
const OFFICIAL_HTML_FILES = [
  'index.html',
  'catalogo.html',
  'admin.html',
  'perfil.html',
  'login.html',
  'registro.html',
  'contacto.html',
  'libro-de-reclamaciones.html',
  'terminos-y-condiciones.html',
  '404.html'
];

OFFICIAL_HTML_FILES.forEach(file => {
  const filePath = path.join(rootDir, file);
  assert(fs.existsSync(filePath), `El archivo ${file} existe en el proyecto`);
  const content = fs.readFileSync(filePath, 'utf8');
  assert(!content.includes('cdn.tailwindcss.com'), `${file} NO contiene cdn.tailwindcss.com`);
  assert(content.includes('assets/css/tailwind.css'), `${file} enlaza correctamente assets/css/tailwind.css`);
});

// ----------------------------------------------------
// GRUPO 2: Pipeline y Configuración de Tailwind CSS
// ----------------------------------------------------
console.log('\nTest 2: Configuración del Pipeline de Compilación');
const packageJsonPath = path.join(rootDir, 'package.json');
assert(fs.existsSync(packageJsonPath), 'package.json existe en el repositorio');

const packageJson = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));
assert(packageJson.scripts && packageJson.scripts['build:css'], 'package.json declara script "build:css"');
assert(packageJson.devDependencies && packageJson.devDependencies.tailwindcss, 'package.json tiene tailwindcss como devDependency');

const tailwindConfigPath = path.join(rootDir, 'tailwind.config.js');
assert(fs.existsSync(tailwindConfigPath), 'tailwind.config.js existe');
const tailwindConfigContent = fs.readFileSync(tailwindConfigPath, 'utf8');
assert(tailwindConfigContent.includes("darkMode: 'class'"), 'tailwind.config.js configura darkMode: class');
assert(tailwindConfigContent.includes('warm') && tailwindConfigContent.includes('terracota'), 'tailwind.config.js incluye paleta warm editorial');
assert(tailwindConfigContent.includes('brand') && tailwindConfigContent.includes('admin'), 'tailwind.config.js incluye paleta admin');
assert(tailwindConfigContent.includes('safelist'), 'tailwind.config.js define safelist de clases dinámicas');

const tailwindInputPath = path.join(rootDir, 'assets', 'css', 'tailwind-input.css');
assert(fs.existsSync(tailwindInputPath), 'assets/css/tailwind-input.css existe');

// ----------------------------------------------------
// GRUPO 3: Verificación del CSS Compilado Oficial
// ----------------------------------------------------
console.log('\nTest 3: Inspección de assets/css/tailwind.css compilado');
const tailwindCssPath = path.join(rootDir, 'assets', 'css', 'tailwind.css');
assert(fs.existsSync(tailwindCssPath), 'assets/css/tailwind.css existe');

const cssStats = fs.statSync(tailwindCssPath);
assert(cssStats.size > 50000, `tailwind.css tiene tamaño sustancial (${Math.round(cssStats.size / 1024)} KB)`);

const compiledCss = fs.readFileSync(tailwindCssPath, 'utf8');
assert(compiledCss.includes('bg-warm-cream') || compiledCss.includes('#FDFBF7'), 'tailwind.css contiene clase o color bg-warm-cream');
assert(compiledCss.includes('bg-terracota') || compiledCss.includes('#C85A32'), 'tailwind.css contiene clase o color bg-terracota');
assert(compiledCss.includes('.dark '), 'tailwind.css compila selectores con variante .dark');
assert(compiledCss.includes('text-brand-orange') || compiledCss.includes('#ea580c'), 'tailwind.css contiene clases brand para admin');
assert(compiledCss.includes('bg-amber-50') && compiledCss.includes('text-amber-700'), 'tailwind.css incluye badges safelisted (amber)');
assert(compiledCss.includes('bg-emerald-50') && compiledCss.includes('text-emerald-700'), 'tailwind.css incluye badges safelisted (emerald)');

// ----------------------------------------------------
// GRUPO 4: Resiliencia de Almacenamiento Local (StorageHelper / F17)
// ----------------------------------------------------
console.log('\nTest 4: Resiliencia ante JSON corrupto en LocalStorage (F17)');

const mockStorage = new MockLocalStorage();
global.localStorage = mockStorage;
global.window = { localStorage: mockStorage };

// Cargar api.js en el entorno
const { ApiService, StorageHelper } = require('../assets/js/api.js');

assert(typeof StorageHelper === 'object' && StorageHelper !== null, 'StorageHelper está expuesto');
assert(typeof StorageHelper.get === 'function', 'StorageHelper.get es una función');
assert(typeof StorageHelper.set === 'function', 'StorageHelper.set es una función');

// 4.1 Caso: Clave inexistente o vacía
assert(StorageHelper.get('clave_inexistente', 'fallback') === 'fallback', 'Retorna fallback en clave inexistente');
assert(StorageHelper.get('clave_inexistente', []) instanceof Array, 'Retorna arreglo fallback si clave no existe');

// 4.2 Caso: Clave con JSON válido
StorageHelper.set('prueba_valida', { nombre: 'Pamolsa', items: [1, 2, 3] });
const validData = StorageHelper.get('prueba_valida');
assert(validData && validData.nombre === 'Pamolsa' && validData.items.length === 3, 'Lee y parsea JSON válido correctamente');

// 4.3 Caso: Clave con JSON corrupto (truncado, comillas rotas, undefined como string)
const CORRUPT_KEYS = [
  { key: 'dp_categorias_cache', raw: '{"id": 1, "nombre": "incompleto...', fallback: null },
  { key: 'dp_productos_custom', raw: '[{ id: 1, "nombre": sin_comillas', fallback: [] },
  { key: 'dp_cotizaciones_recibidas', raw: 'undefined', fallback: [] },
  { key: 'dp_libro_reclamaciones', raw: '{ bad json here %%% }', fallback: [] },
  { key: 'dp_usuario_activo', raw: '{"token": "xyz", "rol": "admin" [broken]', fallback: null },
  { key: 'dp_admin_notifications_cache', raw: 'NaN', fallback: null },
  { key: 'dp_empresa_config', raw: 'null_invalido: 123', fallback: null }
];

CORRUPT_KEYS.forEach(tc => {
  mockStorage.setItem(tc.key, tc.raw);
  let threw = false;
  let res = null;
  try {
    res = StorageHelper.get(tc.key, tc.fallback);
  } catch (e) {
    threw = true;
  }
  assert(!threw, `StorageHelper.get('${tc.key}') NO lanzó SyntaxError con datos corruptos`);
  assert(res === tc.fallback || JSON.stringify(res) === JSON.stringify(tc.fallback), `Retornó fallback esperado para '${tc.key}'`);
  assert(mockStorage.getItem(tc.key) === null, `Purgó automáticamente la clave dañada '${tc.key}' de localStorage (self-healing)`);
});

// 4.4 Inmunidad en llamadas de alto nivel de ApiService frente a datos corruptos
console.log('\nTest 5: Métodos de ApiService con cachés locales corruptas');

// Corromper dp_categorias_cache y llamar getCategories
mockStorage.setItem('dp_categorias_cache', '{ corrupt: data');
let threwCat = false;
let cats = [];
try {
  cats = ApiService.safeJsonStorage('dp_categorias_cache', []);
} catch (e) {
  threwCat = true;
}
assert(!threwCat && Array.isArray(cats), 'safeJsonStorage maneja dp_categorias_cache corrupto');
assert(mockStorage.getItem('dp_categorias_cache') === null, 'dp_categorias_cache corrupto fue purgado');

// Corromper dp_usuario_activo y verificar getAuthHeaders
mockStorage.setItem('dp_usuario_activo', '{"token": corrupt');
const headers = ApiService.getAuthHeaders();
assert(typeof headers === 'object' && !headers['Authorization'], 'getAuthHeaders maneja dp_usuario_activo corrupto sin lanzar error');
assert(mockStorage.getItem('dp_usuario_activo') === null, 'dp_usuario_activo corrupto purgado (sesión inválida)');

// Corromper dp_usuario_activo y verificar sanitizeStorage
mockStorage.setItem('dp_usuario_activo', '{ bad json');
ApiService.sanitizeStorage();
assert(mockStorage.getItem('dp_usuario_activo') === null, 'sanitizeStorage purga dp_usuario_activo corrupto');

// ----------------------------------------------------
// GRUPO 5: Deduplicación y Consistencia de Métodos (F16)
// ----------------------------------------------------
console.log('\nTest 6: Deduplicación y Compatibilidad de ApiService');

assert(typeof ApiService.registerQuote === 'function', 'ApiService.registerQuote existe');
assert(typeof ApiService.saveCotizacion === 'function', 'ApiService.saveCotizacion existe');
assert(typeof ApiService.getQuotes === 'function', 'ApiService.getQuotes existe');
assert(typeof ApiService.getCotizaciones === 'function', 'ApiService.getCotizaciones existe');

// Validar que registerQuote delega en saveCotizacion
let saveCotizacionCalled = false;
let passedQuoteData = null;
const origSaveCotizacion = ApiService.saveCotizacion;
ApiService.saveCotizacion = async function(data, key) {
  saveCotizacionCalled = true;
  passedQuoteData = data;
  return { success: true, codigo_cotizacion: 'COT-TEST-99', modo: 'test' };
};

ApiService.registerQuote({ test: true, cliente: 'Cliente Prueba' }, 'idemp_test_123').then(result => {
  assert(saveCotizacionCalled, 'registerQuote invoca transparentemente a saveCotizacion');
  assert(passedQuoteData && passedQuoteData.test === true, 'registerQuote pasa los argumentos correctos');
  assert(result && result.codigo_cotizacion === 'COT-TEST-99', 'registerQuote retorna el resultado de saveCotizacion');
  ApiService.saveCotizacion = origSaveCotizacion;
});

// Validar que getQuotes delega en getCotizaciones
let getCotizacionesCalled = false;
const origGetCotizaciones = ApiService.getCotizaciones;
ApiService.getCotizaciones = async function(filters) {
  getCotizacionesCalled = true;
  return { success: true, count: 2, data: [{ id: 1, cod: 'COT-1' }, { id: 2, cod: 'COT-2' }] };
};

ApiService.getQuotes({ estado: 'Pendiente' }).then(quotesArray => {
  assert(getCotizacionesCalled, 'getQuotes invoca transparentemente a getCotizaciones');
  assert(Array.isArray(quotesArray) && quotesArray.length === 2, 'getQuotes devuelve el arreglo de cotizaciones');
  ApiService.getCotizaciones = origGetCotizaciones;
});

// Validar gestión de usuarios y subida de imágenes
assert(typeof ApiService.getUsers === 'function', 'ApiService.getUsers existe');
assert(typeof ApiService.updateUserRole === 'function', 'ApiService.updateUserRole existe');
assert(typeof ApiService.deleteUser === 'function', 'ApiService.deleteUser existe');
assert(typeof ApiService.uploadProductImage === 'function', 'ApiService.uploadProductImage existe');

// ----------------------------------------------------
// GRUPO 6: Empaquetado de Producción (scripts/build_package.js)
// ----------------------------------------------------
console.log('\nTest 7: Validación de scripts/build_package.js');

try {
  const buildOutput = execSync('node scripts/build_package.js', { cwd: rootDir, encoding: 'utf8' });
  assert(buildOutput.includes('[OK] Paquete generado limpiamente'), 'scripts/build_package.js finaliza exitosamente');
} catch (e) {
  assert(false, `scripts/build_package.js falló: ${e.message}`);
}

const distDir = path.join(rootDir, 'dist', 'package');
const distTailwindCss = path.join(distDir, 'assets', 'css', 'tailwind.css');
assert(fs.existsSync(distTailwindCss), 'dist/package/assets/css/tailwind.css está presente en el paquete final');

const distTailwindStats = fs.statSync(distTailwindCss);
assert(distTailwindStats.size > 20000, `dist/package/assets/css/tailwind.css tiene tamaño válido (${Math.round(distTailwindStats.size / 1024)} KB)`);

// Validar que archivos de desarrollo están estrictamente excluidos del paquete
const FORBIDDEN_DIST_ENTRIES = [
  'node_modules',
  'package.json',
  'package-lock.json',
  'tailwind.config.js',
  'tailwind-input.css',
  'secrets.php',
  '.env',
  '.git',
  'logs',
  'backups'
];

FORBIDDEN_DIST_ENTRIES.forEach(entry => {
  const distPath = path.join(distDir, entry);
  const assetsCssInput = path.join(distDir, 'assets', 'css', 'tailwind-input.css');
  assert(!fs.existsSync(distPath), `dist/package/ NO contiene '${entry}'`);
  assert(!fs.existsSync(assetsCssInput), `dist/package/assets/css/ NO contiene 'tailwind-input.css'`);
});

// Validar que ningún HTML en dist/package contenga CDN
OFFICIAL_HTML_FILES.forEach(file => {
  const distHtmlPath = path.join(distDir, file);
  if (fs.existsSync(distHtmlPath)) {
    const htmlContent = fs.readFileSync(distHtmlPath, 'utf8');
    assert(!htmlContent.includes('cdn.tailwindcss.com'), `dist/package/${file} NO contiene cdn.tailwindcss.com`);
    assert(htmlContent.includes('assets/css/tailwind.css'), `dist/package/${file} enlaza tailwind.css`);
  }
});

// ----------------------------------------------------
// RESUMEN FINAL
// ----------------------------------------------------
console.log('\n====================================================');
console.log(`📊 RESULTADOS TAREA 12: ${passedTests} / ${totalTests} PRUEBAS SUPERADAS`);
console.log('====================================================');

if (passedTests === totalTests) {
  process.exit(0);
} else {
  process.exit(1);
}

