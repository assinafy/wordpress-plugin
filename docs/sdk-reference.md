# Bundled PHP SDK reference

This reference describes the SDK installed by the plugin (2.4.2). Obtain the configured client through `ClientFactory::client()`. WordPress uses `WpHttpClient`, including credential isolation and encrypted OAuth renewal. The SDK default Guzzle transport is unavailable in the plugin distribution.

For OAuth discovery, obtain the resource through `OAuthTokens::oauth()`. It supplies a credential-free WordPress transport for each discovery origin; constructing the SDK OAuth resource without this discovery factory would use the unavailable default transport.

The [REST payload reference](api-payloads.md) includes the complete shared request and response schemas for every published operation. Ordinary resource methods generally unwrap `data`; list methods retain the envelope and pagination. OAuth responses are flat JSON. Helpers that perform no network call document their local input and return value.

## `AssinafyClient`

### `__construct()`

```php
public function __construct(
        #[\SensitiveParameter] Configuration $config,
        ?HttpClientInterface $httpClient = null,
        ?LoggerInterface $logger = null
    )
```

Construct a client without making an HTTP request.

Uses the supplied configuration, an optional replacement transport and an optional
PSR-3 logger. Omitted dependencies become GuzzleHttpClient and NullLogger.
Obtain typed resources through the accessors; they share this configuration.

### `create()`

```php
public static function create(
        #[\SensitiveParameter] string $apiKey,
        string $accountId,
        string $baseUrl = Configuration::DEFAULT_BASE_URL
    ) : self
```

Create a workspace client using an API key; no network request is made.

Example: `AssinafyClient::create($apiKey, $accountId, Configuration::SANDBOX_BASE_URL)`.
Returns a configured client. Invalid credentials, account IDs or URLs throw
InvalidArgumentException through Configuration validation.

### `fromArray()`

```php
public static function fromArray(#[\SensitiveParameter] array $config) : self
```

Build a client from the keys accepted by Configuration::fromArray(), without HTTP I/O.

Input example: `['api_key' => '<api-key>', 'account_id' => 'account-id',
'base_url' => Configuration::SANDBOX_BASE_URL, 'timeout' => 30, 'connect_timeout' => 10]`.
Returns a configured client; the configuration validates types and required values.

@param array<string, mixed> $config

### `forAuth()`

```php
public static function forAuth(string $baseUrl = Configuration::DEFAULT_BASE_URL) : self
```

Build a client for the unauthenticated surface of the API — the place where
you don't yet have an API key.

Lets you call `$client->auth()->login(...)`, `requestPasswordReset(...)`,
`resetPassword(...)`, `socialLogin(...)`, and the public document endpoints
(`verify`, `publicInfo`, `sendToken`) without having to fabricate credentials
just to satisfy the Configuration constructor.

Calling an account-scoped resource on a public client (e.g. `$client->signers()->list()`)
raises a `\RuntimeException` with a clear message — see {@see Configuration::forPublic()}.

### `forBearer()`

```php
public static function forBearer(
        #[\SensitiveParameter] string $accessToken,
        string $accountId,
        string $baseUrl = Configuration::DEFAULT_BASE_URL
    ) : self
```

Build an account-scoped client authenticated with a Bearer access token.

### `accounts()`

```php
public function accounts() : AccountResource
```

Accounts (workspaces).

`accounts()->list()` and `accounts()->create()` are not account-scoped. On a client built
with {@see self::forAuth()}, pass the Bearer token returned by `auth()->login()`. The
remaining methods act on the configured account using its API key or global Bearer token.

### `documents()`

```php
public function documents() : DocumentResource
```

Documents: upload, list, search, rename, download, tag, and delete.

The heart of the SDK. A document is uploaded, processed asynchronously into pages,
then assigned for signature via {@see self::assignments()}. Account-scoped.

### `signers()`

```php
public function signers() : SignerResource
```

Signers: the address book of people who can be asked to sign.

Signers are workspace-level and reusable across documents; create one once and
reference its id in every assignment. Account-scoped.

### `assignments()`

```php
public function assignments() : AssignmentResource
```

Assignments: request signatures on a document and manage those requests.

Create binds signers to a document and notifies them; the rest of the resource
estimates cost, resends notifications, and extends deadlines.

### `templates()`

```php
public function templates() : TemplateResource
```

Templates: reusable documents with named roles bound to signers at creation time.

Note that a template created through the API receives only an `Editor` role;
signing roles are configured in the web app. Account-scoped.

### `tags()`

```php
public function tags() : TagResource
```

Tags: workspace labels that can be attached to documents for filtering.

Attach and detach them through {@see DocumentResource::appendTags()} and friends.
Account-scoped.

### `fields()`

```php
public function fields() : FieldResource
```

Fields: custom and standard data captured from signers during signing.

Covers field definitions, their types, and server-side value validation.
Account-scoped.

### `webhooks()`

```php
public function webhooks() : WebhookResource
```

Webhooks: the workspace's single event subscription and its delivery history.

Deliveries are unsigned — see {@see self::webhookEvents()} for how to handle that.
Account-scoped.

### `auth()`

```php
public function auth() : AuthResource
```

Authentication: login, social login, API-key management, and password flows.

Mostly used on a client built with {@see self::forAuth()}, before any workspace
credential exists.

### `oauth()`

```php
public function oauth(
        string $clientId,
        #[\SensitiveParameter] ?string $clientSecret = null
    ) : OAuthResource
```

Marketplace OAuth: the authorization-code + PKCE flow for acting on **another**
workspace, plus token exchange, refresh, revocation, userinfo and discovery.

Automating your own workspace needs none of this — keep using an API key.
Usually called on a {@see self::forAuth()} client, since the flow runs before any
workspace credential exists:

```php
$oauth = AssinafyClient::forAuth()->oauth($clientId, $clientSecret);
```

Unlike the other accessors this one is not memoized: the resource is a small
stateless object and the credentials are arguments, so caching it would only
create a stale-secret footgun.

@param string      $clientId     the application's `client_id` from the Assinafy app
@param string|null $clientSecret confidential applications only; public applications
    authenticate with PKCE and are never issued a secret
@throws \Assinafy\SDK\Exceptions\ValidationException on an empty client ID or a
    present-but-blank secret

### `signerSession()`

```php
public function signerSession() : SignerSessionResource
```

The signer's own session: everything a recipient does with their access code.

Accept terms, verify a one-time code, confirm data, upload a signature image,
then sign or decline. Authenticated by the signer access code, not the API key.

### `signerDocuments()`

```php
public function signerDocuments() : SignerDocumentResource
```

A signer's view of the documents assigned to them.

Read-only listing, search, and download, plus bulk sign/decline. Authenticated by
the signer access code, not the API key.

### `users()`

```php
public function users() : UserResource
```

Authenticated user profile and cross-account KPI endpoints.

### `webhookEvents()`

```php
public function webhookEvents() : WebhookEventParser
```

Helpers for decoding incoming webhook deliveries.

The webhook contract provides no signing secret or signature header. Secure the
endpoint as described by {@see WebhookEventParser}.

### `uploadAndRequestSignatures()`

```php
public function uploadAndRequestSignatures(
        #[\SensitiveParameter] string $filePath,
        #[\SensitiveParameter] array $signers,
        #[\SensitiveParameter] ?string $message = null,
        ?string $expiresAt = null,
        bool $waitForReady = true
    ) : array
```

High-level helper: upload a PDF, create signers if needed, then dispatch a virtual
assignment to all of them.

Each entry in `$signers` may be either:
  - an existing signer ID (string), or
  - an associative array `{ id? or full_name (or name), email?, whatsapp_phone_number?
    (or phone)?, verification_method?, notification_methods?, step? }`

Signers without an `id` are created via the API; signers found by email (when an email
is supplied) are reused. DigitalCertificate entries must instead supply an existing
signer ID whose government_id was set first. Returns the created document, the assignment,
and the resolved signer IDs.

SDK input (the helper composes multipart and JSON requests):
```php
[
    'filePath' => '/absolute/path/agreement.pdf',
    'signers' => [
        [
            'full_name' => 'Example Signer',
            'email' => 'person@example.com',
            'verification_method' => 'Email',
            'notification_methods' => ['Email'],
            'step' => 1,
        ],
    ],
    'message' => null,
    'expiresAt' => null,
    'waitForReady' => true,
]
```

Full return example:
```php
[
    'document' => [
        'resource' => 'document',
        'id' => 'document-id',
        'account_id' => 'account-id',
        'template_id' => null,
        'name' => 'agreement.pdf',
        'status' => 'metadata_ready',
        'artifacts' => [
            'original' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/download/original',
            'thumbnail' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/thumbnail',
        ],
        'is_closed' => false,
        'signing_url' => 'https://app-sandbox.assinafy.com.br/sign/signing-token',
        'decline_reason' => null,
        'declined_by' => null,
        'tags' => [],
        'created_at' => '2026-09-01T12:00:00Z',
        'updated_at' => '2026-09-01T12:00:00Z',
        'assignment' => null,
        'pages' => [
            [
                'id' => 'page-id',
                'number' => 1,
                'height' => 1651,
                'width' => 1275,
                'download_url' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/pages/page-id/download',
            ],
        ],
    ],
    'assignment' => [
        'resource' => 'assignment',
        'id' => 'assignment-id',
        'sender_email' => 'person@example.com',
        'method' => 'virtual',
        'expires_at' => null,
        'message' => null,
        'signers' => [
            [
                'id' => 'signer-id',
                'full_name' => 'Example Signer',
                'email' => 'person@example.com',
                'whatsapp_phone_number' => null,
                'government_id' => null,
                'has_accepted_terms' => false,
                'completed' => false,
                'notification_history' => [
                    [
                        'event' => 'signature_request',
                        'status' => 'sent',
                        'error_code' => null,
                        'error_message' => null,
                        'sent_at' => '2026-09-01T12:00:00Z',
                        'failed_at' => null,
                    ],
                ],
                'verification_method' => 'Email',
                'notification_methods' => ['Email'],
                'step' => 1,
                'notified' => true,
            ],
        ],
        'copy_receivers' => [],
        'items' => [
            [
                'id' => 'assignment-item-id',
                'page' => null,
                'signer' => [
                    'id' => 'signer-id',
                    'full_name' => 'Example Signer',
                    'email' => 'person@example.com',
                    'whatsapp_phone_number' => null,
                    'government_id' => null,
                    'has_accepted_terms' => false,
                ],
                'field' => [
                    'id' => 'field-id',
                    'name' => 'Virtual',
                    'type' => 'virtual',
                    'regex' => null,
                    'is_pre_defined' => true,
                    'is_active' => true,
                    'is_required' => false,
                    'is_standard' => false,
                    'is_read_only' => false,
                    'is_visible' => true,
                ],
                'display_settings' => [],
                'value' => null,
                'completed' => false,
            ],
        ],
        'summary' => [
            'signer_count' => 1,
            'completed_count' => 0,
            'signers' => [
                [
                    'id' => 'signer-id',
                    'full_name' => 'Example Signer',
                    'email' => 'person@example.com',
                    'whatsapp_phone_number' => null,
                    'government_id' => null,
                    'has_accepted_terms' => false,
                    'completed' => false,
                ],
            ],
        ],
        'signing_urls' => [
            [
                'signer_id' => 'signer-id',
                'url' => 'https://app-sandbox.assinafy.com.br/sign/signing-token?email=signer%40example.com',
            ],
        ],
    ],
    'signer_ids' => ['signer-id'],
]
```

The returned document is the preparation snapshot, before assignment creation.
Fetch get() for its current signing state. A later failure does not roll back objects
created by earlier steps; use separate calls when each ID must be saved for recovery.

@param array<int, string|array<string, mixed>> $signers
@return array{document: array<string, mixed>, assignment: array<string, mixed>, signer_ids: array<int, string>}

### `getConfig()`

```php
public function getConfig() : Configuration
```

The immutable configuration this client was built with.

### `getHttpClient()`

```php
public function getHttpClient() : HttpClientInterface
```

The transport in use — the injected client, or the SDK's Guzzle default.

### `getLogger()`

```php
public function getLogger() : LoggerInterface
```

The PSR-3 logger currently receiving request and error diagnostics.

### `setLogger()`

```php
public function setLogger(LoggerInterface $logger) : self
```

Swap the logger after construction.

Takes effect immediately on resources that were already created: they hold a shared
proxy rather than the logger itself.

## `Configuration`

### `__construct()`

```php
public function __construct(
        #[\SensitiveParameter] string $apiKey,
        string $accountId,
        string $baseUrl = self::DEFAULT_BASE_URL,
        int $timeout = 30,
        int $connectTimeout = 10,
        #[\SensitiveParameter] ?string $accessToken = null
    )
```

Validate and store connection settings without making a request.

Supply a non-empty API key and account ID, or use forBearer()/forPublic().
Remote URLs require HTTPS; timeout values are positive seconds. Configuration
owns authentication and User-Agent headers. Example input:
`new Configuration($apiKey, $accountId, self::SANDBOX_BASE_URL, 30, 10)`.

@throws \InvalidArgumentException on invalid or conflicting configuration

### `fromArray()`

```php
public static function fromArray(#[\SensitiveParameter] array $config) : self
```

@param array<string, mixed> $config keys: `api_key`/`apiKey`, `account_id`/`accountId`,
    `access_token`/`accessToken`, `base_url`/`baseUrl`, `timeout`,
    `connect_timeout`/`connectTimeout`.
    A `webhook_secret` key is accepted and ignored — see {@see \Assinafy\SDK\Support\WebhookEventParser}.

### `forPublic()`

```php
public static function forPublic(string $baseUrl = self::DEFAULT_BASE_URL) : self
```

Configuration for the unauthenticated surface of the API.

Use this when bootstrapping a session — e.g. before you have an API key:

```php
$client = new AssinafyClient(Configuration::forPublic());
$session = $client->auth()->login('user@example.com', 'secret');
```

The sentinel values are never sent over the network. Account-scoped resources
fail with a clear runtime error if called on a public configuration.

### `forBearer()`

```php
public static function forBearer(
        #[\SensitiveParameter] string $accessToken,
        string $accountId,
        string $baseUrl = self::DEFAULT_BASE_URL,
        int $timeout = 30,
        int $connectTimeout = 10
    ) : self
```

Configure all workspace resources with OAuth/Bearer authentication instead
of an API key.

### `isPublic()`

```php
public function isPublic() : bool
```

True when built by {@see self::forPublic()} — no credential is sent.

### `isBearerAuthenticated()`

```php
public function isBearerAuthenticated() : bool
```

True when requests carry an `Authorization: Bearer` header instead of `X-Api-Key`.

### `getBaseUrl()`

```php
public function getBaseUrl() : string
```

The API root, without a trailing slash (e.g. `https://api.assinafy.com.br/v1`).

### `getApiKey()`

```php
public function getApiKey() : string
```

The raw API key. Empty on a Bearer configuration; a sentinel on a public one.

### `getAccessToken()`

```php
public function getAccessToken() : ?string
```

The Bearer access token, or null when authenticating with an API key.

### `getAccountId()`

```php
public function getAccountId() : string
```

The workspace id account-scoped routes are built from.

### `getTimeout()`

```php
public function getTimeout() : int
```

Total per-request timeout, in seconds.

### `getConnectTimeout()`

```php
public function getConnectTimeout() : int
```

Connection-establishment timeout, in seconds.

### `getHeaders()`

```php
public function getHeaders() : array
```

Default transport headers.

@return array<string, string>

### `__debugInfo()`

```php
public function __debugInfo() : array
```

Keep credentials and workspace identifiers out of diagnostic object dumps.

@return array{base_url: string, authentication: string, account_id: string,
    timeout: int, connect_timeout: int}

## `Exceptions/ApiException`

### `__construct()`

```php
public function __construct(
        string $message,
        int $statusCode,
        #[\SensitiveParameter] ?array $responseData = null,
        ?\Throwable $previous = null,
        array $responseHeaders = []
    )
```

@param array<string, mixed>|null $responseData
@param array<array-key, array<int, string>|string> $responseHeaders

### `getStatusCode()`

```php
public function getStatusCode() : int
```

The HTTP status code the API answered with.

### `getResponseData()`

```php
public function getResponseData() : ?array
```

@return array<string, mixed>|null

### `getResponseHeaders()`

```php
public function getResponseHeaders() : array
```

Response headers retained for request IDs, pagination diagnostics, rate limits,
and `Retry-After` handling.

@return array<string, list<string>>

### `getResponseHeaderLine()`

```php
public function getResponseHeaderLine(string $name) : string
```

One response header, comma-joined, matched case-insensitively; `''` when absent.

### `fromResponse()`

```php
public static function fromResponse(
        int $statusCode,
        #[\SensitiveParameter] array $responseData,
        ?\Throwable $previous = null,
        array $responseHeaders = []
    ) : self
```

@param array<string, mixed> $responseData
@param array<array-key, array<int, string>|string> $responseHeaders

## `Exceptions/AssinafyException`

### `__construct()`

```php
public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        #[\SensitiveParameter] array $context = []
    )
```

@param array<string, mixed> $context

### `getContext()`

```php
public function getContext() : array
```

@return array<string, mixed>

### `setContext()`

```php
public function setContext(#[\SensitiveParameter] array $context) : self
```

@param array<string, mixed> $context

## `Exceptions/ValidationException`

### `__construct()`

```php
public function __construct(
        string $message = 'Validation failed',
        #[\SensitiveParameter] array $errors = [],
        int $code = 422
    )
```

@param array<array-key, mixed> $errors

### `getErrors()`

```php
public function getErrors() : array
```

@return array<array-key, mixed>

### `fromArray()`

```php
public static function fromArray(#[\SensitiveParameter] array $errors) : self
```

@param array<array-key, mixed> $errors

## `Http/GuzzleHttpClient`

### `__construct()`

```php
public function __construct(
        #[\SensitiveParameter] Configuration $config,
        ?LoggerInterface $logger = null,
        #[\SensitiveParameter] ?ClientInterface $client = null
    )
```

@param ClientInterface|null $client Pre-built Guzzle client. Leave null in production —
    the SDK builds one from `$config`. Tests inject a client backed by a `MockHandler`
    so the transport can be exercised without network access.

### `__debugInfo()`

```php
public function __debugInfo() : array
```

Keep credentials out of diagnostic object dumps.

@return array{client: string, logger: string, default_headers: list<string>}

### `get()`

```php
public function get(
        string $uri,
        #[\SensitiveParameter] array $params = [],
        #[\SensitiveParameter] array $headers = []
    ) : Response
```

{@inheritDoc}

### `post()`

```php
public function post(
        string $uri,
        #[\SensitiveParameter] ?array $data = null,
        #[\SensitiveParameter] array $headers = [],
        #[\SensitiveParameter] array $query = []
    ) : Response
```

{@inheritDoc}

### `put()`

```php
public function put(
        string $uri,
        #[\SensitiveParameter] ?array $data = null,
        #[\SensitiveParameter] array $headers = [],
        #[\SensitiveParameter] array $query = []
    ) : Response
```

{@inheritDoc}

### `patch()`

```php
public function patch(
        string $uri,
        #[\SensitiveParameter] ?array $data = null,
        #[\SensitiveParameter] array $headers = [],
        #[\SensitiveParameter] array $query = []
    ) : Response
```

{@inheritDoc}

### `delete()`

```php
public function delete(
        string $uri,
        #[\SensitiveParameter] array $headers = [],
        #[\SensitiveParameter] array $query = [],
        #[\SensitiveParameter] array $data = []
    ) : Response
```

{@inheritDoc}

### `uploadFile()`

```php
public function uploadFile(
        string $uri,
        #[\SensitiveParameter] string $filePath,
        #[\SensitiveParameter] array $data = [],
        #[\SensitiveParameter] array $headers = []
    ) : Response
```

{@inheritDoc}

### `postRaw()`

```php
public function postRaw(
        string $uri,
        #[\SensitiveParameter] string $body,
        string $contentType,
        #[\SensitiveParameter] array $query = [],
        #[\SensitiveParameter] array $headers = []
    ) : Response
```

{@inheritDoc}

## `Http/HttpClientInterface`

### `get()`

```php
public function get(string $uri, array $params = [], array $headers = []) : Response
```

@param array<string, scalar> $params
@param array<string, string> $headers

### `post()`

```php
public function post(string $uri, ?array $data = null, array $headers = [], array $query = []) : Response
```

@param array<array-key, mixed>|null $data JSON body; `null` sends no body, while
    an explicit `[]` sends a JSON array for endpoints whose contract requires it
@param array<string, string>   $headers
@param array<string, scalar>   $query   optional query-string parameters (e.g. `signer-access-code`)

### `put()`

```php
public function put(string $uri, ?array $data = null, array $headers = [], array $query = []) : Response
```

@param array<array-key, mixed>|null $data JSON body; `null` sends no body, while
    an explicit `[]` sends a JSON array for endpoints whose contract requires it
@param array<string, string>   $headers
@param array<string, scalar>   $query   optional query-string parameters (e.g. `signer-access-code`)

### `patch()`

```php
public function patch(string $uri, ?array $data = null, array $headers = [], array $query = []) : Response
```

@param array<array-key, mixed>|null $data JSON body; `null` sends no body
@param array<string, string>   $headers
@param array<string, scalar>   $query

### `delete()`

```php
public function delete(string $uri, array $headers = [], array $query = [], array $data = []) : Response
```

@param array<string, string>   $headers
@param array<string, scalar>   $query   optional query-string parameters (e.g. `force`)
@param array<array-key, mixed> $data    optional JSON body — a few DELETE endpoints
                                         (e.g. `DELETE /accounts/{id}`) document one

