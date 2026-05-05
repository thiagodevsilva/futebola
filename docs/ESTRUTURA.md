# Estrutura do projeto Futebola

Documento de referência da base de código para desenvolvimento e para ferramentas/agentes que precisem entender o projeto.

---

## Visão geral

- **Produto**: portal de futebol brasileiro (notícias via RSS, tabela Série A, próximos jogos). Dados de futebol: **football-data.org** (grátis para sempre, recomendado) ou API-Football / API Futebol como alternativas.
- **Backend**: Laravel 12, API-first, Octane (Swoole).
- **Frontend**: Vue 3 + Vue Router + Tailwind CSS 4 + daisyUI, SPA consumindo `/api/*`.
- **Banco**: MySQL 8 (dev e prod via Docker).
- **Ambientes**: desenvolvimento e produção via **Docker** (docker-compose.dev.yml e docker-compose.prod.yml).

---

## Docker (forma oficial de rodar)

### Desenvolvimento (`docker-compose.dev.yml`)

| Serviço | Imagem / Build | Portas (host) | Função |
|--------|-----------------|---------------|--------|
| **app** | `docker/php/Dockerfile` (PHP 8.3 + Swoole + Composer) | 8081 → 8000 | Laravel Octane; volume `./:/var/www` |
| **db** | mysql:8.0 | 33061 → 3306 | MySQL (MYSQL_DATABASE=app, MYSQL_USER=app, MYSQL_PASSWORD=app) |
| **node** | node:22-alpine | 5173 → 5173 | `npm install && npm run dev` (Vite); volume `./:/var/www` |

- **Subir**: `docker compose -f docker-compose.dev.yml up --build`
- **App**: http://localhost:8081  
- **Front (Vite)**: http://localhost:5173  
- **MySQL**: localhost:33061 (user `app`, database `app`)

Comandos Laravel (migrate, seed, queue, scheduler) rodam **dentro do container app**:

```bash
docker compose -f docker-compose.dev.yml exec app sh
# dentro do container:
php artisan migrate
php artisan db:seed
php artisan queue:work
php artisan schedule:work
```

### Produção (`docker-compose.prod.yml`)

| Serviço | Descrição |
|--------|------------|
| **app** | Build igual ao dev; `composer install --no-dev -o`; Octane; porta **9001** no host. Usa `.env` do host. |
| **db** | MySQL 8.0, sem porta exposta; dados em volume `dbdata`. |

- **Sem serviço Node**: o front é buildado antes (ex.: `npm run build`) e os assets ficam em `public/build`, servidos pelo Laravel.
- Filas e scheduler: rodar no host (supervisor/cron) ou em container separado, apontando para o mesmo banco e `.env`.

---

## Onde está cada coisa no código

| Área | Caminho | Observação |
|------|---------|------------|
| **Rotas API** | `routes/api.php` | GET /api/news, /api/news/:id, /api/standings, /api/fixtures, /api/sources |
| **Rotas Web** | `routes/web.php` | SPA: qualquer rota → `app.blade.php` |
| **Scheduler** | `routes/console.php` | Jobs RSS (15 min), standings (1h), fixtures (30 min) |
| **Migrations** | `database/migrations/` | feeds, news, leagues, standings, fixtures |
| **Models** | `app/Models/` | Feed, News, League, Standing, Fixture |
| **RSS** | `app/Services/Rss/` | RssParser, RssNormalizer, RssAggregatorService |
| **Futebol** | `app/Services/Football/` | FootballDataOrgClient, FootballDataOrgDataService (grátis); ApiFootballClient, FootballDataService; ApiFutebolClient, ApiFutebolDataService |
| **Jobs** | `app/Jobs/` | FetchRssFeedsJob, UpdateStandingsJob, UpdateFixturesJob |
| **Controllers API** | `app/Http/Controllers/Api/` | NewsController, StandingController, FixtureController, SourceController |
| **Config football-data.org** | `config/football-data-org.php` | Token e TTL (recomendado: grátis para sempre) |
| **Config API-Football** | `config/api-football.php` | Chave e TTL (alternativa) |
| **Frontend (Vue)** | `resources/js/` | app.js, App.vue, router/, pages/, components/, api.js |
| **View SPA** | `resources/views/app.blade.php` | Única view HTML; monta o Vue em `#app` |
| **Assets (CSS)** | `resources/css/app.css` | Tailwind + daisyUI + tokens Futebola (#BAFF39, #6E6E6E) |
| **Build (Vite)** | `vite.config.js` | Laravel Vite plugin + Vue plugin + Tailwind; entrada: resources/js/app.js, resources/css/app.css |
| **Seeds** | `database/seeders/` | FeedSeeder (feeds RSS), LeagueSeeder (ligas API-Football); chamados por DatabaseSeeder |
| **Docs** | `docs/` | SETUP-LOCAL.md (Docker dev/prod), RSS-GUIA-TECNICO.md, ESTRUTURA.md (este arquivo) |

---

## Dados de futebol (tabela e próximos jogos)

- **Recomendado (grátis para sempre):** token no `.env`: `FOOTBALL_DATA_ORG_TOKEN=...` (obter em [football-data.org](https://www.football-data.org/)). Inclui Brasileirão Série A/B e Copa do Brasil. O comando de sync prioriza football-data.org quando o token está definido.
- **Alternativas:** `API_FOOTBALL_KEY` (api-football.com; plano grátis com limite de temporadas) ou API Futebol (api-futebol.com.br; trial 7 dias, depois pago). Código mantido para quem tiver assinatura.
- Sincronizar: `php artisan futebola:sync-football` (ou jobs `UpdateStandingsJob` / `UpdateFixturesJob`). Testar token football-data.org: `php artisan futebola:test-football-data-org`.
- Ligas com `football_data_org_code` no seed: Série A (BSA), Série B (BSB), Copa do Brasil (CDB). Outras ligas (Libertadores, Sul-Americana) usam API-Football se configurada.

## .env em desenvolvimento (Docker)

Para o container **app** conectar no MySQL do container **db**, use no `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=app
DB_USERNAME=app
DB_PASSWORD=app
```

(As variáveis do container `db` estão em `docker-compose.dev.yml`; o app usa o `.env` do projeto, que deve bater com esses valores.)

---

## Resumo de comandos (Docker dev)

| Ação | Comando |
|------|--------|
| Subir ambiente | `docker compose -f docker-compose.dev.yml up --build` |
| Entrar no app | `docker compose -f docker-compose.dev.yml exec app sh` |
| Migrar | `docker compose -f docker-compose.dev.yml exec app php artisan migrate` |
| Seed | `docker compose -f docker-compose.dev.yml exec app php artisan db:seed` |
| Fila | `docker compose -f docker-compose.dev.yml exec app php artisan queue:work` |
| Scheduler | `docker compose -f docker-compose.dev.yml exec app php artisan schedule:work` |

Produção: build do front antes de subir a imagem (`npm run build`); fila e scheduler no host ou em processo/container dedicado.
