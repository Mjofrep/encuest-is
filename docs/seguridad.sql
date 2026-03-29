CREATE TABLE fb_usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE fb_roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(50) NOT NULL UNIQUE,
  nombre VARCHAR(120) NOT NULL
);

CREATE TABLE fb_usuarios_roles (
  usuario_id INT NOT NULL,
  rol_id INT NOT NULL,
  PRIMARY KEY (usuario_id, rol_id),
  CONSTRAINT fk_ur_usuario FOREIGN KEY (usuario_id) REFERENCES fb_usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_rol FOREIGN KEY (rol_id) REFERENCES fb_roles(id) ON DELETE RESTRICT
);

CREATE TABLE fb_password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_token_hash (token_hash),
  INDEX idx_user (usuario_id),
  CONSTRAINT fk_pr_usuario FOREIGN KEY (usuario_id) REFERENCES fb_usuarios(id) ON DELETE CASCADE
);

CREATE TABLE fb_auditoria (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  accion VARCHAR(50) NOT NULL,
  detalle VARCHAR(255) NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_audit_user (usuario_id),
  CONSTRAINT fk_audit_usuario FOREIGN KEY (usuario_id) REFERENCES fb_usuarios(id) ON DELETE SET NULL
);

INSERT INTO fb_roles (clave, nombre) VALUES
  ('admin', 'Administrador'),
  ('analista', 'Analista'),
  ('lector', 'Lector');
