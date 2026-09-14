# Elementor Pro Forms

*Português · [Read in English](elementor.en.md)*

A Assinafy inclui uma action **Assinafy** para o **Actions After Submit** do Elementor Pro.
Ela envia um PDF local existente para um signatário, usando os campos de nome e e-mail
selecionados por quem monta o formulário. O Elementor Pro é obrigatório; o plugin Elementor
gratuito não tem o widget Forms.

Este adapter tem testes de contrato contra um WordPress real e dublês pequenos das APIs
documentadas do Elementor. O Elementor Pro licenciado não estava disponível durante o
desenvolvimento, então o adapter **não** foi testado com uma instalação real do Elementor Pro.
Valide o editor, as permissões de salvamento, a validação de envio e as respostas de
sucesso/erro em staging antes de usar.

## Configurar um formulário

1. Configure a conta e o ambiente da Assinafy no WordPress. Use uma conta de testes ao verificar
   um fluxo; um envio bem-sucedido cria uma solicitação de assinatura remota.
2. Faça upload do PDF do contrato para a Biblioteca de Mídia deste site. O usuário que está
   configurando precisa conseguir ler o anexo, e o arquivo local precisa continuar disponível
   para o WordPress.
3. Edite o widget Form do Elementor Pro como um usuário com a capability `assinafy_send`. Adicione
   **Assinafy** em **Actions After Submit** e abra a seção de configurações dela.
4. Selecione **Agreement PDF**. Informe os IDs de campo em **Signer name field ID** e
   **Signer email field ID**, iguais aos IDs configurados nos campos do formulário.
5. Defina **Agreement workflow and revision**, por exemplo `membership-agreement-v2`. Use um
   identificador distinto para cada fluxo de formulário/contrato: de 1 a 64 letras, números,
   pontos, underscores ou hífens. Atualize a revisão ao mudar o contrato pretendido.
6. Opcionalmente, defina **Message to signer**. Salve e teste o formulário em staging.

O PDF selecionado fica fixo na configuração salva do formulário. URLs de arquivo enviadas, uploads
e caminhos do sistema de arquivos não são usados. Além disso, o core confere o PDF e os dados do
signatário antes do envio. Geração dinâmica de PDF, múltiplos signatários, feeds condicionais e
expiração de assinatura configurável estão fora dos controles atuais deste adapter.

O dono do site precisa configurar no Elementor o acesso, o consentimento, os campos obrigatórios e
a proteção contra spam adequados. O adapter roda como uma action depois da validação do Elementor;
ele não deduz consentimento a partir de um envio bem-sucedido. Visitantes públicos não precisam de
`assinafy_send`: eles apenas submetem o fluxo que um autor de formulário autorizado salvou. Outras
actions configuradas no Elementor também podem rodar, e uma falha da Assinafy não desfaz os efeitos
colaterais delas.

## Autorização e exportações

O adapter esconde seus controles do editor de usuários sem `assinafy_send` e, de forma independente,
impõe essa capability pelo filter nativo `elementor/document/save/data` do Elementor. Um editor não
autorizado não consegue adicionar, remover nem modificar um formulário Assinafy configurado,
incluindo os campos mapeados e as configurações dinâmicas dele. Ele pode editar widgets não
relacionados, desde que deixe o formulário configurado intacto. Uma action ativada também exige um
PDF local acessível quando salva por um usuário autorizado.

Essa proteção vale para a API de salvamento de documentos do Elementor. Código PHP confiável que
escreve post meta diretamente continua responsável pela própria autorização, como acontece com a
API PHP de envio do core. O adapter não concede acesso a documentos nem capabilities de envio a
novas roles.

As exportações do Elementor omitem o PDF da Assinafy deste site, os mapeamentos de campo, a
identidade do fluxo e a mensagem. Reconfigure esses valores explicitamente depois de importar um
formulário em outro site.

## Resultados, origem e recuperação

