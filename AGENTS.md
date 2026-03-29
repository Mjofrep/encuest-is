# Agent Guide for feedback

Purpose
- Provide build/test commands and code style rules for agentic changes.
- This repo is a small PHP app (no framework) with public + admin pages.

Rule Sources
- No Cursor rules found in `.cursor/rules/` or `.cursorrules`.
- No Copilot instructions found in `.github/copilot-instructions.md`.

Project Overview
- Public flow: `index.php` -> `formulario.php` -> `guardar_feedback.php` -> `gracias.php`.
- Admin flow: `admin/*` pages for dashboard, campaigns, responses, IA actions.
- IA analysis: `api/analizar_feedback.php` using OpenAI Responses API.
- DB access: `config/db.php` exposes `db()` returning a PDO instance.

Build / Lint / Test
- No build system configured (no composer.json, package.json, Makefile).
- No automated tests configured (no phpunit config present).
- No linter configured.

Suggested local commands
- Syntax check a single PHP file:
  - `php -l path/to/file.php`
- Quick static smoke check for the whole app (manual):
  - Start PHP built-in server and click through pages.
  - Example: `php -S localhost:8000 -t /Applications/MAMP/htdocs/feedback`

Single-test guidance
- There is no test runner in this repo.
- If you add tests later, document the command in this file and how to run one test.

Code Style and Conventions

General
- Use PHP 8+ features consistently (e.g., `match`, strict types).
- Keep files small and page-oriented; avoid introducing heavy frameworks.
- Default to ASCII text in files unless the file already uses accents.

File structure
- Public pages live at repo root: `index.php`, `formulario.php`, `guardar_feedback.php`.
- Admin pages live under `admin/` and include `includes/header_admin.php`.
- Shared UI in `includes/`.
- Configuration in `config/`.

Formatting
- `declare(strict_types=1);` at top of PHP files.
- Use 4-space indentation.
- Keep HTML readable; avoid large inline scripts unless necessary.
- Prefer short PHP blocks with HTML in between.

Imports / includes
- Use `require_once __DIR__ . '/relative.php'` for dependencies.
- Prefer `require` for headers/footers.
- Avoid global include paths.

Naming conventions
- Variables and array keys: snake_case (e.g., `$campana_id`).
- Function names: lowerCamelCase (e.g., `analizarFeedbackConIA`).
- Table and column names: snake_case (e.g., `fb_respuestas`).
- File names: snake_case where applicable (`detalle_feedback.php`).

Database access
- Use PDO prepared statements and parameter arrays.
- Centralize DB connection through `db()` in `config/db.php`.
- Use transactions when a write involves multiple statements.
- Always validate and cast numeric inputs before queries.

Input validation
- Validate `$_GET` and `$_POST` early; redirect on failure.
- Use `trim()` for string inputs.
- For required fields, check empty string and bounds (e.g., rating 1..5).
- On invalid input, redirect to safe pages with a query flag when needed.

Output escaping
- Use `htmlspecialchars()` on all dynamic HTML output.
- Use `nl2br(htmlspecialchars(...))` for multi-line user content.

Error handling
- Wrap multi-step DB writes in try/catch with rollback.
- Return or redirect on errors; avoid partial writes.
- Use `error_log()` for API failures or unexpected states.

HTTP behavior
- After any `header('Location: ...')`, call `exit;`.
- Ensure POST handlers only accept POST requests.

IA integration
- Keep OpenAI usage in `api/analizar_feedback.php`.
- Use JSON schema format with strict validation.
- Do not store API keys in code changes; reference config/ENV instead.

Security considerations
- There is no authentication for `/admin` routes.
- Avoid adding privileged features without access control.
- Do not log or expose secrets in output.

Styling/UI
- Bootstrap 5 and Bootstrap Icons via CDN.
- Keep UI consistent with existing components in `includes/estilos_feedback.php`.
- Avoid introducing new CSS frameworks.

Adding new pages
- Include `config/app.php` and appropriate header/footer.
- Use existing layout patterns (cards, `feedback-shell`).
- Update docs if new flows are added.

Documentation updates
- Update `docs/` when schema, flows, or requirements change.
- Keep `docs/diseno-tecnico.md` aligned with code behavior.

Operational notes
- `config/db.php` currently hardcodes credentials; do not commit real secrets.
- `config/openai.php` expects `OPENAI_API_KEY` and a model name.

Review checklist for changes
- Input validation and output escaping done.
- SQL is prepared and typed.
- Redirects exit properly.
- No secrets added to repo.