### `uploadFile()`

```php
public function uploadFile(string $uri, string $filePath, array $data = [], array $headers = []) : Response
```

@param array<string, mixed>  $data
@param array<string, string> $headers

### `postRaw()`

```php
public function postRaw(
        string $uri,
        string $body,
        string $contentType,
        array $query = [],
        array $headers = []
    ) : Response
```

Send a raw request body (e.g. a binary image) with a custom Content-Type.
Used by the signer-facing `/signature` endpoint which expects `image/png` or `image/jpeg`.

@param array<string, scalar> $query
@param array<string, string> $headers

## `Http/LogRedactor`

### `redact()`

```php
public static function redact(array $data) : array
```

Recursively replace credential values with a placeholder.

Preserves structure and non-secret values so debug logs stay useful. Scalars are
masked wholesale; a secret holding a nested array is masked as a single placeholder
rather than walked, so nothing leaks from inside it.

@param array<array-key, mixed> $data
@return array<array-key, mixed>

### `redactRequestOptions()`

```php
public static function redactRequestOptions(array $options) : array
```

Redact Guzzle request options, including payloads that are not JSON.

Raw `body` requests carry signature/initial image bytes. Multipart `contents`
may be an open file stream. Neither belongs in application logs.

@param array<string, mixed> $options
@return array<string, mixed>

### `summarizeRequestOptions()`

```php
public static function summarizeRequestOptions(array $options) : array
```

Summarize Guzzle request options without retaining payload or query values.

@param array<string, mixed> $options
@return array<string, bool|int|array<int, string>>

### `redactBody()`

```php
public static function redactBody(string $body) : string
```

Redact secrets from a raw JSON body, returning it re-encoded.

Non-JSON bodies (binary downloads, HTML error pages) are returned as a short
placeholder instead — logging a PDF byte-for-byte helps nobody.

### `redactText()`

```php
public static function redactText(string $text) : string
```

Redact credentials embedded in URLs and exception messages.

Signer links put their access code inside a URL stored under a generic `url`
key, and Guzzle exception messages include the full request URL. Exact-key
redaction alone therefore is not sufficient.

## `Http/Response`

### `__construct()`

```php
public function __construct(int $statusCode, array $headers, string $body)
```

@param array<string, array<int, string>|string> $headers

### `getStatusCode()`

```php
public function getStatusCode() : int
```

The HTTP status line code, e.g. `200`.

### `getHeaders()`

```php
public function getHeaders() : array
```

@return array<string, array<int, string>|string>

### `getBody()`

```php
public function getBody() : string
```

The raw response body — JSON text, or bytes for a download.

### `getData()`

```php
public function getData() : ?array
```

@return array<array-key, mixed>|null

### `isSuccess()`

```php
public function isSuccess() : bool
```

True for any 2xx status.

### `isClientError()`

```php
public function isClientError() : bool
```

True for any 4xx status.

### `isServerError()`

```php
public function isServerError() : bool
```

True for any 5xx status.

## `Resources/AbstractResource`

### `__construct()`

```php
public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] Configuration $config,
        ?LoggerInterface $logger = null
    )
```

Inject transport, immutable-by-interface configuration and an optional PSR-3 logger.
No request is made. Consumers normally obtain resources from AssinafyClient.

## `Resources/AccountResource`

### `list()`

```php
public function list(#[\SensitiveParameter] ?string $accessToken = null) : array
```

List the accounts the authenticated credential belongs to.
`GET /accounts`

This is the documented way to discover account IDs, so it is deliberately not
account-scoped. It accepts the configured API key/global Bearer credential, or an
OAuth access token passed explicitly.

Request: no parameters.

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
    'data' => [
        [
            'resource' => 'account',
            'id' => 'account-id',
            'name' => 'Acme Inc.',
            'primary_color' => 'aabbcc',
            'secondary_color' => '112233',
            'notification_sender_type' => 'User',
            'roles' => ['owner'],
            'is_delete_allowed' => true,
            'created_at' => '2026-06-03T03:54:16Z',
        ],
    ],
]
```

@return array{status?: int, message?: string, data?: array<int, array<string, mixed>>}

### `get()`

```php
public function get() : array
```

Retrieve the account this client is configured against.
`GET /accounts/{account_id}`

Request: path parameters shown above; no request body.

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'account',
    'id' => 'account-id',
    'name' => 'Example workspace',
    'primary_color' => null,
    'secondary_color' => null,
    'notification_sender_type' => 'User',
    'roles' => ['owner'],
    'is_delete_allowed' => true,
    'created_at' => '2026-08-28T15:13:43Z',
]
```

@return array<string, mixed>

### `create()`

```php
public function create(
        string $name,
        ?string $notificationSenderType = null,
        #[\SensitiveParameter] ?string $accessToken = null
    ) : array
```

Create a new account (workspace).
`POST /accounts`

Not account-scoped — callable before an account ID exists.

Request body:
```
[
  'name'                     => 'Acme Inc.',  // required
  'notification_sender_type' => 'User',       // optional: 'User' | 'Account'
]
```

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'account',
    'id' => 'account-id',
    'name' => 'Acme Inc.',
    'primary_color' => null,
    'secondary_color' => null,
    'notification_sender_type' => 'User',
    'roles' => ['owner'],
    'is_delete_allowed' => true,
    'created_at' => '2026-08-28T15:13:43Z',
]
```

@param string|null $notificationSenderType one of the `NOTIFICATION_SENDER_*` constants
@return array<string, mixed> the created account
@throws \Assinafy\SDK\Exceptions\ValidationException on an empty name or unknown sender type

### `update()`

```php
public function update(?string $name = null, ?string $notificationSenderType = null) : array
```

Update the configured account.
`PUT /accounts/{account_id}`

Both fields are optional; send only what you want to change. Omitted keys are left
untouched, so this is a partial update despite the `PUT` verb.

Request body (at least one key required):
```
['name' => 'Acme Holdings', 'notification_sender_type' => 'Account']
```

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'account',
    'id' => 'account-id',
    'name' => 'Acme Holdings',
    'primary_color' => null,
    'secondary_color' => null,
    'notification_sender_type' => 'Account',
    'roles' => ['owner'],
    'is_delete_allowed' => true,
    'created_at' => '2026-08-28T15:13:43Z',
]
```

@param string|null $notificationSenderType one of the `NOTIFICATION_SENDER_*` constants
@return array<string, mixed> the updated account
@throws \Assinafy\SDK\Exceptions\ValidationException when nothing was supplied to update

### `delete()`

```php
public function delete(bool $force = false) : array
```

Delete the configured account.
`DELETE /accounts/{account_id}`

Destructive and irreversible: removes the workspace and every document, signer, tag
and field in it.

Request body: `['force' => true]` when `$force` is set, otherwise no body.
This removes the account configured on the client, including its contents.

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
    'data' => [],
]
```

On refusal the API answers `400` and this method raises an
{@see \Assinafy\SDK\Exceptions\ApiException} whose `getResponseData()` carries the
blocking `restrictions`:
```
[
  'status'       => 400,
  'message'      => 'Cannot delete while restrictions are active.',
  'restrictions' => [['code' => 'ActivePaidSubscription'], ['code' => 'PendingDocuments']],
]
```

@param bool $force cancel an active paid subscription instead of refusing to delete
@return array<string, mixed> the raw envelope

### `theme()`

```php
public function theme() : array
```

Retrieve the account's branding theme.
`GET /accounts/{account_id}/theme`

Request: path parameters shown above; no request body.

Example response (SDK return; optional fields depend on state):
```php
[
    'account_name' => 'Example workspace',
    'primary_color' => '2072b9',
    'secondary_color' => 'ffffff',
    'logo' => null,
]
```

@return array<string, mixed>

### `stats()`

```php
public function stats(
        string $granularity = self::GRANULARITY_MONTHLY,
        ?string $month = null
    ) : array
```

Return the configured account's document-funnel KPI series.
`GET /accounts/{account_id}/stats`

Monthly mode returns the latest 12 months. Daily mode requires `$month`
in `YYYY-MM` form and returns every day in that month. Both series are
zero-filled by the API, so there are no gaps to interpolate.

Request (query string): `granularity=monthly|daily`, plus `month=YYYY-MM` which is
required for `daily` and optional for `monthly`.

Signature requests carry two independent breakdowns of the same total: the
`*_notification_*` counters split them by the channel the signer was notified
through, the `*_verification_*` counters by how the signer proved identity.

Example query (no request body):
```php
['granularity' => 'monthly', 'month' => '2026-09']
```

Example response (SDK return; optional fields depend on state):
```php
[
    [
        'period' => '2026-09',
        'documents_uploaded' => 0,
        'documents_sent' => 0,
        'signature_requests' => 0,
        'signature_requests_notification_email' => 0,
        'signature_requests_notification_whatsapp' => 0,
        'signature_requests_notification_bypass' => 0,
        'signature_requests_verification_email' => 0,
        'signature_requests_verification_whatsapp' => 0,
        'signature_requests_verification_bypass' => 0,
        'signature_requests_verification_digital_certificate' => 0,
        'signature_requests_viewed' => 0,
        'signature_requests_completed' => 0,
        'documents_certified' => 0,
    ],
]
```

Available in production and sandbox; access depends on the authenticated account.

@throws \Assinafy\SDK\Exceptions\ValidationException on an unknown granularity, or on
    `daily` without a `YYYY-MM` month
@return array<int, array{period: string, documents_uploaded: int, documents_sent: int,
    signature_requests: int, signature_requests_notification_email: int,
    signature_requests_notification_whatsapp: int,
    signature_requests_notification_bypass: int,
    signature_requests_verification_email: int,
    signature_requests_verification_whatsapp: int,
    signature_requests_verification_bypass: int,
    signature_requests_verification_digital_certificate: int,
    signature_requests_viewed: int, signature_requests_completed: int,
    documents_certified: int}>

### `downloadLogo()`

```php
public function downloadLogo() : string
```

Download the account logo.
`GET /accounts/{account_id}/logo`

Request: no parameters.

Response: the raw image bytes — **not** JSON, and not the envelope. The
`Content-Type` header carries the format the workspace uploaded (`image/png`,
`image/jpeg`). Write the return value straight to disk:
```php
file_put_contents('logo.png', $client->accounts()->downloadLogo());
```

@return string raw image bytes
@throws \Assinafy\SDK\Exceptions\ApiException 404 when no logo has been uploaded

### `uploadLogo()`

```php
public function uploadLogo(#[\SensitiveParameter] string $filePath) : array
```

Upload (or replace) the account logo.
`POST /accounts/{account_id}/logo`

Request: `multipart/form-data` with the image under the field name `file`.
Replaces any logo already stored — there is no separate update route.

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
]
```

@return array<string, mixed> empty on success
@throws \InvalidArgumentException when the file does not exist or is unreadable

### `deleteLogo()`

```php
public function deleteLogo() : array
```

Remove the account logo.
`DELETE /accounts/{account_id}/logo`

Request: no body. Succeeds even when no logo is currently set.

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
]
```

@return array<string, mixed> the raw envelope

## `Resources/AssignmentResource`

### `create()`

```php
public function create(
        string $documentId,
        #[\SensitiveParameter] array $signers,
        string $method = self::METHOD_VIRTUAL,
        #[\SensitiveParameter] array $options = []
    ) : array
```

Create an assignment (signature request).
`POST /documents/{document_id}/assignments`

This is the call that actually notifies signers. `virtual` accepts uploaded or processing documents; `collect` requires
`metadata_ready` because it refers to rendered pages.

`virtual` collects a click-to-sign consent from each signer. `collect` additionally
places named input fields on the pages, so it requires `entries`.

Request body:
```
[
  'method'  => 'virtual',                       // 'virtual' | 'collect'
  'signers' => [
    [
      'id'                   => 'signer-id',   // required
      'verification_method'  => 'Email',        // Email | Whatsapp | DigitalCertificate
      'notification_methods' => ['Email'],      // exactly one: Email | Whatsapp
      'step'                 => 1,              // 1-based signing order
    ],
  ],
  'message'        => 'Please sign this contract',
  'expires_at'     => '2026-12-31T23:59:59Z',   // ISO 8601, must carry Z or ±HH:MM
  'copy_receivers' => [],  // signer IDs CC'd on completion
]
```

A collect request uses the same options and adds page fields:
```php
[
  'method' => 'collect',
  'signers' => [
    ['id' => 'signer-id', 'verification_method' => 'Email', 'notification_methods' => ['Email']],
  ],
  'entries' => [
    [
      'page_id' => 'page-id',
      'fields'  => [
        [
          'signer_id'        => 'signer-id',
          'field_id'         => 'field-id',
          'display_settings' => [
            'left' => 120.0, 'top' => 400.0, 'width' => 180.0, 'height' => 32.0,
            'fontSize' => 12.0, 'fontFamily' => 'Helvetica',
            'backgroundColor' => '#ffffff',
          ],
        ],
      ],
    ],
  ],
]
```

Example response for the virtual request (SDK return; optional fields depend on state):
```php
[
    'resource' => 'assignment',
    'id' => 'assignment-id',
    'sender_email' => 'person@example.com',
    'method' => 'virtual',
    'expires_at' => '2026-12-31T23:59:59Z',
    'message' => 'Please sign this contract',
    'signers' => [
        [
            'id' => 'signer-id',
            'full_name' => 'Example Signer',
            'email' => 'person@example.com',
            'whatsapp_phone_number' => null,
            'government_id' => null,
            'has_accepted_terms' => false,
            'completed' => false,
            'notification_history' => [
                [
                    'event' => 'signature_request',
                    'status' => 'sent',
                    'error_code' => null,
                    'error_message' => null,
                    'sent_at' => '2026-09-01T12:00:00Z',
                    'failed_at' => null,
                ],
            ],
            'verification_method' => 'Email',
            'notification_methods' => ['Email'],
            'step' => 1,
            'notified' => true,
        ],
    ],
    'copy_receivers' => [],
    'items' => [
        [
            'id' => 'assignment-item-id',
            'page' => null,
            'signer' => [
                'id' => 'signer-id',
                'full_name' => 'Example Signer',
                'email' => 'person@example.com',
                'whatsapp_phone_number' => null,
                'government_id' => null,
                'has_accepted_terms' => false,
            ],
            'field' => [
                'id' => 'field-id',
                'name' => 'Virtual',
                'type' => 'virtual',
                'regex' => null,
                'is_pre_defined' => true,
                'is_active' => true,
                'is_required' => false,
                'is_standard' => false,
                'is_read_only' => false,
                'is_visible' => true,
            ],
            'display_settings' => [],
            'value' => null,
            'completed' => false,
        ],
    ],
    'summary' => [
        'signer_count' => 1,
        'completed_count' => 0,
        'signers' => [
            [
                'id' => 'signer-id',
                'full_name' => 'Example Signer',
                'email' => 'person@example.com',
                'whatsapp_phone_number' => null,
                'government_id' => null,
                'has_accepted_terms' => false,
                'completed' => false,
            ],
        ],
    ],
    'signing_urls' => [
        [
            'signer_id' => 'signer-id',
            'url' => 'https://app-sandbox.assinafy.com.br/sign/signing-token?email=signer%40example.com',
        ],
    ],
]
```

The `signing_urls` entries are what you hand to each signer. They do **not** contain
the signer access code — that is delivered separately by the notification channel.

@param array<int, string|array<string, mixed>> $signers
    Either a list of signer IDs (strings) or a list of `{ id, verification_method?,
    notification_methods?, step? }` objects. String IDs are normalized to `{ id }`.
@param array<string, mixed> $options
    Optional keys: `entries` (required for collect), `message`, `expires_at`, `copy_receivers`.
@return array<string, mixed> the created assignment
@throws ValidationException on an unknown method, an empty signer list, a `collect`
    assignment without entries, a malformed `expires_at`, non-contiguous steps, or a
    digital-certificate signer sharing a step with another signer

### `list()`

```php
public function list(int $page = 1, int $perPage = 20, array $filters = []) : array
```

List the assignments belonging to the configured account.
`GET /assignments`

Despite living outside `/accounts/{account_id}`, this endpoint still needs an account
context, which it takes as an `accountId` query parameter. That parameter is NOT in the
published OpenAPI spec (which documents only `page` and `per-page`), but the API answers
`400 "Um contexto de conta é necessário e não foi fornecido."` without it. The SDK sends
it from {@see \Assinafy\SDK\Configuration}. Note the camelCase spelling — `account-id`
and `account_id` are both rejected.

Example query (no request body):
```php
['page' => 1, 'per-page' => 20, 'accountId' => 'account-id']
```

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
    'data' => [
        [
            'id' => 'assignment-id',
            'sender_email' => 'person@example.com',
            'method' => 'virtual',
            'expires_at' => null,
            'message' => null,
            'signers' => [
                [
                    'id' => 'signer-id',
                    'full_name' => 'Example Signer',
                    'email' => 'person@example.com',
                    'whatsapp_phone_number' => null,
                    'government_id' => null,
                    'has_accepted_terms' => false,
                    'completed' => false,
                    'notification_history' => [
                        [
                            'event' => 'signature_request',
                            'status' => 'sent',
                            'error_code' => null,
                            'error_message' => null,
                            'sent_at' => '2026-09-01T12:00:00Z',
                            'failed_at' => null,
                        ],
                    ],
                    'verification_method' => 'Email',
                    'notification_methods' => ['Email'],
                    'step' => 1,
                    'notified' => true,
                ],
            ],
            'copy_receivers' => [],
            'items' => [
                [
                    'id' => 'assignment-item-id',
                    'page' => null,
                    'signer' => [
                        'id' => 'signer-id',
                        'full_name' => 'Example Signer',
                        'email' => 'person@example.com',
                        'whatsapp_phone_number' => null,
                        'government_id' => null,
                        'has_accepted_terms' => false,
                    ],
                    'field' => [
                        'id' => 'field-id',
                        'name' => 'Virtual',
                        'type' => 'virtual',
                        'regex' => null,
                        'is_pre_defined' => true,
                        'is_active' => true,
                        'is_required' => false,
                        'is_standard' => false,
                        'is_read_only' => false,
                        'is_visible' => true,
                    ],
                    'display_settings' => [],
                    'value' => null,
                    'completed' => false,
                ],
            ],
            'summary' => [
                'signer_count' => 1,
                'completed_count' => 0,
                'signers' => [
                    [
                        'id' => 'signer-id',
                        'full_name' => 'Example Signer',
                        'email' => 'person@example.com',
                        'whatsapp_phone_number' => null,
                        'government_id' => null,
                        'has_accepted_terms' => false,
                        'completed' => false,
                    ],
                ],
            ],
            'signing_urls' => [
                [
                    'signer_id' => 'signer-id',
                    'url' => 'https://app-sandbox.assinafy.com.br/sign/signing-token?email=signer%40example.com',
                ],
            ],
        ],
    ],
    'pagination' => [
        'current_page' => 1,
        'page_count' => 1,
        'per_page' => 20,
        'total_count' => 1,
    ],
]
```

@param array<string, scalar> $filters extra query parameters merged over the defaults
@return array{status?: int, message?: string, data?: array<int, array<string, mixed>>,
    pagination?: array{current_page: int, page_count: int, per_page: int, total_count: int}}

### `estimateCost()`

```php
public function estimateCost(
        string $documentId,
        #[\SensitiveParameter] array $signers,
        string $method = self::METHOD_VIRTUAL,
        #[\SensitiveParameter] array $options = []
    ) : array
```

Estimate the credit cost of creating an assignment, without creating it.
`POST /documents/{document_id}/assignments/estimate-cost`

Signer IDs are NOT required here — cost depends only on the verification and
notification methods, so you can price a request before the signers exist:

```php
$client->assignments()->estimateCost($documentId, [
    ['verification_method' => 'Email', 'notification_methods' => ['Email']],
]);
```

Request body for that call:
```php
[
    'method' => 'virtual',
    'signers' => [['verification_method' => 'Email', 'notification_methods' => ['Email']]],
]
```
For `collect`, supply field placements in `entries` as documented by {@see self::create()}.

The document must not have started signing yet; otherwise the API answers
`400 "A atribuição não pode ser criada para um documento com status '…'"`.

Example response (SDK return; optional fields depend on state):
```php
[
    'documents' => 1,
    'credits' => 0,
    'needs_extra_document' => false,
    'extra_document_cost' => 0,
    'total_credits' => 0,
    'breakdown' => [],
    'document_balance' => 67,
    'credit_balance' => 0,
    'has_sufficient_resources' => true,
    'blocking_reason' => null,
    'message' => null,
]
```

@param array<int, string|array<string, mixed>> $signers signer IDs or objects; entries
    may omit `id` and carry only `verification_method` / `notification_methods`
@param array<string, mixed> $options extra body fields forwarded verbatim
@return array<string, mixed>

### `resend()`

```php
public function resend(string $documentId, string $assignmentId, string $signerId) : array
```

Resend the signing-notification to a single signer.
`PUT /documents/{document_id}/assignments/{assignment_id}/signers/{signer_id}/resend`

Sends over the signer's configured `notification_methods` channel. A resend may
consume credits — price it first with {@see self::estimateResendCost()}.

Request: no body; everything is addressed through the path.

Example response (SDK return; optional fields depend on state):
```php
[
    'is_sent' => true,
    'document_id' => 'document-id',
    'signer_id' => 'signer-id',
]
```

@return array<string, mixed>
@throws ValidationException when any identifier is empty
@throws \Assinafy\SDK\Exceptions\ApiException when the signer has already completed

### `estimateResendCost()`

```php
public function estimateResendCost(string $documentId, string $assignmentId, string $signerId) : array
```

