/**
 * Inicializador Global: Descartables Peruanos
 * Manejo de navegación, toasts, atajos y enlaces
 */

// Sistema de Notificaciones Toast
window.showToast = function(message, type = 'info') {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none';
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
  toast.innerHTML = `
    <span>${message}</span>
  `;

  container.appendChild(toast);

  // Animación de entrada
  setTimeout(() => {
    toast.classList.remove('translate-y-4', 'opacity-0');
  }, 10);

  // Desvanecimiento
  setTimeout(() => {
    toast.classList.add('opacity-0', 'translate-y-2');
    setTimeout(() => toast.remove(), 300);
  }, 3500);
};

document.addEventListener('DOMContentLoaded', () => {
  // 1. Inicializar Carrito de Cotización en todas las páginas
  if (window.Carrito) {
    Carrito.init();
  }

  // 2. Inicializar Estado de Usuario en el Navbar
  if (window.Auth) {
    Auth.updateNavbarUserUI();
  }

  // 3. Menú Móvil
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const mobileNav = document.getElementById('mobileNav');
  if (mobileMenuBtn && mobileNav) {
    mobileMenuBtn.addEventListener('click', () => {
      mobileNav.classList.toggle('hidden');
    });
  }

  // 4. Buscador Rápido del Header
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

  // 5. Resaltar enlace de navegación activo
  const currentPath = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-link').forEach(link => {
    const href = link.getAttribute('href');
    if (href === currentPath || (currentPath === '' && href === 'index.html')) {
      link.classList.add('text-[#C85A32]', 'font-bold');
    }
  });
});

