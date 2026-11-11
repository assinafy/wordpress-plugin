# Assinafy para WordPress

[English](README.en.md) · [Guia completo](docs/user-guide.md)

Envie PDFs para assinatura eletrônica, acompanhe o andamento e baixe os artefatos assinados pelo WordPress.
Gerencie termos contratuais, aditivos, renovações e encerramento pelo admin ou WP-CLI.

## Requisitos e instalação

- WordPress 6.8 ou superior; PHP 8.2–8.5 com libsodium. PHP 8.5 é recomendado para novas instalações.
- Workspace Assinafy com os recursos do plano necessários e saldo de documentos/créditos suficiente.
- Admin WordPress em HTTPS e material de criptografia exclusivo no servidor; veja a [configuração](docs/user-guide.md#3-configuração).

Instale o ZIP da versão em **Plugins → Adicionar novo → Enviar plugin** e ative-o.
Um checkout do código-fonte precisa de `composer install` para gerar `vendor-prefixed`; o ZIP já inclui as dependências de execução.
O plugin usa o HTTP nativo do WordPress com o PHP SDK 2.9.2. PHP tem períodos de suporte ativo e de segurança, sem uma edição LTS separada.

## Conectar

1. Abra **Assinafy → Configurações** e selecione OAuth.
2. Use o cliente público existente para abrir a autorização da Assinafy. Entre e escolha um workspace.
3. Autorize `account:read documents:read documents:write webhooks:write offline_access`.
4. Cole o código temporário na página do mesmo administrador em até 60 segundos.
5. Teste a conexão. Os tokens ficam criptografados localmente; refresh tokens são rotacionados automaticamente. Desconectar remove o acesso local e tenta revogá-lo no servidor.

A autenticação por chave de API continua disponível para integrações de servidor. Selecione o ambiente de produção ou sandbox e o workspace correspondentes.
Mantenha credenciais no servidor. Campos de segredo em branco preservam o valor salvo. [Detalhes e recuperação](docs/user-guide.md#3-configuração).

## Fluxo completo do documento

1. Escolha um PDF legível em **Assinafy → Enviar para assinatura**, informe nomes/e-mails e, opcionalmente, mensagem e prazo. Hooks, CLI e adaptadores habilitados usam o mesmo serviço.
2. Valide PDF, contatos, combinação de verificação/notificação, ordem contínua das etapas e prazo antes das requisições.
3. Reserve um recibo local da solicitação. Localize os signatários existentes ou crie os contatos ausentes, envie o PDF e persista seu ID remoto.
4. Estime o custo da atribuição. Saldo insuficiente preserva o upload e retorna um registro recuperável; tente novamente após abastecer o workspace.
5. Crie a atribuição virtual com métodos de verificação, canais de notificação e etapas explícitos. A API envia os convites.
6. Os signatários concluem o fluxo hospedado da Assinafy. E-mail/WhatsApp usam códigos; certificados ficam com seus titulares; biometria coleta consentimento, fotos do documento e prova de vida na Assinafy.
7. Webhooks autenticados e reconciliação horária consultam o estado oficial. A revisão de identidade pode adiar a certificação mesmo depois da assinatura.
8. Acompanhe o progresso e baixe os artefatos disponíveis: original, certificado, página de certificação, PAdES ou pacote, por links protegidos por permissões.
9. Renomeie apenas antes da atribuição; reenvie, prorrogue ou exclua conforme permitido pela API. Retome operações parciais conhecidas sem repetir um upload de resultado incerto.

No `expires_at` de hooks/SDK, prazos somente com data terminam às 23:59 UTC. Os controles nativos enviam 23:59:59 UTC explicitamente. Data/hora sem fuso usa UTC; deslocamentos explícitos são preservados.
Veja [payloads e estados de recuperação](docs/document-flow.md) e [exemplos avançados de hooks](docs/user-guide.md#5-pontos-de-extensão).

| Verificação | Valor na API | Notificações | Requisitos |
| --- | --- | --- | --- |
| E-mail | `Email` | `Email` | E-mail válido |
| WhatsApp | `Whatsapp` | `Whatsapp` | Telefone válido e recurso habilitado no workspace |
| Certificado A1/A3 | `DigitalCertificate` | `Email` ou `Whatsapp` | Signatário existente elegível, recurso habilitado e etapa isolada |
| Biometria | `IdDocumentFaceMatchBasic` | `Email` ou `Whatsapp` | Signatário existente com CPF válido, recurso habilitado, consentimento e fluxo de identidade hospedado |

A tela de envio aceita signatários por e-mail. Hooks avançados e o SDK incluído expõem os demais métodos.
Uma revisão de identidade pendente deve ser resolvida na Assinafy antes da certificação. O WordPress nunca coleta chaves privadas de certificados ou evidências biométricas.

## Webhooks e contratos

**Registrar webhook** gerencia o endpoint deste site sem substituir endpoints de terceiros. Os eventos selecionados são limitados ao catálogo disponível no ambiente.
O receptor valida seu token de URL e a assinatura Standard Webhooks, rejeita entregas assinadas antigas e consulta novamente o documento.
A criação salva o segredo criptografado; OAuth recupera um segredo ausente por rotação. [Configuração de webhooks](docs/user-guide.md#7-webhooks).

**Assinafy → Contratos** consulta os termos atuais sob demanda. Gerencie valores, datas, renovação automática, aditivos e encerramento.
Alterações exigem o recurso de gestão de contratos no plano. Cada alteração de contrato existente envia sua revisão; conflitos exigem recarregar.
A renovação mantém o termo original para impedir avanço duplicado. Encerrar interrompe alertas de ciclo de vida sem excluir o PDF nem alterar as assinaturas.
[Fluxo e payloads de contratos](docs/user-guide.md#gestão-de-contratos-clm).

## Integrações e SDK

WooCommerce e Elementor usam o serviço central. Contact Form 7, WPForms e Gravity Forms são distribuídos como complementos separados.
Configure permissões, mapeamento de campos e consentimento antes de habilitá-los. [Configuração dos hosts](docs/integrations.md).

O SDK também expõe envelopes, sessões de assinatura incorporada, identidade visual, entrega de cópia certificada, políticas de notificação e uso da conta.
Esses recursos são APIs do SDK, não telas nativas adicionais do WordPress. Permissões do plano e escopos OAuth continuam obrigatórios.

- [Cada método do SDK, entrada e retorno](docs/sdk-reference.md)
- [Todas as requisições, respostas e schemas compartilhados da API](docs/api-payloads.md)
- [Contrato oficial da API](https://api.assinafy.com.br/v1/docs)
- [Comandos WP-CLI](docs/user-guide.md#6-wp-cli)
- [Solução de problemas](docs/user-guide.md#10-solução-de-problemas)
- [Multisite, privacidade e desinstalação](docs/user-guide.md#comportamento-em-multisite-e-privacidade)

## Desenvolvimento

Use PHP 8.2–8.5 e uma versão Node.js LTS com suporte, no mínimo 22.13.0.

```sh
composer install
npm ci
composer check
npm run start
npm run test:php:integration
npm run test:php:ajax
npm run test:e2e
npm run makepot
bash tests/build-zip.sh
bash bin/build-zip.sh
bash bin/build-addons.sh
```

O [guia de desenvolvimento](docs/user-guide.md#11-desenvolvimento) descreve fixtures dos hosts e execução multisite.
Testes ao vivo usam `phpunit-sandbox.xml.dist` com credenciais em variáveis de ambiente.
Produção exige `ASSINAFY_LIVE_PRODUCTION=1` explícito e autorização para criar/excluir PDFs e termos contratuais descartáveis.
Esses testes nunca criam atribuições nem alteram configurações do workspace. Assinaturas concluídas exigem destinatários consentidos e os respectivos recursos de teste.

GPL-2.0-or-later. Veja [LICENSE](LICENSE).
