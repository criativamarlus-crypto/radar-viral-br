# Conectar ao ChatGPT (MCP + OAuth)

## O que existe

- Servidor MCP remoto em `https://viral.netnewsltda.online/mcp.php` com as ferramentas `listar_noticias`, `buscar_noticias`, `consultar_tendencias`, `obter_noticia`, `obter_imagens`, `listar_reacts` e `preparar_react` ([API.md](API.md)).
- **Autorização OAuth 2.1**, que é o que os conectores do ChatGPT usam para dados privados (a documentação pública lista OAuth, “sem autenticação” e “misto”; não há campo para colar um token fixo):
  - descoberta: `/.well-known/oauth-protected-resource` (RFC 9728) e `/.well-known/oauth-authorization-server` (RFC 8414);
  - registro dinâmico de cliente (`/oauth-register.php`, RFC 7591) para clientes públicos;
  - autorização com **PKCE S256 obrigatório**, `redirect_uri` com correspondência exata, código de uso único válido por 5 min, `resource` (RFC 8707) e `iss` (RFC 9207) (`/oauth-authorize.php`);
  - tokens (`/oauth-token.php`): acesso de 1 h e **refresh com rotação** (30 dias); só hashes ficam no banco;
  - escopos `read` e `react` (preparar react, que usa a IA do servidor);
  - **a tela de consentimento é o login do painel**: nada é concedido sem você aprovar, e a senha nunca sai do site.
- Alternativa para telas que pedem *Client ID* e *Client secret* em vez de registrar sozinhas: crie um **cliente manual** em *Integrações > OAuth* (o segredo aparece uma única vez e só o hash é guardado).
- Tokens Bearer manuais continuam existindo para scripts e clientes MCP que aceitam cabeçalho.

## O que foi testado e o que não foi

Testado com HTTP real contra o servidor local (346 verificações automáticas, incluindo todo o fluxo): descoberta, registro, interstício, login com retorno, consentimento (aprovar, negar, só leitura), troca de código por token, uso no MCP, refresh com rotação, reutilização de código (revoga os tokens emitidos), PKCE errado, `redirect_uri` não registrado, cliente manual com segredo (corpo e HTTP Basic) e revogação pelo painel. A tela de consentimento também foi conferida no navegador.

**Não foi testado contra o ChatGPT de verdade**: isso exige o site publicado em HTTPS. Os detalhes do ChatGPT (URLs de retorno, escopos pedidos, se registra sozinho ou pede client id/secret) podem diferir do que a documentação indica; por isso há registro dinâmico **e** cliente manual.

## Passo a passo (depois de publicar)

1. Publique o site (CloudPanel) e confirme `/health.php`. Inclua no vhost o `rewrite` do `deploy/nginx-hardening.conf` (descoberta OAuth) e `fastcgi_param HTTP_AUTHORIZATION $http_authorization;`.
2. Confira: `curl https://viral.netnewsltda.online/.well-known/oauth-authorization-server` deve devolver JSON com `issuer` igual ao site.
3. No ChatGPT, ative o **modo desenvolvedor** (em planos Business/Enterprise o administrador do workspace precisa liberar; confirme o que seu plano permite) e crie o conector com a URL `https://viral.netnewsltda.online/mcp.php`, escolhendo **OAuth**.
4. O ChatGPT abre a página de autorização do Radar: entre, confira o aplicativo e o destino e clique em **Autorizar**. Desmarque `react` se quiser só leitura.
5. Se ele pedir *Client ID/Secret*: crie o cliente manual em *Integrações* (endereço de retorno que a própria tela do ChatGPT mostrar) e cole os valores.
6. Revogue a qualquer momento em *Integrações* (as conexões OAuth aparecem na lista de tokens).

Os requisitos do ChatGPT mudam; confirme na ajuda oficial da OpenAI.

## Exemplos de pedidos

- “Busque as dez notícias mais interessantes do Brasil hoje.” → `listar_noticias` (`periodo_horas: 24`, `limite: 10`)
- “Selecione uma notícia de futebol.” → `listar_noticias` (`categoria: "Futebol"`)
- “Prepare um react com nosso personagem.” → `preparar_react`
- “Me mostre a manchete e a imagem original.” → `obter_noticia` + `obter_imagens`

O ChatGPT só deve afirmar o que as ferramentas devolvem. A pontuação é estimativa do Radar, não audiência, e os fatos precisam ser conferidos na matéria original.

## Desenvolvimento local

O servidor embutido do PHP não roteia `/.well-known/*`; use o router de desenvolvimento:

```bash
php -S localhost:8080 -t public bin/dev-router.php
```

Em produção o Nginx faz isso pela regra `rewrite` do `deploy/nginx-hardening.conf`.
