-- ====================================================================
-- PLATAFORMA DESCARTABLES PERUANOS
-- ESQUEMA CANÓNICO UNIFICADO DE BASE DE DATOS MYSQL (v2026.1)
-- ====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Tabla de Control de Migraciones
CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `version` VARCHAR(100) NOT NULL UNIQUE,
    `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de Usuarios (Soporte DNI, RUC, CE - Clientes y Administradores)
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tipo_documento` VARCHAR(10) DEFAULT 'DNI',
    `numero_documento` VARCHAR(20) NOT NULL UNIQUE,
    `nombre_razon_social` VARCHAR(255) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `telefono` VARCHAR(30) NULL,
    `departamento` VARCHAR(100) DEFAULT 'Lima',
    `provincia` VARCHAR(100) DEFAULT 'Lima',
    `distrito` VARCHAR(100) NULL,
    `direccion` TEXT NULL,
    `rol` VARCHAR(20) DEFAULT 'cliente',
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_usuarios_doc` (`numero_documento`),
    INDEX `idx_usuarios_email` (`email`),
    INDEX `idx_usuarios_rol` (`rol`),
    INDEX `idx_usuarios_fecha` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabla de Categorías del Catálogo
CREATE TABLE IF NOT EXISTS `categorias` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) NOT NULL UNIQUE,
    `descripcion` TEXT NULL,
    `icono` VARCHAR(50) DEFAULT 'box',
    `color` VARCHAR(100) DEFAULT 'from-amber-600/20 to-orange-600/20',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_categorias_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabla de Productos del Catálogo
CREATE TABLE IF NOT EXISTS `productos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `categoria_id` INT NULL,
    `sku` VARCHAR(50) NOT NULL UNIQUE,
    `nombre` VARCHAR(255) NOT NULL,
    `descripcion` TEXT NULL,
    `presentacion` VARCHAR(150) DEFAULT 'Unidad',
    `material` VARCHAR(150) DEFAULT 'Polipropileno',
    `precio` DECIMAL(10,2) NULL DEFAULT NULL,
    `stock_estado` VARCHAR(30) DEFAULT 'en_stock',
    `biodegradable` TINYINT(1) DEFAULT 0,
    `imagen_url` VARCHAR(500) DEFAULT 'assets/images/productos/default.png',
    `destacado` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_productos_cat` (`categoria_id`),
    INDEX `idx_productos_sku` (`sku`),
    INDEX `idx_productos_stock` (`stock_estado`),
    INDEX `idx_productos_destacado` (`destacado`),
    INDEX `idx_productos_precio` (`precio`),
    CONSTRAINT `fk_productos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabla del Libro de Reclamaciones Virtual (Normativa INDECOPI D.S. 011-2011-PCM)
CREATE TABLE IF NOT EXISTS `libro_reclamaciones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `codigo_hoja` VARCHAR(50) NOT NULL UNIQUE,
    `tipo_documento` VARCHAR(10) NOT NULL DEFAULT 'DNI',
    `numero_documento` VARCHAR(20) NOT NULL,
    `nombre_completo` VARCHAR(255) NOT NULL,
    `telefono` VARCHAR(30) NULL,
    `email` VARCHAR(150) NOT NULL,
    `departamento` VARCHAR(100) NOT NULL DEFAULT 'Lima',
    `provincia` VARCHAR(100) NOT NULL DEFAULT 'Lima',
    `distrito` VARCHAR(100) NOT NULL,
    `direccion` TEXT NOT NULL,
    `es_menor` TINYINT(1) DEFAULT 0,
    `nombre_tutor` VARCHAR(150) DEFAULT NULL,
    `tipo_bien` VARCHAR(20) NOT NULL DEFAULT 'Producto',
    `monto_reclamado` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `descripcion_bien` TEXT NOT NULL,
    `tipo_reclamacion` VARCHAR(20) NOT NULL DEFAULT 'Reclamo',
    `detalle_reclamacion` TEXT NOT NULL,
    `pedido_consumidor` TEXT NOT NULL,
    `estado` VARCHAR(30) NOT NULL DEFAULT 'Pendiente',
    `respuesta_proveedor` TEXT NULL,
    `fecha_respuesta` DATETIME NULL,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_reclama_codigo` (`codigo_hoja`),
    INDEX `idx_reclama_doc` (`numero_documento`),
    INDEX `idx_reclama_email` (`email`),
    INDEX `idx_reclama_estado` (`estado`),
    INDEX `idx_reclama_fecha` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabla de Cotizaciones Corporativas B2B
