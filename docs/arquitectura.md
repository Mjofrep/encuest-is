# Arquitectura

## Vision general
El sistema es una aplicacion web PHP monolitica con un flujo publico para captura de feedback y un modulo de administracion para analitica y gestion. Persiste datos en MySQL y, de forma opcional, enriquece respuestas con analisis de IA usando la API de OpenAI.

## Componentes principales
- Publico: `index.php`, `formulario.php`, `guardar_feedback.php`, `gracias.php`.
- Administracion: `admin/dashboard.php`, `admin/campanas.php`, `admin/preguntas.php`, `admin/respuestas.php`, `admin/detalle_feedback.php`, `admin/qr_campana.php`, `admin/procesar_ia_respuesta.php`.
- Seguridad: autenticacion y roles en `includes/auth.php` con reset por correo.
- IA: `api/analizar_feedback.php`.
- Configuracion: `config/app.php`, `config/db.php`, `config/openai.php`.
- UI compartida: `includes/header_public.php`, `includes/header_admin.php`, `includes/footer.php`, `includes/estilos_feedback.php`.

## Dependencias externas
- MySQL (persistencia).
- OpenAI Responses API (analisis de IA).
- CDN de Bootstrap y Bootstrap Icons (UI).
- Chart.js CDN (graficos en dashboard).
- api.qrserver.com (generacion de QR para campañas).

## Flujo principal (publico)
1) Usuario abre un enlace con `token` de campaña.
2) `index.php` valida la campaña activa y muestra entrada.
3) `formulario.php` valida el token y muestra el formulario.
4) `guardar_feedback.php` valida y guarda la respuesta en `fb_respuestas`.
5) Se invoca `analizarFeedbackConIA()` para generar analitica con IA.
6) Redireccion a `gracias.php`.

## Flujo de administracion
- `admin/campanas.php`: crea campañas y expone enlaces con token.
- `admin/qr_campana.php`: muestra QR para un token.
- `admin/respuestas.php`: lista respuestas, filtra por campaña y sentimiento, permite ejecutar o reejecutar IA.
- `admin/detalle_feedback.php`: muestra detalle de una respuesta y su analisis IA.
- `admin/dashboard.php`: KPIs agregados y graficos por sentimiento, temas y hallazgos.

## Diagrama logico (alto nivel)
```
[Usuario] -> [Publico] -> [MySQL]
                    -> [OpenAI Responses API]
[Admin]  -> [Admin UI] -> [MySQL]
```

## Consideraciones de seguridad
- No existe autenticacion para el modulo admin en el codigo actual.
- La salida HTML usa `htmlspecialchars` en la mayoria de los puntos de render.
- Las consultas SQL usan `prepare` y parametros.
- Las credenciales de DB y la clave OpenAI estan en archivos de configuracion.
