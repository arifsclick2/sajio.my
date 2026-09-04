# Sajio.my — Simple POS. Simple Ordering. Simple Business.

Monorepo for the Sajio restaurant POS SaaS platform (see `Sajio_Plan_V1.md` for the full product & development plan).

## Structure

| Path  | Stack                                        | Live at              |
|-------|----------------------------------------------|----------------------|
| `api/` | Laravel 13 (PHP 8.3) + PostgreSQL + Redis + Sanctum | `https://api.sajio.my` |
| `web/` | Next.js 16 (App Router, TypeScript, Tailwind v4)     | `https://sajio.my`, `https://app.sajio.my`, `*.sajio.my` |

## Services (Phase 1 foundation)

- **Laravel API** — `POST /api/v1/auth/register`, `POST /api/v1/auth/login`, `GET /api/v1/auth/me`, `POST /api/v1/auth/logout`, `GET /api/health`
- **Next.js web** — marketing landing page + `/login` + `/register` wired to the API
- **Mailgun** (`e.sajio.my`) — transactional email via the `mailgun` HTTP transport
- **Stripe** — test keys configured (payment recording comes in a later phase)
- **Cloudflare** — DNS + wildcard TLS (`sajio.my` + `*.sajio.my`), Full (strict)

## Deployment (this server)

- API: nginx → PHP-FPM at `/var/www/sajio.my/api/public`
- Web: PM2 app `sajio-web` → `web/deploy.sh` (Next standalone on 127.0.0.1:3100; port 3000 is reserved by another site on this box)
- Certs: Let's Encrypt wildcard via DNS-01, auto-renewing (`certbot`)

## Env setup

Copy the `.env.example` in each subproject and fill in real values. Secrets live only in the server's `/root/.sajio-secrets` (never committed).
