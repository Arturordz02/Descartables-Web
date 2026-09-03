-- ====================================================================
-- PLATAFORMA DESCARTABLES PERUANOS
-- ESQUEMA DE BASE DE DATOS MYSQL (NORMATIVA PERÚ - SUNAT / INDECOPI)
-- ====================================================================

CREATE DATABASE IF NOT EXISTS descartables_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE descartables_db;

-- 1. Tabla de Usuarios (Soporte para DNI, RUC y Carné de Extranjería en Perú)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento ENUM('DNI', 'RUC', 'CE') DEFAULT 'DNI',
    numero_documento VARCHAR(20) UNIQUE NOT NULL,
    nombre_razon_social VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    departamento VARCHAR(50) DEFAULT 'Lima',
    provincia VARCHAR(50) DEFAULT 'Lima',
    distrito VARCHAR(50) DEFAULT 'Cercado de Lima',
    direccion VARCHAR(255),
    rol ENUM('admin', 'cliente') DEFAULT 'cliente',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_documento (numero_documento),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de Categorías
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    descripcion TEXT,
    icono VARCHAR(50) DEFAULT 'package',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabla de Productos
CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT,
    sku VARCHAR(50) UNIQUE NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    presentacion VARCHAR(100) DEFAULT 'Caja x 1,000 und',
    material VARCHAR(100),
    biodegradable BOOLEAN DEFAULT FALSE,
    imagen_url VARCHAR(255),
    destacado BOOLEAN DEFAULT FALSE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    INDEX idx_sku (sku),
    INDEX idx_material (material),
    INDEX idx_biodegradable (biodegradable)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabla del Libro de Reclamaciones Virtual (Normativa D.S. 011-2011-PCM INDECOPI Perú)
CREATE TABLE IF NOT EXISTS libro_reclamaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_hoja VARCHAR(30) UNIQUE NOT NULL, -- Ej: REC-2026-00001
    tipo_documento ENUM('DNI', 'CE', 'RUC', 'PASAPORTE') NOT NULL,
    numero_documento VARCHAR(20) NOT NULL,
    nombre_completo VARCHAR(150) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    departamento VARCHAR(50) NOT NULL,
    provincia VARCHAR(50) NOT NULL,
    distrito VARCHAR(50) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    es_menor BOOLEAN DEFAULT FALSE,
    nombre_tutor VARCHAR(150) DEFAULT NULL,
    tipo_bien ENUM('Producto', 'Servicio') DEFAULT 'Producto',
    monto_reclamado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    descripcion_bien TEXT NOT NULL,
    tipo_reclamacion ENUM('Reclamo', 'Queja') NOT NULL,
    detalle_reclamacion TEXT NOT NULL,
    pedido_consumidor TEXT NOT NULL,
    estado ENUM('Pendiente', 'En Proceso', 'Atendido') DEFAULT 'Pendiente',
    respuesta_proveedor TEXT DEFAULT NULL,
    fecha_respuesta DATETIME DEFAULT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo_hoja),
    INDEX idx_rec_doc (numero_documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabla de Cotizaciones Guardadas / Historial
CREATE TABLE IF NOT EXISTS cotizaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_cotizacion VARCHAR(30) UNIQUE NOT NULL,
    usuario_id INT NULL,
    tipo_comprobante ENUM('Boleta', 'Factura') DEFAULT 'Factura',
    documento VARCHAR(20) NOT NULL,
    nombre_cliente VARCHAR(150) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    destino VARCHAR(100) DEFAULT 'Lima Metropolitana',
    detalle_items JSON NOT NULL,
    total_items INT NOT NULL DEFAULT 0,
    enviado_whatsapp BOOLEAN DEFAULT TRUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- DATOS SEMILLA INICIALES
-- ====================================================================

-- Categorías según requerimiento comercial
INSERT INTO categorias (id, nombre, slug, descripcion, icono) VALUES 
(1, 'Productos Pamolsa', 'pamolsa', 'Envases térmicos, bisagras, domos y vasos para la industria gastronómica.', 'coffee'),
(2, 'Línea Proplas / Barrera', 'proplas-barrera', 'Bolsas al vacío, bilaminadas, films y empaques industriales de alta barrera.', 'shield'),
(3, 'Cubiertos Descartables', 'cubiertos', 'Cucharas, tenedores y cuchillos reforzados, cristal y biodegradables.', 'utensils'),
(4, 'Servilletas y Papeles', 'servilletas', 'Servilletas cocktail, interfoliadas, bobinas y papel toalla institucional.', 'scroll'),
(5, 'Productos de Limpieza e Higiene', 'limpieza', 'Bolsas de basura de alto micraje, guantes de nitrilo/látex y desinfectantes.', 'sparkles'),
(6, 'Novedades y Biodegradables', 'novedades', 'Línea eco-amigable de bagazo de caña de azúcar, bowls kraft y biopolímeros.', 'leaf')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), slug=VALUES(slug);

