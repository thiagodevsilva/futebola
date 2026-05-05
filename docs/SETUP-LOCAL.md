# Setup local — Futebola

O projeto é pensado para rodar com **Docker** em dev e prod. Em local você usa o **docker-compose de desenvolvimento**; não é obrigatório ter PHP nem Node instalados na máquina.

---

## Pré-requisitos

- [Docker](https://docs.docker.com/get-docker/) e Docker Compose
- (Opcional) Token da [football-data.org](https://www.football-data.org/) para tabela e próximos jogos — **grátis para sempre**; ou chave da [API-Football](https://www.api-football.com/) como alternativa

---

## 1. Desenvolvimento com Docker (recomendado)

### Subir os serviços

```bash
cd futebola
docker compose -f docker-compose.dev.yml up --build
```

Isso sobe:

- **app** (Laravel Octane) em http://localhost:8081  
- **db** (MySQL 8) em localhost:33061  
- **node** (Vite) em http://localhost:5173  

### Frontend em dev (Vue / Tailwind) — sem comando para “aplicar”

Com os **três** serviços rodando (`app`, `db`, **node**), o Vite fica ativo no container **node**. O Laravel carrega o JS/CSS do Vite em tempo real.

- **Não é preciso rodar nenhum comando** para ver alterações em `.vue`, `.js` ou `.css`: basta **salvar o arquivo**. O Vite recompila e o navegador atualiza (hot reload). Acesse a aplicação em http://localhost:8081.
- Se você subir só `app` e `db` (sem o serviço **node**), o Laravel usa o build antigo em `public/build/`. Aí sim cada mudança no front exigiria rodar o build manualmente (ruim para dev). **Sempre suba o compose completo** para desenvolver o front com hot reload.
- **Build manual** (só quando for gerar assets para produção ou quando rodar sem o **node**):
  ```bash
  docker compose -f docker-compose.dev.yml run --rm node npm run build
  ```
  Isso gera os arquivos em `public/build/`.

**Tela em branco ou “não carrega”?** Se você subiu só **app** e **db** (sem **node**), o Laravel pode estar tentando carregar o JS do Vite (arquivo `public/hot` deixado por uma execução anterior). Para forçar o uso do build e a tela voltar a carregar:
```bash
docker compose -f docker-compose.dev.yml exec app rm -f public/hot
```
Depois dê um refresh na página (ou hard refresh: Ctrl+Shift+R). Se ainda não tiver build recente, rode o build acima e refresque de novo.

**Resumo:** para **não precisar buildar a cada alteração**, suba sempre os **três** serviços (`docker compose -f docker-compose.dev.yml up`). Assim o Vite fica no ar e cada mudança em Vue/CSS aparece ao salvar. **Se mudou algo no front e não apareceu:** rode o build e dê hard refresh (Ctrl+Shift+R):
```bash
docker compose -f docker-compose.dev.yml run --rm node npm run build
```

### .env e chave (primeira vez)

Se ainda não tiver `.env`:

```bash
docker compose -f docker-compose.dev.yml run --rm app sh -c "cp -n .env.example .env 2>/dev/null; php artisan key:generate"
```

Para o app conectar no MySQL do container, use no `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=app
DB_USERNAME=app
DB_PASSWORD=app
```

(Os valores vêm do `docker-compose.dev.yml` do serviço `db`.)

### Migrar e seed

Rodar **dentro do container app**:

```bash
docker compose -f docker-compose.dev.yml exec app php artisan migrate
docker compose -f docker-compose.dev.yml exec app php artisan db:seed
```

Ou entrando no container:

```bash
docker compose -f docker-compose.dev.yml exec app sh
php artisan migrate
php artisan db:seed
exit
```

### Notícias RSS — primeira vez

Para **aparecer notícias** na home, é preciso rodar o fetch dos feeds **pelo menos uma vez** (o scheduler envia o job para a fila, mas o job só executa se um worker estiver rodando). Rode dentro do container:

```bash
docker compose -f docker-compose.dev.yml exec app php artisan futebola:fetch-rss
```

Isso busca os feeds cadastrados (Globo Esporte, ESPN, UOL, etc.) e grava as notícias no banco. Depois disso, a home e a página /news passam a exibir as notícias.

### Filas e scheduler (atualizações automáticas)

Para **atualizações automáticas** (novas notícias a cada 15 min, tabela/jogos conforme agendado), rode o worker e o scheduler. Em dev, em **outro terminal** (com o compose ainda de pé):

```bash
# Worker de filas (RSS e jobs de standings/fixtures)
docker compose -f docker-compose.dev.yml exec app php artisan queue:work --tries=2

# Em outro terminal: scheduler (dispara os jobs a cada 15 min / 1h / 30 min)
docker compose -f docker-compose.dev.yml exec app php artisan schedule:work
```

### Tabela e próximos jogos (recomendado: football-data.org — grátis para sempre)

**Opção recomendada (sem cobrança):**

1. Crie uma conta em [football-data.org](https://www.football-data.org/) (registro gratuito, sem cartão).
2. No `.env`:
   ```env
   FOOTBALL_DATA_ORG_TOKEN=seu_token_aqui
   ```
3. Rode as migrações (inclui coluna `football_data_org_code` nas ligas) e o seed:
   ```bash
   docker compose -f docker-compose.dev.yml exec app php artisan migrate
   docker compose -f docker-compose.dev.yml exec app php artisan db:seed
   ```
4. Sincronize (dentro do container):
   ```bash
   docker compose -f docker-compose.dev.yml exec app php artisan futebola:sync-football
   ```
   Isso preenche a tabela e os próximos jogos para Série A, Série B e Copa do Brasil. Para testar o token: `php artisan futebola:test-football-data-org`.

**Alternativa (API-Football):** plano grátis com limite de temporadas (2022–2024). Defina `API_FOOTBALL_KEY` no `.env`; o sync usará API-Football apenas se `FOOTBALL_DATA_ORG_TOKEN` não estiver definido.

Sem nenhum token/chave ou sem rodar o comando, a home mostra “indisponível” para tabela e próximos jogos.

### Onde acessar

| Serviço       | URL                      |
|---------------|--------------------------|
| App (Laravel) | http://localhost:8081    |
| Front (Vite)  | http://localhost:5173    |
| MySQL         | localhost:33061 (app/app) |

Abra **http://localhost:8081** no navegador; o Laravel serve a SPA e o Vite injeta os assets em dev.

---

## 2. Produção (Docker)

Use o `docker-compose.prod.yml`:

- **app**: PHP 8.3 + Octane, porta **9001** no host.
- **db**: MySQL 8, sem porta exposta; variáveis via `.env`.

### Build do front antes do deploy

O compose de prod **não** sobe o Node. Build dos assets na máquina (ou em CI):

```bash
npm install --legacy-peer-deps
npm run build
```

Isso gera os arquivos em `public/build/`, que vão na imagem junto com o código.

### Subir prod

```bash
docker compose -f docker-compose.prod.yml up --build -d
```

### Filas e scheduler em produção

Rodar no host (ou em outro container) com o mesmo `.env` e código:

```bash
# Worker
php artisan queue:work --tries=2

# Scheduler (cron)
* * * * * cd /caminho/do/futebola && php artisan schedule:run >> /dev/null 2>&1
```

Ou usar supervisor para o worker e cron para o scheduler.

---

## 3. Comandos úteis (dentro do container app)

| Ação           | Comando |
|----------------|--------|
| Entrar no app  | `docker compose -f docker-compose.dev.yml exec app sh` |
| Migrar         | `docker compose -f docker-compose.dev.yml exec app php artisan migrate` |
| Seed           | `docker compose -f docker-compose.dev.yml exec app php artisan db:seed` |
| Rodar fila     | `docker compose -f docker-compose.dev.yml exec app php artisan queue:work` |
| Rodar scheduler| `docker compose -f docker-compose.dev.yml exec app php artisan schedule:work` |
| Limpar cache   | `docker compose -f docker-compose.dev.yml exec app php artisan config:clear && php artisan cache:clear` |

### Popular dados manualmente

Dentro do container (ou via `docker compose ... exec app php artisan tinker`):

```php
App\Jobs\FetchRssFeedsJob::dispatch();
App\Jobs\UpdateStandingsJob::dispatch();
App\Jobs\UpdateFixturesJob::dispatch();
```

---

## 4. Estrutura do projeto

Para visão geral da base (Docker, pastas, rotas, serviços), veja **[docs/ESTRUTURA.md](ESTRUTURA.md)**.

Para detalhes sobre RSS (agregação, boas práticas, manutenção), veja **[docs/RSS-GUIA-TECNICO.md](RSS-GUIA-TECNICO.md)**.
