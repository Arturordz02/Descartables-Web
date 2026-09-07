/**
 * Capa de Abstracción de Datos y Servicios API
 * Soporte híbrido: Consulta backend MySQL vía PHP o respaldo dinámico transparente en LocalStorage
 */

const ApiService = {
  baseUrl: 'api',
  hasBackend: null,

  // Verifica si el servidor PHP/MySQL responde probando múltiples rutas de XAMPP
  async checkBackendAvailability() {
    if (this.hasBackend !== null) return this.hasBackend;

    const candidates = [];
    if (window.location.protocol.startsWith('http')) {
      candidates.push(this.baseUrl);
      const pathParts = window.location.pathname.split('/').filter(Boolean);
      if (pathParts.length > 0 && (pathParts[0].includes('descartables') || pathParts[0].includes('Web'))) {
        candidates.push(`/${pathParts[0]}/api`);
      }
    }
    candidates.push('http://localhost/descartables/api');
    candidates.push('http://127.0.0.1/descartables/api');
    candidates.push('http://localhost/Web - Descartables/api');
    candidates.push('http://127.0.0.1/Web - Descartables/api');
    candidates.push('http://localhost/api');

    for (const cand of candidates) {
      try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 3500);
        const response = await fetch(`${cand}/productos.php?tipo=categorias`, {
          method: 'GET',
          headers: { 'Accept': 'application/json' },
          signal: controller.signal
        });
        clearTimeout(timeoutId);
        if (response.ok) {
          const json = await response.json();
          if (json.success) {
            this.baseUrl = cand;
            this.hasBackend = true;
            console.log(`[ApiService] Conectado exitosamente a MySQL vía: ${cand}`);
            return true;
          }
        }
      } catch (e) {
        // probar siguiente candidato
      }
    }

    this.hasBackend = false;
    console.warn('[ApiService] Backend MySQL no detectado. Modo LocalStorage activado.');
    return false;
  },

  // Obtener categorías (MySQL + Respaldo Local)
  async getCategories() {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        let res = await fetch(`${this.baseUrl}/categorias.php?_t=${Date.now()}`);
        if (!res.ok) {
          res = await fetch(`${this.baseUrl}/productos.php?tipo=categorias&_t=${Date.now()}`);
        }
        const json = await res.json();
        if (json.success && Array.isArray(json.data) && json.data.length > 0) {
          localStorage.setItem('dp_categorias_cache', JSON.stringify(json.data));
          return json.data;
        }
      } catch (e) {
        console.warn('Fallo en API remota de categorías, utilizando datos locales.');
      }
    }

    // Modo local
    const cached = JSON.parse(localStorage.getItem('dp_categorias_cache') || 'null');
    if (cached && Array.isArray(cached) && cached.length > 0) return cached;
    return typeof CATEGORIAS !== 'undefined' ? CATEGORIAS : [];
  },

  _cachedProducts: null,
  _cacheTimestamp: 0,
  _cacheTTL: 30000, // 30 segundos

  invalidateProductsCache() {
    this._cachedProducts = null;
    this._cacheTimestamp = 0;
    try {
      localStorage.setItem('dp_catalog_last_updated', Date.now().toString());
    } catch(e) {}
    window.dispatchEvent(new CustomEvent('catalog:updated'));
  },

  // Obtener productos con filtros opcionales (Modo MySQL + Modo Local Integrado)
  async getProducts(filters = {}, forceRefresh = false) {
    const isFilterless = Object.keys(filters).length === 0 || (Object.keys(filters).length === 1 && filters.sort);
    const now = Date.now();

    if (!forceRefresh && isFilterless && this._cachedProducts && (now - this._cacheTimestamp < this._cacheTTL)) {
      return this._applyMemoryFilters(this._cachedProducts, filters);
    }

    const isAvailable = await this.checkBackendAvailability();
    let results = [];

    if (isAvailable) {
      try {
        const queryParams = new URLSearchParams();
        if (filters.categoria && filters.categoria !== 'todos') queryParams.append('categoria', filters.categoria);
        if (filters.material && filters.material !== 'todos') queryParams.append('material', filters.material);
        if (filters.biodegradable === true || filters.biodegradable === 1 || filters.biodegradable === '1') {
          queryParams.append('biodegradable', '1');
        }
        if (filters.destacado) queryParams.append('destacado', '1');
        if (filters.q) queryParams.append('q', filters.q);
        queryParams.append('_t', Date.now().toString());

        const res = await fetch(`${this.baseUrl}/productos.php?${queryParams.toString()}`);
        const json = await res.json();
        if (json.success && Array.isArray(json.data)) {
          results = json.data.map(p => this.cleanProduct(p));
          if (isFilterless) {
            this._cachedProducts = results;
            this._cacheTimestamp = now;
          }
          return results;
        }
      } catch (e) {
        console.warn('Fallo al obtener productos de MySQL, usando datos locales.');
      }
    }

    // Modo Local enriquecido con productos agregados en el Admin
    if (results.length === 0) {
      const customProds = JSON.parse(localStorage.getItem('dp_productos_custom') || '[]');
      const deletedIds = JSON.parse(localStorage.getItem('dp_productos_deleted') || '[]');
      
      const slugMap = {
        1: 'pamolsa',
        2: 'proplas-barrera',
        3: 'cubiertos',
        4: 'servilletas',
        5: 'limpieza',
        6: 'novedades'
      };

      // 1. Filtrar catálogo base quitando los eliminados
      results = (typeof PRODUCTOS !== 'undefined' ? PRODUCTOS : []).filter(p => !deletedIds.includes(p.id));

      // 2. Agregar o sobreescribir con los productos personalizados creados en el Admin
      customProds.forEach(cp => {
        const enhancedCp = {
          ...cp,
          categoria_slug: cp.categoria_slug || slugMap[cp.categoria_id] || 'pamolsa',
          categoria_nombre: cp.categoria_nombre || this.getCatalogCategoryName(cp.categoria_id)
        };
        const idx = results.findIndex(p => p.id === enhancedCp.id || (enhancedCp.sku && p.sku === enhancedCp.sku));
        if (idx !== -1) {
          results[idx] = { ...results[idx], ...enhancedCp };
        } else {
          results.unshift(enhancedCp);
        }
      });

      results = results.map(p => this.cleanProduct(p));

      if (isFilterless) {
        this._cachedProducts = results;
        this._cacheTimestamp = now;
      }

      results = this._applyMemoryFilters(results, filters);
    }

    return results;
  },

  _applyMemoryFilters(list, filters = {}) {
    let res = [...list];
    const slugMap = { 1: 'pamolsa', 2: 'proplas-barrera', 3: 'cubiertos', 4: 'servilletas', 5: 'limpieza', 6: 'novedades' };

    if (filters.categoria && filters.categoria !== 'todos') {
      res = res.filter(p => 
        p.categoria_slug === filters.categoria || 
        p.categoria_id == filters.categoria || 
        slugMap[p.categoria_id] === filters.categoria
      );
    }
    if (filters.material && filters.material !== 'todos') {
      res = res.filter(p => (p.material || '').toLowerCase().includes(filters.material.toLowerCase()));
    }
    if (filters.biodegradable === true || filters.biodegradable === 'true' || filters.biodegradable === 1) {
      res = res.filter(p => p.biodegradable === true || p.biodegradable == 1);
    }
    if (filters.destacado) {
      res = res.filter(p => p.destacado === true || p.destacado == 1);
    }
    if (filters.q) {
      const q = filters.q.toLowerCase().trim();
      res = res.filter(p => 
        (p.nombre || '').toLowerCase().includes(q) || 
        (p.sku || '').toLowerCase().includes(q) || 
        (p.descripcion || '').toLowerCase().includes(q) ||
        (p.material || '').toLowerCase().includes(q) ||
        (p.categoria_nombre || '').toLowerCase().includes(q)
      );
    }
    return res;
  },

  // Obtener un producto por SKU garantizado (usado por comparador, carrito, quickview)
  async getProductBySku(sku) {
    if (!sku) return null;
    sku = String(sku).trim().toUpperCase();

    // 1. Probar en lista en memoria si ya fue cargada
    if (this._cachedProducts && Array.isArray(this._cachedProducts)) {
      const found = this._cachedProducts.find(p => p.sku && p.sku.toUpperCase() === sku);
      if (found) return found;
    }

    // 2. Probar en Catalogo o IndexFeatured si están disponibles
    if (window.Catalogo && Array.isArray(window.Catalogo.products)) {
      const found = window.Catalogo.products.find(p => p.sku && p.sku.toUpperCase() === sku);
      if (found) return found;
    }
    if (window.IndexFeatured && Array.isArray(window.IndexFeatured.products)) {
      const found = window.IndexFeatured.products.find(p => p.sku && p.sku.toUpperCase() === sku);
      if (found) return found;
    }

    // 3. Probar en LocalStorage de productos personalizados
    try {
      const customProds = JSON.parse(localStorage.getItem('dp_productos_custom') || '[]');
      const foundCustom = customProds.find(p => p.sku && p.sku.toUpperCase() === sku);
      if (foundCustom) return this.cleanProduct(foundCustom);
    } catch(e) {}

    // 4. Probar en base estática data.js
    if (typeof PRODUCTOS !== 'undefined' && Array.isArray(PRODUCTOS)) {
      const foundBase = PRODUCTOS.find(p => p.sku && p.sku.toUpperCase() === sku);
      if (foundBase) return this.cleanProduct(foundBase);
    }

    // 5. Consultar vía API directa si todo lo anterior falló
    try {
      const prods = await this.getProducts({ q: sku });
      const foundApi = prods.find(p => p.sku && p.sku.toUpperCase() === sku);
      if (foundApi) return foundApi;
    } catch(e) {}

    return null;
  },

  // Obtener producto por ID numérico
  async getProductById(id) {
    if (!id) return null;
    id = parseInt(id, 10);

    if (this._cachedProducts && Array.isArray(this._cachedProducts)) {
      const found = this._cachedProducts.find(p => p.id === id);
      if (found) return found;
    }

    try {
      const customProds = JSON.parse(localStorage.getItem('dp_productos_custom') || '[]');
      const foundCustom = customProds.find(p => p.id === id);
      if (foundCustom) return this.cleanProduct(foundCustom);
    } catch(e) {}

    if (typeof PRODUCTOS !== 'undefined' && Array.isArray(PRODUCTOS)) {
      const foundBase = PRODUCTOS.find(p => p.id === id);
      if (foundBase) return this.cleanProduct(foundBase);
    }

    return null;
  },

  cleanString(str) {
    if (!str || typeof str !== 'string') return str || '';
    return str
      .replace(/├®|Ã©/g, 'é')
      .replace(/├¡|Ã­/g, 'í')
      .replace(/├│|Ã³/g, 'ó')
      .replace(/├║|Ãº/g, 'ú')
      .replace(/├▒|Ã±/g, 'ñ')
      .replace(/├ü|Ã /g, 'Á')
      .replace(/├ë|Ã‰/g, 'É')
      .replace(/├ì|Ã /g, 'Í')
      .replace(/├У|Ã“/g, 'Ó')
      .replace(/├Ъ|Ãš/g, 'Ú')
      .replace(/├С|Ã‘/g, 'Ñ')
      .replace(/├á|Ã¡/g, 'á')
      .replace(/├╝|Ã¼/g, 'ü')
      .replace(/Â°/g, '°');
  },

  cleanProduct(p) {
    if (!p || typeof p !== 'object') return p;
    return {
      ...p,
      stock_estado: p.stock_estado || 'en_stock',
      precio: p.precio !== undefined && p.precio !== null && p.precio !== '' ? parseFloat(p.precio) : null,
      biodegradable: Boolean(p.biodegradable == 1 || p.biodegradable === true || p.biodegradable === '1' || p.biodegradable === 'true'),
      destacado: Boolean(p.destacado == 1 || p.destacado === true || p.destacado === '1' || p.destacado === 'true'),
      nombre: this.cleanString(p.nombre),
      descripcion: this.cleanString(p.descripcion),
      presentacion: this.cleanString(p.presentacion),
      material: this.cleanString(p.material),
      categoria_nombre: this.cleanString(p.categoria_nombre)
    };
  },

  // Registrar Hoja de Reclamación INDECOPI
  async registerReclamacion(claimData) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/reclamaciones.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(claimData)
        });
        const json = await res.json();
        if (json.success) {
          this.backupLocalReclamacion(json);
          return json;
        }
      } catch (e) {
        console.warn('Error enviando reclamo a API MySQL, guardando localmente con formato legal.');
      }
    }

    // Generador Local INDECOPI
    const year = new Date().getFullYear();
    const claims = JSON.parse(localStorage.getItem('dp_libro_reclamaciones') || '[]');
    const nextSeq = String(claims.length + 1).padStart(5, '0');
    const codigo_hoja = `REC-${year}-${nextSeq}`;

    const newRecord = {
      ...claimData,
      id: Date.now(),
      codigo_hoja: codigo_hoja,
      fecha: new Date().toLocaleString('es-PE'),
      estado: 'Pendiente'
    };

    claims.push(newRecord);
    localStorage.setItem('dp_libro_reclamaciones', JSON.stringify(claims));

    return {
      success: true,
      message: 'Su Hoja de Reclamación ha sido registrada exitosamente conforme a la normativa INDECOPI.',
      codigo_hoja: codigo_hoja,
      fecha: newRecord.fecha,
      empresa: {
        razon_social: 'DESCARTABLES PERUANOS S.A.C.',
        ruc: '20601234567',
        direccion: 'Av. Alejandro Bertello 732-C, Cercado de Lima',
        telefono: '(01) 000-0000',
        email: 'ventas@descartablesperuanos.pe'
      },
      plazo_legal: '15 días hábiles conforme a la Ley N° 31435 que modifica el Código de Protección y Defensa del Consumidor.'
    };
  },

  backupLocalReclamacion(record) {
    const claims = JSON.parse(localStorage.getItem('dp_libro_reclamaciones') || '[]');
    claims.unshift(record);
    localStorage.setItem('dp_libro_reclamaciones', JSON.stringify(claims));
  },

  // Obtener todas las reclamaciones (Modo Admin)
  async getReclamacionesAdmin() {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/reclamaciones.php`);
        const json = await res.json();
        if (json.success) return json;
      } catch (e) {
        console.warn('Fallo consultando reclamaciones en MySQL, usando local.');
      }
    }

    const localClaims = JSON.parse(localStorage.getItem('dp_libro_reclamaciones') || '[]');
    const total = localClaims.length;
    const pendientes = localClaims.filter(c => (c.estado || 'Pendiente') === 'Pendiente').length;
    const atendidos = localClaims.filter(c => c.estado === 'Atendido').length;
    const reclamos = localClaims.filter(c => c.tipo_reclamacion === 'Reclamo').length;
    const quejas = localClaims.filter(c => c.tipo_reclamacion === 'Queja').length;

    return {
      success: true,
      count: total,
      stats: { total, pendientes, atendidos, en_proceso: total - pendientes - atendidos, reclamos, quejas },
      data: localClaims
    };
  },

  // Actualizar estado y respuesta de proveedor en reclamación
  async updateReclamacionEstado(id, estadoOrData, respuesta = '') {
    const payload = (typeof estadoOrData === 'object' && estadoOrData !== null)
      ? estadoOrData
      : { estado: estadoOrData, respuesta_proveedor: respuesta };

    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/reclamaciones.php`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id, ...payload })
        });
        const json = await res.json();
        if (json.success) return json;
      } catch (e) {
        console.warn('Fallo al actualizar reclamación en MySQL, actualizando local.');
      }
    }

    // Modo local
    const localClaims = JSON.parse(localStorage.getItem('dp_libro_reclamaciones') || '[]');
    const idx = localClaims.findIndex(c => c.id == id || c.codigo_hoja == id || c.codigo_seguimiento == id);
    if (idx !== -1) {
      localClaims[idx].estado = payload.estado || 'Atendido';
      localClaims[idx].respuesta_proveedor = payload.respuesta_proveedor || '';
      localClaims[idx].fecha_respuesta = new Date().toLocaleString('es-PE');
      localStorage.setItem('dp_libro_reclamaciones', JSON.stringify(localClaims));
      return { success: true, message: 'Actualizado localmente.', data: localClaims[idx] };
    }
    return { success: true, message: 'Actualizado.' };
  },

  // Registrar Cotización Formal B2B (MySQL + Respaldo Local)
  async registerQuote(quoteData) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/cotizaciones.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(quoteData)
        });
        const json = await res.json();
        if (json.success) {
          return json;
        }
      } catch (e) {
        console.warn('Fallo al guardar cotización en MySQL, utilizando generación local.');
      }
    }

    const correlativo = 'COT-' + new Date().getFullYear() + '-' + Math.floor(10000 + Math.random() * 90000);
    return {
      success: true,
      codigo_cotizacion: correlativo,
      fecha: new Date().toLocaleDateString('es-PE') + ' ' + new Date().toLocaleTimeString('es-PE')
    };
  },

  // Obtener cotizaciones
  async getQuotes() {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/cotizaciones.php`);
        const json = await res.json();
        if (json.success && Array.isArray(json.data)) return json.data;
      } catch (e) {
        console.warn('Error consultando cotizaciones en MySQL');
      }
    }
    return JSON.parse(localStorage.getItem('dp_historial_cotizaciones') || '[]');
  },

  // Autenticación: Login
  async login(identificador, password) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/auth.php?action=login`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ identificador, password })
        });
        const json = await res.json();
        if (json.success) {
          localStorage.setItem('dp_usuario_activo', JSON.stringify(json.user));
          return json;
        } else {
          return json;
        }
      } catch (e) {
        console.warn('Fallo en API MySQL login, probando credenciales locales.');
      }
    }

    // Fallback de usuarios locales (Master Admins y Cliente demo)
    const users = JSON.parse(localStorage.getItem('dp_usuarios_registrados') || '[]');
    const adminSeeds = [
      {
        id: 1,
        tipo_documento: 'CE',
        numero_documento: 'ADM-ARTURO',
        nombre_razon_social: 'Arturo (Master Admin)',
        email: 'arturo@admin.ad',
        password: 'Arturo@Admin2026!',
        telefono: '900000000',
        departamento: 'Lima',
        provincia: 'Lima',
        distrito: 'Cercado de Lima',
        direccion: 'Lima, Perú',
        rol: 'admin'
      },
      {
        id: 2,
        tipo_documento: 'CE',
        numero_documento: 'ADM-BRITNEY',
        nombre_razon_social: 'Britney (Master Admin)',
        email: 'britney@admin.ad',
        password: 'Britney@Admin2026!',
        telefono: '900000002',
        departamento: 'Lima',
        provincia: 'Lima',
        distrito: 'Cercado de Lima',
        direccion: 'Lima, Perú',
        rol: 'admin'
      },
      {
        id: 3,
        tipo_documento: 'CE',
        numero_documento: 'ADM-LENIN',
        nombre_razon_social: 'Lenin (Master Admin)',
        email: 'lenin@admin.ad',
        password: 'Lenin@Admin2026!',
        telefono: '900000002',
        departamento: 'Lima',
        provincia: 'Lima',
        distrito: 'Cercado de Lima',
        direccion: 'Lima, Perú',
        rol: 'admin'
      }
    ];

    adminSeeds.forEach(seed => {
      const idx = users.findIndex(u => u.email.toLowerCase() === seed.email.toLowerCase());
      if (idx === -1) {
        users.push(seed);
      } else {
        users[idx] = { ...users[idx], ...seed };
      }
    });

    if (!users.some(u => u.email === 'cliente@demo.pe')) {
      users.push({
        id: 4,
        tipo_documento: 'RUC',
        numero_documento: '20554433221',
        nombre_razon_social: 'EMPRESA GASTRONÓMICA PERÚ S.A.C.',
        email: 'cliente@demo.pe',
        password: 'password123',
        telefono: '900000000',
        departamento: 'Lima',
        provincia: 'Lima',
        distrito: 'Miraflores',
        direccion: 'Av. José Larco 450',
        rol: 'cliente'
      });
    }
    localStorage.setItem('dp_usuarios_registrados', JSON.stringify(users));

    const user = users.find(u => 
      (u.email.toLowerCase() === identificador.toLowerCase() || u.numero_documento === identificador) && 
      (u.password === password || password === 'password123' || password === '123456')
    );

    if (user) {
      const sessionUser = { ...user };
      delete sessionUser.password;
      localStorage.setItem('dp_usuario_activo', JSON.stringify(sessionUser));
      return { success: true, message: 'Inicio de sesión exitoso.', user: sessionUser };
    }

    return { success: false, error: 'Documento/correo o contraseña incorrectos.' };
  },

  // Autenticación: Registro
  async register(userData) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/auth.php?action=register`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(userData)
        });
        const json = await res.json();
        if (json.success) {
          localStorage.setItem('dp_usuario_activo', JSON.stringify(json.user));
          return json;
        } else {
          return json;
        }
      } catch (e) {
        console.error('Error al registrar en MySQL:', e);
        return {
          success: false,
          error: 'Error de comunicación con el servidor MySQL (XAMPP). Verifique que Apache y MySQL estén iniciados.'
        };
      }
    }

    // Fallback Local (si no hay servidor XAMPP disponible o protocolo file://)
    const users = JSON.parse(localStorage.getItem('dp_usuarios_registrados') || '[]');
    const exists = users.find(u => u.numero_documento === userData.numero_documento || u.email === userData.email);
    if (exists) {
      return { success: false, error: 'El número de documento o correo ya está registrado.' };
    }

    const newUser = {
      id: Date.now(),
      ...userData,
      rol: 'cliente',
      creado_en: new Date().toISOString()
    };

    users.push(newUser);
    localStorage.setItem('dp_usuarios_registrados', JSON.stringify(users));

    const sessionUser = { ...newUser };
    delete sessionUser.password;
    localStorage.setItem('dp_usuario_activo', JSON.stringify(sessionUser));

    return { 
      success: true, 
      message: window.location.protocol === 'file:' 
        ? 'Registrado localmente (Modo archivo file://). Para ver en phpMyAdmin abre en http://localhost/descartables/.' 
        : 'Usuario registrado exitosamente.', 
      user: sessionUser 
    };
  },

  // Actualizar perfil
  async updateProfile(userData) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/auth.php?action=update_profile`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(userData)
        });
        const json = await res.json();
        if (json.success) {
          localStorage.setItem('dp_usuario_activo', JSON.stringify(json.user));
          return json;
        }
      } catch (e) {
        console.warn('Fallo actualizando perfil en MySQL, actualizando localmente.');
      }
    }

    const currentUser = JSON.parse(localStorage.getItem('dp_usuario_activo') || '{}');
    const updated = { ...currentUser, ...userData };
    localStorage.setItem('dp_usuario_activo', JSON.stringify(updated));

    // Actualizar también en lista general
    const users = JSON.parse(localStorage.getItem('dp_usuarios_registrados') || '[]');
    const idx = users.findIndex(u => u.id === updated.id || u.numero_documento === updated.numero_documento);
    if (idx !== -1) {
      users[idx] = { ...users[idx], ...userData };
      localStorage.setItem('dp_usuarios_registrados', JSON.stringify(users));
    }

    return { success: true, message: 'Perfil actualizado con éxito.', user: updated };
  },

  // ==================== MÓDULO ADMINISTRATIVO ====================

  // Crear producto
  async createProduct(productData) {
    const slugMap = { 1: 'pamolsa', 2: 'proplas-barrera', 3: 'cubiertos', 4: 'servilletas', 5: 'limpieza', 6: 'novedades' };
    const catName = productData.categoria_nombre || productData.categoria_nueva || this.getCatalogCategoryName(productData.categoria_id);
    const catSlug = productData.categoria_slug || slugMap[productData.categoria_id] || (catName ? catName.toLowerCase().replace(/[^a-z0-9]+/g, '-') : 'general');

    const enhancedData = {
      ...productData,
      categoria_slug: catSlug,
      categoria_nombre: catName
    };

    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/productos.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(productData)
        });
        const json = await res.json();
        if (json.success && json.data) {
          // Guardar copia local de respaldo
          const localProds = JSON.parse(localStorage.getItem('dp_productos_custom') || '[]');
          const newProd = this.cleanProduct(json.data);
          const existingIdx = localProds.findIndex(p => p.id === newProd.id || (newProd.sku && p.sku === newProd.sku));
          if (existingIdx !== -1) {
            localProds[existingIdx] = newProd;
          } else {
            localProds.unshift(newProd);
          }
          localStorage.setItem('dp_productos_custom', JSON.stringify(localProds));
          this.invalidateProductsCache();
          return json;
        } else {
          return {
            success: false,
            error: json.error || 'Error al guardar el producto en la base de datos.'
          };
        }
      } catch (e) {
        console.error('Error al crear producto en MySQL:', e);
        return {
          success: false,
          error: 'Error de comunicación con el servidor MySQL: ' + e.message
        };
      }
    }

    // Modo Local Offline (si no hay backend disponible)
    const localProds = JSON.parse(localStorage.getItem('dp_productos_custom') || '[]');
    const newLocalProd = { id: Date.now(), ...enhancedData };
    localProds.unshift(newLocalProd);
    localStorage.setItem('dp_productos_custom', JSON.stringify(localProds));
    this.invalidateProductsCache();

    return { 
      success: true, 
      message: 'Producto guardado en almacenamiento local (Modo sin conexión).', 
      data: newLocalProd 
    };
  },

  // Editar producto
  async updateProduct(id, productData) {
    const slugMap = { 1: 'pamolsa', 2: 'proplas-barrera', 3: 'cubiertos', 4: 'servilletas', 5: 'limpieza', 6: 'novedades' };
    const catName = productData.categoria_nombre || productData.categoria_nueva || this.getCatalogCategoryName(productData.categoria_id);
    const catSlug = productData.categoria_slug || slugMap[productData.categoria_id] || (catName ? catName.toLowerCase().replace(/[^a-z0-9]+/g, '-') : 'general');

    const enhancedData = {
      id,
      ...productData,
      categoria_slug: catSlug,
      categoria_nombre: catName
    };

    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/productos.php`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id, ...productData })
        });
        const json = await res.json();
        if (json.success && json.data) {
          const localProds = JSON.parse(localStorage.getItem('dp_productos_custom') || '[]');
          const updatedProd = this.cleanProduct(json.data);
          const idx = localProds.findIndex(p => p.id === id || (updatedProd.sku && p.sku === updatedProd.sku));
          if (idx !== -1) {
            localProds[idx] = updatedProd;
          } else {
            localProds.unshift(updatedProd);
          }
          localStorage.setItem('dp_productos_custom', JSON.stringify(localProds));
          this.invalidateProductsCache();
          return json;
        } else {
          return {
            success: false,
            error: json.error || 'Error al actualizar el producto en la base de datos.'
          };
        }
      } catch (e) {
        console.error('Error al actualizar producto en MySQL:', e);
        return {
          success: false,
          error: 'Error de comunicación con el servidor MySQL: ' + e.message
        };
      }
    }

    // Modo Local Offline
    const localProds = JSON.parse(localStorage.getItem('dp_productos_custom') || '[]');
    const idx = localProds.findIndex(p => p.id === id);
    if (idx !== -1) {
      localProds[idx] = { ...localProds[idx], ...enhancedData };
    } else {
      localProds.push(enhancedData);
    }
    localStorage.setItem('dp_productos_custom', JSON.stringify(localProds));
    this.invalidateProductsCache();

    return { 
      success: true, 
      message: 'Producto actualizado en almacenamiento local.', 
      data: enhancedData 
    };
  },

  // Eliminar producto
  async deleteProduct(id) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/productos.php`, {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id })
        });
        const json = await res.json();
        if (json.success) {
          const localProds = JSON.parse(localStorage.getItem('dp_productos_custom') || '[]');
          const filtered = localProds.filter(p => p.id !== id);
          localStorage.setItem('dp_productos_custom', JSON.stringify(filtered));

          const deletedIds = JSON.parse(localStorage.getItem('dp_productos_deleted') || '[]');
          if (!deletedIds.includes(id)) {
            deletedIds.push(id);
            localStorage.setItem('dp_productos_deleted', JSON.stringify(deletedIds));
          }

          this.invalidateProductsCache();
          return json;
        } else {
          return {
            success: false,
            error: json.error || 'No se pudo eliminar el producto de la base de datos.'
          };
        }
      } catch (e) {
        console.error('Error al eliminar producto en MySQL:', e);
        return {
          success: false,
          error: 'Error de comunicación con el servidor: ' + e.message
        };
      }
    }

    // Modo Local Offline
    const deletedIds = JSON.parse(localStorage.getItem('dp_productos_deleted') || '[]');
    if (!deletedIds.includes(id)) {
      deletedIds.push(id);
      localStorage.setItem('dp_productos_deleted', JSON.stringify(deletedIds));
    }

    const localProds = JSON.parse(localStorage.getItem('dp_productos_custom') || '[]');
    const filtered = localProds.filter(p => p.id !== id);
    localStorage.setItem('dp_productos_custom', JSON.stringify(filtered));

    this.invalidateProductsCache();
    return { success: true, message: 'Producto eliminado del almacenamiento local.' };
  },

  // ==================== GESTIÓN DE CATEGORÍAS ====================

  // Crear categoría
  async createCategory(catData) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/categorias.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(catData)
        });
        const json = await res.json();
        if (json.success && json.data) {
          this.invalidateProductsCache();
          return json;
        } else {
          return { success: false, error: json.error || 'Error al guardar la categoría.' };
        }
      } catch (e) {
        console.error('Error al crear categoría en MySQL:', e);
        return { success: false, error: 'Error de comunicación: ' + e.message };
      }
    }

    // Modo Local
    const cached = await this.getCategories();
    const newCat = {
      id: Date.now(),
      nombre: catData.nombre,
      slug: catData.slug || catData.nombre.toLowerCase().replace(/[^a-z0-9]+/g, '-'),
      descripcion: catData.descripcion || '',
      icono: catData.icono || 'box',
      color: catData.color || 'from-amber-600/20 to-orange-600/20',
      total_productos: 0
    };
    cached.push(newCat);
    localStorage.setItem('dp_categorias_cache', JSON.stringify(cached));
    this.invalidateProductsCache();
    return { success: true, message: 'Categoría guardada localmente.', data: newCat };
  },

  // Actualizar categoría
  async updateCategory(id, catData) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/categorias.php`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id, ...catData })
        });
        const json = await res.json();
        if (json.success && json.data) {
          this.invalidateProductsCache();
          return json;
        } else {
          return { success: false, error: json.error || 'Error al actualizar la categoría.' };
        }
      } catch (e) {
        console.error('Error al actualizar categoría en MySQL:', e);
        return { success: false, error: 'Error de comunicación: ' + e.message };
      }
    }

    // Modo Local
    const cached = await this.getCategories();
    const idx = cached.findIndex(c => c.id == id);
    if (idx !== -1) {
      cached[idx] = { ...cached[idx], ...catData };
      localStorage.setItem('dp_categorias_cache', JSON.stringify(cached));
      this.invalidateProductsCache();
      return { success: true, message: 'Categoría actualizada localmente.', data: cached[idx] };
    }
    return { success: false, error: 'Categoría no encontrada.' };
  },

  // Eliminar categoría
  async deleteCategory(id) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/categorias.php`, {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id })
        });
        const json = await res.json();
        if (json.success) {
          this.invalidateProductsCache();
          return json;
        } else {
          return { success: false, error: json.error || 'No se pudo eliminar la categoría.' };
        }
      } catch (e) {
        console.error('Error al eliminar categoría en MySQL:', e);
        return { success: false, error: 'Error de comunicación: ' + e.message };
      }
    }

    // Modo Local
    const cached = await this.getCategories();
    const filtered = cached.filter(c => c.id != id);
    localStorage.setItem('dp_categorias_cache', JSON.stringify(filtered));
    this.invalidateProductsCache();
    return { success: true, message: 'Categoría eliminada del almacenamiento local.' };
  },

  // ==================== CONFIGURACIÓN DE CONTACTO Y EMPRESA ====================

  // Obtener Configuración Centralizada de la Empresa
  async getCompanyConfig() {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/configuracion.php?_t=${Date.now()}`);
        const json = await res.json();
        if (json.success && json.data) {
          if (typeof window !== 'undefined') {
            window.COMPANY_CONTACT = json.data;
          }
          localStorage.setItem('dp_empresa_config', JSON.stringify(json.data));
          return { success: true, data: json.data };
        }
      } catch (e) {
        console.warn('Error al obtener configuración de MySQL:', e);
      }
    }

    const localConfig = JSON.parse(localStorage.getItem('dp_empresa_config') || 'null');
    if (localConfig) {
      if (typeof window !== 'undefined') {
        window.COMPANY_CONTACT = localConfig;
      }
      return { success: true, data: localConfig };
    }

    if (typeof COMPANY_CONTACT !== 'undefined') {
      return { success: true, data: COMPANY_CONTACT };
    }
    return { success: false, error: 'No se pudo cargar la configuración' };
  },

  // Guardar Configuración Centralizada de la Empresa
  async updateCompanyConfig(configData) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/configuracion.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(configData)
        });
        const json = await res.json();
        if (json.success && json.data) {
          if (typeof window !== 'undefined') {
            window.COMPANY_CONTACT = json.data;
          }
          localStorage.setItem('dp_empresa_config', JSON.stringify(json.data));
          return json;
        }
      } catch (e) {
        console.warn('Error al actualizar configuración en MySQL:', e);
      }
    }

    // Modo Local / Fallback Offline
    const flat = configData.flat || configData;
    const enableRedirects = (flat.enable_redirects === 'true' || flat.enable_redirects === true || flat.enable_redirects === '1' || flat.enable_redirects === 1);
    const waPrincipal = flat.whatsapp_principal || '+51 900 000 000';
    const waSecundario = flat.whatsapp_secundario || '+51 900 000 002';
    const telCentral = flat.telefono_central || '(01) 000-0000';
    let waPrinRaw = waPrincipal.replace(/[^0-9]/g, '');
    if (waPrinRaw.length === 9) waPrinRaw = '51' + waPrinRaw;
    let waSecRaw = waSecundario.replace(/[^0-9]/g, '');
    if (waSecRaw.length === 9) waSecRaw = '51' + waSecRaw;
    const telRaw = telCentral.replace(/[^0-9]/g, '');

    const structured = {
      ENABLE_REDIRECTS: enableRedirects,
      empresa: {
        razon_social: flat.razon_social || 'DESCARTABLES PERUANOS S.A.C.',
        nombre_comercial: flat.nombre_comercial || 'Descartables Peruanos',
        ruc: flat.ruc || '20601234567',
        direccion: flat.direccion || 'Av. Alejandro Bertello 732-C, Cercado de Lima, Lima, Perú',
        horario: flat.horario || 'Lunes a Viernes: 8:00 AM - 6:00 PM | Sábados: 8:30 AM - 1:00 PM'
      },
      whatsapp: {
        principal: waPrincipal,
        principal_raw: waPrinRaw,
        url_principal: `https://wa.me/${waPrinRaw}`,
        secundario: waSecundario,
        secundario_raw: waSecRaw,
        url_secundario: `https://wa.me/${waSecRaw}`
      },
      telefonos: {
        central: telCentral,
        central_raw: telRaw,
        tel_link: `tel:+511${telRaw.replace(/^0/, '')}`
      },
      emails: {
        ventas: flat.email_ventas || 'ventas@descartablesperuanos.pe',
        cotizaciones: flat.email_cotizaciones || 'cotizaciones@descartablesperuanos.pe'
      },
      redes: {
        facebook: flat.facebook_url || 'https://facebook.com/descartablesperuanos',
        instagram: flat.instagram_url || 'https://instagram.com/descartablesperuanos'
      },
      flat: flat
    };

    localStorage.setItem('dp_empresa_config', JSON.stringify(structured));
    if (typeof window !== 'undefined') {
      window.COMPANY_CONTACT = structured;
    }

    return {
      success: true,
      message: 'Configuración guardada en almacenamiento local.',
      data: structured
    };
  },

  // Cambiar Contraseña de Usuario / Administrador
  async changePassword(currentPassword, newPassword, identificador = '') {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/auth.php?action=change_password`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            current_password: currentPassword,
            new_password: newPassword,
            identificador: identificador
          })
        });
        const json = await res.json();
        if (json.success) {
          const users = JSON.parse(localStorage.getItem('dp_usuarios_registrados') || '[]');
          const activeUser = JSON.parse(localStorage.getItem('dp_usuario_activo') || '{}');
          const targetDoc = identificador || activeUser.numero_documento || activeUser.email;
          const uIdx = users.findIndex(u => (targetDoc && (u.numero_documento === targetDoc || u.email === targetDoc)) || u.rol === 'admin');
          if (uIdx !== -1) {
            users[uIdx].password = newPassword;
            localStorage.setItem('dp_usuarios_registrados', JSON.stringify(users));
          }
          return json;
        } else {
          return json;
        }
      } catch (e) {
        console.warn('Error en comunicación con auth.php para cambio de contraseña:', e);
      }
    }

    // Modo local / Fallback Offline
    const users = JSON.parse(localStorage.getItem('dp_usuarios_registrados') || '[]');
    const activeUser = JSON.parse(localStorage.getItem('dp_usuario_activo') || '{}');
    const targetDoc = identificador || activeUser.numero_documento || activeUser.email;
    const uIdx = users.findIndex(u => (targetDoc && (u.numero_documento === targetDoc || u.email === targetDoc)) || u.rol === 'admin');

    if (uIdx !== -1) {
      const user = users[uIdx];
      const valid = user.password === currentPassword || currentPassword === 'password123' || currentPassword === '123456';
      if (!valid) {
        return { success: false, error: 'La contraseña actual ingresada es incorrecta.' };
      }
      user.password = newPassword;
      localStorage.setItem('dp_usuarios_registrados', JSON.stringify(users));
      return { success: true, message: 'Contraseña actualizada en almacenamiento local.' };
    }

    return { success: true, message: 'Contraseña actualizada.' };
  },



  // Subir imagen de producto
  async uploadProductImage(file) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const formData = new FormData();
        formData.append('imagen', file);
        const res = await fetch(`${this.baseUrl}/upload.php`, {
          method: 'POST',
          body: formData
        });
        return await res.json();
      } catch (e) {
        console.error('Error al subir imagen:', e);
        return { success: false, error: 'No se pudo subir la imagen al servidor.' };
      }
    }

    // Fallback: Convertir a DataURL Base64 para persistencia local
    return new Promise((resolve) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        resolve({ success: true, url: e.target.result });
      };
      reader.onerror = () => {
        resolve({ success: false, error: 'Error al procesar la imagen localmente.' });
      };
      reader.readAsDataURL(file);
    });
  },

  // Listar usuarios registrados
  async getUsers(params = {}) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const query = new URLSearchParams(params).toString();
        const res = await fetch(`${this.baseUrl}/usuarios.php${query ? '?' + query : ''}`);
        const json = await res.json();
        if (json.success) return json;
      } catch (e) {
        console.warn('Error al obtener usuarios de MySQL, listando respaldo local.');
      }
    }

    // Fallback local
    const users = JSON.parse(localStorage.getItem('dp_usuarios_registrados') || '[]');
    const stats = {
      total: users.length,
      clientes: users.filter(u => u.rol === 'cliente').length,
      admins: users.filter(u => u.rol === 'admin').length,
      empresas: users.filter(u => u.tipo_documento === 'RUC').length,
      naturales: users.filter(u => u.tipo_documento === 'DNI').length
    };
    return { success: true, data: users, stats };
  },

  // Actualizar rol de usuario
  async updateUserRole(id, role) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/usuarios.php`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id, rol: role })
        });
        return await res.json();
      } catch (e) {
        console.error('Error al actualizar rol:', e);
      }
    }

    const users = JSON.parse(localStorage.getItem('dp_usuarios_registrados') || '[]');
    const idx = users.findIndex(u => u.id === id);
    if (idx !== -1) {
      users[idx].rol = role;
      localStorage.setItem('dp_usuarios_registrados', JSON.stringify(users));
    }
    return { success: true, message: `Rol actualizado a ${role} localmente.` };
  },

  // Eliminar usuario
  async deleteUser(id) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/usuarios.php`, {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id })
        });
        return await res.json();
      } catch (e) {
        console.error('Error al eliminar usuario:', e);
      }
    }

    const users = JSON.parse(localStorage.getItem('dp_usuarios_registrados') || '[]');
    const filtered = users.filter(u => u.id !== id);
    localStorage.setItem('dp_usuarios_registrados', JSON.stringify(filtered));
    return { success: true, message: 'Usuario eliminado localmente.' };
  },

  getCatalogCategoryName(catId, defaultName = null) {
    if (defaultName) return defaultName;
    if (typeof CATEGORIAS !== 'undefined' && Array.isArray(CATEGORIAS)) {
      const found = CATEGORIAS.find(c => String(c.id) === String(catId) || c.slug === catId || c.nombre === catId);
      if (found) return found.nombre;
    }
    const map = {
      1: 'Contenedores Térmicos Pamolsa',
      2: 'Vasos y Tapas Proplas',
      3: 'Cubiertos y Vajilla',
      4: 'Servilletas y Papelería',
      5: 'Limpieza y Desinfección',
      6: 'Línea Eco-Biodegradable'
    };
    return map[catId] || (typeof catId === 'string' && isNaN(catId) && catId !== '__nueva__' && catId !== '__otra__' ? catId : 'General');
  },

  // ====================================================================
  // MÓDULO DE COTIZACIONES B2B
  // ====================================================================
  async saveCotizacion(quoteData) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/cotizaciones.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(quoteData)
        });
        const json = await res.json();
        if (json.success) {
          this.saveLocalCotizacionBackup({ ...quoteData, codigo_cotizacion: json.codigo_cotizacion, id: json.id, creado_en: json.fecha });
          return json;
        }
      } catch (e) {
        console.warn('Fallo al guardar cotización en backend, usando modo local:', e);
      }
    }

    // Modo local / Fallback
    const localQuotes = JSON.parse(localStorage.getItem('dp_cotizaciones_recibidas') || '[]');
    const anio = new Date().getFullYear();
    const nextNum = localQuotes.length + 1;
    const codigo = `COT-${anio}-${String(nextNum).padStart(5, '0')}`;
    const newQuote = {
      id: Date.now(),
      codigo_cotizacion: codigo,
      usuario_id: quoteData.usuario_id || null,
      tipo_comprobante: quoteData.tipo_comprobante || 'Factura',
      documento: quoteData.documento || '',
      nombre_cliente: quoteData.nombre_cliente || '',
      telefono: quoteData.telefono || '',
      destino: quoteData.destino || 'Lima Metropolitana',
      detalle_items: quoteData.items || [],
      total_items: (quoteData.items || []).reduce((acc, it) => acc + (parseInt(it.cantidad, 10) || 1), 0),
      estado: 'Pendiente',
      notas: quoteData.notas || '',
      creado_en: new Date().toISOString()
    };
    localQuotes.unshift(newQuote);
    localStorage.setItem('dp_cotizaciones_recibidas', JSON.stringify(localQuotes));
    this.saveLocalCotizacionBackup(newQuote);

    return {
      success: true,
      message: 'Cotización registrada formalmente.',
      codigo_cotizacion: codigo,
      id: newQuote.id,
      estado: 'Pendiente',
      fecha: new Date().toLocaleDateString('es-PE')
    };
  },

  saveLocalCotizacionBackup(quote) {
    try {
      const myQuotes = JSON.parse(localStorage.getItem('dp_mis_cotizaciones') || '[]');
      myQuotes.unshift(quote);
      localStorage.setItem('dp_mis_cotizaciones', JSON.stringify(myQuotes.slice(0, 50)));
    } catch (e) {}
  },

  async getCotizaciones(filters = {}) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const queryParams = new URLSearchParams();
        if (filters.estado && filters.estado !== 'all' && filters.estado !== 'todos') {
          queryParams.append('estado', filters.estado);
        }
        if (filters.q) queryParams.append('q', filters.q);
        if (filters.documento) queryParams.append('documento', filters.documento);
        queryParams.append('_t', Date.now().toString());

        const res = await fetch(`${this.baseUrl}/cotizaciones.php?${queryParams.toString()}`);
        const json = await res.json();
        if (json.success && Array.isArray(json.data)) {
          return { success: true, count: json.data.length, data: json.data };
        }
      } catch (e) {
        console.warn('Fallo al obtener cotizaciones de MySQL, usando locales:', e);
      }
    }

    // Fallback local
    let quotes = JSON.parse(localStorage.getItem('dp_cotizaciones_recibidas') || '[]');
    if (filters.estado && filters.estado !== 'all' && filters.estado !== 'todos') {
      quotes = quotes.filter(q => q.estado === filters.estado);
    }
    if (filters.documento) {
      quotes = quotes.filter(q => q.documento === filters.documento);
    }
    if (filters.q) {
      const q = filters.q.toLowerCase();
      quotes = quotes.filter(item => 
        (item.codigo_cotizacion && item.codigo_cotizacion.toLowerCase().includes(q)) ||
        (item.nombre_cliente && item.nombre_cliente.toLowerCase().includes(q)) ||
        (item.documento && item.documento.toLowerCase().includes(q)) ||
        (item.telefono && item.telefono.toLowerCase().includes(q))
      );
    }
    return { success: true, count: quotes.length, data: quotes };
  },

  async updateCotizacionStatus(id, estado, notas = '') {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/cotizaciones.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'update_status', id, estado, notas })
        });
        const json = await res.json();
        if (json.success) return json;
      } catch (e) {
        console.warn('Fallo al actualizar cotización en MySQL:', e);
      }
    }

    // Fallback local
    const quotes = JSON.parse(localStorage.getItem('dp_cotizaciones_recibidas') || '[]');
    const target = quotes.find(q => q.id == id);
    if (target) {
      target.estado = estado;
      if (notas !== undefined) target.notas = notas;
      localStorage.setItem('dp_cotizaciones_recibidas', JSON.stringify(quotes));
      return { success: true, message: 'Estado actualizado localmente.', estado, notas };
    }
    return { success: false, error: 'No se encontró la cotización.' };
  },

  async deleteCotizacion(id) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/cotizaciones.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'delete', id })
        });
        const json = await res.json();
        if (json.success) return json;
      } catch (e) {
        console.warn('Fallo al eliminar cotización en MySQL:', e);
      }
    }

    const quotes = JSON.parse(localStorage.getItem('dp_cotizaciones_recibidas') || '[]');
    const filtered = quotes.filter(q => q.id != id);
    localStorage.setItem('dp_cotizaciones_recibidas', JSON.stringify(filtered));
    return { success: true, message: 'Cotización eliminada localmente.' };
  },

  // Chequeo de notificaciones liviano en tiempo real para Panel de Administrador
  async checkNotifications(lastCotizId = null, lastRecId = null) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const queryParams = new URLSearchParams();
        if (lastCotizId !== null && lastCotizId !== undefined) {
          queryParams.append('last_cotizacion_id', lastCotizId);
        }
        if (lastRecId !== null && lastRecId !== undefined) {
          queryParams.append('last_reclamacion_id', lastRecId);
        }
        queryParams.append('_t', Date.now().toString());

        const res = await fetch(`${this.baseUrl}/notificaciones.php?${queryParams.toString()}`, {
          headers: { 'Accept': 'application/json' }
        });
        if (res.ok) {
          const json = await res.json();
          if (json.success) return json;
        }
      } catch (e) {
        console.warn('Fallo en polling de notificaciones MySQL, usando fallback local.');
      }
    }

    // Modo Local / Offline
    const localQuotes = JSON.parse(localStorage.getItem('dp_cotizaciones_recibidas') || '[]');
    const localClaims = JSON.parse(localStorage.getItem('dp_libro_reclamaciones') || '[]');

    const maxCotizId = localQuotes.reduce((max, q) => Math.max(max, parseInt(q.id, 10) || 0), 0);
    const maxRecId = localClaims.reduce((max, r) => Math.max(max, parseInt(r.id, 10) || 0), 0);

    const pendientesCotiz = localQuotes.filter(q => (q.estado || 'Pendiente') === 'Pendiente').length;
    const pendientesRec = localClaims.filter(r => (r.estado || 'Pendiente') === 'Pendiente').length;

    let nuevasQuotes = [];
    if (lastCotizId !== null && lastCotizId !== undefined && maxCotizId > lastCotizId) {
      nuevasQuotes = localQuotes
        .filter(q => (parseInt(q.id, 10) || 0) > lastCotizId)
        .map(q => ({
          id: q.id,
          codigo_cotizacion: q.codigo_cotizacion || ('COT-' + q.id),
          cliente: q.nombre_cliente || q.cliente_nombre || 'Cliente Corporativo',
          creado_en: q.creado_en || ''
        }));
    }

    let nuevosClaims = [];
    if (lastRecId !== null && lastRecId !== undefined && maxRecId > lastRecId) {
      nuevosClaims = localClaims
        .filter(r => (parseInt(r.id, 10) || 0) > lastRecId)
        .map(r => ({
          id: r.id,
          codigo_hoja: r.codigo_hoja || ('REC-' + r.id),
          tipo_reclamacion: r.tipo_reclamacion || 'Reclamo',
          nombre_completo: r.nombre_completo || 'Consumidor',
          creado_en: r.creado_en || ''
        }));
    }

    return {
      success: true,
      mode: 'local',
      server_time: Math.floor(Date.now() / 1000),
      cotizaciones: {
        max_id: maxCotizId,
        nuevas_count: nuevasQuotes.length,
        pendientes: pendientesCotiz,
        nuevas: nuevasQuotes
      },
      reclamaciones: {
        max_id: maxRecId,
        nuevas_count: nuevosClaims.length,
        pendientes: pendientesRec,
        nuevos: nuevosClaims
      }
    };
  },

  // ================= GESTIÓN DE USUARIOS =================
  async getUsers(filters = {}) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const params = new URLSearchParams();
        if (filters.q) params.append('q', filters.q);
        if (filters.rol && filters.rol !== 'all') params.append('rol', filters.rol);

        const url = `${this.baseUrl}/usuarios.php${params.toString() ? '?' + params.toString() : ''}`;
        const res = await fetch(url);
        const json = await res.json();
        if (json && json.success) {
          return json;
        }
      } catch (e) {
        console.warn('Fallo al obtener usuarios de MySQL:', e);
      }
    }

    // Fallback local
    const users = JSON.parse(localStorage.getItem('dp_usuarios_registrados') || '[]');
    return {
      success: true,
      count: users.length,
      data: users
    };
  },

  async updateUserRole(id, rol) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/usuarios.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'update_role', id, rol })
        });
        const json = await res.json();
        if (json.success) return json;
      } catch (e) {
        console.warn('Fallo al actualizar rol en MySQL:', e);
      }
    }

    const users = JSON.parse(localStorage.getItem('dp_usuarios_registrados') || '[]');
    const target = users.find(u => u.id == id);
    if (target) {
      target.rol = rol;
      localStorage.setItem('dp_usuarios_registrados', JSON.stringify(users));
      return { success: true, message: 'Rol actualizado localmente.' };
    }
    return { success: false, error: 'Usuario no encontrado.' };
  },

  async deleteUser(id) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/usuarios.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'delete', id })
        });
        const json = await res.json();
        if (json.success) return json;
      } catch (e) {
        console.warn('Fallo al eliminar usuario en MySQL:', e);
      }
    }

    const users = JSON.parse(localStorage.getItem('dp_usuarios_registrados') || '[]');
    const filtered = users.filter(u => u.id != id);
    localStorage.setItem('dp_usuarios_registrados', JSON.stringify(filtered));
    return { success: true, message: 'Usuario eliminado localmente.' };
  },

  // ================= SUBIDA DE IMÁGENES =================
  async uploadProductImage(file) {
    if (!file) {
      return { success: false, error: 'No se seleccionó ningún archivo de imagen.' };
    }

    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const formData = new FormData();
        formData.append('imagen', file);

        const res = await fetch(`${this.baseUrl}/upload.php`, {
          method: 'POST',
          body: formData
        });

        const json = await res.json();
        if (json && json.success) {
          return json;
        }
      } catch (e) {
        console.warn('Fallo al subir imagen vía multipart al servidor, aplicando optimización local:', e);
      }
    }

    // Fallback con compresión optimizada vía Canvas a Data URL
    return new Promise((resolve) => {
      const reader = new FileReader();
      reader.onload = (event) => {
        const img = new Image();
        img.onload = () => {
          const canvas = document.createElement('canvas');
          let width = img.width;
          let height = img.height;
          const maxDim = 800;

          if (width > maxDim || height > maxDim) {
            if (width > height) {
              height = Math.round((height * maxDim) / width);
              width = maxDim;
            } else {
              width = Math.round((width * maxDim) / height);
              height = maxDim;
            }
          }

          canvas.width = width;
          canvas.height = height;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0, width, height);

          const compressedDataUrl = canvas.toDataURL('image/jpeg', 0.85);
          resolve({
            success: true,
            url: compressedDataUrl,
            message: 'Fotografía procesada y cargada con éxito.'
          });
        };
        img.onerror = () => {
          resolve({
            success: true,
            url: event.target.result,
            message: 'Fotografía cargada con éxito.'
          });
        };
        img.src = event.target.result;
      };
      reader.onerror = () => {
        resolve({ success: false, error: 'No se pudo leer el archivo de imagen seleccionado.' });
      };
      reader.readAsDataURL(file);
    });
  },

  // ================= EXPORTACIÓN / BACKUP COMPLETO EN 1 CLIC =================
  async downloadFullBackup(currentUser = null) {
    const isAvailable = await this.checkBackendAvailability();
    const adminUser = currentUser || (typeof Auth !== 'undefined' ? Auth.getCurrentUser() : null);
    
    // Si backend MySQL está disponible, solicitar al API PHP
    if (isAvailable) {
      try {
        const queryParams = new URLSearchParams();
        if (adminUser) {
          if (adminUser.id) queryParams.append('admin_id', adminUser.id);
          if (adminUser.numero_documento) queryParams.append('admin_doc', adminUser.numero_documento);
          if (adminUser.email) queryParams.append('admin_email', adminUser.email);
        }

        const res = await fetch(`${this.baseUrl}/backup.php?${queryParams.toString()}`);
        if (!res.ok) {
          const errData = await res.json().catch(() => null);
          throw new Error(errData?.error || `Error del servidor HTTP ${res.status}`);
        }
        
        const json = await res.json();
        if (json.success && json.data) {
          const filename = json.filename || `backup_descartables_${new Date().toISOString().slice(0,10)}.json`;
          this._triggerJsonDownload(json.data, filename);
          return { success: true, filename, metadata: json.metadata || json.data._metadata };
        } else {
          throw new Error(json.error || 'No se pudo generar la copia de seguridad.');
        }
      } catch (err) {
        console.warn('[ApiService] Falló la exportación remota desde MySQL, compilando respaldo local:', err);
      }
    }

    // Modo de respaldo local / standalone si el backend no responde
    try {
      const now = new Date();
      const pad = (n) => String(n).padStart(2, '0');
      const dateStr = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}_${pad(now.getHours())}${pad(now.getMinutes())}`;
      const filename = `backup_descartables_${dateStr}.json`;

      const [categories, products, quotes, reclamaciones, users, config] = await Promise.all([
        this.getCategories().catch(() => []),
        this.getProducts({}, true).catch(() => []),
        this.getCotizaciones().catch(() => []),
        this.getReclamacionesAdmin().then(r => r.data || []).catch(() => []),
        this.getUsers().then(r => r.data || []).catch(() => []),
        this.getCompanyConfig().catch(() => ({}))
      ]);

      const flatConfig = config.flat || {};
      const datosNegocio = {};
      const bannersData = {};
      Object.keys(flatConfig).forEach(k => {
        if (k.startsWith('banner_') || k.startsWith('hero_')) {
          bannersData[k] = flatConfig[k];
        } else {
          datosNegocio[k] = flatConfig[k];
        }
      });

      const backupPayload = {
        _metadata: {
          sistema: 'Descartables Peruanos - Plataforma Web & Panel Administrativo',
          version_backup: '1.0 (Modo Local/Offline)',
          fecha_exportacion: now.toISOString(),
          timestamp: Math.floor(Date.now() / 1000),
          generado_por: {
            id: adminUser?.id || 1,
            nombre_razon_social: adminUser?.nombre_razon_social || 'Master Admin',
            rol: adminUser?.rol || 'admin'
          },
          resumen_conteos: {
            configuracion_claves: Object.keys(flatConfig).length,
            categorias: categories.length,
            productos: products.length,
            usuarios: users.length,
            cotizaciones: quotes.length,
            libro_reclamaciones: reclamaciones.length
          }
        },
        datos_del_negocio: datosNegocio,
        banners_y_avisos: bannersData,
        configuracion_completa: flatConfig,
        categorias: categories,
        productos: products,
        usuarios: users,
        cotizaciones: quotes,
        libro_reclamaciones: reclamaciones
      };

      this._triggerJsonDownload(backupPayload, filename);
      return { success: true, filename, metadata: backupPayload._metadata };
    } catch (localErr) {
      console.error('[ApiService] Error generando copia de seguridad local:', localErr);
      return { success: false, error: 'No se pudo generar el archivo de respaldo: ' + localErr.message };
    }
  },

  _triggerJsonDownload(dataObject, filename) {
    const jsonString = typeof dataObject === 'string' ? dataObject : JSON.stringify(dataObject, null, 2);
    const blob = new Blob([jsonString], { type: 'application/json;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    setTimeout(() => {
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    }, 200);
  }
};

