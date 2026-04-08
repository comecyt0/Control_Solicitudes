-- Migration: Add room request fields to calendar solicitudes and events
-- v1.0 | 2026-04-08

-- 1. Add columns to sb_calendario_solicitudes
ALTER TABLE sb_calendario_solicitudes 
ADD COLUMN requiere_sala BOOLEAN DEFAULT FALSE,
ADD COLUMN area_solicitante TEXT,
ADD COLUMN persona_solicitante TEXT;

-- 2. Add columns to eventos
ALTER TABLE eventos 
ADD COLUMN requiere_sala BOOLEAN DEFAULT FALSE,
ADD COLUMN area_solicitante TEXT,
ADD COLUMN persona_solicitante TEXT;

COMMENT ON COLUMN sb_calendario_solicitudes.requiere_sala IS 'Indica si la solicitud requiere el uso de la sala de juntas';
COMMENT ON COLUMN sb_calendario_solicitudes.area_solicitante IS 'Área que realiza la solicitud (extraída automáticamente)';
COMMENT ON COLUMN sb_calendario_solicitudes.persona_solicitante IS 'Nombre de la persona que realiza la solicitud (extraída automáticamente)';

COMMENT ON COLUMN eventos.requiere_sala IS 'Indica si el evento reservó la sala de juntas';
COMMENT ON COLUMN eventos.area_solicitante IS 'Área responsable del evento';
COMMENT ON COLUMN eventos.persona_solicitante IS 'Nombre del solicitante original';
