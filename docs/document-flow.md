# O fluxo do documento, passo a passo

*Português · [Read in English](document-flow.en.md)*

Este é o caminho principal do plugin: **método `virtual`, verificação por e-mail, todos os
signatários em paralelo na etapa 1.** Os passos 0–4 rodam através de
[`SendService::send()`](../src/Documents/SendService.php). Os links, a sincronização posterior e os
downloads usam o espelho local resultante. Os chamadores do admin, dos hooks, do WooCommerce, da
CLI e do cron compartilham o mesmo serviço de envio.
Os exemplos HTTP mostram a chave de API legada/Sandbox; uma conexão OAuth de produção usa
`Authorization: Bearer {ACCESS_TOKEN}` nos mesmos endpoints.

### Passo 0 — Validar localmente, antes de gastar uma requisição

O core valida a origem opcional, as credenciais, a seleção de arquivo, as identidades dos
signatários, os canais de notificação, as etapas de assinatura e o prazo antes de enviar
requisições HTTP. Em seguida ele usa `DocumentResource::assertUploadable()`, que verifica: o
arquivo existe → a extensão é `.pdf` → ele é legível → o `fopen` funciona → tamanho > 0 →
tamanho ≤ 25 MB → os primeiros 1024 bytes batem com `/%PDF-\d\.\d/` → os últimos 1024 bytes contêm
`%%EOF`.

A validação local reduz a chance de consumir a franquia de upload com conteúdo malformado. Ela
verifica o nome do arquivo e as assinaturas de cabeçalho/rodapé do PDF; o serviço remoto ainda toma
a própria decisão e pode rejeitar um arquivo localmente válido. Uma falha de API sem ID de
documento pode deixar um resultado incerto, então ela não deve disparar um novo upload automático.

Uma falha na checagem de PDF do SDK retorna `WP_Error( 'assinafy_invalid_pdf', … )`; a validação de
origem, anexo, signatários e prazo tem seus próprios códigos de erro. Essas falhas não produzem
upload algum.

Um lock nativo de banco de dados serializa cada chave de envio. Uma reserva local e sua origem
imutável opcional são salvas antes do upload; o ID remoto do documento é salvo antes do assignment.
A chave permanece no espelho depois que seu transient de cinco minutos expira. Registros já
atribuídos retornam o ID local existente; envios incompletos buscam novamente o upload salvo e
criam um assignment apenas se não houver um. Uma reserva sem ID remoto conhecido para para revisão
do operador, porque o resultado do seu upload pode ser desconhecido. A estimativa de custo roda
para novas reservas.

### Passo 1 — Precificar o envio e ler o veredito no corpo da resposta

```http
POST /v1/documents/{documentId}/assignments/estimate-cost
X-Api-Key: {API_KEY}
Content-Type: application/json

{"method":"virtual","signers":[{"verification_method":"Email","notification_methods":["Email"]}]}
```

```json
{
  "status": 200, "message": "",
  "data": {
    "documents": 1, "credits": 0,
    "needs_extra_document": false, "extra_document_cost": 0,
    "total_credits": 0, "breakdown": [],
    "document_balance": 480, "credit_balance": 0,
    "has_sufficient_resources": true, "blocking_reason": null, "message": null
  }
}
```

Um signatário notificado por WhatsApp tem preço diferente, e uma conta sem créditos recusa — **com
HTTP 200**:

```json
{
  "status": 200, "message": "",
  "data": {
    "documents": 1, "credits": 0.45,
    "total_credits": 0.45,
    "breakdown": [{"code":"NotificationWhatsapp","name":"WhatsApp Notification",
                   "cost":0.45,"quantity":1,"unit_cost":0.45}],
    "document_balance": 480, "credit_balance": 0,
    "has_sufficient_resources": false, "blocking_reason": "InsufficientCredits",
    "message": "A conta não possui créditos suficientes."
  }
}
```

**Leia `has_sufficient_resources` mesmo em HTTP 200, e trate também os erros de HTTP/API.** O
`blocking_reason` é um entre `PendingPayment`, `InsufficientDocuments` ou `InsufficientCredits`, e
cada um mapeia para uma mensagem específica ao administrador.

A única recusa que *de fato* usa um código de status é um método de verificação restrito pelo
plano: `DigitalCertificate` em uma conta sem o recurso responde 403
`"A assinatura com certificado digital não está disponível para o seu plano atual."` —
antes de qualquer documento ser consumido, que é exatamente o motivo de a estimativa rodar antes do
upload.

