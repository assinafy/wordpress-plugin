# Arquitetura de integrações e rollout

*Português · [Read in English](integrations.en.md)*

A Assinafy é dona do ciclo de vida da assinatura. Os adapters conectam os eventos e os registros
de uma aplicação hospedeira a esse ciclo por meio de uma pequena API PHP e de actions nativas
do WordPress.

WooCommerce e Elementor são adapters embarcados. Add-ons de desenvolvimento separados para
Gravity Forms, Contact Form 7 e WPForms ficam no checkout do código-fonte, em `addons/`. A
verificação em host real cobre CF7 e WPForms Lite; Gravity Forms, Elementor Pro e WPForms Pro
ainda exigem validação em instalação licenciada. Veja a [configuração do Elementor](elementor.md).
O slug do plugin no WordPress.org continua sendo `assinafy`; esta mudança de arquitetura não
inclui upload nem publicação no WordPress.org.

## Divisão de responsabilidades

| Core | Adapter |
| --- | --- |
| Credenciais, transporte da API e configuração da conta | Disponibilidade do host e versões suportadas |
| Validação de PDF e de signatários, upload e solicitações de assinatura | Eventos do host, mapeamento de campos e condições do fluxo |
| Recuperação durável de envios e proteção contra envio duplicado | Uma referência de origem estável e uma chave de envio com namespace |
| Registros locais de documentos, downloads e ferramentas de privacidade | Vínculos com o registro do host, permissões do host e retenção |
| Autenticação de webhook, reconsulta autoritativa e sincronização de status | Atualização do registro de origem depois de uma transição verificada |

O envio, o armazenamento e a sincronização do core não podem importar classes do host, chamar
funções do host nem se ramificar por nome de provedor. O código de composição do plugin pode
reconhecer um host instalado e registrar o adapter embarcado dele. O comportamento atual do
WooCommerce permanece no lugar.

Uma fronteira de adapter e um pacote de plugin separado são decisões diferentes. Um adapter pode
viver neste repositório sem colocar comportamento específico do host no domínio do core. Movê-lo
para outro pacote se justifica pelo fluxo, pelo ciclo de release ou pelas dependências dele.

## Registre antes do `init`

O core emite esta action uma única vez, depois que seus serviços e hooks são registrados:

```php
do_action( 'assinafy_ready', $send, $records );
```

Os argumentos são `Assinafy\WP\Documents\SendService` e
`Assinafy\WP\Documents\DocumentRecord`. Um addon anexa seu listener quando o arquivo do plugin
dele é carregado, antes do `init`. Registrar o listener a partir de um callback de `init`
posterior pode perder a action. O addon deve usar os serviços fornecidos em vez de reconstruir
o cliente do SDK ou acessar `Plugin` como um service locator.

Por exemplo, o bootstrap de um addon pode registrar seu próprio adapter:

```php
use Assinafy\WP\Documents\DocumentRecord;
use Assinafy\WP\Documents\SendService;
use ExampleVendor\ContractAddon\Adapter;

add_action(
	'assinafy_ready',
	static function ( SendService $send, DocumentRecord $records ): void {
		( new Adapter( $send, $records ) )->register();
	},
	10,
	2
);
```

`ExampleVendor\ContractAddon\Adapter` é a implementação do próprio addon, não uma classe
fornecida pela Assinafy. O exemplo ilustra o registro sem inventar uma API do Gravity Forms nem
um feed de formulário funcional. O `register()` do adapter anexa os callbacks reais do host e os
callbacks de status da Assinafy de que ele precisa. Se o core estiver inativo, a action não
dispara; um addon separado também deve declarar e explicar suas dependências de plugin.

## Contrato de envio

`SendService::send( array $args ): int|WP_Error` é a operação de envio comum. Em caso de sucesso,
devolve o ID do post local `assinafy_document`. Em caso de falha, devolve um `WP_Error`; quando um
envio já reservou um registro local, os dados do erro podem incluir esse `post_id` de recuperação.

