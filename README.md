# Forever Wellthy - Phase 1

Laravel 13 / PHP 8.3: GHL book orders, static Lulu print files, fulfillment
tracking, buyer/admin emails and an authenticated Blade admin panel.

Documentation reviewed: 2026-09-09.

## Start here

- [Handover](handover.md): verified findings and remaining deployment work.
- [Requirements](project-requirements.md): Phase 1 scope and order contract.
- [Rules](rules.md): invariants and documentation maintenance.
- [Operations](docs/operations.md): live setup, diagnostics and recovery.
- [Architecture](docs/architecture.md): class map, status and retry behavior.
- [Reference documents](docs/reference-documents.md): supplied guides and safe credential-validation results.
- [Agent entry point](AGENTS.md): concise instructions for the next coding agent.

## Local setup

Install dependencies with `composer install`. For a new checkout, create
`.env` from `.env.example`, configure a local database, and run
`php artisan key:generate` once. Never regenerate an existing production key.

```bash
php artisan migrate
php artisan test
php artisan serve --host=127.0.0.1 --port=8000
```

The app starts at `/admin/login`. Login uses `users`, not `.env` directly.
For a fresh account, configure `ADMIN_EMAIL` and a strong `ADMIN_PASSWORD`,
then explicitly run `php artisan db:seed`. Existing accounts are not overwritten.

Workers and scheduler are separate processes. Starting a worker can process ALL
pending orders with the configured Lulu credentials. Follow the operations guide
before using a populated database. Vite assets use `npm install` / `npm run build`.

`POST /api/webhooks/ghl` accepts the approved GHL workflow payload with
`X-GHL-Secret`. `/api/health` and `/up` are app checks, not proof that Lulu,
GHL, SMTP or workers are operational.

No credentials, customer fixtures or production DB dumps belong in Git.
