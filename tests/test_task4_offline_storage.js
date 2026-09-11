/**
 * Test Suite para Tarea 4:
 * 1. registerReclamacion() no retorna success: true ni correlativo oficial en HTTP 400, 500 o fallo de red.
 * 2. saveCotizacion() y registerQuote() no retornan success: true ni correlativo oficial en HTTP 400, 500 o fallo de red.
 * 3. sanitizeStorage(), login() y register() nunca guardan contraseñas en localStorage.
 * 4. logout() limpia exhaustivamente tokens y datos privados.
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

// Mock localStorage
class MockLocalStorage {
  constructor() {
    this.store = {};
  }
  getItem(key) {
    return this.store.hasOwnProperty(key) ? this.store[key] : null;
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

// Mock sessionStorage
class MockSessionStorage {
  constructor() {
    this.store = {};
  }
  getItem(key) {
    return this.store.hasOwnProperty(key) ? this.store[key] : null;
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

let passedTests = 0;
let totalTests = 0;

function assert(condition, message) {
  totalTests++;
  if (condition) {
    console.log(`  ✅ PASS: ${message}`);
    passedTests++;
  } else {
    console.error(`  ❌ FAIL: ${message}`);
    process.exitCode = 1;
  }
}

async function runTests() {
  console.log('====================================================');
  console.log('🚀 INICIANDO TEST SUITE TAREA 4: OFFLINE & STORAGE');
  console.log('====================================================\n');

  // Load api.js and auth.js into sandbox
  const apiCode = fs.readFileSync(path.join(__dirname, '../assets/js/api.js'), 'utf8');
  const authCode = fs.readFileSync(path.join(__dirname, '../assets/js/auth.js'), 'utf8');

  // Create isolated sandbox
  const localStorage = new MockLocalStorage();
  const sessionStorage = new MockSessionStorage();

  let fetchMock = null;

  const sandbox = {
    localStorage,
    sessionStorage,
    window: {
      localStorage,
      sessionStorage,
      location: { href: '' },
      addEventListener: () => {}
    },
    document: {
      documentElement: { classList: { contains: () => false } },
      getElementById: () => null,
      querySelectorAll: () => [],
      createElement: () => ({ setAttribute: () => {}, appendChild: () => {}, classList: { add: () => {}, remove: () => {} } }),
      body: { appendChild: () => {}, removeChild: () => {} },
      addEventListener: () => {}
    },
    fetch: (...args) => fetchMock(...args),
    console: { log: () => {}, warn: () => {}, error: () => {} },
    setTimeout: (fn) => fn(),
    clearTimeout: () => {},
    requestAnimationFrame: (fn) => fn(),
    URL: { createObjectURL: () => 'blob:mock', revokeObjectURL: () => {} },
    Date: Date,
    JSON: JSON,
    Math: Math
  };

  vm.createContext(sandbox);
  vm.runInContext(apiCode, sandbox);
  vm.runInContext(authCode, sandbox);

  const ApiService = sandbox.ApiService || sandbox.window.ApiService;
  const Auth = sandbox.Auth || sandbox.window.Auth;

  // TEST 1: registerReclamacion en HTTP 400
  console.log('Test 1: Reclamaciones - Error HTTP 400 del servidor');
  ApiService.hasBackend = true;
  fetchMock = async (url, opts) => {
    if (url.includes('productos.php')) return { ok: true, json: async () => ({ success: true }) };
    return {
      ok: false,
      status: 400,
      json: async () => ({ success: false, error: 'Datos de reclamación inválidos (DNI inválido).' })
    };
  };
  const rec400 = await ApiService.registerReclamacion({ nombre: 'Juan', dni: 'invalid' });
  assert(rec400.success === false, 'registerReclamacion con HTTP 400 devuelve success: false');
  assert(!rec400.codigo_hoja, 'registerReclamacion con HTTP 400 NO genera correlativo oficial (codigo_hoja)');
  assert(rec400.error && (rec400.error.includes('DNI inválido') || rec400.status === 400), 'registerReclamacion propaga el mensaje o estado de error 400');

  // TEST 2: registerReclamacion en HTTP 500
  console.log('\nTest 2: Reclamaciones - Error HTTP 500 del servidor');
  ApiService.hasBackend = true;
  fetchMock = async (url, opts) => {
    if (url.includes('productos.php')) return { ok: true, json: async () => ({ success: true }) };
    return {
      ok: false,
      status: 500,
      json: async () => ({ success: false, error: 'Error interno en base de datos.' })
    };
  };
  const rec500 = await ApiService.registerReclamacion({ nombre: 'Juan', dni: '12345678' });
  assert(rec500.success === false, 'registerReclamacion con HTTP 500 devuelve success: false');
  assert(!rec500.codigo_hoja, 'registerReclamacion con HTTP 500 NO genera correlativo oficial');

  // TEST 3: registerReclamacion en Fallo de Red / Offline
  console.log('\nTest 3: Reclamaciones - Fallo de red / Offline');
  ApiService.hasBackend = null;
  fetchMock = async (url, opts) => {
    throw new Error('Network unreachable');
  };
  const recOffline = await ApiService.registerReclamacion({ nombre: 'Juan', dni: '12345678' });
  assert(recOffline.success === false, 'registerReclamacion offline devuelve success: false');
  assert(!recOffline.codigo_hoja, 'registerReclamacion offline NO inventa correlativo oficial REC-');

  // TEST 4: saveCotizacion en HTTP 400
  console.log('\nTest 4: Cotizaciones (saveCotizacion) - Error HTTP 400');
  ApiService.hasBackend = true;
  fetchMock = async (url, opts) => {
    if (url.includes('productos.php')) return { ok: true, json: async () => ({ success: true }) };
    return {
      ok: false,
      status: 400,
      json: async () => ({ success: false, error: 'Documento de cliente no válido.' })
    };
  };
  const cot400 = await ApiService.saveCotizacion({ documento: 'abc', items: [] });
  assert(cot400.success === false, 'saveCotizacion con HTTP 400 devuelve success: false');
  assert(!cot400.codigo_cotizacion, 'saveCotizacion con HTTP 400 NO genera correlativo oficial COT-');

  // TEST 5: saveCotizacion en HTTP 500
  console.log('\nTest 5: Cotizaciones (saveCotizacion) - Error HTTP 500');
  ApiService.hasBackend = true;
  fetchMock = async (url, opts) => {
    if (url.includes('productos.php')) return { ok: true, json: async () => ({ success: true }) };
    return {
      ok: false,
      status: 500,
      json: async () => ({ success: false, error: 'Error interno en base de datos.' })
    };
  };
  const cot500 = await ApiService.saveCotizacion({ documento: '20601234567', items: [] });
  assert(cot500.success === false, 'saveCotizacion con HTTP 500 devuelve success: false');
  assert(!cot500.codigo_cotizacion, 'saveCotizacion con HTTP 500 NO genera correlativo oficial COT-');

  // TEST 6: registerQuote en Fallo de Red / Offline
  console.log('\nTest 6: Cotizaciones (registerQuote) - Fallo de red / Offline');
  ApiService.hasBackend = null;
  fetchMock = async (url, opts) => {
    throw new Error('Network error');
  };
  const quoteOffline = await ApiService.registerQuote({ documento: '20601234567', items: [] });
  assert(quoteOffline.success === false, 'registerQuote offline devuelve success: false');
  assert(!quoteOffline.codigo_cotizacion, 'registerQuote offline NO genera correlativo oficial COT-');

  // TEST 7: Almacenamiento seguro en login y registro (sin contraseñas)
  console.log('\nTest 7: Autenticación - Sin contraseñas en localStorage');
  ApiService.hasBackend = true;
  fetchMock = async (url, opts) => {
    if (url.includes('productos.php')) return { ok: true, json: async () => ({ success: true }) };
    if (url.includes('action=login')) {
      return {
        ok: true,
        json: async () => ({
          success: true,
          user: {
            id: 10,
            nombre_razon_social: 'Cliente Seguro',
            email: 'cliente@test.pe',
            rol: 'cliente',
            token: 'valid.token.hmac',
            password: 'plaintext_password_that_must_be_removed',
            password_hash: '$2y$10$hashthatmustberemoved'
          }
        })
      };
    }
  };
  const loginRes = await ApiService.login('cliente@test.pe', 'secret123');
  assert(loginRes.success === true, 'Login procesa respuesta exitosa');
  assert(!loginRes.user.password, 'Respuesta de login no incluye password');
  assert(!loginRes.user.password_hash, 'Respuesta de login no incluye password_hash');

  const storedUser = JSON.parse(localStorage.getItem('dp_usuario_activo') || '{}');
  assert(!storedUser.password, 'dp_usuario_activo en localStorage NO contiene campo password');
  assert(!storedUser.password_hash, 'dp_usuario_activo en localStorage NO contiene campo password_hash');
  assert(storedUser.token === 'valid.token.hmac', 'dp_usuario_activo conserva token de sesión válido');

  // TEST 8: sanitizeStorage limpia contraseñas residuales
  console.log('\nTest 8: sanitizeStorage limpia residuos heredados en localStorage');
  localStorage.setItem('dp_usuarios_registrados', JSON.stringify([
    { id: 1, email: 'u1@test.pe', password: 'plainPassword123' },
    { id: 2, email: 'u2@test.pe', password_hash: 'hash456' }
  ]));
  ApiService.sanitizeStorage();
  const cleanedUsers = JSON.parse(localStorage.getItem('dp_usuarios_registrados') || '[]');
  assert(cleanedUsers.every(u => !u.password && !u.password_hash), 'sanitizeStorage eliminó todos los campos password y password_hash');

  // TEST 9: Auth.logout() limpia exhaustivamente datos de sesión
  console.log('\nTest 9: Auth.logout() limpia tokens, cachés y estados privados');
  localStorage.setItem('dp_usuario_activo', JSON.stringify({ id: 1, rol: 'admin', token: 'token123' }));
  localStorage.setItem('dp_mis_cotizaciones', JSON.stringify([{ id: 1 }]));
  localStorage.setItem('dp_historial_cotizaciones', JSON.stringify([{ id: 1 }]));
  localStorage.setItem('dp_cotizaciones_recibidas', JSON.stringify([{ id: 1 }]));
  localStorage.setItem('dp_libro_reclamaciones', JSON.stringify([{ id: 1 }]));
  localStorage.setItem('dp_admin_notifications_cache', JSON.stringify({ count: 5 }));
  sessionStorage.setItem('dp_auth_message', 'Private message');

  Auth.logout();

  assert(localStorage.getItem('dp_usuario_activo') === null, 'logout() eliminó dp_usuario_activo');
  assert(localStorage.getItem('dp_mis_cotizaciones') === null, 'logout() eliminó dp_mis_cotizaciones');
  assert(localStorage.getItem('dp_historial_cotizaciones') === null, 'logout() eliminó dp_historial_cotizaciones');
  assert(localStorage.getItem('dp_cotizaciones_recibidas') === null, 'logout() eliminó dp_cotizaciones_recibidas');
  assert(localStorage.getItem('dp_libro_reclamaciones') === null, 'logout() eliminó dp_libro_reclamaciones');
  assert(localStorage.getItem('dp_admin_notifications_cache') === null, 'logout() eliminó dp_admin_notifications_cache');
  assert(sessionStorage.getItem('dp_auth_message') === null, 'logout() limpió sessionStorage');

  console.log('\n====================================================');
  console.log(`📊 RESULTADOS: ${passedTests}/${totalTests} pruebas pasadas exitosamente.`);
  console.log('====================================================\n');
}

runTests();
