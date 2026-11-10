# Radar Viral BR

Projeto do painel de notícias para vídeos curtos de react. Endereço planejado: https://viral.netnewsltda.online

## Situação
Este repositório contém uma **primeira versão funcional** do código publicada diretamente pelo ChatGPT para contornar um erro no download do ZIP da conversa. O projeto completo original foi criado em um ZIP separado; este repositório ainda precisa de revisão, autenticação de administrador, captura segura de imagens, endpoint MCP e publicação no CloudPanel.

## Funções disponíveis
- Consulta RSS do Google Notícias Brasil e Google Trends (feed público, quando acessível).
- Filtros de futebol, política, famosos, economia, mundo e tecnologia.
- Cards com manchetes, links das fontes e imagens quando o feed fornecer.
- Prompt de react em português brasileiro com gancho, resumo e fechamento, baseado no título e com instrução de checagem de fatos.
- API GET /api.php?categoria=Futebol, somente leitura.

## Para executar localmente
PHP 8.2+ com cURL e SimpleXML:
```bash
php -S localhost:8080 -t public
```
Abrir http://localhost:8080. Necessária conexão externa do servidor para consultar as notícias.

## Para Codex
Leia AGENTS.md. Revise a segurança e organize componentes de código antes de acrescentar recursos. Confira todas as informações antes de publicar conteúdo e não invente números ou fatos.

## Para CloudPanel
- PHP Site com PHP 8.2+ e extensões cURL e SimpleXML
- Domain: viral.netnewsltda.online
- Root Directory: pasta `public/` deste repositório (não apontar para raiz com arquivos privados)
- DNS A e HTTPS válidos
- Deploy via GitHub Actions: workflow manual `.github/workflows/deploy.yml` que só deve ser usado depois de preencher GitHub Secrets de SSH de maneira segura
- Nunca colocar credenciais no código. Nunca expor SSH, chaves ou dados administrativos neste repositório
- **Importante:** este MVP é público para leitura e não tem login administrativo. Exija autenticação antes de publicar funcionalidades de escrita, uploads ou dados privados.

## Próximas etapas
1. Tornar o repositório privado (ele foi criado público)
2. Revisão e melhorias pelo Codex
3. Configurar acesso SSH da hospedagem por chave de deploy e host key conferida
4. Testar em servidor de homologação antes da implantação em produção
5. Adicionar fluxo de integração MCP com autenticação apropriada