Estimate the credit cost of resending a notification to one signer.
`POST /documents/{document_id}/assignments/{assignment_id}/signers/{signer_id}/estimate-resend-cost`

Read-only — nothing is sent to the signer and no credits are spent. Check
`has_sufficient_credits` before calling {@see self::resend()}.

Request: no body; everything is addressed through the path.

Example response (SDK return; optional fields depend on state):
```php
[
    'total' => 0,
    'breakdown' => [
        ['code' => 'NotificationEmailResend', 'name' => 'Email Notification Resend', 'cost' => 0],
    ],
    'credit_balance' => 0,
    'has_sufficient_credits' => true,
]
```

The live-verified resend response uses `total` and `has_sufficient_credits`;
the published `CostEstimate` schema describes assignment creation instead.

@return array<string, mixed>
@throws ValidationException when any identifier is empty

### `resetExpiration()`

```php
public function resetExpiration(string $documentId, string $assignmentId, string $expiresAt) : array
```

Reset the expiration date of an assignment.
`PUT /documents/{document_id}/assignments/{assignment_id}/reset-expiration`

Use this to revive an assignment whose deadline has passed, or to extend one still
running. `$expiresAt` is validated locally before the request is sent, so a malformed
value never reaches the API.

Request body:
```
['expires_at' => '2026-12-31T23:59:59Z']  // ISO 8601; a Z or ±HH:MM offset is mandatory
```

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'assignment',
    'id' => 'assignment-id',
    'sender_email' => 'person@example.com',
    'method' => 'virtual',
    'expires_at' => '2026-12-31T23:59:59Z',
    'message' => null,
    'signers' => [
        [
            'id' => 'signer-id',
            'full_name' => 'Example Signer',
            'email' => 'person@example.com',
            'whatsapp_phone_number' => null,
            'government_id' => null,
            'has_accepted_terms' => false,
            'completed' => false,
            'notification_history' => [
                [
                    'event' => 'signature_request',
                    'status' => 'sent',
                    'error_code' => null,
                    'error_message' => null,
                    'sent_at' => '2026-09-01T12:00:00Z',
                    'failed_at' => null,
                ],
            ],
            'verification_method' => 'Email',
            'notification_methods' => ['Email'],
            'step' => 1,
            'notified' => true,
        ],
    ],
    'copy_receivers' => [],
    'items' => [
        [
            'id' => 'assignment-item-id',
            'page' => null,
            'signer' => [
                'id' => 'signer-id',
                'full_name' => 'Example Signer',
                'email' => 'person@example.com',
                'whatsapp_phone_number' => null,
                'government_id' => null,
                'has_accepted_terms' => false,
            ],
            'field' => [
                'id' => 'field-id',
                'name' => 'Virtual',
                'type' => 'virtual',
                'regex' => null,
                'is_pre_defined' => true,
                'is_active' => true,
                'is_required' => false,
                'is_standard' => false,
                'is_read_only' => false,
                'is_visible' => true,
            ],
            'display_settings' => [],
            'value' => null,
            'completed' => false,
        ],
    ],
    'summary' => [
        'signer_count' => 1,
        'completed_count' => 0,
        'signers' => [
            [
                'id' => 'signer-id',
                'full_name' => 'Example Signer',
                'email' => 'person@example.com',
                'whatsapp_phone_number' => null,
                'government_id' => null,
                'has_accepted_terms' => false,
                'completed' => false,
            ],
        ],
    ],
    'signing_urls' => [
        [
            'signer_id' => 'signer-id',
            'url' => 'https://app-sandbox.assinafy.com.br/sign/signing-token?email=signer%40example.com',
        ],
    ],
]
```

@param string $expiresAt ISO 8601 date-time ending in `Z` or an explicit `±HH:MM` offset
@return array<string, mixed>
@throws ValidationException when `$expiresAt` is not a valid ISO 8601 date-time

### `whatsappNotifications()`

```php
public function whatsappNotifications(string $documentId, string $assignmentId) : array
```

List the WhatsApp notification messages sent for an assignment, with the rendered
header/body/buttons exactly as the signer sees them.
`GET /documents/{document_id}/assignments/{assignment_id}/whatsapp-notifications`

Useful for supporting a signer who says the message never arrived: `status` shows how
far the delivery got. Returns an empty array for assignments notified only by email.

Request: no parameters.

Example response (SDK return; optional fields depend on state):
```php
[
    [
        'sent_at' => 1710000000,
        'header' => 'Documento para assinatura: Contrato de Servico',
        'body' => 'example',
        'buttons' => [
            [
                'text' => 'Abrir documento',
            ],
        ],
        'phone_number' => '+5511999990001',
        'signer_id' => 'signer-id',
    ],
]
```

@return array<int, array<string, mixed>> empty when nothing was sent over WhatsApp
@throws ValidationException when any identifier is empty

## `Resources/AuthResource`

### `login()`

```php
public function login(
        #[\SensitiveParameter] string $email,
        #[\SensitiveParameter] string $password
    ) : array
```

Sign in with email + password.
`POST /login`

The bootstrap call: exchange credentials for a Bearer token when you don't yet hold an
API key. Callable on a {@see \Assinafy\SDK\AssinafyClient::forAuth()} client, which
carries no credentials of its own.

Request body:
```
['email' => 'user@example.com', 'password' => 's3cret']
```

Example response (SDK return; optional fields depend on state):
```php
[
    'access_token' => '<access-token>',
    'user' => [
        'id' => 'auth-user-id',
        'name' => 'John Smith',
        'email' => 'person@example.com',
        'telephone' => null,
        'government_id' => null,
        'is_email_verified' => false,
        'has_accepted_terms' => true,
        'created_at' => '2023-03-03T11:51:34Z',
        'to_be_deleted_at' => null,
    ],
    'accounts' => [
        [
            'id' => 'auth-account-id',
            'name' => 'JS',
            'roles' => ['owner'],
            'is_delete_allowed' => true,
            'created_at' => '2023-03-03T11:51:34Z',
        ],
    ],
]
```

Feed `access_token` and one of the `accounts[].id` values into
{@see \Assinafy\SDK\AssinafyClient::forBearer()} to get a workspace-scoped client.

@return array<string, mixed> `{ access_token, user, accounts }`
@throws ValidationException on a malformed email or empty password
@throws \Assinafy\SDK\Exceptions\ApiException 401 on wrong credentials

### `socialLogin()`

```php
public function socialLogin(
        string $provider,
        #[\SensitiveParameter] string $token,
        bool $hasAcceptedTerms = false
    ) : array
```

Sign in with a social provider.
`POST /authentication/social-login`

`$token` is the provider's own ID token, obtained in the browser — the SDK does not
run the OAuth dance. Only `google` is accepted today ({@see self::PROVIDER_GOOGLE}).
Set `$hasAcceptedTerms` on the first sign-in of a brand-new user.

Request body:
```
['provider' => 'google', 'token' => '<google id token>', 'has_accepted_terms' => true]
```

Example response (SDK return; optional fields depend on state):
```php
[
    'access_token' => '<access-token>',
    'user' => [
        'id' => 'auth-user-id',
        'name' => 'John Smith',
        'email' => 'person@example.com',
        'telephone' => null,
        'government_id' => null,
        'is_email_verified' => false,
        'has_accepted_terms' => true,
        'created_at' => '2023-03-03T11:51:34Z',
        'to_be_deleted_at' => null,
    ],
    'accounts' => [
        [
            'id' => 'auth-account-id',
            'name' => 'JS',
            'roles' => ['owner'],
            'is_delete_allowed' => true,
            'created_at' => '2023-03-03T11:51:34Z',
        ],
    ],
]
```

@return array<string, mixed> `{ access_token, user, accounts }`
@throws ValidationException on an unsupported provider or empty token

### `linkSocialLogin()`

```php
public function linkSocialLogin(
        string $provider,
        #[\SensitiveParameter] string $token,
        #[\SensitiveParameter] ?string $accessToken = null
    ) : array
```

Link a social provider account to the authenticated Assinafy user.
`POST /auth/link-social-login`

Attaches a social identity to an account that already exists, so the user can sign in
either way afterwards. Distinct from {@see self::socialLogin()}, which authenticates.

Uses the configured API key by default. Pass `$accessToken` when using a
public/bootstrap client.

Request body:
```
['provider' => 'google', 'token' => '<google id token>']
```

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
]
```

@return array<string, mixed>
@throws ValidationException on an unsupported provider, an empty token, or an empty
    access token on a public client

### `socialLoginUrl()`

```php
public function socialLoginUrl(string $provider = self::PROVIDER_GOOGLE) : string
```

Build the legacy browser OAuth start URL without requesting it.

Pure string construction — makes **no** HTTP request. Redirect a browser here to
begin the provider handshake; the result comes back to
{@see self::socialLoginCallbackUrl()}.

Request/Response: none.

Returns, for the default base URL:
```
https://api.assinafy.com.br/v1/auth/authenticate?authclient=google
```

This legacy social-login route is outside OpenAPI. The production route returns
a redirect; this builder does not complete provider authentication. Marketplace
OAuth uses the separate authorization server and PKCE flow in docs/OAUTH.md.

@return string absolute URL
@throws ValidationException on an unsupported provider

### `socialLoginCallbackUrl()`

```php
public function socialLoginCallbackUrl() : string
```

Return the legacy browser callback URL without requesting it.

Pure string construction — makes **no** HTTP request. This is where the provider
returns the browser after {@see self::socialLoginUrl()}.

Request/Response: none.

Returns, for the default base URL:
```
https://api.assinafy.com.br/v1/login-callback
```

`/login-callback` is not part of the current OpenAPI contract and serves HTML rather
than JSON, so it is a redirect target rather than an endpoint to call.

@return string absolute URL

### `generateApiKey()`

```php
public function generateApiKey(
        #[\SensitiveParameter] ?string $accessToken,
        #[\SensitiveParameter] string $password
    ) : array
```

Generate (or regenerate) the API key for the authenticated user.
`POST /users/api-keys` — uses the configured API key/Bearer credential or an
explicitly supplied Bearer access token.

**This is the only call that ever returns the key in full**; {@see self::getApiKey()}
returns it masked from then on. Store the value now or you will have to regenerate.
Regenerating invalidates the previous key immediately.

The password is re-checked here even though the request is already authenticated.

Request body:
```
['password' => 's3cret']
```

Example response (SDK return; optional fields depend on state):
```php
[
    'api_key' => '<api-key>',
]
```

@param string|null $accessToken Bearer token, or null to use the configured credential
@return array<string, mixed> `{ api_key }` — the full, unmasked key
@throws ValidationException on an empty password or empty access token

### `getApiKey()`

```php
public function getApiKey(#[\SensitiveParameter] ?string $accessToken = null) : array
```

Retrieve the masked API key for the authenticated user.
`GET /users/api-keys` — uses the configured API key/Bearer credential or an
explicitly supplied Bearer access token.

Confirms a key exists and shows its last four characters; it is **not** usable for
authentication. Only {@see self::generateApiKey()} ever returns the full value.

Request: no parameters.

Example response (SDK return; optional fields depend on state):
```php
[
    'api_key' => '<masked-api-key>',
]
```

@return array<string, mixed> `{ api_key }` — masked
@throws ValidationException when called on a public client without an access token
@throws \Assinafy\SDK\Exceptions\ApiException 404 when no key has been generated

### `deleteApiKey()`

```php
public function deleteApiKey(#[\SensitiveParameter] ?string $accessToken = null) : array
```

Delete the API key for the authenticated user.
`DELETE /users/api-keys` — uses the configured API key/Bearer credential or an
explicitly supplied Bearer access token.

Revokes the key immediately. If the client was authenticating **with** that key, every
later call on it fails with 401 — pass a Bearer `$accessToken` when you need the
session to survive the revocation.

Request: no body.

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
    'data' => [],
]
```

@return array<array-key, mixed> the raw envelope
@throws ValidationException when called on a public client without an access token

### `changePassword()`

```php
public function changePassword(
        #[\SensitiveParameter] ?string $accessToken,
        #[\SensitiveParameter] string $email,
        #[\SensitiveParameter] string $password,
        #[\SensitiveParameter] string $newPassword
    ) : array
```

Change the password of the authenticated user.
`PUT /authentication/change-password` — uses the configured API key/Bearer
credential or an explicitly supplied Bearer access token.

For a user who knows their current password. When they don't, use
{@see self::requestPasswordReset()} followed by {@see self::resetPassword()}.

Request body:
```
[
  'email'        => 'user@example.com',
  'password'     => 's3cret',      // current
  'new_password' => 'n3wS3cret',
]
```

Example response (SDK return; optional fields depend on state):
```php
[
    'email' => 'person@example.com',
]
```

@return array<string, mixed> `{ email }`
@throws ValidationException on a malformed email or an empty password
@throws \Assinafy\SDK\Exceptions\ApiException 401 when the current password is wrong

### `requestPasswordReset()`

```php
public function requestPasswordReset(#[\SensitiveParameter] string $email) : array
```

Trigger a password-reset email.
`PUT /authentication/request-password-reset`

Unauthenticated — callable on a {@see \Assinafy\SDK\AssinafyClient::forAuth()} client.
The SDK strips workspace credentials from this request even on an authenticated one.
Step one of two; the emailed token is then spent by {@see self::resetPassword()}.

Request body:
```
['email' => 'user@example.com']
```

Example response (SDK return; optional fields depend on state):
```php
[
    'email' => 'person@example.com',
]
```

Answers 200 whether or not the address belongs to a user, so it cannot be used to
enumerate accounts — a 200 is not proof the mail was sent.

@return array<string, mixed> `{ email }`
@throws ValidationException on a malformed email

### `resetPassword()`

```php
public function resetPassword(
        #[\SensitiveParameter] string $email,
        #[\SensitiveParameter] string $token,
        #[\SensitiveParameter] string $newPassword
    ) : array
```

Complete a password reset using the token emailed to the user.
`PUT /authentication/reset-password`

Step two of the flow started by {@see self::requestPasswordReset()}. Unauthenticated —
callable on a {@see \Assinafy\SDK\AssinafyClient::forAuth()} client, and the SDK strips
workspace credentials from this request even on an authenticated one.

Request body:
```
[
  'email'        => 'user@example.com',
  'token'        => '<token from the reset email>',
  'new_password' => 'n3wS3cret',
]
```

Example response (SDK return; optional fields depend on state):
```php
[
    'email' => 'person@example.com',
]
```

@return array<string, mixed> `{ email }`
@throws ValidationException on a malformed email, or an empty token or password
@throws \Assinafy\SDK\Exceptions\ApiException 400 when the token is expired or already used

## `Resources/DocumentResource`

### `upload()`

```php
public function upload(#[\SensitiveParameter] string $filePath) : array
```

Upload a PDF and create a new document.
`POST /accounts/{account_id}/documents`

The first step of every signature workflow. The file is checked locally (exists,
readable, PDF, ≤ 25 MB) before any bytes go over the wire.

The upload returns immediately with `status: uploaded`; the API then renders pages
asynchronously. Wait for {@see self::waitUntilReady()} before creating an assignment.

Request: `multipart/form-data` with the PDF under the field name `file`.

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'document',
    'id' => 'document-id',
    'account_id' => 'account-id',
    'template_id' => null,
    'name' => 'agreement.pdf',
    'status' => 'uploaded',
    'artifacts' => [
        'original' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/download/original',
    ],
    'is_closed' => false,
    'signing_url' => 'https://app-sandbox.assinafy.com.br/sign/signing-token',
    'decline_reason' => null,
    'declined_by' => null,
    'tags' => [],
    'created_at' => '2026-09-01T12:00:00Z',
    'updated_at' => '2026-09-01T12:00:00Z',
    'pages' => [],
]
```

@return array<string, mixed> the created document
@throws ValidationException when the file is missing, not a PDF, or over 25 MB

### `get()`

```php
public function get(string $documentId) : array
```

Retrieve a document, including its pages and current assignment.
`GET /documents/{document_id}`

Richer than the {@see self::list()} entries: only this route returns `pages` (with the
page IDs {@see self::downloadPage()} and `collect` assignments need) and the fully
expanded `assignment`.

Request: no parameters.

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'document',
    'id' => 'document-id',
    'account_id' => 'account-id',
    'template_id' => null,
    'name' => 'agreement.pdf',
    'status' => 'metadata_ready',
    'artifacts' => [
        'original' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/download/original',
        'thumbnail' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/thumbnail',
    ],
    'is_closed' => false,
    'signing_url' => 'https://app-sandbox.assinafy.com.br/sign/signing-token',
    'decline_reason' => null,
    'declined_by' => null,
    'tags' => [],
    'created_at' => '2026-09-01T12:00:00Z',
    'updated_at' => '2026-09-01T12:00:00Z',
    'assignment' => null,
    'pages' => [
        [
            'id' => 'page-id',
            'number' => 1,
            'height' => 1651,
            'width' => 1275,
            'download_url' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/pages/page-id/download',
        ],
    ],
]
```

@return array<string, mixed>
@throws ValidationException when `$documentId` is empty
@throws \Assinafy\SDK\Exceptions\ApiException 404 when the document does not exist

### `list()`

```php
public function list(int $page = 1, int $perPage = 20, array $filters = []) : array
```

List documents in the workspace.
`GET /accounts/{account_id}/documents`

Example query (no request body):
```php
['page' => 1, 'per-page' => 20]
```

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
    'data' => [
        [
            'id' => 'document-id',
            'account_id' => 'account-id',
            'template_id' => null,
            'name' => 'agreement.pdf',
            'status' => 'metadata_ready',
            'artifacts' => [
                'original' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/download/original',
                'thumbnail' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/thumbnail',
            ],
            'is_closed' => false,
            'signing_url' => 'https://app-sandbox.assinafy.com.br/sign/signing-token',
            'decline_reason' => null,
            'declined_by' => null,
            'tags' => [],
            'created_at' => '2026-09-01T12:00:00Z',
            'updated_at' => '2026-09-01T12:00:00Z',
            'assignment' => null,
            'pages' => [
                [
                    'id' => 'page-id',
                    'number' => 1,
                    'height' => 1651,
                    'width' => 1275,
                    'download_url' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/pages/page-id/download',
                ],
            ],
        ],
    ],
    'pagination' => [
        'current_page' => 1,
        'page_count' => 1,
        'per_page' => 20,
        'total_count' => 1,
    ],
]
```

@param array<string, scalar> $filters optional `status`, `method`, `search`, `tags`,
    and `sort` (`sort` accepts `name` or `updated_at`)
@return array{status?: int, message?: string, data?: array<int, array<string, mixed>>,
    pagination?: array{current_page: int, page_count: int, per_page: int, total_count: int}}

### `search()`

```php
public function search(string $term, int $page = 1, int $perPage = 20, array $filters = []) : array
```

Search documents, returning a lighter representation than {@see self::list()}.
`GET /accounts/{account_id}/documents/search`

Matches `$term` against the document name. Entries omit `assignment` and `pages`,
which makes this the cheaper choice for type-ahead and pickers; fetch the full record
with {@see self::get()} once the user picks one.

Request (query string): `search`, `page`, `per-page`, plus an optional `status` filter.

Example query (no request body):
```php
['search' => 'agreement', 'page' => 1, 'per-page' => 20]
```

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
    'data' => [
        [
            'id' => 'document-id',
            'account_id' => 'account-id',
            'template_id' => null,
            'name' => 'agreement.pdf',
            'status' => 'metadata_ready',
            'artifacts' => [
                'original' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/download/original',
                'thumbnail' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/thumbnail',
            ],
            'is_closed' => false,
            'signing_url' => 'https://app-sandbox.assinafy.com.br/sign/signing-token',
            'decline_reason' => null,
            'declined_by' => null,
            'tags' => [],
            'created_at' => '2026-09-01T12:00:00Z',
            'updated_at' => '2026-09-01T12:00:00Z',
            'assignment' => null,
            'pages' => [
                [
                    'id' => 'page-id',
                    'number' => 1,
                    'height' => 1651,
                    'width' => 1275,
                    'download_url' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/pages/page-id/download',
                ],
            ],
        ],
    ],
    'pagination' => [
        'current_page' => 1,
        'page_count' => 1,
        'per_page' => 20,
        'total_count' => 1,
    ],
]
```

@param array<string, scalar> $filters additional optional filters, e.g. `status`
@return array{status?: int, message?: string, data?: array<int, array<string, mixed>>,
    pagination?: array{current_page: int, page_count: int, per_page: int, total_count: int}}
@throws ValidationException when `$page` < 1 or `$perPage` is outside 1–100

### `rename()`

```php
public function rename(string $documentId, #[\SensitiveParameter] string $name) : array
```

Rename a document.
`PATCH /documents/{document_id}`

Only allowed before the signature process starts — the document must be in `uploaded`
or `metadata_ready` status with no signers yet. Once an assignment exists (or the
document is certificated) the API rejects the call with
`400 "Document cannot be renamed after the signature process has started."`

The API normalises the name server-side: diacritics are stripped and unsupported
characters become dashes, so `"renamed áç.pdf"` is stored as `"renamed ac.pdf"`.
Max 255 characters.

Request body:
```
['name' => 'Service agreement.pdf']
```

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'document',
    'id' => 'document-id',
    'account_id' => 'account-id',
    'template_id' => null,
    'name' => 'Service agreement.pdf',
    'status' => 'metadata_ready',
    'artifacts' => [
        'original' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/download/original',
        'thumbnail' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/thumbnail',
    ],
    'is_closed' => false,
    'signing_url' => 'https://app-sandbox.assinafy.com.br/sign/signing-token',
    'decline_reason' => null,
    'declined_by' => null,
    'tags' => [],
    'created_at' => '2026-09-01T12:00:00Z',
    'updated_at' => '2026-09-01T12:00:00Z',
    'assignment' => null,
    'pages' => [
        [
            'id' => 'page-id',
            'number' => 1,
            'height' => 1651,
            'width' => 1275,
            'download_url' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/pages/page-id/download',
        ],
    ],
]
```

@return array<string, mixed> the updated document
@throws ValidationException when `$name` is empty or exceeds 255 characters
@throws \Assinafy\SDK\Exceptions\ApiException 400 once signing has started

### `delete()`

```php
public function delete(string $documentId) : array
```

Delete a document.
`DELETE /documents/{document_id}`

Only allowed from a status whose `deletable` flag is true — call
{@see self::statuses()} for the authoritative list. A document mid-certification
cannot be removed.

Request: no body.

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
    'data' => [],
]
```

@return array<array-key, mixed> the raw envelope
@throws ValidationException when `$documentId` is empty
@throws \Assinafy\SDK\Exceptions\ApiException 400 from a non-deletable status

### `download()`

```php
public function download(string $documentId, string $artifact = self::ARTIFACT_CERTIFICATED) : string
```

Download an artifact for a document.
`GET /documents/{document_id}/download/{artifact_name}`

Artifacts, via the `ARTIFACT_*` constants:

| Constant                     | Value              | What you get                              |
|------------------------------|--------------------|-------------------------------------------|
| `ARTIFACT_ORIGINAL`          | `original`         | The PDF exactly as uploaded               |
| `ARTIFACT_CERTIFICATED`      | `certificated`     | Signed PDF with the certificate page      |
| `ARTIFACT_CERTIFICATE_PAGE`  | `certificate-page` | The certificate page on its own     |
| `ARTIFACT_PADES`            | `pades`            | PAdES-conformant signed PDF               |
| `ARTIFACT_BUNDLE`           | `bundle`           | ZIP of every artifact above               |

Everything except `original` exists only once the document reaches `certificated`.

Request: no parameters — the artifact is a path segment.

Response: the raw bytes (`application/pdf`, or `application/zip` for `bundle`) —
**not** the JSON envelope:
```php
file_put_contents('signed.pdf', $client->documents()->download($id));
```

@return string raw file bytes
@throws ValidationException on an unknown artifact name or an empty document ID
@throws \Assinafy\SDK\Exceptions\ApiException 404 when the artifact is not ready yet

### `downloadThumbnail()`

```php
public function downloadThumbnail(string $documentId) : string
```

Download the JPEG thumbnail for a document.
`GET /documents/{document_id}/thumbnail`

A preview of the first page, available once the document reaches `metadata_ready`.

Request: no parameters.

Response: raw `image/jpeg` bytes — not the JSON envelope.

@return string raw JPEG bytes
@throws ValidationException when `$documentId` is empty
@throws \Assinafy\SDK\Exceptions\ApiException 404 before page rendering finishes

### `downloadPage()`

```php
public function downloadPage(string $documentId, string $pageId) : string
```

Download a rendered page as JPEG.
`GET /documents/{document_id}/pages/{page_id}/download`

Page IDs come from the `pages` array on {@see self::get()}. Use these renders to
position fields when building a `collect` assignment — the `width`/`height` reported
alongside each page are the coordinate space `display_settings` is expressed in.

Request: no parameters.

Response: raw `image/jpeg` bytes — not the JSON envelope.

@return string raw JPEG bytes
@throws ValidationException when either identifier is empty

### `activities()`

```php
public function activities(string $documentId) : array
```

List activity events for a document.
`GET /documents/{document_id}/activities`

The activity history: upload, preparation, each notification, each view, each signature,
and certification — **newest first**. This is the record behind the certificate page.

`event` uses the same vocabulary as the webhook event types
({@see \Assinafy\SDK\Resources\WebhookResource} `EVENT_*`), so one switch can handle
both. `origin` is null for events the platform raised itself rather than a request.

Request: no parameters.

Example response (SDK return; optional fields depend on state):
```php
[
    [
        'id' => 31451,
        'event' => 'document_metadata_ready',
        'message' => 'Documento processado.',
        'payload' => [],
        'origin' => null,
        'created_at' => '2026-09-01T12:00:00Z',
    ],
    [
        'id' => 31450,
        'event' => 'document_uploaded',
        'message' => 'Documento criado.',
        'payload' => [],
        'origin' => [
            'ip' => '<ip>',
            'user-agent' => 'Assinafy-PHP-SDK/v2.2.0',
        ],
        'created_at' => '2026-09-01T12:00:00Z',
    ],
]
```

@return array<int, array<string, mixed>>
@throws ValidationException when `$documentId` is empty

### `statuses()`

```php
public function statuses() : array
```

List all possible document statuses, with their `deletable` flag.
`GET /documents/statuses`

The authoritative source for which statuses {@see self::delete()} accepts — prefer it
over hard-coding, since the platform can add statuses. The `STATUS_*` constants mirror
the codes below.

Request: no parameters. Not account-scoped.

Example response (SDK return; optional fields depend on state):
```php
[
    [
        'code' => 'uploading',
        'deletable' => false,
    ],
    [
        'code' => 'uploaded',
        'deletable' => false,
    ],
    [
        'code' => 'metadata_processing',
        'deletable' => false,
    ],
    [
        'code' => 'metadata_ready',
        'deletable' => true,
    ],
    [
        'code' => 'expired',
        'deletable' => true,
    ],
    [
        'code' => 'certificating',
        'deletable' => false,
    ],
    [
        'code' => 'certificated',
        'deletable' => false,
    ],
    [
        'code' => 'rejected_by_signer',
        'deletable' => true,
    ],
    [
        'code' => 'pending_signature',
        'deletable' => true,
    ],
    [
        'code' => 'rejected_by_user',
        'deletable' => true,
    ],
    [
        'code' => 'failed',
        'deletable' => true,
    ],
]
```

@return array<int, array{code: string, deletable: bool}>

### `verify()`

```php
public function verify(string $signatureHash) : array
```

Verify a certificated document by its signature hash. Public endpoint, no auth.
`GET /documents/{signature_hash}/verify`

The hash is printed on the certificate page, so any recipient can confirm a PDF is
genuine and unaltered without holding credentials. The SDK strips workspace
credentials from this request even on an authenticated client.

Request: no parameters — the hash is the path segment.

Example response (SDK return; optional fields depend on state):
```php
[
    'hash' => 'FE32EDDADE7CBDDCBB934E7402047450B0E59C02',
    'id' => 'document-verification-id',
    'agreement_code' => '550E8400-E29B-41D4-A716-446655440000',
    'status' => 'certificated',
    'page_count' => '1',
    'signer_count' => '1',
    'completed_count' => 1,
    'completed_at' => '2023-01-27T19:27:44Z',
    'verified_at' => '2023-01-27T19:27:46Z',
    'is_valid' => true,
    'message' => '',
]
```

`agreement_code` is the agreement code printed on the document certificate. It may be null
or absent (the sandbox omits it), so read it with `$result['agreement_code'] ?? null`.

An unknown or unsigned hash answers **200, not 404** — always branch on `is_valid`:
```
[
  'hash' => 'resource-id', 'id' => null, 'agreement_code' => null, 'status' => null,
  'page_count' => null, 'signer_count' => null, 'completed_count' => null,
  'completed_at' => null, 'verified_at' => '2026-08-27T22:20:03Z',
  'is_valid' => false, 'message' => 'Documento não assinado ou não encontrado.',
]
```

@return array<string, mixed>
@throws ValidationException when `$signatureHash` is empty

### `publicInfo()`

```php
public function publicInfo(string $documentId) : array
```

Public document info (no auth).
`GET /public/documents/{document_id}`

The deliberately thin projection behind a public signing link — enough to render
"Acme Inc. sent you contract.pdf" before the recipient has proved who they are. No
signer list, no artifacts, no content. The SDK strips workspace credentials from this
request even on an authenticated client.

Request: no parameters.

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'document',
    'id' => 'document-id',
    'name' => 'agreement.pdf',
    'page_count' => '1',
    'created_by' => 'Example Signer',
]
```

