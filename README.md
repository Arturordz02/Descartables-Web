# 🇵🇪 Descartables Peruanos — Plataforma Web

> Plataforma web modular, moderna y 100% responsiva para la comercialización mayorista y minorista de envases descartables, térmicos y biodegradables en el mercado peruano. Construida bajo estándares normativos de **INDECOPI (D.S. N° 011-2011-PCM / Ley N° 31435)** y **SUNAT**.

---

## 🎨 Sistema Visual (Warm Editorial)

Inspirado en tendencias modernas de UI/UX (**Shadcn Warm Theme**, **Tailwind OKLCH** y **Bento Grid Architecture**):
- **Base Background:** Warm Cream / Arena Moka (`#FDFBF7` / `#F4EFEA`)
- **Primary Color:** Terracota Cobre (`#C85A32` / `#B84A22`)
- **Accent Color:** Ámbar Dorado / Mostaza Tostado (`#D9822B`)
- **Eco-Badge:** Verde Salvia / Oliva Profundo (`#4A5D4E`)
- **Textos:** Café Espresso (`#1F1815` / `#574B46`)
- **Glassmorphism:** Desenfoque translúcido (`backdrop-blur-md`)
- **100% Responsivo:** Adaptado con mobile bottom action bar, soporte táctil inercial y sin auto-zoom forzado en iOS Safari.

---

## 🚀 Arquitectura y Stack Tecnológico

- **Frontend:** HTML5 Semántico + Tailwind CSS CDN + JavaScript Vainilla (ES6+)
- **Backend API:** PHP 8 (PDO seguro, Headers CORS, respuestas en JSON estructurado)
- **Base de Datos:** MySQL (`descartables_db`, UTF-8 Unicode)
- **Capa Híbrida de Persistencia:** Si el backend MySQL no está disponible, la capa `assets/js/api.js` conmuta de forma transparente e instantánea a `localStorage`, asegurando que toda la web funcione sin requerir servidores locales levantados.

---

## 📦 Categorías y Catálogo Especializado

1. **Productos Pamolsa:** Envases térmicos CT-4, CT-3, vasos térmicos para café, domos para repostería y bandejas de poliestireno expandido.
2. **Línea Proplas / Barrera:** Bolsas de alto vacío, películas stretch film y empaques bilaminados para conservación de alimentos.
3. **Cubiertos Descartables Reforzados:** Cucharas, tenedores y cuchillos pesados para restaurantes y delivery.
4. **Servilletas y Papeles:** Servilletas interfoliadas, cocktail y bobinas para dispensadores institucionales.
5. **Productos de Limpieza e Higiene:** Bolsas de basura de 140L, guantes de nitrilo grado alimentario y desinfectantes.
6. **Novedades y Biodegradables:** Bowls kraft, envases de bagazo de caña 100% compostables y biopolímeros.

---

## ⚖️ Módulos Destacados

### 1. Cotizador Express con WhatsApp Business
- Drawer lateral persistente en toda la web.
- Configuración de comprobante (*Factura con RUC* o *Boleta de Venta con DNI*).
- Selección de agencia de envíos a provincias (*Shalom, Marvisur, Flores Hermanos* o recojo en almacén en Lima).
- Generación automática de mensaje formateado directo a la central de ventas: `+51 994 195 430`.

### 2. Libro de Reclamaciones Virtual Oficial (INDECOPI)
- Cumplimiento estricto del **D.S. N° 011-2011-PCM** y la **Ley N° 31435** (plazo de respuesta máximo de 15 días hábiles).
- Identificación pública del proveedor: **DESCARTABLES PERUANOS S.A.C. (RUC 20601234567)**.
- Diferenciación clara entre **Reclamo** (disconformidad por el producto) y **Queja** (malestar por atención al público).
- Generación correlativa de código único de hoja: ej. `REC-2026-00001`.
- **Hoja de Reclamación Imprimible Oficial en A4:** Modal con botón de impresión y descarga PDF vía estilos de impresión optimizados (`@media print`).

### 3. Módulo de Autenticación y Perfil
- Soporte para clientes con **DNI** (8 dígitos) y empresas con **RUC** (11 dígitos).
- Historial de cotizaciones enviadas y hojas de reclamación registradas.
- Actualización de datos fiscales y dirección de entrega.

---

## 💻 Instalación y Uso Local

### Opción A: Ejecución Rápida (100% Autónoma con LocalStorage)
Simplemente abre `index.html` en tu navegador o mediante la extensión Live Server de VS Code. No requiere levantar base de datos ni configurar PHP.

### Opción B: Ejecución con Servidor PHP y MySQL

1. Clona el repositorio:
   ```bash
   git clone https://github.com/Arturordz02/Descartables-Web.git
   ```
2. Importa la base de datos:
   - Abre phpMyAdmin o tu cliente MySQL.
   - Ejecuta las instrucciones contenidas en [`schema.sql`](schema.sql).
3. Configura la conexión en [`api/config.php`](api/config.php):
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_NAME', 'descartables_db');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```
4. Levanta el servidor local integrado:
   ```bash
   php -S 127.0.0.1:8000
   ```
5. Abre `http://127.0.0.1:8000` en tu navegador.

---

## 📍 Datos de la Empresa

- **Razón Social:** DESCARTABLES PERUANOS S.A.C.
- **RUC:** 20601234567
- **Dirección Central:** Av. Alejandro Bertello 732-C, Cercado de Lima, Lima, Perú.
- **Central Telefónica:** (01) 564-1450
- **WhatsApp de Ventas:** +51 994 195 430 / +51 994 009 692

---

## 📄 Licencia

Distribuido bajo la Licencia MIT. Consulte `LICENSE` para más información.
