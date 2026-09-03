/**
 * Datos Iniciales y Catálogo Maestro: Descartables Peruanos
 * Productos reales y categorías del mercado peruano (Pamolsa, Proplas, etc.)
 */

const CATEGORIAS = [
  {
    id: 1,
    nombre: "Productos Pamolsa",
    slug: "pamolsa",
    descripcion: "Envases térmicos, bisagras, domos y vasos para gastronomía.",
    icono: "coffee",
    color: "from-amber-600/20 to-orange-600/20"
  },
  {
    id: 2,
    nombre: "Línea Proplas / Barrera",
    slug: "proplas-barrera",
    descripcion: "Bolsas al vacío, bilaminadas, films y empaques industriales.",
    icono: "shield-check",
    color: "from-blue-600/20 to-cyan-600/20"
  },
  {
    id: 3,
    nombre: "Cubiertos Descartables",
    slug: "cubiertos",
    descripcion: "Cucharas, tenedores y cuchillos reforzados y biodegradables.",
    icono: "utensils",
    color: "from-stone-600/20 to-zinc-600/20"
  },
  {
    id: 4,
    nombre: "Servilletas y Papeles",
    slug: "servilletas",
    descripcion: "Servilletas cocktail, interfoliadas, bobinas y papel institucional.",
    icono: "file-text",
    color: "from-emerald-600/20 to-teal-600/20"
  },
  {
    id: 5,
    nombre: "Productos de Limpieza e Higiene",
    slug: "limpieza",
    descripcion: "Bolsas de basura industriales, guantes de nitrilo y desinfectantes.",
    icono: "sparkles",
    color: "from-purple-600/20 to-indigo-600/20"
  },
  {
    id: 6,
    nombre: "Novedades y Biodegradables",
    slug: "novedades",
    descripcion: "Línea eco-amigable de bagazo de caña de azúcar y bowls kraft.",
    icono: "leaf",
    color: "from-lime-600/20 to-green-600/20"
  }
];