@return array<string, mixed>
@throws ValidationException when `$documentId` is empty
@throws \Assinafy\SDK\Exceptions\ApiException 404 when the document does not exist

### `sendToken()`

```php
public function sendToken(
        string $documentId,
        #[\SensitiveParameter] string $recipient,
        string $channel = self::SEND_TOKEN_CHANNEL_EMAIL
    ) : array
```

Request an access token to be sent to a signer through email.
`PUT /public/documents/{document_id}/send-token` (no auth).

Only the `email` channel is supported. Pass {@see SEND_TOKEN_CHANNEL_EMAIL} — arbitrary
strings are rejected up front so a typo never reaches the API. The recipient is validated
as an email address locally for the same reason.

The published description claims "email/WhatsApp", but the schema declares no `channel`
property at all and the running API answers `400 "Canal inválido"` for `whatsapp` — the
same error a nonsense channel gets. A lowercase `sms` does pass the API's channel check,
yet always fails with `404 "Solicitação de entrada do signatário não encontrada."`: it
needs an SMS signer entry, and none can be created, because `verification_method` accepts
only Email/Whatsapp/DigitalCertificate and `notification_methods` only Email/Whatsapp.
The single-channel list is deliberate and live-verified; do not widen it.

Request body:
```
['recipient' => 'jane@example.com', 'channel' => 'email']
```

Both keys are mandatory. The published OpenAPI schema for this operation shows a
single optional `email` property instead; that body is rejected by the running API
with `400 "O atributo \"channel\" é obrigatório."`, so the SDK sends the pair above,
which the server accepts. Verified against the live API — do not "correct" this
toward the published schema without re-testing it.

The document must be in `pending_signature`; otherwise the API answers
`400 "O documento não está com status de assinatura pendente."`

Example response (SDK return; optional fields depend on state):
```php
[
    'document' => [
        'resource' => 'document',
        'id' => 'document-id',
        'name' => 'agreement.pdf',
        'page_count' => '1',
        'created_by' => 'Example Signer',
    ],
    'channel' => 'email',
    'recipient' => 'jane@example.com',
]
```

@param string $recipient email address to receive the one-time token
@param string $channel   delivery channel; only {@see SEND_TOKEN_CHANNEL_EMAIL}
@return array<array-key, mixed>
@throws ValidationException on an unsupported channel or a malformed recipient

### `listTags()`

```php
public function listTags(string $documentId) : array
```

List the tags currently attached to a document.
`GET /accounts/{account_id}/documents/{document_id}/tags`

Request: no parameters.

Example response (SDK return; optional fields depend on state):
```php
[
    [
        'id' => 'resource-id',
        'name' => 'Example',
        'color' => null,
        'created_at' => '2026-09-01T12:00:00Z',
        'updated_at' => '2026-09-01T12:00:00Z',
    ],
]
```

@return array<int, array<string, mixed>>
@throws ValidationException when `$documentId` is empty

### `replaceTags()`

```php
public function replaceTags(string $documentId, array $tagNames) : array
```

Replace the document's entire tag set with the given names.
`PUT /accounts/{account_id}/documents/{document_id}/tags`

Names that don't yet exist in the workspace are created automatically
(case-insensitive). An empty array detaches all tags — that is the difference from
{@see self::appendTags()}, which never removes anything.

The request addresses tags by **name**; the response contains the resulting tag objects.

Request body:
```
['tags' => ['contracts']]   // [] clears every tag
```

Example response (SDK return; optional fields depend on state):
```php
[
    [
        'id' => 'tag-id',
        'name' => 'contracts',
        'color' => null,
        'created_at' => '2026-09-01T12:00:00Z',
        'updated_at' => '2026-09-01T12:00:00Z',
    ],
]
```

@param array<int, string> $tagNames tag names; `[]` detaches all
@return array<int, array<string, mixed>> the document's resulting tag set
@throws ValidationException when a name is not a non-empty string

### `appendTags()`

```php
public function appendTags(string $documentId, array $tagNames) : array
```

Attach tags to a document without removing existing ones (idempotent).
`POST /accounts/{account_id}/documents/{document_id}/tags`

Unknown names are auto-created. Re-attaching a tag the document already carries is a
no-op rather than an error, which makes this safe to retry.

Request body (at least one name required):
```
['tags' => ['urgent']]
```

Example response (SDK return; optional fields depend on state):
```php
[
    [
        'id' => 'tag-id',
        'name' => 'urgent',
        'color' => null,
        'created_at' => '2026-09-01T12:00:00Z',
        'updated_at' => '2026-09-01T12:00:00Z',
    ],
]
```

@param array<int, string> $tagNames tag names to attach
@return array<int, array<string, mixed>> the document's resulting tag set

@throws ValidationException when no tag names are provided, or one is not a
    non-empty string

### `detachTag()`

```php
public function detachTag(string $documentId, string $tagId) : array
```

Detach a single tag from a document (the tag itself is not deleted).
`DELETE /accounts/{account_id}/documents/{document_id}/tags/{tag_id}`

Note the asymmetry with {@see self::appendTags()}: attaching takes tag **names**,
detaching takes a tag **ID** — read it from {@see self::listTags()}. To remove the
tag from the workspace entirely, use {@see TagResource::delete()} instead.

Request: no body.

Example response (SDK return; optional fields depend on state):
```php
[
    'detached' => true,
]
```

@return array<string, mixed>
@throws ValidationException when either identifier is empty

### `createFromTemplate()`

```php
public function createFromTemplate(
        string $templateId,
        #[\SensitiveParameter] array $signers,
        #[\SensitiveParameter] array $options = []
    ) : array
```

Create a document from a template.
`POST /accounts/{account_id}/templates/{template_id}/documents`

Instantiates a prepared template — its field placements are reused, so no `entries`
are needed here. Each signer binds to one of the template's `roles`; read the role IDs
from {@see TemplateResource::get()}. Unlike {@see self::upload()} this creates the
document *and* dispatches its assignment in one call.

Signer roles and field placements are configured in the Assinafy web app; a template
created through the API carries only the default `Editor` role.

Request body:
```
[
  'signers' => [
    [
      'role_id'              => 'role-id',  // required
      'id'                   => 'signer-id',   // required
      'verification_method'  => 'Email',
      'notification_methods' => ['Email'],
      'step'                 => 1,
    ],
  ],
  'editor_fields' => [
    ['field_id' => 'field-id', 'value' => 'Acme Inc.'],
  ],
  'name'       => 'agreement.pdf',
  'message'    => 'Please sign this contract',
  'expires_at' => '2026-12-31T23:59:59Z',
  'tags'       => [],
]
```

Example response shape (SDK return; relationship expansion depends on document state):
```php
[
    'resource' => 'document',
    'id' => 'document-id',
    'account_id' => 'account-id',
    'template_id' => 'template-id',
    'name' => 'agreement.pdf',
    'status' => 'metadata_ready',
    'artifacts' => [
        'original' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/download/original',
        'thumbnail' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/thumbnail',
    ],
    'is_closed' => false,
    'signing_url' => 'https://app-sandbox.assinafy.com.br/sign/signing-token',
    'decline_reason' => null,
    'declined_by' => null,
    'tags' => [],
    'created_at' => '2026-09-01T12:00:00Z',
    'updated_at' => '2026-09-01T12:00:00Z',
    'assignment' => null,
    'pages' => [
        [
            'id' => 'page-id',
            'number' => 1,
            'height' => 1651,
            'width' => 1275,
            'download_url' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/pages/page-id/download',
        ],
    ],
]
```

@param array<int, array<string, mixed>> $signers each entry: `{ role_id, id,
    verification_method?, notification_methods?, step? }`
@param array<string, mixed>             $options optional `name`, `message`, `editor_fields`,
    `expires_at`, and `tags`
@return array<string, mixed> the created document
@throws ValidationException when `$signers` is empty or an entry is malformed

### `estimateCostFromTemplate()`

```php
public function estimateCostFromTemplate(
        string $templateId,
        #[\SensitiveParameter] array $signers
    ) : array
```

Estimate cost of creating a document from a template.
`POST /accounts/{account_id}/templates/{template_id}/documents/estimate-cost`

Read-only: nothing is created and no credits are spent. Signer IDs are not needed —
price depends only on `role_id` plus the verification/notification channels, so you
can quote a workflow before the signers exist.

Request body:
```
[
  'signers' => [
    [
      'role_id'              => 'role-id',  // required
      'verification_method'  => 'Email',
      'notification_methods' => ['Email'],
    ],
  ],
]
```

Example response (SDK return; optional fields depend on state):
```php
[
    'documents' => 1,
    'credits' => 0,
    'needs_extra_document' => false,
    'extra_document_cost' => 0,
    'total_credits' => 0,
    'breakdown' => [],
    'document_balance' => 66,
    'credit_balance' => 0,
    'has_sufficient_resources' => true,
    'blocking_reason' => null,
    'message' => null,
]
```

Check `has_sufficient_resources` before creating; `blocking_reason` is a
machine-readable code and `message` is localised for display.

@param array<int, array<string, mixed>> $signers each entry: `{ role_id,
    verification_method?, notification_methods? }`
@return array<string, mixed>
@throws ValidationException when `$signers` is empty or an entry is malformed

### `waitUntilReady()`

```php
public function waitUntilReady(string $documentId, int $maxWaitSeconds = 60, int $pollIntervalSeconds = 2) : array
```

Poll `GET /documents/{id}` until the document reaches a usable status.

Client-side helper, not an API endpoint. Page rendering after {@see self::upload()}
is asynchronous, and an assignment cannot be created until it finishes — this bridges
that gap:
```php
$document = $client->documents()->upload('/path/contract.pdf');
$ready    = $client->documents()->waitUntilReady($document['id']);
```

Returns as soon as `status` is one of {@see self::READY_STATUSES}, throws on any of
{@see self::FAILURE_STATUSES}, and otherwise sleeps `$pollIntervalSeconds` and retries.
The deadline is checked between calls; an in-flight request is bounded separately by
the transport timeout on {@see \Assinafy\SDK\Configuration}, so the worst-case wall
time is `$maxWaitSeconds` plus one request timeout.

Response: the same payload as {@see self::get()}, once it is ready.

Request: path parameters shown above; no request body.

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'document',
    'id' => 'document-id',
    'account_id' => 'account-id',
    'template_id' => null,
    'name' => 'agreement.pdf',
    'status' => 'metadata_ready',
    'artifacts' => [
        'original' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/download/original',
        'thumbnail' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/thumbnail',
    ],
    'is_closed' => false,
    'signing_url' => 'https://app-sandbox.assinafy.com.br/sign/signing-token',
    'decline_reason' => null,
    'declined_by' => null,
    'tags' => [],
    'created_at' => '2026-09-01T12:00:00Z',
    'updated_at' => '2026-09-01T12:00:00Z',
    'assignment' => null,
    'pages' => [
        [
            'id' => 'page-id',
            'number' => 1,
            'height' => 1651,
            'width' => 1275,
            'download_url' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/pages/page-id/download',
        ],
    ],
]
```

@param int $maxWaitSeconds      total budget before giving up
@param int $pollIntervalSeconds delay between polls; the last sleep is trimmed so the
    helper never overshoots the deadline
@return array<string, mixed> the ready document
@throws ValidationException when either interval is not a positive integer
@throws \RuntimeException on a terminal failure status or on timeout

### `isFullySigned()`

```php
public function isFullySigned(string $documentId) : bool
```

`true` once every signer has completed, including while certification is in progress.

Client-side helper over {@see self::get()} — costs one API call. True for `ready`,
`certificating` and `certificated`, so it answers "is everyone done signing?" rather
than "is the certified PDF downloadable?". For the latter, compare `status` against
{@see self::STATUS_CERTIFICATED} directly.

Request/Response: as {@see self::get()}; only `status` is read.

@throws ValidationException when `$documentId` is empty
@throws \Assinafy\SDK\Exceptions\ApiException 404 when the document does not exist

### `getSigningProgress()`

```php
public function getSigningProgress(string $documentId) : array
```

Return a signed/total/percentage summary derived from the document's assignment.

Client-side helper over {@see self::get()} — costs one API call, and derives the
counts from the assignment's `items`/`signers` rather than a dedicated endpoint.

Request: as {@see self::get()}; only `status` and `assignment` are read.

Response (computed locally, ready for a progress bar):
```
['signed' => 1, 'total' => 3, 'pending' => 2, 'percentage' => 33.33]
```

Once the document reaches a fully-signed status the counts are forced to 100% —
the API prunes assignment items after certification, so counting them would
otherwise regress to 0%.

A document with no assignment yields `total => 0` and `percentage => 0.0`.

@return array{signed:int,total:int,pending:int,percentage:float}
@throws ValidationException when `$documentId` is empty

### `assertUploadable()`

```php
public static function assertUploadable(#[\SensitiveParameter] string $filePath) : void
```

Assert a file can be uploaded as a document or template: it must be a readable
PDF with a header and end marker, and not exceed the 25 MB API limit. Shared with
{@see TemplateResource::create()} so both upload paths enforce identical constraints.

@throws ValidationException when the file is missing, unreadable, invalid, or too large

### `assertArtifact()`

```php
public static function assertArtifact(string $artifact) : void
```

Assert that `$artifact` is one of the documented artifact names. Shared with
{@see SignerDocumentResource::download()} so both download paths validate identically.

@throws ValidationException on an unknown artifact name

## `Resources/FieldResource`

### `create()`

```php
public function create(string $type, string $name, array $options = []) : array
```

Create a field definition.
`POST /accounts/{account_id}/fields`

Defines a reusable input for the workspace. The definition is what a `collect`
assignment places on a page — see {@see AssignmentResource::create()} `entries`.

Typed fields (`cpf`, `cnpj`, `email`, `date`, …) validate their own format; add `regex`
only to narrow a `text` field further.

Request body:
```
[
  'type'        => 'text',            // required — a code from types()
  'name'        => 'Job title',       // required — the label the signer sees
  'regex'       => '^[A-Z].*$',       // optional extra constraint
  'is_required' => true,              // optional, defaults to false
]
```

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'field_definition',
    'id' => 'field-id',
    'name' => 'Job title',
    'type' => 'text',
    'regex' => '^[A-Z].*$',
    'is_pre_defined' => false,
    'is_active' => true,
    'is_required' => true,
    'is_standard' => false,
    'is_read_only' => false,
    'is_visible' => true,
]
```

@param string               $type    field type code (see {@see types()})
@param string               $name    label shown for the input
@param array<string, mixed> $options optional `regex` and `is_required`
@return array<string, mixed> the created field definition

@throws ValidationException when type or name is empty

### `list()`

```php
public function list(bool $includeInactive = false, bool $includeStandard = false) : array
```

List field definitions.
`GET /accounts/{account_id}/fields`

Defaults to the active, non-standard fields — the ones a person would pick from when
preparing a document. Not paginated; the whole set comes back at once.

Set `$includeStandard` to also get the platform's `signature`, `initial` and
`signatureDate` fields, which every `collect` assignment needs but which are hidden
from the default listing.

Request (query string): `include_inactive=true`, `include_standard=true` — each sent
only when its flag is set.

Example query (no request body):
```php
['include_inactive' => 'true', 'include_standard' => 'true']
```

Example response (SDK return; optional fields depend on state):
```php
[
    [
        'resource' => 'field_definition',
        'id' => 'field-id',
        'name' => 'Example',
        'type' => 'text',
        'regex' => null,
        'is_pre_defined' => false,
        'is_active' => true,
        'is_required' => true,
        'is_standard' => false,
        'is_read_only' => false,
        'is_visible' => true,
    ],
]
```

@param bool $includeInactive also return fields that have been deactivated
@param bool $includeStandard also return the platform's signature/initial fields
@return array<int, array<string, mixed>>

### `get()`

```php
public function get(string $fieldId) : array
```

Retrieve a single field definition.
`GET /accounts/{account_id}/fields/{field_id}`

Request: no parameters.

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'field_definition',
    'id' => 'field-id',
    'name' => 'Example',
    'type' => 'text',
    'regex' => null,
    'is_pre_defined' => false,
    'is_active' => true,
    'is_required' => true,
    'is_standard' => false,
    'is_read_only' => false,
    'is_visible' => true,
]
```

@return array<string, mixed>
@throws ValidationException when `$fieldId` is empty
@throws \Assinafy\SDK\Exceptions\ApiException 404 when the field does not exist

### `update()`

```php
public function update(string $fieldId, array $data) : array
```

Update a field definition.
`PUT /accounts/{account_id}/fields/{field_id}`

`type` is immutable — create a new field instead of retyping one. Setting
`is_active => false` retires a field from the picker without breaking documents that
already use it; that is the graceful alternative to {@see self::delete()}.

Request body (send only what changes):
```
['name' => 'Job title', 'regex' => null, 'is_active' => false]
```

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'field_definition',
    'id' => 'field-id',
    'name' => 'Job title',
    'type' => 'text',
    'regex' => null,
    'is_pre_defined' => false,
    'is_active' => false,
    'is_required' => true,
    'is_standard' => false,
    'is_read_only' => false,
    'is_visible' => true,
]
```

