# Assinafy para WPForms

*Português · [Read in English](README.en.md)*

Um plugin WordPress separado que solicita a assinatura de um PDF já existente na Biblioteca de Mídia depois que o WPForms aceita uma submissão válida e não classificada como spam. A versão 0.1.0 suporta um signatário mapeado a partir de um campo Name ou Single Line Text e de um campo Email. O core do Assinafy cuida das credenciais da conta, do transporte da API, do status do documento, dos links de assinatura, dos downloads e das ferramentas de privacidade do WordPress.

Exige WordPress 6.8+, PHP 8.2+, Assinafy 1.0.0+ e WPForms Lite ou Pro 1.9.8.1+. Os testes de integração com host real usam o **WPForms Lite 2.0.1.1** oficial. O comportamento do ID de entry no Pro é testado apenas pelo contrato documentado do callback; uma instalação licenciada do Pro e seus recursos pagos não foram testados. O cabeçalho de dependência do plugin exige o Assinafy; o WPForms é verificado em tempo de execução, de modo que instalar o Pro não obriga a instalar também o Lite.

## Configuração

1. Instale e configure o Assinafy e depois instale o WPForms Lite ou Pro.
2. Instale este addon como `assinafy-wpforms` e ative-o.
3. Envie o PDF para a Biblioteca de Mídia.
4. Edite um formulário do WPForms e abra **Settings → Assinafy**. Habilite as solicitações, escolha o PDF, mapeie os campos de nome/e-mail do signatário e, opcionalmente, adicione uma mensagem de convite em texto puro. Salve o formulário. PDF ou mapeamentos de campo inválidos desativam o fluxo ao salvar.
5. Explique a solicitação de assinatura e obtenha no formulário qualquer consentimento necessário. Revise a divulgação sobre o serviço abaixo antes de habilitar o fluxo.

Alterar o fluxo exige tanto permissão para editar aquele formulário do WPForms quanto `assinafy_send`. A mensagem não expande smart tags do WPForms; deixá-la em branco usa o padrão do core. Dados de contato mapeados ausentes ou inválidos impedem a aceitação, mesmo quando esses campos nativos são opcionais.

## Aceitação, recibos e novas tentativas

A primeira solicitação de assinatura roda de forma síncrona em [`wpforms_process_complete`](https://wpforms.com/developers/wpforms_process_complete/), depois da validação, das verificações de spam e do processamento de notificações do WPForms. Uma falha no e-mail de notificação **não** cancela uma submissão que foi aceita. Este não é um hook de sucesso de pagamento: ele não espera a liquidação do pagamento. Ele não gera PDFs, não implementa fluxos condicionais e não adiciona recursos premium do WPForms.

O WPForms Lite retorna o ID de entry `0` e não armazena entries. O addon primeiro cria um recibo de documento do Assinafy privado para usuários autorizados. Sua origem imutável é `wpforms` com `form-FORM_ID:submission-MIRROR_ID`; um ID de entry positivo e real do host usa, em vez disso, `form-FORM_ID:entry-ENTRY_ID`. Esses são recibos de solicitação de assinatura, não um banco de entries substituto do WPForms. Callbacks repetidos dentro de uma mesma requisição PHP reaproveitam o recibo. Submissões HTTP aceitas separadas no Lite, inclusive um visitante reenviando os mesmos valores, são solicitações separadas. Um ID de entry positivo e armazenado é reservado de forma atômica e reaproveitado entre os callbacks.

As configurações do Assinafy no formulário mostram os vinte recibos mais recentes, os links para os registros no core e as falhas. **Retry** exige a permissão de edição do formulário original, a permissão de edição do registro no core, `assinafy_send` e um nonce válido. Ele usa o anexo PDF original, a mensagem e a projeção de signatários do core. Se o upload deu certo mas a atribuição falhou, o core relê o documento remoto e retoma sem um segundo upload. Um upload com desfecho incerto continua bloqueado; confira a conta Assinafy antes de iniciar uma nova submissão. Um anexo PDF original válido continua sendo obrigatório para novas tentativas. Recibos na lixeira e formulários de origem ausentes não podem ser tentados de novo pelo link administrativo.

O status e os links de assinatura ficam disponíveis no registro protegido do core. Recibos do Lite não exibem links de entry inventados. Um link real de entry do Pro exige o Pro e sua permissão de visualização de entries. Não há link público de assinatura na confirmação do formulário.

## Armazenamento e privacidade

O addon armazena apenas o ID do formulário, o ID real da entry (quando houver), o ID do anexo e a mensagem de convite como metadados de nova tentativa no espelho do core. Ele mantém essa configuração mesmo após o sucesso. O nome/e-mail mapeados do signatário são armazenados uma única vez na projeção de signatários do core, que é gerenciada pelas ferramentas de privacidade; a submissão completa do formulário não é copiada. O apagamento de dados pessoais do WordPress redige o contato no core e desativa a nova tentativa, inclusive em registros pendentes. Uma sincronização posterior com a API não consegue restaurar a identidade apagada. Uma operação externa já iniciada não pode ser cancelada por um apagamento local.

A desativação não exclui recibos, documentos nem evidências de assinatura. Remover este addon deixa os metadados dele nos espelhos do core; excluir um espelho remove a post metadata dele. O comportamento documentado de desinstalação/retenção de dados do core continua valendo. Os administradores do site seguem responsáveis pela retenção das mensagens e dos identificadores de origem, que podem conter informações pessoais.

## Serviço externo

Habilitar um fluxo envia o PDF selecionado, o nome/e-mail mapeados do signatário e a mensagem de convite para a conta e o ambiente Assinafy configurados no core. Respostas da API, identificadores remotos e o status da assinatura são espelhados localmente. É necessária uma conta Assinafy, e os termos e preços do serviço se aplicam. A redação feita pelo WordPress não exclui registros remotos no Assinafy.

Serviço: [Assinafy](https://www.assinafy.com.br/). Revise seus [Termos de Uso](https://www.assinafy.com.br/termos-de-uso) e a [Política de Privacidade](https://www.assinafy.com.br/politica-de-privacidade).

## Validação em desenvolvimento

A partir da raiz do repositório, use a suíte de integração compartilhada com o plugin Lite oficial instalado em um diretório de plugin irmão normal. A verificação de requisitos do próprio WPForms depende desse nome de diretório; uma cópia aninhada em `assinafy/build/hosts` não será inicializada como Lite.

```sh
docker exec \
  -e WP_TESTS_DIR=/wordpress-phpunit \
  -e ASSINAFY_WPFORMS_FILE=/var/www/html/wp-content/plugins/wpforms-lite/wpforms.php \
  -w /var/www/html/wp-content/plugins/assinafy \
  wp-env-wordpress-plugin-6587357c-tests-cli-1 \
  vendor/bin/phpunit -c phpunit-integration.xml.dist --filter WPFormsAdapterTest
```

Use o nome do seu próprio container wp-env. Os testes interceptam as chamadas HTTP ao Assinafy e o envio de e-mail do WordPress; eles não enviam convites reais. A cobertura inclui validação e aceitação de spam reais do Lite, falha de notificação, salvamento nativo do formulário, deduplicação de recibos, nova tentativa após falha parcial, apagamento por privacidade e autorização. O `tests/stubs/wpforms.php` local serve apenas para análise estática e nunca deve substituir o host Lite real nos testes de integração.

Fluxos licenciados do Pro, pagamentos, addons de terceiros e interações de navegador continuam fora do escopo verificado desta versão.

Licença: [GPL-2.0-or-later](LICENSE).
