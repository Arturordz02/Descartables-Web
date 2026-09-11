/**
 * Módulo de Autenticación y Gestión de Perfil de Cliente
 * Soporte para Personas Naturales (DNI) y Empresas (RUC)
 */

const Auth = {
  getCurrentUser() {
    try {
      if (typeof StorageHelper !== 'undefined') {
        return StorageHelper.get('dp_usuario_activo', null);
      }
      const user = localStorage.getItem('dp_usuario_activo');
      return user ? JSON.parse(user) : null;
    } catch (e) {
      try { localStorage.removeItem('dp_usuario_activo'); } catch (_) {}
      return null;
    }
  },

  isLoggedIn() {
    return this.getCurrentUser() !== null;
  },

  logout() {
    if (typeof AdminNotifications !== 'undefined' && typeof AdminNotifications.stop === 'function') {
      AdminNotifications.stop();
    }
    try {
      localStorage.removeItem('dp_usuario_activo');
      localStorage.removeItem('dp_mis_cotizaciones');
      localStorage.removeItem('dp_historial_cotizaciones');
      localStorage.removeItem('dp_cotizaciones_recibidas');
      localStorage.removeItem('dp_libro_reclamaciones');
      localStorage.removeItem('dp_admin_notifications_cache');
      localStorage.removeItem('dp_admin_stats');
      if (typeof sessionStorage !== 'undefined') {
        sessionStorage.clear();
      }
    } catch (e) {}

    if (typeof Carrito !== 'undefined' && typeof Carrito.reloadUserCart === 'function') {
      Carrito.reloadUserCart();
    }
    if (window.showToast) {
      window.showToast('Sesión cerrada correctamente', 'info');
    }
    setTimeout(() => {
      window.location.href = 'index.html';
    }, 500);
  },

  updateNavbarUserUI() {
    const user = this.getCurrentUser();
    const navUserContainers = document.querySelectorAll('.nav-user-container');
    const mobileUserContainers = document.querySelectorAll('.nav-user-container-mobile');

    const esc = (s) => (typeof escapeHtml === 'function' ? escapeHtml(s) : String(s || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m])));

    navUserContainers.forEach((container, idx) => {
      if (user) {
        const rawName = user.nombre_razon_social || 'Mi Cuenta';
        const safeName = esc(rawName);
        const safeShortName = esc(rawName.split(' ')[0] || 'Mi Cuenta');
        const safeDocType = esc(user.tipo_documento || 'DOC');
        const safeDocNum = esc(user.numero_documento || '');
        const safeEmail = esc(user.email || '');
        const initial = esc(rawName.charAt(0).toUpperCase() || 'C');
        const dropdownId = `userDropdownMenu_${idx}`;

        container.innerHTML = `
          <div class="relative inline-block text-left">
            <button type="button" onclick="Auth.toggleUserDropdown(event, '${dropdownId}')" class="flex items-center gap-2 py-1.5 px-3 rounded-xl bg-[#F4EFEA] hover:bg-[#EAE3DA] text-[#1F1815] text-xs font-semibold border border-[#EAE3DA] transition-colors tap-target cursor-pointer select-none">
              <span class="w-6 h-6 rounded-full bg-[#C85A32] text-white flex items-center justify-center font-bold text-[10px]">
                ${initial}
              </span>
              <span class="max-w-[120px] truncate">${safeShortName}</span>
              <svg class="w-3.5 h-3.5 text-stone-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            
            <!-- Dropdown Menú sin brecha de cursor y con toggle interactivo -->
            <div id="${dropdownId}" class="user-dropdown-menu absolute right-0 top-full mt-1.5 w-60 bg-white rounded-2xl shadow-2xl border border-[#EAE3DA] py-2 hidden z-50 animate-fade-in divide-y divide-stone-100">
              <div class="px-4 py-2.5 bg-gradient-to-r from-warm-sand to-warm-cream rounded-t-xl">
                <div class="flex items-center justify-between">
                  <p class="text-xs font-bold text-[#1F1815] truncate" title="${safeName}">${safeName}</p>
                  ${user.rol === 'admin' ? '<span class="text-[9px] px-1.5 py-0.5 rounded-full bg-[#C85A32] text-white font-black uppercase">ADMIN</span>' : ''}
                </div>
                <p class="text-[10px] text-[#C85A32] font-semibold">${safeDocType}: ${safeDocNum}</p>
                <p class="text-[10px] text-stone-500 truncate">${safeEmail}</p>
              </div>
              <div class="py-1">
                ${user.rol === 'admin' ? `
                  <a href="admin.html" class="flex items-center gap-2.5 px-4 py-2 text-xs text-[#C85A32] bg-amber-50 hover:bg-amber-100 font-bold transition-colors">
                    <svg class="w-4 h-4 text-[#C85A32] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Panel de Administración</span>
                  </a>
                ` : ''}
                <a href="perfil.html" class="flex items-center gap-2.5 px-4 py-2 text-xs text-[#1F1815] hover:bg-[#F4EFEA] font-medium transition-colors">
                  <svg class="w-4 h-4 text-[#C85A32] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                  <span>Mi Perfil y Facturación</span>
                </a>
                <a href="catalogo.html" class="flex items-center gap-2.5 px-4 py-2 text-xs text-[#1F1815] hover:bg-[#F4EFEA] transition-colors">
                  <svg class="w-4 h-4 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                  <span>Catálogo de Productos</span>
                </a>
              </div>
              <div class="py-1">
                <button type="button" onclick="Auth.logout()" class="w-full flex items-center gap-2.5 px-4 py-2 text-xs text-rose-600 hover:bg-rose-50 font-semibold transition-colors cursor-pointer">
                  <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                  <span>Cerrar Sesión</span>
                </button>
              </div>
            </div>
          </div>
        `;
      } else {
        container.innerHTML = `
          <div class="flex items-center gap-2">
            <a href="login.html" class="text-xs font-semibold text-[#1F1815] hover:text-[#C85A32] py-2 px-3 transition-colors tap-target">
              Ingresar
            </a>
            <a href="registro.html" class="text-xs font-semibold bg-[#C85A32] hover:bg-[#B84A22] text-white py-2 px-3.5 rounded-xl shadow-sm transition-colors tap-target">
              Registrarme
            </a>
          </div>
        `;
      }
    });

    mobileUserContainers.forEach(container => {
      if (user) {
        const isAdmin = user.rol === 'admin';
        const rawName = user.nombre_razon_social || 'Mi Cuenta';
        const safeName = esc(rawName);
        const safeDocType = esc(user.tipo_documento || 'DOC');
        const safeDocNum = esc(user.numero_documento || '');
        const initial = esc(rawName.charAt(0).toUpperCase() || 'C');

        container.innerHTML = `
          <div class="p-3 bg-white rounded-2xl border border-[#EAE3DA] mb-2 space-y-2.5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-8 h-8 rounded-full bg-[#C85A32] text-white flex items-center justify-center font-bold text-xs flex-shrink-0">
                  ${initial}
                </span>
                <div class="min-w-0">
                  <div class="flex items-center gap-1.5">
                    <p class="text-xs font-bold text-[#1F1815] truncate max-w-[140px]">${safeName}</p>
                    ${isAdmin ? '<span class="text-[9px] px-1.5 py-0.2 rounded-full bg-[#C85A32] text-white font-extrabold uppercase">ADMIN</span>' : ''}
                  </div>
                  <p class="text-[10px] text-[#574B46]">${safeDocType}: ${safeDocNum}</p>
                </div>
              </div>
              <button type="button" onclick="Auth.logout()" class="p-1.5 text-rose-500 hover:bg-rose-50 rounded-lg text-xs font-bold transition-colors" title="Cerrar sesión">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
              </button>
            </div>
            <div class="grid ${isAdmin ? 'grid-cols-2' : 'grid-cols-1'} gap-2 pt-1 border-t border-stone-100">
              ${isAdmin ? `
                <a href="admin.html" class="flex items-center justify-center gap-1.5 py-2 px-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold shadow-sm transition-all text-center">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                  <span>Panel Admin</span>
                </a>
              ` : ''}
              <a href="perfil.html" class="flex items-center justify-center gap-1.5 py-2 px-3 bg-[#F4EFEA] hover:bg-[#EAE3DA] text-[#C85A32] rounded-xl text-xs font-bold transition-all text-center">
                <span>Mi Cuenta</span>
              </a>
            </div>
          </div>
        `;
      } else {
        container.innerHTML = `
          <div class="grid grid-cols-2 gap-2 mb-2">
            <a href="login.html" class="text-center py-2 px-3 rounded-xl border border-[#EAE3DA] text-xs font-semibold text-[#1F1815] bg-white">
              Iniciar Sesión
            </a>
            <a href="registro.html" class="text-center py-2 px-3 rounded-xl bg-[#C85A32] text-white text-xs font-semibold shadow-sm">
              Crear Cuenta
            </a>
          </div>
        `;
      }
    });
  },

  // Inicialización de la vista login.html
  initLoginPage() {
    const loginForm = document.getElementById('loginForm');
    if (!loginForm) return;

    // Actualizar indicador de conexión con MySQL
    const statusBadge = document.getElementById('backendStatusBadge');
    if (statusBadge) {
      ApiService.checkBackendAvailability().then(isAvailable => {
        if (isAvailable) {
          statusBadge.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500"></span><span class="text-emerald-800 font-bold">Conectado a Base de Datos MySQL</span>';
          statusBadge.className = 'p-2 rounded-xl text-center text-[11px] bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-center gap-1.5';
        } else {
          statusBadge.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500"></span><span class="text-emerald-800 font-bold">Sistema en Línea</span>';
          statusBadge.className = 'p-2 rounded-xl text-center text-[11px] bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-center gap-1.5';
        }
      });
    }

    // Mostrar mensaje de redirección previa (ej: intento de ingresar a admin sin sesión)
    const authMsg = sessionStorage.getItem('dp_auth_message');
    if (authMsg) {
      const errorBox = document.getElementById('loginErrorBox');
      if (errorBox) {
        errorBox.textContent = authMsg;
        errorBox.classList.remove('hidden');
      }
      sessionStorage.removeItem('dp_auth_message');
    }

    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const identificador = document.getElementById('loginIdentificador').value.trim();
      const password = document.getElementById('loginPassword').value;
      const errorBox = document.getElementById('loginErrorBox');
      const submitBtn = loginForm.querySelector('button[type="submit"]');

      if (!identificador || !password) {
        this.showFormError(errorBox, 'Por favor complete todos los campos.');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span>Verificando...</span>';

      const result = await ApiService.login(identificador, password);
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<span>Iniciar Sesión</span>';

      if (result.success) {
        if (window.showToast) window.showToast('¡Bienvenido(a)!', 'success');
        if (result.user && result.user.rol === 'admin') {
          window.location.href = 'admin.html';
        } else {
          window.location.href = 'perfil.html';
        }
      } else {
        this.showFormError(errorBox, result.error || 'Credenciales incorrectas');
      }
    });
  },

  // Inicialización de la vista registro.html
  initRegisterPage() {
    const registerForm = document.getElementById('registerForm');
    if (!registerForm) return;

    // Actualizar indicador de conexión con MySQL
    const statusBadge = document.getElementById('backendStatusBadge');
    if (statusBadge) {
      ApiService.checkBackendAvailability().then(isAvailable => {
        if (isAvailable) {
          statusBadge.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500"></span><span class="text-emerald-800 font-bold">Conectado a Base de Datos MySQL</span>';
          statusBadge.className = 'p-2 rounded-xl text-center text-[11px] bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-center gap-1.5';
        } else {
          statusBadge.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500"></span><span class="text-emerald-800 font-bold">Sistema en Línea</span>';
          statusBadge.className = 'p-2 rounded-xl text-center text-[11px] bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-center gap-1.5';
        }
      });
    }

    const tipoDocSelect = document.getElementById('regTipoDoc');
    const numDocInput = document.getElementById('regNumDoc');
    const labelNombre = document.getElementById('regLabelNombre');
    const labelDoc = document.getElementById('regLabelDoc');

    // Ajuste dinámico de etiquetas según DNI o RUC
    if (tipoDocSelect) {
      tipoDocSelect.addEventListener('change', () => {
        if (tipoDocSelect.value === 'RUC') {
          if (labelNombre) labelNombre.textContent = 'Razón Social de la Empresa *';
          if (labelDoc) labelDoc.textContent = 'Número de RUC (11 dígitos) *';
          numDocInput.placeholder = 'Ej: 20601234567';
          numDocInput.maxLength = 11;
        } else {
          if (labelNombre) labelNombre.textContent = 'Nombres y Apellidos Completos *';
          if (labelDoc) labelDoc.textContent = 'Número de DNI (8 dígitos) *';
          numDocInput.placeholder = 'Ej: 45879632';
          numDocInput.maxLength = 8;
        }
      });
    }

    registerForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const tipoDoc = document.getElementById('regTipoDoc').value;
      const numDoc = document.getElementById('regNumDoc').value.trim();
      const nombre = document.getElementById('regNombre').value.trim();
      const email = document.getElementById('regEmail').value.trim();
      const telefono = document.getElementById('regTelefono').value.trim();
      const password = document.getElementById('regPassword').value;
      const passwordConf = document.getElementById('regPasswordConf').value;
      const direccion = document.getElementById('regDireccion')?.value.trim() || '';
      const departamento = document.getElementById('regDepartamento')?.value || 'Lima';
      const distrito = document.getElementById('regDistrito')?.value || 'Cercado de Lima';
      const errorBox = document.getElementById('regErrorBox');
      const submitBtn = registerForm.querySelector('button[type="submit"]');

      if (!numDoc || !nombre || !email || !telefono || !password) {
        this.showFormError(errorBox, 'Todos los campos obligatorios deben completarse.');
        return;
      }

      if (tipoDoc === 'DNI' && numDoc.length !== 8) {
        this.showFormError(errorBox, 'El DNI debe tener exactamente 8 dígitos.');
        return;
      }
      if (tipoDoc === 'RUC' && numDoc.length !== 11) {
        this.showFormError(errorBox, 'El RUC debe tener exactamente 11 dígitos.');
        return;
      }
      if (password !== passwordConf) {
        this.showFormError(errorBox, 'Las contraseñas no coinciden.');
        return;
      }
      if (password.length < 6) {
        this.showFormError(errorBox, 'La contraseña debe tener al menos 6 caracteres.');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span>Creando cuenta...</span>';

      const result = await ApiService.register({
        tipo_documento: tipoDoc,
        numero_documento: numDoc,
        nombre_razon_social: nombre,
        email: email,
        telefono: telefono,
        password: password,
        departamento: departamento,
        provincia: 'Lima',
        distrito: distrito,
        direccion: direccion
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = '<span>Crear Cuenta en Descartables Peruanos</span>';

      if (result.success) {
        if (window.showToast) window.showToast('¡Cuenta creada exitosamente!', 'success');
        window.location.href = 'perfil.html';
      } else {
        this.showFormError(errorBox, result.error || 'Error en el registro');
      }
    });
  },

  // Inicialización de la vista perfil.html
  initProfilePage() {
    const user = this.getCurrentUser();
    if (!user) {
      window.location.href = 'login.html';
      return;
    }

    // Mostrar banner de acceso a Admin si el usuario tiene rol admin
    if (user.rol === 'admin') {
      const adminBanner = document.getElementById('perfilAdminBanner');
      if (adminBanner) adminBanner.classList.remove('hidden');
    }

    // Llenar campos de perfil
    const elNombre = document.getElementById('perfilNombreDisplay');
    const elDoc = document.getElementById('perfilDocDisplay');
    const elEmail = document.getElementById('perfilEmailDisplay');
    const elAvatar = document.getElementById('perfilAvatar');

    if (elNombre) elNombre.textContent = user.nombre_razon_social;
    if (elDoc) elDoc.textContent = `${user.tipo_documento}: ${user.numero_documento}`;
    if (elEmail) elEmail.textContent = user.email;
    if (elAvatar) elAvatar.textContent = (user.nombre_razon_social || 'C').charAt(0).toUpperCase();

    const inputNombre = document.getElementById('perfilNombre');
    const inputTel = document.getElementById('perfilTelefono');
    const inputDir = document.getElementById('perfilDireccion');
    const inputDep = document.getElementById('perfilDepartamento');
    const inputDis = document.getElementById('perfilDistrito');

    if (inputNombre) inputNombre.value = user.nombre_razon_social || '';
    if (inputTel) inputTel.value = user.telefono || '';
    if (inputDir) inputDir.value = user.direccion || '';
    if (inputDep) inputDep.value = user.departamento || 'Lima';
    if (inputDis) inputDis.value = user.distrito || 'Cercado de Lima';

    // Manejar envío del formulario de actualización
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
      profileForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = profileForm.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Guardando cambios...';

        const updatedData = {
          id: user.id,
          nombre_razon_social: inputNombre.value.trim(),
          telefono: inputTel.value.trim(),
          direccion: inputDir.value.trim(),
          departamento: inputDep.value,
          provincia: 'Lima',
          distrito: inputDis.value
        };

        const res = await ApiService.updateProfile(updatedData);
        submitBtn.disabled = false;
        submitBtn.textContent = 'Guardar Cambios';

        if (res.success) {
          if (window.showToast) window.showToast('Datos de perfil actualizados con éxito', 'success');
          if (elNombre) elNombre.textContent = res.user.nombre_razon_social;
        } else {
          if (window.Toast) Toast.error('Hubo un inconveniente al actualizar: ' + (res.error || 'intente de nuevo.'));
          else alert('Hubo un inconveniente al actualizar: ' + (res.error || 'intente de nuevo.'));
        }
      });
    }

    // Mostrar historial de cotizaciones y reclamaciones (MySQL + Fallback Local)
    this.renderHistorialCotizaciones(user);
    this.renderHistorialReclamaciones(user);
  },

  async renderHistorialCotizaciones(user = null) {
    const container = document.getElementById('historialCotizacionesList');
    if (!container) return;

    const currentUser = user || this.getCurrentUser();
    container.innerHTML = `
      <div class="p-6 text-center text-stone-400 bg-[#FDFBF7] rounded-2xl border border-[#EAE3DA]">
        <div class="inline-block w-5 h-5 border-2 border-[#C85A32] border-t-transparent rounded-full animate-spin mb-2"></div>
        <p class="text-xs">Consultando cotizaciones...</p>
      </div>
    `;

    let history = [];
    let fetchedFromBackend = false;

    const api = (typeof ApiService !== 'undefined' ? ApiService : window.ApiService) || null;
    // 1. Intentar obtener cotizaciones desde el backend MySQL
    if (currentUser && api && typeof api.getCotizaciones === 'function') {
      try {
        const userDoc = currentUser.numero_documento || currentUser.documento || '';
        const res = await api.getCotizaciones({
          documento: userDoc,
          usuario_id: currentUser.id || null
        });
        if (res && res.success && Array.isArray(res.data)) {
          history = res.data;
          fetchedFromBackend = true;
          // Sincronizar respaldo local para que cotizaciones eliminadas por admin no reaparezcan
          try {
            localStorage.setItem('dp_mis_cotizaciones', JSON.stringify(history));
            localStorage.setItem('dp_historial_cotizaciones', JSON.stringify(history));
          } catch (e) {}
        }
      } catch (e) {
        console.warn('Fallo al obtener cotizaciones de MySQL, usando respaldo local:', e);
      }
    }

    // 2. Fallback LocalStorage solo si NO se pudo contactar al backend
    if (!fetchedFromBackend && history.length === 0) {
      const myQuotes = typeof StorageHelper !== 'undefined' ? StorageHelper.get('dp_mis_cotizaciones', []) : (function() { try { return JSON.parse(localStorage.getItem('dp_mis_cotizaciones') || '[]'); } catch(_) { return []; } })();
      const legacyQuotes = typeof StorageHelper !== 'undefined' ? StorageHelper.get('dp_historial_cotizaciones', []) : (function() { try { return JSON.parse(localStorage.getItem('dp_historial_cotizaciones') || '[]'); } catch(_) { return []; } })();
      const receivedQuotes = typeof StorageHelper !== 'undefined' ? StorageHelper.get('dp_cotizaciones_recibidas', []) : (function() { try { return JSON.parse(localStorage.getItem('dp_cotizaciones_recibidas') || '[]'); } catch(_) { return []; } })();

      const userDoc = currentUser?.numero_documento;
      const filteredReceived = userDoc ? receivedQuotes.filter(q => q.documento === userDoc || q.cliente_doc === userDoc) : receivedQuotes;

      const combined = [...myQuotes, ...legacyQuotes, ...filteredReceived];
      const seen = new Set();
      history = combined.filter(q => {
        const key = q.codigo_cotizacion || q.id;
        if (!key || seen.has(key)) return false;
        seen.add(key);
        return true;
      });
    }

    const esc = (s) => (typeof escapeHtml === 'function' ? escapeHtml(s) : String(s || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m])));

    if (history.length === 0) {
      container.innerHTML = `
        <div class="p-6 text-center text-stone-400 bg-[#FDFBF7] rounded-2xl border border-[#EAE3DA]">
          <p class="text-xs">Aún no has generado cotizaciones registradas.</p>
          <a href="catalogo.html" class="inline-block mt-2 text-xs font-semibold text-[#C85A32] hover:underline">Explorar catálogo &rarr;</a>
        </div>
      `;
      return;
    }

    container.innerHTML = history.map((cot, idx) => {
      const codigo = cot.codigo_cotizacion || cot.codigo || `COT-${idx + 1}`;
      const fecha = (cot.creado_en || cot.fecha || '').slice(0, 16).replace('T', ' ') || 'Reciente';
      const comprobante = cot.tipo_comprobante || cot.comprobante || 'Factura';
      const destino = cot.destino || cot.departamento || 'Lima';
      const estado = cot.estado || 'Pendiente';
      
      let itemsList = [];
      try {
        if (typeof cot.items === 'string') {
          itemsList = JSON.parse(cot.items);
        } else if (Array.isArray(cot.items)) {
          itemsList = cot.items;
        } else if (typeof cot.detalle_items === 'string') {
          itemsList = JSON.parse(cot.detalle_items);
        } else if (Array.isArray(cot.detalle_items)) {
          itemsList = cot.detalle_items;
        }
      } catch (err) {
        itemsList = [];
      }

      let badgeColor = 'bg-amber-100 text-amber-800';
      if (estado === 'Atendido' || estado === 'Despachado') badgeColor = 'bg-emerald-100 text-emerald-800';
      else if (estado === 'Cancelado') badgeColor = 'bg-rose-100 text-rose-800';
      else if (estado === 'En Contacto' || estado === 'Cotizado') badgeColor = 'bg-blue-100 text-blue-800';

      const itemsJsonSafe = encodeURIComponent(JSON.stringify(itemsList));
      const safeCodigo = esc(codigo);
      const safeFecha = esc(fecha);
      const safeComprobante = esc(comprobante);
      const safeDestino = esc(destino);
      const safeEstado = esc(estado);

      return `
        <div class="p-4 bg-white rounded-2xl border border-[#EAE3DA] shadow-sm mb-3">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-mono font-bold text-[#1F1815]">${safeCodigo}</span>
            <div class="flex items-center gap-2">
              <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase ${badgeColor}">${safeEstado}</span>
              <span class="text-[11px] text-[#574B46]">${safeFecha}</span>
            </div>
          </div>
          <p class="text-xs text-[#574B46] mb-2">
            <strong>Tipo:</strong> ${safeComprobante} | <strong>Destino:</strong> ${safeDestino}
          </p>
          <div class="bg-[#F4EFEA] p-2.5 rounded-xl text-xs space-y-1 mb-2.5 max-h-36 overflow-y-auto">
            ${itemsList.map(item => `
              <div class="flex justify-between text-[#1F1815]">
                <span>${esc(item.cantidad || 1)} x ${esc(item.nombre || 'Producto')}</span>
                <span class="text-stone-500 font-mono text-[11px]">${esc(item.sku || '')}</span>
              </div>
            `).join('')}
          </div>
          <button type="button" onclick="if(window.Carrito){ Carrito.items = JSON.parse(decodeURIComponent('${itemsJsonSafe}')); Carrito.openFormalQuoteModal('${safeCodigo}'); }" class="w-full py-2 px-3 rounded-xl bg-[#FDFBF7] hover:bg-[#F4EFEA] text-[#1F1815] text-xs font-bold flex items-center justify-center gap-1.5 border border-[#EAE3DA] transition-colors cursor-pointer tap-target">
            <svg class="w-3.5 h-3.5 text-[#C85A32]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span>Descargar Proforma Formal en PDF</span>
          </button>
        </div>
      `;
    }).join('');
  },

  async renderHistorialReclamaciones(user = null) {
    const container = document.getElementById('historialReclamacionesList');
    if (!container) return;

    const currentUser = user || this.getCurrentUser();
    let claims = [];

    // 1. Intentar consultar desde el backend MySQL
    if (currentUser && currentUser.numero_documento && window.ApiService) {
      try {
        const isAvailable = await ApiService.checkBackendAvailability();
        if (isAvailable) {
          const res = await fetch(`${ApiService.baseUrl}/reclamaciones.php?documento=${encodeURIComponent(currentUser.numero_documento)}`);
          if (res.ok) {
            const json = await res.json();
            if (json.success && Array.isArray(json.data) && json.data.length > 0) {
              claims = json.data;
            }
          }
        }
      } catch (e) {}
    }

    // 2. Fallback Local
    if (claims.length === 0) {
      const localClaims = typeof StorageHelper !== 'undefined' ? StorageHelper.get('dp_libro_reclamaciones', []) : (function() { try { return JSON.parse(localStorage.getItem('dp_libro_reclamaciones') || '[]'); } catch(_) { return []; } })();
      claims = currentUser?.numero_documento 
        ? localClaims.filter(c => c.numero_documento === currentUser.numero_documento)
        : localClaims;
    }

    if (claims.length === 0) {
      container.innerHTML = `
        <div class="p-6 text-center text-stone-400 bg-[#FDFBF7] rounded-2xl border border-[#EAE3DA]">
          <p class="text-xs">No tienes hojas de reclamación registradas.</p>
        </div>
      `;
      return;
    }

    const esc = (s) => (typeof escapeHtml === 'function' ? escapeHtml(s) : String(s || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m])));

    container.innerHTML = claims.map(rec => `
      <div class="p-4 bg-white rounded-2xl border border-[#EAE3DA] shadow-sm mb-3">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold text-[#C85A32] font-mono">${esc(rec.codigo_hoja)}</span>
          <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-semibold">${esc(rec.estado || 'Pendiente')}</span>
        </div>
        <p class="text-xs text-[#1F1815] font-semibold">${esc(rec.tipo_reclamacion || 'Reclamo')}: ${esc(rec.tipo_bien || 'Servicio')}</p>
        <p class="text-xs text-[#574B46] line-clamp-2 my-1">${esc(rec.detalle_reclamacion || '')}</p>
        <p class="text-[11px] text-stone-400">Fecha: ${esc((rec.creado_en || rec.fecha || 'Reciente').slice(0, 10))}</p>
      </div>
    `).join('');
  },

  showFormError(container, message) {
    if (!container) return;
    container.textContent = message;
    container.classList.remove('hidden');
    setTimeout(() => {
      container.classList.add('hidden');
    }, 5000);
  },

  initAdminPage() {
    const user = this.getCurrentUser();
    if (!user || user.rol !== 'admin') {
      sessionStorage.setItem('dp_auth_message', 'Acceso Restringido: Debe iniciar sesión con una cuenta de Administrador.');
      window.location.href = 'login.html';
      return false;
    }
    const nameEl = document.getElementById('adminNombreHeader');
    if (nameEl) nameEl.textContent = user.nombre_razon_social;
    const docEl = document.getElementById('adminDocHeader');
    if (docEl) docEl.textContent = `${user.tipo_documento}: ${user.numero_documento}`;
    return true;
  },

  toggleUserDropdown(e, menuId) {
    if (e) {
      e.stopPropagation();
      e.preventDefault();
    }
    const menu = document.getElementById(menuId);
    if (!menu) return;
    const isHidden = menu.classList.contains('hidden');
    this.closeAllDropdowns();
    if (isHidden) {
      menu.classList.remove('hidden');
    }
  },

  closeAllDropdowns() {
    document.querySelectorAll('.user-dropdown-menu').forEach(m => m.classList.add('hidden'));
  },

  togglePasswordVisibility(inputId, btnEl) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';

    if (btnEl) {
      btnEl.innerHTML = isPassword 
        ? '<svg class="w-4 h-4 text-[#C85A32]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>'
        : '<svg class="w-4 h-4 text-stone-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>';
    }
  }
};

// Cerrar dropdown al hacer clic fuera
document.addEventListener('click', (e) => {
  if (!e.target.closest('.user-dropdown-menu') && !e.target.closest('button')) {
    Auth.closeAllDropdowns();
  }
});

window.Auth = Auth;

