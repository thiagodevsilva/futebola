# Futebola

Portal de futebol brasileiro: notícias agregadas via RSS, tabela do Brasileirão Série A e próximos jogos.  
Stack: **Laravel 12** (API + Octane/Swoole) + **Vue 3** + **Tailwind CSS** + **daisyUI**. Banco **MySQL 8** via Docker.

- **Home**: últimas notícias, tabela Série A, próximos jogos, fontes
- **Notícias**: lista e detalhe com “Ler no site original”
- **Fontes**: listagem dos feeds RSS

---

## Pré-requisitos

- [Docker](https://docs.docker.com/get-docker/) e Docker Compose instalados
- (Opcional) Token da [football-data.org](https://www.football-data.org/) — grátis — para tabela e próximos jogos

---

## Início rápido (primeira vez)

```bash
cd futebola

# 1. Parar outros containers Docker (se houver conflito de portas ou quiser ambiente limpo)
docker stop $(docker ps -q)

# 2. Subir o projeto (app + MySQL + Vite)
docker compose -f docker-compose.dev.yml up --build
```

> **Importante:** o projeto usa `docker-compose.dev.yml`, não o `docker-compose.yml` padrão.  
> Por isso `docker compose down` sem `-f` retorna *"no configuration file provided"*.

Deixe o comando do passo 2 **rodando** no terminal (sem `-d`). Os três serviços precisam estar ativos:

| Serviço | Função | URL / porta |
|---------|--------|-------------|
| **app** | Laravel Octane | http://localhost:8081 |
| **db** | MySQL 8 | localhost:33061 (user `app`, senha `app`, db `app`) |
| **node** | Vite (hot reload) | http://localhost:5173 |

### Configurar `.env` (primeira vez)

Se ainda não existir `.env`:

```bash
docker compose -f docker-compose.dev.yml run --rm app sh -c "cp -n .env.example .env 2>/dev/null; php artisan key:generate"
```

No `.env`, configure o banco para o MySQL do container:

```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=app
DB_USERNAME=app
DB_PASSWORD=app
```

### Migrar, seed e popular dados

```bash
# Banco de dados
docker compose -f docker-compose.dev.yml exec app php artisan migrate
docker compose -f docker-compose.dev.yml exec app php artisan db:seed

# Notícias RSS (busca feeds cadastrados)
docker compose -f docker-compose.dev.yml exec app php artisan futebola:fetch-rss

# Imagens das notícias (og:image na página — ver seção abaixo)
docker compose -f docker-compose.dev.yml exec app php artisan futebola:backfill-news-images
```

### Imagens das notícias

Os feeds RSS **nem sempre trazem imagem**. O Futebola preenche `image_url` em duas etapas:

1. **No fetch RSS** — extrai thumbnail/media do feed ou da descrição HTML (`RssNormalizer`)
2. **Backfill manual** — para notícias ainda sem foto, o comando acessa o link da matéria e lê a meta `og:image` da página (`OgImageService`)

Depois de `futebola:fetch-rss`, rode:

```bash
docker compose -f docker-compose.dev.yml exec app php artisan futebola:backfill-news-images
```

Opções úteis:

- `--limit=50` — processa no máximo 50 notícias (padrão: 100; máximo: 500)
- O comando faz pausa de ~0,8 s entre cada URL para evitar rate limit

Se alguma fonte (ex.: Gazeta) continuar sem imagem, rode de novo com `--limit=20` ou ajuste o timeout em `app/Services/OgImageService.php`.

Para inspecionar se um feed traz imagem no RSS: `php artisan futebola:inspect-feed {url_do_feed}`.

### Tabela e próximos jogos (opcional)

1. Crie conta em [football-data.org](https://www.football-data.org/) e coloque o token no `.env`:
   ```env
   FOOTBALL_DATA_ORG_TOKEN=seu_token_aqui
   ```
2. Sincronize:
   ```bash
   docker compose -f docker-compose.dev.yml exec app php artisan futebola:sync-football
   ```

Sem token, a home mostra “indisponível” para tabela e jogos — o resto funciona normalmente.

### Atualizações automáticas (opcional, em terminais separados)

```bash
# Worker de filas (RSS, standings, fixtures)
docker compose -f docker-compose.dev.yml exec app php artisan queue:work --tries=2

# Scheduler (dispara jobs a cada 15 min / 30 min / 1h)
docker compose -f docker-compose.dev.yml exec app php artisan schedule:work
```

---

## Uso diário

### Subir

```bash
cd futebola
docker compose -f docker-compose.dev.yml up
```

Com rebuild da imagem (após mudanças no Dockerfile ou dependências PHP):

```bash
docker compose -f docker-compose.dev.yml up --build
```

Em background (sem ver logs no terminal):

```bash
docker compose -f docker-compose.dev.yml up -d
```

### Parar

```bash
# Parar só o Futebola
docker compose -f docker-compose.dev.yml down

# Parar e remover o volume do banco (apaga dados!)
docker compose -f docker-compose.dev.yml down -v
```

### Parar todos os containers Docker da máquina

```bash
docker stop $(docker ps -q)
```

---

## Frontend em desenvolvimento

Com **app + db + node** rodando, o Vite recompila automaticamente ao salvar `.vue`, `.js` ou `.css`. Basta recarregar http://localhost:8081.

- Se subir **só app e db** (sem **node**), o Laravel usa o build antigo em `public/build/` e cada alteração exige rebuild manual.
- **Tela em branco?** Remova o arquivo `public/hot` e force refresh (Ctrl+Shift+R):
  ```bash
  docker compose -f docker-compose.dev.yml exec app rm -f public/hot
  docker compose -f docker-compose.dev.yml run --rm node npm run build
  ```

→ Detalhes: [docs/DEPOIS-DE-ALTERAR.md](docs/DEPOIS-DE-ALTERAR.md)

---

## Comandos úteis

Todos rodam **dentro do container app** (prefixo `docker compose -f docker-compose.dev.yml exec app`):

| Ação | Comando |
|------|---------|
| Entrar no container | `docker compose -f docker-compose.dev.yml exec app sh` |
| Migrar | `php artisan migrate` |
| Seed | `php artisan db:seed` |
| Buscar notícias RSS | `php artisan futebola:fetch-rss` |
| Preencher imagens (og:image) | `php artisan futebola:backfill-news-images` |
| Inspecionar feed RSS | `php artisan futebola:inspect-feed {url}` |
| Sincronizar tabela/jogos | `php artisan futebola:sync-football` |
| Validar notícias | `php artisan futebola:validate-news` |
| Limpar cache | `php artisan config:clear && php artisan cache:clear` |
| Build do front (sem node) | `docker compose -f docker-compose.dev.yml run --rm node npm run build` |

### Notícias por time

Em `config/serie_a_teams.php` você define times e portais (GE, Gazeta, etc.). Depois:

```bash
php artisan futebola:clear-news
php artisan futebola:sync-feeds-from-standings
php artisan futebola:fetch-rss
php artisan futebola:backfill-news-images
```

---

## Produção (VPS)

Guia completo: **[docs/DEPLOY-VPS.md](docs/DEPLOY-VPS.md)** (`.env`, porta, Nginx, SSL, fila, troubleshooting).

Resumo (primeira vez em `/opt/futebola`):

```bash
# 1. .env de prod — APP_URL e ASSET_URL em https://, DB_* = MYSQL_*, APP_KEY via openssl
#    echo "base64:$(openssl rand -base64 32)"

# 2. Front (sem npm no host)
docker run --rm -v "$(pwd)":/var/www -w /var/www node:22-alpine \
  sh -c "npm install --legacy-peer-deps && npm run build"

# 3. App + MySQL (porta host 9003 — evitar conflito com outros sites em 9001/9002)
docker compose -f docker-compose.prod.yml up --build -d

# 4. Banco e dados
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --force
docker compose -f docker-compose.prod.yml exec app php artisan futebola:sync-feeds-from-standings
docker compose -f docker-compose.prod.yml exec app php artisan futebola:fetch-rss
docker compose -f docker-compose.prod.yml exec app php artisan futebola:backfill-news-images
docker compose -f docker-compose.prod.yml exec app php artisan futebola:sync-football

# 5. Nginx no host → proxy_pass http://127.0.0.1:9003 + certbot no subdomínio
# 6. Cron schedule:run + queue:work (ver DEPLOY-VPS.md)
```

Nginx do host faz proxy para o Docker; MySQL fica só na rede interna do Compose.

---

## Documentação

| Documento | Conteúdo |
|-----------|----------|
| [docs/SETUP-LOCAL.md](docs/SETUP-LOCAL.md) | Setup completo (dev/prod, migrate, seed, fila, scheduler) |
| [docs/DEPLOY-VPS.md](docs/DEPLOY-VPS.md) | Deploy na VPS (Docker + Nginx + SSL + syncs) |
| [docs/ESTRUTURA.md](docs/ESTRUTURA.md) | Estrutura do código, serviços, rotas, pastas |
| [docs/RSS-GUIA-TECNICO.md](docs/RSS-GUIA-TECNICO.md) | Agregação RSS/Atom e manutenção |
| [docs/DEPOIS-DE-ALTERAR.md](docs/DEPOIS-DE-ALTERAR.md) | O que rodar após alterar Vue, Blade ou PHP |

---

## Estrutura Docker

```
docker/php/Dockerfile       — PHP 8.3 + Swoole + Composer
docker-compose.dev.yml      — app (Octane), MySQL, Node (Vite)
docker-compose.prod.yml     — produção (sem Node; assets pré-buildados)
```

---

*Laravel 12 + Octane (Swoole). Banco: MySQL 8.*
