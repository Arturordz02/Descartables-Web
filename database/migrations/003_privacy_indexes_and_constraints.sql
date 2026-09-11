-- ====================================================================
-- MIGRACIÓN 003: Índices de Privacidad, Búsqueda y Claves Foráneas
-- Plataforma Descartables Peruanos
-- ====================================================================

-- 1. Índices en usuarios para búsquedas administrativas y autenticación rápida
CREATE INDEX IF NOT EXISTS idx_usuarios_rol ON usuarios(rol);
CREATE INDEX IF NOT EXISTS idx_usuarios_fecha ON usuarios(creado_en);

-- 2. Índices en categorias
CREATE INDEX IF NOT EXISTS idx_categorias_slug ON categorias(slug);

-- 3. Índices en productos para filtros de catálogo y stock
CREATE INDEX IF NOT EXISTS idx_productos_stock ON productos(stock_estado);
CREATE INDEX IF NOT EXISTS idx_productos_destacado ON productos(destacado);
CREATE INDEX IF NOT EXISTS idx_productos_precio ON productos(precio);

-- 4. Índices en libro_reclamaciones para consultas de privacidad (código + doc/email) y panel admin
CREATE INDEX IF NOT EXISTS idx_reclama_codigo ON libro_reclamaciones(codigo_hoja);
CREATE INDEX IF NOT EXISTS idx_reclama_doc ON libro_reclamaciones(numero_documento);
CREATE INDEX IF NOT EXISTS idx_reclama_email ON libro_reclamaciones(email);
CREATE INDEX IF NOT EXISTS idx_reclama_estado ON libro_reclamaciones(estado);
CREATE INDEX IF NOT EXISTS idx_reclama_fecha ON libro_reclamaciones(creado_en);

-- 5. Índices en cotizaciones para validación de acceso (código + doc/email) y reportes
CREATE INDEX IF NOT EXISTS idx_cotiz_codigo ON cotizaciones(codigo_cotizacion);
CREATE INDEX IF NOT EXISTS idx_cotiz_usuario ON cotizaciones(usuario_id);
CREATE INDEX IF NOT EXISTS idx_cotiz_doc ON cotizaciones(documento);
CREATE INDEX IF NOT EXISTS idx_cotiz_email ON cotizaciones(email);
CREATE INDEX IF NOT EXISTS idx_cotiz_estado ON cotizaciones(estado);
CREATE INDEX IF NOT EXISTS idx_cotiz_fecha ON cotizaciones(creado_en);

