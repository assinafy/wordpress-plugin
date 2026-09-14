# The document flow, step by step

*[Leia em português](document-flow.md) · English*

This is the plugin's primary path: **method `virtual`, email verification, all signers in
parallel on step 1.** Steps 0–4 run through
[`SendService::send()`](../src/Documents/SendService.php). Links, later synchronization and
downloads use the resulting local mirror. Admin, hook, WooCommerce, CLI and cron callers
share the same send service.

### Step 0 — Validate locally, before spending a request

Core validates the optional source, credentials, file selection, signer identities,
notification channels, signing steps and deadline before sending HTTP requests. It then
uses `DocumentResource::assertUploadable()`, which checks: the file exists → the
extension is `.pdf` → it is readable → `fopen` succeeds → size > 0 → size ≤ 25 MB → the first
1024 bytes match `/%PDF-\d\.\d/` → the trailing 1024 bytes contain `%%EOF`.

Local validation reduces the chance of consuming an upload allowance on malformed content.
It checks the filename and PDF header/trailer signatures; the remote service still makes
its own decision and can reject a locally valid file. An API failure without a document ID
can leave an uncertain outcome, so it must not trigger an automatic new upload.

An SDK PDF-check failure returns `WP_Error( 'assinafy_invalid_pdf', … )`; source, attachment,
signer and expiry validation have their own error codes. These failures produce no upload.

A native database lock serializes each send key. A local reservation and its optional
immutable source are saved before upload; the remote document ID is saved before assignment.
The key remains on the mirror after its five-minute transient expires. Assigned records
return their existing local ID; incomplete sends re-fetch the saved upload and create an
assignment only if one is missing. A reservation with no known remote ID stops for operator
review because its upload outcome may be unknown. Cost estimation runs for new reservations.

### Step 1 — Price the send, and read the verdict from the body

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

A WhatsApp-notified signer prices differently, and an account with no credits refuses — **at
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

**Read `has_sufficient_resources` even on HTTP 200, and also handle HTTP/API errors.** `blocking_reason` is one of
`PendingPayment`, `InsufficientDocuments` or `InsufficientCredits`, and each maps to a
specific administrator-facing message.

The one refusal that *does* use a status code is a plan-gated verification method:
`DigitalCertificate` on an account without the feature answers 403
`"A assinatura com certificado digital não está disponível para o seu plano atual."` —
before any document is consumed, which is exactly why the estimate runs before the upload.

Signer ids are not needed here: only the method mix is priced. The route is addressed to a
document, though, so the plugin uses the most recently mirrored document id as a stand-in —
balance and plan are account-wide, so any document of the account answers the same question.
A site that has never sent anything has no such id and gets `assinafy_estimate_unavailable`,
which **never blocks a send**: a pricing probe that could not run is not a reason to refuse.

### Step 2 — Resolve each signer: look up, then create

`email` is unique per account, so creating a signer with an address that already exists is a
hard 400 `"Um signatário com este e-mail já existe."` A second submission from the same
person fails unless you look up first.

```http
GET /v1/accounts/{ACCOUNT_ID}/signers?search=jane%40example.com&page=1&per-page=100
X-Api-Key: {API_KEY}
```

`search` is a **substring** match over `full_name` OR `email`, so it happily returns
`jane@example.com.test` for `jane@example.com`. The SDK's `signers()->findByEmail()` runs the
paged, case-insensitive exact scan this needs; the plugin uses it rather than re-implementing
the comparison.

On a miss:

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

Field rules, verified against the live API:

- Only `full_name` is required.
- `whatsapp_phone_number` is normalised to E.164 with `+55` assumed: `"48999990000"` comes
  back as `"+5548999990000"`, `"+55 48 99999-0001"` as `"+5548999990001"`. Too short is a 400
  `"Por favor, insira um número de telefone válido."` The SDK is stricter than the API here —
  it requires a leading `+` and country code locally — so the plugin prepends `+55` to bare
  10- and 11-digit national numbers before handing them over.
- `government_id` takes a CPF or a CNPJ in one field, punctuation stripped
  (`"390.533.447-05"` → `"39053344705"`), with real check-digit validation
  (`"11111111111"` → 400 `"CPF inválido."`).

### Step 3 — Upload the PDF

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

Details that change the implementation:

- HTTP **200**, not 201. No `Location` header.
- The multipart part name must be exactly `file`. Anything else is a 400
  `"O parâmetro \"file\" não está presente."`
- An extra `name=` form field is **silently ignored** — the document takes the filename.
- The filename is **normalised server-side**: diacritics folded to ASCII, every unsupported
  character replaced by one `-`. `"Contrato Ação & Nº2 (final).pdf"` becomes
  `"Contrato Acao - N-2 -final-.pdf"`. Letters, digits, spaces, `.` and `-` survive. The
  plugin stores the returned `data.name`, not the name it sent, which is why the post title
  may differ from the media library filename.

