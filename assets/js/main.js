/**
 * Inicializador Global: Descartables Peruanos
 * Manejo de navegación, barra móvil inferior, toasts, atajos y enlaces
 * Optimizado para 100% Responsividad en Móviles, Tablets y Escritorios
 */

// Sistema de Notificaciones Toast
window.showToast = function(message, type = 'info') {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'fixed bottom-20 lg:bottom-6 right-4 left-4 sm:left-auto sm:right-6 z-50 flex flex-col gap-2 pointer-events-none max-w-sm';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  const bgColors = {
    success: 'bg-emerald-800 text-white border-emerald-600',
    info: 'bg-[#1F1815] text-white border-stone-700',
    warning: 'bg-amber-800 text-white border-amber-600',
    error: 'bg-rose-800 text-white border-rose-600'
  };

  toast.className = `pointer-events-auto px-4 py-3 rounded-2xl shadow-xl text-xs font-semibold flex items-center gap-2.5 border backdrop-blur-md transition-all duration-300 transform translate-y-4 opacity-0 ${bgColors[type] || bgColors.info}`;
  toast.innerHTML = `<span>${message}</span>`;

  container.appendChild(toast);

  // Entrada
  setTimeout(() => {
    toast.classList.remove('translate-y-4', 'opacity-0');
  }, 10);

  // Salida
  setTimeout(() => {
    toast.classList.add('opacity-0', 'translate-y-2');
    setTimeout(() => toast.remove(), 300);
  }, 3500);
};

window.Toast = {
  success: (msg) => window.showToast(msg, 'success'),
  info: (msg) => window.showToast(msg, 'info'),
  warning: (msg) => window.showToast(msg, 'warning'),
  error: (msg) => window.showToast(msg, 'error')
};

// Inyección de Barra Móvil Inferior
function injectMobileBottomBar() {
  if (document.getElementById('mobileBottomBar')) return;

  const currentPath = window.location.pathname.split('/').pop() || 'index.html';
  // Excluir en login y registro para evitar solapamiento con teclado virtual y botones
  if (currentPath.includes('login') || currentPath.includes('registro')) return;

  const isHome = currentPath === 'index.html' || currentPath === '';
  const isCat = currentPath === 'catalogo.html';
  const isProfile = currentPath === 'perfil.html';

  const barHTML = `
    <nav id="mobileBottomBar" class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-[#EAE3DA] py-1.5 px-3 flex items-center justify-around shadow-[0_-4px_20px_rgba(0,0,0,0.06)] mobile-quick-bar">
      
      <a href="index.html" class="flex flex-col items-center py-1 px-2.5 rounded-xl transition-colors tap-target ${isHome ? 'text-[#C85A32] font-bold' : 'text-[#574B46] hover:text-[#C85A32]'}">
        <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
        <span class="text-[10px]">Inicio</span>
      </a>

      <a href="catalogo.html" class="flex flex-col items-center py-1 px-2.5 rounded-xl transition-colors tap-target ${isCat ? 'text-[#C85A32] font-bold' : 'text-[#574B46] hover:text-[#C85A32]'}">
        <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
        </svg>
        <span class="text-[10px]">Catálogo</span>
      </a>

      <button class="open-cart-trigger relative flex flex-col items-center py-1 px-2.5 rounded-xl text-[#C85A32] tap-target">
        <div class="relative">
          <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
          </svg>
          <span class="cart-count-badge hidden absolute -top-1 -right-2.5 min-w-[16px] h-4 px-1 rounded-full bg-[#C85A32] text-white text-[9px] font-bold flex items-center justify-center shadow-md">0</span>
        </div>
        <span class="text-[10px] font-bold">Cotizar</span>
      </button>

      <a href="https://wa.me/51994195430" target="_blank" class="flex flex-col items-center py-1 px-2.5 rounded-xl text-emerald-600 tap-target">
        <svg class="w-5 h-5 mb-0.5 fill-current" viewBox="0 0 24 24">
          <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
        </svg>
        <span class="text-[10px] font-bold">WhatsApp</span>
      </a>

      <a href="${localStorage.getItem('dp_usuario_activo') ? 'perfil.html' : 'login.html'}" class="flex flex-col items-center py-1 px-2.5 rounded-xl transition-colors tap-target ${isProfile ? 'text-[#C85A32] font-bold' : 'text-[#574B46] hover:text-[#C85A32]'}">
        <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
        </svg>
        <span class="text-[10px]">Cuenta</span>
      </a>

    </nav>
  `;

  document.body.insertAdjacentHTML('beforeend', barHTML);
  document.body.classList.add('pb-16', 'lg:pb-0');
}

