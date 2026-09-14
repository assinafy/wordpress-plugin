# Assinafy para Gravity Forms

*Português · [Read in English](README.en.md)*

A versão 0.1.0 é um adaptador em desenvolvimento. Ela exige Assinafy Core 1.0.0+, WordPress 6.8+, PHP 8.2+ e Gravity Forms 2.9.4+ licenciado. Os testes de contrato automatizados usam dublês de host documentados com WordPress e Assinafy Core reais; uma instalação licenciada do Gravity Forms **não** foi testada.

Instale este diretório como `wp-content/plugins/assinafy-gravity-forms`, ao lado do Assinafy e do Gravity Forms. Configure a conta no Assinafy Core e depois adicione um feed em Settings → Assinafy do formulário. Informe o ID de um anexo PDF já existente na Biblioteca de Mídia, mapeie Full name e Email e, opcionalmente, defina uma mensagem e a condição nativa do feed. Editar o feed exige `assinafy_send`; a validação do PDF também exige `gravityforms_edit_forms` e permissão para editar aquele anexo.

O [Feed Add-On Framework nativo](https://docs.gravityforms.com/gffeedaddon/) é o dono do processamento condicional e da fila em segundo plano. Este adaptador envia um PDF existente para um signatário usando a verificação por e-mail padrão do core. Ele não gera PDFs, não preenche templates, não exige pagamento, não condiciona confirmações a assinaturas e não dá suporte a fulfillment de pagamento. Adicione consentimento explícito e qualquer condição de feed apropriada ao seu formulário.

Cada par entry/feed produz uma solicitação de assinatura. Um fingerprint com chave do mapeamento original, dos valores e dos bytes do PDF, mais o ID do documento no Assinafy, fica guardado na entry metadata do Gravity Forms; a submissão completa não é copiada. Mapeamentos alterados, valores da entry modificados ou um PDF substituído não podem redirecionar uma solicitação em aberto. Restaure os dados originais antes de tentar de novo. Uma falha retornada antes de o core reservar um documento libera o fingerprint, permitindo corrigir entradas inválidas; uma tentativa interrompida ou com upload parcial mantém o fingerprint. Um novo feed é um fluxo distinto e pode criar outro documento para a mesma entry. O core registra a origem como `gravity-forms` / ID da entry.

A página de detalhes da entry traz o link para o documento no core e oferece uma nova tentativa protegida por nonce para solicitações que falharam. As novas tentativas exigem `assinafy_send` e `gravityforms_view_entries`. Status do core, sincronização por webhook, links de assinatura, downloads e tratamento de privacidade continuam sendo compartilhados. Um upload com desfecho desconhecido exige conferir a conta Assinafy antes de qualquer novo envio. Os dados da entry continuam sujeitos aos controles de privacidade e retenção do próprio Gravity Forms; o apagamento do core cobre o espelho da assinatura.

Este add-on usa o serviço Assinafy por meio do Assinafy Core. No processamento de um feed elegível, ele envia o PDF selecionado, o nome/e-mail mapeados do signatário, a mensagem configurada e as configurações de assinatura para a conta Assinafy configurada. São necessárias uma conta Assinafy e saldo de documentos. Veja o [serviço](https://assinafy.com.br), os [termos](https://assinafy.com.br/termos-de-uso) e a [política de privacidade](https://assinafy.com.br/politica-de-privacidade). Nenhuma credencial ou transporte separado é distribuído junto.

Comando de teste a partir da raiz do repositório (exige a biblioteca de testes do WordPress):

```sh
ASSINAFY_GRAVITY_FORMS_CONTRACT=1 composer test:integration -- --filter GravityFormsContractTest
```

Antes de um release, valide em uma instalação licenciada as configurações reais de feed, o salvamento, o processamento condicional, os jobs em segundo plano, a interface e a nova tentativa na entry, a formatação do campo de nome, as permissões e a privacidade. Testes de contrato não estabelecem compatibilidade com o host.