-- Productos representativos del catálogo peruano
INSERT INTO productos (id, categoria_id, sku, nombre, descripcion, presentacion, material, biodegradable, imagen_url, destacado) VALUES
(1, 1, 'PAM-CT4', 'Contenedor Térmico CT-4 Térmico Pamolsa', 'Contenedor térmico espumado con tapa bisagra para delivery de almuerzos y menús calientes.', 'Caja x 200 und', 'Poliestireno Expandido (EPS)', FALSE, 'https://images.unsplash.com/photo-1578916171728-46686eac8d58?auto=format&fit=crop&w=600&q=80', TRUE),
(2, 1, 'PAM-V8', 'Vaso Térmico 8 oz Pamolsa', 'Vaso espumado para bebidas calientes como café, té o chocolate. Excelente retención de calor.', 'Caja x 1,000 und', 'Poliestireno Expandido (EPS)', FALSE, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=600&q=80', TRUE),
(3, 1, 'PAM-DOM-16', 'Domo Ensalada Transparente con Tapa 16 oz', 'Envase plástico tipo domo de alta transparencia para repostería, ensaladas de frutas y poke.', 'Caja x 500 und', 'PET Cristal', FALSE, 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=600&q=80', FALSE),

(4, 2, 'PRO-VAC-2030', 'Bolsa de Vacío Alta Barrera 20x30 cm Proplas', 'Bolsa coextruida para conservación al vacío de carnes, quesos y congelados sin pérdida de peso.', 'Millar (1,000 und)', 'Poliamida / Polietileno (PA/PE)', FALSE, 'https://images.unsplash.com/photo-1607344645866-009c320c5ab8?auto=format&fit=crop&w=600&q=80', TRUE),
(5, 2, 'PRO-FILM-18', 'Film Extensible Industrial 18 pulgadas x 1500 pies', 'Film stretch para paletizado y embalaje de carga pesada, excelente elongación y resistencia.', 'Caja x 4 rollos', 'Polietileno Lineal LLDPE', FALSE, 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=600&q=80', FALSE),
(6, 2, 'PRO-BIL-1525', 'Bolsa Bilaminada Metalizada Stand-Up Pouch', 'Bolsa con zipper y base para frutos secos, café y polvos, alta protección contra luz y humedad.', 'Caja x 500 und', 'BOPP / PET Met / PE', FALSE, 'https://images.unsplash.com/photo-1589365278144-c9e705f843ba?auto=format&fit=crop&w=600&q=80', FALSE),

(7, 3, 'CUB-TEN-PES', 'Tenedor Descartable Pesado Blanco', 'Tenedor de mesa reforzado extra resistente, no se dobla ni quiebra con carnes o pastas calientes.', 'Caja x 1,000 und (10 pqts)', 'Polipropileno Reforzado (PP)', FALSE, 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?auto=format&fit=crop&w=600&q=80', TRUE),
(8, 3, 'CUB-CUCH-PES', 'Cuchara Descartable Pesada Blanca', 'Cuchara sopera robusta de grado alimentario para restaurantes y servicios de catering.', 'Caja x 1,000 und (10 pqts)', 'Polipropileno Reforzado (PP)', FALSE, 'https://images.unsplash.com/photo-1616401784845-180882ba9ba8?auto=format&fit=crop&w=600&q=80', FALSE),
(9, 3, 'CUB-ECO-CANA', 'Set Cubiertos Biodegradables Cuchara + Tenedor + Servilleta', 'Kit ecológico enfundado para delivery, elaborado con biopolímero compostable a base de fécula.', 'Caja x 500 kits', 'Fécula de Maíz (CPLA)', TRUE, 'https://images.unsplash.com/photo-1598971861713-54ad16a7e72e?auto=format&fit=crop&w=600&q=80', TRUE),

(10, 4, 'PAP-SERV-COC', 'Servilleta Cocktail Blanca 24x24 cm', 'Servilleta de papel tissue suave de doble hoja para barras, cafeterías y eventos corporativos.', 'Fardo x 4,000 und (40 pqts)', 'Papel Celulosa 100% Virgen', TRUE, 'https://images.unsplash.com/photo-1583947215259-38e31be8751f?auto=format&fit=crop&w=600&q=80', FALSE),
(11, 4, 'PAP-SERV-INT', 'Servilleta Interfoliada Tipo Dispensador', 'Servilleta doblada en V de dispensación hoja por hoja para ahorro de consumo en mesas.', 'Caja x 2,400 und', 'Papel Celulosa', TRUE, 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=600&q=80', TRUE),
(12, 4, 'PAP-TOA-BOB', 'Papel Toalla Bobina Industrial 250 metros', 'Rollo de papel toalla de alto rendimiento para dispensadores en cocinas y baños de alto tráfico.', 'Fardo x 2 bobinas', 'Papel Celulosa Virgen', TRUE, 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=600&q=80', FALSE),

(13, 5, 'LIM-BOL-140L', 'Bolsa para Basura Negra 140 Litros (35x50 pulg)', 'Bolsa extra gruesa de 2.0 micras para residuos pesados de restaurantes, industrias y condominios.', 'Fardo x 100 und', 'Polietileno Recuperado HD/LD', FALSE, 'https://images.unsplash.com/photo-1530587191325-3db32d826c18?auto=format&fit=crop&w=600&q=80', TRUE),
(14, 5, 'LIM-GUA-NIT', 'Guantes de Nitrilo Azul Grado Alimentario Caja x 100', 'Guantes sin polvo hipoalergénicos para manipulación higiénica de alimentos y preparación.', 'Caja x 100 und', 'Nitrilo Sintético', FALSE, 'https://images.unsplash.com/photo-1584744982491-665216d95f8b?auto=format&fit=crop&w=600&q=80', FALSE),
(15, 5, 'LIM-DES-LEJ', 'Hipoclorito de Sodio 5.5% Bidón 5 Galones', 'Desinfectante concentrado para desinfección de superficies, verduras y pisos en hostelería.', 'Bidón x 5 Galones', 'Químico Desinfectante', FALSE, 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=600&q=80', FALSE),

(16, 6, 'BIO-BWL-KR750', 'Bowl de Cartón Kraft para Ensaladas 750 ml + Tapa PET', 'Bowl ecológico resistente a grasas y líquidos calientes, incluye tapa hermética transparente.', 'Caja x 300 und', 'Cartón Kraft + Polietileno Ecológico', TRUE, 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=600&q=80', TRUE),
(17, 6, 'BIO-CAN-HB', 'Hamburguesera de Bagazo de Caña de Azúcar 6x6 pulgadas', 'Envase térmico 100% compostable derivado de la caña de azúcar, apto para microondas y congelador.', 'Caja x 500 und', 'Bagazo de Caña de Azúcar', TRUE, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=600&q=80', TRUE),
(18, 6, 'BIO-VAS-KRAF', 'Vaso de Polipapel Kraft Doble Capa 12 oz', 'Vaso térmico doble pared que no quema la mano, no requiere faja aislante. Ideal para cafeterías de especialidad.', 'Caja x 500 und', 'Papel Kraft Biodegradable', TRUE, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=600&q=80', TRUE)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), descripcion=VALUES(descripcion);

-- Usuario Administrador y Clientes de Prueba (Contraseña: password123)
INSERT INTO usuarios (tipo_documento, numero_documento, nombre_razon_social, email, password, telefono, departamento, provincia, distrito, direccion, rol) VALUES
('RUC', '20601234567', 'DESCARTABLES PERUANOS S.A.C.', 'ventas@descartablesperuanos.pe', '$2y$10$4bnulbFW64U7Zc3F/k7vs.VJxUgavbsU0gPZ7kWMdBXDvF545S4Ky', '(01) 564-1450', 'Lima', 'Lima', 'Cercado de Lima', 'Av. Alejandro Bertello 732-C', 'admin'),
('DNI', '45879632', 'Juan Carlos Pérez Mendoza', 'cliente.demo@gmail.com', '$2y$10$4bnulbFW64U7Zc3F/k7vs.VJxUgavbsU0gPZ7kWMdBXDvF545S4Ky', '994195430', 'Lima', 'Lima', 'Miraflores', 'Av. Larco 450 Dpto 302', 'cliente'),
('DNI', '10458796', 'Distribuidora Demo Gastronómica', 'cliente@demo.pe', '$2y$10$4bnulbFW64U7Zc3F/k7vs.VJxUgavbsU0gPZ7kWMdBXDvF545S4Ky', '994195430', 'Lima', 'Lima', 'Miraflores', 'Av. Larco 450', 'cliente')
ON DUPLICATE KEY UPDATE password=VALUES(password), nombre_razon_social=VALUES(nombre_razon_social);


