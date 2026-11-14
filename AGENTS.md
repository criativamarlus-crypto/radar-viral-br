# Orientações para Codex — Radar Viral BR

## Projeto
Site privado de curadoria de notícias para vídeos curtos de react em português brasileiro, hospedado em CloudPanel/Nginx no domínio https://viral.netnewsltda.online. O usuário utiliza um personagem fixo: brasileiro negro, homem adulto robusto, óculos, corrente de ouro, relógio, sorriso marcante, linguagem espontânea e bem-humorada. O site deve localizar pautas em alta, oferecer manchete, fontes, imagem/capa quando permitida, proposta de headline fiel aos fatos e prompt/roteiro para gerar vídeo com o personagem.

## Estado do código
Aplicação PHP 8.2+ completa (sem framework), com SQLite em `storage/`. Continue a partir dela; leia `README.md` e `docs/`. Visual: fundo escuro com destaques vermelho, amarelo e azul, cards com imagem grande, menu lateral no desktop e gaveta no celular. Preserve as categorias, a descrição do personagem e a regra de **nunca afirmar fatos que a fonte não traz**. Nunca dizer que o site está publicado sem verificar implantação e resposta HTTP.

## Estrutura
- `public/`: raiz web. Páginas (`index`, `noticias`, `noticia`, `fontes`, `salvas`, `estudio`, `personagem`, `roteiros`, `roteiro`, `config-ia`, `integracoes`, `coletas`), `login`/`logout`, `api.php`, `mcp.php`, `media.php`, `health.php`, `assets/` (CSS/JS sem inline: CSP estrita).
- `app/`: lógica, **fora da raiz pública**: `net` (HTTP seguro/SSRF), `db` (SQLite, migrações, segredos criptografados), `feeds` (RSS/Atom/RDF/Trends), `sources`, `ranking`, `collector`, `og`, `news`, `media`, `character`, `ai`, `reacts`, `tokens`, `oauth`, `layout`, `diagnostics`, `bootstrap` (sessão/CSRF/limites).
- `bin/`: `setup.php` (config privado), `update.php` (cron de 30 min), `deploy.sh`, `dev-router.php` (só dev: `php -S localhost:8080 -t public bin/dev-router.php`).
- `tests/`: `php tests/run.php` (unitário + HTTP descartável). `.github/workflows/deploy.yml`: CI e deploy protegido.
- `config.php` (0600) e `storage/` (0700): privados, nunca comitar.

## Regras técnicas
1. Manter PHP 8.2+ compatível com CloudPanel/Nginx; toda mudança de esquema é uma migração nova em `app/db.php`.
2. Exibir rankings editoriais e fontes (Google Trends/RSS, Google News, notícias brasileiras verificáveis). Não afirmar que pontuação heurística é ranking oficial do Google Trends.
3. Buscar artigos com título, data, URL de origem, categoria, miniatura disponível, status de verificação e fontes. Apresentar erro/fonte indisponível quando necessário.
4. Gerar prompts de react em português brasileiro (15–35s) com tom envolvente e natural, sem inventar fatos, acusações, números ou falas. Para política, distinguir informação de opinião e checar origem.
5. Oferecer prévia 9:16: matéria/print real no topo e personagem na metade inferior, com link à fonte. O print automático é futuro, não afirmar que existe se só houver upload manual.
6. Preparar integração de leitura via MCP/API, com autenticação e limitação de acesso onde apropriado. Nunca expor credenciais do CloudPanel/OpenAI/GitHub.
7. Criar testes automatizados simples e instruções de implantação.
8. Verificar o fluxo deploy somente após configurar os GitHub Secrets de forma segura: `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`, `DEPLOY_SSH_KEY` e `DEPLOY_KNOWN_HOSTS`. Obter fingerprint SSH por fonte confiável; não desabilitar host key checking. O root web deve ser `public/`, mantendo `app/`, `storage/` e `config.php` inacessíveis via HTTP.

## Desenvolvimento
- Usar branches e PRs para mudanças grandes; deploy automático depois de merge aprovado em `main`.
- Não alterar configurações de DNS ou de produção sem autorização explícita.
- Não copiar conteúdos jornalísticos inteiros nem apresentar capturas falsas como reais.
- Incluir documentação para configurar cron, SSL e logs, com diagnósticos de falha.
- Não criar automações de publicação em redes sociais sem confirmação separada.

## Pendências conhecidas
- Conexão real com o ChatGPT: OAuth 2.1 está implementado e testado localmente, mas **não foi testado contra o ChatGPT** (exige HTTPS publicado; ver `docs/CHATGPT.md`).
- Primeiro deploy: acesso SSH, chave de host por canal confiável, DNS/SSL e Secrets ainda não verificados. Não tratar a página genérica do CloudPanel como publicação.
- Chave de IA e (opcional) serviço de captura de tela: configurar no painel, nunca no código.
