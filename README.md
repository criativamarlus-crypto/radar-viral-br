# Radar Viral BR

Painel **privado**, em português brasileiro, que busca notícias do Brasil e do mundo, estima o potencial de cada uma para vídeos curtos de *react* e prepara headlines, roteiro, comentário, CTA e um prompt de vídeo com o seu personagem fixo. Produção: https://viral.netnewsltda.online

## O que faz

- **Coleta** a cada 30 min (cron e/ou ao abrir o painel) de ~48 fontes RSS/Atom + Google Trends Brasil: Google Notícias, G1, UOL, CNN Brasil, Metrópoles, Terra, GE, Folha, Estadão, BBC, DW, Guardian e outras. Fontes podem ser adicionadas, editadas, ativadas e desativadas em *Fontes monitoradas*. Fonte fora do ar vira erro registrado em *Histórico de coleta*; nada é inventado.
- **Ranking** 0–100 de potencial viral, *estimativa própria* (atualidade, presença em vários veículos, tendências, interesse, discussão, clareza, imagem, viabilidade), com o motivo de cada ponto. Agrupa a mesma história de vários veículos num card. Filtros: última hora, 3 h, 6 h, 24 h, mais recentes, maior potencial, categoria, busca.
- **Imagens**: capa declarada pela fonte, Open Graph da matéria, anexo manual, captura real por serviço opcional. Sempre com crédito e aviso de direitos; nunca fabrica prints. Prévia 9:16.
- **Roteiros**: botão *Gerar react com IA* (Anthropic, OpenAI ou API compatível; sem IA gera um rascunho conservador). Entrega 3 headlines, gancho, roteiro de 15–35 s, comentário (opinião separada de fato), CTA e prompt de vídeo com o personagem fixo. Alerta sobre números/citações que não estão na fonte.
- **Estúdio de React**: composição vertical 9:16 (notícia em cima, personagem embaixo), enquadramento, headline, expressão, salvar projeto, exportar PNG 1080×1920 e `.txt`.
- **Meu Personagem**: prompt mestre imutável + imagens de referência.
- **API REST e MCP** com tokens (leitura / preparar react) e **OAuth 2.1** para conectar o ChatGPT: [docs/API.md](docs/API.md) e [docs/CHATGPT.md](docs/CHATGPT.md).

## Rodar localmente

Requisitos: PHP 8.2+ com `curl`, `simplexml`, `fileinfo`, `mbstring`, `pdo_sqlite` e `sodium` ou `openssl`.

```bash
cp config.example.php config.php
# edite config.php: admin_user, admin_password_hash, storage_path, secure_cookies=false, site_url=http://localhost:8080
php -r 'echo password_hash("SUA-SENHA-LONGA", PASSWORD_DEFAULT), PHP_EOL;'   # cole o hash em config.php
php -S localhost:8080 -t public bin/dev-router.php   # o router roteia /.well-known/* (descoberta OAuth)
```

Abra http://localhost:8080 e entre. No primeiro acesso o painel faz a primeira coleta sozinho. No servidor use `php bin/setup.php`, que cria o `config.php` pedindo a senha no terminal. Para cadastrar a folha de referência do personagem (a imagem que vai junto com cada prompt de vídeo) sem abrir o painel: `php bin/import-character.php --character=clara --reference=foto.png --role=photo --primary=foto.png` (cada personagem aceita até 6 imagens de referência, folhas e fotos, e todas vão em cada prompt de vídeo) (`--character` escolhe o personagem: `beto` ou `clara`).

## Testes

```bash
php tests/run.php     # ~345 verificações; sobe um servidor PHP descartável; não usa rede nem credenciais reais
```

## Publicar no CloudPanel

Siga [docs/CLOUDPANEL.md](docs/CLOUDPANEL.md): PHP Site 8.2+, raiz web em `current/public`, usuário de deploy sem root, chave de host SSH conferida, secrets no GitHub Environment `production`, cron de 30 min. O workflow `Validate and deploy` roda em PRs (sem segredos) e só publica a partir da `main`, com a variável `PRODUCTION_DEPLOY_ENABLED=true` e aprovação do Environment.

## Documentação

[API e MCP](docs/API.md) · [ChatGPT](docs/CHATGPT.md) · [CloudPanel](docs/CLOUDPANEL.md) · [Segurança](docs/SECURITY.md) · [AGENTS.md](AGENTS.md)

## Limites honestos

O Radar lê manchetes e metadados; **não verifica fatos**. A nota não é audiência nem ranking oficial do Google Trends. O feed do Google Notícias é para uso pessoal e não comercial. Nenhum vídeo é gerado nem publicado automaticamente.
