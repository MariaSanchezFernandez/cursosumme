-- =================================================================
-- Cursos Umme — Esquema de base de datos: Login
-- Base de datos: dbs15459256
-- Servidor:      db5020047845.hosting-data.io
-- =================================================================

USE dbs15459256;

-- -----------------------------------------------------------------
-- Tabla: usuarios
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  nombre        VARCHAR(100)     NOT NULL,
  apellidos     VARCHAR(150)     NOT NULL,
  email         VARCHAR(200)     NOT NULL,
  contrasena    VARCHAR(255)     NOT NULL COMMENT 'Hash SHA-256',
  rol           ENUM('admin','alumno') NOT NULL DEFAULT 'alumno',
  fecha_alta    DATE             NOT NULL DEFAULT (CURRENT_DATE),
  activo        TINYINT(1)       NOT NULL DEFAULT 1,

  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------
-- Datos iniciales
-- Contraseña Rocío (admin):   Umme@Admin24
-- Contraseña alumnos:         Umme@2024
-- -----------------------------------------------------------------
INSERT INTO usuarios (nombre, apellidos, email, contrasena, rol, fecha_alta) VALUES
('Rocío',   'Fernandez',       'rocio@cursosumme.com', '22e87b18cceccce65108b355e5ff15e6b25141cae9ce1ddebcfa9cd720bb7dac', 'admin',  '2024-01-01'),
('Ana',     'García López',    'ana@ejemplo.com',      '4b906bf418f949f42ecb103c146e6ee3cafc4ad4cbb5a4349be0a326fb1ccfaa', 'alumno', '2024-03-10'),
('María',   'Martínez Ruiz',   'maria@ejemplo.com',    '4b906bf418f949f42ecb103c146e6ee3cafc4ad4cbb5a4349be0a326fb1ccfaa', 'alumno', '2024-04-05'),
('Carmen',  'Sánchez Torres',  'carmen@ejemplo.com',   '4b906bf418f949f42ecb103c146e6ee3cafc4ad4cbb5a4349be0a326fb1ccfaa', 'alumno', '2024-04-18'),
('Lucía',   'Fernández Gil',   'lucia@ejemplo.com',    '4b906bf418f949f42ecb103c146e6ee3cafc4ad4cbb5a4349be0a326fb1ccfaa', 'alumno', '2024-05-02'),
('Isabel',  'Romero Vega',     'isabel@ejemplo.com',   '4b906bf418f949f42ecb103c146e6ee3cafc4ad4cbb5a4349be0a326fb1ccfaa', 'alumno', '2024-05-20');