Os ids dos signatários não são necessários aqui: apenas a combinação de métodos é precificada. A
rota, porém, é endereçada a um documento, então o plugin usa o id do documento espelhado mais
recente como substituto — saldo e plano valem para a conta inteira, então qualquer documento da
conta responde à mesma pergunta. Um site que nunca enviou nada não tem esse id e recebe
`assinafy_estimate_unavailable`, que **nunca bloqueia um envio**: uma sondagem de preço que não
pôde rodar não é motivo para recusar.

### Passo 2 — Resolver cada signatário: buscar e então criar

O `email` é único por conta, então criar um signatário com um endereço que já existe é um 400 duro:
`"Um signatário com este e-mail já existe."` Uma segunda submissão da mesma pessoa falha se você
não buscar antes.

```http
GET /v1/accounts/{ACCOUNT_ID}/signers?search=jane%40example.com&page=1&per-page=100
X-Api-Key: {API_KEY}
```

O `search` é uma correspondência por **substring** sobre `full_name` OU `email`, então ele
alegremente retorna `jane@example.com.test` para `jane@example.com`. O `signers()->findByEmail()`
do SDK faz a varredura paginada, exata e sem diferenciar maiúsculas de que isso precisa; o plugin
usa esse método em vez de reimplementar a comparação.

Quando não encontra:

```http
POST /v1/accounts/{ACCOUNT_ID}/signers
X-Api-Key: {API_KEY}
Content-Type: application/json

{"full_name":"Jane Doe","email":"jane@example.com"}
```

```json
{
  "status": 200, "message": "",
  "data": {
    "resource": "signer",
    "id": "104618b9dc17b2b771659e8b1360",
    "full_name": "Jane Doe",
    "email": "jane@example.com",
    "whatsapp_phone_number": null,
    "government_id": null,
    "has_accepted_terms": false
  }
}
```

Regras de campo, verificadas contra a API real:

- Apenas `full_name` é obrigatório.
- `whatsapp_phone_number` é normalizado para E.164 assumindo `+55`: `"48999990000"` volta como
  `"+5548999990000"` e `"+55 48 99999-0001"` como `"+5548999990001"`. Curto demais é um 400
  `"Por favor, insira um número de telefone válido."` O SDK é mais rigoroso que a API aqui — ele
  exige localmente um `+` inicial e o código do país — então o plugin prefixa `+55` em números
  nacionais crus de 10 e 11 dígitos antes de repassá-los.
- `government_id` aceita um CPF ou um CNPJ no mesmo campo, com a pontuação removida
  (`"390.533.447-05"` → `"39053344705"`), com validação real de dígito verificador
  (`"11111111111"` → 400 `"CPF inválido."`).

### Passo 3 — Enviar o PDF

```http
POST /v1/accounts/{ACCOUNT_ID}/documents
X-Api-Key: {API_KEY}
Content-Type: multipart/form-data; boundary=…

--boundary
Content-Disposition: form-data; name="file"; filename="contract.pdf"
Content-Type: application/pdf

%PDF-1.4 …
--boundary--
```

```json
{
  "status": 200, "message": "",
  "data": {
    "resource": "document",
    "id": "104618b275d321f5de22240ebfda",
    "account_id": "{ACCOUNT_ID}",
    "template_id": null,
    "name": "contract.pdf",
    "status": "uploaded",
    "artifacts": {
      "original": "https://sandbox.assinafy.com.br/v1/documents/104618b275d321f5de22240ebfda/download/original"
    },
    "is_closed": false,
    "signing_url": "https://app-sandbox.assinafy.com.br/sign/104618b275d321f5de22240ebfda",
    "decline_reason": null,
    "declined_by": null,
    "tags": [],
    "created_at": "2026-09-13T18:40:10Z",
    "updated_at": "2026-09-13T18:40:10Z",
    "pages": []
  }
}
```

Detalhes que mudam a implementação:

- HTTP **200**, não 201. Sem cabeçalho `Location`.
- O nome da parte multipart precisa ser exatamente `file`. Qualquer outro dá 400
  `"O parâmetro \"file\" não está presente."`
- Um campo de formulário `name=` extra é **silenciosamente ignorado** — o documento adota o nome do
  arquivo.
- O nome do arquivo é **normalizado no servidor**: diacríticos reduzidos a ASCII, todo caractere
  não suportado substituído por um `-`. `"Contrato Ação & Nº2 (final).pdf"` vira
  `"Contrato Acao - N-2 -final-.pdf"`. Letras, dígitos, espaços, `.` e `-` sobrevivem. O plugin
  guarda o `data.name` retornado, não o nome que enviou, e é por isso que o título do post pode
  diferir do nome do arquivo na biblioteca de mídia.

