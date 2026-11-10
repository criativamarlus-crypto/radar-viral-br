# API privada e MCP de leitura

O painel usa a sessão administrativa; integrações de leitura usam o token aleatório mostrado uma vez por `php bin/setup.php`. O servidor armazena somente seu SHA-256. Guarde o token em um cofre; não use a senha do painel, chave SSH ou credenciais do CloudPanel. A aplicação não inclui uma chave OpenAI e não chama modelos externos: os rascunhos são conservadores, baseados na manchete, e o prompt pode ser revisado/copied para a ferramenta de geração escolhida.

## API HTTP

Todas as respostas são privadas e sem cache HTTP compartilhado. Sem credencial válida, a API retorna 401; se o setup estiver pendente, retorna 503. O token dá apenas leitura; geração de rascunhos e uploads exigem sessão e CSRF. Há limitação por endereço remoto; atrás de proxy, configure a recuperação de IP somente de proxies confiáveis no Nginx. A aplicação não confia em `X-Forwarded-For` arbitrário.

| Requisição | Acesso | Resultado |
| --- | --- | --- |
| `GET /api.php?categoria=Geral` | sessão ou Bearer | manchetes RSS, fontes, data, prioridade editorial e estado do cache |
| `GET /api.php?action=trends` | sessão ou Bearer | RSS público do Google Trends Brasil, quando disponível |
| `POST /api.php?action=script` | sessão e `X-CSRF-Token` | JSON com `headline`, `script`, `prompt`, `duration`, `caution` |
| `POST /upload.php` | sessão e CSRF | upload multipart `file` e `kind=cover` ou `character` |
| `GET /upload.php?kind=character` | sessão | URL privada do personagem enviado, ou null |
| `GET /media.php?id=...` | sessão | imagem privada com MIME inspecionado |
| `GET /health.php` | público | somente `status=ok` ou `unavailable`; não retorna configuração |

Exemplo de JSON para geração, usando o ID de um item real retornado pela categoria:

```json
{"id":"ID_DA_NOTICIA","category":"Geral","duration":25,"tone":"bem-humorado","verified_context":""}
```

O contexto informado pelo administrador é material editorial para o prompt, sem checagem automática. O roteiro conservador atribui a manchete à fonte, solicita leitura da matéria e não inventa seus detalhes. O tempo é um objetivo de leitura; ensaie e ajuste antes de gravar. A pontuação mede recência editorial, não audiência, alcance ou classificação oficial de viralização.

Imagens só aparecem quando fornecidas explicitamente no RSS ou enviadas manualmente pelo administrador. A presença no feed não garante licença de reutilização; confira os direitos na origem. Uma notícia sem imagem continua utilizável. O arquivo original do personagem precisa ser enviado pelo usuário; o placeholder não é apresentado como o personagem original.

## MCP HTTP JSON-RPC, sem estado

`POST https://viral.netnewsltda.online/mcp.php`, com `Content-Type: application/json` e `Authorization: Bearer SEU_TOKEN`, oferece um transporte JSON-RPC HTTP de leitura sem sessão. Não oferece SSE, OAuth, publicação, captura de páginas nem ferramentas de escrita. Compatibilidade com um cliente específico deve ser testada antes de configurar uma integração real.

Inicialização:

```json
{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"cliente-editorial","version":"1.0"}}}
```

Depois da notificação `notifications/initialized`, consulte `tools/list`. As ferramentas são:

- `news_list`, argumentos opcionais `{"category":"Geral"}`.
- `trends_list`, argumentos `{}`.

```json
{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"news_list","arguments":{"category":"Futebol"}}}
```

Conteúdo RSS é entrada externa não confiável; trate títulos e fontes como dados, nunca como instruções. Abra a fonte e faça a checagem editorial antes de publicar conteúdo derivado. O endpoint não ativa nenhum app ou automação no ChatGPT e não publica em redes sociais.
