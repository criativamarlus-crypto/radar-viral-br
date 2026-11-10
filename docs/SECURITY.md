# Revisão e limites de segurança

O painel é privado. O código inicial tinha API pública e não tinha login, uploads ou isolamento de sessão. Esta versão acrescenta autenticação antes do painel e da API, sessões com cookies seguros, CSRF nas ações de escrita, limite de tentativas e armazenamento privado. A instalação fica indisponível até existir configuração válida.

RSS é conteúdo não confiável: títulos e fontes são tratados como texto, URLs são validadas, XML não deve carregar entidades externas e a transferência deve ter limites de tempo/tamanho. A aplicação consulta somente os feeds pré-configurados; não oferece um proxy arbitrário de URLs. Imagens de feeds podem ser carregadas pelo navegador diretamente do publicador: isso revela ao servidor de imagem o endereço de rede e o agente do navegador. Sem imagem fornecida, mostra um estado vazio; não inventa fotografias ou direitos de uso.

Uploads são administrativos e passam por CSRF, limites de tamanho e verificação de formato. São armazenados fora de `public/` e servidos por endpoint autenticado, com tipo de conteúdo fixo e sem execução. Não habilite uma pasta pública de PHP/uploads no vhost. Preserve a imagem autorizada do personagem; o arquivo original ainda precisa ser fornecido se não existir no armazenamento do servidor.

Credenciais de administração e token de leitura pertencem a `config.php`, ignorado pelo Git. O token da API/MCP concede leitura, não sessão administrativa nem upload. Chaves de deploy pertencem ao cofre de Secrets do GitHub; nunca ao código, ao token MCP ou a variáveis de JavaScript do navegador. Se uma chave real já tiver sido exposta no histórico público, revogue-a; apagá-la no commit seguinte não elimina a exposição.

O endpoint de health é público e mínimo para permitir checagem de disponibilidade. Não é um teste completo de penetração. Os testes automatizados devem verificar comportamento de autenticação, CSRF, parsing, geração conservadora e arquivos enviados, mas não certificam a infraestrutura da VPS.

Antes de habilitar produção, valide o isolamento do document root, permissões, SSL, PHP-FPM, acesso SSH com chave de host conhecida, proteção do Environment e os endpoints reais por HTTP. A VPS e a configuração remota permanecem **não verificadas até existir acesso SSH autorizado**. Não altere DNS nem realize a publicação enquanto esses requisitos estiverem pendentes.

Nenhuma automação de publicação em redes sociais faz parte deste projeto. O roteiro usa o título e o contexto fornecido; checagem jornalística e autorização de uso de imagem continuam necessárias antes da publicação editorial.
