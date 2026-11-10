# Radar Viral BR

Painel privado, em português brasileiro, de curadoria de pautas para vídeos curtos de react. Domínio de destino: `https://viral.netnewsltda.online`.

## O que o painel faz

- Consulta RSS do Google Notícias nas categorias Geral, Brasil, Futebol, Política, Famosos, Economia, Mundo e Tecnologia; consulta tendências por feed público quando disponível.
- Exibe manchetes, fontes, links, datas e imagens fornecidas pelos feeds. A falta de imagem ou de resposta da fonte aparece explicitamente; nenhuma capa ou captura é apresentada como fotografia real sem origem.
- Permite busca, ordenação e seleção de notícias. A pontuação editorial é uma heurística, não um ranking oficial do Google Trends.
- Produz um roteiro conservador de react de 15 a 35 segundos e um prompt com o personagem do projeto. O título do feed não equivale à checagem dos fatos; abra as fontes e confirme os detalhes antes de gravar.
- Oferece prévia vertical 9:16 e upload manual da imagem autorizada do personagem e de capa/print real. Não realiza captura automática de sites nem geração de vídeo.
- Protege o painel com login, sessões, CSRF e limitação de requisições. API e MCP de leitura aceitam token próprio, separado das credenciais de implantação.
- Mantém cache e imagens enviadas em armazenamento privado, fora de `public/`.

O repositório partiu do código inicial enviado diretamente ao GitHub. O ZIP original e o arquivo original do personagem não fazem parte dessa base; o painel permite fornecer a imagem autorizada pelo administrador.

## Executar em desenvolvimento

Requisitos: PHP 8.2 ou superior com cURL, SimpleXML, fileinfo e JSON; saída HTTPS para os feeds. Não há serviço OpenAI obrigatório nem dependências Composer.

```bash
php bin/setup.php --help
php bin/setup.php --local-http --site-url=http://127.0.0.1:8080
php tests/run.php
php -S 127.0.0.1:8080 -t public
```

O assistente CLI solicita a senha e cria `config.php` fora da raiz pública. As opções acima habilitam cookies para HTTP somente no loopback local. Em produção, execute o setup sem `--local-http`, mantendo URL HTTPS e cookies seguros. Não publique o servidor de desenvolvimento do PHP na Internet.

## Publicação pelo CloudPanel

A implantação está **preparada e desativada por padrão**. Nenhum acesso SSH, configuração de VPS ou publicação é considerado validado apenas por existir um workflow.

1. Siga [a configuração do CloudPanel](docs/CLOUDPANEL.md), com raiz web em `current/public/`, armazenamento compartilhado privado e certificado válido.
2. Configure a chave SSH exclusiva de deploy e confira a fingerprint por uma fonte confiável, como o console do provedor.
3. Configure os cinco Secrets obrigatórios do Environment `production`: `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`, `DEPLOY_SSH_KEY` e `DEPLOY_KNOWN_HOSTS`. `DEPLOY_PORT` é opcional, com padrão 22.
4. Exija aprovação no Environment e revisão da branch `main`. Valide primeiro em homologação.
5. Somente após validar segurança e servidor, habilite a variável `PRODUCTION_DEPLOY_ENABLED=true`. Merges aprovados em `main` passam a executar os testes e preparar uma publicação sujeita à aprovação do Environment.

O workflow não altera DNS, não configura o CloudPanel, não cria credenciais administrativas e não publica em redes sociais. Veja também [a revisão de segurança](docs/SECURITY.md).