const PRODUCTOS = [
  {
    id: 1,
    categoria_id: 1,
    categoria_slug: "pamolsa",
    categoria_nombre: "Productos Pamolsa",
    sku: "PAM-CT4",
    nombre: "Contenedor Térmico CT-4 Pamolsa",
    descripcion: "Envase térmico espumado con bisagra integrada. Ideal para transporte de menús, caldos y segundos calientes.",
    presentacion: "Caja x 200 und",
    material: "Poliestireno Expandido (EPS)",
    biodegradable: false,
    imagen_url: "https://images.unsplash.com/photo-1578916171728-46686eac8d58?auto=format&fit=crop&w=700&q=80",
    destacado: true,
    especificaciones: {
      temperatura: "-10°C a 85°C",
      dimensiones: "23 x 16 x 7 cm",
      empaque: "Fardo termoencogible higiénico"
    }
  },
  {
    id: 2,
    categoria_id: 1,
    categoria_slug: "pamolsa",
    categoria_nombre: "Productos Pamolsa",
    sku: "PAM-V8",
    nombre: "Vaso Térmico 8 oz Pamolsa",
    descripcion: "Vaso espumado ergonómico para café, té y bebidas calientes con excelente aislamiento térmico que evita quemaduras.",
    presentacion: "Caja x 1,000 und (40 pqts x 25 und)",
    material: "Poliestireno Expandido (EPS)",
    biodegradable: false,
    imagen_url: "https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=700&q=80",
    destacado: true,
    especificaciones: {
      capacidad: "8 oz (240 ml)",
      temperatura: "Hasta 90°C",
      compatibilidad: "Tapa viajera 8oz estándar"
    }
  },
  {
    id: 3,
    categoria_id: 1,
    categoria_slug: "pamolsa",
    categoria_nombre: "Productos Pamolsa",
    sku: "PAM-DOM-16",
    nombre: "Domo Ensalada Transparente con Tapa 16 oz",
    descripcion: "Envase transparente de máxima claridad visual para ensaladas de frutas, repostería fina, postres y poke bowls.",
    presentacion: "Caja x 500 und",
    material: "PET Cristal de Alta Claridad",
    biodegradable: false,
    imagen_url: "https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=700&q=80",
    destacado: false,
    especificaciones: {
      cierre: "Cierre hermético 'Snap-lock'",
      temperatura: "-20°C a 60°C",
      reciclable: "100% Reciclable Código 1 (PET)"
    }
  },
  {
    id: 4,
    categoria_id: 2,
    categoria_slug: "proplas-barrera",
    categoria_nombre: "Línea Proplas / Barrera",
    sku: "PRO-VAC-2030",
    nombre: "Bolsa de Vacío Alta Barrera 20x30 cm Proplas",
    descripcion: "Bolsa coextruida multicapa de alta barrera contra el oxígeno y la humedad. Máxima conservación de embutidos, quesos y carnes.",
    presentacion: "Millar (1,000 und)",
    material: "Poliamida / Polietileno (PA/PE)",
    biodegradable: false,
    imagen_url: "https://images.unsplash.com/photo-1607344645866-009c320c5ab8?auto=format&fit=crop&w=700&q=80",
    destacado: true,
    especificaciones: {
      espesor: "70 micras / 90 micras",
      resistencia: "Apta para congelación profunda (-40°C)",
      sellado: "Termosellado plano reforzado"
    }
  },
  {
    id: 5,
    categoria_id: 2,
    categoria_slug: "proplas-barrera",
    categoria_nombre: "Línea Proplas / Barrera",
    sku: "PRO-FILM-18",
    nombre: "Film Extensible Industrial 18 pulg x 1500 pies",
    descripcion: "Bobina de film stretch para embalaje y paletizado manual o semiautomático. Gran adherencia y resistencia al rasgado.",
    presentacion: "Caja x 4 bobinas",
    material: "Polietileno Lineal (LLDPE)",
    biodegradable: false,
    imagen_url: "https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=700&q=80",
    destacado: false,
    especificaciones: {
      ancho: "18 pulgadas (45 cm)",
      elongacion: "Hasta 300%",
      peso_bobina: "2.2 kg aprox"
    }
  },
  {
    id: 6,
    categoria_id: 2,
    categoria_slug: "proplas-barrera",
    categoria_nombre: "Línea Proplas / Barrera",
    sku: "PRO-BIL-1525",
    nombre: "Bolsa Bilaminada Metalizada Stand-Up Pouch 250g",
    descripcion: "Empaque tipo 'doypack' con fondo fuelle y zipper resellable. Protege contra radiación solar y conserva aromas de café o frutos secos.",
    presentacion: "Caja x 500 und",
    material: "BOPP Mate / PET Metalizado / PE",
    biodegradable: false,
    imagen_url: "https://images.unsplash.com/photo-1589365278144-c9e705f843ba?auto=format&fit=crop&w=700&q=80",
    destacado: false,
    especificaciones: {
      capacidad: "250 gramos de café en grano",
      cierre: "Zipper hermético + Muesca abre fácil",
      acabado: "Negro mate o Kraft natural"
    }
  },
  {
    id: 7,
    categoria_id: 3,
    categoria_slug: "cubiertos",
    categoria_nombre: "Cubiertos Descartables",
    sku: "CUB-TEN-PES",
    nombre: "Tenedor Descartable Pesado Blanco",
    descripcion: "Tenedor de mesa con mango ergonómico y dientes firmes de alto gramaje. No se flecta ante alimentos calientes o carnes.",
    presentacion: "Caja x 1,000 und (10 paquetes de 100 und)",
    material: "Polipropileno Reforzado (PP)",
    biodegradable: false,
    imagen_url: "https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?auto=format&fit=crop&w=700&q=80",
    destacado: true,
    especificaciones: {
      longitud: "16.5 cm",
      resistencia: "Rigidez superior, libre de BPA",
      color: "Blanco nieve"
    }
  },
  {
    id: 8,
    categoria_id: 3,
    categoria_slug: "cubiertos",
    categoria_nombre: "Cubiertos Descartables",
    sku: "CUB-CUCH-PES",
    nombre: "Cuchara Descartable Pesada Blanca",
    descripcion: "Cuchara sopera honda reforzada para guisos, sopas y postres en restaurantes de alta rotación.",
    presentacion: "Caja x 1,000 und (10 paquetes de 100 und)",
    material: "Polipropileno Reforzado (PP)",
    biodegradable: false,
    imagen_url: "https://images.unsplash.com/photo-1616401784845-180882ba9ba8?auto=format&fit=crop&w=700&q=80",
    destacado: false,
    especificaciones: {
      longitud: "16 cm",
      peso_unitario: "4.2 gramos aprox.",
      grado_alimentario: "Certificado SENASA / DIGESA"
    }
  },
  {
    id: 9,
    categoria_id: 3,
    categoria_slug: "cubiertos",
    categoria_nombre: "Cubiertos Descartables",
    sku: "CUB-ECO-CANA",
    nombre: "Kit Cubiertos Biodegradables Cuchara + Tenedor + Servilleta",
    descripcion: "Set enfundado 100% compostable fabricado a partir de fécula de maíz CPLA. Excelente opción eco para delivery corporativo.",
    presentacion: "Caja x 500 kits completos",
    material: "Biopolímero CPLA Compostable",
    biodegradable: true,
    imagen_url: "https://images.unsplash.com/photo-1598971861713-54ad16a7e72e?auto=format&fit=crop&w=700&q=80",
    destacado: true,
    especificaciones: {
      biodegradabilidad: "Compostable en 90-180 días",
      temperatura: "Soporta hasta 85°C",
      empaque: "Funda de papel kraft sellada"
    }
  },
  {
    id: 10,
    categoria_id: 4,
    categoria_slug: "servilletas",
    categoria_nombre: "Servilletas y Papeles",
    sku: "PAP-SERV-COC",
    nombre: "Servilleta Cocktail Blanca 24x24 cm",
    descripcion: "Servilleta tissue de doble hoja suave y absorbente. Perfecta para bares, cafeterías, bodas y eventos corporativos.",
    presentacion: "Fardo x 4,000 und (40 paquetes de 100 und)",
    material: "Papel Celulosa Virgen 100%",
    biodegradable: true,
    imagen_url: "https://images.unsplash.com/photo-1583947215259-38e31be8751f?auto=format&fit=crop&w=700&q=80",
    destacado: false,
    especificaciones: {
      dimension: "24 x 24 cm desplegada (12 x 12 cm plegada)",
      hojas: "Doble hoja gofrada lateral",
      blancura: "Extra blanco"
    }
  },
  {
    id: 11,
    categoria_id: 4,
    categoria_slug: "servilletas",
    categoria_nombre: "Servilletas y Papeles",
    sku: "PAP-SERV-INT",
    nombre: "Servilleta Interfoliada Tipo Dispensador",
    descripcion: "Servilleta doblada en V diseñada para dispensadores de mesa. Reduce el consumo y desperdicio hasta en un 35%.",
    presentacion: "Caja x 2,400 und (12 paquetes x 200 und)",
    material: "Papel Celulosa Virgen",
    biodegradable: true,
    imagen_url: "https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=700&q=80",
    destacado: true,
    especificaciones: {
      dimension: "20 x 10 cm plegada",
      ahorro: "Salida unitaria hoja por hoja",
      compatibilidad: "Dispensadores estándar universales"
    }
  },
  {
    id: 12,
    categoria_id: 4,
    categoria_slug: "servilletas",
    categoria_nombre: "Servilletas y Papeles",
    sku: "PAP-TOA-BOB",
    nombre: "Papel Toalla Bobina Industrial 250 metros",
    descripcion: "Rollo continuo de toalla para cocinas profesionales, hoteles, laboratorios y áreas de alta concurrencia.",
    presentacion: "Fardo x 2 bobinas (500 metros totales)",
    material: "Papel Celulosa Extra Resistente",
    biodegradable: true,
    imagen_url: "https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=700&q=80",
    destacado: false,
    especificaciones: {
      longitud_rollo: "250 metros lineales",
      gramaje: "40 g/m² de alta absorción",
      color: "Blanco óptico"
    }
  },
  {
    id: 13,
    categoria_id: 5,
    categoria_slug: "limpieza",
    categoria_nombre: "Productos de Limpieza e Higiene",
    sku: "LIM-BOL-140L",
    nombre: "Bolsa para Basura Negra 140 Litros (35x50 pulg)",
    descripcion: "Bolsa de polietileno de alto micraje para residuos pesados gastronómicos, tachos grandes y condominios.",
    presentacion: "Fardo x 100 und (10 paquetes x 10 und)",
    material: "Polietileno Recuperado de Alta Resistencia (2.0 mil)",
    biodegradable: false,
    imagen_url: "https://images.unsplash.com/photo-1530587191325-3db32d826c18?auto=format&fit=crop&w=700&q=80",
    destacado: true,
    especificaciones: {
      capacidad: "140 Litros",
      dimension: "35 x 50 pulgadas (90 x 125 cm)",
      fondo: "Sellado estrella anti-goteo"
    }
  },
  {
    id: 14,
    categoria_id: 5,
    categoria_slug: "limpieza",
    categoria_nombre: "Productos de Limpieza e Higiene",
    sku: "LIM-GUA-NIT",
    nombre: "Guantes de Nitrilo Azul Sin Polvo Grado Alimentario",
    descripcion: "Guantes descartables hipoalergénicos de alta sensibilidad táctil y resistencia química frente a aceites y grasas.",
    presentacion: "Caja x 100 unidades (Tallas S, M, L)",
    material: "Nitrilo Sintético Puro",
    biodegradable: false,
    imagen_url: "https://images.unsplash.com/photo-1584744982491-665216d95f8b?auto=format&fit=crop&w=700&q=80",
    destacado: false,
    especificaciones: {
      color: "Azul cobalto",
      acabado: "Microtexturado en yemas de dedos",
      norma: "AQL 1.5 Grado Médico y Alimentario"
    }
  },
  {
    id: 15,
    categoria_id: 5,
    categoria_slug: "limpieza",
    categoria_nombre: "Productos de Limpieza e Higiene",
    sku: "LIM-DES-LEJ",
    nombre: "Hipoclorito de Sodio 5.5% Bidón 5 Galones",
    descripcion: "Desinfectante clorado concentrado para sanitización de superficies de corte, pisos, cámaras frigoríficas y vajilla.",
    presentacion: "Bidón x 5 Galones (19 Litros)",
    material: "Solución Clorada Concentrada",
    biodegradable: false,
    imagen_url: "https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=700&q=80",
    destacado: false,
    especificaciones: {
      concentracion: "5.5% de cloro activo",
      rendimiento: "Alta dilución para ahorro por litro",
      envase: "Polietileno de alta densidad (HDPE) con precinto"
    }
  },
  {
    id: 16,
    categoria_id: 6,
    categoria_slug: "novedades",
    categoria_nombre: "Novedades y Biodegradables",
    sku: "BIO-BWL-KR750",
    nombre: "Bowl Kraft Redondo 750 ml con Tapa PET",
    descripcion: "Bowl ecológico elaborado en cartón kraft virgen con revestimiento anti-grasa. Incluye tapa transparente de ajuste perfecto.",
    presentacion: "Caja x 300 und (Bowls + Tapas)",
    material: "Cartón Kraft Virgen + Recubrimiento PE Bio",
    biodegradable: true,
    imagen_url: "https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=700&q=80",
    destacado: true,
    especificaciones: {
      capacidad: "750 ml (26 oz)",
      temperatura: "Apto para ensaladas frías o poke tibio",
      estetica: "Tono kraft natural con acabado premium"
    }
  },
  {
    id: 17,
    categoria_id: 6,
    categoria_slug: "novedades",
    categoria_nombre: "Novedades y Biodegradables",
    sku: "BIO-CAN-HB",
    nombre: "Hamburguesera Bagazo de Caña de Azúcar 6x6 pulg",
    descripcion: "Envase 100% compostable y vegetal fabricado con fibra residual de caña de azúcar peruana. No absorbe humedad ni grasa.",
    presentacion: "Caja x 500 und",
    material: "Bagazo de Caña de Azúcar 100% Natural",
    biodegradable: true,
    imagen_url: "https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=700&q=80",
    destacado: true,
    especificaciones: {
      descomposicion: "Compostable en 60-90 días",
      temperatura: "-25°C a 120°C (Microondas y Freezer)",
      certificacion: "BPI / OK Compost Industrial"
    }
  },
  {
    id: 18,
    categoria_id: 6,
    categoria_slug: "novedades",
    categoria_nombre: "Novedades y Biodegradables",
    sku: "BIO-VAS-KRAF",
    nombre: "Vaso de Polipapel Kraft Doble Pared 12 oz",
    descripcion: "Vaso térmico doble capa aislante de cartón kraft que elimina la necesidad de fajas térmicas auxiliares para café caliente.",
    presentacion: "Caja x 500 und",
    material: "Cartón Kraft Virgen Certificado FSC",
    biodegradable: true,
    imagen_url: "https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=700&q=80",
    destacado: true,
    especificaciones: {
      capacidad: "12 oz (350 ml)",
      aislamiento: "Cámara de aire interna anti-quemaduras",
      sostenibilidad: "Papel procedente de bosques sostenibles"
    }
  }
];