@param array<string, mixed> $data subset of `{ name, regex, is_active }`
@return array<string, mixed> the updated field definition
@throws ValidationException when `$data` is empty or `$fieldId` is empty

### `delete()`

```php
public function delete(string $fieldId) : array
```

Delete a field definition. A field already used in a document cannot be deleted.
`DELETE /accounts/{account_id}/fields/{field_id}`

Because a field in use is undeletable, prefer `is_active => false` via
{@see self::update()} for anything that has ever been placed on a document.

Request: no body.

Example response (SDK return; optional fields depend on state):
```php
[]
```

@return array<array-key, mixed>
@throws ValidationException when `$fieldId` is empty
@throws \Assinafy\SDK\Exceptions\ApiException 400 when the field is in use

### `validate()`

```php
public function validate(
        string $fieldId,
        #[\SensitiveParameter] mixed $value,
        #[\SensitiveParameter] ?string $signerAccessCode = null
    ) : array
```

Validate a single input value against a field definition.
`POST /accounts/{account_id}/fields/{field_id}/validate`

Runs the field's own rules (type check plus any `regex`) server-side, so a form can
give the same verdict the API will give at signing time. Punctuation is ignored for
`cpf`/`cnpj`.

A failed validation is **not** an error: the call answers 200 and reports the verdict
in `success`. Branch on `success`, not on the HTTP status.

Request body:
```
['value' => '111.444.777-35']
```

Example response (SDK return; optional fields depend on state):
```php
[
    'type' => 'text',
    'success' => true,
    'error_message' => '',
]
```
…and invalid:
```
['type' => 'cpf', 'success' => false, 'error_message' => 'CPF inválido.']
```

@param mixed       $value            the input to check
@param string|null $signerAccessCode optional signer context in addition to
                                      workspace authentication
@return array<string, mixed> `{ type, success, error_message }`
@throws ValidationException when `$fieldId` is empty or the access code is blank

### `validateMultiple()`

```php
public function validateMultiple(
        #[\SensitiveParameter] array $values,
        #[\SensitiveParameter] ?string $signerAccessCode = null
    ) : array
```

Validate several input values at once.
`POST /accounts/{account_id}/fields/validate-multiple`

One round trip for a whole form. As with {@see self::validate()}, failures come back
as 200 with `success => false` — branch on the per-entry flag, not the HTTP status.

The request body is a bare JSON **array**, not an object with a wrapper key. Results
come back in request order and each carries its `field_id`, so the same field may
appear more than once.

Request body:
```
[
  ['field_id' => 'field-id', 'value' => '111.444.777-35'],
  ['field_id' => 'field-id', 'value' => '123'],
]
```

Example response (SDK return; optional fields depend on state):
```php
[
    [
        'field_id' => 'field-id',
        'type' => 'cpf',
        'success' => false,
        'error_message' => 'Invalid CPF.',
    ],
]
```

@param array<int, array{field_id: string, value: mixed}> $values
@param string|null                                       $signerAccessCode optional signer
    context in addition to workspace authentication
@return array<int, array<string, mixed>> one verdict per input, in request order
@throws ValidationException when the access code is blank

### `types()`

```php
public function types() : array
```

List the field types supported by the platform.
`GET /field-types` (not account-scoped).

The vocabulary for the `type` argument of {@see self::create()}. `name` is a
Portuguese display label; `type` is the code to send.

`cpf` expects 11 digits. `cnpj` accepts 14 characters, where positions 1–12 may
include letters A–Z under the CNPJ Alfanumérico rule and the two check digits stay
numeric. Punctuation is ignored during validation.

Request: no parameters.

Example response (SDK return; optional fields depend on state):
```php
[
    [
        'type' => 'personName',
        'name' => 'Nome',
    ],
    [
        'type' => 'cpf',
        'name' => 'CPF',
    ],
    [
        'type' => 'phoneNumber',
        'name' => 'Número de Telefone',
    ],
    [
        'type' => 'postalCode',
        'name' => 'CEP',
    ],
    [
        'type' => 'email',
        'name' => 'E-mail',
    ],
    [
        'type' => 'cnpj',
        'name' => 'CNPJ',
    ],
    [
        'type' => 'companyName',
        'name' => 'Nome da empresa',
    ],
    [
        'type' => 'email',
        'name' => 'E-mail',
    ],
    [
        'type' => 'text',
        'name' => 'Texto',
    ],
    [
        'type' => 'number',
        'name' => 'Número',
    ],
    [
        'type' => 'date',
        'name' => 'Data',
    ],
]
```

The live list repeats `email`; de-duplicate on `type` before rendering a picker.

@return array<int, array{type: string, name: string}>

## `Resources/OAuthResource`

### `__construct()`

```php
public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] Configuration $config,
        ?LoggerInterface $logger = null,
        string $clientId = '',
        #[\SensitiveParameter] ?string $clientSecret = null,
        ?\Closure $discoveryTransport = null
    )
```

@param string      $clientId     the application's `client_id` from the Assinafy app
@param string|null $clientSecret confidential applications only; public applications
    authenticate with PKCE alone and are never issued a secret
@param (\Closure(string): HttpClientInterface)|null $discoveryTransport builds a
    credential-free client for a given origin. Defaults to a Guzzle client; tests
    inject a stub because the discovery documents live on origins the configured
    transport cannot reach.
@throws ValidationException on an empty client ID or a present-but-blank secret

### `__debugInfo()`

```php
public function __debugInfo() : array
```

Keep the client secret out of diagnostic object dumps.

@return array{client_id: string, client_type: string}

### `startAuthorization()`

```php
public function startAuthorization(
        string $redirectUri,
        array $scopes,
        #[\SensitiveParameter] array $options = []
    ) : array
```

Begin a connection: mint PKCE material and build the authorization URL.
Pure string construction — makes **no** HTTP request.

Call this once per connection attempt. A fresh `code_verifier` and `state` are
generated every time, which is the single rule that stops one attempt's material
being replayed against another. Persist the whole return value in the user's
authenticated server-side session, then redirect the browser to
`authorization_url` with a full page navigation (never an AJAX call).

Request/Response: none.

Returns:
```php
[
    'authorization_url' => 'https://auth.assinafy.com.br/oauth/authorize?response_type=code&…',
    'state' => '<43-char random string>',
    'code_verifier' => '<43-char random string>',
    'code_challenge' => '<base64url sha256 of the verifier>',
    'nonce' => '<43-char random string, only when the openid scope was requested>',
    'redirect_uri' => 'https://app.example.com/oauth/callback',
    'issuer' => 'https://auth.assinafy.com.br',
    'resource' => 'https://api.assinafy.com.br',
]
```

Feed the same array back into {@see self::handleCallback()} and
{@see self::exchangeCode()}; neither re-derives anything from it that it cannot check.

@param string             $redirectUri one of the application's registered URIs,
    character for character — `…/callback` and `…/callback/` are different
@param array<int, mixed>  $scopes      the permissions to request, e.g.
    {@see self::SCOPE_DOCUMENTS_WRITE}. The user approves all of them or none.
@param array<string, mixed> $options overrides: `state`, `code_verifier`, `nonce`,
    `issuer`, `authorization_endpoint`, `resource`. Every one has a correct default;
    supply `issuer`/`authorization_endpoint` from
    {@see self::authorizationServerMetadata()} to avoid hardcoding them.
@return array<string, string> the transaction to persist
@throws ValidationException on a non-HTTPS or fragment-bearing redirect URI, an
    empty scope list, a scope containing a space, or an out-of-grammar verifier

### `handleCallback()`

```php
public function handleCallback(
        #[\SensitiveParameter] array $query,
        #[\SensitiveParameter] array $transaction
    ) : string
```

Validate the browser's return to your redirect URI and return the authorization code.
Pure verification — makes **no** HTTP request.

This is the security-critical step of the flow. It rejects a response whose `state`
does not match the stored transaction (CSRF, or another tab's attempt) and one whose
`iss` is not the expected authorization server (a mixed-up or spoofed issuer), before
the code is ever sent anywhere. Consume the stored transaction exactly once.

Request/Response: none — `$query` is your framework's already-parsed query string.

Approved callback:
```php
['code' => '<authorization-code>', 'state' => '<state>', 'iss' => 'https://auth.assinafy.com.br']
```

Declined callback, which raises `ApiException` with message `access_denied`:
```php
['error' => 'access_denied', 'error_description' => '…', 'state' => '<state>',
 'iss' => 'https://auth.assinafy.com.br']
```

@param array<string, mixed> $query       the callback query parameters
@param array<string, mixed> $transaction the array {@see self::startAuthorization()}
    returned, loaded back from the user's session
@return string the single-use authorization code, which expires 60 seconds after approval
@throws ValidationException when the transaction is unusable, `state` does not match,
    `iss` is absent or wrong, or no code was returned
@throws ApiException when the authorization server reported an error — `getMessage()`
    is the RFC code (`access_denied`, `invalid_scope`, `invalid_request`,
    `unsupported_response_type`, `invalid_target`)

### `exchangeCode()`

```php
public function exchangeCode(
        #[\SensitiveParameter] string $code,
        #[\SensitiveParameter] array $transaction
    ) : array
```

Exchange an authorization code for tokens.
`POST /oauth/token` with `grant_type=authorization_code`

Server-side only, and only once: the code is single-use and expires 60 seconds
after approval. The `redirect_uri` and `code_verifier` must be byte-identical to
the ones the authorization request was made with, which is why the whole stored
transaction is passed rather than re-supplied by hand.

Request body (`application/x-www-form-urlencoded`; `client_secret` is omitted by
public applications):
```php
[
    'grant_type' => 'authorization_code',
    'code' => '<authorization-code>',
    'redirect_uri' => 'https://app.example.com/oauth/callback',
    'code_verifier' => '<code-verifier>',
    'client_id' => '<client-id>',
    'client_secret' => '<client-secret>',
    'resource' => 'https://api.assinafy.com.br',
]
```

Example response (flat JSON — no `data` envelope):
```php
[
    'access_token' => '<access-token>',
    'token_type' => 'Bearer',
    'expires_in' => 3600,
    'scope' => 'documents:read documents:write',
    'refresh_token' => '<refresh-token>',
    'id_token' => '<signed-id-token>',
]
```

`refresh_token` appears only when `offline_access` was requested and approved;
`id_token` only with `openid`. Read the returned `scope` instead of assuming every
requested permission was granted — `offline_access` is a request-time signal and
never appears there. Then call {@see \Assinafy\SDK\Resources\AccountResource::list()}
with the access token: an OAuth token returns exactly the one authorized workspace,
whose `data[0].id` belongs in the connection record next to the tokens.

@param array<string, mixed> $transaction the array {@see self::startAuthorization()}
    returned; `code_verifier`, `redirect_uri` and `resource` are read from it
@return array<string, mixed> `{ access_token, token_type, expires_in, scope,
    refresh_token?, id_token? }`
@throws ValidationException on an empty code or a transaction missing its verifier
    or redirect URI
@throws ApiException `invalid_grant` (expired, replayed, or mismatched code),
    `invalid_client`, `invalid_target`

### `refresh()`

```php
public function refresh(#[\SensitiveParameter] string $refreshToken) : array
```

Renew an access token without the user.
`POST /oauth/token` with `grant_type=refresh_token`

Access tokens last one hour. A refresh token is valid for 30 days, and every refresh
returns a new one with a fresh 30 days, so a connection only expires after 30 days
without a refresh; after that, the user has to reconnect.

**Every refresh retires the token it used and returns a new one.** A replayed refresh
token cannot be distinguished from a stolen one, so the server ends the entire
connection when it sees one. Hold a per-connection lock, persist the returned
`refresh_token` before doing anything else with the response, and never resend a
refresh token after an ambiguous failure (timeout, reset): re-read what you stored and,
if it is still the token you sent, ask the user to reconnect. The SDK never retries.

Request body (`application/x-www-form-urlencoded`):
```php
[
    'grant_type' => 'refresh_token',
    'refresh_token' => '<current-refresh-token>',
    'client_id' => '<client-id>',
    'client_secret' => '<client-secret>',
]
```

Example response (flat JSON — no `data` envelope):
```php
[
    'access_token' => '<new-access-token>',
    'token_type' => 'Bearer',
    'expires_in' => 3600,
    'scope' => 'documents:read documents:write',
    'refresh_token' => '<new-refresh-token>',
]
```

@return array<string, mixed> the same shape as {@see self::exchangeCode()}
@throws ValidationException on an empty refresh token
@throws ApiException `invalid_grant` when the token was already used, has expired,
    lost `offline_access`, or the user reconnected with different permissions —
    reconnect rather than retry

### `revoke()`

```php
public function revoke(
        #[\SensitiveParameter] string $token,
        ?string $tokenTypeHint = null
    ) : array
```

Disconnect: revoke an access or refresh token.
`POST /oauth/revoke`

Call this when a user disconnects in your product, instead of only deleting your
copy. Revoking the refresh token ends the whole connection, so pass that when you
hold one. The endpoint answers `200` for every token outcome — revoked, already
revoked, unknown, malformed — so it can never be used to probe whether a token
exists, and a success here is not evidence the token was real. Only failed client
authentication answers `401`.

Request body (`application/x-www-form-urlencoded`):
```php
[
    'token' => '<refresh-or-access-token>',
    'token_type_hint' => 'refresh_token',
    'client_id' => '<client-id>',
    'client_secret' => '<client-secret>',
]
```

Response: `200` with an empty body, returned as `[]`.

@param string      $token         the refresh token when one is stored, else the access token
@param string|null $tokenTypeHint {@see self::TOKEN_TYPE_HINT_REFRESH} or
    {@see self::TOKEN_TYPE_HINT_ACCESS}; omit when unsure
@return array<string, mixed> an empty array on success
@throws ValidationException on an empty token or an unknown hint
@throws ApiException `invalid_client` on failed client authentication

### `userinfo()`

```php
public function userinfo(#[\SensitiveParameter] string $accessToken) : array
```

Read the OpenID Connect claims of the user who authorized the token.
`GET /oauth/userinfo`

Requires the `openid` scope; `name` additionally requires `profile` and `email`
requires `email`. Use this for the user's profile rather than decoding the
`id_token`, which needs full RS256/JWKS validation by a maintained OIDC library
before any claim in it can be trusted.

Request: no body. The token travels in `Authorization: Bearer`, which is the only
accepted method — an OAuth token sent as `X-Api-Key` or in the query string is refused.

Example response (flat OIDC claims — no `data` envelope):
```php
[
    'sub' => 'd6zqpbyog2v3xvxerwn8la94',
    'name' => 'Example User',
    'email' => 'person@example.com',
    'email_verified' => true,
]
```

`sub` is the user's stable identifier; the optional claims are null or absent when
the matching scope was not granted.

@return array<string, mixed> `{ sub, name?, email?, email_verified? }`
@throws ValidationException on an empty access token
@throws ApiException 401 when the token expired or was revoked; 403 when `openid`
    was not granted. Unlike the token and revocation endpoints, these errors arrive
    in the ordinary API envelope rather than as flat `{ error, error_description }`.

### `protectedResourceMetadata()`

```php
public function protectedResourceMetadata() : array
```

Read this API's protected-resource metadata.
`GET /.well-known/oauth-protected-resource` — at the API origin, outside `/v1`.

RFC 9728. Names the authorization server that issues tokens for this API and the
scopes it accepts, and is what the `resource_metadata="…"` parameter of a
`WWW-Authenticate` challenge points at. Start an integration here, then read the
authorization server's own document from `authorization_servers[0]`.

Uses a separate credential-free client for the API origin: this document sits above
the `/v1` prefix the configured transport is pinned to, and carries no workspace
credential.

Request: none.

Example response (flat JSON — no `data` envelope):
```php
[
    'resource' => 'https://api.assinafy.com.br',
    'authorization_servers' => ['https://auth.assinafy.com.br'],
    'scopes_supported' => [
        'documents:read', 'documents:write', 'templates:read', 'templates:write',
        'account:read', 'webhooks:write', 'openid', 'profile', 'email',
    ],
    'bearer_methods_supported' => ['header'],
]
```

`scopes_supported` deliberately omits `offline_access`: asking for a refresh token
is a client concern, not something this resource is protected by.

@return array<string, mixed> `{ resource, authorization_servers, scopes_supported,
    bearer_methods_supported }`
@throws ApiException when the deployment does not serve OAuth — sandbox does not

### `authorizationServerMetadata()`

```php
public function authorizationServerMetadata(?string $issuer = null) : array
```

Read the authorization server's metadata.
`GET {issuer}/.well-known/oauth-authorization-server`

RFC 8414. This is the document to configure from rather than hardcoding endpoint
URLs: pass its `issuer` and `authorization_endpoint` into
{@see self::startAuthorization()}. It is served **only** by the authorization
server, never by this API, so it is fetched with a separate credential-free client
for that origin.

Request: none.

Example response (flat JSON — no `data` envelope):
```php
[
    'issuer' => 'https://auth.assinafy.com.br',
    'authorization_endpoint' => 'https://auth.assinafy.com.br/oauth/authorize',
    'token_endpoint' => 'https://api.assinafy.com.br/v1/oauth/token',
    'revocation_endpoint' => 'https://api.assinafy.com.br/v1/oauth/revoke',
    'userinfo_endpoint' => 'https://api.assinafy.com.br/v1/oauth/userinfo',
    'jwks_uri' => 'https://auth.assinafy.com.br/.well-known/jwks.json',
    'scopes_supported' => [
        'documents:read', 'documents:write', 'templates:read', 'templates:write',
        'account:read', 'webhooks:write', 'openid', 'profile', 'email', 'offline_access',
    ],
    'response_types_supported' => ['code'],
    'grant_types_supported' => ['authorization_code', 'refresh_token'],
    'code_challenge_methods_supported' => ['S256'],
    'token_endpoint_auth_methods_supported' => ['client_secret_post', 'none'],
    'authorization_response_iss_parameter_supported' => true,
    'client_id_metadata_document_supported' => true,
]
```

Validate the returned `issuer` against the one you expect before sending a secret
to any endpoint it names.

@param string|null $issuer the authorization server origin; defaults to
    {@see self::DEFAULT_ISSUER}, or pass `authorization_servers[0]` from
    {@see self::protectedResourceMetadata()}
@return array<string, mixed> the RFC 8414 metadata object
@throws ValidationException on a non-HTTPS issuer
@throws ApiException when the issuer does not serve the document

### `createCodeVerifier()`

```php
public static function createCodeVerifier() : string
```

Generate an RFC 7636 code verifier: 43 characters from the unreserved set.

A new one is required for every connection attempt. {@see self::startAuthorization()}
calls this for you; use it directly only when your framework owns the session material.

### `codeChallenge()`

```php
public static function codeChallenge(#[\SensitiveParameter] string $codeVerifier) : string
```

Derive the S256 challenge sent to the authorization server from a verifier.

### `createState()`

```php
public static function createState() : string
```

Generate the random per-attempt `state` that protects the callback against CSRF.

## `Resources/SignerDocumentResource`

### `current()`

```php
public function current(string $signerId, #[\SensitiveParameter] string $accessCode) : array
```

Get the document tied to the signer's access code, without page content.
`GET /signers/{signer_id}/document?signer-access-code={code}`

Useful right after the signer opens the link, to show which document is about
to be signed before asking them to verify their code. Does not require the
signer to have verified or confirmed their data yet — that is what distinguishes it
from {@see SignerSessionResource::currentDocument()}, which needs a verified session
and returns the assignment items too.

Request (query string): `signer-access-code`.

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'document',
    'id' => 'document-id',
    'account_id' => 'account-id',
    'template_id' => null,
    'name' => 'agreement.pdf',
    'status' => 'pending_signature',
    'artifacts' => [
        'original' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/download/original',
        'thumbnail' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/thumbnail',
    ],
    'is_closed' => false,
    'signing_url' => 'https://app-sandbox.assinafy.com.br/sign/signing-token',
    'decline_reason' => null,
    'declined_by' => null,
    'tags' => [],
    'created_at' => '2026-09-01T12:00:00Z',
    'updated_at' => '2026-09-01T12:00:00Z',
    'assignment' => [
        'id' => 'assignment-id',
        'sender_email' => 'person@example.com',
        'method' => 'collect',
        'expires_at' => null,
        'message' => null,
        'signers' => [
            [
                'id' => 'signer-id',
                'full_name' => 'Example Signer',
                'email' => 'person@example.com',
                'whatsapp_phone_number' => null,
                'government_id' => null,
                'has_accepted_terms' => false,
                'completed' => false,
                'notification_history' => [
                    [
                        'event' => 'signature_request',
                        'status' => 'sent',
                        'error_code' => null,
                        'error_message' => null,
                        'sent_at' => '2026-09-01T12:00:00Z',
                        'failed_at' => null,
                    ],
                ],
                'verification_method' => 'Email',
                'notification_methods' => ['Email'],
                'step' => 1,
                'notified' => true,
            ],
        ],
        'copy_receivers' => [],
        'items' => [
            [
                'id' => 'assignment-item-id',
                'page' => [
                    'id' => 'page-id',
                    'number' => 1,
                    'height' => 1651,
                    'width' => 1275,
                    'download_url' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/pages/page-id/download',
                ],
                'signer' => [
                    'id' => 'signer-id',
                    'full_name' => 'Example Signer',
                    'email' => 'person@example.com',
                    'whatsapp_phone_number' => null,
                    'government_id' => null,
                    'has_accepted_terms' => false,
                ],
                'field' => [
                    'id' => 'field-id',
                    'name' => 'Assinatura',
                    'type' => 'signature',
                    'regex' => null,
                    'is_pre_defined' => true,
                    'is_active' => true,
                    'is_required' => true,
                    'is_standard' => true,
                    'is_read_only' => false,
                    'is_visible' => true,
                ],
                'display_settings' => [
                    'top' => 10,
                    'left' => 10,
                    'width' => 240,
                    'height' => 60,
                    'fontSize' => 18,
                ],
                'value' => null,
                'completed' => false,
            ],
        ],
        'summary' => [
            'signer_count' => 1,
            'completed_count' => 0,
            'signers' => [
                [
                    'id' => 'signer-id',
                    'full_name' => 'Example Signer',
                    'email' => 'person@example.com',
                    'whatsapp_phone_number' => null,
                    'government_id' => null,
                    'has_accepted_terms' => false,
                    'completed' => false,
                ],
            ],
        ],
        'signing_urls' => [
            [
                'signer_id' => 'signer-id',
                'url' => 'https://app-sandbox.assinafy.com.br/sign/signing-token?email=signer%40example.com',
            ],
        ],
    ],
    'pages' => [
        [
            'id' => 'page-id',
            'number' => 1,
            'height' => 1651,
            'width' => 1275,
            'download_url' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/pages/page-id/download',
        ],
    ],
]
```

@return array<string, mixed>
@throws ValidationException when the signer ID or access code is blank
@throws \Assinafy\SDK\Exceptions\ApiException 401 when the access code is wrong

### `list()`

```php
public function list(
        string $signerId,
        #[\SensitiveParameter] string $accessCode,
        array $filters = []
    ) : array