**Não há espera por `metadata_ready`.** O passo 4 roda imediatamente. A reserva local é criada antes
do upload, e o id do documento retornado é armazenado antes do assignment.

### Passo 4 — Criar o assignment

```http
POST /v1/documents/104618d0d63884bc446c534e5ff5/assignments
X-Api-Key: {API_KEY}
Content-Type: application/json

{
  "method": "virtual",
  "signers": [
    {"id":"19e6b92e7895332ed9708535d8c","verification_method":"Email","notification_methods":["Email"],"step":1},
    {"id":"103033c9cd9426bbbb78eccd2c79","verification_method":"Email","notification_methods":["Email"],"step":1}
  ],
  "message": "Please review and sign the attached agreement.",
  "expires_at": "2026-12-31T23:59:59Z"
}
```

```json
{
  "status": 200, "message": "",
  "data": {
    "resource": "assignment",
    "id": "1a09c15990f0144256b98ff38aa",
    "sender_email": "sender@example.com",
    "method": "virtual",
    "expires_at": "2026-12-31T23:59:59Z",
    "message": "Please review and sign the attached agreement.",
    "signers": [
      {
        "id": "19e6b92e7895332ed9708535d8c",
        "full_name": "Jane Doe",
        "email": "jane@example.com",
        "whatsapp_phone_number": null,
        "government_id": null,
        "has_accepted_terms": true,
        "completed": false,
        "notification_history": [
          {"event":"signature_request","status":"sent","error_code":null,
           "error_message":null,"sent_at":"2026-09-13T18:44:16Z","failed_at":null}
        ],
        "verification_method": "Email",
        "notification_methods": ["Email"],
        "step": 1,
        "notified": true
      },
      {
        "id": "103033c9cd9426bbbb78eccd2c79",
        "full_name": "John Roe",
        "email": "john@example.com",
        "whatsapp_phone_number": null,
        "government_id": null,
        "has_accepted_terms": false,
        "completed": false,
        "notification_history": [
          {"event":"signature_request","status":"sent","error_code":null,
           "error_message":null,"sent_at":"2026-09-13T18:44:17Z","failed_at":null}
        ],
        "verification_method": "Email",
        "notification_methods": ["Email"],
        "step": 1,
        "notified": true
      }
    ],
    "copy_receivers": [],
    "items": [
      {
        "id": "104618d7fae557b29898ca4475b7",
        "page": null,
        "signer": {"id":"19e6b92e7895332ed9708535d8c","full_name":"Jane Doe",
                   "email":"jane@example.com","whatsapp_phone_number":null,
                   "government_id":null,"has_accepted_terms":true},
        "field": {"id":"102d25a48bc7357b93f9b8e01b24","name":"Virtual","type":"virtual",
                  "regex":null,"is_pre_defined":true,"is_active":true,"is_required":false,
                  "is_standard":false,"is_read_only":false,"is_visible":true},
        "display_settings": [],
        "value": null,
        "completed": false
      }
    ],
    "summary": {
      "signer_count": 2,
      "completed_count": 0,
      "signers": [
        {"id":"19e6b92e7895332ed9708535d8c","full_name":"Jane Doe","email":"jane@example.com",
         "whatsapp_phone_number":null,"government_id":null,"has_accepted_terms":true,"completed":false},
        {"id":"103033c9cd9426bbbb78eccd2c79","full_name":"John Roe","email":"john@example.com",
         "whatsapp_phone_number":null,"government_id":null,"has_accepted_terms":false,"completed":false}
      ]
    },
    "signing_urls": [
      {"signer_id":"19e6b92e7895332ed9708535d8c",
       "url":"https://app-sandbox.assinafy.com.br/sign/104618d0d63884bc446c534e5ff5?email=jane%40example.com"},
      {"signer_id":"103033c9cd9426bbbb78eccd2c79",
       "url":"https://app-sandbox.assinafy.com.br/sign/104618d0d63884bc446c534e5ff5?email=john%40example.com"}
    ]
  }
}
```

Pontos que vale ter em mente ao ler esse payload em outro lugar:

- `items[].display_settings` é um **array** JSON `[]` para virtual e um **objeto** para collect. A
  mesma chave muda de tipo; leia-a com acesso por array e valores padrão com `??`.
- `items[].page` é `null` para virtual.
- O campo `virtual` é anexado automaticamente e nunca aparece na lista de campos da conta.
- O plugin não expõe opção de cópia/CC; seu contrato de envio especifica apenas signatários.
- `assignment.summary` é a leitura de progresso mais barata:
  `{signer_count, completed_count, signers[]}`.
