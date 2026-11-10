# Segurança: o que está implementado e seus limites

O painel é privado. Nada além de `/login.php` e `/health.php` (que só devolve `ok`/`unavailable`) responde sem autenticação.

## Acesso
- Login com senha em hash (`password_hash`), sessão em cookie `HttpOnly` + `SameSite=Strict` (+ `Secure` em produção), expiração por inatividade (1 h) e absoluta (8 h), rotação do ID ao entrar, revogação de todas as sessões ao trocar a senha.
- CSRF em todo formulário e em toda ação de escrita. Tokens de API **nunca** escrevem.
- Limite de tentativas de login (5 por 15 min) e limites por ação (coleta, geração de roteiro, upload, MCP…).
- Tokens de API: aleatórios, só o SHA-256 é guardado, escopos (`read`, `react`), revogação imediata.
- CSP estrita (`script-src 'self'`, `style-src 'self'`): o front-end não tem JavaScript nem CSS inline; saídas HTML são escapadas.

## Dados e segredos
- Banco SQLite, sessões, imagens e chave mestra ficam em `storage/` (0700), **fora** da raiz pública. `config.php` (0600) também.
- Chaves de IA e do serviço de captura são criptografadas no banco (libsodium `secretbox`, ou AES-256-GCM como alternativa) com chave mestra própria (`storage/app.key` ou `app_key`). Nunca vão ao navegador e só os 4 últimos caracteres são exibidos. Alternativa: variáveis de ambiente do PHP-FPM.
- Erros de provedores de IA têm chaves e tokens mascarados antes de aparecer.
- Segredos de deploy ficam só nos GitHub Secrets. O repositório não contém chaves (há teste automático que procura).

## Entrada não confiável
- **Feeds**: só HTTPS, tamanho e tempo limitados; DTD/entidades XML rejeitados (XXE/expansão); texto tratado como texto; URLs validadas.
- **SSRF**: todo acesso de saída (feeds, imagens, páginas para Open Graph, IA, captura) exige HTTPS, porta 443 e IP **público**; o IP validado é fixado na conexão (sem *DNS rebinding*) e cada redirecionamento é revalidado; POST nunca é reenviado em redirecionamento.
- **Uploads**: só JPEG/PNG/WebP, até 5 MB / 4096 px / 16 MP, tipo real verificado (não pelo nome), nome aleatório, servidos só por `media.php` autenticado com tipo fixo. SVG, HTML e PHP são recusados.
- **IA**: o texto das notícias vai como dados citados, nunca como instruções; a saída é validada, limpa de HTML, e números/citações que não existem na fonte geram alerta. O prompt de vídeo é montado pelo sistema, não pelo modelo.

## Fidelidade editorial
O Radar não verifica fatos e não afirma que verifica. A pontuação é estimativa própria. Imagens vêm da fonte (com crédito e aviso de direitos), de upload seu, ou de captura real por serviço configurado por você; **nunca são fabricadas**. Capas “emprestadas” de outra matéria do mesmo assunto são rotuladas como tal.

## Fontes e termos
Usa feeds RSS/Atom públicos e o RSS do Google Trends. O feed do **Google Notícias** informa que é para leitura pessoal e não comercial: mantenha o painel privado ou desative essas fontes. Respeite os termos de cada veículo; confira direitos antes de reutilizar texto ou imagem.

## O que NÃO está coberto
Testes automáticos não certificam a infraestrutura: valide DNS, SSL, permissões, PHP-FPM e o acesso SSH no servidor real. Não há automação de postagem em redes sociais. OAuth para o ChatGPT ainda não existe ([CHATGPT.md](CHATGPT.md)).
