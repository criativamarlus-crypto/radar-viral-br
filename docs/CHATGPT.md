# Conectar ao ChatGPT (MCP)

## O que existe

O servidor MCP remoto está em `https://viral.netnewsltda.online/mcp.php`, com as ferramentas `listar_noticias`, `buscar_noticias`, `consultar_tendencias`, `obter_noticia`, `obter_imagens`, `listar_reacts` e `preparar_react` (detalhes em [API.md](API.md)). A autenticação implementada é **token Bearer** criado em *Integrações*.

Foi testado localmente com chamadas JSON-RPC reais (inicialização, listagem, consultas, escopos, recusas). **Não foi testado contra o ChatGPT**, porque isso exige o site publicado em HTTPS e uma conta com acesso a conectores.

## O ponto que pode impedir a conexão direta

Segundo a documentação pública de conectores/apps do ChatGPT (modo desenvolvedor), os modos de autenticação de um servidor MCP remoto são **OAuth**, **sem autenticação** e **misto**; não há campo para colar um token Bearer estático. Isso significa que, para o ChatGPT acessar **dados privados**, o servidor precisará falar **OAuth 2.1** (descoberta de metadados, registro dinâmico de cliente, autorização com PKCE e troca de código por token). O Radar **ainda não implementa OAuth**.

Não use “sem autenticação”: isso deixaria suas notícias, roteiros e a IA do servidor abertos a qualquer pessoa com a URL.

### O que falta para conectar o ChatGPT de verdade

1. Implementar o fluxo OAuth 2.1 (próximo passo recomendado; usa o login do painel como tela de consentimento).
2. Publicar o site em HTTPS (CloudPanel) e confirmar `/health.php`.
3. No ChatGPT, ativar o modo desenvolvedor (em planos Business/Enterprise o administrador do workspace precisa liberar), criar o conector com a URL do MCP e autorizar.
4. Conferir se o seu plano permite ferramentas que gravam (`preparar_react` cria um roteiro salvo).

Os requisitos do ChatGPT mudam; confirme na ajuda oficial da OpenAI antes de começar.

## O que já funciona hoje com o token

- Clientes MCP que permitem enviar cabeçalho `Authorization` (muitos clientes de desenvolvimento e a ferramenta MCP remota da API da OpenAI, quando você informa o cabeçalho/autorização — confirme na documentação vigente).
- Qualquer script: veja os exemplos `curl` em [API.md](API.md).
- Você também pode gerar o react **dentro do próprio site** (botão “Gerar react com IA”), com a IA configurada no servidor, sem depender do ChatGPT.

## Exemplos de pedidos (quando a conexão estiver ativa)

- “Busque as dez notícias mais interessantes do Brasil hoje.” → `listar_noticias` (`periodo_horas: 24`, `limite: 10`)
- “Selecione uma notícia de futebol.” → `listar_noticias` (`categoria: "Futebol"`)
- “Prepare um react com nosso personagem.” → `preparar_react`
- “Me mostre a manchete e a imagem original.” → `obter_noticia` + `obter_imagens`

O ChatGPT só deve afirmar o que as ferramentas devolvem. A pontuação é estimativa do Radar, não audiência, e os fatos precisam ser conferidos na matéria original.