```

List the signer's documents.
`GET /signers/{signer_id}/documents?signer-access-code={code}`

Everything this signer has been asked to sign across the workspace — the backing call
for a "my documents" inbox. Combine with {@see self::signMultiple()} to let a signer
clear several at once.

Request (query string): `page`, `per-page`, `signer-access-code`.

Example query (no request body):
```php
['page' => 1, 'per-page' => 20, 'signer-access-code' => '<access-code>']
```

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
    'data' => [
        [
            'id' => 'document-id',
            'account_id' => 'account-id',
            'template_id' => null,
            'name' => 'agreement.pdf',
            'status' => 'metadata_ready',
            'artifacts' => [
                'original' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/download/original',
                'thumbnail' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/thumbnail',
            ],
            'is_closed' => false,
            'signing_url' => 'https://app-sandbox.assinafy.com.br/sign/signing-token',
            'decline_reason' => null,
            'declined_by' => null,
            'tags' => [],
            'created_at' => '2026-09-01T12:00:00Z',
            'updated_at' => '2026-09-01T12:00:00Z',
            'assignment' => null,
            'pages' => [
                [
                    'id' => 'page-id',
                    'number' => 1,
                    'height' => 1651,
                    'width' => 1275,
                    'download_url' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/pages/page-id/download',
                ],
            ],
        ],
    ],
    'pagination' => [
        'current_page' => 1,
        'page_count' => 1,
        'per_page' => 20,
        'total_count' => 1,
    ],
]
```

@param array<string, scalar> $filters optional `page` and `per-page`
@return array{status?: int, message?: string, data?: array<int, array<string, mixed>>,
    pagination?: array{current_page: int, page_count: int, per_page: int, total_count: int}}
@throws ValidationException when `page`/`per-page` are not integers, or the access
    code is blank

### `search()`

```php
public function search(
        string $signerId,
        #[\SensitiveParameter] string $accessCode,
        string $term
    ) : array
```

Search the signer's documents using the API's compact representation.
`GET /signers/{signer_id}/documents/search`

Matches `$term` against the document name. Unlike {@see self::list()} this route is
not paginated and returns a flat array rather than the paginated envelope.

Request (query string): `search`, `signer-access-code`.

Example query (no request body):
```php
['search' => 'agreement', 'signer-access-code' => '<access-code>']
```

Example response (SDK return; optional fields depend on state):
```php
[
    [
        'resource' => 'document',
        'id' => 'document-id',
        'account_id' => 'account-id',
        'template_id' => null,
        'name' => 'document.pdf',
        'status' => 'metadata_ready',
        'artifacts' => [
            'original' => 'https://api.assinafy.com.br/v1/documents/doc1/download/original',
        ],
        'is_closed' => false,
        'signing_url' => 'https://api.assinafy.com.br/v1/sign/document-id',
        'decline_reason' => null,
        'declined_by' => null,
        'tags' => [
            [
                'id' => 'document-id',
                'name' => 'Example',
            ],
        ],
        'assignment' => null,
        'pages' => [
            [
                'id' => 'document-page-id',
                'number' => 1,
                'height' => 2100,
                'width' => 1275,
                'download_url' => 'https://api.assinafy.com.br/v1/documents/document-id/pages/1a/download',
            ],
        ],
        'created_at' => '2026-06-03T03:54:16Z',
        'updated_at' => '2026-06-03T03:54:16Z',
    ],
]
```

@param string $term substring to match against the document name
@return array<int, array<string, mixed>>
@throws ValidationException when the signer ID or access code is blank

### `signMultiple()`

```php
public function signMultiple(#[\SensitiveParameter] string $accessCode, array $documentIds) : array
```

Sign several virtual-method documents in one call.
`PUT /signers/documents/sign-multiple?signer-access-code={code}`

The bulk "sign all" action for an inbox built on {@see self::list()}. Applies only to
`virtual` assignments — `collect` documents carry per-field input and must go through
{@see SignerSessionResource::sign()} one at a time.

Request:
```
PUT /signers/documents/sign-multiple?signer-access-code=<access code>
['document_ids' => ['first-document-id', 'second-document-id']]
```

Example response (SDK return; optional fields depend on state):
```php
[]
```

@param array<int, string> $documentIds documents to sign; re-indexed so a filtered
    PHP array still encodes as a JSON list
@return array<array-key, mixed>

@throws ValidationException when no document IDs are provided, or the access code is blank

### `declineMultiple()`

```php
public function declineMultiple(
        #[\SensitiveParameter] string $accessCode,
        array $documentIds,
        #[\SensitiveParameter] string $reason
    ) : array
```

Decline several documents in one call.
`PUT /signers/documents/decline-multiple?signer-access-code={code}`

The bulk counterpart to {@see self::signMultiple()}. One reason covers every document
in the batch, and each declined document becomes `rejected_by_signer` — terminal for
its remaining signers too.

Request:
```
PUT /signers/documents/decline-multiple?signer-access-code=<access code>
[
  'document_ids'   => ['document-id'],
  'decline_reason' => 'The payment terms are wrong',
]
```

Example response (SDK return; optional fields depend on state):
```php
[]
```

@param array<int, string> $documentIds documents to decline; re-indexed so a filtered
    PHP array still encodes as a JSON list
@param string             $reason      applied to every document in the batch
@return array<array-key, mixed>

@throws ValidationException when no document IDs or no reason is provided, or the
    access code is blank

### `download()`

```php
public function download(
        string $signerId,
        string $documentId,
        #[\SensitiveParameter] string $accessCode,
        string $artifact = DocumentResource::ARTIFACT_ORIGINAL
    ) : string
```

Download one of the signer's document artifacts (raw binary body).
`GET /signers/{signer_id}/documents/{document_id}/download/{artifact_name}?signer-access-code={code}`

The signer-authenticated counterpart to {@see DocumentResource::download()}: same
artifacts, but reachable with a `signer-access-code` instead of a workspace API key.
This is how a signer keeps a copy of what they signed.

Defaults to `original`, unlike {@see DocumentResource::download()} which defaults to
`certificated` — a signer usually wants the copy before certification completes.

Request (query string): `signer-access-code`. The artifact is a path segment.

Response: the raw bytes (`application/pdf`, or `application/zip` for `bundle`) —
**not** the JSON envelope:
```php
file_put_contents('my-copy.pdf', $client->signerDocuments()->download(
    $signerId, $documentId, $accessCode, DocumentResource::ARTIFACT_CERTIFICATED,
));
```

@param string $artifact one of the {@see DocumentResource} `ARTIFACT_*` constants
@return string raw file bytes
@throws ValidationException on an unknown artifact, a blank identifier, or a blank
    access code
@throws \Assinafy\SDK\Exceptions\ApiException 404 when the artifact is not ready yet

## `Resources/SignerResource`

### `create()`

```php
public function create(
        #[\SensitiveParameter] string $fullName,
        #[\SensitiveParameter] ?string $email = null,
        #[\SensitiveParameter] ?string $whatsappPhoneNumber = null
    ) : array
```

Create a signer.
`POST /accounts/{account_id}/signers`

Only `full_name` is required by the API. `email` and `whatsapp_phone_number`
are optional but at least one is needed for any verification/notification — a signer
with neither can never be notified.

Signers are workspace-level and reusable across documents. To avoid duplicates, look
first with {@see self::findByEmail()}.

Phone numbers are normalised to E.164 locally: a leading `+` and country code are
mandatory, so a local number is never silently assigned to the wrong country.

Request body:
```
[
  'full_name'             => 'Jane Doe',        // required
  'email'                 => 'jane@example.com',
  'whatsapp_phone_number' => '+5548999990000',  // E.164
]
```

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'signer',
    'id' => 'signer-id',
    'full_name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'whatsapp_phone_number' => '+5548999990000',
    'government_id' => null,
    'has_accepted_terms' => false,
]
```

`government_id` cannot be set here — add it afterwards with {@see self::update()},
which digital-certificate signing requires.

@return array<string, mixed> the created signer
@throws ValidationException on an empty name, a malformed email, or a phone number
    without a country code

### `get()`

```php
public function get(string $signerId) : array
```

Retrieve a signer.
`GET /accounts/{account_id}/signers/{signer_id}`

Request: no parameters.

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'signer',
    'id' => 'signer-id',
    'full_name' => 'Example Signer',
    'email' => 'person@example.com',
    'whatsapp_phone_number' => null,
    'government_id' => null,
    'has_accepted_terms' => false,
]
```

@return array<string, mixed>
@throws ValidationException when `$signerId` is empty
@throws \Assinafy\SDK\Exceptions\ApiException 404 when the signer does not exist

### `list()`

```php
public function list(
        int $page = 1,
        int $perPage = 20,
        #[\SensitiveParameter] ?string $search = null
    ) : array
```

List signers in the workspace.
`GET /accounts/{account_id}/signers`

`$search` matches name and email substrings. For an exact email lookup use
{@see self::findByEmail()}, which pages through and compares case-insensitively.

Request (query string): `page`, `per-page`, and `search` when supplied.

Example query (no request body):
```php
['page' => 1, 'per-page' => 20, 'search' => 'Jane']
```

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
    'data' => [
        [
            'id' => 'signer-id',
            'full_name' => 'Example Signer',
            'email' => 'person@example.com',
            'whatsapp_phone_number' => null,
            'government_id' => null,
            'has_accepted_terms' => false,
        ],
    ],
    'pagination' => [
        'current_page' => 1,
        'page_count' => 1,
        'per_page' => 20,
        'total_count' => 1,
    ],
]
```

@return array{status?: int, message?: string, data?: array<int, array<string, mixed>>,
    pagination?: array{current_page: int, page_count: int, per_page: int, total_count: int}}
    full envelope with pagination lifted from response headers
@throws ValidationException when `$page` < 1 or `$perPage` is outside 1–100

### `update()`

```php
public function update(string $signerId, #[\SensitiveParameter] array $data) : array
```

Update a signer.
`PUT /accounts/{account_id}/signers/{signer_id}`

Send only the keys you want to change. This is the only way to set `government_id`,
which must be present **before** a digital-certificate assignment can be created for
this signer.

`whatsapp_phone_number` is normalised to E.164 locally, exactly as in
{@see self::create()}.

Request body (at least one key required):
```
[
  'full_name'             => 'Jane A. Doe',
  'email'                 => 'jane@example.com',
  'whatsapp_phone_number' => '+5548999990000',
  'government_id'         => '11144477735',
]
```

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'signer',
    'id' => 'signer-id',
    'full_name' => 'Jane A. Doe',
    'email' => 'jane@example.com',
    'whatsapp_phone_number' => '+5548999990000',
    'government_id' => null,
    'has_accepted_terms' => false,
]
```

`government_id` may be omitted or masked in the response.

@param array<string, mixed> $data subset of { full_name, email, whatsapp_phone_number,
    government_id }
@return array<string, mixed> the updated signer
@throws ValidationException when `$data` is empty or any supplied value is malformed

### `delete()`

```php
public function delete(string $signerId) : array
```

Delete a signer.
`DELETE /accounts/{account_id}/signers/{signer_id}`

Removes the signer from the workspace directory. Documents they have already signed
keep their record of the signature — the recorded signature history is preserved.

Request: no body.

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
    'data' => [],
]
```

@return array<array-key, mixed> the raw envelope
@throws ValidationException when `$signerId` is empty
@throws \Assinafy\SDK\Exceptions\ApiException 400 when an assignment is still pending

### `findByEmail()`

```php
public function findByEmail(#[\SensitiveParameter] string $email) : ?array
```

Find a signer by email by searching the workspace.
Returns the first exact-match (case-insensitive) signer, or null if none found.

Client-side helper over `GET /accounts/{account_id}/signers`, not a dedicated
endpoint. It pages through the `search` results 100 at a time and compares each
`email` exactly, because the API's `search` is a substring match and would otherwise
return `other-jane@example.com` for `jane@example.com`.

Use it to keep {@see self::create()} idempotent:
```php
$signer = $client->signers()->findByEmail('jane@example.com')
    ?? $client->signers()->create('Jane Doe', 'jane@example.com');
```

Costs one request per 100 matches — usually one.

Example query (no request body):
```php
['search' => 'jane@example.com', 'page' => 1, 'per-page' => 100]
```

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'signer',
    'id' => 'signer-id',
    'full_name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'whatsapp_phone_number' => null,
    'government_id' => null,
    'has_accepted_terms' => false,
]
```

@return array<string, mixed>|null the matching signer, or null when there is none
@throws ValidationException on a malformed email

### `normalizePhoneNumber()`

```php
public static function normalizePhoneNumber(#[\SensitiveParameter] string $phone) : string
```

Normalize explicitly international phone input into E.164 (e.g. `+5548999990000`).
Common visual separators are removed, but a leading `+` and country code are
mandatory so a local number is never silently assigned to the wrong country.

## `Resources/SignerSessionResource`

### `self()`

```php
public function self(#[\SensitiveParameter] string $accessCode) : array
```

Get the signer's own profile.
`GET /signers/self?signer-access-code={code}`

The signer's "who am I". The `has_signature` / `has_initial` flags tell you whether
they still need to draw one before signing — see {@see self::uploadSignature()}.

Request (query string): `signer-access-code`.

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'signer',
    'id' => 'signer-id',
    'full_name' => 'John Signer',
    'email' => 'person@example.com',
    'whatsapp_phone_number' => '+5548999990000',
    'has_accepted_terms' => false,
    'has_signature' => true,
    'has_initial' => false,
    'is_signature_reusable' => false,
]
```

@return array<string, mixed>
@throws ValidationException when the access code is blank
@throws \Assinafy\SDK\Exceptions\ApiException 401 when the access code is wrong

### `acceptTerms()`

```php
public function acceptTerms(#[\SensitiveParameter] string $accessCode) : array
```

Accept terms of use.
`PUT /signers/accept-terms`

A prerequisite for signing: until this is called, `has_accepted_terms` on
{@see self::self()} stays false and the signing routes refuse. Record it once per
signer, not per document.

Request: no body; the credential travels as the `signer-access-code` query parameter.

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
]
```

@return array<array-key, mixed>
@throws ValidationException when the access code is blank
@throws \Assinafy\SDK\Exceptions\ApiException 401 when the access code is wrong

### `verifyCode()`

```php
public function verifyCode(
        #[\SensitiveParameter] string $accessCode,
        #[\SensitiveParameter] string $verificationCode
    ) : array
```

Verify the one-time code sent to the signer's email/WhatsApp.
`POST /verify`

Unlocks the signing flow. `$verificationCode` is the OTP from the notification;
`$accessCode` is the session credential delivered through the signer channel.
It is separate from both the verification code and the signing URL path token.

Request — the code goes in the body under a **kebab-case** key, while the access code
travels as a query parameter:
```
POST /verify?signer-access-code=<access code>
['verification-code' => '482913']
```

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
]
```

@return array<array-key, mixed>
@throws ValidationException when the access code is blank
@throws \Assinafy\SDK\Exceptions\ApiException 400 on a wrong or expired code

### `confirmData()`

```php
public function confirmData(
        string $documentId,
        #[\SensitiveParameter] string $accessCode,
        #[\SensitiveParameter] array $data
    ) : array
```

Confirm the signer's name, email, government ID, or terms acceptance.
`PUT /documents/{documentId}/signers/confirm-data?signer-access-code={code}`

The `signer-access-code` is sent as a query parameter, the rest of the data
goes in the JSON body.

Current API prose also requires `has_accepted_terms: true` here before a
digital-certificate signer can open the document, although that field is absent
from this operation's request schema.

The signer confirms the identity details that will be printed on the certificate page.

Request:
```
PUT /documents/{documentId}/signers/confirm-data?signer-access-code=<access code>
[
  'full_name'          => 'Jane Doe',
  'email'              => 'jane@example.com',
  'government_id'      => '11144477735',
  'has_accepted_terms' => true,
]
```

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'signer',
    'id' => 'signer-id',
    'full_name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'whatsapp_phone_number' => '+5548999990000',
    'has_accepted_terms' => true,
]
```

@param array<string, mixed> $data subset of { full_name, email, government_id,
    has_accepted_terms }
@return array<string, mixed> the confirmed signer data
@throws ValidationException when the document ID or access code is blank

### `uploadSignature()`

```php
public function uploadSignature(
        #[\SensitiveParameter] string $accessCode,
        string $type,
        #[\SensitiveParameter] string $imageBytes,
        string $mimeType = 'image/png',
        ?bool $reuse = null
    ) : array
```

Upload a signature or initial image (PNG/JPEG bytes).
`POST /signature?type=signature|initial&signer-access-code={code}`

The drawn or typed mark that gets stamped onto the document. Sent as a **raw image
body** with a `Content-Type` of `image/png` or `image/jpeg` — not multipart, and not
base64. Pass the bytes themselves:
```php
$client->signerSession()->uploadSignature(
    $accessCode,
    SignerSessionResource::TYPE_SIGNATURE,
    file_get_contents('signature.png'),
);
```

