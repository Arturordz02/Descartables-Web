-- ====================================================================
-- MIGRACIÓN 002: Actualización de Campos y Tipos de Datos
-- Plataforma Descartables Peruanos
-- ====================================================================

-- Esta migración normaliza campos en caso de actualizar desde un esquema legado.

-- 1. Ampliación de campos de texto en usuarios
ALTER TABLE usuarios MODIFY COLUMN nombre_razon_social VARCHAR(255) NOT NULL;
ALTER TABLE usuarios MODIFY COLUMN telefono VARCHAR(30) NULL;
ALTER TABLE usuarios MODIFY COLUMN departamento VARCHAR(100) DEFAULT 'Lima';
ALTER TABLE usuarios MODIFY COLUMN provincia VARCHAR(100) DEFAULT 'Lima';
ALTER TABLE usuarios MODIFY COLUMN distrito VARCHAR(100) NULL;
ALTER TABLE usuarios MODIFY COLUMN direccion TEXT NULL;

-- 2. Categorías: Soporte de estilos e iconos modernos
-- Se asegura longitud y valores por defecto
ALTER TABLE categorias MODIFY COLUMN nombre VARCHAR(150) NOT NULL;
ALTER TABLE categorias MODIFY COLUMN slug VARCHAR(150) NOT NULL;

-- 3. Productos: Ampliación de imagen, descripción y campos de estado
ALTER TABLE productos MODIFY COLUMN nombre VARCHAR(255) NOT NULL;
ALTER TABLE productos MODIFY COLUMN presentacion VARCHAR(150) DEFAULT 'Unidad';
ALTER TABLE productos MODIFY COLUMN material VARCHAR(150) DEFAULT 'Polipropileno';
ALTER TABLE productos MODIFY COLUMN imagen_url VARCHAR(500) DEFAULT 'assets/images/productos/default.png';

-- 4. Cotizaciones: Unificación de campos de contacto y estado
ALTER TABLE cotizaciones MODIFY COLUMN nombre_cliente VARCHAR(255) NOT NULL;
ALTER TABLE cotizaciones MODIFY COLUMN documento VARCHAR(20) NOT NULL;
ALTER TABLE cotizaciones MODIFY COLUMN email VARCHAR(150) NULL DEFAULT '';
ALTER TABLE cotizaciones MODIFY COLUMN telefono VARCHAR(30) NULL;
ALTER TABLE cotizaciones MODIFY COLUMN destino VARCHAR(100) DEFAULT 'Lima Metropolitana';
ALTER TABLE cotizaciones MODIFY COLUMN items LONGTEXT NOT NULL;
ALTER TABLE cotizaciones MODIFY COLUMN estado VARCHAR(30) DEFAULT 'Pendiente';