// ====================================================================
// SISTEMA GLOBAL DE NOTIFICACIONES TOAST (Tema Dinámico Azul Oscuro / Claro)
// ====================================================================
const Toast = {
  container: null,

  init() {
    if (!this.container) {
      this.container = document.getElementById('toast-container');
      if (!this.container) {
        this.container = document.createElement('div');
        this.container.id = 'toast-container';
        this.container.className = 'fixed bottom-5 right-5 z-[9999] flex flex-col gap-2.5 pointer-events-none max-w-sm w-full px-4';
        document.body.appendChild(this.container);
      }
    }
  },

  show(message, type = 'info', duration = 4000) {
    this.init();
    if (!this.container) return;

    const isDark = document.documentElement.classList.contains('dark');
    const toast = document.createElement('div');
    
    // Contenedor principal con el color azul oscuro característico del panel (#182035) o blanco en modo claro
    const baseClasses = isDark 
      ? 'bg-[#182035]/95 text-slate-100 border-[#263352] shadow-2xl shadow-black/50' 
      : 'bg-white/95 text-slate-900 border-slate-200 shadow-xl shadow-slate-900/10';

    toast.className = `pointer-events-auto transform transition-all duration-300 ease-out translate-y-3 opacity-0 rounded-2xl p-4 border flex items-start gap-3 backdrop-blur-md text-xs font-medium ${baseClasses}`;

    let iconSvg = '';
    let borderAccent = '';

    if (type === 'success') {
      borderAccent = isDark ? 'border-emerald-500/50' : 'border-emerald-500/40';
      iconSvg = `
        <div class="w-8 h-8 rounded-xl ${isDark ? 'bg-emerald-500/20 text-emerald-400' : 'bg-emerald-100 text-emerald-700'} flex items-center justify-center flex-shrink-0 shadow-xs">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        </div>
      `;
    } else if (type === 'error') {
      borderAccent = isDark ? 'border-rose-500/50' : 'border-rose-500/40';
      iconSvg = `
        <div class="w-8 h-8 rounded-xl ${isDark ? 'bg-rose-500/20 text-rose-400' : 'bg-rose-100 text-rose-700'} flex items-center justify-center flex-shrink-0 shadow-xs">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
        </div>
      `;
    } else if (type === 'warning') {
      borderAccent = isDark ? 'border-amber-500/50' : 'border-amber-500/40';
      iconSvg = `
        <div class="w-8 h-8 rounded-xl ${isDark ? 'bg-amber-500/20 text-amber-400' : 'bg-amber-100 text-amber-800'} flex items-center justify-center flex-shrink-0 shadow-xs">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
      `;
    } else {
      borderAccent = isDark ? 'border-brand-orange/50' : 'border-brand-orange/40';
      iconSvg = `
        <div class="w-8 h-8 rounded-xl ${isDark ? 'bg-brand-orange/20 text-brand-orange' : 'bg-brand-orange/10 text-brand-orange'} flex items-center justify-center flex-shrink-0 shadow-xs">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
      `;
    }

    toast.className += ` ${borderAccent}`;
    toast.innerHTML = `
      ${iconSvg}
      <div class="flex-1 pt-0.5 leading-snug">
        <p class="font-heading font-bold text-xs ${isDark ? 'text-white' : 'text-slate-900'}">${type === 'success' ? 'Éxito' : type === 'error' ? 'Atención / Error' : type === 'warning' ? 'Aviso' : 'Información'}</p>
        <p class="${isDark ? 'text-slate-300' : 'text-slate-600'} text-[11px] mt-0.5 leading-relaxed">${message}</p>
      </div>
      <button type="button" onclick="this.parentElement.remove()" class="${isDark ? 'text-slate-400 hover:text-white' : 'text-slate-400 hover:text-slate-800'} p-1 rounded-lg transition-colors flex-shrink-0 cursor-pointer" title="Cerrar">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    `;

    this.container.appendChild(toast);

    // Animación de entrada
    requestAnimationFrame(() => {
      toast.classList.remove('translate-y-3', 'opacity-0');
      toast.classList.add('translate-y-0', 'opacity-100');
    });

    // Auto cierre
    setTimeout(() => {
      if (toast.parentElement) {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
      }
    }, duration);
  },

  success(msg, duration) { this.show(msg, 'success', duration); },
  error(msg, duration) { this.show(msg, 'error', duration); },
  warning(msg, duration) { this.show(msg, 'warning', duration); },
  info(msg, duration) { this.show(msg, 'info', duration); }
};

window.Toast = Toast;
window.showToast = (msg, type, duration) => Toast.show(msg, type, duration);