`$reuse = true` stores the image for the signer's later documents, so they don't have
to redraw it; that is what `is_signature_reusable` on {@see self::self()} reports.

Request (query string): `type=signature|initial`, `signer-access-code`, and `reuse`
when supplied. Body: the raw image bytes.

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
]
```

@param string    $type      {@see self::TYPE_SIGNATURE} or {@see self::TYPE_INITIAL}
@param string    $imageBytes raw PNG/JPEG bytes
@param string    $mimeType  `image/png` or `image/jpeg`
@param bool|null $reuse     store the image for the signer's future documents
@return array<array-key, mixed>
@throws ValidationException on an unknown type, empty bytes, an unsupported mime type,
    or a blank access code

### `downloadSignature()`

```php
public function downloadSignature(#[\SensitiveParameter] string $accessCode, string $type) : string
```

Download the signer's saved signature or initial image (raw PNG/JPEG bytes).
`GET /signature/{type}?signer-access-code={code}`

Reads back what {@see self::uploadSignature()} stored — useful for showing a signer
their existing mark and offering to reuse it. Check `has_signature` / `has_initial` on
{@see self::self()} first to avoid a 404.

Request (query string): `signer-access-code`. The type is a path segment.

Response: raw image bytes — not the JSON envelope.

@param string $type {@see self::TYPE_SIGNATURE} or {@see self::TYPE_INITIAL}
@return string raw PNG/JPEG bytes
@throws ValidationException on an unknown type or a blank access code
@throws \Assinafy\SDK\Exceptions\ApiException 404 when nothing has been stored

### `currentDocument()`

```php
public function currentDocument(
        #[\SensitiveParameter] string $accessCode,
        ?bool $hasAcceptedTerms = null
    ) : array
```

Retrieve the document/assignment the signer currently has access to.
`GET /sign?signer-access-code={code}`

The signer's view of what they have been asked to sign. Requires the access code and
a completed {@see self::verifyCode()}. The response mirrors the document shape, plus
the signer's `current_signer` and the assignment `items` they must complete — those
`items[].id` values are the `itemId`s {@see self::sign()} expects.

Request (query string): `signer-access-code`, plus `has_accepted_terms` when supplied.

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'document',
    'id' => 'document-id',
    'account_id' => 'account-id',
    'template_id' => null,
    'name' => 'agreement.pdf',
    'status' => 'pending_signature',
    'artifacts' => [
        'original' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/download/original',
        'thumbnail' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/thumbnail',
    ],
    'is_closed' => false,
    'signing_url' => 'https://app-sandbox.assinafy.com.br/sign/signing-token',
    'decline_reason' => null,
    'declined_by' => null,
    'tags' => [],
    'created_at' => '2026-09-01T12:00:00Z',
    'updated_at' => '2026-09-01T12:00:00Z',
    'assignment' => [
        'id' => 'assignment-id',
        'sender_email' => 'person@example.com',
        'method' => 'collect',
        'expires_at' => null,
        'message' => null,
        'signers' => [
            [
                'id' => 'signer-id',
                'full_name' => 'Example Signer',
                'email' => 'person@example.com',
                'whatsapp_phone_number' => null,
                'government_id' => null,
                'has_accepted_terms' => false,
                'completed' => false,
                'notification_history' => [
                    [
                        'event' => 'signature_request',
                        'status' => 'sent',
                        'error_code' => null,
                        'error_message' => null,
                        'sent_at' => '2026-09-01T12:00:00Z',
                        'failed_at' => null,
                    ],
                ],
                'verification_method' => 'Email',
                'notification_methods' => ['Email'],
                'step' => 1,
                'notified' => true,
            ],
        ],
        'copy_receivers' => [],
        'items' => [
            [
                'id' => 'assignment-item-id',
                'page' => [
                    'id' => 'page-id',
                    'number' => 1,
                    'height' => 1651,
                    'width' => 1275,
                    'download_url' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/pages/page-id/download',
                ],
                'signer' => [
                    'id' => 'signer-id',
                    'full_name' => 'Example Signer',
                    'email' => 'person@example.com',
                    'whatsapp_phone_number' => null,
                    'government_id' => null,
                    'has_accepted_terms' => false,
                ],
                'field' => [
                    'id' => 'field-id',
                    'name' => 'Assinatura',
                    'type' => 'signature',
                    'regex' => null,
                    'is_pre_defined' => true,
                    'is_active' => true,
                    'is_required' => true,
                    'is_standard' => true,
                    'is_read_only' => false,
                    'is_visible' => true,
                ],
                'display_settings' => [
                    'top' => 10,
                    'left' => 10,
                    'width' => 240,
                    'height' => 60,
                    'fontSize' => 18,
                ],
                'value' => null,
                'completed' => false,
            ],
        ],
        'summary' => [
            'signer_count' => 1,
            'completed_count' => 0,
            'signers' => [
                [
                    'id' => 'signer-id',
                    'full_name' => 'Example Signer',
                    'email' => 'person@example.com',
                    'whatsapp_phone_number' => null,
                    'government_id' => null,
                    'has_accepted_terms' => false,
                    'completed' => false,
                ],
            ],
        ],
        'signing_urls' => [
            [
                'signer_id' => 'signer-id',
                'url' => 'https://app-sandbox.assinafy.com.br/sign/signing-token?email=signer%40example.com',
            ],
        ],
    ],
    'pages' => [
        [
            'id' => 'page-id',
            'number' => 1,
            'height' => 1651,
            'width' => 1275,
            'download_url' => 'https://sandbox.assinafy.com.br/v1/documents/document-id/pages/page-id/download',
        ],
    ],
]
```

@param bool|null $hasAcceptedTerms sent as a query flag when supplied
@return array<string, mixed>
@throws ValidationException when the access code is blank
@throws \Assinafy\SDK\Exceptions\ApiException 401 before the code is verified

### `sign()`

```php
public function sign(
        string $documentId,
        string $assignmentId,
        #[\SensitiveParameter] string $accessCode,
        #[\SensitiveParameter] array $fields
    ) : array
```

Sign a document with input fields (collect method).
`POST /documents/{documentId}/assignments/{assignmentId}?signer-access-code={code}`

The act of signing: submits a value for every assignment item. For virtual assignments
the signer must first call {@see confirmData()}. Take the three IDs from the
`assignment.items` array on {@see self::currentDocument()}.

The request body is a bare JSON **array**, not an object with a wrapper key — the SDK
re-indexes `$fields` so a filtered PHP array still encodes as a JSON list.

Request:
```
POST /documents/{documentId}/assignments/{assignmentId}?signer-access-code=<access code>
[
  [
    'itemId'  => 'assignment-item-id',
    'fieldId' => 'field-id',
    'pageId'  => 'page-id',
    'value'   => '111.444.777-35',
  ],
]
```

Example response (SDK return; optional fields depend on state):
```php
[]
```

When this signer is the last one outstanding, the document moves to `ready` and
certification begins.

@param array<int, array{itemId: string, fieldId: string, pageId: string, value: string}> $fields
@return array<array-key, mixed>
@throws ValidationException when an identifier or the access code is blank
@throws \Assinafy\SDK\Exceptions\ApiException 400 when an item is missing or invalid

### `decline()`

```php
public function decline(
        string $documentId,
        string $assignmentId,
        #[\SensitiveParameter] string $accessCode,
        #[\SensitiveParameter] string $reason
    ) : array
```

Decline (reject) an assignment as a signer.
`PUT /documents/{documentId}/assignments/{assignmentId}/reject?signer-access-code={code}`

Terminal for the whole document, not just this signer: the status becomes
`rejected_by_signer` and the remaining signers are never asked. The reason is
mandatory and is surfaced on the document as `decline_reason`.

Request:
```
PUT /documents/{documentId}/assignments/{assignmentId}/reject?signer-access-code=<access code>
['decline_reason' => 'The payment terms are wrong']
```

Example response (SDK return; optional fields depend on state):
```php
[]
```

@param string $reason why the signer refuses; required and non-empty
@return array<array-key, mixed>
@throws ValidationException when no reason is provided, or an identifier is blank

## `Resources/TagResource`

### `list()`

```php
public function list(?string $search = null) : array
```

List the workspace's tags, ordered alphabetically by name.
`GET /accounts/{account_id}/tags`

This endpoint is not paginated; the full tag list is returned as a flat array.

Request (query string): `search`, sent only when supplied and non-empty.

Example query (no request body):
```php
['search' => 'contracts']
```

Example response (SDK return; optional fields depend on state):
```php
[
    [
        'resource' => 'tag',
        'id' => 'tag-id',
        'name' => 'Example',
        'color' => null,
        'created_at' => '2026-09-01T12:00:00Z',
        'updated_at' => '2026-09-01T12:00:00Z',
    ],
]
```

@param string|null $search optional case-insensitive substring filter on the tag name
@return array<int, array<string, mixed>>

### `create()`

```php
public function create(string $name, ?string $color = null) : array
```

Create a new tag in the workspace.
`POST /accounts/{account_id}/tags`

Explicit creation is only needed when you want to set a `color`:
{@see DocumentResource::appendTags()} auto-creates unknown names on attach.

Request body:
```
['name' => 'contracts', 'color' => '2072b9']   // color optional; '#2072b9' also accepted
```

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'tag',
    'id' => 'tag-id',
    'name' => 'contracts',
    'color' => '2072b9',
    'created_at' => '2026-09-01T12:00:00Z',
    'updated_at' => '2026-09-01T12:00:00Z',
]
```

@param string      $name  display name; trimmed, whitespace-collapsed, max 64 chars
@param string|null $color 6-character hex color (with or without leading `#`); null for none
@return array<string, mixed> the created tag

@throws ValidationException when the name is empty, over 64 characters, or the color
    is not 6 hex characters
@throws \Assinafy\SDK\Exceptions\ApiException 409 when the name is already taken

### `update()`

```php
public function update(string $tagId, array $data) : array
```

Update a tag's name and/or color.
`PUT /accounts/{account_id}/tags/{tag_id}`

Either field may be omitted to leave it untouched. Pass `color: null` to clear
the color. Returns 409 Conflict (an {@see \Assinafy\SDK\Exceptions\ApiException})
if another tag already uses the new name.

Renaming updates the tag everywhere it is attached — documents keep the association.

Request body (at least one key required):
```
['name' => 'signed-contracts', 'color' => null]   // null clears the color
```

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'tag',
    'id' => 'tag-id',
    'name' => 'signed-contracts',
    'color' => null,
    'created_at' => '2026-09-01T12:00:00Z',
    'updated_at' => '2026-09-01T12:00:00Z',
]
```

@param array<string, mixed> $data subset of `{ name, color }`
@return array<string, mixed> the updated tag

@throws ValidationException when no updatable field is provided, or a value is malformed
@throws \Assinafy\SDK\Exceptions\ApiException 409 when the new name is already taken

### `delete()`

```php
public function delete(string $tagId, bool $force = false) : array
```

Delete a tag.
`DELETE /accounts/{account_id}/tags/{tag_id}`

By default the API refuses with 409 Conflict if the tag is still attached to any
document or template. Pass `$force = true` to detach it from everything first; the
documents and templates themselves are not deleted.

To remove a tag from one document while leaving it in the workspace, use
{@see DocumentResource::detachTag()} instead.

Request (query string): `force=true`, sent only when `$force` is set. No body.

Example response (SDK return; optional fields depend on state):
```php
[
    'deleted' => true,
]
```

@param bool $force detach the tag from every document and template first
@return array<array-key, mixed>
@throws ValidationException when `$tagId` is empty
@throws \Assinafy\SDK\Exceptions\ApiException 409 when the tag is in use and `$force`
    is false

## `Resources/TemplateResource`

### `create()`

```php
public function create(#[\SensitiveParameter] string $filePath) : array
```

Create a template by uploading a PDF.
`POST /accounts/{account_id}/templates`

The file is uploaded as `multipart/form-data` (same transport as a document
upload). The template starts in the `Uploaded` state; the API renders its
pages asynchronously, so poll {@see get()} until `status` is `Ready` before
downloading pages or creating documents from it.

Request: `multipart/form-data` with the PDF under the field name `file`.

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'template',
    'id' => 'template-id',
    'name' => 'agreement.pdf',
    'document_name' => 'agreement.pdf',
    'message' => null,
    'status' => 'Uploaded',
    'pages' => [],
    'roles' => [
        [
            'id' => 'role-id',
            'name' => 'TemplateEditor',
            'assignment_type' => 'Editor',
            'created_at' => '2026-09-01T12:00:00Z',
            'updated_at' => '2026-09-01T12:00:00Z',
        ],
    ],
    'tags' => [],
    'created_at' => '2026-09-01T12:00:00Z',
    'updated_at' => '2026-09-01T12:00:00Z',
]
```

This route is not part of the published OpenAPI contract but exists on the live API.

@throws \Assinafy\SDK\Exceptions\ValidationException when the file is missing,
    not a PDF, or larger than the 25 MB API limit
@return array<string, mixed> the created template

### `list()`

```php
public function list(int $page = 1, int $perPage = 20, array $filters = []) : array
```

List templates in the workspace.
`GET /accounts/{account_id}/templates`

Request (query string): `page`, `per-page`, plus any `$filters` merged over them.

Example query (no request body):
```php
['page' => 1, 'per-page' => 20, 'search' => 'agreement']
```

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
    'data' => [
        [
            'id' => 'template-id',
            'name' => 'agreement.pdf',
            'document_name' => 'agreement.pdf',
            'message' => null,
            'status' => 'Ready',
            'pages' => [
                [
                    'id' => 'page-id',
                    'number' => 1,
                    'height' => 1651,
                    'width' => 1275,
                    'download_url' => 'https://sandbox.assinafy.com.br/v1/accounts/account-id/templates/template-id/pages/page-id/download',
                    'fields' => [],
                ],
            ],
            'roles' => [
                [
                    'id' => 'role-id',
                    'name' => 'TemplateEditor',
                    'assignment_type' => 'Editor',
                    'created_at' => '2026-09-01T12:00:00Z',
                    'updated_at' => '2026-09-01T12:00:00Z',
                ],
            ],
            'tags' => [],
            'created_at' => '2026-09-01T12:00:00Z',
            'updated_at' => '2026-09-01T12:00:00Z',
        ],
    ],
    'pagination' => [
        'current_page' => 1,
        'page_count' => 1,
        'per_page' => 20,
        'total_count' => 1,
    ],
]
```

@param array<string, scalar> $filters optional documented `search`; the live API also
    accepts the runtime-undocumented `status` and `sort` filters
@return array{status?: int, message?: string, data?: array<int, array<string, mixed>>,
    pagination?: array{current_page: int, page_count: int, per_page: int, total_count: int}}
    full envelope with pagination lifted from response headers
@throws ValidationException when `$page` < 1 or `$perPage` is outside 1–100

### `get()`

```php
public function get(string $templateId) : array
```

Retrieve a template, including roles and per-page field placements.
`GET /accounts/{account_id}/templates/{template_id}`

The single-template response carries the `roles` array that
{@see DocumentResource::createFromTemplate()} relies on to bind signers to
role slots, plus `default_document_tags` (omitted from the list endpoint).

Request: no parameters.

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'template',
    'id' => 'template-id',
    'name' => 'agreement.pdf',
    'document_name' => 'agreement.pdf',
    'message' => null,
    'status' => 'Ready',
    'pages' => [
        [
            'id' => 'page-id',
            'number' => 1,
            'height' => 1651,
            'width' => 1275,
            'download_url' => 'https://sandbox.assinafy.com.br/v1/accounts/account-id/templates/template-id/pages/page-id/download',
            'fields' => [],
        ],
    ],
    'roles' => [
        [
            'id' => 'role-id',
            'name' => 'TemplateEditor',
            'assignment_type' => 'Editor',
            'created_at' => '2026-09-01T12:00:00Z',
            'updated_at' => '2026-09-01T12:00:00Z',
        ],
    ],
    'tags' => [],
    'created_at' => '2026-09-01T12:00:00Z',
    'updated_at' => '2026-09-01T12:00:00Z',
    'default_document_tags' => [],
]
```

Take the `roles[].id` values from here for `createFromTemplate()`, and the
`pages[].id` values for {@see self::downloadPage()}.

This route is not part of the published OpenAPI contract but exists on the live API.

@return array<string, mixed>
@throws ValidationException when `$templateId` is empty
@throws \Assinafy\SDK\Exceptions\ApiException 404 when the template does not exist

### `update()`

```php
public function update(string $templateId, #[\SensitiveParameter] array $data) : array
```

Update a template's editable metadata.
`PUT /accounts/{account_id}/templates/{template_id}`

Only the document file itself is immutable; the editable fields are the
display `name`, the default `document_name` applied to documents created from
the template, and the default invitation `message`.

Request body (at least one key required):
```
[
  'name'          => 'Service agreement (2026)',  // shown in the template list
  'document_name' => 'Acme — service agreement',  // default name for new documents
  'message'       => 'Please sign this contract', // default invitation message
]
```

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'template',
    'id' => 'template-id',
    'name' => 'Service agreement (2026)',
    'document_name' => 'Acme — service agreement',
    'message' => 'Please sign this contract',
    'status' => 'Ready',
    'pages' => [
        [
            'id' => 'page-id',
            'number' => 1,
            'height' => 1651,
            'width' => 1275,
            'download_url' => 'https://sandbox.assinafy.com.br/v1/accounts/account-id/templates/template-id/pages/page-id/download',
            'fields' => [],
        ],
    ],
    'roles' => [
        [
            'id' => 'role-id',
            'name' => 'TemplateEditor',
            'assignment_type' => 'Editor',
            'created_at' => '2026-09-01T12:00:00Z',
            'updated_at' => '2026-09-01T12:00:00Z',
        ],
    ],
    'tags' => [],
    'created_at' => '2026-09-01T12:00:00Z',
    'updated_at' => '2026-09-01T12:00:00Z',
    'default_document_tags' => [],
]
```

This route is not part of the published OpenAPI contract but exists on the live API.

@param array<string, mixed> $data subset of { name, document_name, message }
@return array<string, mixed> the updated template
@throws ValidationException when `$data` is empty or `$templateId` is empty

### `delete()`

```php
public function delete(string $templateId) : array
```

Delete a template.
`DELETE /accounts/{account_id}/templates/{template_id}`

Documents already created from the template are unaffected; they keep their
`template_id` even though the template is gone.

Request: no body.

Example response (SDK return; optional fields depend on state):
```php
[]
```

This route is not part of the published OpenAPI contract but exists on the live API.

@return array<array-key, mixed>
@throws ValidationException when `$templateId` is empty
@throws \Assinafy\SDK\Exceptions\ApiException 404 when the template does not exist

### `downloadPage()`

```php
public function downloadPage(string $templateId, string $pageId) : string
```

Download a rendered template page as JPEG (raw binary body).
`GET /accounts/{account_id}/templates/{template_id}/pages/{page_id}/download`

The page IDs come from the `pages` array on the {@see get()} response. The `width` and
`height` reported there are the coordinate space field placements use.

Request: no parameters.

Response: raw `image/jpeg` bytes — not the JSON envelope.

This route is not part of the published OpenAPI contract but exists on the live API.

@return string raw JPEG bytes
@throws ValidationException when either identifier is empty
@throws \Assinafy\SDK\Exceptions\ApiException 404 before rendering finishes

### `waitUntilReady()`

```php
public function waitUntilReady(
        string $templateId,
        int $maxWaitSeconds = 60,
        int $pollIntervalSeconds = 2
    ) : array
```

Poll {@see self::get()} until the template finishes processing.

Client-side helper, not an API endpoint — the mirror of
{@see DocumentResource::waitUntilReady()} for templates. Page rendering after
{@see self::create()} is asynchronous, and `pages` stays empty until it completes:
```php
$template = $client->templates()->create('/path/agreement.pdf');
$ready    = $client->templates()->waitUntilReady($template['id']);
```

Returns as soon as `status` is `Ready` (compared case-insensitively — the API sends
template statuses in PascalCase, unlike document statuses), throws on `Failed` or
`processing_failed`, and otherwise sleeps and retries. The deadline is checked between
calls; an in-flight request is bounded separately by the configured transport timeout.

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'template',
    'id' => 'template-id',
    'name' => 'agreement.pdf',
    'document_name' => 'agreement.pdf',
    'message' => null,
    'status' => 'Ready',
    'pages' => [
        [
            'id' => 'page-id',
            'number' => 1,
            'height' => 1651,
            'width' => 1275,
            'download_url' => 'https://sandbox.assinafy.com.br/v1/accounts/account-id/templates/template-id/pages/page-id/download',
            'fields' => [],
        ],
    ],
    'roles' => [
        [
            'id' => 'role-id',
            'name' => 'TemplateEditor',
            'assignment_type' => 'Editor',
            'created_at' => '2026-09-01T12:00:00Z',
            'updated_at' => '2026-09-01T12:00:00Z',
        ],
    ],
    'tags' => [],
    'created_at' => '2026-09-01T12:00:00Z',
    'updated_at' => '2026-09-01T12:00:00Z',
    'default_document_tags' => [],
]
```

@param int $maxWaitSeconds      total budget before giving up
@param int $pollIntervalSeconds delay between polls; the last sleep is trimmed so the
    helper never overshoots the deadline
@return array<string, mixed> the ready template
@throws ValidationException when either interval is not a positive integer
@throws \RuntimeException on a failed template or on timeout

## `Resources/UserResource`

### `get()`

```php
public function get(#[\SensitiveParameter] ?string $accessToken = null) : array
```

Get the user represented by the configured API key or a Bearer token.
`GET /users/self`

