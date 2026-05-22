-- Progreso por material individual (vídeo)
-- Permite calcular el avance gradual dentro de un tema
CREATE TABLE IF NOT EXISTS `progresos_materiales` (
  `usuario_id`  INT NOT NULL,
  `material_id` INT NOT NULL,
  `visto_en`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`usuario_id`, `material_id`),
  KEY `idx_pm_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
