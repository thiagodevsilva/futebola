# RSS e Atom — Guia técnico e didático

Documentação didática e técnica sobre RSS/Atom e como o Futebola usa feeds para agregar notícias sem violar direitos autorais.

---

## 1. O que é RSS e Atom (alto nível)

**RSS** (Really Simple Syndication) e **Atom** são formatos de **feed web**: uma forma padronizada de publicar listas de itens (notícias, posts, episódios) para que outros sistemas possam **consumir** sem precisar raspar a página.

- O **site publicador** expõe um **feed** (um URL que devolve XML).
- O **agregador** (como o Futebola) **baixa** esse XML periodicamente, **interpreta** os itens e **exibe** título, resumo, link e data, sempre direcionando o leitor ao **site original** para ler a matéria completa.

Assim, o portal **agrega** (organiza e lista) em vez de **replicar** (copiar o texto inteiro).

---

## 2. Estrutura de um feed

### RSS 2.0

- **Channel**: contêiner do feed.
  - `title`, `link`, `description`, `language`, `lastBuildDate`
- **Item**: cada notícia/post.
  - `title`: título
  - `link`: URL da matéria
  - `pubDate`: data de publicação (formato RFC 2822)
  - `guid`: identificador único (pode ser o próprio link)
  - `description`: resumo ou trecho
  - `author`: autor (opcional)
  - `enclosure`: mídia (ex.: imagem) com `url`, `type`, `length`

Alguns feeds usam o namespace **content:encoded** para o corpo completo; em agregadores devemos usar apenas um **resumo curto** (excerpt), nunca o artigo inteiro.

### Atom

- **feed**: raiz do documento.
- **entry**: cada item.
  - `title`, `id`, `updated`, `published`
  - `link` com `rel="alternate"` e `href` para a página
  - `author/name`, `content` ou `summary`

### Diferença resumida

| Aspecto     | RSS 2.0      | Atom                |
|------------|---------------|----------------------|
| Data       | `pubDate`     | `published` / `updated` |
| ID         | `guid`        | `id`                 |
| Conteúdo   | `description`, `content:encoded` | `content`, `summary` |
| Padrão     | Menos rígido  | RFC 4287, mais rígido |

O Futebola aceita **os dois** formatos e normaliza para um único modelo interno (título, link, data, autor, excerpt, imagem, fonte).

---

## 3. Boas práticas na agregação

- **Intervalos de atualização**: não bater no feed a cada segundo. O Futebola usa **15–30 minutos** (configurável no scheduler). Respeitar `ttl` quando o feed informar.
- **Deduplicação**: usar **link** (ou `guid`/`id`) como chave única; não inserir a mesma notícia duas vezes.
- **Ordenação**: exibir por **data de publicação** (mais recente primeiro).
- **Timezone**: converter datas para o fuso do app (ex.: America/Sao_Paulo) e armazenar em UTC ou com timezone consistente.
- **Falhas**: timeout, retry com backoff e user-agent identificável; logs e métricas (itens novos por feed) para debugar feed “quebrado”.

---

## 4. Arquitetura do sistema Futebola

### Fluxos

1. **Ingestão**: job `FetchRssFeedsJob` (agendado a cada 15 min) para cada feed ativo.
2. **Persistência**: parser → normalização → deduplicação por `link` → insert em `news` (com `feed_id`, título, excerpt, data, autor, link, imagem).
3. **Exposição**: API `GET /api/news` com paginação e filtro por fonte; front consome e sempre mostra “Ler no site original” com o link.

### Cache

- **RSS**: não cacheamos o XML por muito tempo; o “cache” é o próprio banco (últimos itens já salvos).
- **API-Football**: cache em Redis (ou configurado) para standings (ex.: 1h) e fixtures (ex.: 30 min) para reduzir requests e respeitar limite do plano grátis.

### Estratégia para não violar direitos autorais

- Armazenar e exibir apenas: **título**, **resumo curto** (excerpt), **data**, **autor**, **fonte**, **link original**, **imagem** (quando existir no feed).
- **Não** fazer scraping da página da notícia; **não** copiar o corpo do artigo.
- Sempre **linkar** e incentivar “Ler no site original”.

---

## 5. Manutenção

### Como adicionar um novo feed

1. Inserir em `feeds`: nome, url, categoria, ativo, prioridade, idioma (via seeder, migration ou tinker).
2. O job agendado passará a incluir esse feed na próxima execução.
3. Opcional: `FetchRssFeedsJob::dispatch($feedId)` para processar só esse feed.

### Como debugar um feed quebrado

- Ver logs: `storage/logs/laravel.log` (erros de fetch, parse, insert).
- Testar o URL do feed no navegador ou com `curl`; validar se o XML está bem formado.
- Rodar o job para um feed: `FetchRssFeedsJob::dispatch($feedId)` e observar quantidade de itens novos nos logs.
- Checar timezone e formato de data (pubDate/updated) se as datas aparecerem erradas.

### Como medir taxa de novidades por fonte

- Contar `news` por `feed_id` e por intervalo de tempo (ex.: últimos 7 dias).
- Logs do job já registram “new_items” por feed em cada execução; pode-se agregar em métricas/observabilidade.

---

## 6. Checklist de produção

- [ ] Logs: nível adequado, rotação, não logar dados sensíveis.
- [ ] Rate limits: evitar sobrecarga nos servidores dos feeds (intervalo do scheduler).
- [ ] Cache: Redis para API-Football; TTL configurável.
- [ ] Filas: queue worker estável (supervisor/systemd); retries e dead-letter.
- [ ] Scheduler: crontab ou `schedule:work` ativo.
- [ ] Observabilidade: saúde da fila, taxa de sucesso dos jobs, tempo de resposta da API.

---

*Este guia faz parte do projeto Futebola e deve ser usado junto com o [SETUP-LOCAL.md](SETUP-LOCAL.md) para ambiente de desenvolvimento e implantação.*
