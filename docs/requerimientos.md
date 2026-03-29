# Requerimientos

## Runtime
- PHP 8.0+ (se usa `match` y `declare(strict_types=1)`).
- Extensiones PHP: `pdo`, `pdo_mysql`, `curl`, `json`.
- Servidor web: Apache o Nginx (MAMP funciona).
- MySQL 5.7+ o MariaDB compatible.

## Configuracion
- `config/app.php`: nombre, subtitulo, logo y `APP_URL`.
- `config/db.php`: host, base, usuario y clave de MySQL.
- `config/openai.php`: `OPENAI_API_KEY` y `OPENAI_MODEL`.

## Dependencias externas (CDN/API)
- Bootstrap 5.3 (CDN).
- Bootstrap Icons (CDN).
- Chart.js (CDN).
- OpenAI Responses API (analisis IA).
- api.qrserver.com (generacion de QR).

## Permisos y red
- El servidor debe poder hacer requests salientes a OpenAI y al servicio QR.
- Acceso al motor MySQL desde PHP.

## Notas
- No hay autenticacion para `/admin` en el codigo actual.
- Se recomienda mover credenciales a variables de entorno y restringir acceso a `/admin`.
