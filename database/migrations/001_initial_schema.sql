-- ====================================================================
-- MIGRACIÓN 001: Esquema Base Canónico
-- Plataforma Descartables Peruanos
-- ====================================================================

-- 1. Tabla de Control de Migraciones
CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(100) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de Usuarios (Soporte DNI, RUC, CE - Clientes y Administradores)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento VARCHAR(10) DEFAULT 'DNI',
    numero_documento VARCHAR(20) NOT NULL UNIQUE,
    nombre_razon_social VARCHAR(255) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    telefono VARCHAR(30) NULL,
    departamento VARCHAR(100) DEFAULT 'Lima',
    provincia VARCHAR(100) DEFAULT 'Lima',
    distrito VARCHAR(100) NULL,
    direccion TEXT NULL,
    rol VARCHAR(20) DEFAULT 'cliente',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS idx_usuarios_doc ON usuarios(numero_documento);
CREATE INDEX IF NOT EXISTS idx_usuarios_email ON usuarios(email);
CREATE INDEX IF NOT EXISTS idx_usuarios_rol ON usuarios(rol);

-- 3. Tabla de Categorías
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    descripcion TEXT NULL,
    icono VARCHAR(50) DEFAULT 'box',
    color VARCHAR(100) DEFAULT 'from-amber-600/20 to-orange-600/20',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS idx_categorias_slug ON categorias(slug);

-- 4. Tabla de Productos del Catálogo
CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NULL,
    sku VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT NULL,
    presentacion VARCHAR(150) DEFAULT 'Unidad',
    material VARCHAR(150) DEFAULT 'Polipropileno',
    precio DECIMAL(10,2) NULL DEFAULT NULL,
    stock_estado VARCHAR(30) DEFAULT 'en_stock',
    biodegradable TINYINT(1) DEFAULT 0,
    imagen_url VARCHAR(500) DEFAULT 'assets/images/productos/default.png',
    destacado TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS idx_productos_cat ON productos(categoria_id);
CREATE INDEX IF NOT EXISTS idx_productos_sku ON productos(sku);
CREATE INDEX IF NOT EXISTS idx_productos_stock ON productos(stock_estado);
CREATE INDEX IF NOT EXISTS idx_productos_destacado ON productos(destacado);

-- 5. Tabla del Libro de Reclamaciones Virtual (Normativa INDECOPI D.S. 011-2011-PCM)
CREATE TABLE IF NOT EXISTS libro_reclamaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_hoja VARCHAR(50) NOT NULL UNIQUE,
    tipo_documento VARCHAR(10) NOT NULL DEFAULT 'DNI',
    numero_documento VARCHAR(20) NOT NULL,
    nombre_completo VARCHAR(255) NOT NULL,
    telefono VARCHAR(30) NULL,
    email VARCHAR(150) NOT NULL,
    departamento VARCHAR(100) NOT NULL DEFAULT 'Lima',
    provincia VARCHAR(100) NOT NULL DEFAULT 'Lima',
    distrito VARCHAR(100) NOT NULL,
    direccion TEXT NOT NULL,
    es_menor TINYINT(1) DEFAULT 0,
    nombre_tutor VARCHAR(150) DEFAULT NULL,
    tipo_bien VARCHAR(20) NOT NULL DEFAULT 'Producto',
    monto_reclamado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    descripcion_bien TEXT NOT NULL,
    tipo_reclamacion VARCHAR(20) NOT NULL DEFAULT 'Reclamo',
    detalle_reclamacion TEXT NOT NULL,
    pedido_consumidor TEXT NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'Pendiente',
    respuesta_proveedor TEXT NULL,
    fecha_respuesta DATETIME NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS idx_reclama_codigo ON libro_reclamaciones(codigo_hoja);
CREATE INDEX IF NOT EXISTS idx_reclama_doc ON libro_reclamaciones(numero_documento);
CREATE INDEX IF NOT EXISTS idx_reclama_email ON libro_reclamaciones(email);
CREATE INDEX IF NOT EXISTS idx_reclama_estado ON libro_reclamaciones(estado);
CREATE INDEX IF NOT EXISTS idx_reclama_fecha ON libro_reclamaciones(creado_en);

-- 6. Tabla de Cotizaciones Corporativas B2B
CREATE TABLE IF NOT EXISTS cotizaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_cotizacion VARCHAR(50) NOT NULL UNIQUE,
    usuario_id INT NULL,
    tipo_comprobante VARCHAR(20) DEFAULT 'Factura',
    documento VARCHAR(20) NOT NULL,
    nombre_cliente VARCHAR(255) NOT NULL,
    telefono VARCHAR(30) NULL,
    email VARCHAR(150) NULL DEFAULT '',
    destino VARCHAR(100) DEFAULT 'Lima Metropolitana',
    departamento VARCHAR(100) DEFAULT 'Lima',
    provincia VARCHAR(100) DEFAULT 'Lima',
    distrito VARCHAR(100) NULL,
    direccion TEXT NULL,
    items LONGTEXT NOT NULL,
    total_items INT NOT NULL DEFAULT 0,
    notas TEXT NULL,
    estado VARCHAR(30) DEFAULT 'Pendiente',
    enviado_whatsapp TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cotizaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS idx_cotiz_codigo ON cotizaciones(codigo_cotizacion);
CREATE INDEX IF NOT EXISTS idx_cotiz_usuario ON cotizaciones(usuario_id);
CREATE INDEX IF NOT EXISTS idx_cotiz_doc ON cotizaciones(documento);
CREATE INDEX IF NOT EXISTS idx_cotiz_email ON cotizaciones(email);
CREATE INDEX IF NOT EXISTS idx_cotiz_estado ON cotizaciones(estado);
CREATE INDEX IF NOT EXISTS idx_cotiz_fecha ON cotizaciones(creado_en);

-- 7. Tabla de Configuración General del Negocio y Banners
CREATE TABLE IF NOT EXISTS configuracion (
    clave VARCHAR(100) PRIMARY KEY,
    valor TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

