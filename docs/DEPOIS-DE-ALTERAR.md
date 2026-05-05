# O que rodar depois de cada alteração

Siga **sempre** isso para não ter tela branca e ver as mudanças.

---

## 1. Você alterou **Vue, JS ou CSS** (pasta `resources/`)

Rode **só isto** (no terminal, na pasta do projeto):

```bash
docker compose -f docker-compose.dev.yml run --rm node npm run build
```

Ou, se tiver npm no seu PC:

```bash
npm run build
```

Depois: **Ctrl+Shift+R** no navegador (recarregar sem cache) em http://localhost:8081.

---

## 2. Você alterou **Blade** (`.blade.php`)

Rode:

```bash
docker compose -f docker-compose.dev.yml exec app php artisan octane:reload
```

Depois: **F5** no navegador.

---

## 3. Você alterou **só PHP** (controllers, models, config, comandos)

Na maioria das vezes **não precisa rodar nada**: a próxima requisição já usa o código novo.

Se não refletir:

```bash
docker compose -f docker-compose.dev.yml exec app php artisan octane:reload
```

---

## 4. Notícias sem imagem

Os feeds RSS muitas vezes não trazem imagem. As imagens vêm da **página da notícia** (meta `og:image`).

Depois de rodar `futebola:fetch-rss`, execute para preencher as fotos:

```bash
docker compose -f docker-compose.dev.yml exec app php artisan futebola:backfill-news-images
```

Opcional: use `--limit=50` para processar no máximo 50 notícias (evita muitas requisições).

**Por que ESPN tem imagem e Gazeta não?** O comando não trata fontes de forma diferente: para cada notícia sem imagem ele acessa o link da matéria e extrai o `og:image` do HTML. Se a Gazeta não preencher, as causas mais comuns são: (1) **timeout** — o site demorou mais que 15 s para responder; (2) **rate limit** — muitas requisições seguidas; o comando já faz uma pausa de ~0,8 s entre cada URL. Se ainda faltar imagem da Gazeta, rode de novo com `--limit=20` ou aumente o timeout em `OgImageService`.

---

## Resumo rápido

| Alterou              | Comando                                                                 | Depois      |
|----------------------|-------------------------------------------------------------------------|-------------|
| Vue / JS / CSS       | `docker compose -f docker-compose.dev.yml run --rm node npm run build` | Ctrl+Shift+R |
| Blade (.blade.php)   | `docker compose -f docker-compose.dev.yml exec app php artisan octane:reload` | F5          |
| Só PHP               | Nada (ou `octane:reload` se não aparecer)                               | F5          |
| Notícias (após fetch-rss) | `futebola:backfill-news-images` (preenche imagens via og:image)   | —           |

---

## Por que dava tela branca?

O app tentava carregar o front pelo servidor do Vite (arquivo `hot`). Quando o container do Node não está rodando, esse endereço não existe e o JS não carrega → tela branca.

Agora o app **sempre** usa o build em `public/build/` quando ele existir. Por isso, depois de qualquer mudança em Vue/JS/CSS você precisa **gerar de novo o build** com o comando acima e recarregar com **Ctrl+Shift+R**.
