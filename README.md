## About CMS8 (Simplicity)

CMS8 started as a CMS and hosting toolkit. It has evolved into **Simplicity**: a modular, multi-tenant platform for running sales, billing, marketing, and operations in one place.

**What defines the product today**

- **Multi-tenant** — Jetstream teams with isolation and shared workspaces
- **Operations / CRM** — contacts, clients, projects, opportunities, and tasks
- **Billing** — Stripe / Cashier for subscriptions, invoices, and payments
- **Marketing & ads** — campaigns plus Meta and Google Ads integrations
- **Automation + AI** — prompts, funnels, and Laravel AI-powered workflows
- **Lightweight web** — landing pages and CMS with GrapesJS
- **Communications** — Twilio / WhatsApp and mailer

CMS8 is accessible, powerful, and built for teams that need CRM, billing, marketing, and automation in a single application.

## Built by IDONEO

**[IDONEO](https://www.idoneo.dev)** is the software studio behind CMS8 / Simplicity — the team that designed and developed this platform.

<p align="center">
  <a href="https://www.idoneo.dev">
    <img src="public/assets/idoneo-logo.svg" alt="IDONEO" width="180">
  </a>
</p>

These tools help companies **scale operations** and **innovate with structure**: run the business day to day, then turn innovation into a repeatable system.

<p align="center">
  <a href="https://humano.app">
    <img src="public/assets/humano-logo.png" alt="Humano" height="56">
  </a>
  &nbsp;&nbsp;&nbsp;&nbsp;
  <a href="https://www.fanyion.com">
    <img src="public/assets/fanyion-logo.svg" alt="Fanyion" height="56">
  </a>
</p>

### Humano

**[Humano](https://humano.app)** is the technology consulting brand and the operating layer for growing companies. It brings together CRM, billing, marketing, and automation so teams can run day-to-day work in one place: contacts and opportunities, subscriptions and invoices, campaigns and messaging, plus workflows that keep operations moving as the business scales.

### Fanyion

**[Fanyion](https://www.fanyion.com)** is the organizational innovation system. It turns innovation from isolated initiatives into a managed practice—with clear roles, decision flows, and cross-functional participation—so ideas can be captured, evaluated, and driven to impact across the company.

### CMS8 (Simplicity)

**CMS8 (Simplicity)** is the modular multi-tenant platform that powers that journey. Built by **IDONEO**, it is the shared backend for Humano-style operations and the foundation other IDONEO products connect to.

### Connected applications (Next.js)

Specialized frontends built with **Next.js** connect to CMS8 through its API (Sanctum / team context). They share the same multi-tenant core while focusing on one job each:

| App | URL | Role |
| --- | --- | --- |
| **Mailer** | [mailer.idoneo.dev](https://mailer.idoneo.dev) | Write, launch, and track outbound messages |
| **Projects** | [projects.idoneo.dev](https://projects.idoneo.dev/) | Project and quote workflows |
| **Ads** | [ads.idoneo.dev](https://ads.idoneo.dev) | Paid campaigns across Google, Meta, LinkedIn, TikTok, and X |
| **Affiliates** | [affiliates.idoneo.dev](https://affiliates.idoneo.dev) | Referral network, commissions, and partner accounts |

## Development

### Local setup

```sh
git clone git@github.com:diego-mascarenhas/cms8.git
cd cms8
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run dev
```

Serve the app with [Laravel Herd](https://herd.laravel.com) (recommended) or:

```sh
php artisan serve
```

### Production deployment (Laravel Forge)

Production deploys run on **Laravel Forge** with zero-downtime releases (`$CREATE_RELEASE` / `$ACTIVATE_RELEASE`):

1. Create a new release and install PHP dependencies (`composer install --no-dev`)
2. Build frontend assets (`npm ci && npm run build`)
3. Optimize Laravel, link storage, and run migrations
4. Activate the release (atomic symlink switch)
5. Restart queue workers (and WhatsApp/PM2 when applicable)

Full script and notes: [`docs/FORGE-DEPLOYMENT.md`](docs/FORGE-DEPLOYMENT.md).

### Recommended hosting

You can run automatic Forge deployments on a VPS from **REVISION ALPHA**:

<p align="center">
  <a href="https://revisionalpha.com/servidores-dedicados">
    <img src="public/assets/revision-alpha-logo.svg" alt="REVISION ALPHA" width="220">
  </a>
</p>

<p align="center">
  <a href="https://revisionalpha.com/servidores-dedicados">Dedicated servers &amp; VPS — REVISION ALPHA</a>
</p>

## API Documentation

For comprehensive details on how to interact with CMS8's backend, please refer to our API documentation available on Postman:

- [CMS8 API Documentation](https://www.postman.com/revisionalpha/workspace/simplicity/)

## Contributing

Thank you for considering contributing to the CMS8 admin!

## Security Vulnerabilities

If you discover a security vulnerability within CMS8, please send an e-mail to Diego Mascarenhas Goytía via [diego.mascarenhas@icloud.com](mailto:diego.mascarenhas@icloud.com). All security vulnerabilities will be promptly addressed.

## License

The CMS8 admin is open-sourced software licensed under the [GNU Affero General Public License v3.0](https://www.gnu.org/licenses/agpl-3.0.html)

### Additional Terms

By deploying this software, you agree to notify the original author at [diego.mascarenhas@icloud.com](mailto:diego.mascarenhas@icloud.com). or by visiting [http://linkedin.com/in/diego-mascarenhas/](http://linkedin.com/in/diego-mascarenhas/) Any modifications or enhancements must be shared with the original author.