**No wait for `metadata_ready`.** Step 4 runs immediately. The local reservation is created before upload, and the returned document id is stored before assignment.

### Step 4 — Create the assignment

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

Notes worth keeping in mind when you read this payload elsewhere:

- `items[].display_settings` is a JSON **array** `[]` for virtual and an **object** for
  collect. The same key changes type; read it with array access and `??` defaults.
- `items[].page` is `null` for virtual.
- The `virtual` field is attached automatically and never appears in the account's field list.
- The plugin exposes no CC/copy-receiver option; its send contract specifies signers only.
- `assignment.summary` is the cheapest progress read: `{signer_count, completed_count, signers[]}`.
- `step` controls order. Equal steps sign in parallel; a higher step is notified only once
  every lower step has completed. Steps must be contiguous from 1, and either every signer
  specifies one or none does.

The plugin then merges the assignment under the document's `assignment` key — the same shape
`GET /documents/{id}` returns — so the local mirror hydrates from one shape only, and writes
the record.

### Step 5 — Surface the signing links

```
signing_urls[] = [{ "signer_id": "…", "url": "…" }]
url            = https://app.assinafy.com.br/sign/{documentId}?email={urlencoded signer email}
```

- The path segment is the **document id**, not the assignment id and not a per-signer token.
- The only per-signer discriminator is the `email` query parameter.
- The host is the **web app**, not the API host.
- **Read the URL from the response. Never construct it.**
- `signing_urls` contains only signers who have already been notified. Under sequential steps,
  later-step signers have `notified: false` and no URL yet, and the document meta box shows
  "not yet invited" rather than a dead link.

The plugin links to Assinafy-hosted signing pages. It does not implement embedded signing,
signer authentication, signer declines or field submission. It reads signing URLs from the
API and exposes them through the documented admin and WooCommerce flows.

### Step 6 — Track progress

Periodic reconciliation and optional webhook hints share the authoritative re-fetch path.

**The hourly reconcile cron** (`assinafy_reconcile` →
[`StatusSync::reconcile()`](../src/Documents/StatusSync.php)) picks up to 20 records whose
`_assinafy_is_closed` is false, oldest `_assinafy_synced_at` first, and re-reads each one. It
stops early when the recorded rate budget is below 30, to leave capacity for foreground
requests. This is an advisory cutoff, not a reserved or atomic account-wide quota.

**A webhook delivery** does exactly the same thing — see [§7](../README.en.md#7-webhooks). The delivery body
is never written to local state; it is a hint that triggers the same re-fetch.

Both go through one call:

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

One call recovers everything about a signature request. There is no assignment-detail
endpoint, and `?expand=assignment` is a no-op — `assignment` and `pages` are always embedded.

The document edit screen also renders the per-document activity feed, straight from the API,
which is why the plugin keeps no local audit table:

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

Newest first, no pagination at all (`page` and `per-page` are ignored), `message` is
pre-rendered pt-BR prose, `origin` is `null` for system events. **`payload` is a JSON array
`[]` when the event has no payload keys and an object when it does** — which in PHP is `[]`
versus `['signer_email' => …]`. Code typed to an object breaks on `document_uploaded`,
`document_metadata_ready` and `document_prepared`; the meta box reads it as `['key'] ?? null`.

### Step 7 — Deliver the signed PDF

Once the document reaches `certificated`, its `artifacts` map gains `certificated`,
`certificate-page` and `bundle` (plus `pades` when an ICP-Brasil signer participated). The
document UI links available artifacts from that map, excluding `thumbnail`. The proxy serves
only `original`, `certificated`, `certificate-page`, `pades` and `bundle`.

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

The API returns artifact bytes directly. [`DownloadProxy`](../src/Documents/DownloadProxy.php)
fetches them server-side using the account API key, in this order:

1. `current_user_can( 'assinafy_view', $post_id )`
2. `check_admin_referer()`
3. the artifact name is resolved **against the names stored on the record**
4. `documents()->download()`
5. headers, bytes, `exit`

The proxy sends PDF artifacts as `application/pdf` and bundles as `application/zip`, with
the matching extension, no-cache headers and `X-Content-Type-Options: nosniff`.

Capability first, deliberately: a visitor who may not view the record is turned away before
the response reveals whether the record exists.

Probing the API for available artifacts tells you nothing. Every unavailable **and** every
invented name returns the identical response:

```json
{"status":404,"data":null,"message":"Artefato não está disponível."}
```

There is no way to distinguish "not generated yet" from "no such artifact", which is why the
download UI is driven from the `artifacts` map and the proxy answers with one 404 for wrong
name, missing artifact, unknown record and absent credentials alike — nothing about the
document's state leaks through a download link.

`thumbnail` is a key in the `artifacts` map but is **not** a valid name on the download
route; it has its own endpoint. The proxy's allow-list excludes it.
