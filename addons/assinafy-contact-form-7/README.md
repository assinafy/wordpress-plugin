# Assinafy para Contact Form 7

*Português · [Read in English](README.en.md)*

Versão 0.1.0. Um add-on WordPress separado para o Assinafy e o Contact Form 7. Ele envia um PDF já existente na Biblioteca de Mídia para o signatário mapeado depois que o Contact Form 7 reporta uma submissão bem-sucedida.

Exige WordPress 6.8+, PHP 8.2+, o plugin core do Assinafy e o Contact Form 7. Os testes com host real usam o Contact Form 7 6.1.7. Não há dependências distribuídas junto nem etapas de build de JavaScript.

## Configuração

1. Instale e configure o Assinafy, instale o Contact Form 7 e então ative este add-on.
2. Envie o contrato em PDF para a Biblioteca de Mídia.
3. Abra **Contact → Contact Forms**, edite um formulário e selecione o painel **Assinafy**.
4. Selecione o PDF, um campo de nome text/textarea e um campo de e-mail. Os nomes dos campos são escolhidos entre as tags já existentes no formulário.
5. Opcionalmente, selecione um campo de aceite. Se selecionado, ele precisa estar marcado; campos de aceite invertidos ficam de fora. Explique a solicitação de assinatura no formulário.
6. Defina uma mensagem de convite opcional, habilite as solicitações de assinatura e salve o contact form.

A configuração exige a permissão de edição de formulários do host, `assinafy_manage` e `assinafy_send`. O salvamento reaproveita o nonce do editor do Contact Form 7. Salvamentos de formulário via REST ou programáticos que omitam este painel preservam as configurações dele. O seletor de PDF lista os 200 PDFs mais recentes e mantém a seleção atual mesmo que ela seja mais antiga.

## Comportamento na submissão

O callback PHP `wpcf7_submit` exige que tanto o resultado quanto o `WPCF7_Submission` atual tenham status `mail_sent`. Desfechos inválidos, de spam, de aceite ausente, abortados e de falha no envio de e-mail não disparam o envio. O modo demo não envia. A flag nativa de consentimento `do_not_store` também impede a criação de uma solicitação de assinatura.

O modo `skip_mail` intencional do Contact Form 7 reporta sucesso e pode solicitar assinaturas; isso é diferente do modo demo. A primeira tentativa de assinatura roda de forma síncrona após o desfecho aceito pelo CF7. Falhas de API não alteram esse desfecho do host nem a mensagem exibida ao visitante.

O Contact Form 7 não tem um registro de submissão persistente embutido. O add-on reserva um recibo privado de documento do Assinafy antes de chamar a API. A origem dele é:

```php
array(
    'integration' => 'contact-form-7',
    'record_id' => '<form-id>:<local-receipt-id>',
)
```

A chave estável contém o ID do formulário, o ID do recibo e o ID do anexo. Callbacks repetidos para o mesmo objeto de submissão usam um único recibo. Uma nova submissão HTTP cria um novo recibo, inclusive no caso de um reenvio intencional dos mesmos valores. Não há token de replay no navegador entre requisições nem deduplicação por janela de tempo.

## Status e nova tentativa

Abra **Assinafy → Documents** para inspecionar uma solicitação. O core é o dono dos links de assinatura, do status, dos downloads, dos erros, das atualizações por webhook e da reconciliação.

Uma tentativa que falhou e cujos dados retidos do signatário ainda sejam utilizáveis exibe **Retry original request** na barra lateral do documento para usuários com `assinafy_manage`, `assinafy_send` e permissão para editar tanto o recibo do documento quanto o contact form de origem. Esse POST protegido por nonce usa a seleção original de PDF, o convite, a origem e a chave; ele não reenvia o contact form nem aceita dados de signatário substituídos vindos do navegador. Mantenha o PDF selecionado e o contact form disponíveis até a solicitação terminar. A recuperação do core reaproveita um upload conhecido e se recusa a contornar um upload com desfecho desconhecido.

Envios bem-sucedidos removem a configuração de nova tentativa do add-on. Recibos na lixeira, contact forms de origem excluídos e dados do signatário apagados ou ausentes não permitem nova tentativa. Não há fila automática de novas tentativas, geração de PDF, condicionamento a pagamento, template de e-mail adicional nem dependência do Flamingo.

## Dados e serviço externo

Em uma submissão elegível, o PDF, o nome/e-mail mapeados do signatário e o convite são enviados ao serviço Assinafy configurado no plugin core. Veja o [Assinafy](https://assinafy.com.br/), seus [Termos de Uso](https://assinafy.com.br/termos-de-uso) e a [Política de Privacidade](https://assinafy.com.br/politica-de-privacidade).

Apenas a identidade mapeada do signatário é armazenada na projeção de signatários do documento no core. O add-on retém separadamente a seleção de PDF, o convite, a origem e a chave de idempotência enquanto a nova tentativa for necessária. Ele não copia submissões completas, endereços IP, metadados do navegador nem campos não relacionados. As ferramentas de privacidade do WordPress no core exportam e apagam os dados do signatário, inclusive de signatários pendentes sem ID remoto. Tentar de novo com uma identidade apagada é recusado. Documentos remotos e evidências de assinatura são gerenciados separadamente pelo Assinafy.

A desativação preserva recibos e configurações. Remover este add-on não exclui evidências de assinatura; a política documentada de retenção/desinstalação do core se aplica aos seus registros de documento. A configuração do formulário continua vinculada ao seu formulário do Contact Form 7 até que ele seja excluído.

## Verificação

Os testes de integração usam o plugin host real, disponível gratuitamente, e interceptam as chamadas HTTP e de e-mail do WordPress; nenhum convite real é enviado. A partir do checkout wp-env do repositório do core, depois de colocar o host oficial em `build/hosts/contact-form-7`:

```bash
docker exec \
  -e ASSINAFY_CF7_FILE=/var/www/html/wp-content/plugins/assinafy/build/hosts/contact-form-7/wp-contact-form-7.php \
  -w /var/www/html/wp-content/plugins/assinafy \
  <tests-cli-container> vendor/bin/phpunit \
  -c phpunit-integration.xml.dist --filter ContactForm7Test
```

Rode as suítes que dependem de banco de dados em série contra o banco de testes compartilhado. Arquivos de teste e de desenvolvimento ficam de fora do ZIP do add-on.

Referências principais: [arquivo oficial do código-fonte do Contact Form 7 6.1.7](https://downloads.wordpress.org/plugin/contact-form-7.6.1.7.zip), [FAQ do Contact Form 7](https://contactform7.com/faq/), [acceptance checkboxes](https://contactform7.com/acceptance-checkbox/).

Licenciado sob GPL-2.0-or-later; veja [LICENSE](LICENSE).
