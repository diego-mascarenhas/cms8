# IDONEO apps

Google Analytics property: `G-X66DMWHTPT`.

The Next.js apps and Access Validation load that property in production. Local `next dev` does not send pageviews. Projects sends only on a production build, and only when `VITE_GOOGLE_ANALYTICS_ID` is set. cms8 (Humano) has the snippet, and `GOOGLE_ANALYTICS_ID` is empty, so it does not send.

`idoneo-business` is the shared package for Perfil and Negocio. It is not its own app.

| App | Stage | Local | Public | Analytics |
| --- | --- | --- | --- | --- |
| Web | site | http://localhost:3014 | https://idoneo.dev | Yes |
| Mailer | MVP | http://localhost:3001 | https://mailer.idoneo.dev | Yes |
| Affiliates | MVP | http://localhost:3002 | https://affiliates.idoneo.dev | Yes |
| Ads | MVP | http://localhost:3003 | https://ads.idoneo.dev | Yes |
| Projects | MVP | http://localhost:3004 | https://projects.idoneo.dev | Yes, from `VITE_GOOGLE_ANALYTICS_ID` |
| Shop | Product | http://localhost:3005 | https://pedimosfacil.com | Yes |
| Assistant | Product | http://localhost:3006 | https://wapify.me | Yes |
| Estimator | MVP | http://localhost:3007 | https://estimator.idoneo.dev | Yes |
| Communications | MVP | http://localhost:3012 | https://communications.idoneo.dev | Yes |
| Tickets | MVP | http://localhost:3013 | https://tickets.idoneo.dev | Yes |
| Content | MVP | http://localhost:3015 | https://content.idoneo.dev | Yes |
| Academy | Product | http://localhost:3016 | https://academy.idoneo.dev | Yes |
| Strategy | Product | http://localhost:3017 | https://strategy.idoneo.dev | Yes |
| Billing | Product | http://localhost:3018 | https://billing.idoneo.dev | Yes |
| Access Validation | MVP | http://localhost:4001 | https://access-validation.idoneo.dev | Yes |
| cms8 | Product | https://cms8.test | https://humano.app | Snippet only. `GOOGLE_ANALYTICS_ID` is empty |
| Filament | Product | | https://fanyion.com | `GOOGLE_ANALYTICS_MEASUREMENT_ID` is empty in `app.fanyion` |

Communications and Tickets use the default property in code. Their `.env.example` does not list `NEXT_PUBLIC_GOOGLE_ANALYTICS_ID`.