// Datos geográficos de Perú para formularios
const DEPARTAMENTOS_PERU = [
  "Lima", "Arequipa", "Cusco", "La Libertad", "Piura", "Lambayeque", "Junín", "Áncash", "Ica",
  "San Martín", "Cajamarca", "Loreto", "Puno", "Huánuco", "Tacna", "Ayacucho", "Ucayali",
  "Tumbes", "Moquegua", "Amazonas", "Pasco", "Madre de Dios", "Huancavelica", "Apurímac", "Callao"
];

const DISTRITOS_LIMA = [
  "Cercado de Lima", "Miraflores", "San Isidro", "Santiago de Surco", "San Borja",
  "La Molina", "San Miguel", "Magdalena del Mar", "Jesús María", "Pueblo Libre",
  "Lince", "Breña", "Los Olivos", "San Martín de Porres", "Comas", "Independencia",
  "Carabayllo", "San Juan de Lurigancho", "Ate Vitarte", "Santa Anita", "El Agustino",
  "La Victoria", "Surquillo", "Barranco", "Chorrillos", "San Juan de Miraflores",
  "Villa María del Triunfo", "Villa El Salvador", "Lurín", "Pachacámac", "Puente Piedra",
  "Callao", "Bellavista", "La Perla", "La Punta", "Carmen de la Legua", "Ventanilla"
];