CREATE TABLE IF NOT EXISTS `cotizaciones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `codigo_cotizacion` VARCHAR(50) NOT NULL UNIQUE,
    `usuario_id` INT NULL,
    `tipo_comprobante` VARCHAR(20) DEFAULT 'Factura',
    `documento` VARCHAR(20) NOT NULL,
    `nombre_cliente` VARCHAR(255) NOT NULL,
    `telefono` VARCHAR(30) NULL,
    `email` VARCHAR(150) NULL DEFAULT '',
    `destino` VARCHAR(100) DEFAULT 'Lima Metropolitana',
    `departamento` VARCHAR(100) DEFAULT 'Lima',
    `provincia` VARCHAR(100) DEFAULT 'Lima',
    `distrito` VARCHAR(100) NULL,
    `direccion` TEXT NULL,
    `items` LONGTEXT NOT NULL,
    `total_items` INT NOT NULL DEFAULT 0,
    `notas` TEXT NULL,
    `estado` VARCHAR(30) DEFAULT 'Pendiente',
    `enviado_whatsapp` TINYINT(1) DEFAULT 1,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cotiz_codigo` (`codigo_cotizacion`),
    INDEX `idx_cotiz_usuario` (`usuario_id`),
    INDEX `idx_cotiz_doc` (`documento`),
    INDEX `idx_cotiz_email` (`email`),
    INDEX `idx_cotiz_estado` (`estado`),
    INDEX `idx_cotiz_fecha` (`creado_en`),
    CONSTRAINT `fk_cotizaciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabla de Configuración General del Negocio y Banners
CREATE TABLE IF NOT EXISTS `configuracion` (
    `clave` VARCHAR(100) PRIMARY KEY,
    `valor` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Tabla de Secuencias Atómicas para Correlativos (Concurrencia F10)
CREATE TABLE IF NOT EXISTS `secuencias` (
    `tipo` VARCHAR(50) NOT NULL,
    `anio` INT NOT NULL,
    `ultimo_numero` INT NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`tipo`, `anio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Tabla de Idempotencia (Prevención de Duplicados F10)
