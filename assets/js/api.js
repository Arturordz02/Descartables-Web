/**
 * Helper de Almacenamiento Seguro (Safe Storage & Resilience) - F17
 * Garantiza resiliencia frente a cadenas corruptas o truncadas en LocalStorage.
 * Auto-purgado seguro (self-healing) que evita fallos catastróficos por SyntaxError.
 * 
 * NOTA DE SEGURIDAD: LocalStorage NUNCA es autoridad de rol ni permisos.
 * La sesión real y las autorizaciones son verificadas criptográficamente en backend por Vault.
 */
const StorageHelper = {
  get(key, fallback = null) {
    try {
      if (typeof localStorage === 'undefined') return fallback;
      const raw = localStorage.getItem(key);
      if (raw === null || raw === undefined || raw === '') {
        return fallback;
      }
      if (raw === 'undefined' || raw === 'null') {
        try { localStorage.removeItem(key); } catch (_) {}
        return fallback;
      }
      return JSON.parse(raw);
    } catch (e) {
      console.warn(`[StorageHelper] JSON corrupto detectado en clave '${key}', purgando clave dañada.`);
      try {
        if (typeof localStorage !== 'undefined') {
          localStorage.removeItem(key);
        }
      } catch (_) {}
      return fallback;
    }
  },

  set(key, value) {
    try {
      if (typeof localStorage === 'undefined') return false;
      localStorage.setItem(key, JSON.stringify(value));
      return true;
    } catch (e) {
      console.warn(`[StorageHelper] Error al escribir en localStorage['${key}']`, e);
      return false;
    }
  },

  remove(key) {
    try {
      if (typeof localStorage !== 'undefined') {
        localStorage.removeItem(key);
      }
    } catch (_) {}
  },

  safeParse(raw, fallback = null, keyToPurge = null) {
    if (raw === null || raw === undefined || raw === '') {
      return fallback;
    }
    if (raw === 'undefined' || raw === 'null') {
      if (keyToPurge && typeof localStorage !== 'undefined') {
        try { localStorage.removeItem(keyToPurge); } catch (_) {}
      }
      return fallback;
    }
    try {
      return JSON.parse(raw);
    } catch (e) {
      console.warn(`[StorageHelper] Error al parsear JSON ${keyToPurge ? `para clave '${keyToPurge}'` : ''}.`);
      if (keyToPurge && typeof localStorage !== 'undefined') {
        try { localStorage.removeItem(keyToPurge); } catch (_) {}
      }
      return fallback;
    }
  }
};

if (typeof window !== 'undefined') {
  window.StorageHelper = StorageHelper;
}
if (typeof module !== 'undefined' && module.exports) {
  module.exports.StorageHelper = StorageHelper;
}