O PDF, os signatários, a mensagem, a expiração e o `post_id` opcional do espelho mantêm o
[contrato documentado](../README.md#assinafy_send_document--enviar-agora) atual. O `post_id` do espelho
não é o ID da entry nem do pedido no host. A identidade do host pertence a `source`:

```php
$result = $send->send(
	array(
		'attachment_id'   => 412,
		'signers'         => array(
			array( 'full_name' => 'Jane Doe', 'email' => 'jane@example.com' ),
		),
		'source'          => array(
			'integration' => 'example-forms',
			'record_id'   => 'entry-123',
		),
		'idempotency_key' => 'example-forms:entry-123:workflow-signature:feed-7:agreement-v2',
	)
);
```

Os IDs do exemplo ilustram o contrato; o adapter precisa resolver, a partir do seu host, um PDF
real e disponível e dados de signatário validados.

### <a id="source-reference"></a>Referência de origem

`source` é opcional. Quando informado, tem exatamente estas duas chaves:

| Chave | Contrato |
| --- | --- |
| `integration` | Uma string de 1 a 64 letras ASCII minúsculas, dígitos, underscores ou hífens. Use um slug estável do provedor. |
| `record_id` | Uma string de 1 a 191 caracteres ASCII imprimíveis sem espaços (`0x21`–`0x7e`). Trate-a como um identificador opaco do host. |

Não coloque nesta referência uma entry inteira, um perfil de cliente, um endereço de e-mail ou um
mapa de campos. Use um identificador de registro do host que o adapter consiga resolver. A
referência fornece informação de roteamento, não autorização para acessar aquele registro.

O core vincula um source não vazio à reserva local antes do upload. Uma nova tentativa não pode
redirecionar essa reserva para outro source. Omitir o source continua válido para chamadores e
registros antigos. Os adapters devem reenviar sempre o mesmo source nas novas tentativas.

`DocumentRecord::source( int $post_id )` devolve o par armazenado ou `array()` quando nenhum source
foi registrado. Os adapters usam esse accessor em vez de nomear uma meta key privada. O source
pertence ao site WordPress atual; um ID em outro site é outro registro.

### Idempotência e recuperação

Uma `idempotency_key` explícita continua restrita à conta e ao ambiente da Assinafy configurados.
O core **não** acrescenta o source silenciosamente a uma chave explícita. Isso preserva as chaves
existentes, inclusive os registros de recuperação do WooCommerce criados antes de as referências
de origem existirem.

O adapter precisa dar namespace à sua chave com o provedor, o registro, o fluxo ou feed e a revisão
de contrato que identificam uma única requisição pretendida. Adapters diferentes usando apenas
`entry-123` podem colidir dentro da mesma conta. Um valor aleatório, um timestamp ou um UUID gerado
na hora impedem que as novas tentativas encontrem a requisição original.

O digest padrão, usado quando nenhuma chave explícita é informada, inclui o source quando ele
existe. Chamadas existentes sem source mantêm o comportamento anterior. Ainda assim, os adapters
devem preferir uma chave explícita que represente a operação de negócio, e não o estado incidental
da requisição.

Uma chave repetida pode devolver o espelho existente ou retomar um envio parcialmente concluído.
Um resultado de upload desconhecido exige investigação; criar uma chave nova só para contornar esse
erro pode criar outro documento remoto. A imutabilidade do source não substitui o namespacing
correto da chave nem o tratamento da recuperação.

### Actions nativas existentes

Estes pontos de extensão continuam disponíveis para código que não precisa de serviços injetados:

| Action | Comportamento |
| --- | --- |
| `assinafy_send_document( $args )` | Envia durante a requisição atual. |
| `assinafy_send_document_async( $args )` | Agenda os mesmos argumentos pelo WordPress Cron. |
| `assinafy_send_document_result( $result, $args )` | Observa o resultado de qualquer um dos caminhos baseados em hook. |

A action de resultado é específica dos caminhos baseados em hook. Uma chamada direta a
`$send->send()` devolve o resultado ao chamador; ela não promete também essa action de resultado.
Os adapters precisam tratar o resultado devolvido quando chamam o serviço diretamente.

Em envios adiados, os argumentos precisam sobreviver à serialização e o PDF precisa continuar
legível quando o cron rodar. Um arquivo temporário de upload ou um objeto do host que só existe
durante o envio do formulário não bastam. O WordPress Cron também depende de a execução de cron
do site estar configurada.

## Receber mudanças de status verificadas

As actions de status existentes são o contrato de callback:

| Action | Argumentos |
| --- | --- |
| `assinafy_document_status_changed` | ID do espelho local, status atual, status anterior |
| `assinafy_document_certificated` | ID do espelho local, documento consultado |
| `assinafy_document_rejected` | ID do espelho local, documento consultado |
| `assinafy_document_expired` | ID do espelho local, documento consultado |
| `assinafy_document_failed` | ID do espelho local, documento consultado |

Em um callback, leia `$records->source( $post_id )`, confira se `integration` é o slug do seu
adapter e só então resolva `record_id` pela API do próprio host. Um source vazio é um registro
legado ou sem atribuição, não um motivo para adivinhar um dono. Um registro do host apagado precisa
ser tratado sem quebrar a sincronização dos demais documentos.

Essas actions vêm do `StatusSync` depois que uma consulta autenticada à API foi validada e o
espelho, atualizado. A sincronização disparada por webhook e a disparada por cron usam o mesmo
caminho. Elas descrevem transições de status do documento; um signatário pode avançar enquanto o
documento continua em `pending_signature`, sem disparar uma action de transição. Um futuro
consumidor de progresso pode justificar uma notificação pós-sincronização no core; um catálogo
geral de eventos não é necessário agora.

Os callbacks são executados na requisição atual. Eles não são entregas duráveis e não têm garantia
de exatamente-uma-vez. Os handlers precisam ser idempotentes, principalmente quando enviam
notificações, alteram registros de negócio ou concedem acesso. Um adapter que precise de novas
tentativas confiáveis tem de persistir esse trabalho pelo mecanismo suportado do seu host e
reportar as falhas ali.

### Mantenha os webhooks brutos da Assinafy no core

Os adapters não implementam `handle_webhook()` para as entregas da Assinafy. Essas entregas não são
assinadas, então o core confere o token do endpoint e a conta, identifica um documento local
conhecido e reconsulta o estado autoritativo antes de alterá-lo. Encaminhar payloads brutos para os
adapters duplicaria essa fronteira e permitiria decisões de confiança diferentes para a mesma
entrega.

Um webhook de entrada do próprio host, se for necessário, é uma preocupação separada do adapter,
com a autenticação e a validação daquele host. Ele ainda assim entra no fluxo de assinatura pela
operação de envio comum.

## Mantenha o contrato do adapter pequeno

Não existe uma interface de adapter obrigatória com cinco métodos:

| Método sugerido | Substituto mínimo útil |
| --- | --- |
| `get_source()` | Um slug estável mais o par de origem concreto em cada envio. |
| `get_fields()` | Mapeamento de campos específico do host, na hora de implementar a UI real do feed. Sem motor universal de schema de campos. |
| `get_events()` | Registrar as actions nativas do host que o adapter suporta. Só adicione metadados de descoberta se houver um seletor de gatilho real. |
| `get_record()` | Resolver o registro do host dentro do adapter quando o fluxo exigir. Alguns envios de formulário não têm entry persistida. |
| `handle_webhook()` | Assinar as actions de status verificadas do core. |

Um método `register()` que pertence ao addon é uma convenção conveniente, não uma classe base
obrigatória nem um framework de registro. Os campos, os gatilhos selecionáveis e as configurações
de um provedor podem evoluir dentro do adapter dele sem obrigar todos os outros adapters a
implementar métodos que não usam.

## Responsabilidades do adapter

- Autenticar as requisições do host, checar permissões e nonces quando aplicável, exigir
  consentimento e verificar se o gatilho selecionado é apropriado. O serviço PHP é código confiável
  do lado do servidor; ele não autoriza usuários nem expõe um endpoint público em nome de um adapter.
- Mapear e validar campos de signatário, ordem de assinatura, métodos de verificação, condições do
  fluxo e seleção de PDF usando as APIs do host. A validação do core continua sendo a fronteira final.
- Usar armazenamento durável de PDF para trabalho enfileirado e decidir a propriedade e a limpeza dos
  arquivos gerados. A API de envio não oferece, por si só, um motor de templates de documento ou de
  geração de PDF.
- Persistir a relação entre o host e o espelho quando a UI do host precisar dela, reportar falhas de
  envio e preservar a informação de recuperação em vez de marcar como concluída uma operação que falhou.
- Tratar exclusão de registros, pedidos de dados pessoais e retenção no host. A anonimização de
  signatários feita pelo core não apaga a entry, o pedido nem o documento remoto do host. Evite guardar
  envios completos na meta do documento ou logar payloads sensíveis.
- Usar referências de origem e tratamento idempotente de callbacks para manter isolados provedores,
  fluxos e sites WordPress diferentes.

## Rollout

| Ordem | Entrega | Decisão de empacotamento |
| --- | --- | --- |
| 1 | Estabilizar a prontidão do core, as referências de origem e o comportamento de extensão documentado. | Manter o plugin `assinafy` atual e o adapter WooCommerce embarcado. |
| 2 | Implementação nativa do feed do Gravity Forms; validação em host licenciado pendente. | Addon separado `assinafy-gravity-forms`, 0.1.0. |
| 3 | Mapeamento de envio aceito/consentimento do Contact Form 7 e novas tentativas protegidas. | Addon separado `assinafy-contact-form-7`, 0.1.0. |
| 4 | Mapeamento do WPForms Lite, recibo durável e novas tentativas; verificação no Pro licenciado pendente. | Addon separado `assinafy-wpforms`, 0.1.0. |
| 5 | Action do Elementor Forms e permissões de configuração no servidor; validação do Pro pendente. | Adapter embarcado, registrado pelo hook nativo do Pro Forms. |
| 6 | Considerar um produto WooCommerce separado para um fluxo de contrato mais profundo. | Separar apenas quando esse fluxo justificar; até lá, preservar o comportamento embarcado atual. |

### Critérios de aceite do release do Gravity Forms

O adapter em desenvolvimento implementa um PDF existente, um signatário de e-mail mapeado, as
condições de feed e o processamento em segundo plano nativos, e a recuperação na página da entry.
Ele não implementa travas de pagamento, geração de PDF, métodos de verificação variáveis nem
controles de ordem de assinatura. Os critérios abaixo continuam sendo itens de revisão do release;
testes de contrato não substituem a validação em host licenciado.

- Definir as versões suportadas do Gravity Forms e o ciclo de vida real de feed/envio antes de
  escolher os hooks. Não adicionar API de host de fachada nem alegação de compatibilidade sem suporte.
- Fornecer uma configuração de feed funcional para a seleção de PDF ou um produtor de PDF
  explicitamente suportado, mapeamento de signatários, condições, verificação e ordem de assinatura.
- Escolher o gatilho de envio exato, inclusive se o pagamento é obrigatório. Gerar uma chave estável
  a partir da entry, do feed/fluxo e da revisão do contrato; as novas tentativas precisam reaproveitar
  o mesmo envio.
- Salvar o source antes do upload, ligar o espelho de volta à entry e mostrar estados acionáveis de
  sucesso, falha e recuperação pela UI administrativa nativa do host.
- Rotear os resultados terminais verificados de volta para a entry correta. Definir o que acontece
  quando essa entry é apagada, o addon é desativado ou um callback precisa ser repetido.
- Testar a versão suportada do host contra uma instalação real, incluindo consentimento/permissões,
  gatilhos duplicados, recuperação de envio parcial, disponibilidade adiada do PDF e tratamento de
  privacidade.

### <a id="decisions-before-a-deeper-woocommerce-split"></a>Decisões antes de uma separação maior do WooCommerce

Decida se o produto precisa de assinatura antes do pagamento, assinatura antes da entrega, estados
de pedido customizados, contratos assinados para download, geração de contratos, renovações ou
assinaturas recorrentes, múltiplos signatários ou rastreamento de revisões. Defina também o
comportamento de cancelamento/estorno, o acesso de visitantes, as telas da conta do cliente e a
migração dos metadados existentes de produto e de pedido.

Esses requisitos justificariam um fluxo e um ciclo de release separados. Mover o gatilho atual de
conclusão de pedido para outro ZIP, sozinho, não justifica. Qualquer separação precisa preservar os
mapeamentos existentes de pedido para documento, as chaves de idempotência informadas, a recuperação
de envio parcial, o comportamento com HPOS e os links enviados por e-mail ao cliente.

## Verificações de regressão na fronteira do core

Verifique que a prontidão expõe os serviços compartilhados uma única vez; que um source malformado é
rejeitado antes de qualquer efeito colateral remoto; que um source sobrevive a uma falha parcial e não
pode ser alterado em uma nova tentativa; e que a leitura do source continua vazia para registros
legados. O comportamento da chave explícita precisa continuar compatível, enquanto os digests padrão
distinguem sources diferentes.

Exercite um addon pequeno pelo registro, pelo resultado do envio e pelo roteamento de status
verificado, com notificações duplicadas, um registro de host ausente e uma troca de site. Mantenha
passando os chamadores existentes baseados em hook, o fluxo embarcado do WooCommerce e o comportamento
de recuperação deles. Um adapter futuro então adiciona testes para o contrato do próprio host, em vez
de duplicar a suíte de transporte do core.