The "who am I" call — use it to confirm a credential works and to discover which
accounts it can reach.

Request: no parameters.

The live API answers with `data: { user, accounts }`:
```
[
  'status'  => 200,
  'message' => '',
  'data'    => [
    'user' => [
      'id' => 'resource-id', 'name' => 'Jane Doe',
      'email' => 'user@example.com', 'telephone' => null, 'government_id' => '',
      'is_email_verified' => true, 'has_accepted_terms' => true,
      'is_password_set' => true, 'created_at' => '2026-05-12T18:05:11Z',
      'to_be_deleted_at' => null,
    ],
    'accounts' => [
      ['id' => 'resource-id', 'name' => 'Acme Inc.',
       'roles' => ['owner'], 'is_delete_allowed' => true,
       'created_at' => '2026-05-12T18:05:11Z'],
    ],
  ],
]
```

The published contract instead declares `data` to be the user object directly. This
method returns the **user** either way — it unwraps the nested `user` key when present.
Use {@see AccountResource::list()} for the workspace list rather than relying on the
`accounts` key, which the documented shape does not carry.

Example response (SDK return; optional fields depend on state):
```php
[
    'id' => 'auth-user-id',
    'name' => 'John Smith',
    'email' => 'person@example.com',
    'telephone' => null,
    'government_id' => null,
    'is_email_verified' => false,
    'has_accepted_terms' => true,
    'created_at' => '2023-03-03T11:51:34Z',
    'to_be_deleted_at' => null,
]
```

@throws ValidationException when called on a public client without an access token

@return array{id?: string, name?: string, email?: string, telephone?: string|null,
    government_id?: string|null, is_email_verified?: bool, has_accepted_terms?: bool,
    created_at?: string, to_be_deleted_at?: string|null}

### `stats()`

```php
public function stats(
        string $granularity = self::GRANULARITY_MONTHLY,
        ?string $month = null,
        #[\SensitiveParameter] ?string $accessToken = null
    ) : array
```

Get document-funnel KPIs summed over every account the user belongs to.
`GET /users/self/stats`

The cross-account counterpart to {@see AccountResource::stats()}: identical series
shape, but totalled over every workspace rather than scoped to one.

Request (query string): `granularity=monthly|daily`, plus `month=YYYY-MM` which is
required for `daily` and optional for `monthly`.

Example query (no request body):
```php
['granularity' => 'monthly', 'month' => '2026-09']
```

Example response (SDK return; optional fields depend on state):
```php
[
    [
        'period' => '2026-09',
        'documents_uploaded' => 0,
        'documents_sent' => 0,
        'signature_requests' => 0,
        'signature_requests_notification_email' => 0,
        'signature_requests_notification_whatsapp' => 0,
        'signature_requests_notification_bypass' => 0,
        'signature_requests_verification_email' => 0,
        'signature_requests_verification_whatsapp' => 0,
        'signature_requests_verification_bypass' => 0,
        'signature_requests_verification_digital_certificate' => 0,
        'signature_requests_viewed' => 0,
        'signature_requests_completed' => 0,
        'documents_certified' => 0,
    ],
]
```

Available in production and sandbox; access depends on the authenticated account.

@throws ValidationException on an unknown granularity, or on `daily` without a
    `YYYY-MM` month

@return array<int, array{period: string, documents_uploaded: int, documents_sent: int,
    signature_requests: int, signature_requests_notification_email: int,
    signature_requests_notification_whatsapp: int,
    signature_requests_notification_bypass: int,
    signature_requests_verification_email: int,
    signature_requests_verification_whatsapp: int,
    signature_requests_verification_bypass: int,
    signature_requests_verification_digital_certificate: int,
    signature_requests_viewed: int, signature_requests_completed: int,
    documents_certified: int}>

### `notificationPreferences()`

```php
public function notificationPreferences(#[\SensitiveParameter] ?string $accessToken = null) : array
```

Get all owner-facing document email preferences.
`GET /users/self/notification-preferences`

These are the emails the document **owner** receives about their own documents — not
the signature invitations sent to signers. Account and security email (welcome,
password reset, invitations, account deletion) is not configurable and never appears
here.

All nine keys are always returned; everything defaults to `true`. The codes are
available as {@see self::NOTIFICATION_PREFERENCE_CODES}.

Request: no parameters.

Example response (SDK return; optional fields depend on state):
```php
[
    'DocumentCompleted' => true,
    'SignerDeclined' => true,
    'DocumentCancelled' => true,
    'DocumentAboutToExpire' => true,
    'DocumentExpired' => true,
    'DocumentExpirationReset' => true,
    'DocumentProcessingFailed' => true,
    'TemplateProcessingFailed' => true,
    'SignerWhatsappFailed' => true,
]
```

Available in production and sandbox; access depends on the authenticated account.

@throws ValidationException when called on a public client without an access token

@return array{DocumentCompleted?: bool, SignerDeclined?: bool,
    DocumentCancelled?: bool, DocumentAboutToExpire?: bool, DocumentExpired?: bool,
    DocumentExpirationReset?: bool, DocumentProcessingFailed?: bool,
    TemplateProcessingFailed?: bool, SignerWhatsappFailed?: bool}

### `updateNotificationPreferences()`

```php
public function updateNotificationPreferences(
        array $preferences,
        #[\SensitiveParameter] ?string $accessToken = null
    ) : array
```

Merge selected owner-facing document email preferences.
`PUT /users/self/notification-preferences`

A merge, not a replace: omitted keys keep their current values, so you never have to
read-modify-write the whole map. Setting a key to `false` stops that email for this
user in **every** account they belong to — the setting is per-user, not per-workspace.

Keys and values are validated locally against
{@see self::NOTIFICATION_PREFERENCE_CODES} before the request is sent; the API
likewise rejects an unknown code, a non-boolean value, or an empty body with 400 and
writes nothing.

Request body (at least one key required):
```
['DocumentAboutToExpire' => false, 'SignerWhatsappFailed' => false]
```

Example response (SDK return; optional fields depend on state):
```php
[
    'DocumentCompleted' => true,
    'SignerDeclined' => true,
    'DocumentCancelled' => true,
    'DocumentAboutToExpire' => false,
    'DocumentExpired' => true,
    'DocumentExpirationReset' => true,
    'DocumentProcessingFailed' => true,
    'TemplateProcessingFailed' => true,
    'SignerWhatsappFailed' => false,
]
```

Available in production and sandbox; access depends on the authenticated account.

@throws ValidationException when `$preferences` is empty, a code is unknown, or a
    value is not a boolean
@param array<array-key, mixed> $preferences
@return array{DocumentCompleted?: bool, SignerDeclined?: bool,
    DocumentCancelled?: bool, DocumentAboutToExpire?: bool, DocumentExpired?: bool,
    DocumentExpirationReset?: bool, DocumentProcessingFailed?: bool,
    TemplateProcessingFailed?: bool, SignerWhatsappFailed?: bool}

## `Resources/WebhookResource`

### `register()`

```php
public function register(
        #[\SensitiveParameter] string $url,
        #[\SensitiveParameter] string $email,
        array $events = [],
        bool $isActive = true
    ) : array
```

Register or replace the workspace webhook subscription.
`PUT /accounts/{account_id}/webhooks/subscriptions`

A workspace has exactly one subscription, and this call is an upsert — it creates the
subscription or replaces it wholesale. All four body fields are mandatory, so a
partial update is not possible; read the current values with {@see self::get()} first
if you only mean to change one.

`email` is the address the platform notifies when deliveries start failing; it is not
a delivery target.

Request body:
```
[
  'url'       => 'https://example.com/hooks/assinafy',  // required
  'email'     => 'ops@example.com',                     // required
  'events'    => ['document_ready', 'signer_signed_document'],  // required
  'is_active' => true,                                  // required
]
```

Example response (SDK return; optional fields depend on state):
```php
[
    'events' => ['document_ready', 'signer_signed_document'],
    'is_active' => true,
    'url' => 'https://example.com/hooks/assinafy',
    'email' => 'ops@example.com',
    'updated_at' => '2023-05-10T14:58:24Z',
]
```

Deliveries are **unsigned** — there is no secret to register and no signature header.
Secure the endpoint as described by {@see \Assinafy\SDK\Support\WebhookEventParser}.

@param string            $url    absolute HTTP(S) endpoint to POST deliveries to
@param string            $email  address alerted when delivery fails
@param array<int, mixed> $events event type IDs; when empty, {@see DEFAULT_EVENTS} is sent
@param bool              $isActive whether to start delivering immediately
@return array<string, mixed> the stored subscription
@throws ValidationException on a non-absolute URL, a malformed email, or a
    non-string event

### `get()`

```php
public function get() : ?array
```

Get the current webhook subscription (or null if none has ever been configured).
`GET /accounts/{account_id}/webhooks/subscriptions`

Request: no parameters.

Example response (SDK return; optional fields depend on state):
```php
[
    'events' => ['document_ready', 'document_prepared'],
    'is_active' => true,
    'url' => 'https://example.com/hooks/assinafy',
    'email' => 'person@example.com',
    'updated_at' => '2023-05-10T14:58:24Z',
]
```

Returns `null` — not an empty array — when the workspace has never configured one, so
`if ($client->webhooks()->get() === null)` is the way to test for absence.

@return array<string, mixed>|null the subscription, or null when none exists

### `deactivate()`

```php
public function deactivate() : array
```

Disable delivery without losing the subscription configuration.
`PUT /accounts/{account_id}/webhooks/inactivate`

The URL / email / events stay on file so the subscription can be re-enabled
later with {@see activate()} without re-supplying them. This is the only way to stop
deliveries — the API has no `DELETE` route for subscriptions.

Request: no body.

Example response (SDK return; optional fields depend on state):
```php
[
    'events' => ['document_ready', 'document_prepared'],
    'is_active' => true,
    'url' => 'https://example.com/hooks/assinafy',
    'email' => 'person@example.com',
    'updated_at' => '2023-05-10T14:58:24Z',
]
```

@return array<string, mixed>

### `activate()`

```php
public function activate() : array
```

Re-enable delivery on the existing subscription.

Client-side helper, not a single endpoint. There is no dedicated "activate" route, so
this reads the stored subscription with {@see self::get()} and re-sends its URL /
email / events through {@see self::register()} with `is_active = true` — two requests.

Request/Response: as {@see self::get()} then {@see self::register()}.

Response (unwrapped `data` from the register call):
```
[
  'events'     => ['document_ready', 'signer_signed_document'],
  'is_active'  => true,
  'url'        => 'https://example.com/hooks/assinafy',
  'email'      => 'ops@example.com',
  'updated_at' => '2026-08-27T18:02:44Z',
]
```

@return array<string, mixed> the reactivated subscription
@throws \RuntimeException when no subscription has been configured yet

### `eventTypes()`

```php
public function eventTypes() : array
```

List the available webhook event types and their descriptions.
`GET /webhooks/event-types` (not account-scoped).

The authoritative vocabulary for the `events` array of {@see self::register()}. The
`EVENT_*` constants mirror these IDs; prefer this call over hard-coding if you render
a picker, since the platform can add types.

Request: no parameters.

Example response (SDK return; optional fields depend on state):
```php
[
    [
        'id' => 'document_uploaded',
        'description' => 'Triggered when the User has uploaded a Document',
    ],
    [
        'id' => 'document_metadata_ready',
        'description' => 'Triggered when the document is ready to be prepared. The the document has been'
            . ' normalized to PDF and its pages are available.',
    ],
    [
        'id' => 'document_prepared',
        'description' => 'Triggered when the User as subject prepares a Document.',
    ],
    [
        'id' => 'assignment_created',
        'description' => 'Triggered when the User created an assignment for a Document. Includes a'
            . ' snapshot of the creator profile (name, email, telephone) and origin IP/user-agent.',
    ],
    [
        'id' => 'signature_requested',
        'description' => 'Triggered when the User requested signature of a Document',
    ],
    [
        'id' => 'document_ready',
        'description' => 'Triggered when the last Signer of the assignment signs the Document, as a'
            . ' result, the document status becomes ready.',
    ],
    [
        'id' => 'signer_created',
        'description' => 'Triggered when the User created a Signer',
    ],
    [
        'id' => 'signer_email_verified',
        'description' => 'Triggered when Signer\'s email has been verified by a verification code linked to a Document',
    ],
    [
        'id' => 'signer_whatsapp_verified',
        'description' => 'Triggered when Signer\'s WhatsApp phone number has been verified by a verification code linked to a Document',
    ],
    [
        'id' => 'signer_data_confirmed',
        'description' => 'Triggered when Signer\'s data has been confirmed',
    ],
    [
        'id' => 'signer_signed_document',
        'description' => 'Triggered when the Signer signed a Document',
    ],
    [
        'id' => 'signer_viewed_document',
        'description' => 'Triggered when the Signer viewed a Document for the first time',
    ],
    [
        'id' => 'signer_rejected_document',
        'description' => 'Triggered when the Signer rejected signing a Document',
    ],
    [
        'id' => 'user_rejected_document',
        'description' => 'Triggered when document has been cancelled.',
    ],
    [
        'id' => 'document_processing_failed',
        'description' => 'Unprocessable document, either invalid or the system couldn\'t process it',
    ],
]
```

@return array<int, array{id: string, description: string}>

### `dispatches()`

```php
public function dispatches(array $filters = []) : array
```

List the webhook delivery history (dispatches) for the workspace.
`GET /accounts/{account_id}/webhooks`

The delivery log — what was sent, where, and whether it landed. Each entry embeds the
exact `payload` that was POSTed, so a failed delivery can be replayed or inspected
without reproducing the original event.

Request (query string): `event`, `delivered` (`true`/`false`), `from` and `to` (Unix
timestamps), `page`, `per-page`.

Example query (no request body):
```php
[
    'event' => 'document_ready', 'delivered' => 'false',
    'from' => 1788220800, 'to' => 1790812800, 'page' => 1, 'per-page' => 20,
]
```

Example response (SDK return; optional fields depend on state):
```php
[
    'status' => 200,
    'message' => '',
    'data' => [
        [
            'resource' => 'activity_dispatching_history',
            'id' => 'webhook-dispatch-id',
            'event' => 'document_ready',
            'activity_id' => 'activity-id',
            'endpoint' => 'https://example.com/webhook',
            'payload' => null,
            'delivered' => true,
            'http_status' => 200,
            'response_body' => 'OK',
            'error' => null,
            'created_at' => '2026-09-01T10:30:00Z',
            'updated_at' => '2026-09-01T10:30:00Z',
        ],
    ],
    'pagination' => [
        'current_page' => 1,
        'page_count' => 1,
        'per_page' => 20,
        'total_count' => 1,
    ],
]
```

`http_status`, `response_body`, and `error` are the diagnostics for a failed delivery —
they record what your endpoint actually answered. On a successful delivery `delivered`
is true and `error` is empty.

@param array<string, scalar> $filters optional `event`, `delivered`, `from`, `to`,
    `page`, `per-page`
@return array{status?: int, message?: string, data?: array<int, array<string, mixed>>,
    pagination?: array{current_page: int, page_count: int, per_page: int, total_count: int}}
    full envelope with pagination lifted from response headers
@throws ValidationException when `page`/`per-page` are not integers, or `per-page` is
    outside 1–100

### `retryDispatch()`

```php
public function retryDispatch(string $dispatchId) : array
```

Manually retry a single webhook dispatch.
`POST /accounts/{account_id}/webhooks/{dispatch_id}/retry`

Re-sends the original payload byte-for-byte to the currently configured endpoint — use
it after fixing an outage. `$dispatchId` is the `id` of an entry from
{@see self::dispatches()}.

A retry creates a **new** dispatch record rather than mutating the old one, so the
history keeps both attempts.

Request: no body.

Example response (SDK return; optional fields depend on state):
```php
[
    'resource' => 'activity_dispatching_history',
    'id' => 'webhook-dispatch-id',
    'event' => 'document_ready',
    'activity_id' => 'activity-id',
    'endpoint' => 'https://example.com/webhook',
    'payload' => null,
    'delivered' => true,
    'http_status' => 200,
    'response_body' => 'OK',
    'error' => null,
    'created_at' => '2026-09-01T10:30:00Z',
    'updated_at' => '2026-09-01T10:30:00Z',
]
```

@return array<string, mixed> the new dispatch record
@throws ValidationException when `$dispatchId` is empty
@throws \Assinafy\SDK\Exceptions\ApiException 404 when the dispatch does not exist

## `Support/Iso8601`

### `reasonInvalid()`

```php
public static function reasonInvalid(string $value) : ?string
```

Explain why `$value` is not an acceptable date-time, or `null` when it is.

@return self::REASON_*|null

## `Support/MutableLogger`

### `__construct()`

```php
public function __construct(private LoggerInterface $logger)
```

Store the application logger; construction does not emit a log message.

### `setLogger()`

```php
public function setLogger(LoggerInterface $logger) : void
```

Redirect every holder of this proxy to a new logger.

### `getLogger()`

```php
public function getLogger() : LoggerInterface
```

The logger currently being proxied to.

### `log()`

```php
public function log($level, $message, array $context = []) : void
```

@param mixed                $level
@param string|\Stringable   $message
@param array<string, mixed> $context

## `Support/WebhookEventParser`

### `extractEvent()`

```php
public function extractEvent(#[\SensitiveParameter] string $payload) : ?array
```

Decode a raw webhook body into an event array, or null when it is not valid JSON.

Local parsing only — makes no HTTP request.

Request (the delivery your endpoint receives): the raw POST body.

Response (the decoded envelope, using the signer_created event):
```php
[
    'id' => 42,
    'event' => 'signer_created',
    'message' => 'Signer created',
    'subject' => ['id' => 'user-id', 'type' => 'User', 'name' => 'Example User'],
    'origin' => ['ip' => '203.0.113.10', 'user-agent' => 'Example/1.0'],
    'account_id' => 'account-id',
    'created_at' => 1788264000,
    'object' => [
        'id' => 'signer-id',
        'type' => 'Signer',
        'full_name' => 'Example Signer',
        'email' => 'signer@example.com',
        'whatsapp_phone_number' => null,
        'government_id' => null,
        'has_accepted_terms' => false,
    ],
    'payload' => ['signer_full_name' => 'Example Signer'],
]
```

Note there is no `data` key — the entity lives under `object` and the event-specific
detail under `payload`. Returns `null` rather than throwing when the body is not
valid JSON, so a malformed delivery can be answered with a 400 instead of a 500:
```php
$raw = file_get_contents('php://input');
$event = is_string($raw) ? $client->webhookEvents()->extractEvent($raw) : null;
if ($event === null) {
    http_response_code(400);
    return;
}
```

@param string $payload raw request body, exactly as received
@return array<string, mixed>|null the decoded envelope, or null when the body is not
    a JSON object or array

### `getEventType()`

```php
public function getEventType(?array $event) : ?string
```

The event name, e.g. `signature_requested`.

Reads the envelope's `event` key. Accepts the `null` that
{@see self::extractEvent()} returns, so the two compose without a guard, and returns
`null` for anything that is not a string — an unrecognised body can never be mistaken
for a known event.

Local parsing only — makes no HTTP request.

Request: the decoded envelope from {@see self::extractEvent()}.

Response: one of the `EVENT_*` values, e.g. `'document_ready'`.

@param array<string, mixed>|null $event the decoded envelope
@return string|null the event name, or null when absent or not a string
@see \Assinafy\SDK\Resources\WebhookResource the `EVENT_*` constants

### `getEventData()`

```php
public function getEventData(?array $event) : array
```

The entity the event is about — the `object` key of the envelope.

Local parsing only — makes no HTTP request.

Request: the decoded envelope from {@see self::extractEvent()}.

Response: the complete object as received. Its shape depends on `object.type`.
Example for signer_created:
```php
[
    'id' => 'signer-id',
    'type' => 'Signer',
    'full_name' => 'Example Signer',
    'email' => 'signer@example.com',
    'whatsapp_phone_number' => null,
    'government_id' => null,
    'has_accepted_terms' => false,
]
```

Returns `[]` rather than null when the key is missing, so the result is always safe to
iterate. Treat it as a hint, not as truth: deliveries are unsigned, so re-fetch the
entity through the API before acting on it.

@param array<string, mixed>|null $event the decoded envelope
@return array<string, mixed> the `object` entity, or `[]` when absent

### `getEventPayload()`

```php
public function getEventPayload(?array $event) : array
```

The event-specific parameters — the `payload` key of the envelope.

Distinct from {@see self::getEventData()}: `object` is the entity the event concerns,
`payload` is the extra detail about what happened to it.

Local parsing only — makes no HTTP request.

Request: the decoded envelope from {@see self::extractEvent()}.

Response — for `signer_signed_document`, `object` is the Document while `payload`
names which signer signed:
```
['signer_full_name' => 'Example Signer']
```

Many events carry an empty `payload` — the entity alone is the news. Returns `[]`
rather than null when the key is missing, so the result is always safe to iterate.

@param array<string, mixed>|null $event the decoded envelope
@return array<string, mixed> the `payload` detail, or `[]` when absent

### `getAccountId()`

```php
public function getAccountId(?array $event) : ?string
```

The account the event belongs to — useful when one endpoint serves several workspaces.

Reads the envelope's top-level `account_id`. Route on this to pick the right API
credential before re-fetching the entity. Local parsing only — makes no HTTP request.

Request: the decoded envelope from {@see self::extractEvent()}.

Response: the workspace ID, or `null` when absent or not a string.
```php
$accountId = $client->webhookEvents()->getAccountId($event);   // 'account-id'
```

@param array<string, mixed>|null $event the decoded envelope
@return string|null the workspace ID, or null when absent