const ApiService = {
  baseUrl: 'api',
  hasBackend: null,

  // Métodos auxiliares públicos de almacenamiento seguro
  safeJsonStorage(key, fallback = null) {
    return StorageHelper.get(key, fallback);
  },

  safeJsonParse(raw, fallback = null, keyToPurge = null) {
    return StorageHelper.safeParse(raw, fallback, keyToPurge);
  },

  // Cabeceras de autenticación seguras
  getAuthHeaders(customHeaders = {}) {
    const headers = { 'Accept': 'application/json', ...customHeaders };
    try {
      const user = StorageHelper.get('dp_usuario_activo', {});
      if (user && user.token) {
        headers['Authorization'] = `Bearer ${user.token}`;
        headers['X-Auth-Token'] = user.token;
      }
    } catch (e) {}
    return headers;
  },

  // Generador de clave de idempotencia única para reintentos seguros
  generateIdempotencyKey(prefix = 'idemp') {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
      return `${prefix}_${crypto.randomUUID()}`;
    }
    return `${prefix}_${Date.now()}_${Math.random().toString(36).substring(2, 12)}`;
  },

  // Verifica la disponibilidad del backend MySQL en InfinityFree
  async checkBackendAvailability() {
    if (this.hasBackend === true) return true;

    const candidates = ['api', '/api'];

    for (const cand of candidates) {
      try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 7000);
        const response = await fetch(`${cand}/productos.php?tipo=categorias&_t=${Date.now()}`, {
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
            return true;
          }
        }
      } catch (e) {
        // probar siguiente candidato
      }
    }

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
          StorageHelper.set('dp_categorias_cache', json.data);
          return json.data;
        }
      } catch (e) {
        console.warn('Fallo en API remota de categorías, utilizando datos locales.');
      }
    }

    // Modo local / Respaldo seguro
    const cached = StorageHelper.get('dp_categorias_cache', null);
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
      const customProds = StorageHelper.get('dp_productos_custom', []);
      const deletedIds = StorageHelper.get('dp_productos_deleted', []);
      
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
    const customProds = StorageHelper.get('dp_productos_custom', []);
    const foundCustom = customProds.find(p => p.sku && p.sku.toUpperCase() === sku);
    if (foundCustom) return this.cleanProduct(foundCustom);

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

    const customProds = StorageHelper.get('dp_productos_custom', []);
    const foundCustom = customProds.find(p => p.id === id);
    if (foundCustom) return this.cleanProduct(foundCustom);

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
  async registerReclamacion(claimData, idempotencyKey = null) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const idempKey = idempotencyKey || claimData?.idempotency_key || null;
        const reqHeaders = { 'Content-Type': 'application/json' };
        if (idempKey) {
          reqHeaders['Idempotency-Key'] = idempKey;
        }
        const res = await fetch(`${this.baseUrl}/reclamaciones.php`, {
          method: 'POST',
          headers: this.getAuthHeaders(reqHeaders),
          body: JSON.stringify(claimData)
        });
        if (!res.ok) {
          let errorMsg = 'Error en el servidor al registrar el reclamo.';
          let retryAfter = null;
          try {
            const errJson = await res.json();
            if (errJson && errJson.error) errorMsg = errJson.error;
            if (errJson && errJson.retry_after) retryAfter = errJson.retry_after;
          } catch (_) {}
          return { success: false, error: errorMsg, status: res.status, retry_after: retryAfter };
        }
        const json = await res.json();
        if (json.success) {
          this.backupLocalReclamacion(json);
          return json;
        } else {
          return { success: false, error: json.error || 'Error al procesar el reclamo.' };
        }
      } catch (e) {
        console.warn('Error enviando reclamo a API MySQL:', e);
        return {
          success: false,
          offline_draft: true,
          error: 'No se pudo conectar con el servidor oficial. Verifique su conexión.'
        };
      }
    }

    return {
      success: false,
      offline_draft: true,
      error: 'Servidor no disponible para registro oficial de reclamaciones.'
    };
  },

  backupLocalReclamacion(record) {
    try {
      const claims = StorageHelper.get('dp_libro_reclamaciones', []);
      claims.unshift(record);
      StorageHelper.set('dp_libro_reclamaciones', claims.slice(0, 50));
    } catch (e) {}
  },

  // Obtener todas las reclamaciones (Modo Admin)
  async getReclamacionesAdmin() {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/reclamaciones.php`, {
          headers: this.getAuthHeaders()
        });
        const json = await res.json();
        if (json.success) return json;
      } catch (e) {
        console.warn('Fallo consultando reclamaciones en MySQL, usando local.');
      }
    }

    const localClaims = StorageHelper.get('dp_libro_reclamaciones', []);
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
          headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify({ id, ...payload })
        });
        const json = await res.json();
        if (json.success) return json;
      } catch (e) {
        console.warn('Fallo al actualizar reclamación en MySQL, actualizando local.');
      }
    }

    // Modo local
    const localClaims = StorageHelper.get('dp_libro_reclamaciones', []);
    const idx = localClaims.findIndex(c => c.id == id || c.codigo_hoja == id || c.codigo_seguimiento == id);
    if (idx !== -1) {
      localClaims[idx].estado = payload.estado || 'Atendido';
      localClaims[idx].respuesta_proveedor = payload.respuesta_proveedor || '';
      localClaims[idx].fecha_respuesta = new Date().toLocaleString('es-PE');
      StorageHelper.set('dp_libro_reclamaciones', localClaims);
      return { success: true, message: 'Actualizado localmente.', data: localClaims[idx] };
    }
    return { success: true, message: 'Actualizado.' };
  },

  // Registrar Cotización Formal B2B - Alias canónico hacia saveCotizacion (F16)
  async registerQuote(quoteData, idempotencyKey = null) {
    return this.saveCotizacion(quoteData, idempotencyKey);
  },

  // Obtener cotizaciones - Alias canónico hacia getCotizaciones (F16)
  async getQuotes(filters = {}) {
    const res = await this.getCotizaciones(filters);
    if (res && res.data && Array.isArray(res.data)) return res.data;
    if (Array.isArray(res)) return res;
    return [];
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
        if (!res.ok) {
          let errorMsg = 'Error de autenticación en el servidor.';
          let retryAfter = null;
          try {
            const errJson = await res.json();
            if (errJson && errJson.error) errorMsg = errJson.error;
            if (errJson && errJson.retry_after) retryAfter = errJson.retry_after;
          } catch (_) {}
          return { success: false, error: errorMsg, status: res.status, retry_after: retryAfter };
        }
        const json = await res.json();
        if (json.success && json.user) {
          const cleanUser = { ...json.user };
          delete cleanUser.password;
          delete cleanUser.password_hash;
          localStorage.setItem('dp_usuario_activo', JSON.stringify(cleanUser));
          return { success: true, user: cleanUser, message: json.message };
        } else {
          return { success: false, error: json.error || 'Documento/correo o contraseña incorrectos.' };
        }
      } catch (e) {
        console.warn('Fallo en API login:', e);
        return {
          success: false,
          error: 'Error de comunicación con el servidor. Verifique su conexión.'
        };
      }
    }

    return {
      success: false,
      error: 'El servicio de inicio de sesión requiere conexión con el servidor.'
    };
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
        if (!res.ok) {
          let errorMsg = 'Error al registrar usuario en el servidor.';
          let retryAfter = null;
          try {
            const errJson = await res.json();
            if (errJson && errJson.error) errorMsg = errJson.error;
            if (errJson && errJson.retry_after) retryAfter = errJson.retry_after;
          } catch (_) {}
          return { success: false, error: errorMsg, status: res.status, retry_after: retryAfter };
        }
        const json = await res.json();
        if (json.success && json.user) {
          const cleanUser = { ...json.user };
          delete cleanUser.password;
          delete cleanUser.password_hash;
          localStorage.setItem('dp_usuario_activo', JSON.stringify(cleanUser));
          return { success: true, user: cleanUser, message: json.message };
        } else {
          return { success: false, error: json.error || 'Error en el registro.' };
        }
      } catch (e) {
        console.error('Error al registrar en servidor:', e);
        return {
          success: false,
          error: 'Error de comunicación con el servidor. Verifique su conexión.'
        };
      }
    }

    return {
      success: false,
      error: 'El servicio de registro requiere conexión con el servidor.'
    };
  },

  // Actualizar perfil
  async updateProfile(userData) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/auth.php?action=update_profile`, {
          method: 'POST',
          headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify(userData)
        });
        const json = await res.json();
        if (json.success && json.user) {
          const cleanUser = { ...json.user };
          delete cleanUser.password;
          delete cleanUser.password_hash;
          localStorage.setItem('dp_usuario_activo', JSON.stringify(cleanUser));
          return { success: true, user: cleanUser, message: json.message };
        }
        return json;
      } catch (e) {
        console.warn('Fallo actualizando perfil en MySQL:', e);
        return { success: false, error: 'Error de comunicación al actualizar perfil.' };
      }
    }

    return { success: false, error: 'La actualización de perfil requiere conexión con el servidor.' };
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
          headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify(productData)
        });
        const json = await res.json();
        if (json.success && json.data) {
          // Guardar copia local de respaldo
          const localProds = StorageHelper.get('dp_productos_custom', []);
          const newProd = this.cleanProduct(json.data);
          const existingIdx = localProds.findIndex(p => p.id === newProd.id || (newProd.sku && p.sku === newProd.sku));
          if (existingIdx !== -1) {
            localProds[existingIdx] = newProd;
          } else {
            localProds.unshift(newProd);
          }
          StorageHelper.set('dp_productos_custom', localProds);
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
    const localProds = StorageHelper.get('dp_productos_custom', []);
    const newLocalProd = { id: Date.now(), ...enhancedData };
    localProds.unshift(newLocalProd);
    StorageHelper.set('dp_productos_custom', localProds);
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
          headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify({ id, ...productData })
        });
        const json = await res.json();
        if (json.success && json.data) {
          const localProds = StorageHelper.get('dp_productos_custom', []);
          const updatedProd = this.cleanProduct(json.data);
          const idx = localProds.findIndex(p => p.id === id || (updatedProd.sku && p.sku === updatedProd.sku));
          if (idx !== -1) {
            localProds[idx] = updatedProd;
          } else {
            localProds.unshift(updatedProd);
          }
          StorageHelper.set('dp_productos_custom', localProds);
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
    const localProds = StorageHelper.get('dp_productos_custom', []);
    const idx = localProds.findIndex(p => p.id === id);
    if (idx !== -1) {
      localProds[idx] = { ...localProds[idx], ...enhancedData };
    } else {
      localProds.push(enhancedData);
    }
    StorageHelper.set('dp_productos_custom', localProds);
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
          headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify({ id })
        });
        const json = await res.json();
        if (json.success) {
          const localProds = StorageHelper.get('dp_productos_custom', []);
          const filtered = localProds.filter(p => p.id !== id);
          StorageHelper.set('dp_productos_custom', filtered);

          const deletedIds = StorageHelper.get('dp_productos_deleted', []);
          if (!deletedIds.includes(id)) {
            deletedIds.push(id);
            StorageHelper.set('dp_productos_deleted', deletedIds);
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
    const deletedIds = StorageHelper.get('dp_productos_deleted', []);
    if (!deletedIds.includes(id)) {
      deletedIds.push(id);
      StorageHelper.set('dp_productos_deleted', deletedIds);
    }

    const localProds = StorageHelper.get('dp_productos_custom', []);
    const filtered = localProds.filter(p => p.id !== id);
    StorageHelper.set('dp_productos_custom', filtered);

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
          headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
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
          headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
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
          headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
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
    StorageHelper.set('dp_categorias_cache', filtered);
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
          StorageHelper.set('dp_empresa_config', json.data);
          return { success: true, data: json.data };
        }
      } catch (e) {
        console.warn('Error al obtener configuración de MySQL:', e);
      }
    }

    const localConfig = StorageHelper.get('dp_empresa_config', null);
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
          headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
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
          headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify({
            current_password: currentPassword,
            new_password: newPassword,
            identificador: identificador
          })
        });
        if (!res.ok) {
          let errorMsg = 'Error en el servidor al cambiar contraseña.';
          let retryAfter = null;
          try {
            const errJson = await res.json();
            if (errJson && errJson.error) errorMsg = errJson.error;
            if (errJson && errJson.retry_after) retryAfter = errJson.retry_after;
          } catch (_) {}
          return { success: false, error: errorMsg, status: res.status, retry_after: retryAfter };
        }
        const json = await res.json();
        return json;
      } catch (e) {
        console.warn('Error en comunicación con auth.php para cambio de contraseña:', e);
        return { success: false, error: 'Error de comunicación con el servidor.' };
      }
    }

    return { success: false, error: 'El cambio de contraseña requiere conexión con el servidor.' };
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
  async saveCotizacion(quoteData, idempotencyKey = null) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const idempKey = idempotencyKey || quoteData?.idempotency_key || null;
        const reqHeaders = { 'Content-Type': 'application/json' };
        if (idempKey) {
          reqHeaders['Idempotency-Key'] = idempKey;
        }
        const res = await fetch(`${this.baseUrl}/cotizaciones.php`, {
          method: 'POST',
          headers: this.getAuthHeaders(reqHeaders),
          body: JSON.stringify(quoteData)
        });
        if (!res.ok) {
          let errorMsg = 'Error en el servidor al guardar la cotización.';
          let retryAfter = null;
          try {
            const errJson = await res.json();
            if (errJson && errJson.error) errorMsg = errJson.error;
            if (errJson && errJson.retry_after) retryAfter = errJson.retry_after;
          } catch (_) {}
          return { success: false, error: errorMsg, status: res.status, retry_after: retryAfter };
        }
        const json = await res.json();
        if (json.success) {
          this.saveLocalCotizacionBackup({ ...quoteData, codigo_cotizacion: json.codigo_cotizacion, id: json.id, creado_en: json.fecha });
          return json;
        } else {
          return { success: false, error: json.error || 'Error al procesar la cotización.' };
        }
      } catch (e) {
        console.warn('Fallo al guardar cotización en backend:', e);
        return {
          success: false,
          offline_draft: true,
          error: 'No se pudo conectar con el servidor para registrar la cotización.'
        };
      }
    }

    return {
      success: false,
      offline_draft: true,
      error: 'Servidor no disponible para registrar cotizaciones oficiales.'
    };
  },

  saveLocalCotizacionBackup(quote) {
    try {
      const myQuotes = StorageHelper.get('dp_mis_cotizaciones', []);
      myQuotes.unshift(quote);
      StorageHelper.set('dp_mis_cotizaciones', myQuotes.slice(0, 50));
      StorageHelper.set('dp_historial_cotizaciones', myQuotes.slice(0, 50));
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
        if (filters.usuario_id) queryParams.append('usuario_id', filters.usuario_id);
        queryParams.append('_t', Date.now().toString());

        const res = await fetch(`${this.baseUrl}/cotizaciones.php?${queryParams.toString()}`, {
          headers: this.getAuthHeaders()
        });
        const json = await res.json();
        if (json.success && Array.isArray(json.data)) {
          return { success: true, count: json.data.length, data: json.data, stats: json.stats };
        }
      } catch (e) {
        console.warn('Fallo al obtener cotizaciones de MySQL, usando locales:', e);
      }
    }

    // Fallback local
    let quotes = StorageHelper.get('dp_cotizaciones_recibidas', []);
    if (filters.estado && filters.estado !== 'all' && filters.estado !== 'todos') {
      quotes = quotes.filter(q => q.estado === filters.estado);
    }
    if (filters.documento) {
      quotes = quotes.filter(q => q.documento === filters.documento || q.cliente_doc === filters.documento);
    }
    if (filters.usuario_id) {
      quotes = quotes.filter(q => q.usuario_id === filters.usuario_id);
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
          headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify({ action: 'update_status', id, estado, notas })
        });
        const json = await res.json();
        if (json.success) return json;
      } catch (e) {
        console.warn('Fallo al actualizar cotización en MySQL:', e);
      }
    }

    // Fallback local
    const quotes = StorageHelper.get('dp_cotizaciones_recibidas', []);
    const target = quotes.find(q => q.id == id);
    if (target) {
      target.estado = estado;
      if (notas !== undefined) target.notas = notas;
      StorageHelper.set('dp_cotizaciones_recibidas', quotes);
      return { success: true, message: 'Estado actualizado localmente.', estado, notas };
    }
    return { success: false, error: 'No se encontró la cotización.' };
  },

  async deleteCotizacion(idOrIds) {
    const rawIds = Array.isArray(idOrIds) ? idOrIds : [idOrIds];
    const ids = rawIds.map(x => parseInt(x, 10)).filter(x => !isNaN(x) && x > 0);
    if (ids.length === 0) return { success: false, error: 'No se indicaron IDs válidos.' };

    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/cotizaciones.php`, {
          method: 'POST',
          headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify({ action: 'delete', ids })
        });
        const json = await res.json();
        if (json.success) {
          this.purgeLocalCotizaciones(ids);
          return json;
        }
      } catch (e) {
        console.warn('Fallo al eliminar cotización en MySQL:', e);
      }
    }

    this.purgeLocalCotizaciones(ids);
    return { success: true, message: 'Cotización(es) eliminada(s) con éxito.', deleted: ids };
  },

  purgeLocalCotizaciones(ids) {
    try {
      const idSet = new Set(ids.map(String));
      ['dp_cotizaciones_recibidas', 'dp_mis_cotizaciones', 'dp_historial_cotizaciones'].forEach(key => {
        const list = StorageHelper.get(key, []);
        const filtered = list.filter(q => !idSet.has(String(q.id)) && !idSet.has(String(q.codigo_cotizacion || q.codigo)));
        StorageHelper.set(key, filtered);
      });
    } catch (e) {}
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
          headers: this.getAuthHeaders({ 'Accept': 'application/json' })
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
    const localQuotes = StorageHelper.get('dp_cotizaciones_recibidas', []);
    const localClaims = StorageHelper.get('dp_libro_reclamaciones', []);

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
        const res = await fetch(url, {
          headers: this.getAuthHeaders()
        });
        const json = await res.json();
        if (json && json.success) {
          return json;
        }
      } catch (e) {
        console.warn('Fallo al obtener usuarios de MySQL:', e);
      }
    }

    // Fallback local
    const users = StorageHelper.get('dp_usuarios_registrados', []);
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
          headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify({ action: 'update_role', id, rol })
        });
        const json = await res.json();
        if (json.success) return json;
      } catch (e) {
        console.warn('Fallo al actualizar rol en MySQL:', e);
      }
    }

    const users = StorageHelper.get('dp_usuarios_registrados', []);
    const target = users.find(u => u.id == id);
    if (target) {
      target.rol = rol;
      StorageHelper.set('dp_usuarios_registrados', users);
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
          headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
          body: JSON.stringify({ action: 'delete', id })
        });
        const json = await res.json();
        if (json.success) return json;
      } catch (e) {
        console.warn('Fallo al eliminar usuario en MySQL:', e);
      }
    }

    const users = StorageHelper.get('dp_usuarios_registrados', []);
    const filtered = users.filter(u => u.id != id);
    StorageHelper.set('dp_usuarios_registrados', filtered);
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
        const authHeaders = this.getAuthHeaders();
        delete authHeaders['Content-Type'];

        const res = await fetch(`${this.baseUrl}/upload.php`, {
          method: 'POST',
          headers: authHeaders,
          body: formData
        });

        if (!res.ok) {
          let errorMsg = 'Error al subir imagen al servidor.';
          try {
            const errJson = await res.json();
            if (errJson && errJson.error) errorMsg = errJson.error;
          } catch (_) {}
          return { success: false, error: errorMsg, status: res.status };
        }

        const json = await res.json();
        if (json && json.success) {
          return json;
        } else {
          return { success: false, error: json.error || 'Error al procesar la imagen.' };
        }
      } catch (e) {
        console.warn('Fallo de comunicación con upload.php:', e);
        return { success: false, error: 'No se pudo conectar con el servidor para subir la imagen.' };
      }
    }

    return { success: false, error: 'El servicio de subida de imágenes requiere conexión con el servidor.' };
  },

  // ================= EXPORTACIÓN / BACKUP COMPLETO EN 1 CLIC =================
  async downloadFullBackup(currentUser = null) {
    const isAvailable = await this.checkBackendAvailability();
    const adminUser = currentUser || (typeof Auth !== 'undefined' ? Auth.getCurrentUser() : null);
    
    // Si backend MySQL está disponible, solicitar al API PHP
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/backup.php`, {
          headers: this.getAuthHeaders()
        });
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
  },

  // Limpieza de seguridad de credenciales y contraseñas residuales en localStorage
  sanitizeStorage() {
    try {
      if (typeof localStorage === 'undefined') return;

      // 1. Limpiar contraseñas residuales en dp_usuarios_registrados si existieran
      const usersRaw = localStorage.getItem('dp_usuarios_registrados');
      if (usersRaw) {
        try {
          const users = JSON.parse(usersRaw);
          if (Array.isArray(users)) {
            let modified = false;
            const cleanUsers = users.map(u => {
              if (u && (u.password !== undefined || u.password_hash !== undefined)) {
                modified = true;
                const clean = { ...u };
                delete clean.password;
                delete clean.password_hash;
                return clean;
              }
              return u;
            });
            if (modified) {
              localStorage.setItem('dp_usuarios_registrados', JSON.stringify(cleanUsers));
            }
          }
        } catch (_) {}
      }

      // 2. Limpiar contraseña en dp_usuario_activo si existiera
      const activeRaw = localStorage.getItem('dp_usuario_activo');
      if (activeRaw) {
        try {
          const activeUser = JSON.parse(activeRaw);
          if (activeUser && (activeUser.password !== undefined || activeUser.password_hash !== undefined)) {
            delete activeUser.password;
            delete activeUser.password_hash;
            localStorage.setItem('dp_usuario_activo', JSON.stringify(activeUser));
          }
        } catch (_) {
          // Si dp_usuario_activo está corrupto, purgarlo inmediatamente (sesión inválida)
          try { localStorage.removeItem('dp_usuario_activo'); } catch (err) {}
        }
      }
    } catch (_) {}
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

    const safeMsg = (typeof escapeHtml === 'function' ? escapeHtml(message) : String(message || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m])));

    toast.className += ` ${borderAccent}`;
    toast.innerHTML = `
      ${iconSvg}
      <div class="flex-1 pt-0.5 leading-snug">
        <p class="font-heading font-bold text-xs ${isDark ? 'text-white' : 'text-slate-900'}">${type === 'success' ? 'Éxito' : type === 'error' ? 'Atención / Error' : type === 'warning' ? 'Aviso' : 'Información'}</p>
        <p class="${isDark ? 'text-slate-300' : 'text-slate-600'} text-[11px] mt-0.5 leading-relaxed">${safeMsg}</p>
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

// ====================================================================
// UTILIDADES GLOBALES DE ESCAPADO Y SANEAMIENTO CONTRA XSS (F06)
// ====================================================================
function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str).replace(/[&<>"']/g, m => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  }[m]));
}

function sanitizeUrl(url) {
  if (!url || typeof url !== 'string') return '';
  const trimmed = url.trim();
  if (/^(?:javascript|data|vbscript):/i.test(trimmed)) {
    return '#';
  }
  return escapeHtml(trimmed);
}

window.escapeHtml = escapeHtml;
window.sanitizeUrl = sanitizeUrl;
ApiService.escapeHtml = escapeHtml;
ApiService.sanitizeUrl = sanitizeUrl;

window.ApiService = ApiService;
window.Toast = Toast;
window.showToast = (msg, type, duration) => Toast.show(msg, type, duration);

// Exportación compatible con entornos Node.js / pruebas unitarias
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    ApiService,
    StorageHelper,
    Toast,
    escapeHtml,
    sanitizeUrl
  };
}

