<?php
/**
 * Conexión a la Base de Datos MySQL con PDO y Auto-Inicialización de Esquema
 * Plataforma Descartables Peruanos
 */

require_once __DIR__ . '/config.php';

function ensureDatabaseInitialized($pdo) {
    static $initialized = false;
    if ($initialized || !$pdo) return;

    try {
        // 1. Tabla categorias
        $pdo->exec("CREATE TABLE IF NOT EXISTS categorias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(150) NOT NULL,
            slug VARCHAR(150) NOT NULL UNIQUE,
            descripcion TEXT NULL,
            icono VARCHAR(50) DEFAULT 'box',
            color VARCHAR(100) DEFAULT 'from-amber-600/20 to-orange-600/20',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Auto-migraciones para tabla categorias si ya existía sin columnas icono/color
        try {
            $colIcono = $pdo->query("SHOW COLUMNS FROM categorias LIKE 'icono'")->fetch();
            if (!$colIcono) {
                $pdo->exec("ALTER TABLE categorias ADD COLUMN icono VARCHAR(50) DEFAULT 'box'");
            }
        } catch (Exception $e) {}

        try {
            $colColor = $pdo->query("SHOW COLUMNS FROM categorias LIKE 'color'")->fetch();
            if (!$colColor) {
                $pdo->exec("ALTER TABLE categorias ADD COLUMN color VARCHAR(100) DEFAULT 'from-amber-600/20 to-orange-600/20'");
            }
        } catch (Exception $e) {}

        // Seed inicial de categorias si está vacía
        $countCat = $pdo->query("SELECT COUNT(*) as c FROM categorias")->fetch();
        if ((int)($countCat['c'] ?? 0) === 0) {
            $baseCategories = [
                [1, 'Productos Pamolsa', 'pamolsa', 'Envases térmicos, bisagras, domos y vasos para gastronomía.', 'coffee', 'from-amber-600/20 to-orange-600/20'],
                [2, 'Línea Proplas / Barrera', 'proplas-barrera', 'Bolsas al vacío, bilaminadas, films y empaques industriales.', 'shield-check', 'from-blue-600/20 to-cyan-600/20'],
                [3, 'Cubiertos Descartables', 'cubiertos', 'Cucharas, tenedores y cuchillos reforzados y biodegradables.', 'utensils', 'from-stone-600/20 to-zinc-600/20'],
                [4, 'Servilletas y Papeles', 'servilletas', 'Servilletas cocktail, interfoliadas, bobinas y papel institucional.', 'file-text', 'from-emerald-600/20 to-teal-600/20'],
                [5, 'Productos de Limpieza e Higiene', 'limpieza', 'Bolsas de basura industriales, guantes de nitrilo y desinfectantes.', 'sparkles', 'from-purple-600/20 to-indigo-600/20'],
                [6, 'Novedades y Biodegradables', 'novedades', 'Línea eco-amigable de bagazo de caña de azúcar y bowls kraft.', 'leaf', 'from-lime-600/20 to-green-600/20']
            ];
            try {
                $stmt = $pdo->prepare("INSERT INTO categorias (id, nombre, slug, descripcion, icono, color) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($baseCategories as $bc) {
                    $stmt->execute($bc);
                }
            } catch (Exception $e) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO categorias (id, nombre, slug, descripcion) VALUES (?, ?, ?, ?)");
                    foreach ($baseCategories as $bc) {
                        $stmt->execute([$bc[0], $bc[1], $bc[2], $bc[3]]);
                    }
                } catch (Exception $e2) {}
            }
        }

        // 2. Tabla productos
        $pdo->exec("CREATE TABLE IF NOT EXISTS productos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            categoria_id INT NOT NULL,
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
            INDEX idx_cat (categoria_id),
            INDEX idx_sku (sku)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Auto-migraciones para tabla productos
        try {
            $colPrecio = $pdo->query("SHOW COLUMNS FROM productos LIKE 'precio'")->fetch();
            if (!$colPrecio) {
                $pdo->exec("ALTER TABLE productos ADD COLUMN precio DECIMAL(10,2) NULL DEFAULT NULL AFTER material");
            }
        } catch (Exception $e) {}

        try {
            $colStock = $pdo->query("SHOW COLUMNS FROM productos LIKE 'stock_estado'")->fetch();
            if (!$colStock) {
                $pdo->exec("ALTER TABLE productos ADD COLUMN stock_estado VARCHAR(30) DEFAULT 'en_stock' AFTER precio");
            }
        } catch (Exception $e) {}

        try {
            $colBio = $pdo->query("SHOW COLUMNS FROM productos LIKE 'biodegradable'")->fetch();
            if (!$colBio) {
                $pdo->exec("ALTER TABLE productos ADD COLUMN biodegradable TINYINT(1) DEFAULT 0");
            }
        } catch (Exception $e) {}

        try {
            $colDest = $pdo->query("SHOW COLUMNS FROM productos LIKE 'destacado'")->fetch();
            if (!$colDest) {
                $pdo->exec("ALTER TABLE productos ADD COLUMN destacado TINYINT(1) DEFAULT 0");
            }
        } catch (Exception $e) {}

        try {
            $colImg = $pdo->query("SHOW COLUMNS FROM productos LIKE 'imagen_url'")->fetch();
            if (!$colImg) {
                $pdo->exec("ALTER TABLE productos ADD COLUMN imagen_url VARCHAR(500) DEFAULT 'assets/images/productos/default.png'");
            }
        } catch (Exception $e) {}

        // Seed inicial de productos si está vacía
        $countProd = $pdo->query("SELECT COUNT(*) as c FROM productos")->fetch();
        if ((int)($countProd['c'] ?? 0) === 0) {
            $baseProducts = [
                [1, 1, 'PAM-CT4', 'Contenedor Térmico CT-4 Pamolsa', 'Envase térmico espumado con bisagra integrada. Ideal para transporte de menús, caldos y segundos calientes.', 'Caja x 200 und', 'Poliestireno Expandido (EPS)', 28.50, 0, 'https://images.unsplash.com/photo-1578916171728-46686eac8d58?auto=format&fit=crop&w=700&q=80', 1],
                [2, 1, 'PAM-V8', 'Vaso Térmico 8 oz Pamolsa', 'Vaso espumado ergonómico para café, té y bebidas calientes con excelente aislamiento térmico que evita quemaduras.', 'Caja x 1,000 und (40 pqts x 25 und)', 'Poliestireno Expandido (EPS)', 45.00, 0, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=700&q=80', 1],
                [3, 1, 'PAM-DOM-16', 'Domo Ensalada Transparente con Tapa 16 oz', 'Envase transparente de máxima claridad visual para ensaladas de frutas, repostería fina, postres y poke bowls.', 'Caja x 500 und', 'PET Cristal de Alta Claridad', 52.00, 0, 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=700&q=80', 0],
                [4, 2, 'PRO-VAC-2030', 'Bolsa de Vacío Alta Barrera 20x30 cm Proplas', 'Bolsa coextruida multicapa de alta barrera contra el oxígeno y la humedad. Máxima conservación de embutidos, quesos y carnes.', 'Millar (1,000 und)', 'Poliamida / Polietileno (PA/PE)', 120.00, 0, 'https://images.unsplash.com/photo-1607344645866-009c320c5ab8?auto=format&fit=crop&w=700&q=80', 1],
                [5, 2, 'PRO-FILM-18', 'Film Extensible Industrial 18 pulg x 1500 pies', 'Bobina de film stretch para embalaje y paletizado manual o semiautomático. Gran adherencia y resistencia al rasgado.', 'Caja x 4 bobinas', 'Polietileno Lineal (LLDPE)', 68.00, 0, 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=700&q=80', 0],
                [6, 2, 'PRO-BIL-1525', 'Bolsa Bilaminada Metalizada Stand-Up Pouch 250g', 'Empaque tipo \'doypack\' con fondo fuelle y zipper resellable. Protege contra radiación solar y conserva aromas de café o frutos secos.', 'Caja x 500 und', 'BOPP Mate / PET Metalizado / PE', 95.00, 0, 'https://images.unsplash.com/photo-1589365278144-c9e705f843ba?auto=format&fit=crop&w=700&q=80', 0],
                [7, 3, 'CUB-TEN-PES', 'Tenedor Descartable Pesado Blanco', 'Tenedor de mesa con mango ergonómico y dientes firmes de alto gramaje. No se flecta ante alimentos calientes o carnes.', 'Caja x 1,000 und (10 paquetes de 100 und)', 'Polipropileno Reforzado (PP)', 32.00, 0, 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?auto=format&fit=crop&w=700&q=80', 1],
                [8, 3, 'CUB-CUCH-PES', 'Cuchara Descartable Pesada Blanca', 'Cuchara sopera honda reforzada para guisos, sopas y postres en restaurantes de alta rotación.', 'Caja x 1,000 und (10 paquetes de 100 und)', 'Polipropileno Reforzado (PP)', 32.00, 0, 'https://images.unsplash.com/photo-1616401784845-180882ba9ba8?auto=format&fit=crop&w=700&q=80', 0],
                [9, 3, 'CUB-ECO-CANA', 'Kit Cubiertos Biodegradables Cuchara + Tenedor + Servilleta', 'Set enfundado 100% compostable fabricado a partir de fécula de maíz CPLA. Excelente opción eco para delivery corporativo.', 'Caja x 500 kits completos', 'Biopolímero CPLA Compostable', 75.00, 1, 'https://images.unsplash.com/photo-1598971861713-54ad16a7e72e?auto=format&fit=crop&w=700&q=80', 1],
                [10, 4, 'PAP-SERV-COC', 'Servilleta Cocktail Blanca 24x24 cm', 'Servilleta tissue de doble hoja suave y absorbente. Perfecta para bares, cafeterías, bodas y eventos corporativos.', 'Fardo x 4,000 und (40 paquetes de 100 und)', 'Papel Celulosa Virgen 100%', 42.00, 1, 'https://images.unsplash.com/photo-1583947215259-38e31be8751f?auto=format&fit=crop&w=700&q=80', 0],
                [11, 4, 'PAP-SERV-INT', 'Servilleta Interfoliada Tipo Dispensador', 'Servilleta doblada en V diseñada para dispensadores de mesa. Reduce el consumo y desperdicio hasta en un 35%.', 'Caja x 2,400 und (12 paquetes x 200 und)', 'Papel Celulosa Virgen', 38.00, 1, 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=700&q=80', 1],
                [12, 4, 'PAP-TOA-BOB', 'Papel Toalla Bobina Industrial 250 metros', 'Rollo continuo de toalla para cocinas profesionales, hoteles, laboratorios y áreas de alta concurrencia.', 'Fardo x 2 bobinas (500 metros totales)', 'Papel Celulosa Extra Resistente', 48.00, 1, 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=700&q=80', 0],
                [13, 5, 'LIM-BOL-140L', 'Bolsa para Basura Negra 140 Litros (35x50 pulg)', 'Bolsa de polietileno de alto micraje para residuos pesados gastronómicos, tachos grandes y condominios.', 'Fardo x 100 und (10 paquetes x 10 und)', 'Polietileno Recuperado de Alta Resistencia (2.0 mil)', 55.00, 0, 'https://images.unsplash.com/photo-1530587191325-3db32d826c18?auto=format&fit=crop&w=700&q=80', 1],
                [14, 5, 'LIM-GUA-NIT', 'Guantes de Nitrilo Azul Sin Polvo Grado Alimentario', 'Guantes descartables hipoalergénicos de alta sensibilidad táctil y resistencia química frente a aceites y grasas.', 'Caja x 100 unidades (Tallas S, M, L)', 'Nitrilo Sintético Puro', 26.00, 0, 'https://images.unsplash.com/photo-1584744982491-665216d95f8b?auto=format&fit=crop&w=700&q=80', 0],
                [15, 5, 'LIM-DES-LEJ', 'Hipoclorito de Sodio 5.5% Bidón 5 Galones', 'Desinfectante clorado concentrado para sanitización de superficies de corte, pisos, cámaras frigoríficas y vajilla.', 'Bidón x 5 Galones (19 Litros)', 'Solución Clorada Concentrada', 35.00, 0, 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=700&q=80', 0],
                [16, 6, 'BIO-BWL-KR750', 'Bowl Kraft Redondo 750 ml con Tapa PET', 'Bowl ecológico elaborado en cartón kraft virgen con revestimiento anti-grasa. Incluye tapa transparente de ajuste perfecto.', 'Caja x 300 und (Bowls + Tapas)', 'Cartón Kraft Virgen + Recubrimiento PE Bio', 82.00, 1, 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=700&q=80', 1],
                [17, 6, 'BIO-CAN-HB', 'Hamburguesera Bagazo de Caña de Azúcar 6x6 pulg', 'Envase 100% compostable y vegetal fabricado con fibra residual de caña de azúcar peruana. No absorbe humedad ni grasa.', 'Caja x 500 und', 'Bagazo de Caña de Azúcar 100% Natural', 65.00, 1, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=700&q=80', 1],
                [18, 6, 'BIO-VAS-KRAF', 'Vaso de Polipapel Kraft Doble Pared 12 oz', 'Vaso térmico doble capa aislante de cartón kraft que elimina la necesidad de fajas térmicas auxiliares para café caliente.', 'Caja x 500 und', 'Cartón Kraft Virgen Certificado FSC', 58.00, 1, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=700&q=80', 1]
            ];
            $stmtProd = $pdo->prepare("INSERT INTO productos (id, categoria_id, sku, nombre, descripcion, presentacion, material, precio, biodegradable, imagen_url, destacado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($baseProducts as $bp) {
                $stmtProd->execute($bp);
            }
        }

        // 3. Tabla usuarios
        $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
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
            creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_doc (numero_documento),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Sincronización de cuentas de Master Admin desde la Caja Fuerte (Vault)
        $adminAccounts = class_exists('Vault') ? Vault::getMasterAdmins() : [];

        foreach ($adminAccounts as $adm) {
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(email) = LOWER(?) OR numero_documento = ?");
                $checkStmt->execute([$adm['email'], $adm['doc']]);
                $existing = $checkStmt->fetch();

                $passHash = $adm['pass_hash'] ?? ($adm['password'] ?? '');

                if (!$existing && !empty($passHash)) {
                    $insertStmt = $pdo->prepare("INSERT INTO usuarios (tipo_documento, numero_documento, nombre_razon_social, email, password, telefono, departamento, provincia, distrito, direccion, rol) VALUES (?, ?, ?, ?, ?, ?, 'Lima', 'Lima', 'Cercado de Lima', ?, 'admin')");
                    $insertStmt->execute([$adm['tipo_doc'], $adm['doc'], $adm['nombre'], $adm['email'], $passHash, $adm['telefono'], $adm['direccion']]);
                }
            } catch (Exception $e) {}
        }

        // Cuenta demo de cliente para pruebas
        try {
            $checkClient = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(email) = LOWER(?)");
            $checkClient->execute(['cliente@demo.pe']);
            if (!$checkClient->fetch()) {
                $clientHash = password_hash('Cliente@Demo2026!', PASSWORD_BCRYPT);
                $stmtClient = $pdo->prepare("INSERT INTO usuarios (tipo_documento, numero_documento, nombre_razon_social, email, password, telefono, departamento, provincia, distrito, direccion, rol) VALUES ('RUC', '20554433221', 'EMPRESA GASTRONÓMICA PERÚ S.A.C.', 'cliente@demo.pe', ?, '900000002', 'Lima', 'Lima', 'Miraflores', 'Av. José Larco 450', 'cliente')");
                $stmtClient->execute([$clientHash]);
            }
        } catch (Exception $e) {}

        // 4. Tabla cotizaciones
        $pdo->exec("CREATE TABLE IF NOT EXISTS cotizaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo_cotizacion VARCHAR(50) NOT NULL UNIQUE,
            usuario_id INT NULL,
            cliente_nombre VARCHAR(255) NOT NULL,
            cliente_doc VARCHAR(20) NOT NULL,
            cliente_email VARCHAR(150) NULL DEFAULT '',
            cliente_telefono VARCHAR(30) NULL,
            departamento VARCHAR(100) DEFAULT 'Lima',
            provincia VARCHAR(100) DEFAULT 'Lima',
            distrito VARCHAR(100) NULL,
            direccion TEXT NULL,
            tipo_comprobante VARCHAR(20) DEFAULT 'Factura',
            items LONGTEXT NOT NULL,
            notas TEXT NULL,
            estado VARCHAR(30) DEFAULT 'Pendiente',
            creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_codigo (codigo_cotizacion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Auto-migraciones para tabla cotizaciones
        try {
            $colsCotiz = $pdo->query("SHOW COLUMNS FROM cotizaciones")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('estado', $colsCotiz)) $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN estado VARCHAR(30) DEFAULT 'Pendiente'");
            if (!in_array('notas', $colsCotiz)) $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN notas TEXT NULL");
            if (!in_array('total_items', $colsCotiz)) $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN total_items INT NOT NULL DEFAULT 0");
            if (!in_array('usuario_id', $colsCotiz)) $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN usuario_id INT NULL");
            if (!in_array('tipo_comprobante', $colsCotiz)) $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN tipo_comprobante VARCHAR(20) DEFAULT 'Factura'");
            try { $pdo->exec("ALTER TABLE cotizaciones MODIFY COLUMN cliente_email VARCHAR(150) NULL DEFAULT ''"); } catch(Exception $e){}
            try { $pdo->exec("ALTER TABLE cotizaciones MODIFY COLUMN items LONGTEXT NULL"); } catch(Exception $e){}
        } catch (Exception $e) {}

        // 5. Tabla reclamaciones (Libro de Reclamaciones INDECOPI)
        $pdo->exec("CREATE TABLE IF NOT EXISTS reclamaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo_hoja VARCHAR(50) NOT NULL UNIQUE,
            tipo_reclamacion VARCHAR(20) NOT NULL DEFAULT 'Reclamo',
            nombre_completo VARCHAR(255) NOT NULL,
            tipo_documento VARCHAR(10) DEFAULT 'DNI',
            numero_documento VARCHAR(20) NOT NULL,
            telefono VARCHAR(30) NULL,
            email VARCHAR(150) NOT NULL,
            direccion TEXT NOT NULL,
            departamento VARCHAR(100) DEFAULT 'Lima',
            provincia VARCHAR(100) DEFAULT 'Lima',
            distrito VARCHAR(100) NOT NULL,
            tipo_bien VARCHAR(20) DEFAULT 'Producto',
            monto_reclamado DECIMAL(10,2) DEFAULT 0.00,
            descripcion_bien TEXT NOT NULL,
            detalle_reclamacion TEXT NOT NULL,
            pedido_consumidor TEXT NOT NULL,
            estado VARCHAR(30) DEFAULT 'Pendiente',
            respuesta_proveedor TEXT NULL,
            fecha_respuesta DATETIME NULL,
            creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_hoja (codigo_hoja)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 6. Tabla configuracion (Datos del Negocio, Contacto y Redes)
        $pdo->exec("CREATE TABLE IF NOT EXISTS configuracion (
            clave VARCHAR(100) PRIMARY KEY,
            valor TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Seed inicial de configuracion si está vacía
        $countConf = $pdo->query("SELECT COUNT(*) as c FROM configuracion")->fetch();
        if ((int)($countConf['c'] ?? 0) === 0) {
            $baseConfig = [
                'enable_redirects' => 'false',
                'razon_social' => 'DESCARTABLES PERUANOS S.A.C.',
                'nombre_comercial' => 'Descartables Peruanos',
                'ruc' => '20601234567',
                'direccion' => 'Av. Alejandro Bertello 732-C, Cercado de Lima, Lima, Perú',
                'horario' => 'Lunes a Viernes: 8:00 AM - 6:00 PM | Sábados: 8:30 AM - 1:00 PM',
                'whatsapp_principal' => '+51 900 000 000',
                'whatsapp_secundario' => '+51 900 000 002',
                'telefono_central' => '(01) 000-0000',
                'email_ventas' => 'ventas@descartablesperuanos.pe',
                'email_cotizaciones' => 'cotizaciones@descartablesperuanos.pe',
                'facebook_url' => 'https://facebook.com/descartablesperuanos',
                'instagram_url' => 'https://instagram.com/descartablesperuanos'
            ];
            $stmtConf = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?)");
            foreach ($baseConfig as $k => $v) {
                $stmtConf->execute([$k, $v]);
            }
        }

    } catch (Exception $e) {
        // Continuar silenciosamente
    }
    $initialized = true;
}

function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $pdo->exec("SET NAMES utf8mb4");

        // Inicializar esquema si es necesario
        ensureDatabaseInitialized($pdo);

        return $pdo;
    } catch (PDOException $e) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'error'   => 'Error al conectar con la base de datos MySQL.',
            'detail'  => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}
