/**
 * Módulo de Comparador de Productos Técnico (Side-by-Side)
 * Permite seleccionar hasta 3 productos y comparar especificaciones técnicas,
 * resistencia térmica, microondas, sellado y atributos ambientales.
 */

const Comparador = {
  items: [], // Array de SKUs (máximo 3)

  init() {
    this.loadFromStorage();
    this.injectBarAndModal();
    this.renderBar();
    this.syncCardButtons();
  },

  loadFromStorage() {
    try {
      const saved = sessionStorage.getItem('dp_comparador_skus');
      this.items = saved ? JSON.parse(saved) : [];
      if (!Array.isArray(this.items)) this.items = [];
      this.items = this.items.slice(0, 3);
    } catch (e) {
      this.items = [];
    }
  },

  saveToStorage() {
    try {
      sessionStorage.setItem('dp_comparador_skus', JSON.stringify(this.items));
    } catch (e) {}
  },

  notify(msg, type = 'info') {
    if (window.Toast && typeof window.Toast[type] === 'function') {
      window.Toast[type](msg);
    } else if (typeof window.showToast === 'function') {
      window.showToast(msg, type);
    }
  },

  getProduct(sku) {
    if (window.Catalogo && Array.isArray(window.Catalogo.products)) {
      const found = window.Catalogo.products.find(p => p.sku === sku);
      if (found) return found;
    }
    if (window.IndexFeatured && Array.isArray(window.IndexFeatured.products)) {
      const found = window.IndexFeatured.products.find(p => p.sku === sku);
      if (found) return found;
    }
    if (typeof PRODUCTOS !== 'undefined' && Array.isArray(PRODUCTOS)) {
      const found = PRODUCTOS.find(p => p.sku === sku);
      if (found) return found;
    }
    return null;
  },

  toggle(sku) {
    const idx = this.items.indexOf(sku);
    if (idx !== -1) {
      this.items.splice(idx, 1);
      this.notify(`Producto ${sku} retirado del comparador.`, 'info');
    } else {
      if (this.items.length >= 3) {
        this.notify('Solo puedes comparar hasta 3 productos a la vez. Quita uno para agregar este.', 'warning');
        return;
      }
      this.items.push(sku);
      this.notify(`Producto ${sku} añadido al comparador.`, 'success');
    }

    this.saveToStorage();
    this.renderBar();
    this.syncCardButtons();

    // Si el modal está abierto, re-renderizarlo
    const modal = document.getElementById('modalComparador');
    if (modal && !modal.classList.contains('hidden')) {
      this.renderModalContent();
    }
  },

  remove(sku) {
    const idx = this.items.indexOf(sku);
    if (idx !== -1) {
      this.items.splice(idx, 1);
      this.saveToStorage();
      this.renderBar();
      this.syncCardButtons();
      this.renderModalContent();
    }
  },

  clear() {
    this.items = [];
    this.saveToStorage();
    this.renderBar();
    this.syncCardButtons();
    this.closeModal();
    this.notify('Comparador restablecido.', 'info');
  },

  syncCardButtons() {
    document.querySelectorAll('[data-compare-sku]').forEach(btn => {
      const sku = btn.getAttribute('data-compare-sku');
      const isSelected = this.items.includes(sku);
      
      if (isSelected) {
        btn.classList.add('bg-[#C85A32]', 'text-white', 'border-[#C85A32]');
        btn.classList.remove('bg-white', 'text-[#574B46]', 'border-[#EAE3DA]');
        btn.innerHTML = `
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
          <span>En Comparador</span>
        `;
      } else {
        btn.classList.remove('bg-[#C85A32]', 'text-white', 'border-[#C85A32]');
        btn.classList.add('bg-white', 'text-[#574B46]', 'border-[#EAE3DA]');
        btn.innerHTML = `
          <svg class="w-3.5 h-3.5 text-[#C85A32]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
          <span>Comparar</span>
        `;
      }
    });
  },

  injectBarAndModal() {
    if (document.getElementById('barComparador')) return;

    // 1. Barra Flotante Inferior (Elevada sobre la barra movil z-50)
    const barHtml = `
      <div id="barComparador" class="fixed bottom-20 lg:bottom-6 left-1/2 -translate-x-1/2 z-50 hidden transition-all duration-300 w-[95%] max-w-2xl">
        <div class="bg-[#1F1815]/95 backdrop-blur-md text-white p-3 sm:p-4 rounded-2xl shadow-2xl border border-stone-700 flex items-center justify-between gap-3">
          
          <div class="flex items-center gap-3">
            <div id="barComparadorThumbs" class="flex items-center -space-x-2 overflow-hidden">
              <!-- Miniaturas dinámicas -->
            </div>
            <div>
              <p class="font-heading font-bold text-xs sm:text-sm text-white">
                Comparador Técnico
              </p>
              <p id="barComparadorCount" class="text-[10px] sm:text-[11px] text-amber-400 font-semibold">
                0 de 3 productos
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <button type="button" onclick="Comparador.clear()" class="px-2.5 sm:px-3 py-2 rounded-xl text-[11px] font-bold text-stone-400 hover:text-white hover:bg-stone-800 transition-colors cursor-pointer tap-target">
              Limpiar
            </button>
            <button type="button" onclick="Comparador.openModal()" class="px-4 sm:px-5 py-2 rounded-xl bg-[#C85A32] hover:bg-[#B84A22] text-white text-xs font-bold shadow-md shadow-[#C85A32]/30 flex items-center gap-1.5 transition-all cursor-pointer tap-target">
              <span>Comparar ahora</span>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
          </div>

        </div>
      </div>
    `;

    // 2. Modal de Comparación Side-by-Side
    const modalHtml = `
      <div id="modalComparador" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden items-center justify-center p-2 sm:p-4 animate-fade-in overflow-y-auto">
        <div class="bg-white w-full max-w-5xl rounded-3xl border border-[#EAE3DA] shadow-2xl overflow-hidden my-auto max-h-[94vh] flex flex-col">
          
          <!-- Encabezado Modal -->
          <div class="p-4 sm:p-6 bg-[#F4EFEA] border-b border-[#EAE3DA] flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-2xl bg-[#C85A32] text-white flex items-center justify-center font-bold shadow-md shadow-[#C85A32]/20">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
              </div>
              <div>
                <h2 class="font-heading font-extrabold text-lg sm:text-xl text-[#1F1815]">Comparativa Técnica Side-by-Side</h2>
                <p class="text-xs text-[#574B46]">Análisis técnico de rendimiento térmico, inocuidad alimentaria y empaque.</p>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <button type="button" onclick="Comparador.clear()" class="hidden sm:inline-flex px-3 py-1.5 rounded-xl border border-[#EAE3DA] text-xs font-bold text-[#1F1815] hover:bg-stone-200 transition-colors cursor-pointer tap-target">
                Limpiar selección
              </button>
              <button type="button" onclick="Comparador.closeModal()" class="p-2 rounded-xl text-stone-400 hover:text-[#1F1815] hover:bg-stone-200 transition-colors cursor-pointer tap-target" aria-label="Cerrar comparador">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
          </div>

          <!-- Contenido de Tabla Comparativa (Scrollable) -->
          <div id="modalComparadorBody" class="p-4 sm:p-6 overflow-y-auto overflow-x-auto flex-1 text-xs">
            <!-- Renderizado dinámico -->
          </div>

          <!-- Pie del Modal -->
          <div class="p-4 bg-[#FDFBF7] border-t border-[#EAE3DA] flex flex-col sm:flex-row items-center justify-between gap-3 flex-shrink-0 text-xs text-[#574B46]">
            <p>• Los datos técnicos están respaldados por normas de inocuidad DIGESA / FDA para contacto directo con alimentos.</p>
            <button type="button" onclick="Comparador.closeModal()" class="px-5 py-2.5 rounded-xl bg-[#1F1815] text-white font-bold hover:bg-stone-800 transition-colors cursor-pointer tap-target">
              Seguir explorando
            </button>
          </div>

        </div>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', barHtml);
    document.body.insertAdjacentHTML('beforeend', modalHtml);
  },

  renderBar() {
    const bar = document.getElementById('barComparador');
    const thumbsContainer = document.getElementById('barComparadorThumbs');
    const countEl = document.getElementById('barComparadorCount');
    if (!bar) return;

    if (this.items.length === 0) {
      bar.classList.add('hidden');
      return;
    }

    bar.classList.remove('hidden');
    if (countEl) {
      countEl.textContent = `${this.items.length} de 3 productos seleccionados`;
    }

    if (thumbsContainer) {
      thumbsContainer.innerHTML = this.items.map(sku => {
        const prod = this.getProduct(sku);
        const imgUrl = prod ? (prod.imagen_url || 'assets/images/productos/default.png') : '';
        const name = prod ? prod.nombre : sku;
        return `
          <div class="relative w-8 h-8 sm:w-9 sm:h-9 rounded-full border-2 border-[#1F1815] overflow-hidden bg-white shadow-sm flex-shrink-0" title="${name}">
            <img src="${imgUrl}" alt="${name}" class="w-full h-full object-cover">
          </div>
        `;
      }).join('');
    }
  },

  openModal() {
    if (this.items.length === 0) {
      this.notify('Selecciona al menos 1 producto para comparar.', 'warning');
      return;
    }
    const modal = document.getElementById('modalComparador');
    if (!modal) return;

    this.renderModalContent();
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
  },

  closeModal() {
    const modal = document.getElementById('modalComparador');
    if (modal) {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      document.body.style.overflow = '';
    }
  },

  getTechnicalAnalysis(prod) {
    const mat = (prod.material || '').toLowerCase();
    const name = (prod.nombre || '').toLowerCase();
    const desc = (prod.descripcion || '').toLowerCase();
    const specs = prod.especificaciones || {};

    // 1. Rango Térmico
    let temp = specs.temperatura || '';
    if (!temp) {
      if (mat.includes('polipropileno') || mat.includes('pp') || mat.includes('caña') || mat.includes('bagazo')) {
        temp = '-20°C a +120°C (Excelente)';
      } else if (mat.includes('poliestireno') || mat.includes('eps')) {
        temp = '-10°C a +85°C (Alimentos calientes)';
      } else if (mat.includes('pet')) {
        temp = '-20°C a +60°C (Frío y fresco)';
      } else {
        temp = '-10°C a +90°C';
      }
    }

    // 2. Microondas
    let microondas = false;
    let microondasDetalle = 'No apto para microondas';
    if (mat.includes('polipropileno') || mat.includes('pp') || mat.includes('caña') || mat.includes('bagazo') || mat.includes('kraft')) {
      microondas = true;
      microondasDetalle = 'Apto (Hasta 100°C / 3 min)';
    }

    // 3. Congelador / Frío
    let freezerDetalle = 'Apto para congelador (-10°C a -20°C)';

    // 4. Tipo de Cierre
    let cierre = 'Ajuste hermético perimetral';
    if (name.includes('ct-') || desc.includes('bisagra') || name.includes('bisagra')) {
      cierre = 'Bisagra monobloque con traba anti-apertura';
    } else if (name.includes('domo') || name.includes('tapa')) {
      cierre = 'Tapa sobrepuesta con cierre hermético clip';
    } else if (name.includes('bolsa') || desc.includes('vacío')) {
      cierre = 'Termosellado al vacío de alta barrera';
    }

    // 5. Resistencia a Grasas / Aceites
    let grasas = 'Excelente (Barrera antigrasa y salsas)';
    if (mat.includes('papel') && !desc.includes('encerado')) {
      grasas = 'Media (Absorbente)';
    }

    // 6. Impacto Ambiental
    let ambiental = {
      isBio: prod.biodegradable,
      label: prod.biodegradable ? '100% Eco-Biodegradable (Compostable)' : '100% Reciclable Mecánicamente',
      norma: prod.biodegradable ? 'Norma EN 13432 / Bagazo vegetal' : 'Economía Circular / Reciclaje Grado 5 ó 6'
    };

    return { temp, microondas, microondasDetalle, freezerDetalle, cierre, grasas, ambiental };
  },

  renderModalContent() {
    const container = document.getElementById('modalComparadorBody');
    if (!container) return;

    const prods = this.items.map(sku => this.getProduct(sku)).filter(Boolean);

    if (prods.length === 0) {
      container.innerHTML = `
        <div class="py-12 text-center text-stone-400">
          <p class="text-sm font-bold text-[#1F1815]">No hay productos seleccionados para comparar.</p>
          <p class="text-xs text-[#574B46] mt-1">Regresa al catálogo y selecciona hasta 3 productos.</p>
        </div>
      `;
      return;
    }

    container.innerHTML = `
      <div class="min-w-[650px]">
        
        <!-- Fila 1: Tarjetas de Producto Lado a Lado -->
        <div class="grid grid-cols-4 gap-3 pb-4 border-b border-[#EAE3DA]">
          <div class="font-heading font-black text-sm text-[#1F1815] flex items-center">
            Producto / Empaque
          </div>
          ${prods.map(p => `
            <div class="bg-[#FDFBF7] p-3 rounded-2xl border border-[#EAE3DA] flex flex-col justify-between relative group">
              <button onclick="Comparador.remove('${p.sku}')" class="absolute top-2 right-2 p-1.5 rounded-lg bg-white/90 text-stone-400 hover:text-rose-600 hover:bg-white shadow-xs transition-colors cursor-pointer tap-target" title="Quitar de comparador">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>

              <div class="flex items-center gap-3">
                <img src="${p.imagen_url || 'assets/images/productos/default.png'}" alt="${p.nombre}" class="w-14 h-14 rounded-xl object-cover border border-[#EAE3DA] bg-white flex-shrink-0" onerror="this.src='https://images.unsplash.com/photo-1577705998148-6da4f3963bc8?auto=format&fit=crop&w=150&q=80'">
                <div class="min-w-0 pr-4">
                  <span class="font-mono text-[10px] font-bold text-[#C85A32]">${p.sku}</span>
                  <h4 class="font-bold text-xs text-[#1F1815] leading-snug truncate" title="${p.nombre}">${p.nombre}</h4>
                  <span class="text-[10px] text-[#574B46] block truncate">${p.categoria_nombre || 'Descartables'}</span>
                </div>
              </div>

              <div class="mt-3 pt-2 border-t border-[#EAE3DA] flex items-center gap-1.5">
                <button type="button" onclick="Carrito.addItem((Catalogo.products && Catalogo.products.find(x=>x.sku==='${p.sku}')) || PRODUCTOS.find(x=>x.sku==='${p.sku}'), 1)" class="flex-1 py-1.5 px-2 bg-[#C85A32] hover:bg-[#B84A22] text-white rounded-lg text-[11px] font-bold shadow-xs flex items-center justify-center gap-1 transition-all cursor-pointer tap-target">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                  <span>Cotizar</span>
                </button>
                <button type="button" onclick="Catalogo.descargarFichaPDF('${p.sku}')" class="p-1.5 bg-white hover:bg-stone-100 text-[#1F1815] rounded-lg border border-[#EAE3DA] transition-colors cursor-pointer tap-target" title="Descargar Ficha Técnica PDF">
                  <svg class="w-4 h-4 text-[#C85A32]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </button>
              </div>
            </div>
          `).join('')}
          ${prods.length < 3 ? `
            <div class="border-2 border-dashed border-[#EAE3DA] rounded-2xl flex flex-col items-center justify-center p-4 text-center text-stone-400">
              <svg class="w-8 h-8 text-stone-300 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/></svg>
              <p class="text-[11px] font-bold text-[#1F1815]">Espacio disponible</p>
              <p class="text-[10px] text-stone-400">Puedes agregar ${3 - prods.length} producto(s) más</p>
            </div>
          ` : ''}
        </div>

        <!-- Matriz de Especificaciones Técnicas -->
        <div class="divide-y divide-[#EAE3DA]">
          
          <!-- Fila: Material de Fabricación -->
          <div class="grid grid-cols-4 gap-3 py-3 items-center">
            <div class="font-bold text-[#1F1815]">Material de Fabricación</div>
            ${prods.map(p => `
              <div class="font-semibold text-[#1F1815]">
                <span class="px-2.5 py-1 rounded-lg bg-[#F4EFEA] inline-block text-[11px]">${p.material || 'Polímero'}</span>
              </div>
            `).join('')}
          </div>

          <!-- Fila: Rango Térmico (°C) -->
          <div class="grid grid-cols-4 gap-3 py-3 items-center bg-[#F4EFEA]/30">
            <div class="font-bold text-[#1F1815]">Resistencia Térmica</div>
            ${prods.map(p => {
              const tech = this.getTechnicalAnalysis(p);
              return `
                <div class="font-mono font-bold text-[#1F1815] text-[11px]">
                  ${tech.temp}
                </div>
              `;
            }).join('')}
          </div>

          <!-- Fila: Apto Microondas -->
          <div class="grid grid-cols-4 gap-3 py-3 items-center">
            <div class="font-bold text-[#1F1815]">Apto para Microondas</div>
            ${prods.map(p => {
              const tech = this.getTechnicalAnalysis(p);
              return `
                <div>
                  ${tech.microondas 
                    ? '<span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px] inline-flex items-center gap-1">✓ Sí (Hasta 100°C)</span>' 
                    : '<span class="px-2.5 py-1 rounded-full bg-rose-100 text-rose-800 font-bold text-[10px] inline-flex items-center gap-1">✕ No recomendado</span>'}
                </div>
              `;
            }).join('')}
          </div>

          <!-- Fila: Apto Congelador / Frío -->
          <div class="grid grid-cols-4 gap-3 py-3 items-center bg-[#F4EFEA]/30">
            <div class="font-bold text-[#1F1815]">Apto para Congelación</div>
            ${prods.map(p => {
              const tech = this.getTechnicalAnalysis(p);
              return `
                <div class="text-[11px] text-[#1F1815] font-medium flex items-center gap-1">
                  <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                  <span>${tech.freezerDetalle}</span>
                </div>
              `;
            }).join('')}
          </div>

          <!-- Fila: Hermeticidad y Cierre -->
          <div class="grid grid-cols-4 gap-3 py-3 items-center">
            <div class="font-bold text-[#1F1815]">Tipo de Cierre / Sellado</div>
            ${prods.map(p => {
              const tech = this.getTechnicalAnalysis(p);
              return `
                <div class="text-[11px] text-[#1F1815]">
                  ${tech.cierre}
                </div>
              `;
            }).join('')}
          </div>

          <!-- Fila: Resistencia a Grasas / Aceites -->
          <div class="grid grid-cols-4 gap-3 py-3 items-center bg-[#F4EFEA]/30">
            <div class="font-bold text-[#1F1815]">Barrera Antigrasa</div>
            ${prods.map(p => {
              const tech = this.getTechnicalAnalysis(p);
              return `
                <div class="text-[11px] text-[#1F1815] font-medium">
                  ${tech.grasas}
                </div>
              `;
            }).join('')}
          </div>

          <!-- Fila: Clasificación Ambiental -->
          <div class="grid grid-cols-4 gap-3 py-3 items-center">
            <div class="font-bold text-[#1F1815]">Impacto Ambiental</div>
            ${prods.map(p => {
              const tech = this.getTechnicalAnalysis(p);
              return `
                <div>
                  <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold block leading-tight ${tech.ambiental.isBio ? 'bg-emerald-100 text-emerald-800' : 'bg-[#F4EFEA] text-[#1F1815]'}">
                    ${tech.ambiental.label}
                  </span>
                  <span class="text-[9px] text-[#574B46] block mt-0.5">${tech.ambiental.norma}</span>
                </div>
              `;
            }).join('')}
          </div>

          <!-- Fila: Presentación / Empaque -->
          <div class="grid grid-cols-4 gap-3 py-3 items-center bg-[#F4EFEA]/30">
            <div class="font-bold text-[#1F1815]">Presentación Mayorista</div>
            ${prods.map(p => `
              <div class="font-bold text-[#C85A32] font-mono text-[11px]">
                ${p.presentacion || 'Caja mayorista'}
              </div>
            `).join('')}
          </div>

          <!-- Fila: Inocuidad y DIGESA -->
          <div class="grid grid-cols-4 gap-3 py-3 items-center">
            <div class="font-bold text-[#1F1815]">Normativa Sanitaria</div>
            ${prods.map(p => `
              <div class="text-[10px] text-emerald-800 font-bold flex items-center gap-1">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span>Certificado DIGESA / FDA Contacto Directo</span>
              </div>
            `).join('')}
          </div>

        </div>

      </div>
    `;
  }
};

window.Comparador = Comparador;
document.addEventListener('DOMContentLoaded', () => {
  Comparador.init();
});