// Inyección del Banner Flotante de Términos y Condiciones (Esquina Inferior Izquierda)
function injectTermsConsentCard() {
  // Si ya fueron aceptados, no mostrar
  if (localStorage.getItem('dp_terminos_aceptados') === 'true') return;
  if (document.getElementById('dpTermsConsentCard')) return;

  const currentPath = window.location.pathname.split('/').pop() || 'index.html';
  // Si estamos directamente en terminos-y-condiciones.html, no hace falta tapar el contenido
  if (currentPath.includes('terminos-y-condiciones')) return;

  const cardHTML = `
    <div id="dpTermsConsentCard" class="fixed bottom-20 lg:bottom-5 left-4 right-4 sm:right-auto sm:left-5 z-50 max-w-sm bg-white/95 backdrop-blur-md p-4 sm:p-5 rounded-3xl border border-[#EAE3DA] shadow-2xl shadow-black/15 transition-all duration-300 animate-fade-in">
      <div class="flex items-start gap-3">
        <div class="w-9 h-9 rounded-2xl bg-[#F8ECE7] text-[#C85A32] flex items-center justify-center flex-shrink-0 mt-0.5">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <div class="space-y-1.5 flex-1">
          <div class="flex items-center justify-between">
            <h4 class="font-heading font-extrabold text-xs text-[#1F1815] uppercase tracking-wide">Términos y Condiciones</h4>
            <span class="text-[10px] font-bold text-[#C85A32] bg-[#F8ECE7] px-2 py-0.5 rounded-full">Legal Perú</span>
          </div>
          <p class="text-[11px] leading-relaxed text-[#574B46]">
            Al navegar o cotizar en nuestra plataforma, aceptas nuestros 
            <a href="terminos-y-condiciones.html" class="text-[#C85A32] font-bold underline hover:text-[#B84A22]">Términos de Uso</a> y Políticas conforme a la normativa de INDECOPI y SUNAT.
          </p>
          <div class="pt-2 flex items-center gap-2">
            <button type="button" onclick="acceptTermsConsent()" class="flex-1 py-2 px-3 bg-[#C85A32] hover:bg-[#B84A22] text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer tap-target text-center">
              Aceptar
            </button>
            <button type="button" onclick="rejectTermsConsent()" class="py-2 px-3 bg-[#F4EFEA] hover:bg-stone-200 text-[#574B46] text-xs font-semibold rounded-xl border border-[#EAE3DA] transition-colors cursor-pointer tap-target text-center">
              Rechazar
            </button>
          </div>
        </div>
      </div>
    </div>
  `;

  document.body.insertAdjacentHTML('beforeend', cardHTML);
}

window.acceptTermsConsent = function() {
  localStorage.setItem('dp_terminos_aceptados', 'true');
  const card = document.getElementById('dpTermsConsentCard');
  if (card) {
    card.classList.add('opacity-0', 'translate-y-4');
    setTimeout(() => card.remove(), 300);
  }
  if (window.showToast) {
    showToast('Has aceptado los Términos y Condiciones.', 'success');
  }
};

window.rejectTermsConsent = function() {
  const card = document.getElementById('dpTermsConsentCard');
  if (card) {
    card.innerHTML = `
      <div class="p-3 text-center space-y-2">
        <p class="text-xs font-bold text-rose-700">Has rechazado los Términos de Uso de la plataforma.</p>
        <p class="text-[11px] text-[#574B46]">Redirigiendo fuera del sitio web...</p>
      </div>
    `;
  }
  setTimeout(() => {
    // Redirigir fuera de la web
    window.location.href = 'https://www.google.com';
  }, 1000);
};