A action envia de forma síncrona pelo `SendService` compartilhado. Em caso de sucesso, o Elementor
segue com a resposta de sucesso configurada. Em caso de falha, a action adiciona um erro de campo
nativo genérico. As respostas públicas não contêm diagnóstico da API, URL de assinatura nem ID local
de recuperação. O log da Assinafy registra o código do erro e qualquer ID de documento de recuperação
devolvido pelo core.

Cada `Form_Record` aceito recebe uma referência opaca:

```php
array(
    'integration' => 'elementor',
    'record_id'   => 'membership-agreement-v2:<32 hexadecimal characters>',
)
```

A chave de envio é `elementor:` seguido dessa referência exata. O sufixo aleatório identifica o envio
aceito sem guardar informação pessoal no source. Callbacks repetidos sobre o mesmo objeto de record
reaproveitam o resultado dele. Um envio distinto, mesmo com valores de campo idênticos, é uma nova
solicitação de assinatura. Isso não depende da action opcional Collect Submissions do Elementor nem
trata dados pessoais coincidentes como identificador de nova tentativa.

Assim que o core reserva um documento local, o source e a chave de envio dele permanecem nesse
recibo, inclusive depois de uma falha parcial de envio. O core cuida da sincronização remota de
status e da privacidade do documento pelos registros de documento que já mantém. Este adapter não
adiciona um armazenamento de envios separado nem atualizações automáticas de status nos envios
guardados pelo Elementor.

Hoje **não existe tela de nova tentativa no Elementor nem replay automático entre requisições**. Um
novo envio pelo frontend cria uma referência nova e pode criar um segundo documento remoto. Antes de
repetir uma falha, inspecione o documento e o erro da Assinafy que ficaram retidos. A recuperação
conduzida por um desenvolvedor precisa usar o source original, a chave original (`elementor:` mais o
`record_id`) e os argumentos de envio originais já validados. O adapter não guarda uma cópia separada
desses argumentos, então eles precisam estar disponíveis a partir de um registro autorizado do host
ou de outro registro de fluxo existente. Não os adivinhe nem crie uma chave nova para contornar um
resultado de upload desconhecido.

A referência de origem não é o ID de um envio no banco do Elementor. Quem consome os hooks de status
verificado do core pode reconhecer `integration === 'elementor'`, mas resolver um envio do Elementor
exige uma associação deliberada e durável do lado do host. Os callbacks não são entregas duráveis nem
de exatamente-uma-vez. Veja o [contrato de integração do core](integrations.md) para as garantias de
imutabilidade do source, de idempotência e dos hooks de status.

## Verificação e contratos nativos

O grupo `elementor-contract` do PHPUnit cobre o mapeamento e as restrições de arquivo local, o
comportamento em envio de visitante, callbacks duplicados versus envios distintos, a sinalização
nativa de erro, IDs de recuperação, exportações, o timing antecipado do registrar e as permissões de
salvamento no servidor. Closures de envio que apenas registram a chamada e um serviço de core não
configurado impedem solicitações de assinatura reais. Essas checagens não substituem testes de
compatibilidade em host licenciado.

A implementação segue a documentação oficial do Elementor: [registro de action customizada](https://developers.elementor.com/docs/form-actions/add-new-action/),
[estrutura da action](https://developers.elementor.com/docs/form-actions/action-structure/),
[exemplo avançado](https://developers.elementor.com/docs/form-actions/advanced-example/),
[hooks de formulário](https://developers.elementor.com/docs/hooks/forms/) e
[controle Media](https://developers.elementor.com/docs/editor-controls/control-media/).
O filter nativo de salvamento é definido pela
[implementação de document](https://github.com/elementor/elementor/blob/main/core/base/document.php) do Elementor.
A bridge registra durante o carregamento do arquivo do plugin, registra a action durante o evento de
registrar do Elementor e recebe o serviço de envio compartilhado pelo `assinafy_ready` antes de usá-lo.
