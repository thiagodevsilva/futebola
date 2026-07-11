# Deploy em VPS (produção)

Guia para subir o Futebola numa Ubuntu com **Docker** + **Nginx** no host (mesmo padrão dos outros sites em `*.thiagosilva.dev.br`).

Arquitetura:

```
Internet → Nginx (host, :443)
              ↓ proxy_pass http://127.0.0.1:9003
         container app (Octane/Swoole :8000)
              ↓ rede Docker
         container db (MySQL :3306, sem porta no host)
```

O Nginx **não** entra no Docker: só faz proxy para a porta que o Compose publica em `127.0.0.1`.

---

## Pré-requisitos

- Docker + Docker Compose na VPS
- Nginx no host
- DNS: registro **A** `futebola` → IP da VPS
- Código em `/opt/futebola` (ou outro path)

**Porta:** o compose de prod usa `127.0.0.1:9003:8000`. Ajuste se `9003` já estiver ocupada (no lab, `9001`/`9002` costumam estar em uso).

---

## 1. `.env` de produção

```bash
cd /opt/futebola
cp .env.example .env
nano .env
```

Mínimo:

```env
APP_NAME=Futebola
APP_ENV=production
APP_DEBUG=false
APP_URL=https://futebola.thiagosilva.dev.br
ASSET_URL=https://futebola.thiagosilva.dev.br

APP_KEY=

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=futebola
DB_USERNAME=futebola
DB_PASSWORD=senha_forte

# Container MySQL (primeira subida). Deve bater com DB_* acima.
MYSQL_DATABASE=futebola
MYSQL_USER=futebola
MYSQL_PASSWORD=senha_forte
MYSQL_ROOT_PASSWORD=senha_root_forte

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

OCTANE_SERVER=swoole

FOOTBALL_DATA_ORG_TOKEN=
# + demais tokens/portal conforme .env.example
```

### APP_KEY

Com `env_file` no Compose, `php artisan key:generate` costuma falhar (“already present in the environment”). Gere na mão:

```bash
echo "base64:$(openssl rand -base64 32)"
```

Cole o resultado em `APP_KEY=...` no `.env` (incluindo o prefixo `base64:`).

### Senha do banco

`DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` e `MYSQL_DATABASE` / `MYSQL_USER` / `MYSQL_PASSWORD` **têm que ser iguais**.  
O MySQL só aplica `MYSQL_*` na **primeira** criação do volume. Se mudar senha depois:

```bash
docker compose -f docker-compose.prod.yml down -v   # apaga dados deste projeto
```

### HTTPS / mixed content

Sem `APP_URL` e `ASSET_URL` em `https://...`, a página sobe mas CSS/JS saem em `http://` e o navegador bloqueia (tela em branco). Depois de alterar `.env`, **recriar** o app (Octane só lê env na subida):

```bash
docker compose -f docker-compose.prod.yml up -d --force-recreate app
```

---

## 2. Build do front (sem npm no host)

Não é necessário instalar Node/npm na VPS:

```bash
cd /opt/futebola

docker run --rm -v "$(pwd)":/var/www -w /var/www node:22-alpine \
  sh -c "npm install --legacy-peer-deps && npm run build"
```

Confirme: `public/build/manifest.json` existe.  
O build precisa existir **antes** do `docker compose build` (a imagem faz `COPY` do código).

---

## 3. Subir containers

```bash
docker compose -f docker-compose.prod.yml up --build -d
```

Na primeira subida o `app` roda `composer install` e só depois abre a porta — espere `Server running` nos logs:

```bash
docker compose -f docker-compose.prod.yml logs -f app
docker compose -f docker-compose.prod.yml ps
curl -I http://127.0.0.1:9003
```

### Migrate e seed

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --force
```

Sem migrate, a home retorna **500** (`Table 'futebola.sessions' doesn't exist`).

### Dados iniciais (syncs)

O `FeedSeeder` **não** cria feeds (é intencional). A ordem é: ligas → feeds a partir da config/standings → RSS → futebol.

