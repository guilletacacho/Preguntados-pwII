-- Migración: baja/alta lógica de categorías
ALTER TABLE category
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;

-- Las preguntas siguen teniendo su propio is_active; la categoría inactiva
-- se trata como baja efectiva en las consultas.
