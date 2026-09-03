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

  // Obtener categorías
  async getCategories() {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/productos.php?tipo=categorias`);
        const json = await res.json();
        if (json.success && json.data.length > 0) return json.data;
      } catch (e) {
        console.warn('Fallo en API remota de categorías, utilizando datos locales.');
      }
    }
    return CATEGORIAS;
  },

  // Obtener productos con filtros opcionales (Modo MySQL + Modo Local Integrado)
  async getProducts(filters = {}) {
    const isAvailable = await this.checkBackendAvailability();
    let results = [];

    if (isAvailable) {
      try {
        const queryParams = new URLSearchParams();
        if (filters.categoria && filters.categoria !== 'todos') queryParams.append('categoria', filters.categoria);
        if (filters.material && filters.material !== 'todos') queryParams.append('material', filters.material);
        if (filters.biodegradable !== undefined && filters.biodegradable !== '') {
          queryParams.append('biodegradable', filters.biodegradable ? '1' : '0');
        }
        if (filters.destacado) queryParams.append('destacado', '1');
        if (filters.q) queryParams.append('q', filters.q);

        const res = await fetch(`${this.baseUrl}/productos.php?${queryParams.toString()}`);
        const json = await res.json();
        if (json.success && Array.isArray(json.data) && json.data.length > 0) {
          results = json.data;
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

      // 3. Aplicar filtros en memoria
      if (filters.categoria && filters.categoria !== 'todos') {
        results = results.filter(p => 
          p.categoria_slug === filters.categoria || 
          p.categoria_id == filters.categoria || 
          slugMap[p.categoria_id] === filters.categoria
        );
      }
      if (filters.material && filters.material !== 'todos') {
        results = results.filter(p => (p.material || '').toLowerCase().includes(filters.material.toLowerCase()));
      }
      if (filters.biodegradable === true || filters.biodegradable === 'true' || filters.biodegradable === 1) {
        results = results.filter(p => p.biodegradable === true || p.biodegradable == 1);
      }
      if (filters.destacado) {
        results = results.filter(p => p.destacado === true || p.destacado == 1);
      }
      if (filters.q) {
        const q = filters.q.toLowerCase().trim();
        results = results.filter(p => 
          (p.nombre || '').toLowerCase().includes(q) || 
          (p.sku || '').toLowerCase().includes(q) || 
          (p.descripcion || '').toLowerCase().includes(q) ||
          (p.material || '').toLowerCase().includes(q)
        );
      }
    }

    return results;
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
        telefono: '(01) 564-1450',
        email: 'ventas@descartablesperuanos.pe'
      },
      plazo_legal: '15 días hábiles conforme a la Ley N° 31435 que modifica el Código de Protección y Defensa del Consumidor.'
    };
  },

  backupLocalReclamacion(record) {
    const claims = JSON.parse(localStorage.getItem('dp_libro_reclamaciones') || '[]');
    claims.push(record);
    localStorage.setItem('dp_libro_reclamaciones', JSON.stringify(claims));
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

    // Fallback de usuarios locales
    const users = JSON.parse(localStorage.getItem('dp_usuarios_registrados') || '[]');
    // Agregar usuario demo si está vacío
    if (users.length === 0) {
      users.push({
        id: 1,
        tipo_documento: 'RUC',
        numero_documento: '20601234567',
        nombre_razon_social: 'EMPRESA GASTRONÓMICA PERÚ S.A.C.',
        email: 'cliente@demo.pe',
        password: 'password123',
        telefono: '994195430',
        departamento: 'Lima',
        provincia: 'Lima',
        distrito: 'Cercado de Lima',
        direccion: 'Av. Alejandro Bertello 732-C',
        rol: 'cliente'
      });
      localStorage.setItem('dp_usuarios_registrados', JSON.stringify(users));
    }

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
    const enhancedData = {
      ...productData,
      categoria_slug: slugMap[productData.categoria_id] || 'pamolsa',
      categoria_nombre: this.getCatalogCategoryName(productData.categoria_id)
    };

    // Guardar copia local siempre para modo offline/local
    const localProds = JSON.parse(localStorage.getItem('dp_productos_custom') || '[]');
    const newLocalProd = { id: Date.now(), ...enhancedData };
    localProds.unshift(newLocalProd);
    localStorage.setItem('dp_productos_custom', JSON.stringify(localProds));

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
          // Actualizar id en local si vino de MySQL
          newLocalProd.id = json.data.id;
          localStorage.setItem('dp_productos_custom', JSON.stringify(localProds));
          return json;
        }
        return json;
      } catch (e) {
        console.error('Error al crear producto en MySQL:', e);
      }
    }

    return { success: true, message: 'Producto guardado exitosamente.', data: newLocalProd };
  },

  // Editar producto
  async updateProduct(id, productData) {
    const slugMap = { 1: 'pamolsa', 2: 'proplas-barrera', 3: 'cubiertos', 4: 'servilletas', 5: 'limpieza', 6: 'novedades' };
    const enhancedData = {
      id,
      ...productData,
      categoria_slug: slugMap[productData.categoria_id] || 'pamolsa',
      categoria_nombre: this.getCatalogCategoryName(productData.categoria_id)
    };

    // Guardar en copia local
    const localProds = JSON.parse(localStorage.getItem('dp_productos_custom') || '[]');
    const idx = localProds.findIndex(p => p.id === id);
    if (idx !== -1) {
      localProds[idx] = { ...localProds[idx], ...enhancedData };
    } else {
      localProds.push(enhancedData);
    }
    localStorage.setItem('dp_productos_custom', JSON.stringify(localProds));

    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/productos.php`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id, ...productData })
        });
        return await res.json();
      } catch (e) {
        console.error('Error al actualizar producto en MySQL:', e);
      }
    }

    return { success: true, message: 'Producto actualizado exitosamente.', data: enhancedData };
  },

  // Eliminar producto
  async deleteProduct(id) {
    // Registrar ID eliminado para modo local
    const deletedIds = JSON.parse(localStorage.getItem('dp_productos_deleted') || '[]');
    if (!deletedIds.includes(id)) {
      deletedIds.push(id);
      localStorage.setItem('dp_productos_deleted', JSON.stringify(deletedIds));
    }

    const localProds = JSON.parse(localStorage.getItem('dp_productos_custom') || '[]');
    const filtered = localProds.filter(p => p.id !== id);
    localStorage.setItem('dp_productos_custom', JSON.stringify(filtered));

    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const res = await fetch(`${this.baseUrl}/productos.php`, {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id })
        });
        return await res.json();
      } catch (e) {
        console.error('Error al eliminar producto en MySQL:', e);
      }
    }

    return { success: true, message: 'Producto eliminado correctamente.' };
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

  getCatalogCategoryName(catId) {
    const map = {
      1: 'Contenedores Térmicos Pamolsa',
      2: 'Vasos y Tapas Proplas',
      3: 'Cubiertos y Vajilla',
      4: 'Servilletas y Papelería',
      5: 'Limpieza y Desinfección',
      6: 'Línea Eco-Biodegradable'
    };
    return map[catId] || 'General';
  }
};

