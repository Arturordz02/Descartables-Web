-- ====================================================================
-- MIGRACIÓN 005: Control de Concurrencia, Secuencias Atómicas e Idempotencia (F10)
-- Plataforma Descartables Peruanos
-- ====================================================================

-- 1. Tabla de Secuencias Atómicas para Correlativos Continuos (Evita COUNT(*) y MAX(id))
CREATE TABLE IF NOT EXISTS secuencias (
    tipo VARCHAR(50) NOT NULL,       -- 'COTIZACION', 'RECLAMACION'
    anio INT NOT NULL,               -- Año del correlativo (Ej: 2026)
    ultimo_numero INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tipo, anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de Idempotencia para Evitar Duplicados por Reintentos o Timeouts
CREATE TABLE IF NOT EXISTS idempotencia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope VARCHAR(100) NOT NULL,      -- Ej: 'cotizacion:create', 'reclamacion:create'
    clave VARCHAR(100) NOT NULL,      -- Idempotency-Key enviada por el cliente
    usuario_id INT NULL,              -- Usuario autenticado (para aislamiento de tenant)
    documento VARCHAR(20) NULL,       -- Documento del cliente (para validación en invitados)
    request_hash VARCHAR(64) NOT NULL,-- Hash SHA-256 del payload normalizado (excluyendo campos de servidor)
    codigo_resultado VARCHAR(50) NOT NULL, -- Código generado (COT-YYYY-XXXXX o REC-YYYY-XXXXX)
    response_payload LONGTEXT NOT NULL,    -- JSON completo de la respuesta oficial original
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_idemp_scope_clave (scope, clave),
    INDEX idx_idemp_hash (request_hash),
    INDEX idx_idemp_usuario (usuario_id),
    INDEX idx_idemp_fecha (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

