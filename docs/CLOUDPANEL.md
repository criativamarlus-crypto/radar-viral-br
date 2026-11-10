# Implantação no CloudPanel

Estado: workflow preparado, produção desativada por padrão. Os testes locais e o CI não comprovam que a VPS, a identidade SSH, o DNS ou o certificado estão configurados. A configuração do servidor e o primeiro deploy exigem verificação com acesso ao servidor.

## 1. Servidor e layout

Crie/revise um PHP Site no CloudPanel para `viral.netnewsltda.online`, com PHP 8.2+ e as extensões **cURL, SimpleXML, fileinfo, mbstring, pdo_sqlite** e **sodium ou openssl** (o banco é SQLite; não é preciso criar banco MySQL). Confirme a versão e as extensões tanto no CLI quanto no pool PHP-FPM: `php -m | grep -Ei 'curl|simplexml|fileinfo|mbstring|pdo_sqlite|sodium|openssl'`. O painel (Integrações > Diagnóstico) e o `/health.php` acusam o que faltar. É preciso ter `bash`, `tar`, `flock`, `curl`, SSH e SCP no servidor.

Use o usuário do site ou outro usuário exclusivo de deploy com acesso somente aos arquivos desse site. Não use root no workflow. A pasta de deploy deve ser absoluta, sem espaços e sem barra final, por exemplo `/home/USUARIO/apps/radar-viral-br`. O script recusa caminhos suspeitos e não cria nem substitui a configuração do CloudPanel.

O usuário SSH e o usuário PHP-FPM precisam ter permissões compatíveis. No layout fornecido, `config.php` tem modo 0600 e `storage/` modo 0700: use como `DEPLOY_USER` o usuário do site, que também executa o pool PHP-FPM. Um usuário SSH como `chatgpt`, diferente do dono do site, não ganha acesso aos arquivos privados apenas por conseguir conectar à VPS. Não execute este deploy com ele antes de confirmar a identidade e a propriedade dos arquivos. Prefira instalar a chave no usuário do site; se a infraestrutura exigir usuários distintos, um administrador deverá projetar e revisar a delegação restrita antes de adaptar o fluxo. Não conceda sudo irrestrito nem use `chmod 777`.

```text
radar-viral-br/
  shared/
    config.php          # 0600; credenciais privadas
    storage/            # 0700; cache, imagens, limites de acesso
  releases/
    SHA-RUN-ATTEMPT/
      app/
      bin/
      public/
      config.php -> ../../shared/config.php
      storage -> ../../shared/storage
  current -> releases/SHA-RUN-ATTEMPT
  incoming/             # 0700; pacote temporário removido após deploy
```

A raiz web no CloudPanel é **`/home/USUARIO/apps/radar-viral-br/current/public`**. Nunca aponte o site para `shared/`, `releases/` ou a raiz do repositório.

Para o primeiro setup, obtenha uma cópia revisada do código no servidor, em uma pasta privada temporária, e execute como usuário do site (substitua todos os caminhos de exemplo):

```bash
umask 077
mkdir -p /home/USUARIO/apps/radar-viral-br/shared/storage
chmod 700 /home/USUARIO/apps/radar-viral-br/shared /home/USUARIO/apps/radar-viral-br/shared/storage
php /CAMINHO/PRIVADO/DO/CODIGO/bin/setup.php --config=/home/USUARIO/apps/radar-viral-br/shared/config.php
```

Use `--help` para consultar o assistente. A senha é processada no servidor e o arquivo guarda somente seu hash. Guarde o token de leitura em um cofre de segredos; ele não é uma chave SSH nem uma chave OpenAI. Configure `storage_path` com o caminho absoluto de `shared/storage`, `site_url` com `https://viral.netnewsltda.online` e `secure_cookies` como `true`. O usuário PHP-FPM deve ser o mesmo usuário do site, ou ter permissões restritas equivalentes; não resolva diferenças de usuário com `chmod 777`.

O diretório `current` ainda não existe no primeiro setup. Configure o document root no painel e prepare certificado/rota HTTPS antes de habilitar o deploy. O primeiro health check recusa ativar uma release enquanto essa configuração não funcionar. Não remova um site existente para acomodar o layout; valide a mudança em homologação primeiro.

## 2. SSH e chave de host

Gere uma chave exclusiva para deploy em um ambiente confiável e instale sua chave pública no `authorized_keys` do usuário do site. Restrinja encaminhamentos de agente, porta e X11; o usuário não deve ter sudo. Não envie chave privada, senha, `config.php` ou token no chat ou em commits.

