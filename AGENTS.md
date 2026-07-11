# Referência para agentes / codebase Futebola

- **Rodar o projeto**: sempre via **Docker**. Dev: `docker compose -f docker-compose.dev.yml up --build`. Prod: `docker compose -f docker-compose.prod.yml`. Não assumir PHP/Node instalados no host para “rodar local”.
- **Estrutura completa** (serviços, pastas, rotas, onde está cada módulo): **[docs/ESTRUTURA.md](docs/ESTRUTURA.md)**.
- **Setup passo a passo** (env, migrate, seed, fila, scheduler): **[docs/SETUP-LOCAL.md](docs/SETUP-LOCAL.md)**.
- **Deploy VPS** (Nginx, SSL, porta, syncs): **[docs/DEPLOY-VPS.md](docs/DEPLOY-VPS.md)**.
- **RSS** (como funciona a agregação, manutenção): **[docs/RSS-GUIA-TECNICO.md](docs/RSS-GUIA-TECNICO.md)**.

Comandos Laravel (migrate, seed, queue, schedule) devem ser executados **dentro do container app**, por exemplo:  
`docker compose -f docker-compose.dev.yml exec app php artisan migrate`.