CREATE TABLE IF NOT EXISTS `idempotencia` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `scope` VARCHAR(100) NOT NULL,
    `clave` VARCHAR(100) NOT NULL,
    `usuario_id` INT NULL,
    `documento` VARCHAR(20) NULL,
    `request_hash` VARCHAR(64) NOT NULL,
    `codigo_resultado` VARCHAR(50) NOT NULL,
    `response_payload` LONGTEXT NOT NULL,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_idemp_scope_clave` (`scope`, `clave`),
    INDEX `idx_idemp_hash` (`request_hash`),
    INDEX `idx_idemp_usuario` (`usuario_id`),
    INDEX `idx_idemp_fecha` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Tabla de Rate Limiting y Control de Abuso (F11)
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `identifier` VARCHAR(100) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `attempts` INT NOT NULL DEFAULT 1,
    `window_start` INT NOT NULL,
    `blocked_until` INT NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_rate_id_action` (`identifier`, `action`),
    INDEX `idx_rate_blocked` (`blocked_until`),
    INDEX `idx_rate_action_window` (`action`, `window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ====================================================================
-- DATOS SEMILLA INICIALES
-- ====================================================================

-- Categorías
INSERT INTO `categorias` (`id`, `nombre`, `slug`, `descripcion`, `icono`, `color`) VALUES 
(1, 'Productos Pamolsa', 'pamolsa', 'Envases térmicos, bisagras, domos y vasos para gastronomía.', 'coffee', 'from-amber-600/20 to-orange-600/20'),
(2, 'Línea Proplas / Barrera', 'proplas-barrera', 'Bolsas al vacío, bilaminadas, films y empaques industriales.', 'shield-check', 'from-blue-600/20 to-cyan-600/20'),
(3, 'Cubiertos Descartables', 'cubiertos', 'Cucharas, tenedores y cuchillos reforzados y biodegradables.', 'utensils', 'from-stone-600/20 to-zinc-600/20'),
(4, 'Servilletas y Papeles', 'servilletas', 'Servilletas cocktail, interfoliadas, bobinas y papel institucional.', 'file-text', 'from-emerald-600/20 to-teal-600/20'),
(5, 'Productos de Limpieza e Higiene', 'limpieza', 'Bolsas de basura industriales, guantes de nitrilo y desinfectantes.', 'sparkles', 'from-purple-600/20 to-indigo-600/20'),
(6, 'Novedades y Biodegradables', 'novedades', 'Línea eco-amigable de bagazo de caña de azúcar y bowls kraft.', 'leaf', 'from-lime-600/20 to-green-600/20')
ON DUPLICATE KEY UPDATE `nombre`=VALUES(`nombre`), `slug`=VALUES(`slug`), `descripcion`=VALUES(`descripcion`), `icono`=VALUES(`icono`), `color`=VALUES(`color`);

-- Productos representativos
INSERT INTO `productos` (`id`, `categoria_id`, `sku`, `nombre`, `descripcion`, `presentacion`, `material`, `precio`, `stock_estado`, `biodegradable`, `imagen_url`, `destacado`) VALUES
(1, 1, 'PAM-CT4', 'Contenedor Térmico CT-4 Pamolsa', 'Envase térmico espumado con bisagra integrada. Ideal para transporte de menús, caldos y segundos calientes.', 'Caja x 200 und', 'Poliestireno Expandido (EPS)', 28.50, 'en_stock', 0, 'https://images.unsplash.com/photo-1578916171728-46686eac8d58?auto=format&fit=crop&w=700&q=80', 1),
(2, 1, 'PAM-V8', 'Vaso Térmico 8 oz Pamolsa', 'Vaso espumado ergonómico para café, té y bebidas calientes con excelente aislamiento térmico que evita quemaduras.', 'Caja x 1,000 und (40 pqts x 25 und)', 'Poliestireno Expandido (EPS)', 45.00, 'en_stock', 0, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=700&q=80', 1),
(3, 1, 'PAM-DOM-16', 'Domo Ensalada Transparente con Tapa 16 oz', 'Envase transparente de máxima claridad visual para ensaladas de frutas, repostería fina, postres y poke bowls.', 'Caja x 500 und', 'PET Cristal de Alta Claridad', 52.00, 'en_stock', 0, 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=700&q=80', 0),
(4, 2, 'PRO-VAC-2030', 'Bolsa de Vacío Alta Barrera 20x30 cm Proplas', 'Bolsa coextruida multicapa de alta barrera contra el oxígeno y la humedad. Máxima conservación de embutidos, quesos y carnes.', 'Millar (1,000 und)', 'Poliamida / Polietileno (PA/PE)', 120.00, 'en_stock', 0, 'https://images.unsplash.com/photo-1607344645866-009c320c5ab8?auto=format&fit=crop&w=700&q=80', 1),
(5, 2, 'PRO-FILM-18', 'Film Extensible Industrial 18 pulg x 1500 pies', 'Bobina de film stretch para embalaje y paletizado manual o semiautomático. Gran adherencia y resistencia al rasgado.', 'Caja x 4 bobinas', 'Polietileno Lineal (LLDPE)', 68.00, 'en_stock', 0, 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=700&q=80', 0),
(6, 2, 'PRO-BIL-1525', 'Bolsa Bilaminada Metalizada Stand-Up Pouch 250g', 'Empaque tipo ''doypack'' con fondo fuelle y zipper resellable. Protege contra radiación solar y conserva aromas de café o frutos secos.', 'Caja x 500 und', 'BOPP Mate / PET Metalizado / PE', 95.00, 'en_stock', 0, 'https://images.unsplash.com/photo-1589365278144-c9e705f843ba?auto=format&fit=crop&w=700&q=80', 0),
(7, 3, 'CUB-TEN-PES', 'Tenedor Descartable Pesado Blanco', 'Tenedor de mesa con mango ergonómico y dientes firmes de alto gramaje. No se flecta ante alimentos calientes o carnes.', 'Caja x 1,000 und (10 paquetes de 100 und)', 'Polipropileno Reforzado (PP)', 32.00, 'en_stock', 0, 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?auto=format&fit=crop&w=700&q=80', 1),
(8, 3, 'CUB-CUCH-PES', 'Cuchara Descartable Pesada Blanca', 'Cuchara sopera honda reforzada para guisos, sopas y postres en restaurantes de alta rotación.', 'Caja x 1,000 und (10 paquetes de 100 und)', 'Polipropileno Reforzado (PP)', 32.00, 'en_stock', 0, 'https://images.unsplash.com/photo-1616401784845-180882ba9ba8?auto=format&fit=crop&w=700&q=80', 0),
(9, 3, 'CUB-ECO-CANA', 'Kit Cubiertos Biodegradables Cuchara + Tenedor + Servilleta', 'Set enfundado 100% compostable fabricado a partir de fécula de maíz CPLA. Excelente opción eco para delivery corporativo.', 'Caja x 500 kits completos', 'Biopolímero CPLA Compostable', 75.00, 'en_stock', 1, 'https://images.unsplash.com/photo-1598971861713-54ad16a7e72e?auto=format&fit=crop&w=700&q=80', 1),
(10, 4, 'PAP-SERV-COC', 'Servilleta Cocktail Blanca 24x24 cm', 'Servilleta tissue de doble hoja suave y absorbente. Perfecta para bares, cafeterías, bodas y eventos corporativos.', 'Fardo x 4,000 und (40 paquetes de 100 und)', 'Papel Celulosa Virgen 100%', 42.00, 'en_stock', 1, 'https://images.unsplash.com/photo-1583947215259-38e31be8751f?auto=format&fit=crop&w=700&q=80', 0),
(11, 4, 'PAP-SERV-INT', 'Servilleta Interfoliada Tipo Dispensador', 'Servilleta doblada en V diseñada para dispensadores de mesa. Reduce el consumo y desperdicio hasta en un 35%.', 'Caja x 2,400 und (12 paquetes x 200 und)', 'Papel Celulosa Virgen', 38.00, 'en_stock', 1, 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=700&q=80', 1),
(12, 4, 'PAP-TOA-BOB', 'Papel Toalla Bobina Industrial 250 metros', 'Rollo continuo de toalla para cocinas profesionales, hoteles, laboratorios y áreas de alta concurrencia.', 'Fardo x 2 bobinas (500 metros totales)', 'Papel Celulosa Extra Resistente', 48.00, 'en_stock', 1, 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=700&q=80', 0),
(13, 5, 'LIM-BOL-140L', 'Bolsa para Basura Negra 140 Litros (35x50 pulg)', 'Bolsa de polietileno de alto micraje para residuos pesados gastronómicos, tachos grandes y condominios.', 'Fardo x 100 und (10 paquetes x 10 und)', 'Polietileno Recuperado de Alta Resistencia (2.0 mil)', 55.00, 'en_stock', 0, 'https://images.unsplash.com/photo-1530587191325-3db32d826c18?auto=format&fit=crop&w=700&q=80', 1),
(14, 5, 'LIM-GUA-NIT', 'Guantes de Nitrilo Azul Sin Polvo Grado Alimentario', 'Guantes descartables hipoalergénicos de alta sensibilidad táctil y resistencia química frente a aceites y grasas.', 'Caja x 100 unidades (Tallas S, M, L)', 'Nitrilo Sintético Puro', 26.00, 'en_stock', 0, 'https://images.unsplash.com/photo-1584744982491-665216d95f8b?auto=format&fit=crop&w=700&q=80', 0),
(15, 5, 'LIM-DES-LEJ', 'Hipoclorito de Sodio 5.5% Bidón 5 Galones', 'Desinfectante clorado concentrado para sanitización de superficies de corte, pisos, cámaras frigoríficas y vajilla.', 'Bidón x 5 Galones (19 Litros)', 'Solución Clorada Concentrada', 35.00, 'en_stock', 0, 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=700&q=80', 0),
(16, 6, 'BIO-BWL-KR750', 'Bowl Kraft Redondo 750 ml con Tapa PET', 'Bowl ecológico elaborado en cartón kraft virgen con revestimiento anti-grasa. Incluye tapa transparente de ajuste perfecto.', 'Caja x 300 und (Bowls + Tapas)', 'Cartón Kraft Virgen + Recubrimiento PE Bio', 82.00, 'en_stock', 1, 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=700&q=80', 1),
(17, 6, 'BIO-CAN-HB', 'Hamburguesera Bagazo de Caña de Azúcar 6x6 pulg', 'Envase 100% compostable y vegetal fabricado con fibra residual de caña de azúcar peruana. No absorbe humedad ni grasa.', 'Caja x 500 und', 'Bagazo de Caña de Azúcar 100% Natural', 65.00, 'en_stock', 1, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=700&q=80', 1),
(18, 6, 'BIO-VAS-KRAF', 'Vaso de Polipapel Kraft Doble Pared 12 oz', 'Vaso térmico doble capa aislante de cartón kraft que elimina la necesidad de fajas térmicas auxiliares para café caliente.', 'Caja x 500 und', 'Cartón Kraft Virgen Certificado FSC', 58.00, 'en_stock', 1, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=700&q=80', 1)
ON DUPLICATE KEY UPDATE `nombre`=VALUES(`nombre`), `descripcion`=VALUES(`descripcion`), `presentacion`=VALUES(`presentacion`), `material`=VALUES(`material`), `precio`=VALUES(`precio`), `stock_estado`=VALUES(`stock_estado`), `biodegradable`=VALUES(`biodegradable`), `imagen_url`=VALUES(`imagen_url`), `destacado`=VALUES(`destacado`);

-- Configuración de la Empresa
INSERT INTO `configuracion` (`clave`, `valor`) VALUES 
('enable_redirects', 'false'),
('razon_social', 'DESCARTABLES PERUANOS S.A.C.'),
('nombre_comercial', 'Descartables Peruanos'),
('ruc', '20601234567'),
('direccion', 'Av. Alejandro Bertello 732-C, Cercado de Lima, Lima, Perú'),
('horario', 'Lunes a Viernes: 8:00 AM - 6:00 PM | Sábados: 8:30 AM - 1:00 PM'),
('whatsapp_principal', '+51 900 000 000'),
('whatsapp_secundario', '+51 900 000 002'),
('telefono_central', '(01) 000-0000'),
('email_ventas', 'ventas@descartablesperuanos.pe'),
('email_cotizaciones', 'cotizaciones@descartablesperuanos.pe'),
('facebook_url', 'https://facebook.com/descartablesperuanos'),
('instagram_url', 'https://instagram.com/descartablesperuanos')
ON DUPLICATE KEY UPDATE `valor`=VALUES(`valor`);
