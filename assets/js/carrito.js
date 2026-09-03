/**
 * Carrito Flotante de Cotización e Integración con WhatsApp Business
 * Plataforma Descartables Peruanos (+51 994 195 430)
 */

const Carrito = {
  items: [],
  whatsappNumber: '51994195430',

  init() {
    this.loadFromStorage();
    this.renderDrawerMarkup();
    this.updateBadges();
    this.attachEvents();
  },

  loadFromStorage() {
    try {
      const stored = localStorage.getItem('dp_carrito_cotizacion');
      this.items = stored ? JSON.parse(stored) : [];
    } catch (e) {
      this.items = [];
    }
  },

  saveToStorage() {
    localStorage.setItem('dp_carrito_cotizacion', JSON.stringify(this.items));
    this.updateBadges();
  },

  addItem(product, cantidad = 1) {
    cantidad = parseInt(cantidad, 10) || 1;
    const existingIndex = this.items.findIndex(item => item.id === product.id || item.sku === product.sku);

    if (existingIndex > -1) {
      this.items[existingIndex].cantidad += cantidad;
    } else {
      this.items.push({
        id: product.id,
        sku: product.sku,
        nombre: product.nombre,
        presentacion: product.presentacion || 'Caja estándar',
        material: product.material || 'Estándar',
        biodegradable: !!product.biodegradable,
        imagen_url: product.imagen_url,
        cantidad: cantidad
      });
    }

    this.saveToStorage();
    this.renderCartItems();
    this.openDrawer();

    if (window.showToast) {
      window.showToast(`Se agregó "${product.nombre}" a la cotización`, 'success');
    }
  },

  removeItem(sku) {
    this.items = this.items.filter(item => item.sku !== sku);
    this.saveToStorage();
    this.renderCartItems();
    if (window.showToast) {
      window.showToast('Producto retirado de la cotización', 'info');
    }
  },

  updateQuantity(sku, newQty) {
    const item = this.items.find(i => i.sku === sku);
    if (item) {
      item.cantidad = Math.max(1, parseInt(newQty, 10) || 1);
      this.saveToStorage();
      this.renderCartItems();
    }
  },

  clearCart() {
    if (this.items.length === 0) return;
    if (confirm('¿Desea vaciar todos los productos del carrito de cotización?')) {
      this.items = [];
      this.saveToStorage();
      this.renderCartItems();
      if (window.showToast) {
        window.showToast('Carrito de cotización vaciado', 'info');
      }
    }
  },

  getTotalItems() {
    return this.items.reduce((sum, item) => sum + item.cantidad, 0);
  },

  updateBadges() {
    const total = this.getTotalItems();
    const badges = document.querySelectorAll('.cart-count-badge');
    badges.forEach(badge => {
      badge.textContent = total;
      if (total > 0) {
        badge.classList.remove('hidden');
      } else {
        badge.classList.add('hidden');
      }
    });
  },

  renderDrawerMarkup() {
    if (document.getElementById('cartDrawerContainer')) return;

    const drawerHTML = `
      <!-- Backdrop -->
      <div id="cartBackdrop" class="fixed inset-0 bg-stone-900/60 backdrop-blur-sm z-50 transition-opacity duration-300 opacity-0 pointer-events-none"></div>

      <!-- Drawer Lateral -->
      <aside id="cartDrawer" class="fixed top-0 right-0 h-full w-full max-w-[100vw] sm:max-w-md bg-[#FDFBF7] shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col border-l border-[#EAE3DA]">
        
        <!-- Header del Carrito -->
        <div class="px-4 sm:px-6 py-3.5 sm:py-4 bg-white/90 backdrop-blur border-b border-[#EAE3DA] flex items-center justify-between">
          <div class="flex items-center gap-2.5 sm:gap-3">
            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-[#C85A32]/10 text-[#C85A32] flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
              </svg>
            </div>
            <div>
              <h3 class="font-bold text-base sm:text-lg text-[#1F1815]">Cotizador Express</h3>
              <p class="text-[11px] text-[#574B46]">Atención comercial y WhatsApp directo</p>
            </div>
          </div>
          <button id="closeCartBtn" class="p-2 sm:p-2.5 rounded-xl text-stone-500 hover:text-stone-800 hover:bg-stone-100 transition-colors tap-target" aria-label="Cerrar carrito">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Lista de Productos (Scrollable) -->
        <div id="cartItemsList" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-3 sm:space-y-4 touch-scroll">
          <!-- Renderizado dinámico -->
        </div>

        <!-- Formulario y Footer de Cotización -->
        <div id="cartFooterArea" class="p-4 sm:p-5 bg-white border-t border-[#EAE3DA] space-y-3 max-h-[50vh] sm:max-h-[55vh] overflow-y-auto touch-scroll">
          
          <div class="bg-[#F4EFEA] p-3 sm:p-3.5 rounded-xl text-xs text-[#574B46] space-y-2">
            <div class="font-semibold text-[#1F1815] flex items-center justify-between">
              <span>Datos de Cotización (Perú)</span>
              <span class="text-[10px] font-normal text-[#C85A32]">*Recomendado</span>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <div>
                <label class="block text-[11px] text-[#574B46] mb-0.5">Comprobante:</label>
                <select id="cotizacionTipoComp" class="w-full text-xs bg-white border border-[#EAE3DA] rounded-lg px-2.5 py-2 sm:py-1.5 focus:outline-none focus:border-[#C85A32]">
                  <option value="Factura">Factura con RUC</option>
                  <option value="Boleta">Boleta de Venta</option>
                </select>
              </div>
              <div>
                <label class="block text-[11px] text-[#574B46] mb-0.5">RUC / DNI:</label>
                <input type="text" id="cotizacionDoc" placeholder="11 u 8 dígitos" class="w-full text-xs bg-white border border-[#EAE3DA] rounded-lg px-2.5 py-2 sm:py-1.5 focus:outline-none focus:border-[#C85A32]" maxlength="11">
              </div>
            </div>

            <div>
              <label class="block text-[11px] text-[#574B46] mb-0.5">Cliente / Razón Social:</label>
              <input type="text" id="cotizacionNombre" placeholder="Ej: Distribuidora Gastronómica / Juan Pérez" class="w-full text-xs bg-white border border-[#EAE3DA] rounded-lg px-2.5 py-2 sm:py-1.5 focus:outline-none focus:border-[#C85A32]">
            </div>

            <div>
              <label class="block text-[11px] text-[#574B46] mb-0.5">Destino / Agencia de Envíos:</label>
              <select id="cotizacionDestino" class="w-full text-xs bg-white border border-[#EAE3DA] rounded-lg px-2.5 py-2 sm:py-1.5 focus:outline-none focus:border-[#C85A32]">
                <option value="Lima Metropolitana (Entrega Directa)">Lima Metropolitana (Entrega Directa)</option>
                <option value="Provincia - Agencia Shalom">Provincia - Agencia Shalom</option>
                <option value="Provincia - Agencia Marvisur">Provincia - Agencia Marvisur</option>
                <option value="Provincia - Flores Hermanos">Provincia - Flores Hermanos</option>
                <option value="Recojo en Tienda (Cercado de Lima)">Recojo en Almacén (Av. Bertello 732-C)</option>
              </select>
            </div>
          </div>

          <!-- Acciones Principales -->
          <div class="space-y-2 pt-1">
            <button id="btnSendWhatsApp" class="w-full py-3.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/20 transition-all tap-target">
              <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
              </svg>
              <span>Enviar Cotización por WhatsApp</span>
            </button>

            <div class="flex items-center justify-between pt-1 text-xs text-[#574B46]">
              <button id="btnClearCart" class="text-rose-600 hover:underline py-1">Vaciar lista</button>
              <button id="btnCopyCart" class="text-[#C85A32] hover:underline flex items-center gap-1 py-1">
                <span>Copiar texto</span>
              </button>
            </div>
          </div>

        </div>

      </aside>
    `;

    const container = document.createElement('div');
    container.id = 'cartDrawerContainer';
    container.innerHTML = drawerHTML;
    document.body.appendChild(container);

    this.renderCartItems();
    this.prefillCustomerData();
  },

  prefillCustomerData() {
    try {
      const activeUser = JSON.parse(localStorage.getItem('dp_usuario_activo') || 'null');
      if (activeUser) {
        const docInput = document.getElementById('cotizacionDoc');
        const nameInput = document.getElementById('cotizacionNombre');
        const tipoComp = document.getElementById('cotizacionTipoComp');

        if (docInput && !docInput.value) docInput.value = activeUser.numero_documento || '';
        if (nameInput && !nameInput.value) nameInput.value = activeUser.nombre_razon_social || '';
        if (tipoComp && activeUser.tipo_documento === 'RUC') tipoComp.value = 'Factura';
      }
    } catch (e) {}
  },

  renderCartItems() {
    const listContainer = document.getElementById('cartItemsList');
    const footerArea = document.getElementById('cartFooterArea');
    if (!listContainer) return;

    if (this.items.length === 0) {
      listContainer.innerHTML = `
        <div class="h-full flex flex-col items-center justify-center text-center p-8 text-stone-400">
          <div class="w-16 h-16 rounded-2xl bg-stone-100 flex items-center justify-center text-stone-300 mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
          </div>
          <h4 class="font-bold text-[#1F1815] text-base mb-1">Tu cotizador está vacío</h4>
          <p class="text-xs text-[#574B46] mb-6">Explora nuestro catálogo y agrega los productos descartables que necesitas.</p>
          <a href="catalogo.html" class="px-4 py-2 bg-[#C85A32] text-white rounded-xl text-xs font-semibold hover:bg-[#B84A22] transition-colors">
            Explorar Catálogo
          </a>
        </div>
      `;
      if (footerArea) footerArea.classList.add('opacity-50', 'pointer-events-none');
      return;
    }

    if (footerArea) footerArea.classList.remove('opacity-50', 'pointer-events-none');

    listContainer.innerHTML = this.items.map(item => `
      <div class="bg-white p-3.5 rounded-2xl border border-[#EAE3DA] flex gap-3 shadow-sm hover:shadow-md transition-shadow">
        <img src="${item.imagen_url || 'https://images.unsplash.com/photo-1578916171728-46686eac8d58?auto=format&fit=crop&w=200&q=80'}" alt="${item.nombre}" class="w-16 h-16 rounded-xl object-cover border border-stone-100 flex-shrink-0">
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-1">
            <span class="text-[10px] font-bold text-[#C85A32] uppercase tracking-wider">${item.sku}</span>
            <button onclick="Carrito.removeItem('${item.sku}')" class="text-stone-400 hover:text-rose-600 p-0.5" title="Eliminar">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
          <h4 class="font-semibold text-xs text-[#1F1815] truncate" title="${item.nombre}">${item.nombre}</h4>
          <p class="text-[11px] text-[#574B46] mb-2">${item.presentacion}</p>
          
          <div class="flex items-center justify-between">
            <div class="flex items-center border border-[#EAE3DA] rounded-lg overflow-hidden bg-[#FDFBF7]">
              <button onclick="Carrito.updateQuantity('${item.sku}', ${item.cantidad - 1})" class="px-2 py-0.5 text-xs text-[#574B46] hover:bg-stone-200 transition-colors font-bold">-</button>
              <span class="px-2.5 py-0.5 text-xs font-semibold text-[#1F1815]">${item.cantidad}</span>
              <button onclick="Carrito.updateQuantity('${item.sku}', ${item.cantidad + 1})" class="px-2 py-0.5 text-xs text-[#574B46] hover:bg-stone-200 transition-colors font-bold">+</button>
            </div>
            ${item.biodegradable ? '<span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 font-medium">Eco</span>' : ''}
          </div>
        </div>
      </div>
    `).join('');
  },

  openDrawer() {
    const backdrop = document.getElementById('cartBackdrop');
    const drawer = document.getElementById('cartDrawer');
    if (!drawer) return;

    this.renderCartItems();
    this.prefillCustomerData();

    backdrop.classList.remove('pointer-events-none', 'opacity-0');
    backdrop.classList.add('opacity-100');
    drawer.classList.remove('translate-x-full');
    drawer.classList.add('translate-x-0');
    document.body.style.overflow = 'hidden';
  },

  closeDrawer() {
    const backdrop = document.getElementById('cartBackdrop');
    const drawer = document.getElementById('cartDrawer');
    if (!drawer) return;

    backdrop.classList.remove('opacity-100');
    backdrop.classList.add('opacity-0', 'pointer-events-none');
    drawer.classList.remove('translate-x-0');
    drawer.classList.add('translate-x-full');
    document.body.style.overflow = '';
  },

  buildWhatsAppText() {
    if (this.items.length === 0) return '';

    const tipoComp = document.getElementById('cotizacionTipoComp')?.value || 'Factura';
    const doc = document.getElementById('cotizacionDoc')?.value.trim() || 'No especificado';
    const nombre = document.getElementById('cotizacionNombre')?.value.trim() || 'Cliente Web';
    const destino = document.getElementById('cotizacionDestino')?.value || 'Lima Metropolitana';

    let text = `Hola Descartables Peruanos, deseo cotizar el siguiente pedido:\n\n`;
    this.items.forEach(item => {
      text += `- ${item.cantidad} x ${item.nombre} (${item.presentacion}) (SKU: ${item.sku})\n`;
    });

    text += `\nCliente: ${nombre} | RUC/DNI: ${doc}\n`;
    text += `Comprobante: ${tipoComp} | Destino: ${destino}\n`;
    text += `\nSolicito disponibilidad y precio por mayor/menor. ¡Muchas gracias!`;

    return text;
  },

  sendToWhatsApp() {
    if (this.items.length === 0) {
      alert('Tu carrito de cotización está vacío. Agrega productos para cotizar.');
      return;
    }

    const message = this.buildWhatsAppText();
    const encoded = encodeURIComponent(message);
    const waUrl = `https://wa.me/${this.whatsappNumber}?text=${encoded}`;

    // Guardar registro de la cotización en historial local
    try {
      const history = JSON.parse(localStorage.getItem('dp_historial_cotizaciones') || '[]');
      history.unshift({
        fecha: new Date().toLocaleString('es-PE'),
        items: [...this.items],
        comprobante: document.getElementById('cotizacionTipoComp')?.value || 'Factura',
        documento: document.getElementById('cotizacionDoc')?.value || '',
        nombre: document.getElementById('cotizacionNombre')?.value || 'Cliente Web',
        destino: document.getElementById('cotizacionDestino')?.value || 'Lima'
      });
      localStorage.setItem('dp_historial_cotizaciones', JSON.stringify(history.slice(0, 20)));
    } catch (e) {}

    window.open(waUrl, '_blank');
  },

  copyCartText() {
    const text = this.buildWhatsAppText();
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
      if (window.showToast) {
        window.showToast('¡Texto de la cotización copiado al portapapeles!', 'success');
      }
    }).catch(() => {
      alert('No se pudo copiar automáticamente. Por favor use el botón de WhatsApp.');
    });
  },

  attachEvents() {
    document.addEventListener('click', (e) => {
      // Disparador abrir carrito
      const trigger = e.target.closest('.open-cart-trigger');
      if (trigger) {
        e.preventDefault();
        this.openDrawer();
        return;
      }

      // Botón cerrar
      if (e.target.closest('#closeCartBtn') || e.target.id === 'cartBackdrop') {
        this.closeDrawer();
        return;
      }

      // Enviar WhatsApp
      if (e.target.closest('#btnSendWhatsApp')) {
        this.sendToWhatsApp();
        return;
      }

      // Vaciar
      if (e.target.closest('#btnClearCart')) {
        this.clearCart();
        return;
      }

      // Copiar
      if (e.target.closest('#btnCopyCart')) {
        this.copyCartText();
        return;
      }

      // Botón agregar a cotización con atributo data-sku o data-id
      const addBtn = e.target.closest('.btn-add-quote');
      if (addBtn) {
        const sku = addBtn.dataset.sku;
        const id = parseInt(addBtn.dataset.id, 10);
        let qty = 1;
        
        // Buscar si hay selector de cantidad hermano o padre
        const parentCard = addBtn.closest('.product-card');
        if (parentCard) {
          const qtyInput = parentCard.querySelector('.input-qty-selector');
          if (qtyInput) qty = parseInt(qtyInput.value, 10) || 1;
        }

        const product = PRODUCTOS.find(p => p.sku === sku || p.id === id);
        if (product) {
          this.addItem(product, qty);
        }
      }
    });

    // Tecla Escape para cerrar
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        this.closeDrawer();
      }
    });
  }
};

// Exponer globalmente
window.Carrito = Carrito;

