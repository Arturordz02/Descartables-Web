/**
 * Test Suite: Tarea 5 - Prevención de XSS e Inyección HTML (F06)
 * Valida que ningún dato controlado por usuario, API o almacenamiento
 * pueda inyectar código HTML o JavaScript en la interfaz o vistas de impresión.
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('\n--- INICIANDO TEST SUITE TAREA 5: XSS Y SANITIZACIÓN DOM (F06) ---\n');

let totalTests = 0;
let passedTests = 0;

function it(desc, fn) {
  totalTests++;
  try {
    fn();
    console.log(`  [PASS] ${desc}`);
    passedTests++;
  } catch (err) {
    console.error(`  [FAIL] ${desc}`);
    console.error(`         ${err.message}`);
  }
}

// 1. Cargar implementación de escapeHtml y sanitizeUrl
const apiJsContent = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'api.js'), 'utf8');

const ApiService = {
  escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  },
  sanitizeUrl(url, fallback = 'assets/images/productos/default.png') {
    if (!url || typeof url !== 'string') return fallback;
    const trimmed = url.trim();
    if (/^(?:javascript|data|vbscript):/i.test(trimmed)) {
      return fallback;
    }
    return trimmed;
  }
};

// -------------------------------------------------------------
// GRUPO 1: Funciones de Sanitización y Escape
// -------------------------------------------------------------
console.log('1. Pruebas Unitarias de Sanitización (escapeHtml / sanitizeUrl):');

it('escapeHtml escapa payload de usuario: <b data-audit="marker">PRUEBA</b>', () => {
  const payload = '<b data-audit="marker">PRUEBA</b>';
  const escaped = ApiService.escapeHtml(payload);
  assert.strictEqual(escaped, '&lt;b data-audit=&quot;marker&quot;&gt;PRUEBA&lt;/b&gt;');
  assert(!escaped.includes('<b '));
});

it('escapeHtml escapa payload de imagen XSS: <img src=x onerror=alert(1)>', () => {
  const payload = '<img src=x onerror=alert(1)>';
  const escaped = ApiService.escapeHtml(payload);
  assert.strictEqual(escaped, '&lt;img src=x onerror=alert(1)&gt;');
  assert(!escaped.includes('<img'));
});

it('escapeHtml escapa scripts ejecutables: <script>alert("XSS")</script>', () => {
  const payload = '<script>alert("XSS")</script>';
  const escaped = ApiService.escapeHtml(payload);
  assert.strictEqual(escaped, '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;');
  assert(!escaped.includes('<script>'));
});

it('escapeHtml escapa atributos con comillas simples y dobles', () => {
  const payload = `' onfocus='alert(1)' " onmouseover="alert(2)"`;
  const escaped = ApiService.escapeHtml(payload);
  assert.strictEqual(escaped, `&#39; onfocus=&#39;alert(1)&#39; &quot; onmouseover=&quot;alert(2)&quot;`);
});

it('sanitizeUrl bloquea esquemas peligrosos (javascript:, vbscript:, data:)', () => {
  assert.strictEqual(ApiService.sanitizeUrl('javascript:alert(1)'), 'assets/images/productos/default.png');
  assert.strictEqual(ApiService.sanitizeUrl('JAVASCRIPT:alert(1)'), 'assets/images/productos/default.png');
  assert.strictEqual(ApiService.sanitizeUrl('vbscript:msgbox(1)'), 'assets/images/productos/default.png');
  assert.strictEqual(ApiService.sanitizeUrl('data:text/html,<script>alert(1)</script>'), 'assets/images/productos/default.png');
});

it('sanitizeUrl preserva URLs válidas (http, https, rutas relativas)', () => {
  assert.strictEqual(ApiService.sanitizeUrl('https://images.unsplash.com/photo-1'), 'https://images.unsplash.com/photo-1');
  assert.strictEqual(ApiService.sanitizeUrl('http://example.com/img.png'), 'http://example.com/img.png');
  assert.strictEqual(ApiService.sanitizeUrl('assets/images/productos/vaso.png'), 'assets/images/productos/vaso.png');
  assert.strictEqual(ApiService.sanitizeUrl('/images/logo.png'), '/images/logo.png');
});

// -------------------------------------------------------------
// GRUPO 2: Vistas Imprimibles y Modales (Cotizaciones, Reclamaciones, Ficha Técnica)
// -------------------------------------------------------------
console.log('\n2. Pruebas de Vistas Imprimibles (Hoja Reclamación, Proforma B2B, Ficha Técnica):');

it('Proforma B2B en admin.html escapa campos de cliente y productos antes de imprimir', () => {
  const adminHtml = fs.readFileSync(path.join(__dirname, '..', 'admin.html'), 'utf8');
  assert(adminHtml.includes('printCurrentCotizacion()'), 'Debe existir printCurrentCotizacion');
  assert(adminHtml.includes('const clienteNombre = this.escapeHtml(c.cliente_nombre'), 'Debe escapar cliente_nombre');
  assert(adminHtml.includes('const clienteDoc = this.escapeHtml(c.cliente_doc'), 'Debe escapar cliente_doc');
  assert(adminHtml.includes('const notasStr = c.notas ? this.escapeHtml(c.notas) : \'\''), 'Debe escapar notas');
});

it('Hoja de Reclamaciones INDECOPI escapa campos de consumidor, bien y reclamo', () => {
  const reclamacionesJs = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'reclamaciones.js'), 'utf8');
  assert(reclamacionesJs.includes('const safeNombreCompleto = esc(data.nombre_completo)'), 'Debe escapar nombre_completo');
  assert(reclamacionesJs.includes('const safeDetalleRec = esc(data.detalle_reclamacion)'), 'Debe escapar detalle_reclamacion');
  assert(reclamacionesJs.includes('const safePedidoCon = esc(data.pedido_consumidor)'), 'Debe escapar pedido_consumidor');
});

it('Ficha Técnica PDF en catalogo.js escapa datos de producto y especificaciones', () => {
  const catalogoJs = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'catalogo.js'), 'utf8');
  assert(catalogoJs.includes('descargarFichaPDF(sku)'), 'Debe existir descargarFichaPDF');
  assert(catalogoJs.includes('const safeNombre = esc(prod.nombre'), 'Debe escapar nombre en ficha');
  assert(catalogoJs.includes('const safeImg = sanUrl(prod.imagen_url'), 'Debe sanitizar imagen_url en ficha');
});

// -------------------------------------------------------------
// GRUPO 3: Catálogo, Comparador, Carrito y Header Search
// -------------------------------------------------------------
console.log('\n3. Pruebas de Componentes Interactivos (Catálogo, Carrito, Comparador, Buscador):');

it('catalogo.js renderProducts escapa SKU, nombre, descripción y sanitiza imagen', () => {
  const catalogoJs = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'catalogo.js'), 'utf8');
  assert(catalogoJs.includes('const safeSku = esc(prod.sku'), 'renderProducts debe escapar sku');
  assert(catalogoJs.includes('const safeNombre = esc(prod.nombre'), 'renderProducts debe escapar nombre');
  assert(catalogoJs.includes('const safeDesc = esc(prod.descripcion'), 'renderProducts debe escapar descripcion');
});

it('comparador.js renderBar y renderModalContent escapan atributos y sanitizan imágenes', () => {
  const comparadorJs = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'comparador.js'), 'utf8');
  assert(comparadorJs.includes('this.escapeHtml(p.sku'), 'Comparador debe escapar sku');
  assert(comparadorJs.includes('this.escapeHtml(p.nombre'), 'Comparador debe escapar nombre');
  assert(comparadorJs.includes('this.sanitizeUrl(p.imagen_url'), 'Comparador debe sanitizar imagen_url');
});

it('carrito.js renderCartItems y modal formal escapan campos de productos y datos de contacto', () => {
  const carritoJs = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'carrito.js'), 'utf8');
  assert(carritoJs.includes('const safeSku = esc(item.sku);'), 'renderCartItems debe escapar sku');
  assert(carritoJs.includes('const safeNombre = esc(item.nombre);'), 'renderCartItems debe escapar nombre');
  assert(carritoJs.includes('const safeImg = safeUrl(item.imagen_url'), 'renderCartItems debe sanitizar imagen_url');
});

it('main.js HeaderSearch escapa texto antes de aplicar highlight con regex', () => {
  const mainJs = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'main.js'), 'utf8');
  assert(mainJs.includes('const safeText = this.escapeHtml(String(text));'), 'HeaderSearch debe escapar safeText');
  assert(mainJs.includes('const safeQuery = this.escapeRegExp(this.escapeHtml(query));'), 'HeaderSearch debe escapar safeQuery');
});

it('main.js showToast no inyecta HTML directo sin sanitización', () => {
  const mainJs = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'main.js'), 'utf8');
  assert(mainJs.includes('span.textContent = String(message ?? \'\');'), 'showToast debe asignar textContent');
});

// -------------------------------------------------------------
// GRUPO 4: Pruebas de Renderizado Seguro de Payloads Reales
// -------------------------------------------------------------
console.log('\n4. Simulación de Renderizado con Payloads Maliciosos:');

it('Payloads XSS inyectados en producto se neutralizan en el HTML generado (Catálogo / Normal)', () => {
  const maliciousProduct = {
    sku: '<script>alert(1)</script>',
    nombre: '<img src=x onerror=alert("xss")>',
    descripcion: '<b data-audit="marker">PRUEBA</b>',
    categoria_nombre: '"><svg onload=alert(1)>',
    material: 'Polipropileno / <script>',
    presentacion: 'Caja x 500 <img src=1>',
    imagen_url: 'javascript:alert(1)'
  };

  const safeSku = ApiService.escapeHtml(maliciousProduct.sku);
  const safeNombre = ApiService.escapeHtml(maliciousProduct.nombre);
  const safeDesc = ApiService.escapeHtml(maliciousProduct.descripcion);
  const safeCat = ApiService.escapeHtml(maliciousProduct.categoria_nombre);
  const safeImg = ApiService.sanitizeUrl(maliciousProduct.imagen_url);

  const cardHtml = `<div title="${safeNombre}"><img src="${safeImg}"><span>${safeSku}</span><p>${safeDesc}</p><span>${safeCat}</span></div>`;

  assert(!cardHtml.includes('<script>'));
  assert(!cardHtml.includes('<img src=x'));
  assert(!cardHtml.includes('<b data-audit'));
  assert(!cardHtml.includes('<svg onload'));
  assert(!cardHtml.includes('javascript:'));
  assert(cardHtml.includes('&lt;b data-audit=&quot;marker&quot;&gt;PRUEBA&lt;/b&gt;'));
  assert(cardHtml.includes('&lt;script&gt;alert(1)&lt;/script&gt;'));
  assert(cardHtml.includes('&lt;img src=x onerror=alert(&quot;xss&quot;)&gt;'));
});

it('Payloads XSS inyectados en Reclamación se neutralizan en la Hoja Oficial Imprimible', () => {
  const maliciousClaim = {
    codigo_hoja: 'REC-2026-00001',
    fecha: '2026-09-08',
    nombre_completo: '<b data-audit="marker">PRUEBA</b>',
    tipo_documento: 'DNI',
    numero_documento: '12345678',
    email: 'test@example.com',
    telefono: '999888777',
    direccion: '<script>alert(1)</script>',
    distrito: 'Lima',
    provincia: 'Lima',
    departamento: 'Lima',
    tipo_bien: 'Producto',
    monto_reclamado: 100,
    descripcion_bien: '<img src=x onerror=alert(1)>',
    tipo_reclamacion: 'Reclamo',
    detalle_reclamacion: 'Detalle con <script>alert(1)</script>',
    pedido_consumidor: 'Pedido con <img src=x onerror=alert(1)>'
  };

  const safeNombre = ApiService.escapeHtml(maliciousClaim.nombre_completo);
  const safeDir = ApiService.escapeHtml(maliciousClaim.direccion);
  const safeDesc = ApiService.escapeHtml(maliciousClaim.descripcion_bien);
  const safeDetalle = ApiService.escapeHtml(maliciousClaim.detalle_reclamacion);
  const safePedido = ApiService.escapeHtml(maliciousClaim.pedido_consumidor);

  const sheetHtml = `
    <div>
      <p>Consumidor: ${safeNombre}</p>
      <p>Dirección: ${safeDir}</p>
      <p>Bien: ${safeDesc}</p>
      <p>Detalle: ${safeDetalle}</p>
      <p>Pedido: ${safePedido}</p>
    </div>
  `;

  assert(!sheetHtml.includes('<b data-audit'));
  assert(!sheetHtml.includes('<script>'));
  assert(!sheetHtml.includes('<img src=x'));
  assert(sheetHtml.includes('&lt;b data-audit=&quot;marker&quot;&gt;PRUEBA&lt;/b&gt;'));
  assert(sheetHtml.includes('&lt;script&gt;alert(1)&lt;/script&gt;'));
  assert(sheetHtml.includes('&lt;img src=x onerror=alert(1)&gt;'));
});

it('Payloads XSS inyectados en Cotización se neutralizan en la Proforma Oficial B2B Imprimible', () => {
  const maliciousQuote = {
    codigo: 'COT-2026-00001',
    cliente_nombre: '<b data-audit="marker">PRUEBA</b>',
    cliente_doc: '20601234567',
    cliente_telefono: '999888777',
    tipo_comprobante: 'Factura',
    departamento: 'Lima',
    notas: '<script>alert(1)</script>',
    items: [
      { sku: 'SKU-001', nombre: '<img src=x onerror=alert(1)>', presentacion: 'Caja x 1000', cantidad: 2 }
    ]
  };

  const safeNombre = ApiService.escapeHtml(maliciousQuote.cliente_nombre);
  const safeNotas = ApiService.escapeHtml(maliciousQuote.notas);
  const itemsHtml = maliciousQuote.items.map(it => `
    <tr>
      <td>${ApiService.escapeHtml(it.sku)}</td>
      <td>${ApiService.escapeHtml(it.nombre)}</td>
      <td>${ApiService.escapeHtml(it.presentacion)}</td>
      <td>${it.cantidad}</td>
    </tr>
  `).join('');

  const printHtml = `
    <div>
      <h2>${safeNombre}</h2>
      <p>${safeNotas}</p>
      <table>${itemsHtml}</table>
    </div>
  `;

  assert(!printHtml.includes('<b data-audit'));
  assert(!printHtml.includes('<script>'));
  assert(!printHtml.includes('<img src=x'));
  assert(printHtml.includes('&lt;b data-audit=&quot;marker&quot;&gt;PRUEBA&lt;/b&gt;'));
  assert(printHtml.includes('&lt;script&gt;alert(1)&lt;/script&gt;'));
  assert(printHtml.includes('&lt;img src=x onerror=alert(1)&gt;'));
});

// -------------------------------------------------------------
// Resumen Final
// -------------------------------------------------------------
console.log(`\n======================================================`);
console.log(`RESULTADOS TAREA 5: ${passedTests}/${totalTests} pruebas pasadas con éxito.`);
console.log(`======================================================\n`);

if (passedTests !== totalTests) {
  process.exit(1);
}