Obtenha a chave pública do servidor e sua fingerprint a partir do console do provedor/VPS ou do administrador, por um canal já confiável. No console:

```bash
ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub
cat /etc/ssh/ssh_host_ed25519_key.pub
```

Construa a linha de `known_hosts` para o mesmo hostname usado em `DEPLOY_HOST`, por exemplo `host.exemplo ssh-ed25519 CHAVE_PUBLICA_CONFERIDA`. Confirme que a fingerprint corresponde. `ssh-keyscan` sozinho não comprova identidade. O deploy usa `StrictHostKeyChecking=yes` e recusa uma chave desconhecida ou alterada. `DEPLOY_PORT` é opcional e usa 22 por padrão. Para outra porta, configure um número entre 1 e 65535 e grave o host no formato `[host.exemplo]:2222 ssh-ed25519 CHAVE_PUBLICA_CONFERIDA` em `DEPLOY_KNOWN_HOSTS`.

Teste manualmente o acesso com chave e a chave de host conferida. Confira que o usuário alcança somente o site e consegue executar PHP e gravar `shared/storage`. Não ative o deploy para descobrir credenciais por tentativa e erro.

## 3. Nginx, SSL e isolamento

Instale um certificado válido no CloudPanel; confirme que o DNS aponta para o servidor correto e que HTTP redireciona para HTTPS no host canônico. Esta alteração de DNS/configuração de produção requer autorização do proprietário e não é feita pelo workflow.

Revise [o snippet Nginx](../deploy/nginx-hardening.conf) no vhost existente. Preserve a configuração PHP-FPM gerenciada pelo painel, adicione as negativas de acesso e mantenha a raiz em `current/public`. Use `nginx -t` antes de recarregar Nginx. Faça backup do vhost existente. No PHP, use `display_errors=Off`, `log_errors=On`, `expose_php=Off`, `upload_max_filesize=5M`, `post_max_size=6M`, `memory_limit=256M` e `max_execution_time=120` (a coleta de ~50 fontes leva ~10 s e a geração com IA até ~60 s). Se o Nginx devolver 504 nessas ações, aumente `fastcgi_read_timeout` do handler PHP para 120s.

Dentro do handler PHP-FPM já existente, confirme também `fastcgi_param HTTP_AUTHORIZATION $http_authorization;`, para que os endpoints API/MCP recebam o Bearer token (e o OAuth receba o `Authorization: Basic` de clientes com segredo). O snippet traz um `rewrite` para `/.well-known/oauth-*`: mantenha-o no `server {}`, senão a descoberta OAuth do ChatGPT dá 404 (confira com `curl -s https://viral.netnewsltda.online/.well-known/oauth-authorization-server`). Não crie outro handler PHP concorrente. Confira a autenticação com token válido e a rejeição de token inválido antes de liberar a integração.

Depois do setup, verifique por HTTP que `/config.php`, `/app/bootstrap.php`, `/storage/`, `/.git/config` e arquivos em `/uploads/` não são acessíveis (403 ou 404; nunca conteúdo privado). Verifique também que URLs PHP inexistentes retornam 404. `/health.php` deve retornar 200 com `{"status":"ok"}`; ele não retorna credenciais nem detalhes da infraestrutura. O painel deve exigir login e a API sem credenciais deve retornar 401.

## 4. GitHub Actions

Em `Settings → Environments`, crie **production** com revisores obrigatórios, bloqueie autoaprovação e limite deployments à branch `main`. Se o plano do GitHub não disponibilizar essas proteções, mantenha o deploy desativado até definir um controle equivalente. Proteja `main` para exigir revisão de PR e os dois testes PHP antes do merge.

Adicione no Environment `production`:

| Secret | Conteúdo |
| --- | --- |
| `DEPLOY_HOST` | Hostname/IP confiável da VPS, sem protocolo ou porta |
| `DEPLOY_USER` | Usuário exclusivo não root |
| `DEPLOY_PATH` | Diretório absoluto de deploy, sem espaços ou barra final |
| `DEPLOY_SSH_KEY` | Chave privada exclusiva de deploy |
| `DEPLOY_KNOWN_HOSTS` | Linha(s) de chave de host conferida(s) pelo canal confiável |
| `DEPLOY_PORT` (opcional) | Porta SSH de 1 a 65535; padrão 22. Não deve constar em DEPLOY_HOST |

