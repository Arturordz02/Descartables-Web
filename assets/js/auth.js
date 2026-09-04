/**
 * Módulo de Autenticación y Gestión de Perfil de Cliente
 * Soporte para Personas Naturales (DNI) y Empresas (RUC)
 */

const Auth = {
  getCurrentUser() {
    try {
      const user = localStorage.getItem('dp_usuario_activo');
      return user ? JSON.parse(user) : null;
    } catch (e) {
      return null;
    }
  },

  isLoggedIn() {
    return this.getCurrentUser() !== null;
  },

  logout() {
    localStorage.removeItem('dp_usuario_activo');
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

    navUserContainers.forEach((container, idx) => {
      if (user) {
        const shortName = user.nombre_razon_social.split(' ')[0] || 'Mi Cuenta';
        const dropdownId = `userDropdownMenu_${idx}`;
        container.innerHTML = `
          <div class="relative inline-block text-left">
            <button type="button" onclick="Auth.toggleUserDropdown(event, '${dropdownId}')" class="flex items-center gap-2 py-1.5 px-3 rounded-xl bg-[#F4EFEA] hover:bg-[#EAE3DA] text-[#1F1815] text-xs font-semibold border border-[#EAE3DA] transition-colors tap-target cursor-pointer select-none">
              <span class="w-6 h-6 rounded-full bg-[#C85A32] text-white flex items-center justify-center font-bold text-[10px]">
                ${(user.nombre_razon_social || 'C').charAt(0).toUpperCase()}
              </span>
              <span class="max-w-[120px] truncate">${shortName}</span>
              <svg class="w-3.5 h-3.5 text-stone-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            
            <!-- Dropdown Menú sin brecha de cursor y con toggle interactivo -->
            <div id="${dropdownId}" class="user-dropdown-menu absolute right-0 top-full mt-1.5 w-60 bg-white rounded-2xl shadow-2xl border border-[#EAE3DA] py-2 hidden z-50 animate-fade-in divide-y divide-stone-100">
              <div class="px-4 py-2.5 bg-gradient-to-r from-warm-sand to-warm-cream rounded-t-xl">
                <div class="flex items-center justify-between">
                  <p class="text-xs font-bold text-[#1F1815] truncate" title="${user.nombre_razon_social}">${user.nombre_razon_social}</p>
                  ${user.rol === 'admin' ? '<span class="text-[9px] px-1.5 py-0.5 rounded-full bg-[#C85A32] text-white font-black uppercase">ADMIN</span>' : ''}
                </div>
                <p class="text-[10px] text-[#C85A32] font-semibold">${user.tipo_documento}: ${user.numero_documento}</p>
                <p class="text-[10px] text-stone-500 truncate">${user.email || ''}</p>
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
        container.innerHTML = `
          <div class="p-3 bg-white rounded-2xl border border-[#EAE3DA] flex items-center justify-between mb-2">
            <div class="flex items-center gap-2.5">
              <span class="w-8 h-8 rounded-full bg-[#C85A32] text-white flex items-center justify-center font-bold text-xs">
                ${(user.nombre_razon_social || 'C').charAt(0).toUpperCase()}
              </span>
              <div class="min-w-0">
                <p class="text-xs font-bold text-[#1F1815] truncate max-w-[160px]">${user.nombre_razon_social}</p>
                <p class="text-[10px] text-[#574B46]">${user.tipo_documento}: ${user.numero_documento}</p>
              </div>
            </div>
            <a href="perfil.html" class="px-3 py-1.5 bg-[#F4EFEA] text-[#C85A32] rounded-xl text-xs font-bold hover:bg-[#EAE3DA]">
              Mi Cuenta
            </a>
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
        if (window.location.protocol === 'file:') {
          statusBadge.innerHTML = `
            <div class="text-left space-y-1">
              <div class="flex items-center gap-1.5 font-bold text-amber-900">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                <span>Modo Archivo Local (file://)</span>
              </div>
              <p class="text-[10px] text-amber-800 leading-tight">
                Para registrar en MySQL y ver los datos en phpMyAdmin, abre la web desde tu servidor XAMPP en: 
                <a href="http://localhost/descartables/login.html" class="underline font-bold text-[#C85A32]">http://localhost/descartables/login.html</a>
              </p>
            </div>
          `;
          statusBadge.className = 'p-3 rounded-2xl bg-amber-50 border border-amber-300 text-xs text-amber-900';
        } else if (isAvailable) {
          statusBadge.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500"></span><span class="text-emerald-800 font-bold">Conectado a Base de Datos MySQL (XAMPP)</span>';
          statusBadge.className = 'p-2 rounded-xl text-center text-[11px] bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-center gap-1.5';
        } else {
          statusBadge.innerHTML = '<span class="w-2 h-2 rounded-full bg-amber-500"></span><span class="text-amber-800 font-bold">Servidor MySQL no detectado. Inicie Apache y MySQL en XAMPP.</span>';
          statusBadge.className = 'p-2 rounded-xl text-center text-[11px] bg-amber-50 border border-amber-200 text-amber-800 flex items-center justify-center gap-1.5';
        }
      });
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
        if (window.location.protocol === 'file:') {
          statusBadge.innerHTML = `
            <div class="text-left space-y-1">
              <div class="flex items-center gap-1.5 font-bold text-amber-900">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                <span>Modo Archivo Local (file://)</span>
              </div>
              <p class="text-[10px] text-amber-800 leading-tight">
                Para que el registro se guarde en tu base de datos de phpMyAdmin, abre la web desde tu servidor XAMPP en: 
                <a href="http://localhost/descartables/registro.html" class="underline font-bold text-[#C85A32]">http://localhost/descartables/registro.html</a>
              </p>
            </div>
          `;
          statusBadge.className = 'p-3 rounded-2xl bg-amber-50 border border-amber-300 text-xs text-amber-900';
        } else if (isAvailable) {
          statusBadge.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500"></span><span class="text-emerald-800 font-bold">Conectado a Base de Datos MySQL (XAMPP) — Guardando en phpMyAdmin</span>';
          statusBadge.className = 'p-2 rounded-xl text-center text-[11px] bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-center gap-1.5';
        } else {
          statusBadge.innerHTML = '<span class="w-2 h-2 rounded-full bg-amber-500"></span><span class="text-amber-800 font-bold">Servidor MySQL no detectado. Inicie Apache y MySQL en XAMPP.</span>';
          statusBadge.className = 'p-2 rounded-xl text-center text-[11px] bg-amber-50 border border-amber-200 text-amber-800 flex items-center justify-center gap-1.5';
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

    // Mostrar historial de cotizaciones recientes
    this.renderHistorialCotizaciones();
    this.renderHistorialReclamaciones();
  },

  renderHistorialCotizaciones() {
    const container = document.getElementById('historialCotizacionesList');
    if (!container) return;

    const history = JSON.parse(localStorage.getItem('dp_historial_cotizaciones') || '[]');
    if (history.length === 0) {
      container.innerHTML = `
        <div class="p-6 text-center text-stone-400 bg-[#FDFBF7] rounded-2xl border border-[#EAE3DA]">
          <p class="text-xs">Aún no has generado cotizaciones en esta sesión.</p>
          <a href="catalogo.html" class="inline-block mt-2 text-xs font-semibold text-[#C85A32] hover:underline">Explorar catálogo</a>
        </div>
      `;
      return;
    }

    container.innerHTML = history.map((cot, idx) => `
      <div class="p-4 bg-white rounded-2xl border border-[#EAE3DA] shadow-sm mb-3">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold text-[#1F1815]">Cotización #${idx + 1}</span>
          <span class="text-[11px] text-[#574B46]">${cot.fecha}</span>
        </div>
        <p class="text-xs text-[#574B46] mb-2">
          <strong>Tipo:</strong> ${cot.comprobante} | <strong>Destino:</strong> ${cot.destino}
        </p>
        <div class="bg-[#F4EFEA] p-2.5 rounded-xl text-xs space-y-1 mb-2.5">
          ${cot.items.map(item => `
            <div class="flex justify-between text-[#1F1815]">
              <span>${item.cantidad} x ${item.nombre}</span>
              <span class="text-stone-500 font-mono text-[11px]">${item.sku}</span>
            </div>
          `).join('')}
        </div>
        <button type="button" onclick="Carrito.items = JSON.parse(decodeURIComponent('${encodeURIComponent(JSON.stringify(cot.items))}')); Carrito.openFormalQuoteModal();" class="w-full py-2 px-3 rounded-xl bg-[#FDFBF7] hover:bg-[#F4EFEA] text-[#1F1815] text-xs font-bold flex items-center justify-center gap-1.5 border border-[#EAE3DA] transition-colors cursor-pointer tap-target">
          <svg class="w-3.5 h-3.5 text-[#C85A32]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          <span>Descargar Proforma Formal en PDF</span>
        </button>
      </div>
    `).join('');
  },

  renderHistorialReclamaciones() {
    const container = document.getElementById('historialReclamacionesList');
    if (!container) return;

    const claims = JSON.parse(localStorage.getItem('dp_libro_reclamaciones') || '[]');
    if (claims.length === 0) {
      container.innerHTML = `
        <div class="p-6 text-center text-stone-400 bg-[#FDFBF7] rounded-2xl border border-[#EAE3DA]">
          <p class="text-xs">No tienes hojas de reclamación registradas.</p>
        </div>
      `;
      return;
    }

    container.innerHTML = claims.map(rec => `
      <div class="p-4 bg-white rounded-2xl border border-[#EAE3DA] shadow-sm mb-3">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold text-[#C85A32] font-mono">${rec.codigo_hoja}</span>
          <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-semibold">${rec.estado || 'Pendiente'}</span>
        </div>
        <p class="text-xs text-[#1F1815] font-semibold">${rec.tipo_reclamacion}: ${rec.tipo_bien}</p>
        <p class="text-xs text-[#574B46] line-clamp-2 my-1">${rec.detalle_reclamacion}</p>
        <p class="text-[11px] text-stone-400">Fecha: ${rec.fecha || 'Reciente'}</p>
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
  }
};

// Cerrar dropdown al hacer clic fuera
document.addEventListener('click', (e) => {
  if (!e.target.closest('.user-dropdown-menu') && !e.target.closest('button')) {
    Auth.closeAllDropdowns();
  }
});

window.Auth = Auth;

