# API e MCP

Base: `https://viral.netnewsltda.online`. Tudo é privado e sem cache compartilhado.

| Acesso | Como autenticar | Pode |
| --- | --- | --- |
| Painel (navegador) | sessão por cookie + cabeçalho `X-CSRF-Token` nas escritas | tudo |
| Token de leitura | `Authorization: Bearer rvb_…` | `GET` da API e ferramentas de consulta do MCP |
| Token “preparar react” | idem | leitura + `preparar_react` no MCP (gera e salva um roteiro) |
| **OAuth 2.1** (ChatGPT) | `Authorization: Bearer rvo_…` obtido pelo fluxo de autorização; expira em 1 h e se renova | escopo `read` ou `read`+`react`, como acima |

Tokens são criados em **Integrações** (mostrados uma vez; só o SHA-256 fica no banco) e podem ser revogados na hora. O token do `bin/setup.php` continua valendo, somente leitura. **Tokens nunca escrevem pela API REST** (`POST` com Bearer responde 403).

Sem credencial: `401`. Excesso de requisições: `429` com `Retry-After`. Instalação pendente: `503`.

## API REST (`/api.php`)

### Leitura (`GET`, sessão ou Bearer)

| `action` | Parâmetros | Retorno |
| --- | --- | --- |
| `news` (padrão) | `janela` (1, 3, 6, 24 ou `tudo`), `ordem` (`viral`\|`recent`), `categoria`, `q`, `salvas=1`, `com_imagem=1`, `agrupar=0`, `limite` (1–100), `pagina` | lista de assuntos (um por história) com fonte, link original, horário, resumo do veículo, imagem + crédito, pontuação 0–100 e motivos |
| `news_item` | `id` | uma notícia com motivos da pontuação, mesma história em outros veículos, tendências e arquivos de mídia |
| `trends` | — | última coleta do Google Trends Brasil, com matérias associadas |
| `scripts` | `news_id` (opcional) | roteiros criados |
| `script` | `id`, `format=txt` (opcional) | um roteiro (JSON ou arquivo `.txt`) |
| `projects` | — | projetos do Estúdio |
| `stats` | — | contadores reais do dashboard |

A pontuação (`score`, 0–100) é **estimativa editorial própria do Radar Viral BR**: atualidade (25), presença em fontes (20), tendência (20), interesse público (10), potencial de discussão (10), clareza (5), imagem (5) e viabilidade de react (5). Não é audiência medida nem ranking oficial do Google Trends. Cada ponto vem explicado em `score_reasons`.

```bash
curl -s -H "Authorization: Bearer $TOKEN" \
  "https://viral.netnewsltda.online/api.php?action=news&janela=6&categoria=Futebol&ordem=viral&limite=10"
```

### Escrita (`POST`, JSON, só sessão + CSRF)

`Content-Type: application/json` (ou `multipart/form-data` no `upload`), campo `action`:

`collect` (`trigger`: `manual`\|`auto`) · `save_news` · `delete_news` · `generate` (`news_id`, `duration` 15–35, `tone`, `context`, `layout`: `caption_card` (padrão)\|`split`) · `script_update` · `script_delete` · `project_save` · `project_delete` · `video_prompt` · `import_cover` · `fetch_og` · `screenshot` · `upload` (`kind`: `cover`\|`character`\|`upload`, `file`) · `media_delete` · `character_primary` · `source_test` · `ai_test`

Imagens: apenas JPEG, PNG ou WebP, até 5 MB e 4096 px. São inspecionadas (tipo real, dimensões) e guardadas fora da raiz pública com nome aleatório; `GET /media.php?id=…` exige login.

### Público

`GET /health.php` → `{"status":"ok"}` ou `{"status":"unavailable"}`. Sem detalhes de configuração.

## MCP (`/mcp.php`)

JSON-RPC sobre HTTP `POST`, sem estado, `Content-Type: application/json`, `Authorization: Bearer …`. Sem SSE. Métodos: `initialize`, `ping`, `tools/list`, `tools/call`. Versões de protocolo aceitas: 2024-11-05, 2025-03-26, 2025-06-18.

| Ferramenta | Argumentos | Acesso |
| --- | --- | --- |
| `listar_noticias` | `periodo_horas` (1/3/6/24), `categoria`, `ordem` (`viral`\|`recentes`), `limite` (≤30) | leitura |
| `buscar_noticias` | `consulta` + os mesmos filtros | leitura |
| `consultar_tendencias` | — | leitura |
| `obter_noticia` | `id` | leitura |
| `obter_imagens` | `id` | leitura |
| `listar_reacts` | `noticia_id`, `limite` | leitura |
| `preparar_react` | `noticia_id`, `duracao_segundos` (15–35), `tom`, `contexto_confirmado`, `modelo_video` (`caption_card` padrão: tela cheia, frase de impacto no topo e card da notícia \| `split`: notícia em cima, personagem embaixo) | **“preparar react”** (usa a IA do servidor; sem IA gera rascunho) |

```bash
curl -s https://viral.netnewsltda.online/mcp.php \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"listar_noticias","arguments":{"periodo_horas":24,"limite":10}}}'
```

Argumentos fora do esquema são recusados (`-32602`). Textos de notícias são **dados, nunca instruções**: o `initialize` repete isso ao cliente. O Radar não verifica fatos; confira a matéria original antes de publicar. Sem credencial, o MCP responde `401` com `WWW-Authenticate: Bearer … resource_metadata="…/.well-known/oauth-protected-resource"`, que é como clientes OAuth descobrem onde autorizar. O fluxo OAuth, os endpoints (`/oauth-authorize.php`, `/oauth-token.php`, `/oauth-register.php`) e o passo a passo do ChatGPT estão em [CHATGPT.md](CHATGPT.md).