// Módulo de Productos Destacados en Página Principal
const IndexFeatured = {
  products: [],
  async init() {
    const grid = document.getElementById('featuredProductsGrid');
    if (!grid) return;

    try {
      if (window.ApiService) {
        const prods = await ApiService.getProducts({ destacado: true });
        if (Array.isArray(prods) && prods.length > 0) {
          this.products = prods;
          this.render();
        }
      }
    } catch (e) {
      console.warn('Error cargando destacados en index:', e);
    }
  },

  render() {
    const grid = document.getElementById('featuredProductsGrid');
    if (!grid) return;

    // Mostrar hasta 8 productos destacados
    const items = this.products.slice(0, 8);
    grid.innerHTML = items.map(prod => `
      <div class="product-card bg-white rounded-2xl border border-warm-border p-4 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between group">
        <div>
          <div class="relative h-44 rounded-xl overflow-hidden bg-warm-sand mb-3">
            <img src="${prod.imagen_url}" alt="${prod.nombre}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
            <div class="absolute top-2 left-2 flex flex-col gap-1">
              <span class="px-2 py-0.5 rounded bg-espresso text-white font-mono text-[10px] font-bold">${prod.sku}</span>
              ${prod.biodegradable ? '<span class="px-2 py-0.5 rounded bg-emerald-700 text-white text-[9px] font-bold">100% Bio</span>' : ''}
            </div>
          </div>
          <span class="text-[10px] font-semibold text-terracota uppercase">${prod.categoria_nombre || 'Descartables'}</span>
          <h3 class="font-bold text-sm text-espresso mt-0.5 leading-snug line-clamp-2" title="${prod.nombre}">${prod.nombre}</h3>
          <p class="text-xs text-espresso-muted line-clamp-2 my-1">${prod.descripcion || ''}</p>
          <p class="text-xs font-semibold text-stone-700 bg-warm-sand px-2 py-1 rounded inline-block my-1">${prod.presentacion || ''}</p>
        </div>
        <div class="space-y-2 mt-2 pt-3 border-t border-warm-border">
          <div class="flex items-center gap-2">
            <input type="number" min="1" value="1" class="input-qty-selector w-12 py-1.5 text-center text-xs border border-warm-border rounded-lg bg-warm-cream">
            <button type="button" data-sku="${prod.sku}" class="btn-add-quote flex-1 py-2 bg-terracota hover:bg-terracota-hover text-white rounded-lg text-xs font-semibold transition-colors flex items-center justify-center gap-1 cursor-pointer tap-target">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              <span>Cotizar</span>
            </button>
          </div>
          <button type="button" data-compare-sku="${prod.sku}" onclick="Comparador.toggle('${prod.sku}')" class="w-full py-1.5 px-2 rounded-lg border border-[#EAE3DA] bg-white text-[#574B46] hover:bg-[#F4EFEA] text-[11px] font-semibold flex items-center justify-center gap-1 transition-all cursor-pointer tap-target">
            <svg class="w-3 h-3 text-[#C85A32]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            <span>Comparar</span>
          </button>
        </div>
      </div>
    `).join('');

    if (window.Comparador) {
      Comparador.syncCardButtons();
    }
  }
};
window.IndexFeatured = IndexFeatured;

document.addEventListener('DOMContentLoaded', () => {
  // 1. Inicializar Carrito de Cotización en todas las páginas
  if (window.Carrito) {
    Carrito.init();
  }

  // 1.5. Cargar destacados dinámicos en index si existe el contenedor
  IndexFeatured.init();

  // 2. Inicializar Estado de Usuario en el Navbar
  if (window.Auth) {
    Auth.updateNavbarUserUI();
  }

  // 3. Barra Móvil Inferior
  injectMobileBottomBar();

  // 4. Banner Flotante de Términos y Condiciones
  injectTermsConsentCard();

  // 5. Menú Móvil Superior
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const mobileNav = document.getElementById('mobileNav');
  if (mobileMenuBtn && mobileNav) {
    mobileMenuBtn.addEventListener('click', () => {
      mobileNav.classList.toggle('hidden');
    });

    // Cerrar al hacer clic en enlaces móviles
    mobileNav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        mobileNav.classList.add('hidden');
      });
    });
  }

  // 6. Buscador Rápido del Header
  const navSearchInput = document.getElementById('navSearchInput');
  if (navSearchInput) {
    navSearchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        const query = navSearchInput.value.trim();
        if (query) {
          window.location.href = `catalogo.html?q=${encodeURIComponent(query)}`;
        }
      }
    });
  }

  // 7. Resaltar enlace de navegación activo
  const currentPath = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-link').forEach(link => {
    const href = link.getAttribute('href');
    if (href === currentPath || (currentPath === '' && href === 'index.html')) {
      link.classList.add('text-[#C85A32]', 'font-bold');
    }
  });
});
