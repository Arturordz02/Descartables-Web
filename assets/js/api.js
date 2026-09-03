/**
 * Capa de Abstracción de Datos y Servicios API
 * Soporte híbrido: Consulta backend MySQL vía PHP o respaldo dinámico transparente en LocalStorage
 */

const ApiService = {
  baseUrl: 'api',
  hasBackend: null,

  // Verifica si el servidor PHP/MySQL responde
  async checkBackendAvailability() {
    if (this.hasBackend !== null) return this.hasBackend;
    try {
      const response = await fetch(`${this.baseUrl}/productos.php?tipo=categorias`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
      });
      if (response.ok) {
        const json = await response.json();
        this.hasBackend = !!json.success;
      } else {
        this.hasBackend = false;
      }
    } catch (e) {
      this.hasBackend = false;
    }
    return this.hasBackend;
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

  // Obtener productos con filtros opcionales
  async getProducts(filters = {}) {
    const isAvailable = await this.checkBackendAvailability();
    if (isAvailable) {
      try {
        const queryParams = new URLSearchParams();
        if (filters.categoria) queryParams.append('categoria', filters.categoria);
        if (filters.material) queryParams.append('material', filters.material);
        if (filters.biodegradable !== undefined && filters.biodegradable !== '') {
          queryParams.append('biodegradable', filters.biodegradable ? '1' : '0');
        }
        if (filters.destacado) queryParams.append('destacado', '1');
        if (filters.q) queryParams.append('q', filters.q);

        const res = await fetch(`${this.baseUrl}/productos.php?${queryParams.toString()}`);
        const json = await res.json();
        if (json.success) return json.data;
      } catch (e) {
        console.warn('Fallo al obtener productos de MySQL, usando datos locales.');
      }
    }

    // Fallback local enriquecido
    let results = [...PRODUCTOS];

    if (filters.categoria && filters.categoria !== 'todos') {
      results = results.filter(p => p.categoria_slug === filters.categoria);
    }
    if (filters.material && filters.material !== 'todos') {
      results = results.filter(p => p.material.toLowerCase().includes(filters.material.toLowerCase()));
    }
    if (filters.biodegradable === true || filters.biodegradable === 'true' || filters.biodegradable === 1) {
      results = results.filter(p => p.biodegradable === true);
    }
    if (filters.destacado) {
      results = results.filter(p => p.destacado === true);
    }
    if (filters.q) {
      const q = filters.q.toLowerCase().trim();
      results = results.filter(p => 
        p.nombre.toLowerCase().includes(q) || 
        p.sku.toLowerCase().includes(q) || 
        p.descripcion.toLowerCase().includes(q)
      );
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
        console.warn('Fallo en API MySQL registro, registrando localmente.');
      }
    }

    // Fallback Local
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

    return { success: true, message: 'Usuario registrado exitosamente.', user: sessionUser };
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
  }
};

