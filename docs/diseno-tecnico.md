# Diseno tecnico

## Estructura del codigo
- PHP sin framework, separacion por paginas y modulos.
- Reutilizacion de layout por includes (`includes/*`).
- Conexion a DB centralizada en `config/db.php`.
- Analisis IA encapsulado en `api/analizar_feedback.php`.

## Modelo de datos (inferido del codigo)
Tablas principales utilizadas:

### `fb_campanas`
- `id` (PK)
- `nombre`
- `descripcion` (nullable)
- `url_token` (token publico)
- `estado` (activa/inactiva)
- `fecha_creacion`

### `fb_respuestas`
- `id` (PK)
- `campana_id` (FK -> `fb_campanas.id`)
- `fecha_respuesta`
- `calificacion` (1 a 5)
- `respuesta_2` (texto libre, puntos fuertes)
- `comentario` (texto libre, mejoras)
- `sucursal` (nullable)
- `canal` (nullable)
- `analizado_ia` (0/1)

### `fb_analisis_ia`
- `respuesta_id` (PK o FK -> `fb_respuestas.id`)
- `sentimiento` (positivo/negativo/neutro/mixto)
- `tema_principal`
- `tema_secundario`
- `urgencia` (baja/media/alta)
- `resumen`
- `accion_sugerida`
- `fecha_analisis`

### `fb_analisis_ia_detalle`
- `id` (PK, inferido por uso de `ORDER BY id`)
- `respuesta_id` (FK -> `fb_respuestas.id`)
- `tipo` (positivo/negativo)
- `fragmento`
- `tema` (nullable)
- `created_at`

### `fb_preguntas`
- `id` (PK)
- `campana_id` (FK -> `fb_campanas.id`)
- `texto_pregunta`
- `tipo` (escala/texto/opcion)
- `orden`
- `obligatoria`
- `created_at`
- `updated_at`

### `fb_preguntas_opciones`
- `id` (PK)
- `pregunta_id` (FK -> `fb_preguntas.id`)
- `texto_opcion`
- `orden`

### `fb_respuestas_detalle`
- `id` (PK)
- `respuesta_id` (FK -> `fb_respuestas.id`)
- `pregunta_id` (FK -> `fb_preguntas.id`)
- `respuesta_texto`
- `respuesta_opcion`
- `respuesta_escala`
- `created_at`

### `fb_usuarios`
- `id` (PK)
- `nombre`
- `email`
- `password_hash`
- `estado` (1/0)
- `created_at`
- `updated_at`

### `fb_roles`
- `id` (PK)
- `clave` (admin/analista/lector)
- `nombre`

### `fb_usuarios_roles`
- `usuario_id` (FK -> `fb_usuarios.id`)
- `rol_id` (FK -> `fb_roles.id`)

### `fb_password_resets`
- `id` (PK)
- `usuario_id` (FK -> `fb_usuarios.id`)
- `token_hash`
- `expires_at`
- `used_at`
- `created_at`

### `fb_auditoria`
- `id` (PK)
- `usuario_id` (nullable)
- `accion`
- `detalle`
- `ip`
- `created_at`

Nota: la estructura exacta debe confirmarse con el esquema real.

## Analisis IA
Funcion principal: `analizarFeedbackConIA($respuestaId)`.

- Construye un prompt con datos de campaña, calificacion, y respuestas del usuario.
- Usa Responses API con `json_schema` y `strict=true` para asegurar formato.
- Persiste:
  - Cabecera en `fb_analisis_ia`.
  - Detalles en `fb_analisis_ia_detalle` (fortalezas y problemas).
- Marca `fb_respuestas.analizado_ia = 1`.
- Reproceso seguro: elimina analisis previo antes de insertar nuevo.

## Endpoints y paginas
- Publico:
  - `index.php?token=...` valida campaña activa.
  - `formulario.php?token=...` formulario de feedback.
  - `guardar_feedback.php` (POST) guarda respuesta.
  - `gracias.php` confirma envio.
- Admin:
  - `admin/dashboard.php` KPIs y graficos.
  - `admin/campanas.php` alta y listado.
  - `admin/preguntas.php` mantenedor de preguntas por campaña.
  - `admin/qr_campana.php?token=...` QR.
  - `admin/respuestas.php` listado y filtros.
  - `admin/detalle_feedback.php?id=...` detalle.
  - `admin/procesar_ia_respuesta.php?id=...` ejecuta IA.
  - `admin/usuarios.php` CRUD de usuarios y roles.
  - `admin/login.php`, `admin/logout.php` autenticacion.
  - `admin/forgot_password.php`, `admin/reset_password.php` reset por correo.

## Manejo de errores
- Se redirige a paginas seguras ante validaciones fallidas.
- Errores de IA se registran con `error_log` y no rompen el flujo de admin.
- `guardar_feedback.php` muestra mensaje simple en caso de excepcion.

## Riesgos y mejoras sugeridas
- Agregar autenticacion y autorizacion en `/admin`.
- Extraer credenciales a variables de entorno.
- Limitar la ejecucion de IA en colas o jobs asincronos si crece el volumen.
- Validar limites de longitud en DB para campos de texto.