```bash
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --force
docker compose -f docker-compose.prod.yml exec app php artisan futebola:sync-feeds-from-standings
docker compose -f docker-compose.prod.yml exec app php artisan futebola:fetch-rss
docker compose -f docker-compose.prod.yml exec app php artisan futebola:backfill-news-images
docker compose -f docker-compose.prod.yml exec app php artisan futebola:sync-football
```

(`sync-football` precisa de token/portal no `.env`. Pode rodar antes do sync de feeds se quiser standings atualizados nos nomes dos times.)

---

## 4. Nginx no host

Modelo alinhado aos outros apps Docker da VPS (`lab-docker-vue-nest`): proxy + SSL.

1. DNS **A** `futebola` → IP da VPS (`dig +short futebola.thiagosilva.dev.br A`).
2. Certificado: o cert de `thiagosilva.dev.br` **não** é wildcard — emita um para o subdomínio:

```bash
# HTTP temporário com acme-challenge (sem return 301 bloqueando)
# ver exemplo completo abaixo, depois:
mkdir -p /var/www/certbot
certbot certonly --webroot -w /var/www/certbot -d futebola.thiagosilva.dev.br
```

Exemplo `/etc/nginx/sites-available/futebola`:

```nginx
server {
  listen 80;
  listen [::]:80;
  server_name futebola.thiagosilva.dev.br;

  location ^~ /.well-known/acme-challenge/ {
    root /var/www/certbot;
    default_type "text/plain";
    allow all;
  }

  return 301 https://$host$request_uri;
}

server {
  listen 443 ssl http2;
  listen [::]:443 ssl http2;
  server_name futebola.thiagosilva.dev.br;

  ssl_certificate     /etc/letsencrypt/live/futebola.thiagosilva.dev.br/fullchain.pem;
  ssl_certificate_key /etc/letsencrypt/live/futebola.thiagosilva.dev.br/privkey.pem;
  ssl_protocols       TLSv1.2 TLSv1.3;
  ssl_ciphers         HIGH:!aNULL:!MD5;
  ssl_prefer_server_ciphers on;

  access_log /var/log/nginx/futebola-access.log;
  error_log  /var/log/nginx/futebola-error.log;

  location / {
    proxy_pass http://127.0.0.1:9003;
    proxy_http_version 1.1;
    proxy_set_header Host              $host;
    proxy_set_header X-Real-IP         $remote_addr;
    proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_connect_timeout 60s;
    proxy_send_timeout 60s;
    proxy_read_timeout 60s;
  }
}
```

```bash
ln -sf /etc/nginx/sites-available/futebola /etc/nginx/sites-enabled/futebola
nginx -t && systemctl reload nginx
```

---

## 5. Fila e scheduler

Sem isso, RSS/tabela não atualizam sozinhos.

Cron (scheduler):

```cron
* * * * * cd /opt/futebola && docker compose -f docker-compose.prod.yml exec -T app php artisan schedule:run >> /dev/null 2>&1
```

Worker (systemd/supervisor/screen — precisa ficar rodando):

```bash
docker compose -f docker-compose.prod.yml exec app php artisan queue:work --tries=2
```

---

## Deploys seguintes

```bash
cd /opt/futebola
git pull

docker run --rm -v "$(pwd)":/var/www -w /var/www node:22-alpine \
  sh -c "npm install --legacy-peer-deps && npm run build"

docker compose -f docker-compose.prod.yml up --build -d
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

---

## Troubleshooting rápido

| Sintoma | Causa comum |
|---------|-------------|
| `Connection refused` em `:9003` | App ainda no `composer install` ou caiu — `logs -f app` |
| HTTP 500 | Falta `migrate` (ex.: tabela `sessions`) |
| Tela branca, Mixed Content | `APP_URL`/`ASSET_URL` sem `https://` — recreate app |
| `APP_KEY` / key length | Key sem `base64:` ou tamanho errado — regenerar com openssl |
| Certbot NXDOMAIN | DNS A ainda não criado |
| Porta já em uso | Outro site em `9001`/`9002` — use `9003` (ou livre) |

---

## Observação sobre o build Docker

O `Dockerfile` compila Swoole via `pecl` — na primeira build pode demorar e consumir muita CPU/RAM na VPS. Em VPS pequena, considere swap ou build em horário ocioso. Rebuilds seguintes usam cache do Docker e ficam bem mais rápidos.