- `step` controla a ordem. Etapas iguais assinam em paralelo; uma etapa maior só é notificada
  depois que todas as etapas menores forem concluídas. As etapas precisam ser contíguas a partir de
  1, e ou todos os signatários especificam uma, ou nenhum especifica.

O plugin então mescla o assignment sob a chave `assignment` do documento — o mesmo formato que
`GET /documents/{id}` retorna — para que o espelho local seja hidratado a partir de um formato só,
e grava o registro.

### Passo 5 — Expor os links de assinatura

```
signing_urls[] = [{ "signer_id": "…", "url": "…" }]
url            = https://app.assinafy.com.br/sign/{documentId}?email={urlencoded signer email}
```

- O segmento do caminho é o **id do documento**, não o id do assignment e não um token por
  signatário.
- O único discriminador por signatário é o parâmetro de query `email`.
- O host é o **app web**, não o host da API.
- **Leia a URL na resposta. Nunca a construa.**
- `signing_urls` contém apenas signatários que já foram notificados. Com etapas sequenciais, os
  signatários de etapas posteriores têm `notified: false` e ainda não têm URL, e a meta box do
  documento mostra "ainda não convidado" em vez de um link morto.

O plugin apenas leva às páginas de assinatura hospedadas pela Assinafy. Ele não implementa
assinatura embarcada, autenticação de signatário, recusa de signatário nem preenchimento de campos.
Ele lê as URLs de assinatura da API e as expõe pelos fluxos documentados do admin e do WooCommerce.

### Passo 6 — Acompanhar o progresso

A reconciliação periódica e as dicas opcionais de webhook compartilham o mesmo caminho autoritativo
de releitura.

**O cron horário de reconciliação** (`assinafy_reconcile` →
[`StatusSync::reconcile()`](../src/Documents/StatusSync.php)) pega até 20 registros cujo
`_assinafy_is_closed` é falso, do `_assinafy_synced_at` mais antigo em diante, e relê cada um. Ele
para antes da hora quando o orçamento de requisições registrado está abaixo de 30, para deixar
capacidade às requisições em primeiro plano. Isso é um corte consultivo, não uma cota reservada ou
atômica para toda a conta.