Mantenha a variável **de repositório** `PRODUCTION_DEPLOY_ENABLED` ausente ou `false` durante a revisão. Ela é consultada antes de o job acessar o Environment. Somente depois de validar o servidor, a identidade SSH, segurança, homologação e aprovação do proprietário, defina-a como `true`.

Em PR, o workflow executa somente lint e testes sem Secrets. Depois de um merge aprovado em `main`, os dois jobs PHP devem passar antes de o job de produção aguardar aprovação no Environment. A execução manual também exige `main`, variável habilitada e proteção de produção. Não existe deploy a partir de código de PR.

O pacote contém somente `app/`, `public/`, `bin/` e `config.example.php`. O deploy não substitui `shared/config.php` ou `shared/storage`. Faz verificação SSH estrita, valida requisitos do PHP, publica uma release nova e troca `current` de forma atômica. Verifica `/health.php` por HTTPS no Nginx local (com o nome correto do certificado) e pelo endereço público. Em falha, restaura o symlink anterior, ou remove o novo `current` se não havia release anterior. Isso não restaura configurações externas modificadas manualmente.

## 5. Cron, banco e logs

Cadastre no CloudPanel um cron **a cada 30 minutos**, como o mesmo usuário do site:

```cron
*/30 * * * * /usr/bin/flock -n /home/USUARIO/apps/radar-viral-br/.feeds.lock /usr/bin/php /home/USUARIO/apps/radar-viral-br/current/bin/update.php >> /home/USUARIO/apps/radar-viral-br/shared/storage/cron.log 2>&1
```

Confirme os caminhos reais de `php` e `flock`. O script coleta todas as fontes ativas em paralelo, grava o histórico (visível em *Histórico de coleta*), reclassifica e recalcula o ranking, busca imagens Open Graph das melhores matérias sem capa, limpa sessões/limites expirados e remove matérias antigas não salvas (14 dias). Saída `0` = coletou (mesmo com fontes falhando; os erros aparecem no log e no painel); `1` = todas as fontes falharam ou a aplicação não iniciou. **Sem o cron o painel ainda se atualiza sozinho**: ao ser aberto com dados de mais de 30 minutos, dispara a coleta (manual ou automática; nunca duas ao mesmo tempo, há trava).

O banco é `shared/storage/radar.sqlite` (modo WAL: aparecem também `-wal` e `-shm`). **Faça backup** de `shared/storage/radar.sqlite` e de `shared/storage/app.key` (sem a chave mestra, as chaves de IA salvas no banco não podem ser lidas e precisarão ser digitadas de novo). Backup consistente com o site no ar:

```bash
sqlite3 /home/USUARIO/apps/radar-viral-br/shared/storage/radar.sqlite ".backup '/home/USUARIO/backups/radar-$(date +%F).sqlite'"
```

Configure rotação para `cron.log` e para os logs PHP/Nginx do site. Não coloque tokens ou dados de login nos logs. Para rollback manual, execute como usuário do site uma troca atômica para uma release anterior já validada, confira `/health.php` e o login. Nunca apague a release atual ou a anterior durante um deploy; o workflow mantém releases anteriores (programe a retenção depois).

## Diagnóstico

| Sintoma | Verificação |
| --- | --- |
| Job de deploy não aparece | Variável de repositório habilitada, branch main e testes concluídos |
| Job aguarda revisão | Aprovação pendente no Environment production |
| Host key verification failed | Host e fingerprint conferidos pelo console; não desative a verificação |
| Permission denied (publickey) | Usuário correto e chave pública instalada no authorized_keys |
| Health 503 | Config privado, hash de senha, extensões PHP e storage privado gravável |
| Health 404 ou página antiga | Raiz CloudPanel em current/public, host/SSL e cache/proxy corretos |
| Falha de cookie/login | HTTPS, secure_cookies e relógio do servidor; conferir PHP session storage |
| Feeds vazios/antigos | *Histórico de coleta* mostra o erro de cada fonte; confirme saída HTTPS do servidor (porta 443) e o cron |
| Health 503 com PHP ok | extensão `pdo_sqlite`/`mbstring` ausente no PHP-FPM, ou `shared/storage` sem permissão 0700/gravável |
| IA não responde | *Configurações de IA* > Testar conexão; chave, nome do modelo e saldo do provedor |
| PHP funciona no CLI mas retorna 502 | Pool PHP-FPM, versão/extensões e permissões; logs do site no CloudPanel |

Uma resposta 200 em uma página genérica do domínio não prova que o Radar Viral BR está instalado. Valide o health JSON, login, carregamento de notícias, upload autorizado e negativas de acesso aos arquivos privados.
