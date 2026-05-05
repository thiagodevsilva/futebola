# Futebola

Portal de futebol brasileiro: notícias agregadas via RSS, tabela do Brasileirão Série A e próximos jogos (API-Football).  
Stack: **Laravel** (API) + **Vue 3** + **Tailwind CSS** + **daisyUI**. Banco MySQL via Docker.

- **Home**: últimas notícias, tabela Série A, próximos jogos, fontes
- **Notícias**: lista e detalhe com “Ler no site original”
- **Fontes**: listagem dos feeds RSS

**Tabela e jogos**: configure a API (football-data.org ou API-Football) no `.env` e rode o sync.

**Notícias (times da config)**: em `config/serie_a_teams.php` você define os times e vários portais (GE, Gazeta, etc.) por time. Depois:
- `php artisan futebola:clear-news` — remove todas as notícias
- `php artisan futebola:sync-feeds-from-standings` — cria/atualiza os feeds
- `php artisan futebola:fetch-rss` — busca as notícias

**Validar fora do código** (sem depender do front):
- `php artisan futebola:validate-news` — mostra feeds ativos, contagem por time e amostra de títulos; no final sugere URLs para testar a API no navegador (ex.: `/api/news?topic=sao-paulo`).

**Como rodar**: uso oficial é com **Docker** (dev e prod).  
→ [docs/SETUP-LOCAL.md](docs/SETUP-LOCAL.md) — setup completo (Docker dev/prod, migrate, seed, fila, scheduler)  
→ [docs/ESTRUTURA.md](docs/ESTRUTURA.md) — referência da base (onde está cada coisa, serviços, comandos)  
→ [docs/RSS-GUIA-TECNICO.md](docs/RSS-GUIA-TECNICO.md) — RSS/Atom e manutenção

## Pré-requisito

- [Docker](https://docs.docker.com/get-docker/) e Docker Compose

## Subir o projeto

```bash
docker compose -f docker-compose.dev.yml up --build
```

**Importante:** deixe esse comando rodando (não use `-d`). Os **três** containers precisam estar ativos:
- **app** (Laravel) → http://localhost:8081  
- **db** (MySQL)  
- **node** (Vite) → atualização automática ao editar Vue/JS/CSS (sem precisar de `npm run build`)

Se subir só `app` e `db`, o front usa o build antigo; aí toda alteração exige `npm run build:docker`. Com o **node** rodando, basta salvar o arquivo e recarregar a página.

Na primeira vez, se precisar de `.env` e chave:

```bash
docker compose -f docker-compose.dev.yml run --rm app sh -c "cp -n .env.example .env 2>/dev/null; php artisan key:generate"
```

Depois suba de novo: `docker compose -f docker-compose.dev.yml up`.

## Onde acessar

| Serviço      | URL                    |
|-------------|------------------------|
| App (Laravel) | http://localhost:8081 |
| Front (Vite)  | http://localhost:5173 |
| MySQL         | localhost:33061       |

## Depois de alterar o código (evitar tela branca)

→ **[docs/DEPOIS-DE-ALTERAR.md](docs/DEPOIS-DE-ALTERAR.md)** — o que rodar após cada alteração (Vue/JS/CSS, Blade, PHP).

**Resumo:** alterou Vue/JS/CSS → rode `npm run build:docker` (ou o comando do doc) e dê **Ctrl+Shift+R** no navegador.

## Comandos úteis

Rodar dentro do container da app:

```bash
# Entrar no container
docker compose -f docker-compose.dev.yml exec app sh

# Exemplos (de dentro do container ou com run)
php artisan migrate
php artisan make:controller NomeController
composer require pacote/nome
```

## Estrutura

- `docker/php/Dockerfile` — imagem PHP 8.3 + Swoole + Composer
- `docker-compose.dev.yml` — app (Octane), MySQL, Node (Vite)
- `docker-compose.prod.yml` — uso em produção (quando houver)

---

*Laravel 12 + Octane (Swoole). Banco: MySQL 8.*