**Uma entrega de webhook** faz exatamente a mesma coisa — veja a [§7](../README.md#7-webhooks). O corpo da
entrega nunca é gravado no estado local; ele é uma dica que dispara a mesma releitura.

Ambos passam por uma única chamada:

```http
GET /v1/documents/104618b275d321f5de22240ebfda
X-Api-Key: {API_KEY}
Accept: application/json
```

```json
{
  "status": 200, "message": "",
  "data": {
    "resource": "document",
    "id": "104618b275d321f5de22240ebfda",
    "account_id": "{ACCOUNT_ID}",
    "template_id": null,
    "name": "contract.pdf",
    "status": "pending_signature",
    "artifacts": {
      "original":  "https://sandbox.assinafy.com.br/v1/documents/104618b2…/download/original",
      "thumbnail": "https://sandbox.assinafy.com.br/v1/documents/104618b2…/thumbnail"
    },
    "is_closed": false,
    "signing_url": "https://app-sandbox.assinafy.com.br/sign/104618b275d321f5de22240ebfda",
    "decline_reason": null,
    "declined_by": null,
    "tags": [],
    "created_at": "2026-09-13T18:40:10Z",
    "updated_at": "2026-09-13T18:40:12Z",
    "assignment": {
      "id": "1a09c15990f0144256b98ff38aa", "method": "virtual",
      "sender_email": "sender@example.com", "expires_at": "2026-12-31T23:59:59Z",
      "message": "Please review and sign.", "copy_receivers": [], "items": [],
      "signers": [{"id":"19e6b92e7895332ed9708535d8c","full_name":"Jane Doe",
                   "email":"jane@example.com","completed":false,"step":1,"notified":true}],
      "summary": {"signer_count":1,"completed_count":0,"signers":[]},
      "signing_urls": [{"signer_id":"19e6b92e7895332ed9708535d8c",
        "url":"https://app-sandbox.assinafy.com.br/sign/104618b2…?email=jane%40example.com"}]
    },
    "pages": [{"id":"104618b2ac6a3b2f99d0fd2c5f43","number":1,"height":1755,"width":1240,
               "download_url":"https://sandbox.assinafy.com.br/v1/documents/104618b2…/pages/104618b2…/download"}]
  }
}
```

Uma única chamada recupera tudo sobre uma solicitação de assinatura. Não existe endpoint de
detalhe do assignment, e `?expand=assignment` não faz nada — `assignment` e `pages` sempre vêm
embutidos.

A tela de edição do documento também renderiza o feed de atividades daquele documento, direto da
API, e é por isso que o plugin não mantém tabela de auditoria local:

```http
GET /v1/documents/104618b275d321f5de22240ebfda/activities
X-Api-Key: {API_KEY}
```

```json
{
  "status": 200, "message": "",
  "data": [
    { "id": 17172, "event": "signature_requested",
      "message": "Solicitação para assinar enviada para Jane Doe <jane@example.com>.",
      "payload": { "signer_email": "jane@example.com", "signer_full_name": "Jane Doe",
                   "notification_method": "email", "signer_whatsapp_phone_number": null },
      "origin": null, "created_at": "2026-07-28T19:33:56Z" },
    { "id": 17171, "event": "assignment_created",
      "message": "Solicitação de assinatura criada por Acme Test.",
      "payload": { "user_name": "Acme Test", "user_email": "owner@example.com", "user_telephone": null },
      "origin": { "ip": "203.0.113.20", "user-agent": null },
      "created_at": "2026-07-28T19:33:56Z" },
    { "id": 17169, "event": "document_metadata_ready",
      "message": "Documento processado.", "payload": [], "origin": null,
      "created_at": "2026-07-28T19:33:53Z" },
    { "id": 17168, "event": "document_uploaded",
      "message": "Documento criado.", "payload": [],
      "origin": { "ip": "203.0.113.20", "user-agent": null },
      "created_at": "2026-07-28T19:33:50Z" }
  ]
}
```

Mais recentes primeiro, sem paginação alguma (`page` e `per-page` são ignorados), `message` é prosa
pt-BR já renderizada, `origin` é `null` para eventos do sistema. **`payload` é um array JSON `[]`
quando o evento não tem chaves de payload e um objeto quando tem** — o que em PHP é `[]` versus
`['signer_email' => …]`. Código tipado como objeto quebra em `document_uploaded`,
`document_metadata_ready` e `document_prepared`; a meta box lê o valor como `['key'] ?? null`.

### Passo 7 — Entregar o PDF assinado

Assim que o documento chega a `certificated`, seu mapa `artifacts` ganha `certificated`,
`certificate-page` e `bundle` (mais `pades` quando um signatário ICP-Brasil participou). A UI do
documento linka os artefatos disponíveis a partir desse mapa, excluindo `thumbnail`. O proxy
serve apenas `original`, `certificated`, `certificate-page`, `pades` e `bundle`.

```http
GET /v1/documents/104618b275d321f5de22240ebfda/download/certificated
X-Api-Key: {API_KEY}
```

```http
HTTP/2 200
content-type: application/pdf
content-length: 1146
content-disposition: attachment; filename="contract.pdf"
cache-control: must-revalidate, post-check=0, pre-check=0
accept-ranges: bytes

%PDF-1.4 …
```

A API devolve os bytes do artefato diretamente. O
[`DownloadProxy`](../src/Documents/DownloadProxy.php) os busca no servidor usando a chave de API da
conta, nesta ordem:

1. `current_user_can( 'assinafy_view', $post_id )`
2. `check_admin_referer()`
3. o nome do artefato é resolvido **contra os nomes armazenados no registro**
4. `documents()->download()`
5. cabeçalhos, bytes, `exit`

O proxy envia artefatos PDF como `application/pdf` e bundles como `application/zip`, com a extensão
correspondente, cabeçalhos de no-cache e `X-Content-Type-Options: nosniff`.

A capability vem primeiro, deliberadamente: um visitante que não pode ver o registro é barrado
antes que a resposta revele se o registro existe.

Sondar a API em busca de artefatos disponíveis não diz nada. Todo nome indisponível **e** todo nome
inventado retornam a resposta idêntica:

```json
{"status":404,"data":null,"message":"Artefato não está disponível."}
```

Não há como distinguir "ainda não gerado" de "não existe esse artefato", e é por isso que a UI de
download é guiada pelo mapa `artifacts` e o proxy responde com um único 404 para nome errado,
artefato ausente, registro desconhecido e credenciais ausentes, tudo igual — nada sobre o estado do
documento vaza por um link de download.

`thumbnail` é uma chave no mapa `artifacts`, mas **não** é um nome válido na rota de download; ele
tem o próprio endpoint. A lista de permitidos do proxy o exclui.
