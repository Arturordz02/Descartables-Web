/**
 * Módulo de Catálogo Interactivo y Ficha Técnica
 * Filtros en tiempo real, búsqueda y modales
 */

const Catalogo = {
  currentFilters: {
    categoria: 'todos',
    material: 'todos',
    biodegradable: false,
    q: '',
    sort: 'destacados'
  },
  products: [],

  async init() {
    this.readUrlParams();
    this.injectQuickViewModal();
    this.attachFilterEvents();
    await this.loadAndRender();
  },

  readUrlParams() {
    const params = new URLSearchParams(window.location.search);
    const cat = params.get('cat');
    const search = params.get('q');
    const bio = params.get('bio');

    if (cat) this.currentFilters.categoria = cat;
    if (search) this.currentFilters.q = search;
    if (bio === '1' || bio === 'true') this.currentFilters.biodegradable = true;

    // Sincronizar inputs si existen
    const catSelect = document.getElementById('filterCategoria');
    const searchInput = document.getElementById('catalogoSearchInput');
    const bioCheckbox = document.getElementById('filterBio');

    if (catSelect && cat) catSelect.value = cat;
    if (searchInput && search) searchInput.value = search;
    if (bioCheckbox && this.currentFilters.biodegradable) bioCheckbox.checked = true;
  },

  async loadAndRender() {
    const grid = document.getElementById('catalogoGrid');
    if (!grid) return;

    grid.innerHTML = `
      <div class="col-span-full py-16 text-center text-[#574B46]">
        <div class="inline-block w-8 h-8 border-4 border-[#C85A32] border-t-transparent rounded-full animate-spin mb-3"></div>
        <p class="text-sm font-medium">Cargando catálogo especializado...</p>
      </div>
    `;

    this.products = await ApiService.getProducts(this.currentFilters);

    // Aplicar ordenamiento
    if (this.currentFilters.sort === 'nombre_asc') {
      this.products.sort((a, b) => a.nombre.localeCompare(b.nombre));
    } else if (this.currentFilters.sort === 'nombre_desc') {
      this.products.sort((a, b) => b.nombre.localeCompare(a.nombre));
    }

    this.renderProducts();
  },

  renderProducts() {
    const grid = document.getElementById('catalogoGrid');
    const countBadge = document.getElementById('catalogoTotalCount');
    if (!grid) return;

    if (countBadge) {
      countBadge.textContent = `${this.products.length} producto${this.products.length !== 1 ? 's' : ''}`;
    }

    if (this.products.length === 0) {
      grid.innerHTML = `
        <div class="col-span-full py-16 text-center bg-white rounded-3xl border border-[#EAE3DA] p-8">
          <div class="w-16 h-16 rounded-full bg-[#F4EFEA] text-[#574B46] flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 class="font-bold text-lg text-[#1F1815] mb-1">No se encontraron productos</h3>
          <p class="text-xs text-[#574B46] max-w-sm mx-auto mb-4">Intenta ajustar los filtros de categoría, material o escribe otro término de búsqueda.</p>
          <button onclick="Catalogo.resetFilters()" class="px-4 py-2 bg-[#C85A32] hover:bg-[#B84A22] text-white rounded-xl text-xs font-semibold">
            Restablecer filtros
          </button>
        </div>
      `;
      return;
    }

    grid.innerHTML = this.products.map(prod => `
      <div class="product-card bg-white rounded-2xl border border-[#EAE3DA] overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col group">
        
        <!-- Contenedor Imagen & Badges -->
        <div class="relative h-48 sm:h-52 bg-[#F4EFEA] overflow-hidden">
          <img src="${prod.imagen_url}" alt="${prod.nombre}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
          
          <div class="absolute top-3 left-3 flex flex-col gap-1.5 items-start">
            <span class="px-2.5 py-1 rounded-lg bg-black/70 backdrop-blur-md text-white font-mono text-[10px] font-bold tracking-wider">
              ${prod.sku}
            </span>
            ${prod.biodegradable ? `
              <span class="px-2.5 py-0.5 rounded-lg bg-emerald-600/90 backdrop-blur-md text-white text-[10px] font-bold flex items-center gap-1 shadow-sm">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 100-2 1 1 0 000 2zm5-1.757l4.9-4.9a2 2 0 000-2.828L13.485 5.1a2 2 0 00-2.828 0L10 5.757v8.486zM16 18H9.071l6-6H16a2 2 0 012 2v2a2 2 0 01-2 2z" clip-rule="evenodd"/></svg>
                100% Bio
              </span>
            ` : ''}
          </div>

          <!-- Botones de Acción Rápida (Ficha PDF + QuickView) -->
          <div class="absolute bottom-3 right-3 flex items-center gap-1.5">
            <button type="button" onclick="Catalogo.descargarFichaPDF('${prod.sku}')" class="p-2 rounded-xl bg-white/90 backdrop-blur text-[#C85A32] hover:bg-white shadow-md transition-colors cursor-pointer tap-target" title="Descargar Ficha Técnica PDF">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </button>
            <button type="button" onclick="Catalogo.openQuickView('${prod.sku}')" class="p-2 rounded-xl bg-white/90 backdrop-blur text-[#1F1815] hover:bg-white shadow-md transition-colors cursor-pointer tap-target" title="Ver especificaciones técnicas">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Contenido -->
        <div class="p-5 flex-1 flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between text-[11px] text-[#574B46] mb-1">
              <span class="font-semibold text-[#C85A32]">${prod.categoria_nombre || 'Descartables'}</span>
              <span class="truncate max-w-[120px] bg-[#F4EFEA] px-2 py-0.5 rounded text-[10px]">${prod.material.split('/')[0]}</span>
            </div>
            
            <h3 class="font-bold text-sm text-[#1F1815] mb-1.5 leading-snug line-clamp-2" title="${prod.nombre}">
              ${prod.nombre}
            </h3>
            
            <p class="text-xs text-[#574B46] line-clamp-2 mb-3">
              ${prod.descripcion}
            </p>
          </div>

          <div>
            <div class="pt-3 border-t border-[#EAE3DA] flex items-center justify-between mb-3 text-xs">
              <span class="text-[#574B46]">Presentación:</span>
              <span class="font-bold text-[#1F1815] bg-[#FDFBF7] px-2 py-0.5 rounded border border-[#EAE3DA]">${prod.presentacion}</span>
            </div>

            <!-- Selector de Cantidad y Botón Cotizar -->
            <div class="flex items-center gap-2 mb-2">
              <div class="flex items-center border border-[#EAE3DA] rounded-xl overflow-hidden bg-[#FDFBF7] w-24">
                <button type="button" onclick="Catalogo.stepQty(this, -1)" class="w-7 h-9 text-xs font-bold text-[#574B46] hover:bg-stone-200 transition-colors cursor-pointer">-</button>
                <input type="number" min="1" value="1" class="input-qty-selector w-10 h-9 text-center text-xs font-semibold text-[#1F1815] bg-transparent focus:outline-none">
                <button type="button" onclick="Catalogo.stepQty(this, 1)" class="w-7 h-9 text-xs font-bold text-[#574B46] hover:bg-stone-200 transition-colors cursor-pointer">+</button>
              </div>

              <button type="button" data-sku="${prod.sku}" class="btn-add-quote flex-1 py-2.5 px-3 bg-[#C85A32] hover:bg-[#B84A22] text-white rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 shadow-sm hover:shadow transition-all cursor-pointer tap-target">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Cotizar</span>
              </button>
            </div>

            <!-- Botón Comparador Técnico -->
            <button type="button" data-compare-sku="${prod.sku}" onclick="Comparador.toggle('${prod.sku}')" class="w-full py-2 px-3 rounded-xl border border-[#EAE3DA] bg-white text-[#574B46] hover:bg-[#F4EFEA] text-xs font-semibold flex items-center justify-center gap-1.5 transition-all cursor-pointer tap-target">
              <svg class="w-3.5 h-3.5 text-[#C85A32]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
              <span>Comparar</span>
            </button>
          </div>

        </div>

      </div>
    `).join('');

    if (window.Comparador) {
      window.Comparador.syncCardButtons();
    }
  },

  stepQty(btn, step) {
    const parent = btn.parentElement;
    const input = parent.querySelector('.input-qty-selector');
    if (input) {
      let val = parseInt(input.value, 10) || 1;
      val = Math.max(1, val + step);
      input.value = val;
    }
  },

  resetFilters() {
    this.currentFilters = {
      categoria: 'todos',
      material: 'todos',
      biodegradable: false,
      q: '',
      sort: 'destacados'
    };

    const catSelect = document.getElementById('filterCategoria');
    const matSelect = document.getElementById('filterMaterial');
    const searchInput = document.getElementById('catalogoSearchInput');
    const bioCheckbox = document.getElementById('filterBio');

    if (catSelect) catSelect.value = 'todos';
    if (matSelect) matSelect.value = 'todos';
    if (searchInput) searchInput.value = '';
    if (bioCheckbox) bioCheckbox.checked = false;

    this.loadAndRender();
  },

  attachFilterEvents() {
    const catSelect = document.getElementById('filterCategoria');
    const matSelect = document.getElementById('filterMaterial');
    const searchInput = document.getElementById('catalogoSearchInput');
    const bioCheckbox = document.getElementById('filterBio');
    const sortSelect = document.getElementById('sortSelect');

    if (catSelect) {
      catSelect.addEventListener('change', (e) => {
        this.currentFilters.categoria = e.target.value;
        this.loadAndRender();
      });
    }

    if (matSelect) {
      matSelect.addEventListener('change', (e) => {
        this.currentFilters.material = e.target.value;
        this.loadAndRender();
      });
    }

    if (bioCheckbox) {
      bioCheckbox.addEventListener('change', (e) => {
        this.currentFilters.biodegradable = e.target.checked;
        this.loadAndRender();
      });
    }

    if (sortSelect) {
      sortSelect.addEventListener('change', (e) => {
        this.currentFilters.sort = e.target.value;
        this.loadAndRender();
      });
    }

    if (searchInput) {
      let debounceTimer;
      searchInput.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
          this.currentFilters.q = e.target.value.trim();
          this.loadAndRender();
        }, 300);
      });
    }

    // Botones de categoría rápida
    document.querySelectorAll('.btn-category-pill').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const slug = btn.dataset.slug;
        this.currentFilters.categoria = slug;
        if (catSelect) catSelect.value = slug;
        document.querySelectorAll('.btn-category-pill').forEach(b => b.classList.remove('bg-[#C85A32]', 'text-white'));
        btn.classList.add('bg-[#C85A32]', 'text-white');
        this.loadAndRender();
      });
    });
  },

  toggleMobileFilters() {
    const sidebar = document.getElementById('catalogoSidebar');
    const badge = document.getElementById('activeFilterBadge');
    if (!sidebar) return;

    const isHidden = sidebar.classList.contains('hidden');
    if (isHidden) {
      sidebar.classList.remove('hidden');
      if (badge) badge.textContent = 'Ocultar';
    } else {
      sidebar.classList.add('hidden');
      if (badge) badge.textContent = 'Mostrar';
    }
  },

  injectQuickViewModal() {
    if (document.getElementById('modalQuickView')) return;

    const modalHTML = `
      <div id="modalQuickView" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-stone-900/60 backdrop-blur-sm hidden animate-fade-in">
        <div class="bg-white rounded-2xl sm:rounded-3xl max-w-2xl w-full max-h-[92vh] overflow-y-auto shadow-2xl border border-[#EAE3DA] overflow-hidden relative">
          
          <button onclick="Catalogo.closeQuickView()" class="absolute top-3 right-3 sm:top-4 sm:right-4 z-10 w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-white/90 backdrop-blur text-stone-700 hover:bg-white flex items-center justify-center shadow-md tap-target" aria-label="Cerrar modal">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <div id="quickViewContent" class="p-4 sm:p-6 md:p-8 space-y-4 sm:space-y-6">
            <!-- Dinámico -->
          </div>

        </div>
      </div>
    `;

    const div = document.createElement('div');
    div.innerHTML = modalHTML;
    document.body.appendChild(div.firstElementChild);
  },

  openQuickView(sku) {
    const prod = (this.products && this.products.find(p => p.sku === sku)) || 
                 (typeof PRODUCTOS !== 'undefined' ? PRODUCTOS.find(p => p.sku === sku) : null);
    if (!prod) return;

    const modal = document.getElementById('modalQuickView');
    const container = document.getElementById('quickViewContent');
    if (!modal || !container) return;

    const specs = prod.especificaciones || {};

    container.innerHTML = `
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 items-center">
        <div class="rounded-2xl overflow-hidden bg-[#F4EFEA] aspect-video sm:aspect-square max-h-64 md:max-h-none">
          <img src="${prod.imagen_url}" alt="${prod.nombre}" class="w-full h-full object-cover">
        </div>
        <div class="space-y-2">
          <div class="flex items-center gap-2">
            <span class="inline-block px-2.5 py-1 rounded-lg bg-[#C85A32]/10 text-[#C85A32] font-mono text-[11px] sm:text-xs font-bold">
              ${prod.sku}
            </span>
            ${prod.biodegradable ? '<span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 text-[10px] font-bold">100% Bio</span>' : ''}
          </div>
          <h3 class="font-bold text-lg sm:text-xl text-[#1F1815] leading-tight">${prod.nombre}</h3>
          <p class="text-xs text-[#574B46]">${prod.descripcion}</p>
          
          <div class="space-y-2 bg-[#FDFBF7] p-3 sm:p-4 rounded-xl sm:rounded-2xl border border-[#EAE3DA] text-xs">
            <div class="flex justify-between">
              <span class="text-[#574B46]">Categoría:</span>
              <span class="font-semibold text-[#1F1815]">${prod.categoria_nombre}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-[#574B46]">Presentación:</span>
              <span class="font-semibold text-[#1F1815]">${prod.presentacion}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-[#574B46]">Material:</span>
              <span class="font-semibold text-[#1F1815]">${prod.material}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-[#574B46]">Impacto Ecológico:</span>
              <span class="font-semibold ${prod.biodegradable ? 'text-emerald-700' : 'text-stone-600'}">
                ${prod.biodegradable ? 'Biodegradable / Compostable' : 'Reciclable'}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Ficha Técnica Adicional -->
      <div class="border-t border-[#EAE3DA] pt-3 sm:pt-4">
        <h4 class="font-bold text-xs sm:text-sm text-[#1F1815] mb-2 sm:mb-3">Especificaciones Técnicas de Rendimiento</h4>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 sm:gap-3 text-xs">
          ${Object.entries(specs).map(([k, v]) => `
            <div class="p-2.5 sm:p-3 bg-[#F4EFEA] rounded-xl">
              <p class="text-[9px] sm:text-[10px] text-[#574B46] uppercase font-semibold">${k.replace(/_/g, ' ')}</p>
              <p class="font-bold text-[#1F1815] text-[11px] sm:text-xs mt-0.5 break-words">${v}</p>
            </div>
          `).join('')}
        </div>
      </div>

      <div class="pt-2 flex flex-col sm:flex-row gap-2 sm:gap-3">
        <button onclick="Carrito.addItem((Catalogo.products && Catalogo.products.find(p=>p.sku==='${prod.sku}')) || (typeof PRODUCTOS !== 'undefined' ? PRODUCTOS.find(p=>p.sku==='${prod.sku}') : null), 1); Catalogo.closeQuickView();" class="flex-1 py-3 px-4 bg-[#C85A32] hover:bg-[#B84A22] text-white rounded-xl text-xs font-semibold shadow-md flex items-center justify-center gap-2 tap-target cursor-pointer">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
          <span>Agregar a Cotización</span>
        </button>
        <button onclick="Catalogo.descargarFichaPDF('${prod.sku}')" class="py-3 px-4 bg-warm-sand hover:bg-stone-200 text-espresso rounded-xl text-xs font-bold border border-warm-border flex items-center justify-center gap-2 tap-target cursor-pointer" title="Descargar Ficha Técnica en PDF">
          <svg class="w-4 h-4 text-[#C85A32]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          <span>Ficha Técnica PDF</span>
        </button>
        <button onclick="Catalogo.closeQuickView()" class="px-5 py-3 border border-[#EAE3DA] rounded-xl text-xs font-semibold text-[#574B46] hover:bg-stone-50 tap-target cursor-pointer">
          Cerrar
        </button>
      </div>
    `;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  },

  descargarFichaPDF(sku) {
    const prod = (this.products && this.products.find(p => p.sku === sku)) || 
                 (typeof PRODUCTOS !== 'undefined' ? PRODUCTOS.find(p => p.sku === sku) : null);
    if (!prod) {
      if (window.Toast) Toast.error('No se encontró el producto para generar la ficha.');
      return;
    }

    const printWin = window.open('', '_blank', 'width=900,height=1000');
    if (!printWin) {
      alert('Por favor permita ventanas emergentes para descargar la ficha técnica.');
      return;
    }

    const specs = prod.especificaciones || {
      "temperatura_operativa": "-10°C a +100°C",
      "apto_microondas": (prod.material || '').includes('Polipropileno') || (prod.material || '').includes('Caña') ? 'Sí (Hasta 100°C)' : 'No (Solo alimentos tibios o fríos)',
      "resistencia_grasas": "Alta resistencia a aceites y grasas alimentarias",
      "grado_alimentario": "Certificación DIGESA / FDA Contacto Directo",
      "biodegradabilidad": prod.biodegradable ? "100% Compostable (Norma EN 13432)" : "100% Reciclable Mecánicamente",
      "almacenamiento": "Lugar fresco, seco y bajo sombra"
    };

    printWin.document.write(`
      <!DOCTYPE html>
      <html lang="es">
      <head>
        <meta charset="UTF-8">
        <title>Ficha Técnica - ${prod.sku} - DESCARTABLES PERUANOS S.A.C.</title>
        <style>
          @page { size: A4 portrait; margin: 15mm; }
          body { font-family: 'Calibri', Arial, sans-serif; color: #1F1815; margin: 0; padding: 25px; font-size: 13px; line-height: 1.4; }
          .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #C85A32; padding-bottom: 12px; margin-bottom: 20px; }
          .logo-box { display: flex; align-items: center; gap: 12px; }
          .logo-badge { background: #C85A32; color: #fff; width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 20px; }
          .title-box h1 { margin: 0; font-size: 18px; font-weight: bold; color: #1F1815; }
          .title-box p { margin: 2px 0 0 0; font-size: 11px; color: #C85A32; font-weight: bold; text-transform: uppercase; }
          .doc-badge { border: 2px solid #1F1815; padding: 8px 16px; text-align: center; border-radius: 10px; background: #FDFBF7; }
          .doc-badge .ruc { font-size: 11px; font-weight: bold; display: block; }
          .doc-badge .type { font-size: 13px; font-weight: 900; color: #C85A32; margin-top: 2px; }
          .prod-hero { display: flex; gap: 20px; margin-bottom: 20px; background: #FDFBF7; border: 1px solid #EAE3DA; border-radius: 14px; padding: 18px; }
          .prod-img { width: 170px; height: 170px; object-fit: cover; border-radius: 10px; border: 1px solid #ddd; background: #fff; flex-shrink: 0; }
          .prod-info { flex: 1; }
          .prod-sku { font-family: monospace; font-size: 14px; font-weight: bold; color: #C85A32; }
          .prod-name { font-size: 17px; font-weight: bold; margin: 4px 0 8px 0; color: #1F1815; }
          .prod-desc { font-size: 12px; color: #574B46; margin-bottom: 12px; }
          .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
          .data-table th, .data-table td { padding: 9px 12px; border: 1px solid #EAE3DA; font-size: 12px; }
          .data-table th { background: #F4EFEA; text-align: left; font-weight: bold; color: #1F1815; width: 35%; }
          .data-table td { background: #fff; }
          .section-title { font-size: 13px; font-weight: bold; text-transform: uppercase; color: #C85A32; margin: 18px 0 8px 0; border-bottom: 1px solid #EAE3DA; padding-bottom: 4px; }
          .footer { margin-top: 35px; border-top: 1px solid #EAE3DA; padding-top: 15px; display: flex; justify-content: space-between; align-items: flex-end; font-size: 11px; color: #574B46; }
          .stamp { text-align: center; border: 1px dashed #999; padding: 8px 20px; border-radius: 8px; }
          @media print {
            body { padding: 0; }
          }
        </style>
      </head>
      <body>
        <div class="header">
          <div class="logo-box">
            <div class="logo-badge">DP</div>
            <div class="title-box">
              <h1>DESCARTABLES PERUANOS S.A.C.</h1>
              <p>Ficha Técnica Oficial y Especificaciones de Calidad</p>
            </div>
          </div>
          <div class="doc-badge">
            <span class="ruc">RUC: 20601234567</span>
            <span class="type">FICHA TÉCNICA</span>
          </div>
        </div>

        <div class="prod-hero">
          <img src="${prod.imagen_url || 'assets/images/productos/default.png'}" class="prod-img" onerror="this.src='https://images.unsplash.com/photo-1577705998148-6da4f3963bc8?auto=format&fit=crop&w=300&q=80'">
          <div class="prod-info">
            <span class="prod-sku">SKU: ${prod.sku}</span>
            <h2 class="prod-name">${prod.nombre}</h2>
            <p class="prod-desc">${prod.descripcion || 'Empaque descartable de alto rendimiento para el sector gastronómico, retail y delivery corporativo.'}</p>
            <p style="margin:4px 0;"><strong>Categoría:</strong> ${prod.categoria_nombre || 'Envases Descartables'}</p>
            <p style="margin:4px 0;"><strong>Presentación / Empaque:</strong> ${prod.presentacion || 'Caja mayorista'}</p>
            <p style="margin:4px 0;"><strong>Clasificación Ambiental:</strong> ${prod.biodegradable ? '<span style="color:#059669; font-weight:bold;">🌿 100% Eco-Biodegradable / Compostable</span>' : '100% Reciclable Mecánicamente'}</p>
          </div>
        </div>

        <div class="section-title">Parámetros Técnicos y Físico-Químicos</div>
        <table class="data-table">
          <tr>
            <th>Material de Fabricación</th>
            <td>${prod.material || 'Polímero Grado Alimentario'}</td>
          </tr>
          ${Object.entries(specs).map(([k, v]) => `
            <tr>
              <th>${k.replace(/_/g, ' ').toUpperCase()}</th>
              <td>${v}</td>
            </tr>
          `).join('')}
        </table>

        <div class="section-title">Normativa y Control Sanitario (Perú / Internacional)</div>
        <table class="data-table">
          <tr>
            <th>Inocuidad Alimentaria</th>
            <td>Cumple con los estándares sanitarios para contacto directo con alimentos (Resolución Ministerial N° 461-2007/MINSA y normas FDA).</td>
          </tr>
          <tr>
            <th>Uso y Aplicaciones</th>
            <td>Apto para porcionado, transporte, almacenamiento y despacho en pollerías, restaurantes, servicios de catering, pastelerías e industria alimentaria.</td>
          </tr>
        </table>

        <div class="footer">
          <div>
            <strong>DESCARTABLES PERUANOS S.A.C.</strong><br>
            Av. Alejandro Bertello 732-C, Cercado de Lima, Perú<br>
            ventas@descartablesperuanos.pe | Central: (01) 564-1450 | WhatsApp: +51 994 195 430
          </div>
          <div class="stamp">
            <strong>CONTROL DE CALIDAD Y EMISIÓN</strong><br>
            <span style="font-size:10px; color:#888;">Validado por Dpto. Técnico • Emisión ${new Date().toLocaleDateString('es-PE')}</span>
          </div>
        </div>

        <script>
          window.onload = function() {
            setTimeout(function() { window.print(); }, 400);
          };
        <\/script>
      </body>
      </html>
    `);
    printWin.document.close();
  },

  closeQuickView() {
    const modal = document.getElementById('modalQuickView');
    if (modal) {
      modal.classList.add('hidden');
      document.body.style.overflow = '';
    }
  }
};

window.Catalogo = Catalogo;

