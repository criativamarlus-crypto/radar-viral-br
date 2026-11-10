# Orientações para Codex — Radar Viral BR

## Projeto
Site privado de curadoria de notícias para vídeos curtos de react em português brasileiro, hospedado em CloudPanel/Nginx no domínio https://viral.netnewsltda.online. O usuário utiliza um personagem fixo: brasileiro negro, homem adulto robusto, óculos, corrente de ouro, relógio, sorriso marcante, linguagem espontânea e bem-humorada. O site deve localizar pautas em alta, oferecer manchete, fontes, imagem/capa quando permitida, proposta de headline fiel aos fatos e prompt/roteiro para gerar vídeo com o personagem.

## Estado e fonte do código
O código inicial foi enviado diretamente ao GitHub pelo ChatGPT e passou a ser a base de desenvolvimento deste repositório. Continue a partir dos arquivos existentes; não bloqueie o trabalho pedindo novamente o ZIP original. O pacote `Radar_Viral_BR_Codex_GitHub.zip` e a imagem original do personagem não fazem parte dessa base. Preserve o visual escuro/dourado, as categorias e a descrição do personagem; o administrador pode enviar sua imagem autorizada no painel. Nunca dizer que o site está publicado sem verificar implantação e resposta HTTP.

## Estrutura prevista do pacote PHP
- `public/`: raiz pública do site, `index.php`, `login.php`, `api.php`, `mcp.php`, JS, CSS, assets do personagem.
- `app/`: leitura de feeds RSS e bootstrap, **fora da raiz pública**.
- `bin/`: instalação/CLI e atualização por cron.
- `storage/`: cache privado, nunca expor via HTTP.
- `.github/workflows/deploy.yml`: testes e implantação via SSH/SCP no CloudPanel.
- `config.php`: credenciais criadas no servidor, nunca comitar.
- `public/uploads/`: arquivos enviados pelo administrador, controlar extensão, conteúdo e tamanho.

## Objetivos técnicos
1. Importar os arquivos existentes, verificar o código e corrigir erros; manter arquitetura PHP 8.2+ compatível com CloudPanel/Nginx.
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

## Próximo passo para o Codex
Continuar a revisão dos arquivos existentes, executar `php tests/run.php` e validar o primeiro deploy no CloudPanel somente após conferir a identidade SSH por canal confiável e a configuração do servidor. O workflow permanece bloqueado até configuração explícita. Não tratar a página genérica “Hello World” como publicação da aplicação. Não afirmar que a VPS foi validada quando ainda não houver acesso SSH funcional.
