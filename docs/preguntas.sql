CREATE TABLE fb_preguntas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  campana_id INT NOT NULL,
  texto_pregunta VARCHAR(500) NOT NULL,
  tipo ENUM('escala','texto','opcion') NOT NULL,
  orden INT NOT NULL DEFAULT 1,
  obligatoria TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_preguntas_campana (campana_id),
  CONSTRAINT fk_preguntas_campana FOREIGN KEY (campana_id) REFERENCES fb_campanas(id) ON DELETE CASCADE
);

CREATE TABLE fb_preguntas_opciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pregunta_id INT NOT NULL,
  texto_opcion VARCHAR(255) NOT NULL,
  orden INT NOT NULL DEFAULT 1,
  INDEX idx_opciones_pregunta (pregunta_id),
  CONSTRAINT fk_opciones_pregunta FOREIGN KEY (pregunta_id) REFERENCES fb_preguntas(id) ON DELETE CASCADE
);

CREATE TABLE fb_respuestas_detalle (
  id INT AUTO_INCREMENT PRIMARY KEY,
  respuesta_id INT NOT NULL,
  pregunta_id INT NOT NULL,
  respuesta_texto TEXT NULL,
  respuesta_opcion VARCHAR(255) NULL,
  respuesta_escala INT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_respuesta_detalle (respuesta_id),
  INDEX idx_pregunta_detalle (pregunta_id),
  CONSTRAINT fk_detalle_respuesta FOREIGN KEY (respuesta_id) REFERENCES fb_respuestas(id) ON DELETE CASCADE,
  CONSTRAINT fk_detalle_pregunta FOREIGN KEY (pregunta_id) REFERENCES fb_preguntas(id) ON DELETE CASCADE
);
