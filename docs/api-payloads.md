# REST request and response payloads

Source: [Assinafy OpenAPI](https://api.assinafy.com.br/v1/docs/openapi.json). Paths include `/v1`; SDK base URLs are `https://api.assinafy.com.br/v1` and `https://sandbox.assinafy.com.br/v1`.

Each operation includes its full published parameter, body, success and error schemas. JSON blocks are schemas, not requests: `required`, `enum`, `nullable`, constraints and examples describe the permitted payload. Binary bodies remain bytes. Examples use reserved contact domains. Supply credentials, codes and identifiers from your own connection.

Every `$ref` points to a complete shared definition in the [component dictionary](#component-dictionary). Keeping shared shapes together avoids inconsistent copies. For SDK signatures, examples and unwrapped return values, see [SDK reference](sdk-reference.md). Runtime account entitlements determine feature availability.

The OpenAPI document declares no top-level `security` key: every API operation declares its own. An empty list denotes a public operation.

Webhook sections describe outbound event deliveries to the configured endpoint. Discover subscribable events with `GET /v1/webhooks/event-types`. The target environment's event catalog determines which events can be subscribed to.

## `GET /v1/accounts/{accountId}`

Get account

Retrieve a workspace account the user belongs to.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "expand",
      "in": "query",
      "description": "`signing_features`: add which signing features the workspace can use.",
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The account",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Account"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/accounts/{accountId}`

Update account

Update a workspace account's profile.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "name": {
              "type": "string",
              "example": "Acme Inc."
            },
            "notification_sender_type": {
              "description": "Who signers see as the notification sender for documents in this account. `User` (default) shows the document owner's name; `Account` shows this account's name.",
              "type": "string",
              "enum": [
                "User",
                "Account"
              ],
              "example": "Account"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The updated account",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Account"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `DELETE /v1/accounts/{accountId}`

Delete account

Delete a workspace account.

By default the request fails with `400` when the workspace has an active paid subscription — the `restrictions` array in the response lists each blocker by code so you can address them individually before retrying. Pass `force: true` to cancel any active paid subscription automatically and proceed with immediate deletion.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "requestBody": {
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "force": {
              "description": "When `true`, cancels any active paid subscription on this workspace and proceeds with deletion immediately. Defaults to `false`.",
              "type": "boolean",
              "example": false
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Account deleted",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": [],
                    "example": []
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/DeletionRestrictions"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "403": {
      "$ref": "#/components/responses/Forbidden",
      "description": "An OAuth application token, a deactivated membership or a role refuses it."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/theme`

Get account theme

Retrieve account theme information (branding name, colors, and logo URL).

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "responses": {
    "200": {
      "description": "The theme",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/AccountTheme"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/logo`

Download account logo

Download the account logo image binary.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "responses": {
    "200": {
      "description": "The logo image",
      "content": {
        "image/*": {
          "schema": {
            "type": "string",
            "format": "binary"
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/logo`

Upload account logo

Upload or replace the account logo image.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "multipart/form-data": {
        "schema": {
          "required": [
            "file"
          ],
          "properties": {
            "file": {
              "type": "string",
              "format": "binary"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Logo updated",
      "content": {
        "application/json": {
          "schema": {
            "$ref": "#/components/schemas/Envelope"
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `DELETE /v1/accounts/{accountId}/logo`

Delete account logo

Remove the account logo image.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "responses": {
    "200": {
      "description": "Logo deleted",
      "content": {
        "application/json": {
          "schema": {
            "$ref": "#/components/schemas/Envelope"
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts`

List my accounts

List the workspace accounts the authenticated user belongs to.

Called with an OAuth application token, this returns exactly one workspace: the one the user chose when they authorized the application. Use its `id` as the `{accountId}` segment of every other endpoint — a token is bound to a single workspace, and any request naming a different one is refused. This endpoint needs no particular scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "responses": {
    "200": {
      "description": "The user's accounts",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/Account"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts`

Create account

Create a new workspace account owned by the authenticated user.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "name"
          ],
          "properties": {
            "name": {
              "type": "string",
              "example": "Acme Inc."
            },
            "notification_sender_type": {
              "description": "Who signers see as the notification sender for documents in this account. `User` (default) shows the document owner's name; `Account` shows this account's name.",
              "type": "string",
              "enum": [
                "User",
                "Account"
              ],
              "example": "Account"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The created account",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Account"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/documents/{documentId}/activities`

List document activities

List the activities recorded for a document, newest first (`sort=id` for oldest first). Paged: 20 per page by default; the `X-Pagination-Total-Count`, `X-Pagination-Page-Count`, `X-Pagination-Current-Page` and `X-Pagination-Per-Page` headers describe the full list, and a page past the end returns an empty `data`. Each entry carries an event-specific `payload` snapshot (keys vary per event) and the request `origin` (`ip`, `user-agent`).

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    },
    {
      "name": "sort",
      "in": "query",
      "description": "`-id` (default, newest first) or `id`.",
      "schema": {
        "type": "string"
      }
    },
    {
      "$ref": "#/components/parameters/Page"
    },
    {
      "$ref": "#/components/parameters/PerPage"
    }
  ],
  "responses": {
    "200": {
      "description": "Document activities",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/DocumentActivity"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/Forbidden",
      "description": "With a user's token or key, a deactivated membership, a role or an OAuth scope refuses it."
    },
    "404": {
      "description": "The document is not in the workspace.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Document not found."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/assignments`

List assignments

List the workspace's assignments, oldest first. With an OAuth token the workspace is the token's; otherwise pass `accountId`. Each assignment carries its `document_id`.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "name": "accountId",
      "in": "query",
      "description": "The workspace ID. Required unless the request uses an OAuth token.",
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "sort",
      "in": "query",
      "description": "`created_at` (default, oldest first), `expires_at` or `id`; prefix with `-` for descending (`-created_at`, newest first). Unknown values are ignored.",
      "schema": {
        "type": "string",
        "example": "-created_at"
      }
    },
    {
      "$ref": "#/components/parameters/Page"
    },
    {
      "$ref": "#/components/parameters/PerPage"
    }
  ],
  "responses": {
    "200": {
      "description": "A page of assignments",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/Assignment"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "Neither `accountId` nor an OAuth token names the workspace.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 400
                  },
                  "message": {
                    "type": "string",
                    "example": "An account context is required and was not provided."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/Forbidden",
      "description": "With a user's token or key, a deactivated membership, a role or an OAuth scope refuses it."
    },
    "404": {
      "description": "The workspace does not exist.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Account not found."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/documents/{documentId}/assignments`

Create assignment (request signatures)

Request signatures on a document. Use `method: virtual` to sign without input fields, or `method: collect` to place input fields on specific pages.

**Digital certificate signing works only on `virtual` assignments.** A `DigitalCertificate` signer cannot sign placed fields, so a `collect` assignment with one is refused with `400` before anything is charged.

A document takes one assignment: a retry after a lost response answers `400` and charges nothing. Read the document to see whether the first request went through.

For **virtual**, the document may be in `uploaded`, `metadata_processing` or `metadata_ready`; it is promoted to `pending_signature` automatically once metadata processing completes. For **collect**, the document must be in `metadata_ready` (fields reference specific pages).

`step` controls signing order: signers sharing a step sign in parallel, and the next step is notified only after the previous step completes. If supplied, every signer must supply it and values must be contiguous starting at 1.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "method",
            "signers"
          ],
          "properties": {
            "method": {
              "description": "`virtual` signs without input fields; `collect` places input fields on pages. Signers with `DigitalCertificate` verification need `virtual` (`collect` returns `400`).",
              "type": "string",
              "enum": [
                "virtual",
                "collect"
              ],
              "example": "virtual"
            },
            "signers": {
              "type": "array",
              "items": {
                "required": [
                  "id"
                ],
                "properties": {
                  "id": {
                    "type": "string",
                    "example": "615605f50e968054a5b7c9b8"
                  },
                  "verification_method": {
                    "description": "How the signer's identity is verified before signing. `Email` (default) sends a one-time code to the signer's email; `Whatsapp` sends the code over WhatsApp — the verification itself is not billed, but it requires the WhatsApp notification channel, so the signer costs 0.45 credits (available only on paid subscriptions); `DigitalCertificate` has the signer sign with their own ICP-Brasil certificate (A1/A3) — it requires the Digital Certificate feature, the signer must have a CPF or CNPJ in `government_id`, must be alone in its signing step, needs `method: virtual` (a `collect` assignment with a `DigitalCertificate` signer returns `400`), and is charged 0.5 credits per signer. A CPF requires that person's certificate (an e-CPF, or an e-CNPJ naming them as legal representative); a CNPJ requires an e-CNPJ for that company, from any of its representatives. `IdDocumentFaceMatchBasic` verifies the signer from a photo of their government ID, a photo of them holding it and a liveness check — it requires the ID Document + Face Match feature, the signer must have a valid CPF in `government_id`, and is charged a per-signer fee. Omit to default to `Email`.",
                    "type": "string",
                    "enum": [
                      "Email",
                      "Whatsapp",
                      "DigitalCertificate",
                      "IdDocumentFaceMatchBasic"
                    ],
                    "example": "Email"
                  },
                  "notification_methods": {
                    "description": "How the signer is told a signature is being requested. **Exactly one method per signer** — the array shape is historical, and sending two returns `400`. The method must also be compatible with `verification_method`: `Email` verification takes `Email`, `Whatsapp` verification takes `Whatsapp`, and `DigitalCertificate` and `IdDocumentFaceMatchBasic` take either. WhatsApp incurs an additional cost and is available only on paid subscriptions. Omit it to have it inferred from `verification_method`, or `{\"Email\"}` when neither is sent. See **Verification & Notification Methods** for the full pairing table.",
                    "type": "array",
                    "items": {
                      "type": "string",
                      "enum": [
                        "Email",
                        "Whatsapp"
                      ]
                    },
                    "example": [
                      "Email"
                    ]
                  },
                  "embedded": {
                    "description": "Mark this signer as embedded: they sign inside an iframe in your application through a short-lived embed session, and your backend gets no signing link for them (`signing_urls` returns `url: null, embedded: true`). They still receive the normal invitation with their code, and can also sign from it. Requires the Embed plan feature (`403 PlanRestricted` otherwise) and works with Email or Whatsapp verification. Defaults to `false`.",
                    "type": "boolean",
                    "example": false
                  },
                  "step": {
                    "type": "integer",
                    "example": 1
                  }
                },
                "type": "object"
              }
            },
            "entries": {
              "description": "Required for `collect`: field placements per page. In `collect`, every signer listed in `signers` must appear once and own at least one field here; otherwise `400`.",
              "type": "array",
              "items": {
                "properties": {
                  "page_id": {
                    "type": "string"
                  },
                  "fields": {
                    "type": "array",
                    "items": {
                      "properties": {
                        "signer_id": {
                          "type": "string"
                        },
                        "field_id": {
                          "type": "string"
                        },
                        "display_settings": {
                          "$ref": "#/components/schemas/DisplaySettings"
                        }
                      },
                      "type": "object"
                    }
                  }
                },
                "type": "object"
              }
            },
            "message": {
              "description": "Text included in the invitation email.",
              "type": "string"
            },
            "expires_at": {
              "description": "ISO 8601; default is no expiration. Must be at least one hour in the future. A UTC offset in the value is honored (`2026-12-31T23:59:59-03:00` is `2027-01-01T02:59:59Z`); a value without one is read as UTC, and a date alone (`2026-12-31`) means 23:59 UTC that day.",
              "type": "string",
              "format": "date-time"
            },
            "copy_receivers": {
              "description": "Signer IDs that only receive a copy.",
              "type": "array",
              "items": {
                "type": "string"
              }
            },
            "max_total_credits": {
              "description": "Optional ceiling on what this request may spend: refused with `409` and code `CreditCeilingExceeded`, nothing charged, when its `total_credits` is above this. Send the `total_credits` of the last estimate. A value that is not a number of credits, zero or more, answers `400`; numeric text counts, as a form-encoded body sends it.",
              "type": "number",
              "example": 1.5
            }
          },
          "type": "object"
        },
        "examples": {
          "virtual_full": {
            "$ref": "#/components/examples/AssignmentCreateVirtualFull"
          },
          "collect_full": {
            "$ref": "#/components/examples/AssignmentCreateCollectFull"
          },
          "virtual": {
            "$ref": "#/components/examples/AssignmentCreateVirtual"
          },
          "collect": {
            "$ref": "#/components/examples/AssignmentCreateCollect"
          }
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The created assignment",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Assignment"
                  }
                },
                "type": "object"
              }
            ]
          },
          "examples": {
            "virtual": {
              "$ref": "#/components/examples/AssignmentCreatedVirtual"
            },
            "collect": {
              "$ref": "#/components/examples/AssignmentCreatedCollect"
            }
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/BadRequest",
      "description": "The body cannot make an assignment: a signer, method, step, field or expiration is missing or invalid, the document is not ready or already has one, or the workspace lacks the documents or credits it needs (e.g. `At least one signer is required.`)."
    },
    "409": {
      "$ref": "#/components/responses/CreditCeilingExceeded"
    },
    "403": {
      "description": "The workspace's plan lacks a verification or notification method sent (`PlanRestricted`), or an OAuth app's token lacks `documents:write` (`InsufficientScope`); also a deactivated membership or a role.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 403
                  },
                  "message": {
                    "type": "string",
                    "example": "WhatsApp notification is not available for your current plan."
                  },
                  "code": {
                    "type": "string",
                    "enum": [
                      "PlanRestricted",
                      "InsufficientScope"
                    ],
                    "example": "PlanRestricted"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "description": "The document is not in the workspace.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Document not found."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/documents/{documentId}/assignments/estimate-cost`

Estimate assignment cost

Estimate the cost of creating an assignment without creating it, returning a cost breakdown and the current account balances. Signer IDs are not required — only the verification/notification method affects cost. Each assignment consumes 1 document from the plan allowance; if exhausted, an extra document is charged from credits (`needs_extra_document` = true). `blocking_reason` may be `PendingPayment`, `InsufficientDocuments` or `InsufficientCredits`.

### Pricing

Per-unit costs (in credits) used to build the estimate:

| Item | Cost |
|------|------|
| Extra document | 1 credit |
| Email notification | 0 credits |
| WhatsApp notification | 0.45 credits |
| Digital certificate signature (per signer) | 0.5 credits |
| Identity document verification (per signer) | depends on the plan, see the table below |

| Plan | Price per signer |
|------|------------------|
| Free | 1.5 credits |
| Standard | 1 credit |
| Pro | 0.5 credits |
| Other plans and enterprise contracts | 0.25 credits |

`Email` and `Whatsapp` verification are not priced separately — their only line in the `breakdown` is their notification. A `Whatsapp`-verified signer therefore shows up as a WhatsApp notification, because that channel is mandatory for that verification method. A `DigitalCertificate` signer adds the digital-certificate signature cost **on top of** its notification cost; it appears in the `breakdown` under the `SignatureDigitalCertificate` code. An `IdDocumentFaceMatchBasic` signer likewise adds your plan's verification price on top of its notification, under the `VerificationIdDocumentFaceMatchBasic` code.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "method": {
              "description": "Send the method you will create the assignment with. With `collect`, a `DigitalCertificate` signer returns `400`, because certificate signing works only on `virtual` assignments.",
              "type": "string",
              "enum": [
                "virtual",
                "collect"
              ],
              "example": "virtual"
            },
            "signers": {
              "description": "Required for `virtual`; each entry may be `{}` to default to Email.",
              "type": "array",
              "items": {
                "properties": {
                  "verification_method": {
                    "description": "Verification method to price. `Whatsapp` forces the WhatsApp notification, so it prices at 0.45 credits per signer; `DigitalCertificate` adds the per-signer signature cost on top of its notification, and with `method: collect` returns `400` (certificate signers sign only virtual assignments). `IdDocumentFaceMatchBasic` requires its account feature, requires the signer to have a valid CPF in `government_id`, and is charged your plan's per-signer verification price on top of its notification.",
                    "type": "string",
                    "enum": [
                      "Email",
                      "Whatsapp",
                      "DigitalCertificate",
                      "IdDocumentFaceMatchBasic"
                    ],
                    "example": "Whatsapp"
                  },
                  "notification_methods": {
                    "description": "The notification channel to price — exactly one per signer, subject to the same pairing rules as **Create assignment**. `Whatsapp` costs 0.45 credits per signer. Omit it to have it inferred from `verification_method`.",
                    "type": "array",
                    "items": {
                      "type": "string",
                      "enum": [
                        "Email",
                        "Whatsapp"
                      ]
                    }
                  }
                },
                "type": "object"
              }
            },
            "entries": {
              "description": "Required for `collect`.",
              "type": "array",
              "items": {
                "type": "object"
              }
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Cost estimate and balances",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/CostEstimate"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/BadRequest",
      "description": "The body cannot make an assignment: the same checks of signers, methods and steps as creating it, and the document must be able to take one (e.g. `At least one signer is required.`)."
    },
    "403": {
      "description": "The workspace's plan lacks a verification or notification method sent (`PlanRestricted`), or an OAuth app's token lacks `documents:write` (`InsufficientScope`); also a deactivated membership or a role.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 403
                  },
                  "message": {
                    "type": "string",
                    "example": "WhatsApp notification is not available for your current plan."
                  },
                  "code": {
                    "type": "string",
                    "enum": [
                      "PlanRestricted",
                      "InsufficientScope"
                    ],
                    "example": "PlanRestricted"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "description": "The document is not in the workspace.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Document not found."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/documents/{documentId}/assignments/{assignmentId}/signers/{signerId}/resend`

Resend signature request

Resend the signature-request notification to a signer of the assignment who has not signed yet, while the document is pending signature. Paid notification methods are charged for each message that goes out.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    },
    {
      "name": "assignmentId",
      "in": "path",
      "description": "The assignment ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "signerId",
      "in": "path",
      "description": "The signer ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "Resend result",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "properties": {
                      "is_sent": {
                        "type": "boolean"
                      },
                      "document_id": {
                        "type": "string"
                      },
                      "signer_id": {
                        "type": "string"
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "The document is not pending signature, the signer is not part of the assignment, has already signed, or waits for an earlier signing step; or the account lacks credits for the reminder.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 400
                  },
                  "message": {
                    "type": "string",
                    "example": "This signer has already signed."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "description": "The assignment or signer was not found on this document.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Assignment not found."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/Forbidden",
      "description": "With a user's token or key, a deactivated membership, a role or an OAuth scope refuses it."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/documents/{documentId}/assignments/{assignmentId}/signers/{signerId}/estimate-resend-cost`

Estimate resend cost

Estimate the cost of resending the signature request to a signer. The response has its own shape (`total`, not `total_credits`), because a reminder uses no document.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    },
    {
      "name": "assignmentId",
      "in": "path",
      "description": "The assignment ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "signerId",
      "in": "path",
      "description": "The signer ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "Cost estimate",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/ResendCostEstimate"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/documents/{documentId}/assignments/{assignmentId}/reset-expiration`

Reset assignment expiration

Set a new expiration date for an assignment, or remove its expiration by sending `expires_at: null`. Not allowed once the document is closed.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    },
    {
      "name": "assignmentId",
      "in": "path",
      "description": "The assignment ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "expires_at"
          ],
          "properties": {
            "expires_at": {
              "description": "New expiration date (ISO 8601). Must be at least one hour in the future. Send `null` to remove the expiration — the key itself is required. A UTC offset in the value is honored (`2026-12-31T23:59:59-03:00` is `2027-01-01T02:59:59Z`); a value without one is read as UTC, and a date alone (`2026-12-31`) means 23:59 UTC that day.",
              "type": [
                "string",
                "null"
              ],
              "format": "date-time",
              "example": "2026-12-31T23:59:59Z"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The updated assignment",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Assignment"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/documents/{documentId}/assignments/{assignmentId}/signers/{signerId}/identity-verification`

Signer identity review

The signer's identity-document verification as the account's reviewer sees it. When the provider sends a verification to review, the account decides it with **Decide signer identity review**. The signer can still view and sign meanwhile; the review holds the document's certification instead (status `pending_identity_review` once every signer has signed).
Evidence is a summary kept by Assinafy (no images or biometrics). `evidence_available` is false when the provider keeps none or it could not be fetched yet. While the review is open, `images` lists the evidence images the reviewer can open with **Signer identity review image**. A verification the provider approved directly, without a review, shows no evidence here (`evidence_available` is false); its evidence is on the `signer_identity_verified` activity. For a document of an envelope, the signer's one check of the whole envelope, read from any of its documents they are on. Reachable with the `documents:read` OAuth scope; the images and the decision are not available to third-party OAuth applications (`403`): the review is made by a person, in Assinafy or its apps.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    },
    {
      "name": "assignmentId",
      "in": "path",
      "description": "The assignment ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "signerId",
      "in": "path",
      "description": "The signer ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "Identity review",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/SignerIdentityReview"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/Forbidden"
    },
    "404": {
      "description": "Assignment, signer or verification not found."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/documents/{documentId}/assignments/{assignmentId}/signers/{signerId}/identity-verification/images/{name}`

Signer identity review image

One evidence image of a signer identity verification that is `InReview`, for the account's reviewer: the document photos (or the digital document PDF) and the face photo. Fetched from the verification provider on each request and never stored by Assinafy. Available only while the review is open; once it is decided, every image returns 404. `name` must be one of the review's `images`. Not available to third-party OAuth applications (`403`), whatever their scopes: the images are for the person reviewing. Assinafy's own apps can open them with the `documents:read` scope, for the user signed in to them.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    },
    {
      "name": "assignmentId",
      "in": "path",
      "description": "The assignment ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "signerId",
      "in": "path",
      "description": "The signer ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "name",
      "in": "path",
      "description": "The image, as listed in the review's `images`.",
      "required": true,
      "schema": {
        "type": "string",
        "enum": [
          "document_front",
          "document_back",
          "document_pdf",
          "face"
        ]
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The image bytes (`image/jpeg`, `image/png`) or the document PDF (`application/pdf`). Sent with `Cache-Control: no-store`.",
      "content": {
        "image/jpeg": {
          "schema": {
            "type": "string",
            "format": "binary"
          }
        },
        "image/png": {
          "schema": {
            "type": "string",
            "format": "binary"
          }
        },
        "application/pdf": {
          "schema": {
            "type": "string",
            "format": "binary"
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/Forbidden"
    },
    "404": {
      "description": "Assignment, signer or verification not found, the review is not open, or there is no such image."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/documents/{documentId}/assignments/{assignmentId}/signers/{signerId}/identity-verification/review`

Decide signer identity review

Approves or rejects a signer whose identity verification is `InReview`, while the document is `pending_signature` or `pending_identity_review`. Approved: once every signer has signed and no other signer is under review, the document is certificated; the certificate states the approval by review and every difference found. Rejected: the document is cancelled (`rejected_by_user`, with the reviewer as the user) and every signer and copy receiver gets the cancellation notice; the note is never shown to them. The signer gets no message about the decision itself. For a document of an envelope, it decides the signer's one check of the whole envelope, from any of its documents they are on, and is recorded on each of them: approving releases each once signed, and rejecting cancels the whole envelope as its cancel does, each person told once. The decision is also reported to the verification provider. Not available to third-party OAuth applications (`403`), whatever their scopes: the certificate names the person who reviewed. Assinafy's own apps can decide it with the `documents:write` scope, and the reviewer is the user signed in to them.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    },
    {
      "name": "assignmentId",
      "in": "path",
      "description": "The assignment ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "signerId",
      "in": "path",
      "description": "The signer ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "decision"
          ],
          "properties": {
            "decision": {
              "type": "string",
              "enum": [
                "Approved",
                "Rejected"
              ],
              "example": "Approved"
            },
            "note": {
              "description": "Optional. Recorded in the activity log and sent to the provider; never shown to the signer.",
              "type": [
                "string",
                "null"
              ],
              "maxLength": 2000,
              "example": "Conferido por videochamada."
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Decided",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/SignerIdentityReview"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "Invalid decision, the verification is not `InReview`, or the document is no longer awaiting signatures (for a document of an envelope, none of the signer's documents of it is).",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 400
                  },
                  "message": {
                    "type": "string",
                    "example": "decision must be Approved or Rejected."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/Forbidden"
    },
    "404": {
      "description": "Assignment, signer or verification not found.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Identity verification not found."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/login`

Login

Authenticate with email and password and receive a JWT access token.

```json
{
  "security": [],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "email",
            "password"
          ],
          "properties": {
            "email": {
              "type": "string",
              "format": "email",
              "example": "user@example.com"
            },
            "password": {
              "type": "string",
              "format": "password",
              "example": "password"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Access token, user and accounts",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/AuthSession"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/authentication/request-password-reset`

Request password reset

Send the user an email with instructions to reset their password. Used when the password was forgotten or never set.

```json
{
  "security": [],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "email"
          ],
          "properties": {
            "email": {
              "type": "string",
              "format": "email",
              "example": "user@example.com"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Reset email sent",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "properties": {
                      "email": {
                        "type": "string",
                        "format": "email",
                        "example": "user@example.com"
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/authentication/reset-password`

Reset password

Reset the user's password using the token received by email.

```json
{
  "security": [],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "email",
            "new_password"
          ],
          "properties": {
            "email": {
              "type": "string",
              "format": "email",
              "example": "user@example.com"
            },
            "token": {
              "description": "Token received by email.",
              "type": "string",
              "example": "b3ac64d6c55b3ac64d6c55"
            },
            "new_password": {
              "type": "string",
              "format": "password",
              "example": "N3w_p4ssw0rd"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Password reset",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "properties": {
                      "email": {
                        "type": "string",
                        "format": "email",
                        "example": "user@example.com"
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/authentication/change-password`

Change password

Change the authenticated user's password. The body's `email` must be their own address (any letter case); any other address is refused (`403`), whether or not it has an account, and an `email` that is not text answers `400`.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "email",
            "password",
            "new_password"
          ],
          "properties": {
            "email": {
              "type": "string",
              "format": "email",
              "example": "user@example.com"
            },
            "password": {
              "description": "The current password.",
              "type": "string",
              "format": "password",
              "example": "X3$_!456aTa"
            },
            "new_password": {
              "description": "The new password.",
              "type": "string",
              "format": "password",
              "example": "X3$_!456aT"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Password changed",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "properties": {
                      "email": {
                        "type": "string",
                        "format": "email",
                        "example": "user@example.com"
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "403": {
      "$ref": "#/components/responses/Forbidden",
      "description": "The address is not the authenticated user's: `You can only change your own password.`"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/authentication/mfa/verify`

Complete a two-factor login

Exchanges the `mfa_token` returned by login for an access token. `code` is either the 6-digit code from the authenticator app or one of the recovery codes issued at enrollment. The challenge is single-use and expires 5 minutes after login.

```json
{
  "security": [],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "mfa_token",
            "code"
          ],
          "properties": {
            "mfa_token": {
              "description": "The token returned by the login response.",
              "type": "string"
            },
            "code": {
              "description": "A 6-digit authenticator code, or a recovery code such as ABCD-EFGH-JKMN.",
              "type": "string",
              "example": "123456"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Access token, user and accounts",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/AuthSession"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError",
      "description": "Invalid or already-used code"
    },
    "401": {
      "description": "The challenge expired, was already used, or too many codes were tried"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/contracts`

List contracts

List the contract terms tracked on the workspace's documents, most recently updated first. Every filter is optional and only narrows the list. To catch up after a webhook that never arrived, page this list until the contracts are older than your last sync: every change to the terms, by a user or by the hourly run, moves `updated_at`. Each document's history of contract events is at `GET /v1/documents/{documentId}/activities`.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "status",
      "in": "query",
      "description": "Contract status; several, comma-separated, match any of them.",
      "style": "form",
      "explode": false,
      "schema": {
        "type": "array",
        "items": {
          "type": "string",
          "enum": [
            "draft",
            "active",
            "expired",
            "terminated"
          ]
        }
      },
      "example": "active,expired"
    },
    {
      "name": "counterparty",
      "in": "query",
      "description": "Part of the counterparty name, or its exact CPF/CNPJ.",
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "search",
      "in": "query",
      "description": "Part of the document's name.",
      "schema": {
        "type": "string",
        "example": "Prestação de serviços"
      }
    },
    {
      "name": "end_date_from",
      "in": "query",
      "description": "Contracts ending on or after this date.",
      "schema": {
        "type": "string",
        "format": "date"
      }
    },
    {
      "name": "end_date_to",
      "in": "query",
      "description": "Contracts ending on or before this date.",
      "schema": {
        "type": "string",
        "format": "date"
      }
    },
    {
      "name": "ending_within_days",
      "in": "query",
      "description": "Contracts ending from today (the API's calendar day, Brasília time) to that many days later, both included; `0` is today. Any status: add `status=active` for the ones still in force.",
      "schema": {
        "type": "integer",
        "maximum": 3650,
        "minimum": 0,
        "example": 30
      }
    },
    {
      "name": "external_system",
      "in": "query",
      "description": "System of an external reference, e.g. `hubspot`.",
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "external_id",
      "in": "query",
      "description": "ID of an external reference in that system.",
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "sort",
      "in": "query",
      "description": "`end_date` or `updated_at`; prefix with `-` for descending (`-updated_at`, the default). Contracts without an end date come last either way.",
      "schema": {
        "type": "string",
        "example": "end_date"
      }
    },
    {
      "name": "expand",
      "in": "query",
      "description": "`document` embeds each contract's document.",
      "schema": {
        "type": "string"
      }
    },
    {
      "$ref": "#/components/parameters/Page"
    },
    {
      "$ref": "#/components/parameters/PerPage"
    }
  ],
  "responses": {
    "200": {
      "description": "A page of contracts",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/DocumentContract"
                    }
                  }
                },
                "type": "object"
              }
            ]
          },
          "examples": {
            "page": {
              "$ref": "#/components/examples/ContractList"
            },
            "expanded": {
              "$ref": "#/components/examples/ContractListExpanded"
            }
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ContractInputError"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/documents/{documentId}/contract`

Get a document's contract terms

The contract terms tracked on a document, with `amendments`: the documents that amend, renew or terminate it. For a document that is itself an amendment, its link and delta (`DocumentContractAmendment`) instead. Readable even after the workspace's plan stops including contract tracking.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/DocumentId"
    }
  ],
  "responses": {
    "200": {
      "description": "The contract terms, or the amendment",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "required": [
                      "resource"
                    ],
                    "oneOf": [
                      {
                        "$ref": "#/components/schemas/DocumentContract"
                      },
                      {
                        "$ref": "#/components/schemas/DocumentContractAmendment"
                      }
                    ]
                  }
                },
                "type": "object"
              }
            ]
          },
          "examples": {
            "contract": {
              "$ref": "#/components/examples/ContractWithAmendments"
            },
            "amendment": {
              "$ref": "#/components/examples/ContractAmendmentPending"
            }
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NoContractTerms"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/accounts/{accountId}/documents/{documentId}/contract`

Set a document's contract terms

Creates the document's contract terms, or changes only the attributes sent (see the request body for what `null` does). `external_references`, when sent, replaces the whole list. Records a `document_contract_updated` event when the terms change; a retry that changes nothing records none. The body must be a JSON object with at least one attribute. With `parent_document_id`, the document is instead linked as an amendment, renewal or termination of that contract (or of any document in its family, resolved to the root) and the other attributes are the terms it changes; a later `PUT` on a pending amendment merges into that delta (a `null` drops a key) and `parent_document_id: null` unlinks it; every change to the link records `document_contract_amendment_updated` on this document. The delta applies to the parent once the document is certificated (at once, if it already is), in signing order (the order the documents' certifications were recorded in, fixed for each once it is; a certificated document with none recorded waits, and is placed by its certification time an hour on): instruments signed earlier apply first, one that cannot holds the later ones back, and an instrument signed before one already applied is blocked instead of applied over it. Applying records `document_contract_amended`; an applied amendment is read-only. A cancelled, declined or expired document takes no new terms and no new link; terms or a link already on one stay and can still change. Send the `updated_at` you read with the write to have it refused (`409`) if the contract or link changed meanwhile. Contract tracking comes with every standard plan, the free plan too; `403` when the workspace's current subscription lacks it.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/DocumentId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "$ref": "#/components/schemas/DocumentContractInput"
        },
        "examples": {
          "full": {
            "$ref": "#/components/examples/ContractUpdateFull"
          },
          "partial": {
            "$ref": "#/components/examples/ContractUpdatePartial"
          },
          "references": {
            "$ref": "#/components/examples/ContractUpdateReferences"
          },
          "link": {
            "$ref": "#/components/examples/ContractAmendmentLink"
          }
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The contract terms as saved, or the amendment as linked",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "required": [
                      "resource"
                    ],
                    "oneOf": [
                      {
                        "$ref": "#/components/schemas/DocumentContract"
                      },
                      {
                        "$ref": "#/components/schemas/DocumentContractAmendment"
                      }
                    ]
                  }
                },
                "type": "object"
              }
            ]
          },
          "examples": {
            "contract": {
              "$ref": "#/components/examples/Contract"
            },
            "amendmentPending": {
              "$ref": "#/components/examples/ContractAmendmentPending"
            },
            "amendmentApplied": {
              "$ref": "#/components/examples/ContractAmendmentApplied"
            }
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ContractInputError"
    },
    "403": {
      "$ref": "#/components/responses/ContractPlanRestricted"
    },
    "409": {
      "$ref": "#/components/responses/ContractChanged"
    },
    "404": {
      "description": "The document is not in the workspace, the `parent_document_id` names a document outside it, or `parent_document_id: null` is sent for a document that is not an amendment.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Document not found."
                  }
                },
                "type": "object"
              }
            ]
          },
          "example": {
            "status": 404,
            "message": "Document not found."
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/documents/{documentId}/contract/related`

List the contracts related to a document

The family the document belongs to, seen from its root: the contract terms with every amendment, renewal and termination document linked to them (`amendments`). Works from the contract document and from any of its amendments alike.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/DocumentId"
    },
    {
      "name": "expand",
      "in": "query",
      "description": "`document` embeds the root contract's document, its `name` included, so an amendment's page names its parent without a second read; each entry of `amendments` already carries its own `document_name`. Without it the answer is as before.",
      "schema": {
        "type": "string",
        "enum": [
          "document"
        ]
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The root contract and its family",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "required": [
                      "resource"
                    ],
                    "allOf": [
                      {
                        "$ref": "#/components/schemas/DocumentContract"
                      }
                    ]
                  }
                },
                "type": "object"
              }
            ]
          },
          "examples": {
            "family": {
              "$ref": "#/components/examples/ContractWithAmendments"
            }
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NoContractTerms"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/documents/{documentId}/contract/renew`

Renew a contract

Starts the contract's next term: `end_date` moves by `renewal_term_months` (12 when unset), or to the `end_date` sent, which must be after the current one. A `value` sent replaces the current one. Reopens an expired contract; a terminated one returns `400`. Steps `term`, so the lifecycle alerts run again for the new term. Records a `document_contract_renewed` event. Contract tracking comes with every standard plan, the free plan too; `403` when the workspace's current subscription lacks it. The body names the `term` the renewal starts from (the contract's current one) or the `end_date` it renews to, so a renewal answered with a `5xx` error, or whose answer was lost, is safe to send again: the repeat is refused with `400`, as the contract is then in its next term and that date is no longer after the current one (or with `409`, when it sends the `updated_at` it read).

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/DocumentId"
    }
  ],
  "requestBody": {
    "required": false,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "end_date": {
              "description": "The new end date, after the current one. Absent, empty or `null`: the current end date plus the renewal term, stepped until it is in the future; `term` is then required.",
              "type": [
                "string",
                "null"
              ],
              "format": "date"
            },
            "value": {
              "description": "The new value; unchanged when not sent, cleared by `null`.",
              "type": [
                "number",
                "null"
              ]
            },
            "term": {
              "description": "The term this renewal starts from, the contract's current `term`. Required unless `end_date` is sent; for a contract in another term the renewal is refused, so a repeated request never renews twice.",
              "type": "integer",
              "minimum": 1,
              "example": 1
            },
            "updated_at": {
              "description": "Optional precondition: the contract's `updated_at` as read. Changed since: `409`, nothing renewed.",
              "type": "string",
              "format": "date-time",
              "example": "2026-10-23T12:00:00Z"
            }
          },
          "type": "object"
        },
        "examples": {
          "by_term": {
            "$ref": "#/components/examples/ContractRenewByTerm"
          },
          "explicit": {
            "$ref": "#/components/examples/ContractRenewExplicit"
          }
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The renewed contract",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "required": [
                      "resource"
                    ],
                    "allOf": [
                      {
                        "$ref": "#/components/schemas/DocumentContract"
                      }
                    ]
                  }
                },
                "type": "object"
              }
            ]
          },
          "examples": {
            "renewed": {
              "$ref": "#/components/examples/ContractRenewed"
            }
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ContractInputError"
    },
    "403": {
      "$ref": "#/components/responses/ContractPlanRestricted"
    },
    "409": {
      "$ref": "#/components/responses/ContractChanged"
    },
    "404": {
      "$ref": "#/components/responses/NoContractTerms"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/documents/{documentId}/contract/terminate`

Terminate a contract

Closes the contract: its status becomes `terminated` and the lifecycle job stops alerting on it. Records a `document_contract_terminated` event carrying the `reason`. Contract tracking comes with every standard plan, the free plan too; `403` when the workspace's current subscription lacks it.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/DocumentId"
    }
  ],
  "requestBody": {
    "required": false,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "reason": {
              "description": "Kept in the `document_contract_terminated` event; HTML is stripped.",
              "type": [
                "string",
                "null"
              ],
              "maxLength": 500
            },
            "updated_at": {
              "description": "Optional precondition: the contract's `updated_at` as read. Changed since: `409`, nothing terminated.",
              "type": "string",
              "format": "date-time",
              "example": "2026-10-23T12:00:00Z"
            }
          },
          "type": "object"
        },
        "examples": {
          "with_reason": {
            "$ref": "#/components/examples/ContractTerminate"
          }
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The terminated contract",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "required": [
                      "resource"
                    ],
                    "allOf": [
                      {
                        "$ref": "#/components/schemas/DocumentContract"
                      }
                    ]
                  }
                },
                "type": "object"
              }
            ]
          },
          "examples": {
            "terminated": {
              "$ref": "#/components/examples/ContractTerminated"
            }
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ContractInputError"
    },
    "403": {
      "$ref": "#/components/responses/ContractPlanRestricted"
    },
    "409": {
      "$ref": "#/components/responses/ContractChanged"
    },
    "404": {
      "$ref": "#/components/responses/NoContractTerms"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/documents`

List documents

List documents of the workspace, most recently updated first. Every row includes its `assignment` and `pages`; an `expand` parameter is ignored. For a lighter list without them, use `GET /v1/accounts/{accountId}/documents/search`.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "status",
      "in": "query",
      "description": "Status filter, e.g. `pending_signature`.",
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "envelope_id",
      "in": "query",
      "description": "Only the members of this envelope. Anything but text answers `400`.",
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "method",
      "in": "query",
      "description": "Signature method filter.",
      "schema": {
        "type": "string",
        "enum": [
          "virtual",
          "collect"
        ]
      }
    },
    {
      "name": "search",
      "in": "query",
      "description": "Partial match on document.name, signer.full_name, signer.email.",
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "tags",
      "in": "query",
      "description": "Comma-separated tag IDs; returns documents having ALL listed tags.",
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "sort",
      "in": "query",
      "description": "`name` or `updated_at`, ascending; prefix with `-` for descending (`-updated_at`, the default). Other values are ignored.",
      "schema": {
        "type": "string",
        "example": "-updated_at"
      }
    },
    {
      "$ref": "#/components/parameters/Page"
    },
    {
      "$ref": "#/components/parameters/PerPage"
    }
  ],
  "responses": {
    "200": {
      "description": "A page of documents",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/Document"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "`envelope_id` refused as sent: not text, an entry of `errors`.",
      "content": {
        "application/json": {
          "schema": {
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              }
            ]
          },
          "example": {
            "status": 400,
            "message": "Give envelope_id as text.",
            "errors": [
              {
                "field": "envelope_id",
                "code": "Invalid",
                "message": "Give envelope_id as text."
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/Forbidden",
      "description": "With a user's token or key, a deactivated membership, a role or an OAuth scope refuses it."
    },
    "404": {
      "description": "The workspace does not exist.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Account not found."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/documents`

Upload and create document

Create a document from an uploaded file. Maximum file size 25MB; maximum 2000 pages.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "multipart/form-data": {
        "schema": {
          "required": [
            "file"
          ],
          "properties": {
            "file": {
              "description": "The PDF file to upload.",
              "type": "string",
              "format": "binary"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The created document",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Document"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/documents/search`

Search documents (lightweight)

Search documents of the workspace, returning a compact representation (no expanded assignment/pages).

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/Search"
    },
    {
      "name": "status",
      "in": "query",
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "envelope_id",
      "in": "query",
      "description": "Only the members of this envelope. Anything but text answers `400`.",
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "sort",
      "in": "query",
      "description": "`name` or `updated_at`, ascending; prefix with `-` for descending (`-updated_at`, the default). Other values are ignored.",
      "schema": {
        "type": "string",
        "example": "-updated_at"
      }
    },
    {
      "$ref": "#/components/parameters/Page"
    },
    {
      "$ref": "#/components/parameters/PerPage"
    }
  ],
  "responses": {
    "200": {
      "description": "Matching documents",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/Document"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "`envelope_id` refused as sent: not text, an entry of `errors`.",
      "content": {
        "application/json": {
          "schema": {
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              }
            ]
          },
          "example": {
            "status": 400,
            "message": "Give envelope_id as text.",
            "errors": [
              {
                "field": "envelope_id",
                "code": "Invalid",
                "message": "Give envelope_id as text."
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/Forbidden",
      "description": "With a user's token or key, a deactivated membership, a role or an OAuth scope refuses it."
    },
    "404": {
      "description": "The workspace does not exist.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Account not found."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/documents/statuses`

List document statuses

The supported document statuses and whether a document in each status can be deleted.

| Status | Deletable | Description |
|--------|-----------|-------------|
| `uploading` | no | The document upload is in process. |
| `uploaded` | no | The document has been uploaded. |
| `metadata_processing` | no | The initial processing is under way. |
| `metadata_ready` | yes | The initial processing has been completed. |
| `expired` | yes | The signature deadline has been reached. |
| `certificating` | no | The document has been signed and is being certificated. |
| `certificated` | no | The document is certificated. |
| `rejected_by_signer` | yes | A signer declined signing the document. |
| `pending_signature` | yes | The document is waiting for signatures. |
| `pending_identity_review` | yes | Every signer has signed; the document waits for the account to review a signer's identity before it is certificated. Deleting it cancels it and notifies the signers. |
| `rejected_by_user` | yes | The signature process was cancelled by a user. |
| `failed` | yes | The document processing has failed. |

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "responses": {
    "200": {
      "description": "Supported statuses",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/DocumentStatus"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/documents/{documentId}`

Get document

Get a document by its ID. `decline_reason` is only present when the access token belongs to the document's creator. With a signer access code (`signer-access-code`), only documents the signer has been sent are found, and `assignment` holds only the signer's own items, without `signers`, `signing_urls`, `copy_receivers` or `summary`.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    },
    {
      "signerAccessCode": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    },
    {
      "name": "expand",
      "in": "query",
      "description": "`envelope_id` adds the id of the envelope the document was sent in, `envelope` its id and name; each is `null` for a document sent on its own. Send both separated by a comma. Read by the workspace only; without it the document reads as it always has.",
      "required": false,
      "style": "form",
      "explode": false,
      "schema": {
        "type": "array",
        "items": {
          "type": "string",
          "enum": [
            "envelope_id",
            "envelope"
          ]
        }
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The document",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Document"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "403": {
      "$ref": "#/components/responses/Forbidden",
      "description": "With a user's token or key, a deactivated membership, a role or an OAuth scope refuses it."
    },
    "400": {
      "$ref": "#/components/responses/SignerStepPending",
      "description": "The signer's step has not started yet (sequential signing)."
    },
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed",
      "description": "With a signer's access code: the code is closed."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `DELETE /v1/documents/{documentId}`

Delete document

Delete a document by its ID. Only documents in a deletable status can be removed (see GET /v1/documents/statuses). A document of an envelope, or one taken out of it, can't be deleted while any of the envelope's documents is open: cancel the envelope (`PUT /v1/accounts/{accountId}/envelopes/{envelopeId}/cancel`) first.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    }
  ],
  "responses": {
    "200": {
      "description": "Document deleted",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": [],
                    "example": []
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "409": {
      "description": "The document belongs to an envelope that is still open (`EnvelopeOpen`).",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 409
                  },
                  "code": {
                    "type": "string",
                    "enum": [
                      "EnvelopeOpen"
                    ]
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "The document is certificated, or still being uploaded or processed.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 400
                  },
                  "message": {
                    "type": "string",
                    "example": "Cannot delete a document with status: 'uploaded'."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/Forbidden",
      "description": "With a user's token or key, a deactivated membership, a role or an OAuth scope refuses it."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PATCH /v1/documents/{documentId}`

Rename document

Update a document's name. Only allowed before any assignment is created (i.e. while the document is in `uploaded` or `metadata_ready` status and has no signers yet); once the signature process has started or the document is certificated, the name is locked. The name is normalized: diacritics are removed and unsupported characters are replaced with dashes.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "name"
          ],
          "properties": {
            "name": {
              "type": "string",
              "maxLength": 255,
              "example": "Service agreement.pdf"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The updated document",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Document"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/documents/{documentId}/download/{artifactName}`

Download document artifact

Download a document artifact. With a signer access code only `original` is available (`403` otherwise): signers get the certified copy by e-mail or WhatsApp. Artifact types: original, certificated, certificate-page, pades, bundle. The pades artifact (signers' ICP-Brasil signatures + platform certification box) is only present on documents that had digital-certificate signers; `bundle` is a zip of the original, certificated and certificate-page artifacts, plus the pades artifact on documents that have one.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    },
    {
      "signerAccessCode": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    },
    {
      "name": "artifactName",
      "in": "path",
      "description": "Artifact type.",
      "required": true,
      "schema": {
        "type": "string",
        "enum": [
          "original",
          "certificated",
          "certificate-page",
          "pades",
          "bundle"
        ]
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The artifact binary",
      "content": {
        "application/pdf": {
          "schema": {
            "type": "string",
            "format": "binary"
          }
        }
      }
    },
    "403": {
      "description": "A signer access code asked for an artifact other than `original`; or, with a user's token or key, a deactivated membership, a role or an OAuth scope refuses it.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 403
                  },
                  "message": {
                    "type": "string",
                    "example": "The signed document is sent to the signer by e-mail or WhatsApp."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed",
      "description": "The signer access code is closed: the signer finished (for a document of an envelope, every document of it they are on) more than 30 minutes ago."
    },
    "400": {
      "$ref": "#/components/responses/SignerStepPending",
      "description": "The signer's step has not started yet (sequential signing)."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/documents/{documentSignatureHash}/verify`

Verify a signed document

Verify a document by its signature hash (found on a signed document) and return its certification details. Always returns `200`: when the hash is not found or the document is not signed, `is_valid` is `false`, the other fields are `null`, and `message` explains why. Public endpoint.

```json
{
  "security": [],
  "parameters": [
    {
      "name": "documentSignatureHash",
      "in": "path",
      "description": "The document signature hash.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "Verification result",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/DocumentVerification"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/documents/{documentId}/tags`

List document tags

List the tags attached to a document.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "documentId",
      "in": "path",
      "description": "The document ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "Attached tags",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/Tag"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/accounts/{accountId}/documents/{documentId}/tags`

Replace document tags

Replace the full set of tags attached to a document.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "documentId",
      "in": "path",
      "description": "The document ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "tags": {
              "description": "Tag IDs.",
              "type": "array",
              "items": {
                "type": "string"
              }
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Updated tags",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/Tag"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/documents/{documentId}/tags`

Attach document tags

Attach one or more tags to a document.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "documentId",
      "in": "path",
      "description": "The document ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "tags": {
              "description": "Tag IDs.",
              "type": "array",
              "items": {
                "type": "string"
              }
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Attached tags",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/Tag"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `DELETE /v1/accounts/{accountId}/documents/{documentId}/tags/{tagId}`

Detach document tag

Detach a single tag from a document.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "documentId",
      "in": "path",
      "description": "The document ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "tagId",
      "in": "path",
      "description": "The tag ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "Tag detached",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "properties": {
                      "detached": {
                        "type": "boolean",
                        "example": true
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/fields`

List fields

List the field definitions of a workspace.

When `include_standard` is enabled, records of type `signature`, `initial` and `signatureDate` are also returned. Every workspace has one of each, created with it; they cannot be created, and a field can't be placed on the `Editor` role with them.

To find the fields a sender can place on a document or template, list with `include_standard=true`: the result is every active field definition, signature fields included, and each one's `id` is the `field_id` to place.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "include_inactive",
      "in": "query",
      "description": "Include inactive field definitions.",
      "schema": {
        "type": "boolean"
      }
    },
    {
      "name": "include_standard",
      "in": "query",
      "description": "Include standard field types (signature, initial, signatureDate).",
      "schema": {
        "type": "boolean"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "Field definitions",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/Field"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/fields`

Create field

Create a field definition in the workspace.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "name",
            "type"
          ],
          "properties": {
            "name": {
              "type": "string",
              "example": "Full name"
            },
            "type": {
              "type": "string",
              "example": "text"
            },
            "regex": {
              "type": [
                "string",
                "null"
              ]
            },
            "is_required": {
              "type": "boolean"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The created field",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Field"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/fields/{fieldId}`

Get field

Retrieve a single field definition.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "fieldId",
      "in": "path",
      "description": "The field ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The field",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Field"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/accounts/{accountId}/fields/{fieldId}`

Update field

Update a field definition.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "fieldId",
      "in": "path",
      "description": "The field ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "name": {
              "type": "string"
            },
            "regex": {
              "type": [
                "string",
                "null"
              ]
            },
            "is_active": {
              "type": "boolean"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The updated field",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Field"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `DELETE /v1/accounts/{accountId}/fields/{fieldId}`

Delete field

Delete a field definition.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "fieldId",
      "in": "path",
      "description": "The field ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "Field deleted",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": [],
                    "example": []
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/fields/{fieldId}/validate`

Validate field value

Validate an input value against a field definition. Typically called with a signer access code during the signing flow.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    },
    {
      "signerAccessCode": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "fieldId",
      "in": "path",
      "description": "The field ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "value"
          ],
          "properties": {
            "value": {
              "description": "The input value to validate.",
              "example": "400.676.228-36"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Validation result",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/FieldValidation"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/BadRequest",
      "description": "`value` is missing, or the signer's step has not started yet (sequential signing)."
    },
    "403": {
      "$ref": "#/components/responses/Forbidden",
      "description": "With a user's token or key, a deactivated membership, a role or an OAuth scope refuses it."
    },
    "404": {
      "description": "The field is not one of the workspace, or the workspace does not exist.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Field 63488ffb7adf435aba319787 was not found."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed",
      "description": "With a signer's access code: the code is closed."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/fields/validate-multiple`

Validate multiple field values

Validate multiple input values at once. The request body is a JSON array of `{field_id, value}` objects.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    },
    {
      "signerAccessCode": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "type": "array",
          "items": {
            "required": [
              "field_id",
              "value"
            ],
            "properties": {
              "field_id": {
                "description": "The field definition ID.",
                "type": "string",
                "example": "63488ffb7adf435aba319787"
              },
              "value": {
                "description": "The input value to validate.",
                "example": "1111111111111"
              }
            },
            "type": "object"
          }
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Validation results",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/FieldValidationResult"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/BadRequest",
      "description": "`value` is missing, or the signer's step has not started yet (sequential signing)."
    },
    "403": {
      "$ref": "#/components/responses/Forbidden",
      "description": "With a user's token or key, a deactivated membership, a role or an OAuth scope refuses it."
    },
    "404": {
      "description": "The field is not one of the workspace, or the workspace does not exist.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Field 63488ffb7adf435aba319787 was not found."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed",
      "description": "With a signer's access code: the code is closed."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/field-types`

List field types

List the types a workspace can create field definitions with (`POST /v1/accounts/{accountId}/fields`). The built-in `signature`, `initial` and `signatureDate` types are not listed: they can't be created, and their definitions come from `GET /v1/accounts/{accountId}/fields?include_standard=true`. `cpf` expects 11 digits; `cnpj` accepts 14-char values (letters A-Z allowed in positions 1–12 per the CNPJ Alfanumérico rule; check digits 13–14 stay numeric). Punctuation is ignored during validation.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "responses": {
    "200": {
      "description": "Field types",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/FieldType"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/users/self/mfa`

List two-factor methods

The authenticated user's enrolled two-factor methods and how many recovery codes remain unused.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "responses": {
    "200": {
      "description": "Enrolled methods",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "properties": {
                      "methods": {
                        "type": "array",
                        "items": {
                          "properties": {
                            "id": {
                              "type": "string",
                              "example": "a1b2c3d4e5f60718293a4b5c6d7e8f90"
                            },
                            "type": {
                              "type": "string",
                              "example": "Totp"
                            },
                            "label": {
                              "type": "string",
                              "example": "My phone"
                            },
                            "confirmed_at": {
                              "type": "string",
                              "format": "date-time",
                              "example": "2026-09-09T14:21:03Z"
                            },
                            "last_used_at": {
                              "type": "string",
                              "format": "date-time",
                              "example": "2026-09-09T18:02:44Z"
                            }
                          },
                          "type": "object"
                        }
                      },
                      "recovery_codes_remaining": {
                        "type": "integer",
                        "example": 8
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/users/self/mfa/totp`

Start authenticator enrollment

Creates an unconfirmed authenticator method and returns the shared secret. The secret is returned only by this call and cannot be retrieved again. Two-factor authentication is not active until the enrollment is confirmed.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "requestBody": {
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "label": {
              "type": "string",
              "example": "My phone"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Enrollment started",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "properties": {
                      "id": {
                        "type": "string",
                        "example": "a1b2c3d4e5f60718293a4b5c6d7e8f90"
                      },
                      "secret": {
                        "type": "string",
                        "example": "GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ"
                      },
                      "provisioning_uri": {
                        "type": "string",
                        "example": "otpauth://totp/user%40example.com?issuer=Assinafy&secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ"
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/users/self/mfa/totp/confirm`

Confirm authenticator enrollment

Activates the method by proving one live code from the NEW device (`code`). Returns the recovery codes, which are shown only once and cannot be retrieved again. From this point every login requires a second factor. If the user already has a confirmed method of the same type, confirming REPLACES it — the old one is soft-deleted and recovery codes are reissued — so this call additionally requires re-authentication via `password` or `reauth_code` (a live code from the CURRENT device, or one of the existing recovery codes), exactly like disabling a method. First-time enrollment needs neither.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "id",
            "code"
          ],
          "properties": {
            "id": {
              "type": "string",
              "example": "a1b2c3d4e5f60718293a4b5c6d7e8f90"
            },
            "code": {
              "description": "Live code from the NEW device being confirmed.",
              "type": "string",
              "example": "123456"
            },
            "password": {
              "description": "Re-authentication proof, required only when replacing an existing confirmed method.",
              "type": "string",
              "format": "password"
            },
            "reauth_code": {
              "description": "Re-authentication proof alternative to password: a live code from the CURRENT device, or a recovery code. Required only when replacing an existing confirmed method.",
              "type": "string"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Two-factor enabled",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "properties": {
                      "recovery_codes": {
                        "type": "array",
                        "items": {
                          "type": "string",
                          "example": "ABCD-EFGH-JKMN"
                        }
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError",
      "description": "Invalid enrollment code, or — when replacing an existing method — missing or invalid re-authentication."
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/users/self/mfa/recovery-codes`

Regenerate recovery codes

Issues a fresh set of ten recovery codes and invalidates the previous set. Requires the current password, a live authenticator code, or one of the existing recovery codes (which is then consumed).

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "password": {
              "type": "string",
              "format": "password"
            },
            "code": {
              "description": "A live 6-digit authenticator code, or one of the existing recovery codes.",
              "type": "string",
              "example": "123456"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "New recovery codes",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "properties": {
                      "recovery_codes": {
                        "type": "array",
                        "items": {
                          "type": "string",
                          "example": "ABCD-EFGH-JKMN"
                        }
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `DELETE /v1/users/self/mfa/{customId}`

Remove a two-factor method

Removes an enrolled method. Requires the current password, a live authenticator code, or one of the existing recovery codes (which is then consumed), so that a stolen session cannot silently disable two-factor authentication. Removing the last method also discards the recovery codes.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "name": "customId",
      "in": "path",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "password": {
              "type": "string",
              "format": "password"
            },
            "code": {
              "description": "A live 6-digit authenticator code, or one of the existing recovery codes.",
              "type": "string",
              "example": "123456"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Method removed",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "properties": {
                      "is_mfa_enabled": {
                        "type": "boolean",
                        "example": false
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/users/self/notification-preferences`

Get my notification preferences

Which owner-facing document notifications the authenticated user receives by e-mail. All thirteen keys are always returned; everything defaults to `true`. Account and security e-mail (welcome, password reset, invitations, account deletion) is not configurable and never appears here. Not available to third-party OAuth applications (`403`), whatever their scopes: these are the user's personal settings. Assinafy's own apps can reach it.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "responses": {
    "200": {
      "description": "The current preferences",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/NotificationPreferences"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/users/self/notification-preferences`

Update my notification preferences

Merges the supplied map into the authenticated user's preferences. Send only the keys you want to change — omitted keys keep their current value. Setting a key to `false` stops that e-mail for this user in every account they belong to. Returns the full map. An unknown code, a non-boolean value, or an empty body is rejected with 400 and nothing is written. Not available to third-party OAuth applications (`403`), whatever their scopes: these are the user's personal settings. Assinafy's own apps can reach it.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "$ref": "#/components/schemas/NotificationPreferences"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The updated preferences",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/NotificationPreferences"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/oauth/token`

Exchange a code, refresh token, or subject token for an access token

Implements the RFC 6749 §5.1/§5.2 token-endpoint body contract in
     *         both directions: a successful exchange returns a flat JSON object with
     *         `access_token` at the top level, and a failure returns a flat
     *         `{error, error_description}` object — neither is wrapped in this API's
     *         usual response envelope, since no standard OAuth client library (or MCP
     *         connector) would find `access_token` or `error` inside a `data` key.
     *
     *         The third grant, `urn:ietf:params:oauth:grant-type:token-exchange` (RFC 8693),
     *         is restricted to a confidential internal-service client (see `/oauth/introspect`)
     *         and trades a front-end resource's access token (the `subject_token`) for one
     *         minted for THIS API — the crossing an MCP server makes after a user consents,
     *         since its own token can never be forwarded here directly. The issued token is
     *         never wider than the subject: `scope`, when sent, must be a subset of the
     *         subject's own scopes, and `resource` must equal this API's own resource
     *         identifier exactly. No refresh token is issued; the caller re-exchanges from
     *         the user's own token instead of holding unattended API access.

Replaying a refresh token already rotated ends the whole authorization. For Assinafy's own apps it ends that device's session.

```json
{
  "security": [],
  "requestBody": {
    "required": true,
    "content": {
      "application/x-www-form-urlencoded": {
        "schema": {
          "$ref": "#/components/schemas/OAuthTokenRequest"
        }
      },
      "application/json": {
        "schema": {
          "$ref": "#/components/schemas/OAuthTokenRequest"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The token response",
      "content": {
        "application/json": {
          "schema": {
            "properties": {
              "access_token": {
                "type": "string"
              },
              "issued_token_type": {
                "description": "Present only for the token-exchange grant, per RFC 8693 §2.2.1.",
                "type": "string",
                "example": "urn:ietf:params:oauth:token-type:access_token"
              },
              "token_type": {
                "type": "string",
                "example": "Bearer"
              },
              "expires_in": {
                "description": "For the token-exchange grant, clamped to the subject token's own remaining lifetime as well as the exchanged-token TTL — never longer than either.",
                "type": "integer",
                "example": 3600
              },
              "refresh_token": {
                "description": "Present only when the `offline_access` scope was requested AND consented on the authorization_code/refresh_token grants. Never present for the token-exchange grant. Without it a client must send the user through the authorization flow again once the access token expires.",
                "type": [
                  "string",
                  "null"
                ]
              },
              "scope": {
                "description": "The scope of the ACCESS token. `offline_access` is a request-time signal rather than a permission, so it never appears here even when it was requested.",
                "type": "string",
                "example": "documents:read"
              },
              "id_token": {
                "description": "A signed OIDC id_token (RS256). Present only when the openid scope was granted.",
                "type": [
                  "string",
                  "null"
                ]
              }
            },
            "type": "object"
          }
        }
      }
    },
    "400": {
      "description": "`invalid_grant` (bad, expired, replayed, or wrong-client authorization code; a `code_verifier` outside the RFC 7636 grammar of 43-128 unreserved characters; redirect_uri mismatch; a refresh token whose authorization no longer includes `offline_access`; for token-exchange, a `subject_token` that is unusable, itself an exchanged token, not visible to service clients, or expired), `invalid_target` (a `resource` this server does not issue tokens for, one disagreeing with the authorized value, or — for token-exchange — one other than this API's own resource identifier), `invalid_scope` (token-exchange only: the requested `scope` is not a subset of the subject token's own scopes), `invalid_request` (token-exchange only: an unsupported `subject_token_type`/`requested_token_type`), or `unsupported_grant_type`",
      "content": {
        "application/json": {
          "schema": {
            "properties": {
              "error": {
                "type": "string",
                "enum": [
                  "invalid_grant",
                  "invalid_target",
                  "invalid_scope",
                  "invalid_request",
                  "unsupported_grant_type"
                ]
              },
              "error_description": {
                "type": "string"
              }
            },
            "type": "object"
          }
        }
      }
    },
    "401": {
      "description": "`invalid_client` — unknown/disabled client or failed client authentication. The description never reveals whether client_id exists.",
      "content": {
        "application/json": {
          "schema": {
            "properties": {
              "error": {
                "type": "string",
                "example": "invalid_client"
              },
              "error_description": {
                "type": "string",
                "example": "Client authentication failed."
              }
            },
            "type": "object"
          }
        }
      }
    },
    "429": {
      "description": "Rate limit exceeded; wait the `Retry-After` seconds. Limited per caller IP."
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/oauth/revoke`

Revoke a token

Revokes an access or refresh token. A refresh token ends the whole authorization; for Assinafy's own apps, it ends that device's session, and the authorization stays. Every token outcome always returns 200 — including a token that does not exist, is already revoked, or is malformed — so the endpoint can never be used to probe whether a token exists. The one exception is failed client authentication, which returns 401.

```json
{
  "security": [],
  "requestBody": {
    "required": true,
    "content": {
      "application/x-www-form-urlencoded": {
        "schema": {
          "$ref": "#/components/schemas/OAuthRevokeRequest"
        }
      },
      "application/json": {
        "schema": {
          "$ref": "#/components/schemas/OAuthRevokeRequest"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Revoked"
    },
    "401": {
      "description": "`invalid_client` — the only case that is not reported as success. Every token outcome (revoked, already revoked, unknown, malformed) always returns 200; only failed client authentication returns 401.",
      "content": {
        "application/json": {
          "schema": {
            "properties": {
              "error": {
                "type": "string",
                "example": "invalid_client"
              },
              "error_description": {
                "type": "string",
                "example": "Client authentication failed."
              }
            },
            "type": "object"
          }
        }
      }
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/oauth/userinfo`

OpenID Connect userinfo

Claims about the user who authorized this token. Requires the openid scope; name requires profile and email requires email. Per OIDC Core §5.3.2, the response is a flat JSON object of claims — never this API's usual `{status, data, message}` envelope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "responses": {
    "200": {
      "description": "The user's claims",
      "content": {
        "application/json": {
          "schema": {
            "properties": {
              "sub": {
                "type": "string",
                "example": "d6zqpbyog2v3xvxerwn8la94"
              },
              "name": {
                "type": [
                  "string",
                  "null"
                ],
                "example": "Maria Silva"
              },
              "email": {
                "type": [
                  "string",
                  "null"
                ],
                "format": "email"
              },
              "email_verified": {
                "type": [
                  "boolean",
                  "null"
                ]
              }
            },
            "type": "object"
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/Forbidden"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/documents/{documentId}/thumbnail`

Download document thumbnail

Download the thumbnail image of a document's first page.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    },
    {
      "signerAccessCode": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    },
    {
      "$ref": "#/components/parameters/SignedRender"
    }
  ],
  "responses": {
    "200": {
      "description": "The thumbnail image",
      "content": {
        "image/*": {
          "schema": {
            "type": "string",
            "format": "binary"
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "503": {
      "$ref": "#/components/responses/SignedRenderPending"
    },
    "304": {
      "description": "Not modified: the `If-None-Match` or `If-Modified-Since` sent matches the page. No body."
    },
    "403": {
      "$ref": "#/components/responses/Forbidden",
      "description": "With a user's token or key, a deactivated membership, a role or an OAuth scope refuses it."
    },
    "400": {
      "$ref": "#/components/responses/SignerStepPending",
      "description": "The signer's step has not started yet (sequential signing)."
    },
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed",
      "description": "With a signer's access code: the code is closed."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/documents/{documentId}/pages/{pageId}/download`

Download document page

Download the rendered image of a specific document page.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    },
    {
      "signerAccessCode": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    },
    {
      "name": "pageId",
      "in": "path",
      "description": "The page ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    },
    {
      "$ref": "#/components/parameters/SignedRender"
    }
  ],
  "responses": {
    "200": {
      "description": "The page image",
      "content": {
        "image/*": {
          "schema": {
            "type": "string",
            "format": "binary"
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "503": {
      "$ref": "#/components/responses/SignedRenderPending"
    },
    "304": {
      "description": "Not modified: the `If-None-Match` or `If-Modified-Since` sent matches the page. No body."
    },
    "403": {
      "$ref": "#/components/responses/Forbidden",
      "description": "With a user's token or key, a deactivated membership, a role or an OAuth scope refuses it."
    },
    "400": {
      "$ref": "#/components/responses/SignerStepPending",
      "description": "The signer's step has not started yet (sequential signing)."
    },
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed",
      "description": "With a signer's access code: the code is closed."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/public/documents/{documentId}`

View public document

Retrieve a publicly shared document by ID. Public endpoint.

```json
{
  "security": [],
  "parameters": [
    {
      "name": "documentId",
      "in": "path",
      "description": "The document ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The public document",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Document"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/public/documents/{documentId}/send-token`

Send access token for public document

Send a one-time access token to a signer of a public document, so they can view it. The document must be pending signature, and `recipient` must belong to a signer of the document who has not signed yet. Only the `email` channel is supported. Public endpoint.

```json
{
  "security": [],
  "parameters": [
    {
      "name": "documentId",
      "in": "path",
      "description": "The document ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "channel",
            "recipient"
          ],
          "properties": {
            "channel": {
              "description": "Where to send the token. Only `email` is supported.",
              "type": "string",
              "enum": [
                "email"
              ],
              "example": "email"
            },
            "recipient": {
              "description": "The signer's address on that channel — for `email`, the signer's email address.",
              "type": "string",
              "example": "signer@example.com"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Token sent",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "properties": {
                      "document": {
                        "properties": {
                          "resource": {
                            "type": "string",
                            "example": "document"
                          },
                          "id": {
                            "type": "string",
                            "example": "a1b2c3d4e5f6a7b8c9d0"
                          },
                          "name": {
                            "type": "string",
                            "example": "Contract.pdf"
                          },
                          "page_count": {
                            "type": "integer",
                            "example": 3
                          },
                          "created_by": {
                            "type": "string",
                            "example": "Jane Doe"
                          }
                        },
                        "type": "object"
                      },
                      "channel": {
                        "type": "string",
                        "example": "email"
                      },
                      "recipient": {
                        "type": "string",
                        "example": "signer@example.com"
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/signers`

List signers

List the signers of a workspace.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "search",
      "in": "query",
      "description": "Filter by full_name or email.",
      "schema": {
        "type": "string"
      }
    },
    {
      "$ref": "#/components/parameters/Page"
    },
    {
      "$ref": "#/components/parameters/PerPage"
    }
  ],
  "responses": {
    "200": {
      "description": "A page of signers",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/Signer"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/signers`

Create signer

Create a signer in the workspace.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "full_name"
          ],
          "properties": {
            "full_name": {
              "type": "string",
              "example": "John Dove"
            },
            "email": {
              "type": "string",
              "format": "email",
              "example": "person@example.com"
            },
            "whatsapp_phone_number": {
              "description": "E.164; normalized on save.",
              "type": "string",
              "example": "+5548999990000"
            },
            "government_id": {
              "description": "Signer's CPF (11 digits) or CNPJ (14 characters; may be alphanumeric). Formatting is accepted and the value is normalized on save. Required before the signer can use a verification method that identifies them by CPF.",
              "type": "string",
              "example": "390.533.447-05"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The created signer",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Signer"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/signers/{signerId}`

Get signer

Retrieve a signer's information.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "signerId",
      "in": "path",
      "description": "The signer ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The signer",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Signer"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/accounts/{accountId}/signers/{signerId}`

Update signer

Update a signer's information. Only the fields sent change; send just the ones you are changing.

**Clearing a field:** `null` or `"` clears `email`, `whatsapp_phone_number` and `government_id` (`whatsapp_phone_number` is stored as `null` either way; the other two keep the value sent). `full_name` cannot be cleared.

**Verification integrity:** `email` / `whatsapp_phone_number` cannot be changed while the signer has verified that channel on an in-flight (not yet certificated) document — the response is `400` naming the offending document(s). Already-certificated documents do not block updates, except a document of an envelope while another of the signer's documents of it is in flight. Changing a channel that has *unverified* in-flight requests, or unverified requests on a document of an envelope while another of the signer's documents of it is in flight, rotates their access/verification codes (invalidating previously sent links/OTPs); use the resend endpoint to redeliver. `full_name` can always be updated.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "signerId",
      "in": "path",
      "description": "The signer ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "full_name": {
              "type": "string",
              "example": "John Dove"
            },
            "email": {
              "description": "Can't be changed or cleared while the signer has verified it on an in-flight document, or on a document of an envelope while another of their documents of it is in flight (`400`).",
              "type": "string",
              "format": "email",
              "example": "person@example.com"
            },
            "whatsapp_phone_number": {
              "description": "E.164; normalized on save, and compared after normalizing, so the same number in another format is not a change. Can't be changed or cleared while the signer has verified it on an in-flight document, or on a document of an envelope while another of their documents of it is in flight (`400`).",
              "type": "string",
              "example": "+5548999990000"
            },
            "government_id": {
              "description": "Signer's CPF (11 digits) or CNPJ (14 characters; may be alphanumeric). Formatting is accepted and the value is normalized on save.",
              "type": "string",
              "example": "390.533.447-05"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The updated signer",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Signer"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/BadRequest",
      "description": "A field is invalid (with `errors`, e.g. `Full Name cannot be blank.`), or the change would replace an e-mail or WhatsApp number the signer has verified on an active document."
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "403": {
      "$ref": "#/components/responses/Forbidden",
      "description": "With a user's token or key, a deactivated membership, a role or an OAuth scope refuses it."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `DELETE /v1/accounts/{accountId}/signers/{signerId}`

Delete signer

Delete a signer.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "signerId",
      "in": "path",
      "description": "The signer ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "Signer deleted",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": [],
                    "example": []
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/signers/self`

Get current signer

Return the signer identified by the signer access code, including the `has_signature`/`has_initial`/`is_signature_reusable` flags and the `verification_method` of this signature request. Available before verification, so the signing page can pick the right first step.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "responses": {
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed"
    },
    "200": {
      "description": "The signer",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/SignerSelf"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/SignerStepPending",
      "description": "The signer's step has not started yet (sequential signing)."
    },
    "403": {
      "description": "Called with an OAuth application token instead of the access code.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 403
                  },
                  "message": {
                    "type": "string",
                    "example": "This endpoint cannot be accessed with an OAuth application token."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/signers/identity-verification`

Identity document verification status

Current state of the signer's identity document verification. Backfills a pending decision from the provider. Uses the signer access code.

**One live session per signer:** `can_start` is true before a provider session exists, and again once that session is Expired -- the capture link lapsed before anything was submitted, so the signer may open a new one. Once a session is Pending, In Review, Approved, Declined or Abandoned it is false. A signer who reaches Declined or Abandoned cannot restart here; the account must start a whole new process (new document, new assignment). With the code of a document of an envelope, this is the signer's one check for the whole envelope, whichever of their codes of it asks.

**In review:** the signer continues -- views, confirms their data and signs -- while the account reviews the result. The document is certificated only after the account approves every signer under review; if it rejects one, the document is cancelled and every signer receives the cancellation notice.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "responses": {
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed"
    },
    "200": {
      "description": "Verification state",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "properties": {
                      "can_start": {
                        "description": "True when no session exists yet, or the last one Expired. With the code of a document of an envelope, false once no document of it, not taken out, awaits the signer's signature.",
                        "type": "boolean",
                        "example": false
                      },
                      "status": {
                        "type": [
                          "string",
                          "null"
                        ],
                        "example": "Pending"
                      },
                      "url": {
                        "description": "The provider's capture URL while the session is Pending; null otherwise. A bearer link: open it as-is (including any `#t=` fragment); never log it or store it in the browser.",
                        "type": [
                          "string",
                          "null"
                        ],
                        "example": "https://verify.assinafy.com.br/c/01a0ed77-2afe-7f4b-bca9-349edcf4bb99#t=token"
                      },
                      "expires_at": {
                        "description": "When the capture link stops working. Verification may still be processed or reviewed after it. Null for providers that do not report it.",
                        "type": [
                          "string",
                          "null"
                        ],
                        "format": "date-time",
                        "example": "2026-10-07T21:18:47Z"
                      },
                      "completed_at": {
                        "type": [
                          "string",
                          "null"
                        ],
                        "format": "date-time",
                        "example": "2026-08-10T20:32:00Z"
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/SignerStepPending",
      "description": "The signer's step has not started yet (sequential signing)."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/signers/identity-verification`

Start identity document verification

Records the signer's biometric-processing consent and opens a provider session. Returns the hosted verification URL to redirect to. Uses the signer access code.

**One live session per signer:** this succeeds when no session exists yet, or when the last one Expired (the capture link lapsed before anything was submitted); the expired session is replaced by a new one. Otherwise -- Pending, In Review, Approved, Declined or Abandoned -- it returns `400`. A resend never destroys or replaces a live provider session, and the account is billed once per signer at assignment creation, never again on a restart. Recovering from a Declined or Abandoned verification means the account must start a whole new process (new document, new assignment). With the code of a document of an envelope, it starts the signer's one check for the whole envelope: one session, whichever of their codes of it is used, billed once with the envelope.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "consent"
          ],
          "properties": {
            "consent": {
              "description": "Must be true. Explicit consent to biometric processing.",
              "type": "boolean",
              "example": true
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed"
    },
    "200": {
      "description": "Session created",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "properties": {
                      "id": {
                        "type": "string",
                        "example": "6152120297080d55bdd13197"
                      },
                      "url": {
                        "description": "The provider's capture URL for the session just opened. A bearer link: open it as-is (including any `#t=` fragment); never log it or store it in the browser.",
                        "type": "string",
                        "example": "https://verify.assinafy.com.br/c/01a0ed77-2afe-7f4b-bca9-349edcf4bb99#t=token"
                      },
                      "expires_at": {
                        "type": [
                          "string",
                          "null"
                        ],
                        "format": "date-time",
                        "example": "2026-10-07T21:18:47Z"
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "Consent missing, this signer already has a verification session that is not Expired (with the code of a document of an envelope, on any of their documents of it) or can no longer verify (with the code of a document of an envelope, also once no document of it, not taken out, awaits their signature), another start of the signer's envelope check is still running (`Identity verification is already being started for this signer. Try again shortly.`), the signer has no full name, or the signer's step has not started yet (sequential signing).",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 400
                  },
                  "message": {
                    "type": "string",
                    "example": "Consent is required to start identity verification."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/signers/{signerId}/document`

Get signer's document

Return the document and the signer's assignment items, scoped to the signer access code. The response adds `current_signer`: this signer, with their `verification_method`, `notification_methods` and `completed` (whether they have already signed). Once the signer is done (signed, or the document was declined, cancelled, expired or failed; for a document of an envelope, with every document of the envelope they are on) for more than 30 minutes, or the workspace was deleted, the code is closed: this returns only `id`, `name`, `status`, `current_signer` and `certified_copy`, with `is_signer_access_closed: true` (and `envelope`, when asked for), and the signer's other endpoints answer `410`. `certified_copy` says whether the signer can ask for the certified copy again (`POST /v1/sign/certified-copy`): `available`, the `notification_method` it goes on and, for WhatsApp, `resends_left`. Unlike `GET /v1/sign`, this answers before a certificate or identity-check signer confirms their data, so their link learns its envelope here.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "parameters": [
    {
      "name": "signerId",
      "in": "path",
      "description": "The signer ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "expand",
      "in": "query",
      "description": "`envelope` adds the envelope the code belongs to, its id and name, also once the code is closed and when the code's own document was taken out (the code still reaches the rest); `null` for a link outside an envelope or whose envelope was deleted. Without it the document reads as it always has.",
      "required": false,
      "style": "form",
      "explode": false,
      "schema": {
        "type": "array",
        "items": {
          "type": "string",
          "enum": [
            "envelope"
          ]
        }
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The document with the signer's items",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/SigningDocument"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound",
      "description": "The signer is not the code's, or the document was deleted."
    },
    "400": {
      "$ref": "#/components/responses/SignerStepPending",
      "description": "The signer's step has not started yet (sequential signing)."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/sign`

View document to sign

Retrieve the document a signer has been invited to sign, using the signer access code. Marks the document as viewed. The assignment carries only this signer's items, and leaves out the other signers, their signing links (`signing_urls`), the copy receivers and the signing `summary`. The response adds `current_signer`: this signer, with their `verification_method`, `notification_methods` and `completed` (whether they have already signed). Returns 409 while the document is still being prepared (retry with backoff). Once the signer is done (signed, or the document was declined, cancelled, expired or failed; for a document of an envelope, with every document of the envelope they are on) for more than 30 minutes, or the workspace was deleted, the code is closed: this returns only `id`, `name`, `status`, `current_signer` and `certified_copy`, with `is_signer_access_closed: true` (and `envelope`, when asked for), and the signer's other endpoints answer `410`. `certified_copy` says whether the signer can ask for the certified copy again (`POST /v1/sign/certified-copy`): `available`, the `notification_method` it goes on and, for WhatsApp, `resends_left`.

**Signers whose verification method is `DigitalCertificate`** must have confirmed their data *and* accepted the terms before this returns the document; otherwise it is `400`. Both are satisfied in one call to `PUT /v1/documents/{documentId}/signers/confirm-data` with `has_accepted_terms: true`, so send that before this endpoint — the `has_accepted_terms` query parameter here is too late to open the gate. `PUT /v1/signers/accept-terms` also works and is never gated.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "parameters": [
    {
      "name": "has_accepted_terms",
      "in": "query",
      "description": "Set true to record terms acceptance.",
      "schema": {
        "type": "boolean"
      }
    },
    {
      "name": "expand",
      "in": "query",
      "description": "`envelope` adds the envelope the code belongs to, its id and name, also once the code is closed and when the code's own document was taken out (the code still reaches the rest); `null` for a link outside an envelope or whose envelope was deleted. Without it the document reads as it always has.",
      "required": false,
      "style": "form",
      "explode": false,
      "schema": {
        "type": "array",
        "items": {
          "type": "string",
          "enum": [
            "envelope"
          ]
        }
      }
    },
    {
      "name": "document_id",
      "in": "query",
      "description": "With the code of a document of an envelope: another document of that envelope the signer is on, to open in place of the code's own; the code's e-mail or WhatsApp verification is carried there, once. A value that is no document id (a list, or empty) answers `400`. Ignored with any other code.",
      "required": false,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The document with the signer's assignment",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/SigningDocument"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "A digital-certificate signer has not yet confirmed their data or accepted the terms, the signer's step has not started yet (sequential signing), or, with the code of a document of an envelope, `document_id` is no document id.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 400
                  },
                  "message": {
                    "type": "string",
                    "example": "Signer data must be confirmed before viewing the document."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "description": "`document_id` names a document outside the code's envelope, or one the signer is not on; or the workspace deleted the document while the code is still open.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "The requested document has been deleted."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "409": {
      "description": "The document is not ready to be viewed yet.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 409
                  },
                  "message": {
                    "type": "string",
                    "example": "The document is not ready to be viewed yet."
                  },
                  "code": {
                    "type": "string",
                    "enum": [
                      "DocumentNotReady"
                    ],
                    "example": "DocumentNotReady"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/documents/{documentId}/assignments/{assignmentId}`

Sign assignment items

Sign a document with input fields (collect method): submit the signer's item values, completing their items. Signing again after the signer has signed changes nothing and returns the current assignment. For **virtual** assignments the signer must first confirm their data via `PUT /v1/documents/{documentId}/signers/confirm-data`, otherwise this returns `400` (Signer data must be confirmed before signing). Signers whose verification method is `DigitalCertificate` cannot use this endpoint — their signature must be produced through `POST /v1/signers/certificate/start` + `/complete`, and this returns `400`. The request body is a JSON array of item entries. Uses the signer access code. With the code of a document of an envelope, it acts on the signer's request on the document the route names, if that document is in the same envelope, carrying the code's e-mail or WhatsApp verification there.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    },
    {
      "name": "assignmentId",
      "in": "path",
      "description": "The assignment ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "type": "array",
          "items": {
            "required": [
              "itemId",
              "fieldId",
              "pageId",
              "value"
            ],
            "properties": {
              "itemId": {
                "description": "The assignment item ID.",
                "type": "string",
                "example": "615606efcde1a39c9d21e30e"
              },
              "fieldId": {
                "description": "Field associated with the item.",
                "type": "string",
                "example": "6152120297080d55bdd13197"
              },
              "pageId": {
                "description": "The page ID.",
                "type": "string",
                "example": "615213ed81b071f4293b2fc2"
              },
              "value": {
                "description": "String representation of the value.",
                "type": "string",
                "example": "Signed by Sonny Bayer"
              }
            },
            "type": "object"
          }
        }
      }
    }
  },
  "responses": {
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed"
    },
    "200": {
      "description": "Signing result",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "Signer data must be confirmed before signing (virtual assignments), the signer must sign with a digital certificate through the digital certificate endpoints, an entry names a page, field or item that is not one of this signer's, or the signer's step has not started yet (sequential signing). Nothing is signed when an entry is refused.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 400
                  },
                  "message": {
                    "type": "string",
                    "example": "Signer data confirmation is required."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "description": "The assignment is not the one of the signer's request: the access code's own or, with the code of a document of an envelope, the one on the document the route names; or the route names a document outside the code's envelope, or one the signer is not on.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Assignment not found."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "409": {
      "description": "The document is not ready to be signed yet.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 409
                  },
                  "message": {
                    "type": "string",
                    "example": "The document is not ready to be signed yet."
                  },
                  "code": {
                    "type": "string",
                    "enum": [
                      "DocumentNotReady"
                    ],
                    "example": "DocumentNotReady"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/documents/{documentId}/assignments/{assignmentId}/reject`

Reject (decline) assignment

The signer declines to sign the document, giving a reason. Uses the signer access code. With the code of a document of an envelope, it acts on the signer's request on the document the route names, if that document is in the same envelope, carrying the code's e-mail or WhatsApp verification there.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    },
    {
      "name": "assignmentId",
      "in": "path",
      "description": "The assignment ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "decline_reason"
          ],
          "properties": {
            "decline_reason": {
              "description": "Descriptive reason for declining. Up to 2000 characters; longer values return 400.",
              "type": "string",
              "maxLength": 2000,
              "example": "I do not agree with clause 2."
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed"
    },
    "200": {
      "description": "Assignment declined",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "description": "Empty array.",
                    "type": "array",
                    "items": []
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "`decline_reason` is missing or longer than 2000 characters, the assignment or the document is no longer pending, or the signer's step has not started yet (sequential signing).",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 400
                  },
                  "message": {
                    "type": "string",
                    "example": "The attribute decline_reason is required."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "description": "The assignment is not the one of the signer's request: the access code's own or, with the code of a document of an envelope, the one on the document the route names; or the route names a document outside the code's envelope, or one the signer is not on.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Assignment not found."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/signers/documents/sign-multiple`

Sign multiple documents

Sign several documents in one request, for a signer with multiple pending documents. Each document must be prepared for the **virtual** signature method. A listed document the signer already signed is left as it is. Uses the signer access code.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "document_ids"
          ],
          "properties": {
            "document_ids": {
              "description": "IDs of the documents to sign.",
              "type": "array",
              "items": {
                "type": "string"
              },
              "example": [
                "documentid1",
                "documentid2"
              ]
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed"
    },
    "200": {
      "description": "Signing result",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "description": "Empty array.",
                    "type": "array",
                    "items": []
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "A listed document is not a virtual document of this signer, is not pending signature, waits for an earlier signing step, or, for the code of a document of an envelope, is not part of that envelope.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 400
                  },
                  "message": {
                    "type": "string",
                    "example": "The document doc1 is not pending signature."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "description": "A listed document ID does not exist in the workspace. The message names the unknown IDs.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Documents not found: doc1, doc2."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/signers/documents/decline-multiple`

Decline multiple documents

Decline several documents in one request. Uses the signer access code.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "document_ids",
            "decline_reason"
          ],
          "properties": {
            "document_ids": {
              "description": "IDs of the documents to decline.",
              "type": "array",
              "items": {
                "type": "string"
              },
              "example": [
                "documentid1",
                "documentid2"
              ]
            },
            "decline_reason": {
              "description": "Reason for declining. Up to 2000 characters; longer values return 400.",
              "type": "string",
              "maxLength": 2000,
              "example": "Unfavorable terms."
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed"
    },
    "200": {
      "description": "Decline result",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "description": "Empty array.",
                    "type": "array",
                    "items": []
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "A listed document is not this signer's, is not pending signature, waits for an earlier signing step, or, for the code of a document of an envelope, is not part of that envelope. Nothing is declined.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 400
                  },
                  "message": {
                    "type": "string",
                    "example": "Signer is not associated to document doc1."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "description": "A listed document ID does not exist in the workspace. The message names the unknown IDs.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Documents not found: doc1, doc2."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/verify`

Verify signer code (OTP)

Submit the verification code (OTP) sent to the signer to unlock the signing flow. Uses the signer access code. 5 wrong codes within 15 minutes of the first one lock the request until those 15 minutes end: every attempt, the right code included, returns `429`. A right code clears the count. With the code of a document of an envelope, the code of the signer's envelope invitation is accepted too, and wrong codes on any of their documents of the envelope count together.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "verification-code"
          ],
          "properties": {
            "verification-code": {
              "type": "string",
              "example": "123456"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed"
    },
    "200": {
      "description": "Code verified. `data` is an empty list.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": [],
                    "example": []
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "The code is wrong, or the signer's step has not started yet (sequential signing).",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 400
                  },
                  "message": {
                    "type": "string",
                    "example": "Invalid verification code."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "429": {
      "description": "Too many wrong codes. Try again once 15 minutes have passed since the first one.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 429
                  },
                  "message": {
                    "type": "string",
                    "example": "Too many invalid verification codes. Try again later."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/documents/{documentId}/signers/confirm-data`

Confirm signer data

The signer confirms or updates their data before signing. Uses the signer access code. With the code of a document of an envelope, it acts on the signer's request on the document the route names, if that document is in the same envelope, carrying the code's e-mail or WhatsApp verification there. The code's own document, taken out of its envelope, takes the confirmation too while a document of the envelope still awaits signatures: it signs nothing (that document stays cancelled) and opens the reads a certificate or identity-check signer's method gates, so the signer reaches the documents left.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "email": {
              "description": "Required when the signer is verified or notified by e-mail. Saved when the signer has none; otherwise it must be theirs.",
              "type": "string",
              "format": "email"
            },
            "whatsapp_phone_number": {
              "description": "Required when the signer is verified or notified by WhatsApp. Saved when the signer has none; otherwise it must be theirs.",
              "type": "string",
              "example": "+5548999990000"
            },
            "has_accepted_terms": {
              "description": "`true` also records that the signer accepted the terms of use.",
              "type": "boolean"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed"
    },
    "404": {
      "description": "The route names a document the code doesn't reach: outside the code's envelope, or one the signer is not on.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Document not found."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "200": {
      "description": "Data confirmed. `data` is an empty list.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": [],
                    "example": []
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "An e-mail or WhatsApp number the signer's methods need is missing, invalid or not theirs, or the signer's step has not started yet (sequential signing).",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 400
                  },
                  "message": {
                    "type": "string",
                    "example": "The provided email does not match the signer's email."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/signers/accept-terms`

Accept terms (signer)

Record that the signer accepted the terms of use. Uses the signer access code.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "responses": {
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed"
    },
    "200": {
      "description": "Terms accepted",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "required": [
                      "full_name",
                      "email",
                      "has_accepted_terms"
                    ],
                    "properties": {
                      "full_name": {
                        "type": "string",
                        "example": "John Signer"
                      },
                      "email": {
                        "type": [
                          "string",
                          "null"
                        ],
                        "format": "email",
                        "example": "person@example.com"
                      },
                      "has_accepted_terms": {
                        "type": "boolean",
                        "example": true
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "The signer's verification is not done yet (e.g. the e-mail code), or the signer's step has not started yet (sequential signing).",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 400
                  },
                  "message": {
                    "type": "string",
                    "example": "Email verification is required."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/signature`

Upload signature image

Upload the signer's signature (or initials) image as the raw request body. Uses the signer access code.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "parameters": [
    {
      "name": "type",
      "in": "query",
      "description": "Image type, e.g. `signature` or `initial`.",
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "reuse",
      "in": "query",
      "description": "Whether the signer opted to reuse this signature in future processes. When set, updates the signer's `is_signature_reusable` flag; when omitted, the flag is left unchanged.",
      "schema": {
        "type": "boolean"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "image/png": {
        "schema": {
          "type": "string",
          "format": "binary"
        }
      }
    }
  },
  "responses": {
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed"
    },
    "200": {
      "description": "Signature stored. `data` is an empty list.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": [],
                    "example": []
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "The image is not a PNG or JPEG file, or it is blank; or the signer's step has not started yet (sequential signing).",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 400
                  },
                  "message": {
                    "type": "string",
                    "example": "The signature image must be a PNG or JPEG file."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/signature/{signatureType}`

Download signature image

Download the signer's stored signature/initials image. Uses the signer access code.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "parameters": [
    {
      "name": "signatureType",
      "in": "path",
      "description": "Image type (e.g. `signature`, `initial`).",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed"
    },
    "200": {
      "description": "The signature image",
      "content": {
        "image/*": {
          "schema": {
            "type": "string",
            "format": "binary"
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "400": {
      "$ref": "#/components/responses/SignerStepPending",
      "description": "The signer's step has not started yet (sequential signing)."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/signers/{signerId}/documents`

List signer's documents

List the documents a signer is party to. Accepts either the signer access code or the account's own credentials (API key or bearer token) — with account credentials, the signer must belong to that account's workspace. With the signer access code, `signerId` must be the code's own signer (otherwise `403`), only documents whose signing step has been reached are listed (for the code of a document of an envelope, only that envelope's documents), and each assignment leaves out `signing_urls`.

```json
{
  "security": [
    {
      "signerAccessCode": []
    },
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "name": "signerId",
      "in": "path",
      "description": "The signer ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "envelope_id",
      "in": "query",
      "description": "Only the members of this envelope. Anything but text answers `400`.",
      "schema": {
        "type": "string"
      }
    },
    {
      "$ref": "#/components/parameters/Page"
    },
    {
      "$ref": "#/components/parameters/PerPage"
    }
  ],
  "responses": {
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed"
    },
    "200": {
      "description": "The signer's documents",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/Document"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "description": "The access code belongs to another signer; or, with a user's token or key, a role, a membership or an OAuth scope refuses it.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 403
                  },
                  "message": {
                    "type": "string",
                    "example": "The access code does not belong to this signer."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "description": "The signer is not in the caller's workspace.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Signer not found"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "With a signer access code, the signer's step has not started yet (sequential signing); or `envelope_id` is not text, an entry of `errors`.",
      "content": {
        "application/json": {
          "schema": {
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              }
            ]
          },
          "example": {
            "status": 400,
            "message": "This signer is in a pending step and has not been notified yet."
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/signers/{signerId}/documents/search`

Search signer's documents

Search the documents a signer is party to (compact representation). Accepts either the signer access code or the account's own credentials (API key or bearer token) — with account credentials, the signer must belong to that account's workspace. With the signer access code, `signerId` must be the code's own signer (otherwise `403`), only documents whose signing step has been reached are listed (for the code of a document of an envelope, only that envelope's documents), and each assignment leaves out `signing_urls`.

```json
{
  "security": [
    {
      "signerAccessCode": []
    },
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "name": "signerId",
      "in": "path",
      "description": "The signer ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "envelope_id",
      "in": "query",
      "description": "Only the members of this envelope. Anything but text answers `400`.",
      "schema": {
        "type": "string"
      }
    },
    {
      "$ref": "#/components/parameters/Search"
    }
  ],
  "responses": {
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed"
    },
    "200": {
      "description": "Matching documents",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/Document"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "description": "The access code belongs to another signer; or, with a user's token or key, a role, a membership or an OAuth scope refuses it.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 403
                  },
                  "message": {
                    "type": "string",
                    "example": "The access code does not belong to this signer."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "description": "The signer is not in the caller's workspace.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Signer not found"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "With a signer access code, the signer's step has not started yet (sequential signing); or `envelope_id` is not text, an entry of `errors`.",
      "content": {
        "application/json": {
          "schema": {
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              }
            ]
          },
          "example": {
            "status": 400,
            "message": "This signer is in a pending step and has not been notified yet."
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/signers/{signerId}/documents/{documentId}/download/{artifactName}`

Download the document to sign

The original of a document the signer still has to sign, with their access code (`signer-access-code`). Only `original` is available (`403` otherwise): the certified copy is sent to the signer by e-mail or WhatsApp. A request without an access code, such as a link from an older completion e-mail, is redirected (`302`) to the expired-link page, with a `?token=` the page can ask for the certified copy with when the signer and document match a completed signature request.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "parameters": [
    {
      "name": "signerId",
      "in": "path",
      "description": "The signer ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    },
    {
      "$ref": "#/components/parameters/DocumentId"
    },
    {
      "name": "artifactName",
      "in": "path",
      "description": "Artifact type. Only `original`.",
      "required": true,
      "schema": {
        "type": "string",
        "enum": [
          "original"
        ]
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The artifact binary",
      "content": {
        "application/pdf": {
          "schema": {
            "type": "string",
            "format": "binary"
          }
        }
      }
    },
    "302": {
      "description": "No access code: redirected to the expired-link page."
    },
    "403": {
      "description": "An artifact other than `original`.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 403
                  },
                  "message": {
                    "type": "string",
                    "example": "The signed document is sent to the signer by e-mail or WhatsApp."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "description": "The signer or the document is not the code's, or the code doesn't reach the document.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Document not found."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "410": {
      "$ref": "#/components/responses/SignerAccessClosed",
      "description": "The signer finished (for a document of an envelope, every document of it they are on) more than 30 minutes ago; the code no longer opens the document."
    },
    "400": {
      "$ref": "#/components/responses/SignerStepPending",
      "description": "The signer's step has not started yet (sequential signing)."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/authentication/social-login`

Social login

Exchange a token from a social login provider (currently only `google`) for an Assinafy access token.

```json
{
  "security": [],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "provider",
            "token",
            "has_accepted_terms"
          ],
          "properties": {
            "provider": {
              "type": "string",
              "enum": [
                "google"
              ],
              "example": "google"
            },
            "token": {
              "description": "Access/ID token from the provider.",
              "type": "string",
              "example": "yOTUvImV4cCI6MTY3OTY1ODY5NS..."
            },
            "has_accepted_terms": {
              "type": "boolean",
              "example": true
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Access token, user and accounts",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/AuthSession"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/auth/link-social-login`

Link social login

Link a social-login provider account to the authenticated user.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "provider",
            "token"
          ],
          "properties": {
            "provider": {
              "type": "string",
              "enum": [
                "google"
              ],
              "example": "google"
            },
            "token": {
              "description": "Token from the provider.",
              "type": "string"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Provider linked",
      "content": {
        "application/json": {
          "schema": {
            "$ref": "#/components/schemas/Envelope"
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/stats`

Account document KPIs

Precomputed per-account document-funnel KPIs. `granularity=monthly` (default) returns the last 12 months, most recent first; `granularity=daily` with `month=YYYY-MM` returns that month's days. Series are zero-filled. Requires the `account:read` OAuth scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "granularity",
      "in": "query",
      "description": "`monthly` (default) or `daily`.",
      "schema": {
        "type": "string",
        "enum": [
          "monthly",
          "daily"
        ]
      }
    },
    {
      "name": "month",
      "in": "query",
      "description": "Target month `YYYY-MM` (required when `granularity=daily`).",
      "schema": {
        "type": "string",
        "example": "2026-06"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "KPI series",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/DocumentStatsRow"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError",
      "description": "Invalid month"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/users/self/stats`

My cross-account document KPIs

The authenticated user's document-funnel KPIs summed across all accounts they currently belong to. `granularity=monthly` (default) returns the last 12 months, most recent first; `granularity=daily` with `month=YYYY-MM` returns that month's days. Series are zero-filled. Not available to OAuth access tokens (`403`): a token is bound to one workspace, so an OAuth app reads that workspace's figures from **Account document KPIs** instead.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "name": "granularity",
      "in": "query",
      "description": "`monthly` (default) or `daily`.",
      "schema": {
        "type": "string",
        "enum": [
          "monthly",
          "daily"
        ]
      }
    },
    {
      "name": "month",
      "in": "query",
      "description": "Target month `YYYY-MM` (required when `granularity=daily`).",
      "schema": {
        "type": "string",
        "example": "2026-06"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "KPI series",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/DocumentStatsRow"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError",
      "description": "Invalid month"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/tags`

List tags

List the tags of a workspace.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/Search"
    }
  ],
  "responses": {
    "200": {
      "description": "The workspace tags",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/Tag"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/tags`

Create tag

Create a tag in the workspace. Names are unique per workspace (case-insensitive); a collision returns 409.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "name"
          ],
          "properties": {
            "name": {
              "description": "Trimmed; whitespace collapsed; max 64 chars.",
              "type": "string",
              "example": "Contracts"
            },
            "color": {
              "description": "6-char hex (with or without leading #).",
              "type": [
                "string",
                "null"
              ],
              "example": "ff8800"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The created tag",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Tag"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "409": {
      "description": "A tag with the same name already exists.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 409
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/accounts/{accountId}/tags/{tagId}`

Update tag

Update a tag's name or color.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "tagId",
      "in": "path",
      "description": "The tag ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "name": {
              "type": "string",
              "example": "Signed Contracts"
            },
            "color": {
              "type": [
                "string",
                "null"
              ],
              "example": "00aa55"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The updated tag",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Tag"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `DELETE /v1/accounts/{accountId}/tags/{tagId}`

Delete tag

Delete a tag. Pass `?force=true` to detach it from any documents/templates it is attached to.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "tagId",
      "in": "path",
      "description": "The tag ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "force",
      "in": "query",
      "description": "Detach from resources before deleting.",
      "schema": {
        "type": "boolean"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "Tag deleted",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "properties": {
                      "deleted": {
                        "type": "boolean",
                        "example": true
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/templates`

List templates

List the templates of a workspace.

The `status` field of a template is one of:

| Status | Description |
|--------|-------------|
| `uploading` | The template is being uploaded. |
| `uploaded` | The template has been uploaded. |
| `processing` | The template is being processed. |
| `ready` | The template is ready to use. |
| `failed` | The template processing has failed. |

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/Search"
    },
    {
      "$ref": "#/components/parameters/Page"
    },
    {
      "$ref": "#/components/parameters/PerPage"
    }
  ],
  "responses": {
    "200": {
      "description": "A page of templates (default_document_tags omitted in the list)",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/Template"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/templates/{templateId}/documents`

Create document from template

Generate a new document from a template. The request is checked (roles, fields, balance) before it returns, but the assignment is created in the background, so the response has `status: uploaded` and `assignment: null`. Follow the outcome on `GET /v1/documents/{documentId}` or through webhooks: when the send succeeds the status moves to `pending_signature` and `assignment_created` is sent; when it fails the status becomes `failed`, `document_processing_failed` is sent with the reason, and nothing is charged. When the template has fields, every signing role used must have at least one field; otherwise `400`. A template with placed signer fields sends a `collect` assignment, so it cannot take a `DigitalCertificate` signer (`400`); certificate signing needs a template without placed signer fields. Provide one signer entry per template role. An entry with `id` uses that existing signer; an entry with `email` reuses the account's signer with that email or creates one.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "templateId",
      "in": "path",
      "description": "The template ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "Idempotency-Key",
      "in": "header",
      "description": "Any text, case-sensitive and new for each document you mean to create (a UUID, say). Retrying with the same key after a lost response answers the document the first request created, as the first response would, and creates and charges nothing, whatever the body says. A key belongs to its template and doesn't expire, so a `failed` document keeps answering it. `404` if that document was deleted; `409` with code `IdempotencyKeyInUse` if the first request is still running: try again. A request refused before its job is queued leaves its key free.",
      "required": false,
      "schema": {
        "type": "string",
        "example": "0f8fad5b-d9cb-469f-a165-70867728950e"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "signers"
          ],
          "properties": {
            "signers": {
              "description": "One entry per template role.",
              "type": "array",
              "items": {
                "required": [
                  "role_id"
                ],
                "properties": {
                  "role_id": {
                    "description": "The template role ID associated with the signer.",
                    "type": "string",
                    "example": "fa8c14f32d732271e071998246e"
                  },
                  "id": {
                    "description": "An existing signer's ID. Send either `id` or `email`; `id` wins when both are sent, and an unknown `id` returns `400`.",
                    "type": "string",
                    "example": "fa8c140cb49b79f940aab95fddd"
                  },
                  "email": {
                    "description": "Used when `id` is not sent: the account's signer with this email is reused, or a new one is created with `name`.",
                    "type": "string",
                    "example": "signer@example.com"
                  },
                  "name": {
                    "description": "Full name for a signer created from `email`.",
                    "type": "string",
                    "example": "Maria Silva"
                  },
                  "whatsapp_phone_number": {
                    "description": "WhatsApp number for a signer created from `email`.",
                    "type": "string",
                    "example": "+5548999999999"
                  },
                  "verification_method": {
                    "description": "Verification method for this signer. If provided without notification_methods, the notification method is inferred. Defaults to Email. `DigitalCertificate` has the signer sign with their own ICP-Brasil certificate — it requires the Digital Certificate feature, the signer must have a CPF or CNPJ in `government_id` (a CPF requires that person's certificate; a CNPJ requires an e-CNPJ for that company), must be alone in its signing step, and is charged 0.5 credits per signer. It is refused with `400` when the template has placed signer fields, because certificate signers sign only virtual assignments. `IdDocumentFaceMatchBasic` verifies the signer from a photo of their government ID, a photo of them holding it and a liveness check — it requires the ID Document + Face Match feature, the signer must have a valid CPF in `government_id`, and is charged a per-signer fee. Only applies to signers (not copy receivers).",
                    "type": "string",
                    "enum": [
                      "Email",
                      "Whatsapp",
                      "DigitalCertificate",
                      "IdDocumentFaceMatchBasic"
                    ],
                    "example": "Email"
                  },
                  "notification_methods": {
                    "description": "Notification method codes for this signer. If provided without verification_method, the verification method is inferred. Defaults to Email. Only one method allowed per signer.",
                    "type": "array",
                    "items": {
                      "type": "string"
                    },
                    "example": [
                      "Email"
                    ]
                  },
                  "embedded": {
                    "description": "Mark this signer as embedded: they sign inside an iframe in your application through a short-lived embed session, and your backend gets no signing link for them (`signing_urls` returns `url: null, embedded: true`). They still receive the normal invitation with their code, and can also sign from it. Requires the Embed plan feature (`403 PlanRestricted` otherwise) and works with Email or Whatsapp verification. Defaults to `false`.",
                    "type": "boolean",
                    "example": false
                  },
                  "step": {
                    "description": "Positive integer that controls signing order. Signers sharing the same step sign in parallel; a step activates only after every signer in the previous step has signed. If supplied for any role it must be supplied for all, forming a contiguous sequence starting at 1. Copy receivers ignore this field.",
                    "type": "integer",
                    "example": 1
                  }
                },
                "type": "object"
              }
            },
            "editor_fields": {
              "description": "Values for the fields placed on the template's `Editor` role, baked into the generated document. The `Editor` role takes no `signers` entry: it is filled only through this list. Every field placed on it needs an entry with a non-empty `value` (`400` otherwise); when it has no fields, send nothing. In `signers`, every `Signer` role needs an entry (`400` otherwise), and a `CopyReceiver` role takes an entry only for someone who should receive the certified document; a copy receiver's `verification_method`, `notification_methods` and `step` are ignored.",
              "type": "array",
              "items": {
                "required": [
                  "field_id",
                  "value"
                ],
                "properties": {
                  "field_id": {
                    "description": "The field identifier, matching the field_id in the template data.",
                    "type": "string",
                    "example": "fa8c14f3af99d2846d1789de4ba"
                  },
                  "value": {
                    "description": "The value to assign to the field.",
                    "type": "string",
                    "example": "Field value"
                  }
                },
                "type": "object"
              }
            },
            "name": {
              "description": "Title for the document. Defaults to the template name.",
              "type": "string",
              "example": "sample-contract-one-page.pdf"
            },
            "message": {
              "description": "Optional message sent to signers.",
              "type": "string",
              "example": "Message to the signers"
            },
            "expires_at": {
              "description": "Assignment expiration date (ISO 8601). No expiration by default. Must be at least one hour in the future. A UTC offset in the value is honored (`2026-12-31T23:59:59-03:00` is `2027-01-01T02:59:59Z`); a value without one is read as UTC, and a date alone (`2026-12-31`) means 23:59 UTC that day.",
              "type": "string",
              "format": "date-time",
              "example": "2024-07-30T23:59:00Z"
            },
            "tags": {
              "description": "Tag names to attach to the new document. Names that don't exist are auto-created. The template's default-document-tags are always applied; values here are merged on top (duplicates removed).",
              "type": "array",
              "items": {
                "type": "string"
              }
            },
            "max_total_credits": {
              "description": "Optional ceiling on what this request may spend: refused with `409` and code `CreditCeilingExceeded`, nothing charged, when its `total_credits` is above this. Send the `total_credits` of the last estimate. A value that is not a number of credits, zero or more, answers `400`; numeric text counts, as a form-encoded body sends it. If the cost rises after the request is accepted, as when the plan's documents run out, nothing is charged, the document ends `failed` and `document_processing_failed` carries the reason in `error_message`.",
              "type": "number",
              "example": 1.5
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The created document",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/Document"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/BadRequest",
      "description": "The roles, signers, methods or editor fields sent cannot make a document, or the workspace lacks the documents or credits it needs (e.g. `Signer id or email is required.`). A field error comes with `errors`."
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "409": {
      "description": "The request costs more than `max_total_credits` (`CreditCeilingExceeded`), or another request with this `Idempotency-Key` is still running (`IdempotencyKeyInUse`).",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 409
                  },
                  "code": {
                    "type": "string",
                    "enum": [
                      "CreditCeilingExceeded",
                      "IdempotencyKeyInUse"
                    ]
                  },
                  "total_credits": {
                    "description": "With `CreditCeilingExceeded`: what the request costs now.",
                    "type": "number",
                    "example": 1.5
                  },
                  "max_total_credits": {
                    "description": "With `CreditCeilingExceeded`: the ceiling it was sent with.",
                    "type": "number",
                    "example": 1
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "description": "The workspace's plan lacks a verification or notification method sent (`PlanRestricted`), or an OAuth app's token lacks `documents:write` (`InsufficientScope`); also a deactivated membership or a role.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 403
                  },
                  "message": {
                    "type": "string",
                    "example": "WhatsApp notification is not available for your current plan."
                  },
                  "code": {
                    "type": "string",
                    "enum": [
                      "PlanRestricted",
                      "InsufficientScope"
                    ],
                    "example": "PlanRestricted"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/templates/{templateId}/documents/estimate-cost`

Estimate document-from-template cost

Estimate the cost of creating a document from a template without creating it. Contact information is not required — only the role_id and optionally a verification or notification method are needed. Each document always consumes 1 document from the plan's monthly allowance; if exhausted, the ExtraDocument cost is charged from credits (needs_extra_document = true). blocking_reason may be PendingPayment, InsufficientDocuments, or InsufficientCredits.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "templateId",
      "in": "path",
      "description": "The template ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "signers"
          ],
          "properties": {
            "signers": {
              "description": "One entry per template role (editor roles are ignored for cost calculation).",
              "type": "array",
              "items": {
                "required": [
                  "role_id"
                ],
                "properties": {
                  "role_id": {
                    "description": "The template role ID associated with the signer.",
                    "type": "string",
                    "example": "fa8c14f32d732271e071998246e"
                  },
                  "verification_method": {
                    "description": "Verification method. If provided without notification_methods, the notification method is inferred. Defaults to Email. Verification is never billed on its own — the cost comes from the notification it is paired with, so `Whatsapp` verification requires the WhatsApp notification and costs 0.45 credits per signer. `DigitalCertificate` adds the per-signer signature cost (0.5 credits) on top of the notification cost, and returns `400` when the template has placed signer fields. `IdDocumentFaceMatchBasic` verifies the signer from a photo of their government ID, a photo of them holding it and a liveness check — it requires its account feature, requires the signer to have a valid CPF in `government_id`, and is charged a per-signer fee.",
                    "type": "string",
                    "enum": [
                      "Email",
                      "Whatsapp",
                      "DigitalCertificate",
                      "IdDocumentFaceMatchBasic"
                    ],
                    "example": "Whatsapp"
                  },
                  "notification_methods": {
                    "description": "Notification method for this signer — exactly one, and it must be compatible with `verification_method`. If provided without verification_method, the verification method is inferred. Defaults to Email.",
                    "type": "array",
                    "items": {
                      "type": "string"
                    },
                    "example": [
                      "Whatsapp"
                    ]
                  }
                },
                "type": "object"
              }
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Cost estimate",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/CostEstimate"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "The roles, signers or methods sent cannot make a document (the same rules as creating it).",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 400
                  },
                  "message": {
                    "type": "string",
                    "example": "A signer using a digital certificate must be alone in its signing step."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "description": "The workspace's plan lacks a verification or notification method sent (`PlanRestricted`), or an OAuth app's token lacks `documents:write` (`InsufficientScope`); also a deactivated membership or a role.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 403
                  },
                  "message": {
                    "type": "string",
                    "example": "WhatsApp notification is not available for your current plan."
                  },
                  "code": {
                    "type": "string",
                    "enum": [
                      "PlanRestricted",
                      "InsufficientScope"
                    ],
                    "example": "PlanRestricted"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "description": "The template is not in the workspace, or the workspace does not exist.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Template not found."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/users/self`

Get the authenticated user

Returns the profile of the user owning the access token. Not available to OAuth access tokens (`403`); an OAuth app reads the user's identity from `GET /v1/oauth/userinfo`.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "responses": {
    "200": {
      "description": "The current user",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/AuthUser"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/users/api-keys`

Get API key

Retrieve a masked version of the existing API key. The full key cannot be retrieved. Returns `null` when no key has been generated yet.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "responses": {
    "200": {
      "description": "The masked API key",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/ApiKey"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/users/api-keys`

Create API key

Generate an API key for the user, used via the `X-Api-Key` header. Generating a new key deletes the previous one. Never use an API key from a front-end application.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "password"
          ],
          "properties": {
            "password": {
              "description": "The user's password.",
              "type": "string",
              "format": "password",
              "example": "password"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The generated API key (shown in full only once)",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/ApiKey"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `DELETE /v1/users/api-keys`

Delete API key

Delete the existing API key.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "responses": {
    "200": {
      "description": "API key deleted",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": [],
                    "example": []
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/webhooks/subscriptions`

Get webhook subscription

Retrieve the account's oldest webhook endpoint — which events it is subscribed to and the delivery configuration. Accounts with several endpoints should use **List webhook endpoints**. Requires the `account:read` OAuth scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "responses": {
    "200": {
      "description": "The subscription",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/WebhookSubscription"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/accounts/{accountId}/webhooks/subscriptions`

Update webhook subscription

Update the account's oldest webhook endpoint (creating it if the account has none) — which events are monitored, whether delivery is enabled, and the delivery/contact details. Accounts with several endpoints should use **Update webhook endpoint**. Requires the `webhooks:write` OAuth scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "events",
            "is_active",
            "url",
            "email"
          ],
          "properties": {
            "events": {
              "description": "Event type codes to subscribe to (see `GET /v1/webhooks/event-types`).",
              "type": "array",
              "items": {
                "type": "string"
              },
              "example": [
                "document_ready",
                "document_prepared"
              ]
            },
            "is_active": {
              "description": "Whether events should be delivered to the webhook.",
              "type": "boolean",
              "example": true
            },
            "url": {
              "description": "The URL that will receive events: `https` for a new or changed URL (a URL stored before keeps working). Its host must resolve to a public address (`400` otherwise), and redirects are not followed.",
              "type": "string",
              "format": "uri",
              "example": "https://example.com?test=1"
            },
            "email": {
              "description": "Email that receives important webhook-communication notices.",
              "type": "string",
              "format": "email",
              "example": "person@example.com"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The updated subscription",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/WebhookSubscription"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/accounts/{accountId}/webhooks/inactivate`

Inactivate webhook subscription

Deactivate the account's oldest webhook endpoint. While inactive, no events are sent to it; other endpoints are unaffected. Requires the `webhooks:write` OAuth scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "responses": {
    "200": {
      "description": "The inactivated subscription",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/WebhookSubscription"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/webhooks/event-types`

List webhook event types

List all available event types that can be subscribed to via webhooks. Requires the `documents:read` OAuth scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "responses": {
    "200": {
      "description": "Event types",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/WebhookEventType"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/webhooks`

List webhook deliveries

Retrieve the delivery history for webhooks sent to the account's endpoints — use it to monitor status, debug failures, and verify payloads. Pagination is returned in the `X-Pagination-*` response headers. Requires the `documents:read` OAuth scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "endpoint_id",
      "in": "query",
      "description": "Only deliveries to this webhook endpoint.",
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "event",
      "in": "query",
      "description": "Filter by event type (e.g. `document_ready`).",
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "delivered",
      "in": "query",
      "description": "Filter by delivery status: `true` or `false`.",
      "schema": {
        "type": "string",
        "enum": [
          "true",
          "false"
        ]
      }
    },
    {
      "name": "from",
      "in": "query",
      "description": "Unix timestamp — only entries after this time.",
      "schema": {
        "type": "integer"
      }
    },
    {
      "name": "to",
      "in": "query",
      "description": "Unix timestamp — only entries before this time.",
      "schema": {
        "type": "integer"
      }
    },
    {
      "name": "page",
      "in": "query",
      "description": "Page number.",
      "schema": {
        "type": "integer",
        "default": 1
      }
    },
    {
      "$ref": "#/components/parameters/PerPage"
    }
  ],
  "responses": {
    "200": {
      "description": "Delivery history",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/WebhookDispatch"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/webhooks/{historyId}/retry`

Retry webhook delivery

Sends the entry's event to the entry's endpoint once, now, even while the endpoint is paused, and returns the dispatch entry of that attempt; a delivery that gets through lifts the pause. Requires the `webhooks:write` OAuth scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "historyId",
      "in": "path",
      "description": "The webhook dispatch entry ID to retry.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The new dispatch entry",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/WebhookDispatch"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError",
      "description": "The webhook subscription is inactive or the event type is not subscribed; a failed `webhook_test` entry is never retried: send another test."
    },
    "404": {
      "$ref": "#/components/responses/NotFound",
      "description": "The webhook entry was not found or does not belong to this account."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/webhooks/endpoints`

List webhook endpoints

List the account's webhook endpoints, oldest first. Requires the `account:read` OAuth scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "url",
      "in": "query",
      "description": "Only the endpoint registered with exactly this URL.",
      "required": false,
      "schema": {
        "type": "string",
        "format": "uri"
      },
      "example": "https://example.com/webhooks/assinafy"
    }
  ],
  "responses": {
    "200": {
      "description": "The endpoints",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/WebhookEndpoint"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/webhooks/endpoints`

Create webhook endpoint

Register a URL to receive the account's webhook events. An account can have 1 endpoint, or up to 3 on paid plans; creating one past the limit returns `403`. Each endpoint of a workspace must have a different `url` (`400` otherwise). When `signing_enabled` is `true`, a signing secret is generated and returned once, as `secret` in this response — store it, since it is never shown in list or view responses. It can be read again later with **Get webhook endpoint signing secret**, except by OAuth applications: an OAuth application that loses the secret gets a new one with **Rotate webhook endpoint signing secret**. Requires the `webhooks:write` OAuth scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "url",
            "email",
            "events"
          ],
          "properties": {
            "url": {
              "description": "URL that receives the events: `https` for a new or changed URL (a URL stored before keeps working). Its host must resolve to a public address; private, loopback, link-local and other reserved addresses are refused with `400`. Deliveries don't follow redirects: a `3xx` answer counts as a failed delivery.",
              "type": "string",
              "format": "uri",
              "example": "https://example.com/webhooks/assinafy"
            },
            "email": {
              "description": "Contact email for delivery-failure notices.",
              "type": "string",
              "format": "email",
              "example": "ops@example.com"
            },
            "events": {
              "description": "Event types to deliver (see `GET /v1/webhooks/event-types`).",
              "type": "array",
              "items": {
                "type": "string"
              },
              "example": [
                "document_ready",
                "signer_signed_document"
              ]
            },
            "name": {
              "description": "Label to tell endpoints apart.",
              "type": "string",
              "example": "ERP"
            },
            "is_active": {
              "description": "Whether events are delivered. Defaults to `true`.",
              "type": "boolean",
              "example": true
            },
            "signing_enabled": {
              "description": "Sign deliveries with a Standard Webhooks signature. Defaults to `false`.",
              "type": "boolean",
              "example": true
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The created endpoint",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "object",
                    "allOf": [
                      {
                        "$ref": "#/components/schemas/WebhookEndpoint"
                      },
                      {
                        "properties": {
                          "secret": {
                            "description": "Standard Webhooks signing secret: `whsec_` followed by the base64-encoded key. Present only when `signing_enabled` is `true`.",
                            "type": "string",
                            "example": "whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw"
                          }
                        },
                        "type": "object"
                      }
                    ]
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "403": {
      "$ref": "#/components/responses/Forbidden",
      "description": "The account already has as many endpoints as its plan allows."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `DELETE /v1/accounts/{accountId}/webhooks/endpoints`

Delete webhook endpoint by URL

Stop delivering events to the endpoint registered with `url` and free its slot, for integrations that keep the URL they registered rather than the endpoint ID. The URL must match exactly as it was registered. Returns `404` when no endpoint of the workspace uses it. Requires the `webhooks:write` OAuth scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "url",
      "in": "query",
      "description": "The endpoint's URL.",
      "required": true,
      "schema": {
        "type": "string",
        "format": "uri"
      },
      "example": "https://example.com/webhooks/assinafy"
    }
  ],
  "responses": {
    "200": {
      "description": "Endpoint deleted",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": [],
                    "example": []
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError",
      "description": "`url` is missing."
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/webhooks/endpoints/{endpointId}`

Get webhook endpoint

Retrieve one webhook endpoint. Requires the `account:read` OAuth scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "endpointId",
      "in": "path",
      "description": "The webhook endpoint ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The endpoint",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/WebhookEndpoint"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/accounts/{accountId}/webhooks/endpoints/{endpointId}`

Update webhook endpoint

Change a webhook endpoint. Only the fields sent are updated; `url` cannot be one another endpoint of the workspace already uses, nor point to a private or reserved address (`400`). Setting `signing_enabled` to `true` generates a secret if the endpoint has none and keeps the current one otherwise; setting it to `false` discards the secret. When this request turns signing on, the new secret is returned once, as `secret` in this response, as on create; otherwise `secret` is absent. Requires the `webhooks:write` OAuth scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "endpointId",
      "in": "path",
      "description": "The webhook endpoint ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "url": {
              "type": "string",
              "format": "uri",
              "example": "https://example.com/webhooks/assinafy"
            },
            "email": {
              "type": "string",
              "format": "email",
              "example": "ops@example.com"
            },
            "events": {
              "type": "array",
              "items": {
                "type": "string"
              },
              "example": [
                "document_ready"
              ]
            },
            "name": {
              "type": "string",
              "example": "ERP"
            },
            "is_active": {
              "type": "boolean",
              "example": false
            },
            "signing_enabled": {
              "type": "boolean",
              "example": true
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The updated endpoint",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "object",
                    "allOf": [
                      {
                        "$ref": "#/components/schemas/WebhookEndpoint"
                      },
                      {
                        "properties": {
                          "secret": {
                            "description": "Standard Webhooks signing secret: `whsec_` followed by the base64-encoded key. Present only when this request turned signing on.",
                            "type": "string",
                            "example": "whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw"
                          }
                        },
                        "type": "object"
                      }
                    ]
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `DELETE /v1/accounts/{accountId}/webhooks/endpoints/{endpointId}`

Delete webhook endpoint

Stop delivering events to an endpoint and free its slot. Requires the `webhooks:write` OAuth scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "endpointId",
      "in": "path",
      "description": "The webhook endpoint ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "Endpoint deleted",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": [],
                    "example": []
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/webhooks/endpoints/{endpointId}/secret`

Get webhook endpoint signing secret

Return the secret used to sign deliveries to this endpoint (see **Webhook Payloads → Verifying signatures**). Returns `400` when signing is disabled. Not available to OAuth applications: they receive a secret only when one is generated, in the **Create webhook endpoint** or **Update webhook endpoint** response (when that request turns signing on) or from **Rotate webhook endpoint signing secret**.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "endpointId",
      "in": "path",
      "description": "The webhook endpoint ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The secret",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/WebhookEndpointSecret"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError",
      "description": "Signing is not enabled for this endpoint."
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/webhooks/endpoints/{endpointId}/secret/rotate`

Rotate webhook endpoint signing secret

Replace the endpoint's signing secret and return the new one. The old secret stops working immediately, so update your receiver right away; or send `keep_previous: true` to keep it signing deliveries beside the new one for 24 hours (`previous_secret_expires_at`): during that time `webhook-signature` carries both signatures, so your receiver can switch to the new secret without failing verification. Only the secret just replaced is kept: rotating again within those 24 hours stops the one kept before. Two rotations sent at once take effect one after the other, each answering the secret it set. Returns `400` when signing is disabled or `keep_previous` is not a boolean. Requires the `webhooks:write` OAuth scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "endpointId",
      "in": "path",
      "description": "The webhook endpoint ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "requestBody": {
    "required": false,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "keep_previous": {
              "description": "`true`: the replaced secret keeps signing for 24 hours. Default `false`: it stops at once.",
              "type": "boolean",
              "example": false
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The new secret",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "object",
                    "allOf": [
                      {
                        "$ref": "#/components/schemas/WebhookEndpointSecret"
                      },
                      {
                        "properties": {
                          "previous_secret_expires_at": {
                            "description": "Only with `keep_previous: true`: until when the secret replaced keeps signing deliveries beside this one.",
                            "type": "string",
                            "format": "date-time",
                            "example": "2026-10-30T15:00:00Z"
                          }
                        },
                        "type": "object"
                      }
                    ]
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError",
      "description": "Signing is not enabled for this endpoint, or `keep_previous` is not a boolean."
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /.well-known/oauth-protected-resource`

OAuth 2.0 protected resource metadata

RFC 9728. Identifies this API as a protected resource, the
     *         authorization server(s) that can issue tokens for it, and the scopes it
     *         accepts. `scopes_supported` deliberately excludes `offline_access` — per the
     *         MCP specification, requesting a refresh token is a client concern, not
     *         something a resource is protected by. Also referenced from the
     *         `WWW-Authenticate: Bearer resource_metadata="..."` challenge on a 401/403.
     *         Per RFC 8615, the response is the bare metadata object itself — never this
     *         API's usual `{status, data, message}` envelope. The `authorization_servers` entry names the host that owns the browser-facing flow — start every integration by fetching `{authorization_servers[0]}/.well-known/oauth-authorization-server` (RFC 8414) from there, not from this API.

```json
{
  "security": [],
  "responses": {
    "200": {
      "description": "The protected resource metadata",
      "content": {
        "application/json": {
          "schema": {
            "properties": {
              "resource": {
                "type": "string",
                "format": "uri"
              },
              "authorization_servers": {
                "type": "array",
                "items": {
                  "type": "string",
                  "format": "uri"
                }
              },
              "scopes_supported": {
                "type": "array",
                "items": {
                  "type": "string"
                }
              },
              "bearer_methods_supported": {
                "type": "array",
                "items": {
                  "type": "string",
                  "example": "header"
                }
              }
            },
            "type": "object"
          }
        }
      }
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/documents/{documentId}/assignments/{assignmentId}/whatsapp-notifications`

List WhatsApp notifications

List all WhatsApp notification messages sent for an assignment. The response includes the rendered template text split into `header`, `body` and `buttons` — exactly what the signer would see. In sandbox/stage, WhatsApp messages are simulated (no real delivery) and button URLs include access/verification codes you can use to simulate the signing flow; in production the button URLs are stripped. Requires the `documents:read` OAuth scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    },
    {
      "name": "assignmentId",
      "in": "path",
      "description": "The assignment ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "WhatsApp notifications",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/WhatsappNotification"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/usage`

Get account usage

The workspace's plan and what is left of it, for a plan and credit meter. Nothing about prices or payments: those stay in **Get current subscription**. Not available to third-party OAuth applications (`403`), whatever their scopes: this is billing data. Assinafy's own apps can read it with the `account:read` scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "responses": {
    "200": {
      "description": "The usage",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/AccountUsage"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/Forbidden"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/embed-origins`

List embed origins

List the origins allowed to embed the workspace's signing pages, oldest first. Returns `403` with code `PlanRestricted` when the workspace's plan does not include embedded signing. Not available to OAuth applications.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "responses": {
    "200": {
      "description": "The allowed origins",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/EmbedOrigin"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/Forbidden"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/embed-origins`

Add embed origin

Allow one more origin to embed the workspace's signing pages. An origin is an exact `scheme://host[:port]`: `https` (or `http` for `localhost` and `127.0.0.1` only), with no path, query, fragment, credentials or wildcard. The host is lowercased and a trailing `/` and the default port are dropped. Returns `400` when the origin is invalid, already registered, or the workspace already has 10 origins. Returns `403` with code `PlanRestricted` when the workspace's plan does not include embedded signing. Not available to OAuth applications.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "origin"
          ],
          "properties": {
            "origin": {
              "type": "string",
              "example": "https://app.example.com"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The origin added",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/EmbedOrigin"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError",
      "description": "The origin is invalid or already registered, or the workspace has 10 origins."
    },
    "403": {
      "$ref": "#/components/responses/Forbidden"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/accounts/{accountId}/embed-origins/{originId}`

Update embed origin

Replace one allowed origin with another, keeping its `id`. The same rules as **Add embed origin** apply. Returns `400` when the new origin is invalid or another entry already has it. Returns `403` with code `PlanRestricted` when the workspace's plan does not include embedded signing. Not available to OAuth applications.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "originId",
      "in": "path",
      "description": "The embed origin ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "required": [
            "origin"
          ],
          "properties": {
            "origin": {
              "type": "string",
              "example": "https://app.example.com"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The updated origin",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/EmbedOrigin"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError",
      "description": "The origin is invalid or already registered."
    },
    "403": {
      "$ref": "#/components/responses/Forbidden"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `DELETE /v1/accounts/{accountId}/embed-origins/{originId}`

Remove embed origin

Stop allowing an origin to embed the workspace's signing pages. Removing the last origin disables embedding: new sessions return `409` with code `embed_origins_missing`. Returns `403` with code `PlanRestricted` when the workspace's plan does not include embedded signing. Not available to OAuth applications.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "originId",
      "in": "path",
      "description": "The embed origin ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "Origin removed",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": [],
                    "example": []
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/Forbidden"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/documents/{documentId}/assignments/{assignmentId}/signers/{signerId}/embed-sessions`

Create an embed session

Mint a single-use embed URL for a signer created with `embedded: true`. Load the URL in an iframe on one of the workspace's embed origins within 5 minutes; opening it spends the token, so mint a fresh one each time the signer opens the page. The signer's access code is never returned. Refused with `400` (`embed_not_enabled_for_signer`) for a signer that is not embedded, `409` when the signer already finished (`already_signed`), the document is still being uploaded or processed (`document_not_ready`: retry in a moment), the document can no longer be signed or the signer's step has not started (`document_unavailable`), or the workspace has no embed origin (`embed_origins_missing`), `403` with code `PlanRestricted` when the plan does not include embedded signing, and `429` after 30 sessions for one signer in an hour.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
    },
    {
      "name": "assignmentId",
      "in": "path",
      "description": "The assignment ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    },
    {
      "name": "signerId",
      "in": "path",
      "description": "The signer ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The embed session",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/EmbedSession"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/BadRequest"
    },
    "403": {
      "$ref": "#/components/responses/Forbidden"
    },
    "404": {
      "description": "The document, assignment or signer was not found."
    },
    "409": {
      "description": "The signer can not sign right now (`already_signed`, `document_not_ready`, `document_unavailable`), or the workspace has no embed origin (`embed_origins_missing`)."
    },
    "429": {
      "description": "More than 30 sessions for this signer in the last hour."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/embed/sessions/{token}/exchange`

Exchange an embed session

Called by Assinafy's embed page, not by integrations. Public: the token is the credential. Trades a session token for the signer's access code, once; the token is spent even when the call is refused with `409`. An unknown, expired or already used token returns `410` with code `embed_session_invalid`. Returns `409` when the signer or document can no longer sign.

```json
{
  "security": [],
  "parameters": [
    {
      "name": "token",
      "in": "path",
      "description": "The 64-character session token from the embed URL.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "requestBody": {
    "required": false,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "parent_origin": {
              "description": "The origin of the page hosting the iframe, recorded for audit.",
              "type": "string",
              "example": "https://app.example.com"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The signing session",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/EmbedSessionExchange"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "409": {
      "description": "The signer or document can not sign right now (`already_signed`, `document_not_ready`, `document_unavailable`)."
    },
    "410": {
      "description": "Unknown, expired or already used token (`embed_session_invalid`)."
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/envelopes`

List envelopes

The workspace's envelopes, the most recent first: each with its documents in order, their statuses and signers, and the counts by status.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/Page"
    },
    {
      "$ref": "#/components/parameters/PerPage"
    },
    {
      "name": "search",
      "in": "query",
      "description": "Part of the envelope's name.",
      "required": false,
      "schema": {
        "type": "string",
        "example": "Contrato"
      }
    },
    {
      "name": "status",
      "in": "query",
      "description": "`open`: envelopes with a document still open; `closed`: every document signed, declined, cancelled, expired or failed.",
      "required": false,
      "schema": {
        "type": "string",
        "enum": [
          "open",
          "closed"
        ]
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The workspace's envelopes",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/DocumentEnvelope"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "A filter refused as sent, an entry of `errors` each: `search` (not text), `status` (other than `open` or `closed`).",
      "content": {
        "application/json": {
          "schema": {
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              }
            ]
          },
          "example": {
            "status": 400,
            "message": "Give status as open or closed.",
            "errors": [
              {
                "field": "status",
                "code": "Invalid",
                "message": "Give status as open or closed."
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/DocumentsReadForbidden"
    },
    "404": {
      "$ref": "#/components/responses/NotFound",
      "description": "The workspace does not exist."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/envelopes`

Create envelope

Send several documents to the same signers at once. Each document gets its own assignment and keeps its own status, certificate and downloads; one invitation goes to each signer, through the methods sent for them, for all the documents they are on, from the first of them. A send that fails is recorded on that signer's request (`notification_history`) and doesn't undo the envelope: `200` means the envelope was created, not that every invitation was delivered. The envelope is charged as one document, whatever its documents, each signer's notifications and identity check once for the whole envelope, and each digital-certificate signature on every document it is made on. Every document must be uploaded and processed (`metadata_ready`) with no assignment, and every listed signer must be on at least one document. Each document is signed on its virtual page or, with `collect`, on the fields placed on its pages, as on an assignment; a digital-certificate signer signs only virtual documents, alone on each. Needs the Envelopes feature on the workspace's plan (`403` with code `PlanRestricted` otherwise). A refused request creates and charges nothing; `errors` names every field at fault.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "Idempotency-Key",
      "in": "header",
      "description": "Any text, case-sensitive and new for each envelope you mean to create (a UUID, say). Retrying with the same key after a lost response answers the envelope the first request created, and creates and charges nothing, whatever the body says. A key belongs to its workspace and doesn't expire. `409` with code `IdempotencyKeyInUse` if the first request is still running: try again. An empty value counts as no key, as on template documents.",
      "required": false,
      "schema": {
        "type": "string",
        "example": "0f8fad5b-d9cb-469f-a165-70867728950e"
      }
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "$ref": "#/components/schemas/EnvelopeCreateRequest"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The created envelope",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/DocumentEnvelope"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "Refused as sent; nothing was created or charged. Each field at fault is an entry of `errors` (a list refused as a whole is not read item by item), an item of a list named by its index (`documents[1].signer_ids`): `name`; `message` (not text); `signers` and `documents` (missing, empty, not a list, or more than 20 signers or 20 documents; `documents` also when its documents hold more than 2,000 fields between them, a virtual document's being one per signer, or more than 100 copy receivers); `signers[i].id` (missing, listed twice, not a signer of the workspace, or on no document); `signers[i].verification_method` (not a code); `signers[i].notification_methods` (not a list of codes); `signers[i].step` (sent at all); `documents[i].id` (missing, listed twice, or a document that can't take the assignment: not found, not `metadata_ready`, or already assigned); `documents[i].method` (other than `virtual` or `collect`); `documents[i].entries` (missing for `collect`, or not a list of pages with fields naming their `signer_id`); `documents[i].expires_at` (not text, a number or null); `documents[i].signer_ids` (missing, not a list of ids, a signer not listed once in `signers`, for `collect` not exactly the signers on its fields, or a digital-certificate signer on a `collect` document or with another signer). A page or field the assignment doesn't find on a `collect` document, a method code the platform doesn't know, a verification and notification that don't pair, a signer without the e-mail or WhatsApp number their method needs, an `embedded` other than `true`, `false`, `1` or `0` (`null` reads as left out, as on an assignment) or set for a signer verified other than by Bypass, Email or WhatsApp, a balance too low for the envelope, an `expires_at` or `copy_receiver_ids` the assignment refuses, and a document another request assigned or put back into processing meanwhile answer `400` with `message` only, as on an assignment.",
      "content": {
        "application/json": {
          "schema": {
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              }
            ]
          },
          "example": {
            "status": 400,
            "message": "This signer is on no document.",
            "errors": [
              {
                "field": "signers[1].id",
                "code": "Invalid",
                "message": "This signer is on no document."
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/EnvelopesFeatureForbidden"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "409": {
      "description": "The envelope costs more than `max_total_credits` (`CreditCeilingExceeded`), or another request with this `Idempotency-Key` is still running (`IdempotencyKeyInUse`).",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 409
                  },
                  "code": {
                    "type": "string",
                    "enum": [
                      "CreditCeilingExceeded",
                      "IdempotencyKeyInUse"
                    ]
                  },
                  "total_credits": {
                    "description": "With `CreditCeilingExceeded`: what the envelope costs now.",
                    "type": "number",
                    "example": 1.5
                  },
                  "max_total_credits": {
                    "description": "With `CreditCeilingExceeded`: the ceiling it was sent with.",
                    "type": "number",
                    "example": 1
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/envelopes/estimate-cost`

Estimate envelope cost

What creating the envelope would cost, creating and charging nothing. Takes the creation body and checks it as creation does (`max_total_credits` aside); answers as an assignment's estimate: the envelope counts as one document (`documents` is 1, an extra one when the plan's allowance doesn't cover it), each signer's notifications and identity check count once for the whole envelope, and each digital-certificate signature on every document it is made on, as creation charges them.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "$ref": "#/components/schemas/EnvelopeCreateRequest"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Cost estimate and balances",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/CostEstimate"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "Refused on the same fields as creation, in `errors`, and with `message` alone for a method the platform does not know or a pairing it refuses. What creation checks while it creates each document (a signer's missing contact, a page or field not found, the balance) is not checked here: `has_sufficient_resources` says what the balance allows.",
      "content": {
        "application/json": {
          "schema": {
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              }
            ]
          },
          "example": {
            "status": 400,
            "message": "Add at least one item to documents.",
            "errors": [
              {
                "field": "documents",
                "code": "Required",
                "message": "Add at least one item to documents."
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/EnvelopesFeatureForbidden"
    },
    "404": {
      "$ref": "#/components/responses/NotFound",
      "description": "The workspace does not exist."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/envelopes/{envelopeId}`

Get envelope

One envelope of the workspace: its documents in order, each with its status and signers, and the counts by status.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/EnvelopeId"
    }
  ],
  "responses": {
    "200": {
      "description": "The envelope",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/DocumentEnvelope"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/DocumentsReadForbidden"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `DELETE /v1/accounts/{accountId}/envelopes/{envelopeId}`

Delete envelope

Delete an envelope once every document of it is closed: certificated, declined, cancelled, expired or failed. Before that it answers `409` with code `EnvelopeOpen`: cancel it first. The envelope is gone: it is no longer listed and reads `404`, and a create retried with its `Idempotency-Key` no longer answers it. Its documents stay as ordinary documents, with their certificates, downloads and webhooks; `GET /v1/documents/{documentId}?expand=envelope_id` reads null for them.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/EnvelopeId"
    }
  ],
  "responses": {
    "200": {
      "description": "Envelope deleted",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": [],
                    "example": []
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/DocumentsWriteForbidden"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "409": {
      "description": "A document of the envelope is still open (`EnvelopeOpen`).",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 409
                  },
                  "code": {
                    "type": "string",
                    "enum": [
                      "EnvelopeOpen"
                    ]
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PATCH /v1/accounts/{accountId}/envelopes/{envelopeId}`

Update envelope

Rename the envelope, change its message or put its documents in a new order; send only what changes. Nothing is sent to anyone: the reads and every notice sent from then on (a reminder, a cancel, a document added) use the new name, message and order. The message is also each document's assignment `message`. Works on a closed envelope too. A refused request changes nothing; `errors` names every field at fault.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/EnvelopeId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "name": {
              "description": "What the invitation calls what is being signed. `<` is refused (`400`).",
              "type": "string",
              "maxLength": 255,
              "minLength": 1,
              "example": "Contrato de prestação de serviços"
            },
            "message": {
              "description": "The message sent to every signer from now on; null for none.",
              "type": [
                "string",
                "null"
              ],
              "example": "Segue o contrato e seus anexos."
            },
            "document_ids": {
              "description": "Every document of the envelope, each once, in the new order; `position` follows it. An envelope listing no document takes no order.",
              "type": "array",
              "items": {
                "type": "string"
              },
              "minItems": 1,
              "example": [
                "615601fab04c0a3147bb1246",
                "615601fab04c0a3147bb1247"
              ]
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The envelope as changed",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/DocumentEnvelope"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "Refused as sent; nothing changed. Each field at fault is an entry of `errors`: `name` (empty, not text, over 255 characters, or holding `<`), `message` (not text), `document_ids` (empty, or not a list of every document of the envelope, each once).",
      "content": {
        "application/json": {
          "schema": {
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              }
            ]
          },
          "example": {
            "status": 400,
            "message": "List each of the envelope's documents once, in their new order.",
            "errors": [
              {
                "field": "document_ids",
                "code": "Invalid",
                "message": "List each of the envelope's documents once, in their new order."
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/DocumentsWriteForbidden"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/envelopes/{envelopeId}/activities`

List envelope activities

The envelope's trail: the activities of every document it has had, one taken out included (not one deleted, whose own list answers 404), newest first (`sort=id` for oldest first), each naming its document (`document_id`, `document_name`). Each entry is otherwise a document activity, as `GET /v1/documents/{documentId}/activities` answers it. Paged: 20 per page by default; the `X-Pagination-Total-Count`, `X-Pagination-Page-Count`, `X-Pagination-Current-Page` and `X-Pagination-Per-Page` headers describe the full list, and a page past the end returns an empty `data`.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/EnvelopeId"
    },
    {
      "name": "sort",
      "in": "query",
      "description": "`-id` (default, newest first) or `id`.",
      "schema": {
        "type": "string"
      }
    },
    {
      "$ref": "#/components/parameters/Page"
    },
    {
      "$ref": "#/components/parameters/PerPage"
    }
  ],
  "responses": {
    "200": {
      "description": "The envelope's activities",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "$ref": "#/components/schemas/EnvelopeActivity"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/DocumentsReadForbidden"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/envelopes/{envelopeId}/download/{artifactName}`

Download envelope artifacts

One zip, named after the envelope, of the chosen artifact of every document of it, in order. Each file is named after its document's position (`1 - contract.pdf`); `bundle` gives each document a folder of its bundle's files (`1 - contract/`). `certificated`, `certificate-page`, `pades` and `bundle` include only the documents certified so far, and `pades` only those that have one; `404` when no document has the artifact. The artifacts are those of `GET /v1/documents/{documentId}/download/{artifactName}`.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/EnvelopeId"
    },
    {
      "name": "artifactName",
      "in": "path",
      "description": "Artifact type.",
      "required": true,
      "schema": {
        "type": "string",
        "enum": [
          "original",
          "certificated",
          "certificate-page",
          "pades",
          "bundle"
        ]
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The zip",
      "content": {
        "application/zip": {
          "schema": {
            "type": "string",
            "format": "binary"
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/DocumentsReadForbidden"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/accounts/{accountId}/envelopes/{envelopeId}/cancel`

Cancel envelope

Cancels every document of the envelope that can still be cancelled, the way deleting a pending document cancels it: the owner, its signers and its copy receivers are told. A document already closed, one every signer has signed (its certification queued), or one being certificated, is left as it is. A notice that fails doesn't stop the other documents. Safe to repeat: a second call cancels nothing and answers each document's status.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/EnvelopeId"
    }
  ],
  "responses": {
    "200": {
      "description": "What happened to each document, in the envelope's order",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "type": "array",
                    "items": {
                      "required": [
                        "id",
                        "status",
                        "cancelled"
                      ],
                      "properties": {
                        "id": {
                          "type": "string",
                          "example": "615601fab04c0a3147bb1246"
                        },
                        "status": {
                          "description": "The document's status after the call.",
                          "type": "string",
                          "example": "rejected_by_user"
                        },
                        "cancelled": {
                          "description": "Whether this call cancelled it.",
                          "type": "boolean",
                          "example": true
                        }
                      },
                      "type": "object"
                    }
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/DocumentsWriteForbidden"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/accounts/{accountId}/envelopes/{envelopeId}/signers/{signerId}/resend`

Remind envelope signer

Reminds a signer of the whole envelope at once: one message, the envelope's invitation sent again from their first link, listing the documents they still have to sign and, by e-mail, those they already signed. Paid notification methods are charged once for it, for each message that goes out. Each document they still have to sign records a `signature_requested` event, as the invitation does.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/EnvelopeId"
    },
    {
      "name": "signerId",
      "in": "path",
      "description": "The signer ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "Reminder result",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "required": [
                      "is_sent",
                      "envelope_id",
                      "signer_id"
                    ],
                    "properties": {
                      "is_sent": {
                        "description": "Whether every notification method sent it, as on a document's reminder.",
                        "type": "boolean"
                      },
                      "envelope_id": {
                        "type": "string"
                      },
                      "signer_id": {
                        "type": "string"
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "The signer has nothing left to sign in the envelope: every document of theirs is signed or closed; or the account lacks credits for the reminder.",
      "content": {
        "application/json": {
          "schema": {
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              }
            ]
          },
          "example": {
            "status": 400,
            "message": "This signer has nothing left to sign in this envelope."
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/DocumentsWriteForbidden"
    },
    "404": {
      "description": "The envelope was not found, or the signer is not one of its signers.",
      "content": {
        "application/json": {
          "schema": {
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              }
            ]
          },
          "example": {
            "status": 404,
            "message": "Signer not found."
          }
        }
      }
    },
    "409": {
      "description": "The envelope is closed: every document of it is signed, declined, cancelled, expired or failed (`EnvelopeClosed`).",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 409
                  },
                  "code": {
                    "type": "string",
                    "enum": [
                      "EnvelopeClosed"
                    ]
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/envelopes/{envelopeId}/documents`

Add document to envelope

Add a document to an open envelope for some of its signers. The document is checked as on creation: a `metadata_ready` document of the workspace with no assignment, signed on its virtual page or, with `collect`, on the fields placed in `entries`, with its own `expires_at` and `copy_receiver_ids`. An envelope holds up to 20 documents. It charges only its digital-certificate signatures, the envelope having paid for its document and its signers' notifications and identity checks when it was sent, and goes last in the envelope's order; a signer whose identity check the envelope approved is verified on it too. Each chosen signer is sent the envelope's invitation again, through the methods and with the link of their first invitation, now listing the new document, even once the document that invitation was sent from is taken out; the other signers hear nothing. The signers are the envelope's own: no one new joins once it was sent. Needs the Envelopes feature on the workspace's plan, as creating does. A refused request creates and charges nothing; `errors` names every field at fault.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/EnvelopeId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "type": "object",
          "allOf": [
            {
              "$ref": "#/components/schemas/EnvelopeDocumentRequest"
            },
            {
              "properties": {
                "max_total_credits": {
                  "description": "Optional ceiling on what this request may spend, as on an assignment: refused with `409` and code `CreditCeilingExceeded`, nothing created or charged, when the document costs more on its own. A value that is not a number of credits, zero or more, answers `400`; numeric text counts, as a form-encoded body sends it.",
                  "type": "number",
                  "minimum": 0,
                  "example": 1.5
                }
              },
              "type": "object"
            }
          ]
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The envelope with the document added",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/DocumentEnvelope"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "Refused as sent; nothing was created or charged. Each field at fault is an entry of `errors`: `id` (missing, or a document that can't take the assignment: not found, not `metadata_ready`, or already assigned); `method` (other than `virtual` or `collect`); `entries` (missing for `collect`, or not a list of pages with fields naming their `signer_id`); `signer_ids` (missing, not a list of ids, listed twice, not a signer of the envelope's documents, for `collect` not exactly the signers on its fields, or a digital-certificate signer on a `collect` document or with another signer); `expires_at` (not text, a number or null); a null `field` when the envelope already has 20 documents, or would hold more than 2,000 fields (a virtual document's being one per signer) or more than 100 copy receivers with this one. A page or field the assignment doesn't find on a `collect` document, a balance too low, an `expires_at` or `copy_receiver_ids` the assignment refuses, and a document another request assigned or put back into processing meanwhile answer `400` with `message` only, as on an assignment.",
      "content": {
        "application/json": {
          "schema": {
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              }
            ]
          },
          "example": {
            "status": 400,
            "message": "Each of a document's signers must be listed once in the envelope's signers.",
            "errors": [
              {
                "field": "signer_ids",
                "code": "Invalid",
                "message": "Each of a document's signers must be listed once in the envelope's signers."
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/EnvelopesFeatureForbidden"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "409": {
      "description": "Every document of the envelope is closed (`EnvelopeClosed`), or the document costs more than `max_total_credits` on its own (`CreditCeilingExceeded`).",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 409
                  },
                  "code": {
                    "type": "string",
                    "enum": [
                      "EnvelopeClosed",
                      "CreditCeilingExceeded"
                    ]
                  },
                  "total_credits": {
                    "description": "With `CreditCeilingExceeded`: what the document costs now.",
                    "type": "number",
                    "example": 1.5
                  },
                  "max_total_credits": {
                    "description": "With `CreditCeilingExceeded`: the ceiling it was sent with.",
                    "type": "number",
                    "example": 1
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `DELETE /v1/accounts/{accountId}/envelopes/{envelopeId}/documents/{documentId}`

Take document out of envelope

Take an open document out of the envelope: it is cancelled as cancelling the envelope cancels it, its owner, signers and copy receivers told in a notice naming the envelope, and it is no longer listed; the other documents carry on. Out of the envelope, `GET /v1/documents/{documentId}?expand=envelope_id` reads null for it; a signer whose invitation link was sent from it keeps reaching the envelope's other documents with that link, so it can't be deleted on its own (`409` with code `EnvelopeOpen`) until every document of the envelope is closed. A document that is not in the envelope, or was taken out already, answers `404`.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "$ref": "#/components/parameters/EnvelopeId"
    },
    {
      "$ref": "#/components/parameters/DocumentId"
    }
  ],
  "responses": {
    "200": {
      "description": "The envelope without the document",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/DocumentEnvelope"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/DocumentsWriteForbidden"
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "409": {
      "description": "The document is closed, signed by every signer (its certification queued) or being certificated, and can no longer be cancelled (`DocumentClosed`).",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 409
                  },
                  "code": {
                    "type": "string",
                    "enum": [
                      "DocumentClosed"
                    ]
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/accounts/{accountId}/notification-policy`

Get the workspace notification policy

Which document alerts this workspace sends, set by its owners. Every member can read it; `editable` says whether the requesting user can change it. Not available to third-party OAuth applications (`403`), whatever their scopes. Assinafy's own apps can reach it.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "responses": {
    "200": {
      "description": "The policy",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/NotificationPolicy"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/accounts/{accountId}/notification-policy`

Update the workspace notification policy

Owners only (`403` for other members). Merges the supplied fields: send only `events` keys you want to change, and/or `owners_receive_all`. An event set to `false` reaches nobody in this workspace, on any channel, whatever each member's own preferences; the identity review e-mail is the exception and is always sent, because the signer cannot continue without it. An unknown field or code, a non-boolean value or an empty body is rejected with `400` and nothing is written. Returns the full policy.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "events": {
              "$ref": "#/components/schemas/NotificationPolicyEvents"
            },
            "owners_receive_all": {
              "type": "boolean",
              "example": true
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The updated policy",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/NotificationPolicy"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/users/self/push-preferences`

Get my push notification preferences

Which document alerts the authenticated user receives as push notifications on their phones, separate from the e-mail preferences. Every key is always returned; everything defaults to `true`. New keys may be added. Not available to third-party OAuth applications (`403`), whatever their scopes: these are the user's personal settings. Assinafy's own apps can reach it.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "responses": {
    "200": {
      "description": "The current preferences",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/PushPreferences"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/users/self/push-preferences`

Update my push notification preferences

Merges the supplied map into the authenticated user's push preferences. Send only the keys you want to change — omitted keys keep their current value. Setting a key to `false` stops that push for this user in every account they belong to; e-mail is not affected. Returns the full map. An unknown code, a non-boolean value, or an empty body is rejected with 400 and nothing is written. Not available to third-party OAuth applications (`403`), whatever their scopes: these are the user's personal settings. Assinafy's own apps can reach it.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "$ref": "#/components/schemas/PushPreferences"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The updated preferences",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/PushPreferences"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/ValidationError"
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/sign/theme`

Workspace brand for the signing page

The brand of the workspace that sent the document: its name, its colors (`primary_color` is the button background, `secondary_color` the button text, 6-digit hex without `#`) and the address of its logo, or `null` when it has none. The logo address carries no access code: append `signer-access-code` to it, as to every signer call. Available before verification and after the document is deleted, so every screen of the signing page can carry the brand. Uses the signer access code.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "responses": {
    "200": {
      "description": "The brand",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/AccountTheme"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/SignerStepPending",
      "description": "The signer's step has not started yet (sequential signing)."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized",
      "description": "The access code is missing or unknown."
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/sign/logo`

Workspace logo for the signing page

The logo image of the workspace that sent the document. Available before verification and after the document is deleted. Uses the signer access code.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "responses": {
    "200": {
      "description": "The logo image",
      "content": {
        "image/*": {
          "schema": {
            "type": "string",
            "format": "binary"
          }
        }
      }
    },
    "400": {
      "$ref": "#/components/responses/SignerStepPending",
      "description": "The signer's step has not started yet (sequential signing)."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized",
      "description": "The access code is missing or unknown."
    },
    "404": {
      "description": "The workspace has no logo.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Logo does not exist."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/sign/certified-copy`

Send the certified copy again

Sends the signer their certified document again, with a download link valid for 30 minutes. It goes on the channel of the signature request (e-mail when the request used e-mail, otherwise WhatsApp) and only to the e-mail or number the signing messages went to, never to a changed signer record. Works with the signer's access code after they signed, including once the code is closed and after the workspace is deleted. E-mail: once every 2 minutes and 10 times in 24 hours. WhatsApp: once per signature request; after that the signer must ask the sender. `certified_copy` on `GET /v1/sign` says whether it is available.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "requestBody": {
    "required": false,
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "document_id": {
              "description": "With the code of a document of an envelope: another document of that envelope the signer is on, whose certified copy to send, in place of the code's own. A value that is no document id (a list, or empty) answers `400`. Ignored with any other code.",
              "type": "string",
              "example": "615601fab04c0a3147bb1246"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Sent",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "properties": {
                      "available": {
                        "description": "Whether it can be asked for again.",
                        "type": "boolean"
                      },
                      "notification_method": {
                        "type": "string",
                        "enum": [
                          "Email",
                          "Whatsapp"
                        ]
                      },
                      "resends_left": {
                        "description": "WhatsApp re-sends left; null for e-mail.",
                        "type": [
                          "integer",
                          "null"
                        ]
                      },
                      "sent_to": {
                        "description": "The masked contact it went to.",
                        "type": "string",
                        "example": "j***@example.com"
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "description": "`document_id` names a document outside the code's envelope, or one the signer is not on.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Document not found."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "409": {
      "description": "The signer has not signed, the document is not certified yet, or the request has no e-mail or WhatsApp to send to (ask the sender).",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 409
                  },
                  "message": {
                    "type": "string",
                    "example": "The signed document is not ready yet."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "429": {
      "description": "E-mail: asked again within 2 minutes or more than 10 times in 24 hours. WhatsApp: already re-sent once (ask the sender).",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 429
                  },
                  "message": {
                    "type": "string",
                    "example": "The signed document was just sent. Wait a couple of minutes before asking again."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "500": {
      "description": "The message could not be sent; nothing was used up, so it can be asked again.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 500
                  },
                  "message": {
                    "type": "string",
                    "example": "The message could not be sent."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "400": {
      "description": "The signer's step has not started yet (sequential signing), or, with the code of a document of an envelope, `document_id` is no document id.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 400
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    }
  }
}
```

## `GET /v1/signers/downloads/{token}`

Download the certified copy from a sent link

The link in the completion and re-send messages. Valid 7 days in the completion message and 30 minutes when re-sent. Add `/pades` for the PAdES version on documents that have one. An expired or unknown link is redirected (`302`) to the expired-link page; for a known link the redirect carries `?token=`, with which the page can ask for a new one (`POST /v1/signers/downloads/{token}/resend`).

```json
{
  "security": [],
  "parameters": [
    {
      "name": "token",
      "in": "path",
      "description": "The link token.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The certified PDF",
      "content": {
        "application/pdf": {
          "schema": {
            "type": "string",
            "format": "binary"
          }
        }
      }
    },
    "302": {
      "description": "Expired or unknown link: redirected to the expired-link page."
    },
    "404": {
      "description": "`/pades` on a document that has no PAdES version.",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/ErrorEnvelope"
              },
              {
                "properties": {
                  "status": {
                    "type": "integer",
                    "example": 404
                  },
                  "message": {
                    "type": "string",
                    "example": "Artifact 'pades' does not exist."
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/signers/downloads/{token}/resend`

Send the certified copy again from an expired link

Used by the page an expired download link leads to. Works with the link's token even after it expired, and on the same terms as `POST /v1/sign/certified-copy`: the request's channel, only the e-mail or number of the signing, the same limits. Copy receivers' links have no signature request (`409`).

```json
{
  "security": [],
  "parameters": [
    {
      "name": "token",
      "in": "path",
      "description": "The link token.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "Sent",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "properties": {
                      "available": {
                        "type": "boolean"
                      },
                      "notification_method": {
                        "type": "string",
                        "enum": [
                          "Email",
                          "Whatsapp"
                        ]
                      },
                      "resends_left": {
                        "type": [
                          "integer",
                          "null"
                        ]
                      },
                      "sent_to": {
                        "type": "string",
                        "example": "j***@example.com"
                      }
                    },
                    "type": "object"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "description": "Unknown link."
    },
    "409": {
      "description": "Nothing can be sent from here (copy receiver, Bypass signer, no recorded contact, or the document is not certified): ask the sender."
    },
    "429": {
      "description": "E-mail: asked again within 2 minutes or more than 10 times in 24 hours. WhatsApp: already re-sent once."
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `POST /v1/accounts/{accountId}/webhooks/endpoints/{endpointId}/test`

Send a test delivery

Sends the endpoint one `webhook_test` event now, signed and sent like any delivery, and returns the entry of that attempt (`kind: manual`, also listed in the delivery history). It goes to this endpoint only, whatever events it subscribes to, and also while the endpoint is paused or inactive; it neither lifts a pause nor counts toward one. The endpoint has 10 seconds to answer. Its `payload` and `origin` are `null`; the endpoint object carries its id, name, url, events and status, never its secret. No endpoint can subscribe to `webhook_test`, and it is not listed in `GET /webhooks/event-types`. Its `payload` and `origin` are `null`; the endpoint object carries its id, name, url, events and status, never its secret. No endpoint can subscribe to `webhook_test`, and it is not listed in `GET /webhooks/event-types`. The event names the endpoint as its `subject` and `object` and carries nothing about the workspace beyond `account_id`: no billing and no members. Up to 10 tests per user every 10 minutes. Requires the `webhooks:write` OAuth scope.

```json
{
  "security": [
    {
      "bearerAuth": []
    },
    {
      "apiKeyAuth": []
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/AccountId"
    },
    {
      "name": "endpointId",
      "in": "path",
      "description": "The webhook endpoint ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The entry of the test delivery",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/WebhookDispatch"
                  }
                },
                "type": "object"
              }
            ]
          }
        }
      }
    },
    "404": {
      "$ref": "#/components/responses/NotFound"
    },
    "429": {
      "description": "Rate limit exceeded."
    },
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## Webhook payloads

```json
{
  "document_uploaded": {
    "post": {
      "summary": "document_uploaded",
      "description": "A user uploaded a document. **Subject:** User · **Object:** Document · **Payload:** none.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184400,
              "event": "document_uploaded",
              "message": null,
              "payload": null,
              "origin": {
                "ip": "203.0.113.7",
                "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
              },
              "created_at": 1790000000,
              "subject": {
                "id": "d6zqpbyog2v3xvxerwn8la94",
                "name": "Mariana Costa",
                "email": "person@example.com",
                "telephone": "+5511987654321",
                "government_id": "12345678909",
                "is_email_verified": true,
                "is_mfa_enabled": false,
                "has_accepted_terms": true,
                "is_password_set": true,
                "created_at": 1772720000,
                "to_be_deleted_at": null,
                "type": "User"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "uploaded",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original"
                },
                "is_closed": false,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000000,
                "assignment": null,
                "pages": [],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "document_metadata_ready": {
    "post": {
      "summary": "document_metadata_ready",
      "description": "The document was normalized to PDF and its pages are available, so it can be prepared. **Subject:** User · **Object:** Document · **Payload:** none.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184401,
              "event": "document_metadata_ready",
              "message": null,
              "payload": [],
              "origin": null,
              "created_at": 1790000020,
              "subject": {
                "id": "d6zqpbyog2v3xvxerwn8la94",
                "name": "Mariana Costa",
                "email": "person@example.com",
                "telephone": "+5511987654321",
                "government_id": "12345678909",
                "is_email_verified": true,
                "is_mfa_enabled": false,
                "has_accepted_terms": true,
                "is_password_set": true,
                "created_at": 1772720000,
                "to_be_deleted_at": null,
                "type": "User"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "metadata_ready",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                },
                "is_closed": false,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000020,
                "assignment": null,
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "document_prepared": {
    "post": {
      "summary": "document_prepared",
      "description": "A user prepared the document (assigned its fields to signers). **Subject:** User · **Object:** Document · **Payload:** none.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184402,
              "event": "document_prepared",
              "message": null,
              "payload": null,
              "origin": null,
              "created_at": 1790000300,
              "subject": {
                "id": "d6zqpbyog2v3xvxerwn8la94",
                "name": "Mariana Costa",
                "email": "person@example.com",
                "telephone": "+5511987654321",
                "government_id": "12345678909",
                "is_email_verified": true,
                "is_mfa_enabled": false,
                "has_accepted_terms": true,
                "is_password_set": true,
                "created_at": 1772720000,
                "to_be_deleted_at": null,
                "type": "User"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "pending_signature",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                },
                "is_closed": false,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000300,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790000300,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": false,
                      "completed": false,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": false
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 0,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "assignment_created": {
    "post": {
      "summary": "assignment_created",
      "description": "A user created an assignment (a signature request) for the document. The payload is a snapshot of the creator profile. **Subject:** User · **Object:** Document · **Payload:** `user_name`, `user_email`, `user_telephone`.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184403,
              "event": "assignment_created",
              "message": null,
              "payload": {
                "user_name": "Mariana Costa",
                "user_email": "person@example.com",
                "user_telephone": "+5511987654321"
              },
              "origin": {
                "ip": "203.0.113.7",
                "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
              },
              "created_at": 1790000300,
              "subject": {
                "id": "d6zqpbyog2v3xvxerwn8la94",
                "name": "Mariana Costa",
                "email": "person@example.com",
                "telephone": "+5511987654321",
                "government_id": "12345678909",
                "is_email_verified": true,
                "is_mfa_enabled": false,
                "has_accepted_terms": true,
                "is_password_set": true,
                "created_at": 1772720000,
                "to_be_deleted_at": null,
                "type": "User"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "pending_signature",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                },
                "is_closed": false,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000300,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790000300,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": false,
                      "completed": false,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": false
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 0,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "signature_requested": {
    "post": {
      "summary": "signature_requested",
      "description": "A signer was invited to sign the document: when the assignment is created, or for a later signing step when that step opens, and again for each reminder sent with `PUT /v1/documents/{documentId}/assignments/{assignmentId}/signers/{signerId}/resend`. One event per notification method that went out, named in `notification_method`; with `bypass` nothing is sent, the event marks the signing link as issued, and no reminder goes out by `bypass` (a signer notified only by Bypass is refused). The automatic notice before the deadline, the notice of a new deadline and a send the provider refuses don't send it; a message accepted and later bounced still did. **Subject:** User · **Object:** Document · **Payload:** `signer_full_name`, `signer_email`, `signer_whatsapp_phone_number`, `notification_method` (`email`, `whatsapp` or `bypass`).",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184404,
              "event": "signature_requested",
              "message": null,
              "payload": {
                "signer_full_name": "João da Silva",
                "signer_email": "joao.silva@example.com",
                "signer_whatsapp_phone_number": "+5548999990000",
                "notification_method": "email"
              },
              "origin": null,
              "created_at": 1790000301,
              "subject": {
                "id": "d6zqpbyog2v3xvxerwn8la94",
                "name": "Mariana Costa",
                "email": "person@example.com",
                "telephone": "+5511987654321",
                "government_id": "12345678909",
                "is_email_verified": true,
                "is_mfa_enabled": false,
                "has_accepted_terms": true,
                "is_password_set": true,
                "created_at": 1772720000,
                "to_be_deleted_at": null,
                "type": "User"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "pending_signature",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                },
                "is_closed": false,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000300,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790000301,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": false,
                      "completed": false,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": false
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 0,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "signer_created": {
    "post": {
      "summary": "signer_created",
      "description": "A user created a signer. **Subject:** User · **Object:** Signer · **Payload:** `signer_full_name`.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184405,
              "event": "signer_created",
              "message": null,
              "payload": {
                "signer_full_name": "João da Silva"
              },
              "origin": {
                "ip": "203.0.113.7",
                "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
              },
              "created_at": 1789999400,
              "subject": {
                "id": "d6zqpbyog2v3xvxerwn8la94",
                "name": "Mariana Costa",
                "email": "person@example.com",
                "telephone": "+5511987654321",
                "government_id": "12345678909",
                "is_email_verified": true,
                "is_mfa_enabled": false,
                "has_accepted_terms": true,
                "is_password_set": true,
                "created_at": 1772720000,
                "to_be_deleted_at": null,
                "type": "User"
              },
              "object": {
                "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                "full_name": "João da Silva",
                "email": "joao.silva@example.com",
                "whatsapp_phone_number": "+5548999990000",
                "government_id": null,
                "has_accepted_terms": false,
                "has_signature": false,
                "has_initial": false,
                "is_signature_reusable": false,
                "type": "Signer"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "signer_email_verified": {
    "post": {
      "summary": "signer_email_verified",
      "description": "The signer confirmed their email with the verification code sent for this document. **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`, `signer_email`.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184407,
              "event": "signer_email_verified",
              "message": null,
              "payload": {
                "signer_full_name": "João da Silva",
                "signer_email": "joao.silva@example.com"
              },
              "origin": {
                "ip": "203.0.113.7",
                "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
              },
              "created_at": 1790003700,
              "subject": {
                "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                "full_name": "João da Silva",
                "email": "joao.silva@example.com",
                "whatsapp_phone_number": "+5548999990000",
                "government_id": null,
                "has_accepted_terms": true,
                "type": "Signer"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "pending_signature",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                },
                "is_closed": false,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000300,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790003700,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": false,
                      "completed": false,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": false
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 0,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "signer_whatsapp_verified": {
    "post": {
      "summary": "signer_whatsapp_verified",
      "description": "The signer confirmed their WhatsApp number with the verification code sent for this document. **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`, `signer_whatsapp_phone_number`.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184408,
              "event": "signer_whatsapp_verified",
              "message": null,
              "payload": {
                "signer_full_name": "João da Silva",
                "signer_whatsapp_phone_number": "+5548999990000"
              },
              "origin": {
                "ip": "203.0.113.7",
                "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
              },
              "created_at": 1790003700,
              "subject": {
                "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                "full_name": "João da Silva",
                "email": "joao.silva@example.com",
                "whatsapp_phone_number": "+5548999990000",
                "government_id": null,
                "has_accepted_terms": true,
                "type": "Signer"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "pending_signature",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                },
                "is_closed": false,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000300,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790003700,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": false,
                      "completed": false,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": false
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 0,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "signer_data_confirmed": {
    "post": {
      "summary": "signer_data_confirmed",
      "description": "The signer confirmed their data before signing. **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`, `signer_email`, `signer_whatsapp_phone_number`, `verification_method`.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184409,
              "event": "signer_data_confirmed",
              "message": null,
              "payload": {
                "signer_full_name": "João da Silva",
                "signer_email": "joao.silva@example.com",
                "signer_whatsapp_phone_number": "+5548999990000",
                "verification_method": "Email"
              },
              "origin": {
                "ip": "203.0.113.7",
                "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
              },
              "created_at": 1790003800,
              "subject": {
                "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                "full_name": "João da Silva",
                "email": "joao.silva@example.com",
                "whatsapp_phone_number": "+5548999990000",
                "government_id": null,
                "has_accepted_terms": true,
                "type": "Signer"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "pending_signature",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                },
                "is_closed": false,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000300,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790003800,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": false,
                      "completed": false,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": false
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 0,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "signer_viewed_document": {
    "post": {
      "summary": "signer_viewed_document",
      "description": "The signer opened the document for the first time. **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184406,
              "event": "signer_viewed_document",
              "message": null,
              "payload": {
                "signer_full_name": "João da Silva"
              },
              "origin": {
                "ip": "203.0.113.7",
                "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
              },
              "created_at": 1790003600,
              "subject": {
                "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                "full_name": "João da Silva",
                "email": "joao.silva@example.com",
                "whatsapp_phone_number": "+5548999990000",
                "government_id": null,
                "has_accepted_terms": true,
                "type": "Signer"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "pending_signature",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                },
                "is_closed": false,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000300,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790003600,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": false,
                      "completed": false,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": false
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 0,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "signer_identity_verified": {
    "post": {
      "summary": "signer_identity_verified",
      "description": "The signer passed identity-document verification (directly, or approved by the account after review). **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`, and `evidence` when the provider keeps one: what was read off the document (`document_type`, `document_name`), `cpf_match`, `name_similarity` (0-1), for each check its `name`, `result` and `score`, and `evidence_sha256`. No images.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184406,
              "event": "signer_identity_verified",
              "message": null,
              "payload": {
                "signer_full_name": "João da Silva",
                "evidence": {
                  "document_type": "CNH",
                  "document_name": "JOAO DA SILVA",
                  "cpf_match": true,
                  "name_similarity": 1,
                  "checks": [
                    {
                      "name": "name",
                      "result": "pass",
                      "score": 1
                    },
                    {
                      "name": "face_match",
                      "result": "pass",
                      "score": 0.94
                    },
                    {
                      "name": "liveness",
                      "result": "pass",
                      "score": 0.98
                    }
                  ],
                  "evidence_sha256": "9f2c0d6e4b1a8c3f5e7d9b0a2c4e6f8a1b3d5f7091e2c4a6b8d0f2e4a6c8e0b2"
                }
              },
              "origin": null,
              "created_at": 1790003600,
              "subject": {
                "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                "full_name": "João da Silva",
                "email": "joao.silva@example.com",
                "whatsapp_phone_number": "+5548999990000",
                "government_id": null,
                "has_accepted_terms": true,
                "type": "Signer"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "pending_signature",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                },
                "is_closed": false,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000300,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790003600,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": false,
                      "completed": false,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": false
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 0,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "signer_identity_verification_failed": {
    "post": {
      "summary": "signer_identity_verification_failed",
      "description": "The signer's identity-document verification ended without approval. **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`, `reason` (`provider_declined`, `identity_binding_failed`, `workflow_capability_mismatch`, `reviewer_rejected`).",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184406,
              "event": "signer_identity_verification_failed",
              "message": null,
              "payload": {
                "signer_full_name": "João da Silva",
                "reason": "provider_declined"
              },
              "origin": null,
              "created_at": 1790003600,
              "subject": {
                "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                "full_name": "João da Silva",
                "email": "joao.silva@example.com",
                "whatsapp_phone_number": "+5548999990000",
                "government_id": null,
                "has_accepted_terms": true,
                "type": "Signer"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "pending_signature",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                },
                "is_closed": false,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000300,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790003600,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": false,
                      "completed": false,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": false
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 0,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "signer_identity_review_required": {
    "post": {
      "summary": "signer_identity_review_required",
      "description": "The provider could not decide the signer's identity verification and it awaits the account's review. The signer can still sign; the document is certificated only after the account approves (status `pending_identity_review` once every signer has signed). **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`, `reason_codes`.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184406,
              "event": "signer_identity_review_required",
              "message": null,
              "payload": {
                "signer_full_name": "João da Silva",
                "reason_codes": [
                  "NAME_MISMATCH",
                  "LIVENESS_UNCERTAIN"
                ]
              },
              "origin": null,
              "created_at": 1790003600,
              "subject": {
                "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                "full_name": "João da Silva",
                "email": "joao.silva@example.com",
                "whatsapp_phone_number": "+5548999990000",
                "government_id": null,
                "has_accepted_terms": true,
                "type": "Signer"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "pending_signature",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                },
                "is_closed": false,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000300,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790003600,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": false,
                      "completed": false,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": false
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 0,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "signer_identity_review_approved": {
    "post": {
      "summary": "signer_identity_review_approved",
      "description": "A user of the account approved the signer's identity after review. `reason_codes` are the differences the provider found and the reviewer accepted. **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`, `reviewer_id`, `reviewer_name`, `reason_codes`.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184406,
              "event": "signer_identity_review_approved",
              "message": null,
              "payload": {
                "signer_full_name": "João da Silva",
                "reviewer_id": "Kx9pQ2",
                "reviewer_name": "Mariana Costa",
                "reason_codes": [
                  "NAME_MISMATCH",
                  "LIVENESS_UNCERTAIN"
                ]
              },
              "origin": null,
              "created_at": 1790003600,
              "subject": {
                "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                "full_name": "João da Silva",
                "email": "joao.silva@example.com",
                "whatsapp_phone_number": "+5548999990000",
                "government_id": null,
                "has_accepted_terms": true,
                "type": "Signer"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "pending_signature",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                },
                "is_closed": false,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000300,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790003600,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": false,
                      "completed": false,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": false
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 0,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "signer_identity_review_rejected": {
    "post": {
      "summary": "signer_identity_review_rejected",
      "description": "A user of the account rejected the signer's identity after review. The document is cancelled (`user_rejected_document` follows, with the reviewer as the user). `note` is the reviewer's optional note, HTML-escaped (`&` arrives as `&amp;`). **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`, `reviewer_id`, `reviewer_name`, `note`.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184406,
              "event": "signer_identity_review_rejected",
              "message": null,
              "payload": {
                "signer_full_name": "João da Silva",
                "reviewer_id": "Kx9pQ2",
                "reviewer_name": "Mariana Costa",
                "note": "Pessoa diferente da foto do documento."
              },
              "origin": null,
              "created_at": 1790003600,
              "subject": {
                "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                "full_name": "João da Silva",
                "email": "joao.silva@example.com",
                "whatsapp_phone_number": "+5548999990000",
                "government_id": null,
                "has_accepted_terms": true,
                "type": "Signer"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "rejected_by_user",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                },
                "is_closed": false,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000300,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790003600,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": false,
                      "completed": false,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": false
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 0,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "signer_signed_document": {
    "post": {
      "summary": "signer_signed_document",
      "description": "The signer signed the document. **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`; a signer using a digital certificate adds `verification_method` (`DigitalCertificate`), `certificate_holder`, `certificate_type`, `cpf`, `cnpj`, `serial_number`, `issuer` and `signed_at` (Unix time).",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184410,
              "event": "signer_signed_document",
              "message": null,
              "payload": {
                "signer_full_name": "João da Silva"
              },
              "origin": {
                "ip": "203.0.113.7",
                "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
              },
              "created_at": 1790003900,
              "subject": {
                "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                "full_name": "João da Silva",
                "email": "joao.silva@example.com",
                "whatsapp_phone_number": "+5548999990000",
                "government_id": null,
                "has_accepted_terms": true,
                "type": "Signer"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "certificating",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                },
                "is_closed": false,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790003900,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790003900,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": true,
                      "completed": true,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": true
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": true
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 1,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": true,
                        "completed": true
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "signer_rejected_document": {
    "post": {
      "summary": "signer_rejected_document",
      "description": "The signer declined to sign the document. **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184411,
              "event": "signer_rejected_document",
              "message": null,
              "payload": {
                "signer_full_name": "João da Silva"
              },
              "origin": {
                "ip": "203.0.113.7",
                "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
              },
              "created_at": 1790003900,
              "subject": {
                "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                "full_name": "João da Silva",
                "email": "joao.silva@example.com",
                "whatsapp_phone_number": "+5548999990000",
                "government_id": null,
                "has_accepted_terms": true,
                "type": "Signer"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "rejected_by_signer",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                },
                "is_closed": true,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790003900,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790003900,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": false,
                      "completed": false,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": false
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 0,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "user_rejected_document": {
    "post": {
      "summary": "user_rejected_document",
      "description": "A user of the account cancelled the document. **Subject:** User · **Object:** Document · **Payload:** `user_name`.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184412,
              "event": "user_rejected_document",
              "message": null,
              "payload": {
                "user_name": "Mariana Costa"
              },
              "origin": {
                "ip": "203.0.113.7",
                "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
              },
              "created_at": 1790003900,
              "subject": {
                "id": "d6zqpbyog2v3xvxerwn8la94",
                "name": "Mariana Costa",
                "email": "person@example.com",
                "telephone": "+5511987654321",
                "government_id": "12345678909",
                "is_email_verified": true,
                "is_mfa_enabled": false,
                "has_accepted_terms": true,
                "is_password_set": true,
                "created_at": 1772720000,
                "to_be_deleted_at": null,
                "type": "User"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "rejected_by_user",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                },
                "is_closed": true,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790003900,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790003900,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": false,
                      "completed": false,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": false
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 0,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "document_ready": {
    "post": {
      "summary": "document_ready",
      "description": "The last signer signed and the signed document is available: its status is `certificated` and `artifacts` holds the signed files. **Subject:** Account · **Object:** Document · **Payload:** none.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184413,
              "event": "document_ready",
              "message": null,
              "payload": null,
              "origin": null,
              "created_at": 1790003960,
              "subject": {
                "id": "65f1c2a9b3e4d5f6",
                "name": "ACME Contabilidade",
                "primary_color": "1d4ed8",
                "secondary_color": null,
                "notification_sender_type": "User",
                "created_at": 1772720000,
                "users": [
                  {
                    "id": "d6zqpbyog2v3xvxerwn8la94",
                    "name": "Mariana Costa",
                    "email": "person@example.com",
                    "telephone": "+5511987654321",
                    "government_id": "12345678909",
                    "is_email_verified": true,
                    "has_accepted_terms": true,
                    "is_password_set": true,
                    "created_at": 1772720000,
                    "to_be_deleted_at": null
                  }
                ],
                "type": "Account"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "certificated",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail",
                  "certificated": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/certificated",
                  "certificate-page": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/certificate-page",
                  "bundle": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/bundle"
                },
                "is_closed": true,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": "a3f1c9e07b2d4e8f9a6c5b1d0e7f3a2c9b8d4e6f",
                "verification_url": "https://www.assinafy.com.br/verificador/a3f1c9e07b2d4e8f9a6c5b1d0e7f3a2c9b8d4e6f",
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790003960,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790003960,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": true,
                      "completed": true,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": true
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": true
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 1,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": true,
                        "completed": true
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "document_processing_failed": {
    "post": {
      "summary": "document_processing_failed",
      "description": "The document could not be processed: an invalid or unreadable file, or a template document whose job was refused (a missing balance, a cost above `max_total_credits`). `error_message` says why. **Subject:** Account · **Object:** Document · **Payload:** `error_message`.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184414,
              "event": "document_processing_failed",
              "message": null,
              "payload": {
                "error_message": "The file could not be converted to PDF."
              },
              "origin": null,
              "created_at": 1790000015,
              "subject": {
                "id": "65f1c2a9b3e4d5f6",
                "name": "ACME Contabilidade",
                "primary_color": "1d4ed8",
                "secondary_color": null,
                "notification_sender_type": "User",
                "created_at": 1772720000,
                "users": [
                  {
                    "id": "d6zqpbyog2v3xvxerwn8la94",
                    "name": "Mariana Costa",
                    "email": "person@example.com",
                    "telephone": "+5511987654321",
                    "government_id": "12345678909",
                    "is_email_verified": true,
                    "has_accepted_terms": true,
                    "is_password_set": true,
                    "created_at": 1772720000,
                    "to_be_deleted_at": null
                  }
                ],
                "type": "Account"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "failed",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original"
                },
                "is_closed": true,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000015,
                "assignment": null,
                "pages": [],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "template_created": {
    "post": {
      "summary": "template_created",
      "description": "A user created a template. **Subject:** User · **Object:** Template · **Payload:** none.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184415,
              "event": "template_created",
              "message": null,
              "payload": null,
              "origin": {
                "ip": "203.0.113.7",
                "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
              },
              "created_at": 1790000000,
              "subject": {
                "id": "d6zqpbyog2v3xvxerwn8la94",
                "name": "Mariana Costa",
                "email": "person@example.com",
                "telephone": "+5511987654321",
                "government_id": "12345678909",
                "is_email_verified": true,
                "is_mfa_enabled": false,
                "has_accepted_terms": true,
                "is_password_set": true,
                "created_at": 1772720000,
                "to_be_deleted_at": null,
                "type": "User"
              },
              "object": {
                "id": "66b2f1a7d8e9f0a1b2c3d4e5",
                "name": "Contrato padrão",
                "document_name": "Contrato padrão.pdf",
                "message": null,
                "status": "Uploaded",
                "pages": [],
                "roles": [],
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000000,
                "default_document_tags": [],
                "type": "Template"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "template_processed": {
    "post": {
      "summary": "template_processed",
      "description": "The template was processed and is ready to use. **Subject:** User · **Object:** Template · **Payload:** none.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184416,
              "event": "template_processed",
              "message": null,
              "payload": [],
              "origin": null,
              "created_at": 1790000060,
              "subject": {
                "id": "d6zqpbyog2v3xvxerwn8la94",
                "name": "Mariana Costa",
                "email": "person@example.com",
                "telephone": "+5511987654321",
                "government_id": "12345678909",
                "is_email_verified": true,
                "is_mfa_enabled": false,
                "has_accepted_terms": true,
                "is_password_set": true,
                "created_at": 1772720000,
                "to_be_deleted_at": null,
                "type": "User"
              },
              "object": {
                "id": "66b2f1a7d8e9f0a1b2c3d4e5",
                "name": "Contrato padrão",
                "document_name": "Contrato padrão.pdf",
                "message": null,
                "status": "Ready",
                "pages": [
                  {
                    "id": "66b2f1b8e9f0a1b2c3d4e5f6",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/templates/66b2f1a7d8e9f0a1b2c3d4e5/pages/66b2f1b8e9f0a1b2c3d4e5f6/download",
                    "fields": []
                  }
                ],
                "roles": [
                  {
                    "id": "66b2f1c9f0a1b2c3d4e5f6a7",
                    "name": "Contratante",
                    "assignment_type": "Signer",
                    "created_at": 1790000000,
                    "updated_at": 1790000000
                  }
                ],
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000060,
                "default_document_tags": [],
                "type": "Template"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "template_processing_failed": {
    "post": {
      "summary": "template_processing_failed",
      "description": "The template could not be processed. **Subject:** Account · **Object:** Template · **Payload:** `error_message`.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184417,
              "event": "template_processing_failed",
              "message": null,
              "payload": {
                "error_message": "The file could not be converted to PDF."
              },
              "origin": null,
              "created_at": 1790000060,
              "subject": {
                "id": "65f1c2a9b3e4d5f6",
                "name": "ACME Contabilidade",
                "primary_color": "1d4ed8",
                "secondary_color": null,
                "notification_sender_type": "User",
                "created_at": 1772720000,
                "users": [
                  {
                    "id": "d6zqpbyog2v3xvxerwn8la94",
                    "name": "Mariana Costa",
                    "email": "person@example.com",
                    "telephone": "+5511987654321",
                    "government_id": "12345678909",
                    "is_email_verified": true,
                    "has_accepted_terms": true,
                    "is_password_set": true,
                    "created_at": 1772720000,
                    "to_be_deleted_at": null
                  }
                ],
                "type": "Account"
              },
              "object": {
                "id": "66b2f1a7d8e9f0a1b2c3d4e5",
                "name": "Contrato padrão",
                "document_name": "Contrato padrão.pdf",
                "message": null,
                "status": "Failed",
                "pages": [],
                "roles": [],
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790000000,
                "default_document_tags": [],
                "type": "Template"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "document_contract_updated": {
    "post": {
      "summary": "document_contract_updated",
      "description": "A user created or changed the contract terms of the document. Read them with `GET /v1/accounts/{accountId}/documents/{documentId}/contract`. Also sent by the hourly run when it hands the contract of an owner who left the workspace to another owner. Also sent whenever the terms change by other means (see the `by_system` example: subject Account, no `user_name`) — right after `document_contract_amended`, `document_contract_renewed`, `document_contract_expired` or `document_contract_terminated` — with the subject of that event (Account on the hourly or queued run, without `user_name`). **Subject:** User, or Account · **Object:** Document · **Payload:** `user_name` (User subject only), `end_date` (the end date after the change; null when none), `changed` (the fields of the contract resource that changed, such as `[\"value\", \"end_date\", \"term\"]`; for terms just created, every field that differs from a contract with no terms).",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "examples": {
              "default": {
                "summary": "Default",
                "value": {
                  "id": 184590,
                  "event": "document_contract_updated",
                  "message": null,
                  "payload": {
                    "user_name": "Mariana Costa",
                    "end_date": "2027-10-31",
                    "changed": [
                      "value",
                      "end_date",
                      "term"
                    ]
                  },
                  "origin": {
                    "ip": "203.0.113.7",
                    "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
                  },
                  "created_at": 1790090000,
                  "subject": {
                    "id": "d6zqpbyog2v3xvxerwn8la94",
                    "name": "Mariana Costa",
                    "email": "person@example.com",
                    "telephone": "+5511987654321",
                    "government_id": "12345678909",
                    "is_email_verified": true,
                    "is_mfa_enabled": false,
                    "has_accepted_terms": true,
                    "is_password_set": true,
                    "created_at": 1772720000,
                    "to_be_deleted_at": null,
                    "type": "User"
                  },
                  "object": {
                    "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                    "account_id": "65f1c2a9b3e4d5f6",
                    "template_id": null,
                    "name": "Contrato de Prestação de Serviços.pdf",
                    "status": "certificated",
                    "artifacts": {
                      "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                      "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                    },
                    "is_closed": true,
                    "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                    "verification_code": null,
                    "verification_url": null,
                    "decline_reason": null,
                    "declined_by": null,
                    "tags": [],
                    "created_at": 1790000000,
                    "updated_at": 1790090000,
                    "assignment": {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                      "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                      "created_at": 1790003900,
                      "sender_email": "person@example.com",
                      "method": "virtual",
                      "expires_at": 1792592000,
                      "message": "Olá João, segue o contrato para assinatura.",
                      "signers": [
                        {
                          "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                          "full_name": "João da Silva",
                          "email": "joao.silva@example.com",
                          "whatsapp_phone_number": "+5548999990000",
                          "government_id": null,
                          "has_accepted_terms": false,
                          "completed": false,
                          "notification_history": [],
                          "verification_method": "Email",
                          "notification_methods": [
                            "Email"
                          ],
                          "step": 1,
                          "notified": true
                        }
                      ],
                      "copy_receivers": [],
                      "items": [
                        {
                          "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                          "page": null,
                          "signer": {
                            "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                            "full_name": "João da Silva",
                            "email": "joao.silva@example.com",
                            "whatsapp_phone_number": "+5548999990000",
                            "government_id": null,
                            "has_accepted_terms": false
                          },
                          "field": {
                            "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                            "name": "Virtual",
                            "type": "virtual",
                            "regex": null,
                            "is_pre_defined": false,
                            "is_active": true,
                            "is_required": false,
                            "is_standard": false,
                            "is_read_only": false,
                            "is_visible": false
                          },
                          "display_settings": [],
                          "value": null,
                          "completed": false
                        }
                      ],
                      "summary": {
                        "signer_count": 1,
                        "completed_count": 0,
                        "signers": [
                          {
                            "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                            "full_name": "João da Silva",
                            "email": "joao.silva@example.com",
                            "whatsapp_phone_number": "+5548999990000",
                            "government_id": null,
                            "has_accepted_terms": false,
                            "completed": false
                          }
                        ]
                      },
                      "signing_urls": [
                        {
                          "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                          "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                        }
                      ]
                    },
                    "pages": [
                      {
                        "id": "66b2f12ad4e5f6a7b8c9d0e1",
                        "number": 1,
                        "height": 2200,
                        "width": 1700,
                        "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                      },
                      {
                        "id": "66b2f12ad4e5f6a7b8c9d0e2",
                        "number": 2,
                        "height": 2200,
                        "width": 1700,
                        "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                      }
                    ],
                    "type": "Document"
                  },
                  "account_id": "65f1c2a9b3e4d5f6"
                }
              },
              "by_system": {
                "summary": "By system",
                "value": {
                  "id": 184590,
                  "event": "document_contract_updated",
                  "message": null,
                  "payload": {
                    "end_date": "2027-10-31",
                    "changed": [
                      "end_date",
                      "term"
                    ]
                  },
                  "origin": null,
                  "created_at": 1790090000,
                  "subject": {
                    "id": "65f1c2a9b3e4d5f6",
                    "name": "ACME Contabilidade",
                    "primary_color": "1d4ed8",
                    "secondary_color": null,
                    "notification_sender_type": "User",
                    "created_at": 1772720000,
                    "users": [
                      {
                        "id": "d6zqpbyog2v3xvxerwn8la94",
                        "name": "Mariana Costa",
                        "email": "person@example.com",
                        "telephone": "+5511987654321",
                        "government_id": "12345678909",
                        "is_email_verified": true,
                        "is_mfa_enabled": false,
                        "has_accepted_terms": true,
                        "is_password_set": true,
                        "created_at": 1772720000,
                        "to_be_deleted_at": null
                      }
                    ],
                    "type": "Account"
                  },
                  "object": {
                    "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                    "account_id": "65f1c2a9b3e4d5f6",
                    "template_id": null,
                    "name": "Contrato de Prestação de Serviços.pdf",
                    "status": "certificated",
                    "artifacts": {
                      "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                      "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                    },
                    "is_closed": true,
                    "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                    "verification_code": null,
                    "verification_url": null,
                    "decline_reason": null,
                    "declined_by": null,
                    "tags": [],
                    "created_at": 1790000000,
                    "updated_at": 1790090000,
                    "assignment": {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                      "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                      "created_at": 1790003900,
                      "sender_email": "person@example.com",
                      "method": "virtual",
                      "expires_at": 1792592000,
                      "message": "Olá João, segue o contrato para assinatura.",
                      "signers": [
                        {
                          "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                          "full_name": "João da Silva",
                          "email": "joao.silva@example.com",
                          "whatsapp_phone_number": "+5548999990000",
                          "government_id": null,
                          "has_accepted_terms": false,
                          "completed": false,
                          "notification_history": [],
                          "verification_method": "Email",
                          "notification_methods": [
                            "Email"
                          ],
                          "step": 1,
                          "notified": true
                        }
                      ],
                      "copy_receivers": [],
                      "items": [
                        {
                          "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                          "page": null,
                          "signer": {
                            "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                            "full_name": "João da Silva",
                            "email": "joao.silva@example.com",
                            "whatsapp_phone_number": "+5548999990000",
                            "government_id": null,
                            "has_accepted_terms": false
                          },
                          "field": {
                            "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                            "name": "Virtual",
                            "type": "virtual",
                            "regex": null,
                            "is_pre_defined": false,
                            "is_active": true,
                            "is_required": false,
                            "is_standard": false,
                            "is_read_only": false,
                            "is_visible": false
                          },
                          "display_settings": [],
                          "value": null,
                          "completed": false
                        }
                      ],
                      "summary": {
                        "signer_count": 1,
                        "completed_count": 0,
                        "signers": [
                          {
                            "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                            "full_name": "João da Silva",
                            "email": "joao.silva@example.com",
                            "whatsapp_phone_number": "+5548999990000",
                            "government_id": null,
                            "has_accepted_terms": false,
                            "completed": false
                          }
                        ]
                      },
                      "signing_urls": [
                        {
                          "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                          "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                        }
                      ]
                    },
                    "pages": [
                      {
                        "id": "66b2f12ad4e5f6a7b8c9d0e1",
                        "number": 1,
                        "height": 2200,
                        "width": 1700,
                        "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                      },
                      {
                        "id": "66b2f12ad4e5f6a7b8c9d0e2",
                        "number": 2,
                        "height": 2200,
                        "width": 1700,
                        "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                      }
                    ],
                    "type": "Document"
                  },
                  "account_id": "65f1c2a9b3e4d5f6"
                }
              }
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "document_contract_renewal_notice": {
    "post": {
      "summary": "document_contract_renewal_notice",
      "description": "The contract's notice period started: its end date minus its notice period is today, Brasília time. Sent once per term, on the hourly run that first finds it due, so a contract whose notice period already started gets it on the next run. **Subject:** Account · **Object:** Document · **Payload:** `end_date`.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184601,
              "event": "document_contract_renewal_notice",
              "message": null,
              "payload": {
                "end_date": "2027-10-31"
              },
              "origin": null,
              "created_at": 1790100000,
              "subject": {
                "id": "65f1c2a9b3e4d5f6",
                "name": "ACME Contabilidade",
                "primary_color": "1d4ed8",
                "secondary_color": null,
                "notification_sender_type": "User",
                "created_at": 1772720000,
                "users": [
                  {
                    "id": "d6zqpbyog2v3xvxerwn8la94",
                    "name": "Mariana Costa",
                    "email": "person@example.com",
                    "telephone": "+5511987654321",
                    "government_id": "12345678909",
                    "is_email_verified": true,
                    "is_mfa_enabled": false,
                    "has_accepted_terms": true,
                    "is_password_set": true,
                    "created_at": 1772720000,
                    "to_be_deleted_at": null
                  }
                ],
                "type": "Account"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "certificated",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail",
                  "certificated": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/certificated",
                  "certificate-page": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/certificate-page",
                  "bundle": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/bundle"
                },
                "is_closed": true,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": "a3f1c9e07b2d4e8f9a6c5b1d0e7f3a2c9b8d4e6f",
                "verification_url": "https://www.assinafy.com.br/verificador/a3f1c9e07b2d4e8f9a6c5b1d0e7f3a2c9b8d4e6f",
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790100000,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790003960,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": true,
                      "completed": true,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": true
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": true
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 1,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": true,
                        "completed": true
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "document_contract_expiring": {
    "post": {
      "summary": "document_contract_expiring",
      "description": "The contract ends within `days_before` days: 45 and 30. Each is sent once per term, on the hourly run that first finds it due; when both are already due (a contract tracked late), only the nearer one is sent. **Subject:** Account · **Object:** Document · **Payload:** `days_before`, `end_date`.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184602,
              "event": "document_contract_expiring",
              "message": null,
              "payload": {
                "days_before": 45,
                "end_date": "2027-10-31"
              },
              "origin": null,
              "created_at": 1790100000,
              "subject": {
                "id": "65f1c2a9b3e4d5f6",
                "name": "ACME Contabilidade",
                "primary_color": "1d4ed8",
                "secondary_color": null,
                "notification_sender_type": "User",
                "created_at": 1772720000,
                "users": [
                  {
                    "id": "d6zqpbyog2v3xvxerwn8la94",
                    "name": "Mariana Costa",
                    "email": "person@example.com",
                    "telephone": "+5511987654321",
                    "government_id": "12345678909",
                    "is_email_verified": true,
                    "is_mfa_enabled": false,
                    "has_accepted_terms": true,
                    "is_password_set": true,
                    "created_at": 1772720000,
                    "to_be_deleted_at": null
                  }
                ],
                "type": "Account"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "certificated",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail",
                  "certificated": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/certificated",
                  "certificate-page": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/certificate-page",
                  "bundle": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/bundle"
                },
                "is_closed": true,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": "a3f1c9e07b2d4e8f9a6c5b1d0e7f3a2c9b8d4e6f",
                "verification_url": "https://www.assinafy.com.br/verificador/a3f1c9e07b2d4e8f9a6c5b1d0e7f3a2c9b8d4e6f",
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790100000,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790003960,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": true,
                      "completed": true,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": true
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": true
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 1,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": true,
                        "completed": true
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "document_contract_expired": {
    "post": {
      "summary": "document_contract_expired",
      "description": "The contract ended without auto-renew; its status is now `expired`. Sent on the hourly run after the end date passes, Brasília time. **Subject:** Account · **Object:** Document · **Payload:** `end_date`, `changed` (the contract fields that moved: `[\"status\"]`).",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184603,
              "event": "document_contract_expired",
              "message": null,
              "payload": {
                "end_date": "2027-10-31",
                "changed": [
                  "status"
                ]
              },
              "origin": null,
              "created_at": 1790100000,
              "subject": {
                "id": "65f1c2a9b3e4d5f6",
                "name": "ACME Contabilidade",
                "primary_color": "1d4ed8",
                "secondary_color": null,
                "notification_sender_type": "User",
                "created_at": 1772720000,
                "users": [
                  {
                    "id": "d6zqpbyog2v3xvxerwn8la94",
                    "name": "Mariana Costa",
                    "email": "person@example.com",
                    "telephone": "+5511987654321",
                    "government_id": "12345678909",
                    "is_email_verified": true,
                    "is_mfa_enabled": false,
                    "has_accepted_terms": true,
                    "is_password_set": true,
                    "created_at": 1772720000,
                    "to_be_deleted_at": null
                  }
                ],
                "type": "Account"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "certificated",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail",
                  "certificated": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/certificated",
                  "certificate-page": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/certificate-page",
                  "bundle": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/bundle"
                },
                "is_closed": true,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": "a3f1c9e07b2d4e8f9a6c5b1d0e7f3a2c9b8d4e6f",
                "verification_url": "https://www.assinafy.com.br/verificador/a3f1c9e07b2d4e8f9a6c5b1d0e7f3a2c9b8d4e6f",
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790100000,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790003960,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": true,
                      "completed": true,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": true
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": true
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 1,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": true,
                        "completed": true
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "document_contract_renewed": {
    "post": {
      "summary": "document_contract_renewed",
      "description": "The contract started its next term: by auto-renew on the run after it ended (subject Account, see the `by_auto_renew` example) or by a user through the renew endpoint (subject User, `user_name` in the payload). `end_date` is the new one, and the `term` on the contract stepped. **Subject:** Account or User · **Object:** Document · **Payload:** `end_date`, `changed` (the contract fields that moved, such as `[\"end_date\", \"term\"]`); `user_name` when a user renewed.",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "examples": {
              "default": {
                "summary": "Default",
                "value": {
                  "id": 184604,
                  "event": "document_contract_renewed",
                  "message": null,
                  "payload": {
                    "user_name": "Mariana Costa",
                    "end_date": "2028-10-31",
                    "changed": [
                      "end_date",
                      "term"
                    ]
                  },
                  "origin": {
                    "ip": "203.0.113.7",
                    "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
                  },
                  "created_at": 1790100000,
                  "subject": {
                    "id": "d6zqpbyog2v3xvxerwn8la94",
                    "name": "Mariana Costa",
                    "email": "person@example.com",
                    "telephone": "+5511987654321",
                    "government_id": "12345678909",
                    "is_email_verified": true,
                    "is_mfa_enabled": false,
                    "has_accepted_terms": true,
                    "is_password_set": true,
                    "created_at": 1772720000,
                    "to_be_deleted_at": null,
                    "type": "User"
                  },
                  "object": {
                    "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                    "account_id": "65f1c2a9b3e4d5f6",
                    "template_id": null,
                    "name": "Contrato de Prestação de Serviços.pdf",
                    "status": "certificated",
                    "artifacts": {
                      "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                      "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                    },
                    "is_closed": true,
                    "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                    "verification_code": null,
                    "verification_url": null,
                    "decline_reason": null,
                    "declined_by": null,
                    "tags": [],
                    "created_at": 1790000000,
                    "updated_at": 1790100000,
                    "assignment": {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                      "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                      "created_at": 1790003900,
                      "sender_email": "person@example.com",
                      "method": "virtual",
                      "expires_at": 1792592000,
                      "message": "Olá João, segue o contrato para assinatura.",
                      "signers": [
                        {
                          "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                          "full_name": "João da Silva",
                          "email": "joao.silva@example.com",
                          "whatsapp_phone_number": "+5548999990000",
                          "government_id": null,
                          "has_accepted_terms": false,
                          "completed": false,
                          "notification_history": [],
                          "verification_method": "Email",
                          "notification_methods": [
                            "Email"
                          ],
                          "step": 1,
                          "notified": true
                        }
                      ],
                      "copy_receivers": [],
                      "items": [
                        {
                          "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                          "page": null,
                          "signer": {
                            "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                            "full_name": "João da Silva",
                            "email": "joao.silva@example.com",
                            "whatsapp_phone_number": "+5548999990000",
                            "government_id": null,
                            "has_accepted_terms": false
                          },
                          "field": {
                            "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                            "name": "Virtual",
                            "type": "virtual",
                            "regex": null,
                            "is_pre_defined": false,
                            "is_active": true,
                            "is_required": false,
                            "is_standard": false,
                            "is_read_only": false,
                            "is_visible": false
                          },
                          "display_settings": [],
                          "value": null,
                          "completed": false
                        }
                      ],
                      "summary": {
                        "signer_count": 1,
                        "completed_count": 0,
                        "signers": [
                          {
                            "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                            "full_name": "João da Silva",
                            "email": "joao.silva@example.com",
                            "whatsapp_phone_number": "+5548999990000",
                            "government_id": null,
                            "has_accepted_terms": false,
                            "completed": false
                          }
                        ]
                      },
                      "signing_urls": [
                        {
                          "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                          "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                        }
                      ]
                    },
                    "pages": [
                      {
                        "id": "66b2f12ad4e5f6a7b8c9d0e1",
                        "number": 1,
                        "height": 2200,
                        "width": 1700,
                        "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                      },
                      {
                        "id": "66b2f12ad4e5f6a7b8c9d0e2",
                        "number": 2,
                        "height": 2200,
                        "width": 1700,
                        "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                      }
                    ],
                    "type": "Document"
                  },
                  "account_id": "65f1c2a9b3e4d5f6"
                }
              },
              "by_auto_renew": {
                "summary": "By auto renew",
                "value": {
                  "id": 184604,
                  "event": "document_contract_renewed",
                  "message": null,
                  "payload": {
                    "end_date": "2028-10-31",
                    "changed": [
                      "end_date",
                      "term"
                    ]
                  },
                  "origin": null,
                  "created_at": 1790100000,
                  "subject": {
                    "id": "65f1c2a9b3e4d5f6",
                    "name": "ACME Contabilidade",
                    "primary_color": "1d4ed8",
                    "secondary_color": null,
                    "notification_sender_type": "User",
                    "created_at": 1772720000,
                    "users": [
                      {
                        "id": "d6zqpbyog2v3xvxerwn8la94",
                        "name": "Mariana Costa",
                        "email": "person@example.com",
                        "telephone": "+5511987654321",
                        "government_id": "12345678909",
                        "is_email_verified": true,
                        "is_mfa_enabled": false,
                        "has_accepted_terms": true,
                        "is_password_set": true,
                        "created_at": 1772720000,
                        "to_be_deleted_at": null
                      }
                    ],
                    "type": "Account"
                  },
                  "object": {
                    "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                    "account_id": "65f1c2a9b3e4d5f6",
                    "template_id": null,
                    "name": "Contrato de Prestação de Serviços.pdf",
                    "status": "certificated",
                    "artifacts": {
                      "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                      "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                    },
                    "is_closed": true,
                    "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                    "verification_code": null,
                    "verification_url": null,
                    "decline_reason": null,
                    "declined_by": null,
                    "tags": [],
                    "created_at": 1790000000,
                    "updated_at": 1790100000,
                    "assignment": {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                      "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                      "created_at": 1790003900,
                      "sender_email": "person@example.com",
                      "method": "virtual",
                      "expires_at": 1792592000,
                      "message": "Olá João, segue o contrato para assinatura.",
                      "signers": [
                        {
                          "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                          "full_name": "João da Silva",
                          "email": "joao.silva@example.com",
                          "whatsapp_phone_number": "+5548999990000",
                          "government_id": null,
                          "has_accepted_terms": false,
                          "completed": false,
                          "notification_history": [],
                          "verification_method": "Email",
                          "notification_methods": [
                            "Email"
                          ],
                          "step": 1,
                          "notified": true
                        }
                      ],
                      "copy_receivers": [],
                      "items": [
                        {
                          "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                          "page": null,
                          "signer": {
                            "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                            "full_name": "João da Silva",
                            "email": "joao.silva@example.com",
                            "whatsapp_phone_number": "+5548999990000",
                            "government_id": null,
                            "has_accepted_terms": false
                          },
                          "field": {
                            "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                            "name": "Virtual",
                            "type": "virtual",
                            "regex": null,
                            "is_pre_defined": false,
                            "is_active": true,
                            "is_required": false,
                            "is_standard": false,
                            "is_read_only": false,
                            "is_visible": false
                          },
                          "display_settings": [],
                          "value": null,
                          "completed": false
                        }
                      ],
                      "summary": {
                        "signer_count": 1,
                        "completed_count": 0,
                        "signers": [
                          {
                            "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                            "full_name": "João da Silva",
                            "email": "joao.silva@example.com",
                            "whatsapp_phone_number": "+5548999990000",
                            "government_id": null,
                            "has_accepted_terms": false,
                            "completed": false
                          }
                        ]
                      },
                      "signing_urls": [
                        {
                          "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                          "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                        }
                      ]
                    },
                    "pages": [
                      {
                        "id": "66b2f12ad4e5f6a7b8c9d0e1",
                        "number": 1,
                        "height": 2200,
                        "width": 1700,
                        "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                      },
                      {
                        "id": "66b2f12ad4e5f6a7b8c9d0e2",
                        "number": 2,
                        "height": 2200,
                        "width": 1700,
                        "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                      }
                    ],
                    "type": "Document"
                  },
                  "account_id": "65f1c2a9b3e4d5f6"
                }
              }
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "document_contract_terminated": {
    "post": {
      "summary": "document_contract_terminated",
      "description": "A user terminated the contract; its status is now `terminated`. **Subject:** User · **Object:** Document · **Payload:** `user_name`, `reason` (null when none was given), `end_date`, `changed` (the contract fields that moved: `[\"status\"]`).",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "example": {
              "id": 184605,
              "event": "document_contract_terminated",
              "message": null,
              "payload": {
                "user_name": "Mariana Costa",
                "reason": "Fornecedor substituído",
                "end_date": "2027-10-31",
                "changed": [
                  "status"
                ]
              },
              "origin": {
                "ip": "203.0.113.7",
                "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
              },
              "created_at": 1790100000,
              "subject": {
                "id": "d6zqpbyog2v3xvxerwn8la94",
                "name": "Mariana Costa",
                "email": "person@example.com",
                "telephone": "+5511987654321",
                "government_id": "12345678909",
                "is_email_verified": true,
                "is_mfa_enabled": false,
                "has_accepted_terms": true,
                "is_password_set": true,
                "created_at": 1772720000,
                "to_be_deleted_at": null,
                "type": "User"
              },
              "object": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "certificated",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail"
                },
                "is_closed": true,
                "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790100000,
                "assignment": {
                  "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                  "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                  "created_at": 1790003900,
                  "sender_email": "person@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+5548999990000",
                      "government_id": null,
                      "has_accepted_terms": false,
                      "completed": false,
                      "notification_history": [],
                      "verification_method": "Email",
                      "notification_methods": [
                        "Email"
                      ],
                      "step": 1,
                      "notified": true
                    }
                  ],
                  "copy_receivers": [],
                  "items": [
                    {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                      "page": null,
                      "signer": {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                        "name": "Virtual",
                        "type": "virtual",
                        "regex": null,
                        "is_pre_defined": false,
                        "is_active": true,
                        "is_required": false,
                        "is_standard": false,
                        "is_read_only": false,
                        "is_visible": false
                      },
                      "display_settings": [],
                      "value": null,
                      "completed": false
                    }
                  ],
                  "summary": {
                    "signer_count": 1,
                    "completed_count": 0,
                    "signers": [
                      {
                        "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+5548999990000",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                      "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e1",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                  },
                  {
                    "id": "66b2f12ad4e5f6a7b8c9d0e2",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                  }
                ],
                "type": "Document"
              },
              "account_id": "65f1c2a9b3e4d5f6"
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "document_contract_amended": {
    "post": {
      "summary": "document_contract_amended",
      "description": "A signed amendment, renewal or termination document updated the contract terms of the parent document; `changes` lists every field that moved as `{from, to}`, `null` when the instrument moves none (the parent is then left as it was and no `document_contract_updated` follows). Sent seconds after the amendment document is certificated (a job on its `document_ready` event) or by the hourly contract run that catches up, both with subject Account; or, for a document already signed when linked, by the user who linked it (subject User, `user_name` in the payload; see the `by_user` example). **Subject:** Account or User · **Object:** Document (the parent) · **Payload:** `document_id` (the amendment document), `relation_type` (`amendment`, `renewal` or `termination`), `number`, `changes`, `end_date` (the parent's end date after the change), `changed` (the names of the parent's fields that moved, as `document_contract_updated` lists them; `[]` when none did).",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "examples": {
              "default": {
                "summary": "Default",
                "value": {
                  "id": 184613,
                  "event": "document_contract_amended",
                  "message": null,
                  "payload": {
                    "document_id": "7c3d1e2f4a5b6c7d8e9f0a1b",
                    "relation_type": "amendment",
                    "number": 1,
                    "changes": {
                      "value": {
                        "from": 120000.5,
                        "to": 130000
                      },
                      "end_date": {
                        "from": "2027-10-31",
                        "to": "2028-06-30"
                      },
                      "term": {
                        "from": 1,
                        "to": 2
                      }
                    },
                    "end_date": "2028-06-30",
                    "changed": [
                      "value",
                      "end_date",
                      "term"
                    ]
                  },
                  "origin": null,
                  "created_at": 1790100000,
                  "subject": {
                    "id": "65f1c2a9b3e4d5f6",
                    "name": "ACME Contabilidade",
                    "primary_color": "1d4ed8",
                    "secondary_color": null,
                    "notification_sender_type": "User",
                    "created_at": 1772720000,
                    "users": [
                      {
                        "id": "d6zqpbyog2v3xvxerwn8la94",
                        "name": "Mariana Costa",
                        "email": "person@example.com",
                        "telephone": "+5511987654321",
                        "government_id": "12345678909",
                        "is_email_verified": true,
                        "is_mfa_enabled": false,
                        "has_accepted_terms": true,
                        "is_password_set": true,
                        "created_at": 1772720000,
                        "to_be_deleted_at": null
                      }
                    ],
                    "type": "Account"
                  },
                  "object": {
                    "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                    "account_id": "65f1c2a9b3e4d5f6",
                    "template_id": null,
                    "name": "Contrato de Prestação de Serviços.pdf",
                    "status": "certificated",
                    "artifacts": {
                      "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                      "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail",
                      "certificated": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/certificated",
                      "certificate-page": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/certificate-page",
                      "bundle": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/bundle"
                    },
                    "is_closed": true,
                    "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                    "verification_code": "a3f1c9e07b2d4e8f9a6c5b1d0e7f3a2c9b8d4e6f",
                    "verification_url": "https://www.assinafy.com.br/verificador/a3f1c9e07b2d4e8f9a6c5b1d0e7f3a2c9b8d4e6f",
                    "decline_reason": null,
                    "declined_by": null,
                    "tags": [],
                    "created_at": 1790000000,
                    "updated_at": 1790100000,
                    "assignment": {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                      "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                      "created_at": 1790003960,
                      "sender_email": "person@example.com",
                      "method": "virtual",
                      "expires_at": 1792592000,
                      "message": "Olá João, segue o contrato para assinatura.",
                      "signers": [
                        {
                          "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                          "full_name": "João da Silva",
                          "email": "joao.silva@example.com",
                          "whatsapp_phone_number": "+5548999990000",
                          "government_id": null,
                          "has_accepted_terms": true,
                          "completed": true,
                          "notification_history": [],
                          "verification_method": "Email",
                          "notification_methods": [
                            "Email"
                          ],
                          "step": 1,
                          "notified": true
                        }
                      ],
                      "copy_receivers": [],
                      "items": [
                        {
                          "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                          "page": null,
                          "signer": {
                            "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                            "full_name": "João da Silva",
                            "email": "joao.silva@example.com",
                            "whatsapp_phone_number": "+5548999990000",
                            "government_id": null,
                            "has_accepted_terms": true
                          },
                          "field": {
                            "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                            "name": "Virtual",
                            "type": "virtual",
                            "regex": null,
                            "is_pre_defined": false,
                            "is_active": true,
                            "is_required": false,
                            "is_standard": false,
                            "is_read_only": false,
                            "is_visible": false
                          },
                          "display_settings": [],
                          "value": null,
                          "completed": true
                        }
                      ],
                      "summary": {
                        "signer_count": 1,
                        "completed_count": 1,
                        "signers": [
                          {
                            "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                            "full_name": "João da Silva",
                            "email": "joao.silva@example.com",
                            "whatsapp_phone_number": "+5548999990000",
                            "government_id": null,
                            "has_accepted_terms": true,
                            "completed": true
                          }
                        ]
                      },
                      "signing_urls": [
                        {
                          "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                          "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                        }
                      ]
                    },
                    "pages": [
                      {
                        "id": "66b2f12ad4e5f6a7b8c9d0e1",
                        "number": 1,
                        "height": 2200,
                        "width": 1700,
                        "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                      },
                      {
                        "id": "66b2f12ad4e5f6a7b8c9d0e2",
                        "number": 2,
                        "height": 2200,
                        "width": 1700,
                        "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                      }
                    ],
                    "type": "Document"
                  },
                  "account_id": "65f1c2a9b3e4d5f6"
                }
              },
              "by_user": {
                "summary": "By user",
                "value": {
                  "id": 184614,
                  "event": "document_contract_amended",
                  "message": null,
                  "payload": {
                    "document_id": "7c3d1e2f4a5b6c7d8e9f0a1b",
                    "relation_type": "amendment",
                    "number": 1,
                    "changes": {
                      "value": {
                        "from": 120000.5,
                        "to": 130000
                      },
                      "end_date": {
                        "from": "2027-10-31",
                        "to": "2028-06-30"
                      },
                      "term": {
                        "from": 1,
                        "to": 2
                      }
                    },
                    "end_date": "2028-06-30",
                    "user_name": "Mariana Costa",
                    "changed": [
                      "value",
                      "end_date",
                      "term"
                    ]
                  },
                  "origin": {
                    "ip": "203.0.113.7",
                    "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
                  },
                  "created_at": 1790100000,
                  "subject": {
                    "id": "d6zqpbyog2v3xvxerwn8la94",
                    "name": "Mariana Costa",
                    "email": "person@example.com",
                    "telephone": "+5511987654321",
                    "government_id": "12345678909",
                    "is_email_verified": true,
                    "is_mfa_enabled": false,
                    "has_accepted_terms": true,
                    "is_password_set": true,
                    "created_at": 1772720000,
                    "to_be_deleted_at": null,
                    "type": "User"
                  },
                  "object": {
                    "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                    "account_id": "65f1c2a9b3e4d5f6",
                    "template_id": null,
                    "name": "Contrato de Prestação de Serviços.pdf",
                    "status": "certificated",
                    "artifacts": {
                      "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original",
                      "thumbnail": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/thumbnail",
                      "certificated": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/certificated",
                      "certificate-page": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/certificate-page",
                      "bundle": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/bundle"
                    },
                    "is_closed": true,
                    "signing_url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                    "verification_code": "a3f1c9e07b2d4e8f9a6c5b1d0e7f3a2c9b8d4e6f",
                    "verification_url": "https://www.assinafy.com.br/verificador/a3f1c9e07b2d4e8f9a6c5b1d0e7f3a2c9b8d4e6f",
                    "decline_reason": null,
                    "declined_by": null,
                    "tags": [],
                    "created_at": 1790000000,
                    "updated_at": 1790100000,
                    "assignment": {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                      "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                      "created_at": 1790003960,
                      "sender_email": "person@example.com",
                      "method": "virtual",
                      "expires_at": 1792592000,
                      "message": "Olá João, segue o contrato para assinatura.",
                      "signers": [
                        {
                          "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                          "full_name": "João da Silva",
                          "email": "joao.silva@example.com",
                          "whatsapp_phone_number": "+5548999990000",
                          "government_id": null,
                          "has_accepted_terms": true,
                          "completed": true,
                          "notification_history": [],
                          "verification_method": "Email",
                          "notification_methods": [
                            "Email"
                          ],
                          "step": 1,
                          "notified": true
                        }
                      ],
                      "copy_receivers": [],
                      "items": [
                        {
                          "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                          "page": null,
                          "signer": {
                            "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                            "full_name": "João da Silva",
                            "email": "joao.silva@example.com",
                            "whatsapp_phone_number": "+5548999990000",
                            "government_id": null,
                            "has_accepted_terms": true
                          },
                          "field": {
                            "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                            "name": "Virtual",
                            "type": "virtual",
                            "regex": null,
                            "is_pre_defined": false,
                            "is_active": true,
                            "is_required": false,
                            "is_standard": false,
                            "is_read_only": false,
                            "is_visible": false
                          },
                          "display_settings": [],
                          "value": null,
                          "completed": true
                        }
                      ],
                      "summary": {
                        "signer_count": 1,
                        "completed_count": 1,
                        "signers": [
                          {
                            "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                            "full_name": "João da Silva",
                            "email": "joao.silva@example.com",
                            "whatsapp_phone_number": "+5548999990000",
                            "government_id": null,
                            "has_accepted_terms": true,
                            "completed": true
                          }
                        ]
                      },
                      "signing_urls": [
                        {
                          "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                          "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                        }
                      ]
                    },
                    "pages": [
                      {
                        "id": "66b2f12ad4e5f6a7b8c9d0e1",
                        "number": 1,
                        "height": 2200,
                        "width": 1700,
                        "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                      },
                      {
                        "id": "66b2f12ad4e5f6a7b8c9d0e2",
                        "number": 2,
                        "height": 2200,
                        "width": 1700,
                        "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                      }
                    ],
                    "type": "Document"
                  },
                  "account_id": "65f1c2a9b3e4d5f6"
                }
              }
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  },
  "document_contract_amendment_updated": {
    "post": {
      "summary": "document_contract_amendment_updated",
      "description": "A user linked the document to a contract as its amendment, renewal or termination, changed the terms the link carries, or unlinked it (`parent_document_id` null, see the `unlinked` example). Sent for every change to the link (a retry that changes nothing is not sent), a signed instrument included: a signed instrument blocked from applying can still have its recorded delta corrected, and this is the record of who did. **Subject:** User · **Object:** Document (the instrument) · **Payload:** `user_name`, `parent_document_id`, `relation_type`, `terms` (the delta as it now stands; `null` after an unlink), `changed` (which of `parent_document_id`, `relation_type` and `terms` moved: all three on a first link and on an unlink, `[\"terms\"]` when only the delta changed).",
      "tags": [
        "Webhook Payloads"
      ],
      "parameters": [
        {
          "name": "User-Agent",
          "in": "header",
          "required": true,
          "description": "Always `Assinafy-Webhooks/1.0`.",
          "schema": {
            "type": "string"
          },
          "example": "Assinafy-Webhooks/1.0"
        },
        {
          "name": "webhook-id",
          "in": "header",
          "required": true,
          "description": "Message ID: the same on every attempt of this event to this endpoint. Use it to deduplicate retries.",
          "schema": {
            "type": "string"
          },
          "example": "9f86d081884c7d659a2feaa0c55ad015"
        },
        {
          "name": "webhook-timestamp",
          "in": "header",
          "required": true,
          "description": "Unix timestamp (seconds) of this attempt.",
          "schema": {
            "type": "string"
          },
          "example": "1790000000"
        },
        {
          "name": "webhook-signature",
          "in": "header",
          "required": false,
          "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`; for 24 hours after a rotation with `keep_previous`, one such entry per secret, space-separated. See **Verifying signatures**.",
          "schema": {
            "type": "string"
          },
          "example": "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE="
        }
      ],
      "requestBody": {
        "required": true,
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/WebhookEvent"
            },
            "examples": {
              "default": {
                "summary": "Default",
                "value": {
                  "id": 184600,
                  "event": "document_contract_amendment_updated",
                  "message": null,
                  "payload": {
                    "user_name": "Mariana Costa",
                    "parent_document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                    "relation_type": "amendment",
                    "terms": {
                      "value": 130000,
                      "end_date": "2028-06-30"
                    },
                    "changed": [
                      "parent_document_id",
                      "relation_type",
                      "terms"
                    ]
                  },
                  "origin": {
                    "ip": "203.0.113.7",
                    "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
                  },
                  "created_at": 1790095000,
                  "subject": {
                    "id": "d6zqpbyog2v3xvxerwn8la94",
                    "name": "Mariana Costa",
                    "email": "person@example.com",
                    "telephone": "+5511987654321",
                    "government_id": "12345678909",
                    "is_email_verified": true,
                    "is_mfa_enabled": false,
                    "has_accepted_terms": true,
                    "is_password_set": true,
                    "created_at": 1772720000,
                    "to_be_deleted_at": null,
                    "type": "User"
                  },
                  "object": {
                    "id": "7c3d1e2f4a5b6c7d8e9f0a1b",
                    "account_id": "65f1c2a9b3e4d5f6",
                    "template_id": null,
                    "name": "Aditivo ao Contrato de Prestação de Serviços.pdf",
                    "status": "pending_signature",
                    "artifacts": {
                      "original": "https://api.assinafy.com.br/v1/documents/7c3d1e2f4a5b6c7d8e9f0a1b/download/original",
                      "thumbnail": "https://api.assinafy.com.br/v1/documents/7c3d1e2f4a5b6c7d8e9f0a1b/thumbnail"
                    },
                    "is_closed": false,
                    "signing_url": "https://app.assinafy.com.br/sign/7c3d1e2f4a5b6c7d8e9f0a1b",
                    "verification_code": null,
                    "verification_url": null,
                    "decline_reason": null,
                    "declined_by": null,
                    "tags": [],
                    "created_at": 1790000000,
                    "updated_at": 1790090000,
                    "assignment": {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                      "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                      "created_at": 1790003900,
                      "sender_email": "person@example.com",
                      "method": "virtual",
                      "expires_at": 1792592000,
                      "message": "Olá João, segue o contrato para assinatura.",
                      "signers": [
                        {
                          "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                          "full_name": "João da Silva",
                          "email": "joao.silva@example.com",
                          "whatsapp_phone_number": "+5548999990000",
                          "government_id": null,
                          "has_accepted_terms": false,
                          "completed": false,
                          "notification_history": [],
                          "verification_method": "Email",
                          "notification_methods": [
                            "Email"
                          ],
                          "step": 1,
                          "notified": true
                        }
                      ],
                      "copy_receivers": [],
                      "items": [
                        {
                          "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                          "page": null,
                          "signer": {
                            "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                            "full_name": "João da Silva",
                            "email": "joao.silva@example.com",
                            "whatsapp_phone_number": "+5548999990000",
                            "government_id": null,
                            "has_accepted_terms": false
                          },
                          "field": {
                            "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                            "name": "Virtual",
                            "type": "virtual",
                            "regex": null,
                            "is_pre_defined": false,
                            "is_active": true,
                            "is_required": false,
                            "is_standard": false,
                            "is_read_only": false,
                            "is_visible": false
                          },
                          "display_settings": [],
                          "value": null,
                          "completed": false
                        }
                      ],
                      "summary": {
                        "signer_count": 1,
                        "completed_count": 0,
                        "signers": [
                          {
                            "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                            "full_name": "João da Silva",
                            "email": "joao.silva@example.com",
                            "whatsapp_phone_number": "+5548999990000",
                            "government_id": null,
                            "has_accepted_terms": false,
                            "completed": false
                          }
                        ]
                      },
                      "signing_urls": [
                        {
                          "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                          "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                        }
                      ]
                    },
                    "pages": [
                      {
                        "id": "66b2f12ad4e5f6a7b8c9d0e1",
                        "number": 1,
                        "height": 2200,
                        "width": 1700,
                        "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                      },
                      {
                        "id": "66b2f12ad4e5f6a7b8c9d0e2",
                        "number": 2,
                        "height": 2200,
                        "width": 1700,
                        "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                      }
                    ],
                    "type": "Document"
                  },
                  "account_id": "65f1c2a9b3e4d5f6"
                }
              },
              "unlinked": {
                "summary": "Unlinked",
                "value": {
                  "id": 184601,
                  "event": "document_contract_amendment_updated",
                  "message": null,
                  "payload": {
                    "user_name": "Mariana Costa",
                    "parent_document_id": null,
                    "relation_type": null,
                    "terms": null,
                    "changed": [
                      "parent_document_id",
                      "relation_type",
                      "terms"
                    ]
                  },
                  "origin": {
                    "ip": "203.0.113.7",
                    "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
                  },
                  "created_at": 1790095000,
                  "subject": {
                    "id": "d6zqpbyog2v3xvxerwn8la94",
                    "name": "Mariana Costa",
                    "email": "person@example.com",
                    "telephone": "+5511987654321",
                    "government_id": "12345678909",
                    "is_email_verified": true,
                    "is_mfa_enabled": false,
                    "has_accepted_terms": true,
                    "is_password_set": true,
                    "created_at": 1772720000,
                    "to_be_deleted_at": null,
                    "type": "User"
                  },
                  "object": {
                    "id": "7c3d1e2f4a5b6c7d8e9f0a1b",
                    "account_id": "65f1c2a9b3e4d5f6",
                    "template_id": null,
                    "name": "Aditivo ao Contrato de Prestação de Serviços.pdf",
                    "status": "pending_signature",
                    "artifacts": {
                      "original": "https://api.assinafy.com.br/v1/documents/7c3d1e2f4a5b6c7d8e9f0a1b/download/original",
                      "thumbnail": "https://api.assinafy.com.br/v1/documents/7c3d1e2f4a5b6c7d8e9f0a1b/thumbnail"
                    },
                    "is_closed": false,
                    "signing_url": "https://app.assinafy.com.br/sign/7c3d1e2f4a5b6c7d8e9f0a1b",
                    "verification_code": null,
                    "verification_url": null,
                    "decline_reason": null,
                    "declined_by": null,
                    "tags": [],
                    "created_at": 1790000000,
                    "updated_at": 1790090000,
                    "assignment": {
                      "id": "66b2f2b3c4d5e6f7a8b9c0d1",
                      "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                      "created_at": 1790003900,
                      "sender_email": "person@example.com",
                      "method": "virtual",
                      "expires_at": 1792592000,
                      "message": "Olá João, segue o contrato para assinatura.",
                      "signers": [
                        {
                          "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                          "full_name": "João da Silva",
                          "email": "joao.silva@example.com",
                          "whatsapp_phone_number": "+5548999990000",
                          "government_id": null,
                          "has_accepted_terms": false,
                          "completed": false,
                          "notification_history": [],
                          "verification_method": "Email",
                          "notification_methods": [
                            "Email"
                          ],
                          "step": 1,
                          "notified": true
                        }
                      ],
                      "copy_receivers": [],
                      "items": [
                        {
                          "id": "66b2f2b3c4d5e6f7a8b9c0d2",
                          "page": null,
                          "signer": {
                            "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                            "full_name": "João da Silva",
                            "email": "joao.silva@example.com",
                            "whatsapp_phone_number": "+5548999990000",
                            "government_id": null,
                            "has_accepted_terms": false
                          },
                          "field": {
                            "id": "65f1c2aa0b1c2d3e4f5a6b7c",
                            "name": "Virtual",
                            "type": "virtual",
                            "regex": null,
                            "is_pre_defined": false,
                            "is_active": true,
                            "is_required": false,
                            "is_standard": false,
                            "is_read_only": false,
                            "is_visible": false
                          },
                          "display_settings": [],
                          "value": null,
                          "completed": false
                        }
                      ],
                      "summary": {
                        "signer_count": 1,
                        "completed_count": 0,
                        "signers": [
                          {
                            "id": "66b2f0c9a1b2c3d4e5f6a7b8",
                            "full_name": "João da Silva",
                            "email": "joao.silva@example.com",
                            "whatsapp_phone_number": "+5548999990000",
                            "government_id": null,
                            "has_accepted_terms": false,
                            "completed": false
                          }
                        ]
                      },
                      "signing_urls": [
                        {
                          "signer_id": "66b2f0c9a1b2c3d4e5f6a7b8",
                          "url": "https://app.assinafy.com.br/sign/66b2f0e1c3a4d5e6f7a8b9c0?email=joao.silva%40example.com"
                        }
                      ]
                    },
                    "pages": [
                      {
                        "id": "66b2f12ad4e5f6a7b8c9d0e1",
                        "number": 1,
                        "height": 2200,
                        "width": 1700,
                        "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e1/download"
                      },
                      {
                        "id": "66b2f12ad4e5f6a7b8c9d0e2",
                        "number": 2,
                        "height": 2200,
                        "width": 1700,
                        "download_url": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/pages/66b2f12ad4e5f6a7b8c9d0e2/download"
                      }
                    ],
                    "type": "Document"
                  },
                  "account_id": "65f1c2a9b3e4d5f6"
                }
              }
            }
          }
        }
      },
      "responses": {
        "2XX": {
          "description": "Any `2xx` response acknowledges the delivery. Any other status, a connection error or a timeout counts as a failed attempt."
        }
      }
    }
  }
}
```

## Component dictionary

```json
{
  "components": {
    "schemas": {
      "Envelope": {
        "description": "Standard success wrapper. Operations add their own `data`.",
        "properties": {
          "status": {
            "description": "HTTP status code, mirrored in the body.",
            "type": "integer",
            "example": 200
          },
          "message": {
            "description": "Human-readable message; empty on success.",
            "type": "string",
            "example": ""
          }
        },
        "type": "object"
      },
      "ErrorEnvelope": {
        "description": "Standard error wrapper. `status` mirrors the HTTP status code.",
        "properties": {
          "status": {
            "type": "integer",
            "example": 400
          },
          "message": {
            "description": "Human-readable error message.",
            "type": "string",
            "example": "Bad request."
          },
          "data": {
            "type": [
              "object",
              "null"
            ],
            "example": null
          },
          "code": {
            "description": "On the refusals a client can act on: see Reading a refusal. Other refusals have only `message`.",
            "type": "string",
            "example": "PlanRestricted"
          },
          "errors": {
            "description": "On a `400` about a field (a model's validation, a missing or an unknown field): one entry per field in error; `message` stays the first entry's. Other `400`s have only `message`. See Reading a refusal.",
            "type": "array",
            "items": {
              "required": [
                "field",
                "code",
                "message"
              ],
              "properties": {
                "field": {
                  "description": "The field as sent, a dot for a nested one (`address.line1`); `null` when the request as a whole is refused.",
                  "type": [
                    "string",
                    "null"
                  ],
                  "example": "full_name"
                },
                "code": {
                  "description": "`Required`, `UnknownField` or `Invalid`; the contract endpoints have codes of their own.",
                  "type": "string",
                  "example": "Required"
                },
                "message": {
                  "description": "In the platform's language.",
                  "type": "string",
                  "example": "Full Name cannot be blank."
                }
              },
              "type": "object"
            }
          }
        },
        "type": "object"
      },
      "ApiKey": {
        "properties": {
          "api_key": {
            "type": [
              "string",
              "null"
            ],
            "example": "mIpe_zdJfKUpMK9Va3XuYgzPXMxz49fIaRCWXseVkpVAX608A9j3i_D67qU5qW3M"
          }
        },
        "type": "object"
      },
      "AuthUser": {
        "properties": {
          "id": {
            "type": "string",
            "example": "bgjazeo5r9v2lq7l36dx48np"
          },
          "name": {
            "type": "string",
            "example": "John Smith"
          },
          "email": {
            "type": "string",
            "format": "email",
            "example": "example@example.com"
          },
          "telephone": {
            "type": [
              "string",
              "null"
            ],
            "example": "17989206641"
          },
          "government_id": {
            "type": [
              "string",
              "null"
            ],
            "example": "15774136604"
          },
          "is_email_verified": {
            "type": "boolean",
            "example": false
          },
          "has_accepted_terms": {
            "type": "boolean",
            "example": true
          },
          "created_at": {
            "type": "string",
            "format": "date-time",
            "example": "2023-03-03T11:51:34Z"
          },
          "to_be_deleted_at": {
            "type": [
              "string",
              "null"
            ],
            "format": "date-time",
            "example": null
          }
        },
        "type": "object"
      },
      "AuthAccount": {
        "properties": {
          "id": {
            "type": "string",
            "example": "6401df46d6a6b0c692d9ec49"
          },
          "name": {
            "type": "string",
            "example": "JS"
          },
          "roles": {
            "type": "array",
            "items": {
              "type": "string"
            },
            "example": [
              "owner"
            ]
          },
          "is_delete_allowed": {
            "type": "boolean",
            "example": true
          },
          "created_at": {
            "type": "string",
            "format": "date-time",
            "example": "2023-03-03T11:51:34Z"
          }
        },
        "type": "object"
      },
      "Signer": {
        "description": "A signing party belonging to a workspace account.",
        "properties": {
          "resource": {
            "description": "Present in single-resource responses.",
            "type": "string",
            "example": "signer"
          },
          "id": {
            "type": "string",
            "example": "62d6ee35c7741ca4006b9e11"
          },
          "full_name": {
            "type": "string",
            "example": "John Signer"
          },
          "email": {
            "type": [
              "string",
              "null"
            ],
            "format": "email",
            "example": "person@example.com"
          },
          "whatsapp_phone_number": {
            "description": "E.164 format; normalized on save.",
            "type": [
              "string",
              "null"
            ],
            "example": "+5548999990000"
          },
          "government_id": {
            "description": "Signer's normalized CPF (11 digits) or CNPJ (14 characters; may be alphanumeric).",
            "type": [
              "string",
              "null"
            ],
            "example": "39053344705"
          },
          "has_accepted_terms": {
            "type": "boolean",
            "example": false
          }
        },
        "type": "object"
      },
      "SignerSelf": {
        "description": "The current signer, as returned by `GET /v1/signers/self`. Extends Signer with the signature-state flags that are only computed for the authenticated signer.",
        "type": "object",
        "allOf": [
          {
            "$ref": "#/components/schemas/Signer"
          },
          {
            "properties": {
              "has_signature": {
                "description": "Whether the signer has a saved signature image stored.",
                "type": "boolean",
                "example": true
              },
              "has_initial": {
                "description": "Whether the signer has a saved initials image stored.",
                "type": "boolean",
                "example": false
              },
              "is_signature_reusable": {
                "description": "Whether the signer opted to reuse their saved signature/initials in future processes. When false, clients should not pre-render the saved image even if `has_signature`/`has_initial` is true.",
                "type": "boolean",
                "example": false
              },
              "verification_method": {
                "description": "How this signature request verifies the signer. `Email`/`Whatsapp` ask for the 6-digit code; `IdDocumentFaceMatchBasic` runs the identity document check instead (see **Identity document verification status**).",
                "type": "string",
                "enum": [
                  "Email",
                  "Whatsapp",
                  "DigitalCertificate",
                  "IdDocumentFaceMatchBasic"
                ],
                "example": "Email"
              }
            },
            "type": "object"
          }
        ]
      },
      "DocumentPage": {
        "properties": {
          "id": {
            "type": "string",
            "example": "615601faf166d6d1d8e7dc30"
          },
          "number": {
            "type": "integer",
            "example": 1
          },
          "height": {
            "type": "integer",
            "example": 2100
          },
          "width": {
            "type": "integer",
            "example": 1275
          },
          "download_url": {
            "type": "string",
            "example": "https://api.assinafy.com.br/v1/documents/doc1/pages/1a/download"
          }
        },
        "type": "object"
      },
      "DisplaySettings": {
        "description": "A field placement rectangle on a document page. Geometry values are pixels in Assinafy's 150-DPI page image, measured from the upper-left corner. Clients must keep the rectangle within the selected page's width and height; the API does not clamp out-of-bounds values.",
        "required": [
          "left",
          "top",
          "width",
          "height",
          "fontSize"
        ],
        "properties": {
          "left": {
            "description": "Horizontal distance from the page's left edge, in page-image pixels.",
            "type": "number",
            "format": "float",
            "minimum": 0,
            "example": 69
          },
          "top": {
            "description": "Vertical distance from the page's top edge, in page-image pixels.",
            "type": "number",
            "format": "float",
            "minimum": 0,
            "example": 282
          },
          "width": {
            "description": "Width of the placement rectangle, in page-image pixels.",
            "type": "number",
            "format": "float",
            "exclusiveMinimum": 0,
            "example": 421
          },
          "height": {
            "description": "Height of the placement rectangle, in page-image pixels.",
            "type": "number",
            "format": "float",
            "exclusiveMinimum": 0,
            "example": 45.86
          },
          "fontFamily": {
            "description": "Font-family presentation metadata.",
            "type": "string",
            "example": "Arial"
          },
          "fontSize": {
            "description": "Font size in the 150-DPI page-image coordinate system.",
            "type": "number",
            "format": "float",
            "exclusiveMinimum": 0,
            "example": 22
          },
          "backgroundColor": {
            "description": "CSS-compatible background-color presentation metadata.",
            "type": "string",
            "example": "#D5EBFF"
          }
        },
        "type": "object"
      },
      "DocumentStatus": {
        "properties": {
          "code": {
            "type": "string",
            "example": "metadata_ready"
          },
          "deletable": {
            "type": "boolean",
            "example": true
          }
        },
        "type": "object"
      },
      "Document": {
        "description": "A document and its current lifecycle state.",
        "properties": {
          "resource": {
            "description": "Present in single-resource responses.",
            "type": "string",
            "example": "document"
          },
          "id": {
            "type": "string",
            "example": "615601fab04c0a3147bb1246"
          },
          "account_id": {
            "type": "string",
            "example": "d199996981dbd199996981db"
          },
          "template_id": {
            "type": [
              "string",
              "null"
            ],
            "example": null
          },
          "name": {
            "type": "string",
            "example": "document.pdf"
          },
          "status": {
            "description": "Status code — see GET /v1/documents/statuses.",
            "type": "string",
            "example": "metadata_ready"
          },
          "artifacts": {
            "description": "Artifact download URLs keyed by name. Always `original`, plus `thumbnail` once one exists. A certificated document also carries `certificated`, `certificate-page` and `bundle`, and `pades` when it was signed with a digital certificate — the PAdES version holds the signers' ICP-Brasil signatures, which certification flattens out of the certificated PDF.",
            "type": "object",
            "example": {
              "original": "https://api.assinafy.com.br/v1/documents/doc1/download/original"
            }
          },
          "is_closed": {
            "type": "boolean",
            "example": false
          },
          "signing_url": {
            "type": "string",
            "example": "https://api.assinafy.com.br/v1/sign/doc1"
          },
          "verification_code": {
            "description": "The code printed on the certificate page. Anyone can check it with `GET /v1/documents/{verification_code}/verify` or on `verification_url`. `null` until the document is `certificated`, and on documents certificated before verification codes existed.",
            "type": [
              "string",
              "null"
            ],
            "example": "a3f1c9e07b2d4e8f9a6c5b1d0e7f3a2c9b8d4e6f"
          },
          "verification_url": {
            "description": "The public verification page for this document. `null` until the document is `certificated`.",
            "type": [
              "string",
              "null"
            ],
            "format": "uri",
            "example": "https://www.assinafy.com.br/verificador/a3f1c9e07b2d4e8f9a6c5b1d0e7f3a2c9b8d4e6f"
          },
          "decline_reason": {
            "type": [
              "string",
              "null"
            ],
            "example": null
          },
          "declined_by": {
            "oneOf": [
              {
                "$ref": "#/components/schemas/Signer"
              },
              {
                "type": "null"
              }
            ]
          },
          "tags": {
            "type": "array",
            "items": {
              "properties": {
                "id": {
                  "type": "string"
                },
                "name": {
                  "type": "string"
                },
                "color": {
                  "description": "6-char hex without leading #.",
                  "type": [
                    "string",
                    "null"
                  ],
                  "example": "ff8800"
                }
              },
              "type": "object"
            }
          },
          "assignment": {
            "description": "The document's assignment, or `null` before one is created. The server decides when it is included, whatever `expand` the request sends: the document list, `GET /v1/documents/{documentId}`, the signer's document list and document creation from a template always include it; `/documents/search` and the upload response never do. Only the rename response follows `?expand=assignment`.",
            "oneOf": [
              {
                "$ref": "#/components/schemas/Assignment"
              },
              {
                "type": "null"
              }
            ]
          },
          "pages": {
            "description": "The document's pages. Included on the same responses as `assignment`, and also on the upload response.",
            "type": "array",
            "items": {
              "$ref": "#/components/schemas/DocumentPage"
            }
          },
          "envelope_id": {
            "description": "Only on `GET /v1/documents/{documentId}?expand=envelope_id` read by the workspace, not in document lists or webhooks, so their payloads keep their shape: the envelope this document is in, or `null` for a document sent on its own, taken out of its envelope, or whose envelope was deleted.",
            "type": [
              "string",
              "null"
            ],
            "example": null
          },
          "envelope": {
            "description": "Only on `GET /v1/documents/{documentId}?expand=envelope` read by the workspace and `GET /v1/sign?expand=envelope` or `GET /v1/signers/{signerId}/document?expand=envelope` read with the signer's code, not in document lists or webhooks: the envelope this document is in, or `null` for a document sent on its own, taken out of its envelope, or whose envelope was deleted. The signer's reads name the envelope their code belongs to even when the code's own document was taken out, since the code still reaches the rest.",
            "required": [
              "id",
              "name"
            ],
            "properties": {
              "id": {
                "type": "string",
                "example": "fe1c140cb49b79f940aab95fddd"
              },
              "name": {
                "type": "string",
                "example": "Contrato de prestação de serviços"
              }
            },
            "type": [
              "object",
              "null"
            ]
          },
          "has_contract": {
            "description": "Only on `GET /v1/documents/{documentId}` read by the workspace, while the environment has contract tracking on: not with a signer's access code, not in document lists or webhooks, and left out when the lookup fails, so a missing key means unknown. Whether `GET /v1/accounts/{accountId}/documents/{documentId}/contract` answers with contract terms, the document's own or those of a contract it amends whose document still exists, so a page can lay out the contract section before reading it.",
            "type": "boolean",
            "example": true
          },
          "created_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-06-03T03:54:16Z"
          },
          "updated_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-06-03T03:54:16Z"
          }
        },
        "type": "object"
      },
      "SigningDocument": {
        "description": "The document as its signer reads it with the access code: the document with `current_signer`, `is_signer_access_closed` and `certified_copy`. Once the code is closed, only `id`, `name`, `status` and these three, and `envelope` when asked for.",
        "required": [
          "id",
          "name",
          "status",
          "current_signer",
          "is_signer_access_closed",
          "certified_copy"
        ],
        "type": "object",
        "allOf": [
          {
            "$ref": "#/components/schemas/Document"
          },
          {
            "properties": {
              "current_signer": {
                "description": "This signer. Once the code is closed, only `id` and the last three.",
                "type": "object",
                "allOf": [
                  {
                    "$ref": "#/components/schemas/Signer"
                  },
                  {
                    "properties": {
                      "verification_method": {
                        "description": "This signature request's verification method.",
                        "type": "string",
                        "example": "Email"
                      },
                      "notification_methods": {
                        "description": "This signature request's notification methods; `null` on requests stored before they were recorded.",
                        "type": [
                          "array",
                          "null"
                        ],
                        "items": {
                          "type": "string"
                        },
                        "example": [
                          "Email"
                        ]
                      },
                      "completed": {
                        "description": "Whether this signer has signed.",
                        "type": "boolean",
                        "example": false
                      }
                    },
                    "type": "object"
                  }
                ]
              },
              "is_signer_access_closed": {
                "description": "Whether the code is closed.",
                "type": "boolean",
                "example": false
              },
              "certified_copy": {
                "description": "Whether the signer can ask for the certified copy again (`POST /v1/sign/certified-copy`).",
                "required": [
                  "available",
                  "notification_method",
                  "resends_left"
                ],
                "properties": {
                  "available": {
                    "type": "boolean",
                    "example": false
                  },
                  "notification_method": {
                    "description": "The channel it goes on; `null` when there is none.",
                    "type": [
                      "string",
                      "null"
                    ],
                    "enum": [
                      "Email",
                      "Whatsapp",
                      null
                    ],
                    "example": "Email"
                  },
                  "resends_left": {
                    "description": "WhatsApp re-sends left; `null` for e-mail.",
                    "type": [
                      "integer",
                      "null"
                    ],
                    "example": null
                  }
                },
                "type": "object"
              }
            },
            "type": "object"
          }
        ]
      },
      "Account": {
        "description": "A workspace account (organization).",
        "properties": {
          "resource": {
            "type": "string",
            "example": "account"
          },
          "id": {
            "type": "string",
            "example": "6401df46d6a6b0c692d9ec49"
          },
          "name": {
            "type": "string",
            "example": "Acme Inc."
          },
          "primary_color": {
            "type": [
              "string",
              "null"
            ],
            "example": "aabbcc"
          },
          "secondary_color": {
            "type": [
              "string",
              "null"
            ],
            "example": "112233"
          },
          "notification_sender_type": {
            "type": "string",
            "enum": [
              "User",
              "Account"
            ],
            "example": "User"
          },
          "roles": {
            "type": "array",
            "items": {
              "type": "string"
            },
            "example": [
              "owner"
            ]
          },
          "is_delete_allowed": {
            "type": "boolean",
            "example": true
          },
          "limits": {
            "description": "Limits of the workspace's current plan. Returned only by `GET /v1/accounts/{accountId}`, not in account lists. More keys may be added.",
            "properties": {
              "webhook_endpoints": {
                "description": "How many webhook endpoints the workspace can have: `1`, or `3` with the Multiple Webhooks feature. Creating one past the limit returns `403`.",
                "type": "integer",
                "example": 1
              }
            },
            "type": "object"
          },
          "signing_features": {
            "description": "Only with `?expand=signing_features` on `GET /v1/accounts/{accountId}`: which signing features the workspace's subscription includes now (its plan's and any granted to it), so a client offers only the identity checks and notices it can use. `DigitalCertificate` and `VerificationIdDocumentFaceMatchBasic` gate the `DigitalCertificate` and `IdDocumentFaceMatchBasic` verification methods, `WhatsappNotification` the `Whatsapp` notification method (and so the `Whatsapp` verification method), and `VerificationBypass` the `Bypass` verification method. `false` means creating an assignment, or a document from a template, with that method (or estimating its cost) answers `403`. `Embed` gates embedded signing: with `false`, the embed origins endpoints, `\"embedded\": true` on a signer and minting an embed session answer `403` with code `PlanRestricted`. `true` is not a promise: credits, the assignment's own rules (a `DigitalCertificate` signer needs a `virtual` assignment) and a change of plan still apply. Requires the `account:read` OAuth scope.",
            "required": [
              "DigitalCertificate",
              "VerificationIdDocumentFaceMatchBasic",
              "WhatsappNotification",
              "VerificationBypass",
              "Embed"
            ],
            "properties": {
              "DigitalCertificate": {
                "description": "Signing with the signer's own ICP-Brasil certificate.",
                "type": "boolean",
                "example": true
              },
              "VerificationIdDocumentFaceMatchBasic": {
                "description": "Identity check by ID document and face match.",
                "type": "boolean",
                "example": false
              },
              "WhatsappNotification": {
                "description": "Sending signature requests by WhatsApp.",
                "type": "boolean",
                "example": true
              },
              "VerificationBypass": {
                "description": "Signing without a verification code (the `Bypass` verification method).",
                "type": "boolean",
                "example": false
              },
              "Embed": {
                "description": "Embedded signing: the signing screen inside your own app (see **Embedded Signing Guide**).",
                "type": "boolean",
                "example": false
              }
            },
            "type": "object"
          },
          "created_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-06-03T03:54:16Z"
          }
        },
        "type": "object"
      },
      "Field": {
        "description": "A reusable field definition.",
        "properties": {
          "resource": {
            "type": "string",
            "example": "field"
          },
          "id": {
            "type": "string",
            "example": "6152120297080d55bdd13197"
          },
          "name": {
            "type": "string",
            "example": "Signature"
          },
          "type": {
            "type": "string",
            "example": "signature"
          },
          "regex": {
            "type": [
              "string",
              "null"
            ]
          },
          "is_pre_defined": {
            "type": "boolean"
          },
          "is_active": {
            "type": "boolean"
          },
          "is_required": {
            "type": "boolean"
          },
          "is_standard": {
            "type": "boolean"
          },
          "is_read_only": {
            "type": "boolean"
          },
          "is_visible": {
            "type": "boolean"
          }
        },
        "type": "object"
      },
      "Tag": {
        "description": "A workspace-scoped label. Names are unique per workspace (case-insensitive).",
        "properties": {
          "resource": {
            "type": "string",
            "example": "tag"
          },
          "id": {
            "type": "string",
            "example": "fa8c09f3e709a8a1c82d69b1454"
          },
          "name": {
            "type": "string",
            "example": "Contracts"
          },
          "color": {
            "description": "6-char hex without leading #.",
            "type": [
              "string",
              "null"
            ],
            "example": "ff8800"
          },
          "created_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-05-14T12:00:00Z"
          },
          "updated_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-05-14T12:00:00Z"
          }
        },
        "type": "object"
      },
      "DocumentContract": {
        "description": "The contract terms tracked on a document. Every key below is always present, `null` when it holds no value, except `resource`, `document` and `amendments`, which depend on the response.",
        "required": [
          "document_id",
          "status",
          "counterparty_name",
          "counterparty_taxpayer_id",
          "value",
          "currency",
          "start_date",
          "end_date",
          "auto_renew",
          "renewal_term_months",
          "notice_period_days",
          "term",
          "owner_id",
          "metadata",
          "external_references",
          "created_at",
          "updated_at"
        ],
        "properties": {
          "resource": {
            "description": "On a response that is this contract alone; list items carry none.",
            "type": "string",
            "example": "document_contract"
          },
          "document_id": {
            "type": "string",
            "example": "66b2f0e1c3a4d5e6f7a8b9c0"
          },
          "status": {
            "type": "string",
            "enum": [
              "draft",
              "active",
              "expired",
              "terminated"
            ],
            "example": "active"
          },
          "counterparty_name": {
            "type": [
              "string",
              "null"
            ],
            "example": "Acme Ltda"
          },
          "counterparty_taxpayer_id": {
            "description": "CPF or CNPJ, digits only.",
            "type": [
              "string",
              "null"
            ],
            "example": "11222333000181"
          },
          "value": {
            "description": "Two decimal places at most.",
            "type": [
              "number",
              "null"
            ],
            "example": 120000.5
          },
          "currency": {
            "description": "ISO 4217 code; `BRL` when a value is sent without one.",
            "type": [
              "string",
              "null"
            ],
            "example": "BRL"
          },
          "start_date": {
            "type": [
              "string",
              "null"
            ],
            "format": "date",
            "example": "2026-11-01"
          },
          "end_date": {
            "type": [
              "string",
              "null"
            ],
            "format": "date",
            "example": "2027-10-31"
          },
          "auto_renew": {
            "type": "boolean",
            "example": true
          },
          "renewal_term_months": {
            "type": [
              "integer",
              "null"
            ],
            "example": 12
          },
          "notice_period_days": {
            "type": [
              "integer",
              "null"
            ],
            "example": 30
          },
          "term": {
            "description": "Steps on every renewal, starting at 1.",
            "type": "integer",
            "example": 1
          },
          "owner_id": {
            "description": "ID of the workspace member who owns the contract. When the owner is no longer a member of the workspace (removed, left, or deleted with their account), the hourly run hands the contract to the first owner who is still an active member, or leaves it without one; an inactive member stays the owner. The contract's e-mails (renewal notice, expiring, expired, renewed) go to the first of the owner and the document's creator who is an active member, as that member's notification preferences allow.",
            "type": [
              "string",
              "null"
            ],
            "example": "d6zqpbyog2v3xvxerwn8la94"
          },
          "metadata": {
            "description": "Up to 50 keys of your own, each holding text of at most 500 characters; `null` when there are none. Values come back exactly as sent. A key ending in `_at`, or `stuck_since`, cannot hold a number.",
            "type": [
              "object",
              "null"
            ],
            "example": {
              "crm_stage": "won"
            },
            "additionalProperties": {
              "type": "string"
            }
          },
          "external_references": {
            "description": "IDs outside systems use for this contract, ordered by system and id; `[]` when there are none.",
            "type": "array",
            "items": {
              "required": [
                "system",
                "id"
              ],
              "properties": {
                "system": {
                  "type": "string",
                  "example": "hubspot"
                },
                "id": {
                  "type": "string",
                  "example": "98765"
                }
              },
              "type": "object"
            }
          },
          "document": {
            "$ref": "#/components/schemas/Document",
            "description": "Only with `expand=document`: the document the terms are tracked on."
          },
          "amendments": {
            "description": "Only on the contract read and the related read (`GET …/contract`, `GET …/contract/related`), never on the list or on the answer to a write, renewal or termination: the documents that amend, renew or terminate this contract, the applied ones first by `number`, then the pending ones.",
            "type": "array",
            "items": {
              "$ref": "#/components/schemas/DocumentContractAmendment"
            }
          },
          "created_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-10-23T12:00:00Z"
          },
          "updated_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-10-23T12:00:00Z"
          }
        },
        "type": "object"
      },
      "DocumentContractInput": {
        "description": "Every attribute is optional and only the ones sent change. `null` clears an attribute, except `auto_renew` (back to `false`) and `currency` (back to `BRL` while a `value` is set); `status` does not accept `null`. A changed `end_date` starts a new `term`.",
        "properties": {
          "status": {
            "description": "On creation any status, so a contract that ended before it was tracked is registered as it ended: `expired` needs an `end_date` in the past, `terminated` its `end_date`; neither is walked by the lifecycle. Afterwards only `draft` or `active` (a contract in force never goes back to `draft`): `expired` and `terminated` are set by the lifecycle and the terminate endpoint; a closed contract is reopened by renewing it.",
            "type": "string",
            "enum": [
              "draft",
              "active",
              "expired",
              "terminated"
            ]
          },
          "counterparty_name": {
            "description": "Emoji are kept; other characters outside the 3-byte range are dropped.",
            "type": [
              "string",
              "null"
            ],
            "maxLength": 255
          },
          "counterparty_taxpayer_id": {
            "description": "A valid CPF or CNPJ, formatted or not.",
            "type": [
              "string",
              "null"
            ]
          },
          "value": {
            "description": "Two decimal places at most.",
            "type": [
              "number",
              "null"
            ],
            "maximum": 9999999999999.99,
            "minimum": 0
          },
          "currency": {
            "description": "Three letters, such as `BRL` or `USD`, stored in capitals; `BRL` when a `value` is set without one.",
            "type": [
              "string",
              "null"
            ],
            "example": "BRL"
          },
          "start_date": {
            "description": "`YYYY-MM-DD`, from the year 1000.",
            "type": [
              "string",
              "null"
            ],
            "format": "date"
          },
          "end_date": {
            "description": "`YYYY-MM-DD`, on or after `start_date`.",
            "type": [
              "string",
              "null"
            ],
            "format": "date"
          },
          "auto_renew": {
            "description": "Whether the hourly run starts the next term the day after `end_date`, by `renewal_term_months`, instead of expiring the contract.",
            "type": "boolean"
          },
          "renewal_term_months": {
            "description": "How far a renewal by the term moves `end_date`; 12 when unset.",
            "type": [
              "integer",
              "null"
            ],
            "maximum": 600,
            "minimum": 1
          },
          "notice_period_days": {
            "description": "Days before `end_date` the notice period starts (`document_contract_renewal_notice`); none when unset.",
            "type": [
              "integer",
              "null"
            ],
            "maximum": 3650,
            "minimum": 0
          },
          "owner_id": {
            "description": "An active member of this workspace.",
            "type": [
              "string",
              "null"
            ]
          },
          "metadata": {
            "description": "Your own fields: up to 50 keys of 1 to 40 letters, digits, `_`, `-` or `.`, each holding plain text of at most 500 characters; no number under a key ending in `_at`, or `stuck_since`.",
            "maxProperties": 50,
            "type": [
              "object",
              "null"
            ],
            "additionalProperties": {
              "type": "string",
              "maxLength": 500
            }
          },
          "external_references": {
            "description": "Replaces the whole list; `[]` or `null` clears it. Two items whose `system` and `id` compare equal case aside are one reference (the first sent is kept); a pair the database takes for one beyond that (accents, `ß` and `ss`) answers `400`.",
            "type": [
              "array",
              "null"
            ],
            "items": {
              "required": [
                "system",
                "id"
              ],
              "properties": {
                "system": {
                  "description": "Letters, digits, `_`, `-` and `.`; stored in lower case.",
                  "type": "string",
                  "maxLength": 64,
                  "example": "hubspot"
                },
                "id": {
                  "description": "Kept as sent; must not contain emoji or other 4-byte characters.",
                  "type": "string",
                  "maxLength": 255,
                  "example": "98765"
                }
              },
              "type": "object"
            },
            "maxItems": 20
          },
          "parent_document_id": {
            "description": "Links the document as an amendment of this contract document, or of any document in its family (resolved to the root); `null` unlinks a pending amendment. With it, the other attributes are the terms the instrument changes, not terms of its own; on a pending amendment a `null` value drops that term from the delta rather than clearing it on the parent.",
            "type": [
              "string",
              "null"
            ]
          },
          "relation_type": {
            "description": "Required on the first link. `amendment` may change `value`, `currency`, `start_date` (effective from, display only), `end_date`, `auto_renew`, `renewal_term_months`, `notice_period_days`, `counterparty_name`, `counterparty_taxpayer_id` and `metadata`; `renewal` only `end_date` and `value`; `termination` only `end_date` (the effective date).",
            "type": "string",
            "enum": [
              "amendment",
              "renewal",
              "termination"
            ]
          },
          "updated_at": {
            "description": "Optional precondition: the `updated_at` the client read the contract (or this document's amendment link) with, as the API returned it. If the contract or link changed since, nothing is written and the answer is `409` with `code: ContractChanged`. Not sent, or `null`: the write applies whatever changed meanwhile (last write wins). Compared to the second; a fraction of a second, as a JavaScript date prints it, is read and ignored. New terms and a first link have nothing to have read, so sending one answers `409`; on a pending amendment, send the link's own `updated_at` (the GET on that document).",
            "type": "string",
            "format": "date-time",
            "example": "2026-10-23T12:00:00Z"
          }
        },
        "type": "object"
      },
      "DocumentContractAmendment": {
        "description": "A document that amends, renews or terminates a contract: its link and the terms it changes. The parent's terms take the delta once this document is certificated; until then the parent is untouched. Every key below is always present, `null` when it holds no value, except `resource`.",
        "required": [
          "document_id",
          "document_name",
          "parent_document_id",
          "relation_type",
          "number",
          "status",
          "document_status",
          "apply_error",
          "currency",
          "terms",
          "changes",
          "applied_at",
          "created_at",
          "updated_at"
        ],
        "properties": {
          "resource": {
            "description": "On a response that is this amendment alone; the entries of a contract's `amendments` carry none.",
            "type": "string",
            "example": "document_contract_amendment"
          },
          "document_id": {
            "description": "This document, the instrument.",
            "type": "string",
            "example": "7c3d1e2f4a5b6c7d8e9f0a1b"
          },
          "document_name": {
            "description": "This document's name.",
            "type": "string",
            "example": "Aditivo 1 - Contrato de prestação de serviços.pdf"
          },
          "parent_document_id": {
            "description": "The contract document whose terms change.",
            "type": "string",
            "example": "66b2f0e1c3a4d5e6f7a8b9c0"
          },
          "relation_type": {
            "type": "string",
            "enum": [
              "amendment",
              "renewal",
              "termination"
            ],
            "example": "amendment"
          },
          "number": {
            "description": "Position among the parent's applied amendments by signing date; `null` while pending.",
            "type": [
              "integer",
              "null"
            ],
            "example": 1
          },
          "status": {
            "description": "`applied` once the delta moved the parent's terms.",
            "type": "string",
            "enum": [
              "pending",
              "applied"
            ],
            "example": "pending"
          },
          "document_status": {
            "description": "This document's own status, so a declined, cancelled or expired instrument shows as such. An instrument applies only while its document is `certificated`: one whose document reads `failed` after it was signed waits, with no `apply_error`, until it is certificated again, and then applies in its place in the signing order.",
            "type": "string",
            "example": "pending_signature"
          },
          "apply_error": {
            "description": "Why a signed instrument has not applied yet, retried every hour: the workspace's plan does not include contract tracking (it applies on the first run after the plan returns), the parent would not take the delta, an instrument signed before this one has not applied yet, one signed after it has already applied (this one then stays blocked: unlink it, and record what it still changes on a new instrument), or an unexpected failure.",
            "type": [
              "string",
              "null"
            ]
          },
          "currency": {
            "description": "The currency of the amounts in `terms` and `changes`. Once applied, the contract's currency at that moment, kept whatever the contract moves to later. While pending (and for an instrument applied before this was kept), the one this instrument moves the contract to, otherwise the contract's. `null` when neither has one.",
            "type": [
              "string",
              "null"
            ],
            "example": "BRL"
          },
          "terms": {
            "description": "The delta: only the terms this instrument changes; `null` when it changes none (a termination on the day it is signed).",
            "type": [
              "object",
              "null"
            ],
            "example": {
              "value": 130000,
              "end_date": "2028-06-30"
            }
          },
          "changes": {
            "description": "Every parent field the roll-up moves, as `{from, to}`: a preview while pending, the record once applied; `null` when it moves none.",
            "type": [
              "object",
              "null"
            ],
            "example": {
              "value": {
                "from": 120000.5,
                "to": 130000
              },
              "end_date": {
                "from": "2027-10-31",
                "to": "2028-06-30"
              },
              "term": {
                "from": 1,
                "to": 2
              }
            }
          },
          "applied_at": {
            "type": [
              "string",
              "null"
            ],
            "format": "date-time"
          },
          "created_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-10-23T12:00:00Z"
          },
          "updated_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-10-23T12:00:00Z"
          }
        },
        "type": "object"
      },
      "SigningUrl": {
        "description": "One entry per signer whose turn has come. The link depends on how the signer gets the invitation: with `Email` it is a page that does not open the document by itself, where the signer asks for their invitation again by e-mail; with `Whatsapp` only it is `null` (the signer has their link in the WhatsApp message). An embedded signer (`embedded: true`) never gets a link: `url` is `null` and the integrator mints an embed session for them instead.",
        "properties": {
          "signer_id": {
            "type": "string"
          },
          "url": {
            "description": "See the schema description. `null` for a signer notified only by WhatsApp and for an embedded signer.",
            "type": [
              "string",
              "null"
            ],
            "example": "https://api.assinafy.com.br/v1/sign/doc1?email=joe@example.com"
          },
          "embedded": {
            "description": "Whether the signer was marked `embedded` when the assignment was created.",
            "type": "boolean",
            "example": false
          }
        },
        "type": "object"
      },
      "AssignmentSigner": {
        "description": "A signer within an assignment: the base Signer plus per-assignment verification/notification details.",
        "type": "object",
        "allOf": [
          {
            "$ref": "#/components/schemas/Signer"
          },
          {
            "properties": {
              "verification_method": {
                "type": [
                  "string",
                  "null"
                ],
                "example": "Email"
              },
              "notification_methods": {
                "type": [
                  "array",
                  "null"
                ],
                "items": {
                  "type": "string"
                },
                "example": [
                  "Email"
                ]
              },
              "step": {
                "description": "Sequential signing step (defaults to 1).",
                "type": [
                  "integer",
                  "null"
                ],
                "example": 1
              },
              "notified": {
                "type": [
                  "boolean",
                  "null"
                ]
              },
              "identity_verification_status": {
                "description": "Latest identity-document verification status; `InReview` means the account must decide it before the document can be certificated.",
                "type": [
                  "string",
                  "null"
                ],
                "enum": [
                  "Pending",
                  "InReview",
                  "Approved",
                  "Declined",
                  "Expired",
                  "Abandoned"
                ],
                "example": "InReview"
              },
              "completed": {
                "description": "Only present in account-owner contexts.",
                "type": [
                  "boolean",
                  "null"
                ]
              },
              "notification_history": {
                "description": "Per-channel delivery history for this signer (email + WhatsApp), most-recent send order.",
                "type": [
                  "array",
                  "null"
                ],
                "items": {
                  "$ref": "#/components/schemas/NotificationHistoryEntry"
                }
              }
            },
            "type": "object"
          }
        ]
      },
      "NotificationHistoryEntry": {
        "description": "A single notification delivery record for a signer channel.",
        "properties": {
          "event": {
            "type": "string",
            "example": "signature_request"
          },
          "status": {
            "description": "Furthest delivery stage reached. `opened` means the email was opened/clicked or the WhatsApp message was read.",
            "type": "string",
            "enum": [
              "sent",
              "delivered",
              "opened",
              "failed"
            ],
            "example": "failed"
          },
          "error_code": {
            "description": "Why the notification failed. Only set when `status` is `failed`.",
            "type": [
              "string",
              "null"
            ],
            "enum": [
              "MailboxFull",
              "AddressNotFound",
              "AddressSuppressed",
              "ContentRejected",
              "MarkedAsSpam",
              "NotOnWhatsapp",
              "RecipientRestricted",
              "NotSent",
              "Unknown"
            ],
            "example": "MailboxFull"
          },
          "error_message": {
            "description": "Human-readable, localized explanation of `error_code`. Only set when `status` is `failed`.",
            "type": [
              "string",
              "null"
            ],
            "example": "A caixa de entrada do destinatário está cheia. Peça ao signatário para liberar espaço ou use outro e-mail."
          },
          "sent_at": {
            "type": [
              "string",
              "null"
            ],
            "format": "date-time",
            "example": "2026-07-07T12:00:00Z"
          },
          "failed_at": {
            "type": [
              "string",
              "null"
            ],
            "format": "date-time",
            "example": "2026-07-07T12:05:00Z"
          }
        },
        "type": "object"
      },
      "AssignmentItem": {
        "properties": {
          "id": {
            "type": "string"
          },
          "page": {
            "oneOf": [
              {
                "$ref": "#/components/schemas/DocumentPage"
              },
              {
                "type": "null"
              }
            ]
          },
          "signer": {
            "description": "Signer responsible for this item.",
            "type": "object"
          },
          "field": {
            "description": "Field definition associated with the item.",
            "type": [
              "object",
              "null"
            ]
          },
          "display_settings": {
            "description": "Rendering metadata for the item. Collect items use the DisplaySettings schema; virtual and legacy items may return an empty or non-object value."
          },
          "value": {
            "description": "Captured value when completed; `null` until then."
          },
          "completed": {
            "type": "boolean"
          }
        },
        "type": "object"
      },
      "AssignmentSummary": {
        "properties": {
          "signer_count": {
            "type": "integer"
          },
          "completed_count": {
            "type": "integer"
          },
          "signers": {
            "type": "array",
            "items": {
              "type": "object"
            }
          }
        },
        "type": "object"
      },
      "Assignment": {
        "description": "A request for signers to sign a document.",
        "properties": {
          "resource": {
            "type": "string",
            "example": "assignment"
          },
          "id": {
            "type": "string",
            "example": "615606ef81d199996981dbce"
          },
          "document_id": {
            "description": "The document this assignment belongs to. `null` only if the document was deleted.",
            "type": [
              "string",
              "null"
            ],
            "example": "615601fab04c0a3147bb1246"
          },
          "created_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-06-03T03:54:16Z"
          },
          "sender_email": {
            "description": "Email of the user who sent the document; null once that user has deleted their account.",
            "type": [
              "string",
              "null"
            ],
            "format": "email",
            "example": "sender@example.com"
          },
          "method": {
            "type": "string",
            "enum": [
              "virtual",
              "collect"
            ],
            "example": "virtual"
          },
          "expires_at": {
            "type": [
              "string",
              "null"
            ],
            "format": "date-time",
            "example": null
          },
          "message": {
            "type": [
              "string",
              "null"
            ]
          },
          "signers": {
            "type": "array",
            "items": {
              "$ref": "#/components/schemas/AssignmentSigner"
            }
          },
          "copy_receivers": {
            "type": "array",
            "items": {
              "type": "object"
            }
          },
          "items": {
            "type": "array",
            "items": {
              "$ref": "#/components/schemas/AssignmentItem"
            }
          },
          "summary": {
            "$ref": "#/components/schemas/AssignmentSummary"
          },
          "signing_urls": {
            "type": "array",
            "items": {
              "$ref": "#/components/schemas/SigningUrl"
            }
          }
        },
        "type": "object"
      },
      "EnvelopeCreateRequest": {
        "description": "An envelope as sent: on creation, and to estimate what creating it would cost.",
        "required": [
          "name",
          "signers",
          "documents"
        ],
        "properties": {
          "name": {
            "description": "What the invitation calls what is being signed, in place of a document's name. `<` is refused (`400`).",
            "type": "string",
            "maxLength": 255,
            "minLength": 1,
            "example": "Contrato de prestação de serviços"
          },
          "message": {
            "description": "Optional message sent to every signer; their invitation adds the names of the documents they are on.",
            "type": [
              "string",
              "null"
            ],
            "example": "Segue o contrato e seus anexos."
          },
          "max_total_credits": {
            "description": "Optional ceiling on what this request may spend, as on an assignment: refused with `409` and code `CreditCeilingExceeded`, nothing created or charged, when the envelope's total credits are above it. A value that is not a number of credits, zero or more, answers `400`; numeric text counts, as a form-encoded body sends it. The estimate ignores it.",
            "type": "number",
            "minimum": 0,
            "example": 1.5
          },
          "signers": {
            "description": "Every signer of the envelope, once, by id: up to 20. They all sign at once: an envelope takes no `step`.",
            "type": "array",
            "items": {
              "required": [
                "id"
              ],
              "properties": {
                "id": {
                  "description": "An existing signer's ID.",
                  "type": "string",
                  "minLength": 1,
                  "example": "fa8c140cb49b79f940aab95fddd"
                },
                "verification_method": {
                  "description": "As on an assignment. A `DigitalCertificate` signer signs only virtual documents, alone on each, each with their certificate, and is charged on each. An `IdDocumentFaceMatchBasic` signer checks their identity once for the whole envelope, and is charged for it once.",
                  "type": "string",
                  "enum": [
                    "Email",
                    "Whatsapp",
                    "DigitalCertificate",
                    "IdDocumentFaceMatchBasic"
                  ],
                  "example": "Email"
                },
                "notification_methods": {
                  "description": "As on an assignment: one method, inferred from `verification_method` when left out.",
                  "type": "array",
                  "items": {
                    "type": "string",
                    "enum": [
                      "Email",
                      "Whatsapp"
                    ]
                  },
                  "maxItems": 1,
                  "example": [
                    "Email"
                  ]
                },
                "embedded": {
                  "description": "As on an assignment: the signer signs inside your app, through an embed session, on every document of the envelope, the ones added later too. Their `signing_urls` entry has no link (`url` is `null`). An embed session minted on any of their documents still waiting for them lists the documents of the envelope they still have to sign, and the code their invitation carries verifies them there.",
                  "type": "boolean",
                  "default": false
                }
              },
              "type": "object"
            },
            "maxItems": 20,
            "minItems": 1
          },
          "documents": {
            "description": "The documents, in the order the envelope lists them: up to 20.",
            "type": "array",
            "items": {
              "$ref": "#/components/schemas/EnvelopeDocumentRequest"
            },
            "maxItems": 20,
            "minItems": 1
          }
        },
        "type": "object"
      },
      "EnvelopeDocumentRequest": {
        "description": "A document of an envelope, as sent on creation (`documents[]`) and when added.",
        "required": [
          "id",
          "signer_ids"
        ],
        "properties": {
          "id": {
            "description": "A `metadata_ready` document of the workspace with no assignment.",
            "type": "string",
            "minLength": 1,
            "example": "615601fab04c0a3147bb1246"
          },
          "method": {
            "description": "As on an assignment: `virtual` (the default), signed on its virtual page, or `collect`, signed on the fields placed in `entries`.",
            "type": "string",
            "default": "virtual",
            "enum": [
              "virtual",
              "collect"
            ]
          },
          "entries": {
            "description": "Required for `collect`, as on an assignment: the fields placed on each page of the document. Its signers are the ones on its fields, exactly its `signer_ids`.",
            "type": "array",
            "items": {
              "required": [
                "page_id",
                "fields"
              ],
              "properties": {
                "page_id": {
                  "description": "A page of the document.",
                  "type": "string"
                },
                "fields": {
                  "type": "array",
                  "items": {
                    "required": [
                      "signer_id",
                      "field_id",
                      "display_settings"
                    ],
                    "properties": {
                      "signer_id": {
                        "description": "A signer of the document.",
                        "type": "string"
                      },
                      "field_id": {
                        "description": "A field definition of the workspace.",
                        "type": "string"
                      },
                      "display_settings": {
                        "$ref": "#/components/schemas/DisplaySettings"
                      }
                    },
                    "type": "object"
                  },
                  "minItems": 1
                }
              },
              "type": "object"
            },
            "minItems": 1
          },
          "signer_ids": {
            "description": "The signers of this document, each listed once: on creation, signers listed in `signers`; when added, signers of the envelope's documents.",
            "type": "array",
            "items": {
              "type": "string"
            },
            "minItems": 1,
            "example": [
              "fa8c140cb49b79f940aab95fddd"
            ]
          },
          "expires_at": {
            "description": "This document's expiration, read as on an assignment, at least one hour in the future. Text is read as a date: ISO 8601 is the format to send (a UTC offset in the value is honored, a value without one is read as UTC, and a date alone means 23:59 UTC that day), and text that is a number is Unix time. A number is Unix time in seconds, its fraction dropped. Null, 0, an empty text or leaving it out means no expiration; a value that can't be read as a date answers `400`.",
            "example": "2026-12-31T23:59:59Z",
            "oneOf": [
              {
                "type": "string"
              },
              {
                "type": "number"
              },
              {
                "type": "null"
              }
            ]
          },
          "copy_receiver_ids": {
            "description": "Signer IDs that only receive a copy of this document, as on an assignment.",
            "type": "array",
            "items": {
              "type": "string"
            },
            "example": [
              "cr8c140cb49b79f940aab95fddd"
            ]
          }
        },
        "type": "object"
      },
      "DocumentEnvelope": {
        "description": "Documents sent together to the same signers. Each document keeps its own assignment, status, certificate and downloads.",
        "required": [
          "id",
          "name",
          "message",
          "documents",
          "counts",
          "signing_urls",
          "created_at"
        ],
        "properties": {
          "resource": {
            "description": "Present in single-resource responses.",
            "type": "string",
            "example": "envelope"
          },
          "id": {
            "type": "string",
            "example": "1a2f3c4d5e6f708192a3b4c5d6e"
          },
          "name": {
            "type": "string",
            "example": "Contrato de prestação de serviços"
          },
          "message": {
            "type": [
              "string",
              "null"
            ],
            "example": "Segue o contrato e seus anexos."
          },
          "documents": {
            "description": "The envelope's documents, in the order it lists them.",
            "type": "array",
            "items": {
              "required": [
                "id",
                "position",
                "name",
                "status",
                "artifacts",
                "signers"
              ],
              "properties": {
                "id": {
                  "type": "string",
                  "example": "615601fab04c0a3147bb1246"
                },
                "position": {
                  "description": "The document's place in the envelope's order, from 1.",
                  "type": "integer",
                  "minimum": 1,
                  "example": 1
                },
                "name": {
                  "type": "string",
                  "example": "contrato.pdf"
                },
                "status": {
                  "description": "Status code — see GET /v1/documents/statuses.",
                  "type": "string",
                  "example": "pending_signature"
                },
                "artifacts": {
                  "description": "The document's files, as its own read (`GET /v1/documents/{documentId}`) gives them: always `original`, plus `thumbnail` once one exists, and for a certificated document `certificated`, `certificate-page`, `bundle` and, when signed with a digital certificate, `pades`. In the envelope list (`GET /v1/accounts/{accountId}/envelopes`) only `thumbnail`, given without checking that it exists: its URL answers `404` until the thumbnail is generated.",
                  "type": "object",
                  "example": {
                    "original": "https://api.assinafy.com.br/v1/documents/615601fab04c0a3147bb1246/download/original",
                    "thumbnail": "https://api.assinafy.com.br/v1/documents/615601fab04c0a3147bb1246/thumbnail"
                  },
                  "additionalProperties": {
                    "type": "string"
                  }
                },
                "signers": {
                  "description": "The document's signers: a signer appears only on the documents they are on.",
                  "type": "array",
                  "items": {
                    "required": [
                      "id",
                      "full_name",
                      "email",
                      "whatsapp_phone_number",
                      "completed",
                      "signed_at"
                    ],
                    "properties": {
                      "id": {
                        "type": "string",
                        "example": "fa8c140cb49b79f940aab95fddd"
                      },
                      "full_name": {
                        "type": "string",
                        "example": "Ana Souza"
                      },
                      "email": {
                        "type": [
                          "string",
                          "null"
                        ],
                        "example": "ana@example.com"
                      },
                      "whatsapp_phone_number": {
                        "type": [
                          "string",
                          "null"
                        ],
                        "example": "+5548999990001"
                      },
                      "completed": {
                        "description": "Whether they have signed this document.",
                        "type": "boolean",
                        "example": false
                      },
                      "signed_at": {
                        "description": "When they signed this document; `null` until they do.",
                        "type": [
                          "string",
                          "null"
                        ],
                        "format": "date-time",
                        "example": "2026-11-06T14:30:00Z"
                      }
                    },
                    "type": "object"
                  }
                }
              },
              "type": "object"
            }
          },
          "counts": {
            "description": "How many of its documents are in each status (status code => count); only the statuses present. An envelope has no status of its own. `null` once it lists no document: every one deleted or taken out.",
            "type": [
              "object",
              "null"
            ],
            "example": {
              "certificated": 1,
              "pending_signature": 2
            },
            "additionalProperties": {
              "type": "integer"
            }
          },
          "signing_urls": {
            "description": "One entry per signer, as on an assignment (`SigningUrl`): the link of the document their invitation is sent from while it waits for them, else of their first document that still does (the e-mail page asks that document for the invitation again; any of their links opens the whole envelope).",
            "type": "array",
            "items": {
              "$ref": "#/components/schemas/SigningUrl"
            }
          },
          "created_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-11-04T12:00:00Z"
          }
        },
        "type": "object"
      },
      "CostEstimateBreakdownItem": {
        "properties": {
          "code": {
            "type": "string",
            "example": "NotificationWhatsapp"
          },
          "name": {
            "type": "string",
            "example": "Whatsapp Notification"
          },
          "cost": {
            "type": "number",
            "example": 0.9
          },
          "quantity": {
            "type": "integer",
            "example": 2
          },
          "unit_cost": {
            "type": "number",
            "example": 0.45
          }
        },
        "type": "object"
      },
      "CostEstimate": {
        "description": "Cost breakdown for an assignment plus current account balances. `credits` is the sum of `breakdown` (notifications, digital-certificate signatures and identity checks). `total_credits` is what the send will take from the credit balance: `credits`, plus `extra_document_cost` when `needs_extra_document` is true. The extra document is not a `breakdown` line.",
        "required": [
          "documents",
          "credits",
          "needs_extra_document",
          "extra_document_cost",
          "total_credits",
          "breakdown",
          "document_balance",
          "credit_balance",
          "has_sufficient_resources",
          "blocking_reason",
          "message"
        ],
        "properties": {
          "documents": {
            "description": "Documents consumed: 1, for an assignment as for an envelope, whatever its documents.",
            "type": "integer",
            "example": 1
          },
          "credits": {
            "description": "Sum of the `breakdown` costs: notifications, digital-certificate signatures and identity checks. Does not include the extra document.",
            "type": "number",
            "example": 0.9
          },
          "needs_extra_document": {
            "description": "True when the plan's document allowance is exhausted and an extra document will be charged from credits.",
            "type": "boolean",
            "example": true
          },
          "extra_document_cost": {
            "description": "Credits for one extra document. Charged only when `needs_extra_document` is true.",
            "type": "number",
            "example": 1
          },
          "total_credits": {
            "description": "Credits the send will consume: `credits`, plus `extra_document_cost` when `needs_extra_document` is true.",
            "type": "number",
            "example": 1.9
          },
          "breakdown": {
            "type": "array",
            "items": {
              "$ref": "#/components/schemas/CostEstimateBreakdownItem"
            }
          },
          "document_balance": {
            "description": "Documents left in the plan. `null` when the account's documents are not limited.",
            "type": [
              "number",
              "null"
            ],
            "example": 0
          },
          "credit_balance": {
            "description": "Credits available. `null` when the account's credits are not limited.",
            "type": [
              "number",
              "null"
            ],
            "example": 10
          },
          "has_sufficient_resources": {
            "description": "Whether creating it would pass its balance checks now: the checks creation itself makes, so a contract that allows overage passes with a low `document_balance` or `credit_balance`.",
            "type": "boolean",
            "example": true
          },
          "blocking_reason": {
            "type": [
              "string",
              "null"
            ],
            "enum": [
              "PendingPayment",
              "InsufficientDocuments",
              "InsufficientCredits"
            ],
            "example": null
          },
          "message": {
            "type": [
              "string",
              "null"
            ]
          }
        },
        "type": "object"
      },
      "ResendCostEstimate": {
        "description": "Credits a reminder to one signer will take, and whether the account can pay for it. A reminder uses no document.",
        "properties": {
          "total": {
            "description": "Sum of the `breakdown` costs.",
            "type": "number",
            "example": 0.45
          },
          "breakdown": {
            "type": "array",
            "items": {
              "properties": {
                "code": {
                  "type": "string",
                  "example": "NotificationWhatsappResend"
                },
                "name": {
                  "type": "string",
                  "example": "Whatsapp Notification Resend"
                },
                "cost": {
                  "type": "number",
                  "example": 0.45
                }
              },
              "type": "object"
            }
          },
          "credit_balance": {
            "description": "Credits available. `null` when the account's credits are not limited.",
            "type": [
              "number",
              "null"
            ],
            "example": 10
          },
          "has_sufficient_credits": {
            "description": "Whether the reminder would pass its credit check now: the check the reminder itself makes, so a contract that allows overage passes with a low `credit_balance`, and a plan whose payment is pending doesn't, whatever the balance.",
            "type": "boolean",
            "example": true
          }
        },
        "type": "object"
      },
      "TemplateFieldPlacement": {
        "properties": {
          "id": {
            "type": "string"
          },
          "field_id": {
            "type": "string"
          },
          "role_id": {
            "type": "string"
          },
          "label": {
            "type": "string"
          },
          "display_settings": {
            "description": "Rendering metadata for the placement."
          },
          "created_at": {
            "type": "string",
            "format": "date-time"
          },
          "updated_at": {
            "type": "string",
            "format": "date-time"
          }
        },
        "type": "object"
      },
      "TemplatePage": {
        "properties": {
          "id": {
            "type": "string"
          },
          "number": {
            "type": "integer",
            "example": 1
          },
          "height": {
            "type": "integer",
            "example": 2100
          },
          "width": {
            "type": "integer",
            "example": 1275
          },
          "download_url": {
            "type": "string"
          },
          "fields": {
            "type": "array",
            "items": {
              "$ref": "#/components/schemas/TemplateFieldPlacement"
            }
          }
        },
        "type": "object"
      },
      "TemplateRole": {
        "properties": {
          "id": {
            "type": "string"
          },
          "name": {
            "type": "string",
            "example": "Editor"
          },
          "assignment_type": {
            "type": "string",
            "example": "Editor"
          },
          "created_at": {
            "type": "string",
            "format": "date-time"
          },
          "updated_at": {
            "type": "string",
            "format": "date-time"
          }
        },
        "type": "object"
      },
      "Template": {
        "description": "A reusable document template.",
        "properties": {
          "resource": {
            "type": "string",
            "example": "template"
          },
          "id": {
            "type": "string",
            "example": "fa88b732db84d01427d4cdd1092"
          },
          "name": {
            "type": "string",
            "example": "template.pdf"
          },
          "document_name": {
            "description": "Default name for documents created from this template.",
            "type": [
              "string",
              "null"
            ]
          },
          "message": {
            "description": "Default invitation message.",
            "type": [
              "string",
              "null"
            ]
          },
          "status": {
            "description": "One of uploading, uploaded, processing, ready, failed.",
            "type": "string",
            "example": "ready"
          },
          "pages": {
            "type": "array",
            "items": {
              "$ref": "#/components/schemas/TemplatePage"
            }
          },
          "roles": {
            "type": "array",
            "items": {
              "$ref": "#/components/schemas/TemplateRole"
            }
          },
          "tags": {
            "type": "array",
            "items": {
              "properties": {
                "id": {
                  "type": "string"
                },
                "name": {
                  "type": "string"
                }
              },
              "type": "object"
            }
          },
          "default_document_tags": {
            "description": "Applied to documents created from this template; only returned by the single-template endpoint.",
            "type": "array",
            "items": {
              "properties": {
                "id": {
                  "type": "string"
                },
                "name": {
                  "type": "string"
                }
              },
              "type": "object"
            }
          },
          "created_at": {
            "type": "string",
            "format": "date-time"
          },
          "updated_at": {
            "type": "string",
            "format": "date-time"
          }
        },
        "type": "object"
      },
      "Balance": {
        "description": "A document or credit balance for the current billing cycle.",
        "properties": {
          "balance": {
            "description": "Remaining quantity; null for unlimited.",
            "type": [
              "number",
              "null"
            ],
            "example": 100
          },
          "limit": {
            "description": "Allowance per cycle/pool; null when not cycle-limited or unlimited.",
            "type": [
              "number",
              "null"
            ],
            "example": 100
          },
          "spent": {
            "description": "Quantity consumed in the current cycle.",
            "type": "number",
            "example": 0
          }
        },
        "type": "object"
      },
      "AccountUsage": {
        "description": "The workspace's plan and what is left of it.",
        "properties": {
          "plan": {
            "description": "The plan's name. `null` when the subscription has no plan or its plan was retired.",
            "type": [
              "string",
              "null"
            ],
            "example": "Pro"
          },
          "documents_renewal_date": {
            "description": "When the document allowance next renews. On a custom contract, the next boundary of the contract's billing day. `null` while the subscription awaits its first payment, or on a contract with no billing day.",
            "type": [
              "string",
              "null"
            ],
            "format": "date",
            "example": "2026-12-01"
          },
          "balances": {
            "description": "Document and credit balances, as in **Get current subscription**.",
            "properties": {
              "documents": {
                "$ref": "#/components/schemas/Balance"
              },
              "credits": {
                "$ref": "#/components/schemas/Balance"
              }
            },
            "type": "object"
          }
        },
        "type": "object"
      },
      "WebhookSubscription": {
        "description": "An account's webhook subscription configuration.",
        "properties": {
          "events": {
            "description": "Event types subscribed for delivery.",
            "type": "array",
            "items": {
              "type": "string"
            },
            "example": [
              "document_ready",
              "document_prepared"
            ]
          },
          "is_active": {
            "description": "Whether webhook delivery is active.",
            "type": "boolean",
            "example": true
          },
          "is_held": {
            "description": "Whether deliveries are paused after 10 failures in a row (see **Delivery contract**).",
            "type": "boolean",
            "example": false
          },
          "held_since": {
            "description": "When the pause began (Unix timestamp, seconds); `null` while deliveries run.",
            "type": [
              "integer",
              "null"
            ],
            "example": null
          },
          "url": {
            "description": "Webhook endpoint URL; `https` is required for a new or changed URL.",
            "type": [
              "string",
              "null"
            ],
            "example": "https://example.com?test=1"
          },
          "email": {
            "description": "Contact email for delivery notices.",
            "type": [
              "string",
              "null"
            ],
            "example": "person@example.com"
          },
          "updated_at": {
            "type": [
              "string",
              "null"
            ],
            "format": "date-time",
            "example": "2023-05-10T14:58:24Z"
          }
        },
        "type": "object"
      },
      "WebhookEndpoint": {
        "description": "A URL that receives the account's webhook events. Every active endpoint subscribed to an event receives it.",
        "properties": {
          "resource": {
            "description": "Always `activity_event_subscription` in single-resource responses.",
            "type": "string",
            "example": "activity_event_subscription"
          },
          "id": {
            "description": "Endpoint ID.",
            "type": "string",
            "example": "65f1c2a9b3e4d5f60718293a4b5c6d7e"
          },
          "name": {
            "description": "Label to tell endpoints apart.",
            "type": [
              "string",
              "null"
            ],
            "example": "ERP"
          },
          "url": {
            "description": "URL that receives the events; `https` is required for a new or changed URL.",
            "type": "string",
            "format": "uri",
            "example": "https://example.com/webhooks/assinafy"
          },
          "email": {
            "description": "Contact email for delivery-failure notices.",
            "type": "string",
            "format": "email",
            "example": "ops@example.com"
          },
          "events": {
            "description": "Event types delivered to this endpoint (see **List webhook event types**).",
            "type": "array",
            "items": {
              "type": "string"
            },
            "example": [
              "document_ready",
              "signer_signed_document"
            ]
          },
          "is_active": {
            "description": "Whether events are delivered to this endpoint.",
            "type": "boolean",
            "example": true
          },
          "is_held": {
            "description": "Whether deliveries are paused after 10 failures in a row: we probe the endpoint on a growing schedule and deliver what it missed when it answers (see **Delivery contract**). Editing the URL, or sending `is_active: true`, lifts the pause.",
            "type": "boolean",
            "example": false
          },
          "held_since": {
            "description": "When the pause began (Unix timestamp, seconds); `null` while deliveries run.",
            "type": [
              "integer",
              "null"
            ],
            "example": null
          },
          "signing_enabled": {
            "description": "Whether deliveries carry a `webhook-signature` header (see **Webhook Payloads → Verifying signatures**).",
            "type": "boolean",
            "example": true
          },
          "created_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-10-01T12:00:00Z"
          },
          "updated_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-10-01T12:00:00Z"
          }
        },
        "type": "object"
      },
      "EmbedOrigin": {
        "description": "An origin allowed to embed the workspace's signing pages.",
        "properties": {
          "id": {
            "description": "Embed origin ID.",
            "type": "string",
            "example": "6f1c2a9e0b"
          },
          "origin": {
            "description": "Exact `scheme://host[:port]`.",
            "type": "string",
            "example": "https://app.example.com"
          },
          "created_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-11-04T12:05:00Z"
          }
        },
        "type": "object"
      },
      "EmbedSession": {
        "description": "A single-use embed session for an embedded signer.",
        "properties": {
          "url": {
            "description": "Load this URL in an iframe within 5 minutes. Opening it spends the token.",
            "type": "string",
            "example": "https://app.assinafy.com.br/embed/3f2c9a1e5b7d4c68a0e1f2b3c4d5e6f708192a3b4c5d6e7f8091a2b3c4d5e6f7"
          },
          "expires_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-11-04T12:05:00Z"
          }
        },
        "type": "object"
      },
      "EmbedSessionExchange": {
        "description": "What the embed page gets for a session token.",
        "properties": {
          "signer_access_code": {
            "description": "The signer's access code, used by the embed page only.",
            "type": "string"
          },
          "signer_id": {
            "type": "string",
            "example": "6f1c2a9e0b"
          },
          "document_id": {
            "type": "string",
            "example": "5e0b1c2a9f"
          },
          "verification_method": {
            "type": "string",
            "example": "Email"
          },
          "embed_origins": {
            "type": "array",
            "items": {
              "type": "string"
            },
            "example": [
              "https://app.example.com"
            ]
          }
        },
        "type": "object"
      },
      "WebhookEndpointSecret": {
        "description": "An endpoint's signing secret.",
        "properties": {
          "secret": {
            "description": "Standard Webhooks secret: `whsec_` followed by the base64-encoded key.",
            "type": "string",
            "example": "whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw"
          }
        },
        "type": "object"
      },
      "WebhookDispatch": {
        "description": "A single webhook delivery-history entry.",
        "properties": {
          "resource": {
            "description": "Always `activity_dispatching_history` in single-resource responses.",
            "type": "string",
            "example": "activity_dispatching_history"
          },
          "id": {
            "description": "Dispatch entry ID.",
            "type": "string",
            "example": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
          },
          "event": {
            "description": "Event type that triggered the dispatch.",
            "type": "string",
            "example": "document_ready"
          },
          "activity_id": {
            "description": "Internal activity ID associated with the dispatch.",
            "type": "integer",
            "example": 456
          },
          "endpoint_id": {
            "description": "ID of the webhook endpoint the delivery was sent to (`null` once that endpoint is deleted).",
            "type": [
              "string",
              "null"
            ],
            "example": "65f1c2a9b3e4d5f60718293a4b5c6d7e"
          },
          "endpoint": {
            "description": "URL that received the request.",
            "type": [
              "string",
              "null"
            ],
            "example": "https://example.com/webhook"
          },
          "payload": {
            "description": "JSON payload sent to the endpoint.",
            "type": [
              "object",
              "null"
            ]
          },
          "delivered": {
            "description": "Whether delivery succeeded.",
            "type": "boolean",
            "example": true
          },
          "webhook_id": {
            "description": "The `webhook-id` header of the request: the same on every attempt of this event to this endpoint.",
            "type": [
              "string",
              "null"
            ],
            "example": "9f86d081884c7d659a2feaa0c55ad015"
          },
          "attempt": {
            "description": "Which attempt of this event to this endpoint the entry records, counted from 1.",
            "type": [
              "integer",
              "null"
            ],
            "example": 2
          },
          "kind": {
            "description": "`scheduled`: the delivery of the event as it happened; `probe`: the one delivery we send to a paused endpoint to see whether it answers; `manual`: asked for through **Retry webhook delivery** or **Send a test delivery**.",
            "type": "string",
            "enum": [
              "scheduled",
              "probe",
              "manual"
            ],
            "example": "scheduled"
          },
          "will_retry": {
            "description": "Whether another attempt follows this one. `false` on a delivered entry and on the last attempt of a failed one.",
            "type": "boolean",
            "example": true
          },
          "duration_ms": {
            "description": "How long the endpoint took to answer or fail, in milliseconds. `null` when no request was sent (a host that does not resolve, an address that is not public) and on entries recorded before it was measured.",
            "type": [
              "integer",
              "null"
            ],
            "example": 184
          },
          "http_status": {
            "description": "HTTP status returned (null if connection failed).",
            "type": [
              "integer",
              "null"
            ],
            "example": 200
          },
          "response_body": {
            "description": "Endpoint response body, truncated to 2000 chars.",
            "type": [
              "string",
              "null"
            ],
            "example": "OK"
          },
          "error": {
            "description": "Delivery error message, if any.",
            "type": [
              "string",
              "null"
            ],
            "example": null
          },
          "created_at": {
            "type": "string",
            "format": "date-time",
            "example": "2024-01-15T10:30:00Z"
          },
          "updated_at": {
            "type": "string",
            "format": "date-time",
            "example": "2024-01-15T10:30:00Z"
          }
        },
        "type": "object"
      },
      "WebhookEvent": {
        "description": "Body of every webhook delivery. Timestamps in the body (`created_at`, and the `*_at` fields of `subject`/`object`) are Unix timestamps in seconds, not the ISO strings the REST responses use. `subject` and `object` are serialized from their current state when the delivery is sent.",
        "required": [
          "id",
          "event",
          "created_at",
          "subject",
          "object",
          "account_id"
        ],
        "properties": {
          "id": {
            "description": "ID of the activity that produced the event.",
            "type": "integer",
            "example": 184467
          },
          "event": {
            "description": "Event type. See **List webhook event types**; `webhook_test` is sent only by **Send a test delivery**.",
            "type": "string",
            "example": "signer_viewed_document"
          },
          "message": {
            "description": "Reserved; currently always `null`.",
            "type": [
              "string",
              "null"
            ],
            "example": null
          },
          "payload": {
            "description": "Event-specific parameters; keys vary per event.",
            "type": [
              "object",
              "null"
            ],
            "additionalProperties": true
          },
          "origin": {
            "description": "Where the action came from, when it was triggered by a request.",
            "properties": {
              "ip": {
                "type": "string",
                "example": "203.0.113.7"
              },
              "user-agent": {
                "type": "string",
                "example": "Mozilla/5.0"
              }
            },
            "type": [
              "object",
              "null"
            ]
          },
          "created_at": {
            "description": "When the event was recorded (Unix timestamp, seconds).",
            "type": "integer",
            "example": 1790000000
          },
          "subject": {
            "description": "Who performed the action: a `User`, `Signer` or `Account` (the endpoint itself in a `webhook_test`), plus a `type` property naming it.",
            "type": "object",
            "additionalProperties": true
          },
          "object": {
            "description": "What the action was performed on: a `Document`, `Signer` or `Template` with its relations expanded (the endpoint itself in a `webhook_test`), plus a `type` property naming it.",
            "type": "object",
            "additionalProperties": true
          },
          "account_id": {
            "description": "ID of the account that owns the event.",
            "type": "string",
            "example": "65f1c2a9b3e4d5f6"
          }
        },
        "type": "object"
      },
      "WebhookEventType": {
        "description": "A subscribable webhook event type.",
        "properties": {
          "id": {
            "description": "Event type code.",
            "type": "string",
            "example": "document_ready"
          },
          "description": {
            "description": "When the event is triggered.",
            "type": "string",
            "example": "Triggered when the last Signer of the assignment signs the Document."
          }
        },
        "type": "object"
      },
      "AccountTheme": {
        "description": "An account's branding theme.",
        "properties": {
          "account_name": {
            "type": "string",
            "example": "Account Name"
          },
          "primary_color": {
            "description": "Button background: hex color without leading `#`. The platform default when the account set none.",
            "type": "string",
            "example": "aabbcc"
          },
          "secondary_color": {
            "description": "Button text: hex color without leading `#`. The platform default when the account set none.",
            "type": "string",
            "example": "ffffff"
          },
          "logo": {
            "description": "Absolute URL of the logo for the caller: `/v1/accounts/{accountId}/logo` for workspace members, `/v1/sign/logo` (append `signer-access-code`) for signers. `null` when the account has no logo.",
            "type": [
              "string",
              "null"
            ],
            "example": "https://api.assinafy.com.br/v1/accounts/1a/logo"
          }
        },
        "type": "object"
      },
      "FieldType": {
        "description": "A supported field/validation type.",
        "properties": {
          "type": {
            "type": "string",
            "example": "cpf"
          },
          "name": {
            "type": "string",
            "example": "CPF"
          }
        },
        "type": "object"
      },
      "FieldValidation": {
        "description": "The result of validating a value against a field definition.",
        "properties": {
          "type": {
            "description": "The field's validation type.",
            "type": "string",
            "example": "cpf"
          },
          "success": {
            "type": "boolean",
            "example": true
          },
          "error_message": {
            "description": "Empty when valid.",
            "type": "string",
            "example": ""
          }
        },
        "type": "object"
      },
      "FieldValidationResult": {
        "description": "A per-field result from a multi-field validation.",
        "properties": {
          "field_id": {
            "type": "string",
            "example": "63488ffb7adf435aba319787"
          },
          "type": {
            "type": "string",
            "example": "cpf"
          },
          "success": {
            "type": "boolean",
            "example": false
          },
          "error_message": {
            "type": "string",
            "example": "Invalid CPF."
          }
        },
        "type": "object"
      },
      "DocumentVerification": {
        "description": "The verification result for a document looked up by signature hash. When not verified, most fields are null and `is_valid` is false.",
        "properties": {
          "hash": {
            "type": "string",
            "example": "FE32EDDADE7CBDDCBB934E7402047450B0E59C02"
          },
          "id": {
            "type": [
              "string",
              "null"
            ],
            "example": "63ddb172402799bfc991d10d"
          },
          "agreement_code": {
            "description": "Agreement code printed on the document certificate.",
            "type": [
              "string",
              "null"
            ],
            "example": "550E8400-E29B-41D4-A716-446655440000"
          },
          "status": {
            "type": [
              "string",
              "null"
            ],
            "example": "certificated"
          },
          "page_count": {
            "type": [
              "string",
              "null"
            ],
            "example": "1"
          },
          "signer_count": {
            "type": [
              "string",
              "null"
            ],
            "example": "1"
          },
          "completed_count": {
            "type": [
              "integer",
              "null"
            ],
            "example": 1
          },
          "completed_at": {
            "description": "When the last signature was made.",
            "type": [
              "string",
              "null"
            ],
            "format": "date-time",
            "example": "2023-01-27T19:27:44Z"
          },
          "certificated_at": {
            "description": "When certification was first recorded by the document_ready event. Null when no certification event is available, including some historical documents.",
            "type": [
              "string",
              "null"
            ],
            "format": "date-time",
            "example": "2023-01-27T19:27:45Z"
          },
          "verified_at": {
            "description": "When this check ran (the time of the request). It says nothing about when the document was signed or certified.",
            "type": "string",
            "format": "date-time",
            "example": "2023-01-27T19:27:46Z"
          },
          "is_valid": {
            "type": "boolean",
            "example": true
          },
          "message": {
            "description": "Reason when not valid.",
            "type": "string",
            "example": ""
          }
        },
        "type": "object"
      },
      "DocumentActivity": {
        "description": "A document activity/audit event.",
        "properties": {
          "id": {
            "type": "integer",
            "example": 4
          },
          "event": {
            "description": "Event type code.",
            "type": "string",
            "example": "assignment_created"
          },
          "message": {
            "type": "string",
            "example": "Assignment created by John Smith."
          },
          "payload": {
            "description": "`[]` for the events that record nothing (`document_uploaded`, `document_metadata_ready`, `document_prepared`). Otherwise an event-specific snapshot taken when the event happened, with the same keys as the webhook of that event (see **Webhook Payloads**). The common ones: `user_name` (`assignment_created`, `user_rejected_document`); `signer_full_name` (every `signer_*` event and `signature_requested`); `signer_email`, `signer_whatsapp_phone_number` and `notification_method` (`signature_requested`); `reviewer_name` (identity review decisions); `error_message` (`document_processing_failed`). `message` already carries the actor's name, translated, so clients can show it as is. A `*_at` key inside the payload is returned as an ISO 8601 string here, as in every API response, but as a Unix integer in webhook bodies.",
            "type": [
              "object",
              "array",
              "null"
            ]
          },
          "origin": {
            "description": "Request origin when available.",
            "properties": {
              "ip": {
                "description": "`null` when the request carried no client address.",
                "type": [
                  "string",
                  "null"
                ],
                "example": "172.19.0.1"
              },
              "user-agent": {
                "type": "string"
              }
            },
            "type": [
              "object",
              "null"
            ]
          },
          "created_at": {
            "type": "string",
            "format": "date-time",
            "example": "2022-07-19T19:28:13Z"
          }
        },
        "type": "object"
      },
      "EnvelopeActivity": {
        "description": "A document activity of an envelope's trail, naming its document.",
        "required": [
          "document_id",
          "document_name"
        ],
        "type": "object",
        "allOf": [
          {
            "$ref": "#/components/schemas/DocumentActivity"
          },
          {
            "properties": {
              "document_id": {
                "description": "The document the activity is about.",
                "type": "string",
                "example": "fe1c140cb49b79f940aab95fddd"
              },
              "document_name": {
                "type": "string",
                "example": "Contrato.pdf"
              }
            },
            "type": "object"
          }
        ]
      },
      "WhatsappNotification": {
        "description": "A rendered WhatsApp notification sent for an assignment, split into header/body/buttons as the signer would see them.",
        "properties": {
          "sent_at": {
            "description": "When the message was sent.",
            "type": "string",
            "format": "date-time",
            "example": "2026-07-07T12:00:00Z"
          },
          "header": {
            "type": "string",
            "example": "Documento para assinatura: Contrato de Servico"
          },
          "body": {
            "type": "string"
          },
          "buttons": {
            "type": "array",
            "items": {
              "properties": {
                "text": {
                  "description": "The button label shown to the signer.",
                  "type": "string",
                  "example": "Abrir documento"
                }
              },
              "type": "object"
            }
          },
          "phone_number": {
            "description": "Recipient phone (E.164).",
            "type": "string",
            "example": "+5511999990001"
          },
          "signer_id": {
            "type": "string",
            "example": "a51edaee68a7"
          }
        },
        "type": "object"
      },
      "AuthSession": {
        "description": "A JWT access token plus the authenticated user and the accounts they belong to.",
        "properties": {
          "access_token": {
            "type": "string",
            "example": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
          },
          "user": {
            "$ref": "#/components/schemas/AuthUser"
          },
          "accounts": {
            "type": "array",
            "items": {
              "$ref": "#/components/schemas/AuthAccount"
            }
          }
        },
        "type": "object"
      },
      "SignerIdentityReview": {
        "properties": {
          "id": {
            "type": "string",
            "example": "104c267f8e1e8c6855d7788b76a8"
          },
          "status": {
            "type": "string",
            "enum": [
              "Pending",
              "InReview",
              "Approved",
              "Declined",
              "Expired",
              "Abandoned"
            ],
            "example": "InReview"
          },
          "provider": {
            "type": "string",
            "enum": [
              "AssinafyVerify"
            ],
            "example": "AssinafyVerify"
          },
          "verification_method": {
            "type": "string",
            "enum": [
              "IdDocumentFaceMatchBasic"
            ],
            "example": "IdDocumentFaceMatchBasic"
          },
          "reason_codes": {
            "description": "Why the provider sent it to review, in its order. Unknown codes have the code as description.",
            "type": "array",
            "items": {
              "properties": {
                "code": {
                  "type": "string",
                  "example": "NAME_MISMATCH"
                },
                "description": {
                  "type": "string",
                  "example": "Nome no documento difere do cadastrado (similaridade 52%)"
                }
              },
              "type": "object"
            }
          },
          "evidence_available": {
            "type": "boolean",
            "example": true
          },
          "expected": {
            "description": "What the signer was registered with.",
            "properties": {
              "name": {
                "type": [
                  "string",
                  "null"
                ],
                "example": "Maria da Silva"
              },
              "cpf": {
                "type": [
                  "string",
                  "null"
                ],
                "example": "52998224725"
              }
            },
            "type": [
              "object",
              "null"
            ]
          },
          "document": {
            "description": "What was read from the document.",
            "properties": {
              "type": {
                "type": [
                  "string",
                  "null"
                ],
                "example": "CNH"
              },
              "name": {
                "type": [
                  "string",
                  "null"
                ],
                "example": "MARIA SILVA"
              },
              "cpf": {
                "type": [
                  "string",
                  "null"
                ],
                "example": "52998224725"
              }
            },
            "type": [
              "object",
              "null"
            ]
          },
          "matches": {
            "properties": {
              "cpf": {
                "type": [
                  "boolean",
                  "null"
                ],
                "example": true
              },
              "name": {
                "description": "Name similarity, 0-1.",
                "type": [
                  "number",
                  "null"
                ],
                "example": 0.5217
              },
              "birth_date": {
                "type": [
                  "boolean",
                  "null"
                ],
                "example": null
              }
            },
            "type": [
              "object",
              "null"
            ]
          },
          "checks": {
            "type": "array",
            "items": {
              "properties": {
                "name": {
                  "type": "string",
                  "example": "name"
                },
                "result": {
                  "description": "pass, review, skipped, or a failure result.",
                  "type": "string",
                  "example": "review"
                },
                "score": {
                  "type": [
                    "number",
                    "null"
                  ],
                  "example": 0.5217
                },
                "reasons": {
                  "type": "array",
                  "items": {
                    "type": "string"
                  },
                  "example": [
                    "NAME_MISMATCH"
                  ]
                }
              },
              "type": "object"
            }
          },
          "images": {
            "description": "The evidence images the reviewer can open with **Signer identity review image**, in display order: `document_front`, `document_back`, `document_pdf`, `face`. Only while `status` is `InReview`; empty once decided.",
            "type": "array",
            "items": {
              "type": "string",
              "enum": [
                "document_front",
                "document_back",
                "document_pdf",
                "face"
              ]
            },
            "example": [
              "document_front",
              "document_back",
              "face"
            ]
          },
          "ruleset_version": {
            "type": [
              "string",
              "null"
            ],
            "example": "2026-10-12.2"
          },
          "evidence_sha256": {
            "type": [
              "string",
              "null"
            ],
            "example": "9f2c0d6e4b1a8c3f5e7d9b0a2c4e6f8a1b3d5f7091e2c4a6b8d0f2e4a6c8e0b2"
          },
          "reviewed_by": {
            "properties": {
              "id": {
                "type": "string",
                "example": "Kx9pQ2"
              },
              "name": {
                "type": "string",
                "example": "Ana Souza"
              }
            },
            "type": [
              "object",
              "null"
            ]
          },
          "reviewed_at": {
            "type": [
              "string",
              "null"
            ],
            "format": "date-time",
            "example": "2026-10-13T21:05:00Z"
          },
          "review_note": {
            "type": [
              "string",
              "null"
            ],
            "example": "Conferido por videochamada."
          },
          "completed_at": {
            "type": [
              "string",
              "null"
            ],
            "format": "date-time",
            "example": "2026-10-13T21:05:00Z"
          }
        },
        "type": "object"
      },
      "DocumentStatsRow": {
        "description": "One period of the document-funnel KPI series. `period` is `YYYY-MM` (monthly) or `YYYY-MM-DD` (daily); series are zero-filled, no gaps. Signature requests come with two independent breakdowns: the `signature_requests_notification_*` counters split them by the channels the signer was notified on — a signer reached on more than one channel counts once per channel, so these add up to at least `signature_requests` — while the `signature_requests_verification_*` counters split them by how the signer's identity is verified, and since each request has exactly one verification method those four always add up to `signature_requests`.",
        "properties": {
          "period": {
            "description": "`YYYY-MM` (monthly) or `YYYY-MM-DD` (daily).",
            "type": "string",
            "example": "2026-06"
          },
          "documents_uploaded": {
            "type": "integer",
            "example": 42
          },
          "documents_sent": {
            "type": "integer",
            "example": 37
          },
          "signature_requests": {
            "type": "integer",
            "example": 61
          },
          "signature_requests_notification_email": {
            "description": "Requests notified by e-mail.",
            "type": "integer",
            "example": 55
          },
          "signature_requests_notification_whatsapp": {
            "description": "Requests notified by WhatsApp.",
            "type": "integer",
            "example": 18
          },
          "signature_requests_notification_bypass": {
            "description": "Requests with no notification sent (`Bypass`).",
            "type": "integer",
            "example": 3
          },
          "signature_requests_verification_email": {
            "description": "Requests verified by an e-mail token.",
            "type": "integer",
            "example": 48
          },
          "signature_requests_verification_whatsapp": {
            "description": "Requests verified by a WhatsApp token.",
            "type": "integer",
            "example": 6
          },
          "signature_requests_verification_bypass": {
            "description": "Requests signed without token verification (`Bypass`).",
            "type": "integer",
            "example": 3
          },
          "signature_requests_verification_digital_certificate": {
            "description": "Requests signed with the signer's own ICP-Brasil digital certificate.",
            "type": "integer",
            "example": 4
          },
          "signature_requests_viewed": {
            "description": "Signature requests whose document was first viewed during the period.",
            "type": "integer",
            "example": 44
          },
          "signature_requests_completed": {
            "description": "Signature requests completed by individual signers during the period.",
            "type": "integer",
            "example": 52
          },
          "documents_certified": {
            "type": "integer",
            "example": 30
          }
        },
        "type": "object"
      },
      "NotificationPreferences": {
        "description": "Owner-facing document notifications, keyed by notification type. `true` means the e-mail is sent.",
        "properties": {
          "DocumentCompleted": {
            "description": "Every signer has signed and the document is certified.",
            "type": "boolean",
            "example": true
          },
          "SignerDeclined": {
            "description": "A signer declined to sign.",
            "type": "boolean",
            "example": true
          },
          "DocumentCancelled": {
            "description": "The document was cancelled.",
            "type": "boolean",
            "example": true
          },
          "DocumentAboutToExpire": {
            "description": "The signature deadline is approaching.",
            "type": "boolean",
            "example": true
          },
          "DocumentExpired": {
            "description": "The signature deadline passed.",
            "type": "boolean",
            "example": true
          },
          "DocumentExpirationReset": {
            "description": "The signature deadline was extended.",
            "type": "boolean",
            "example": true
          },
          "DocumentProcessingFailed": {
            "description": "An uploaded document could not be processed.",
            "type": "boolean",
            "example": true
          },
          "TemplateProcessingFailed": {
            "description": "A template could not be processed.",
            "type": "boolean",
            "example": true
          },
          "SignerWhatsappFailed": {
            "description": "A WhatsApp notification to a signer could not be delivered.",
            "type": "boolean",
            "example": true
          },
          "ContractRenewalNotice": {
            "description": "A tracked contract entered its notice period: it is about to end or to renew.",
            "type": "boolean",
            "example": true
          },
          "ContractExpiring": {
            "description": "A tracked contract ends in 45 or in 30 days.",
            "type": "boolean",
            "example": true
          },
          "ContractExpired": {
            "description": "A tracked contract ended without renewal.",
            "type": "boolean",
            "example": true
          },
          "ContractRenewed": {
            "description": "A tracked contract was auto-renewed into its next term.",
            "type": "boolean",
            "example": true
          }
        },
        "type": "object"
      },
      "NotificationPolicyEvents": {
        "description": "Every e-mail and push alert, keyed by notification type. `false` means the workspace never sends it. New keys may be added.",
        "properties": {
          "DocumentCompleted": {
            "type": "boolean",
            "example": true
          },
          "SignerDeclined": {
            "type": "boolean",
            "example": true
          },
          "DocumentCancelled": {
            "type": "boolean",
            "example": true
          },
          "DocumentAboutToExpire": {
            "type": "boolean",
            "example": true
          },
          "DocumentExpired": {
            "type": "boolean",
            "example": true
          },
          "DocumentExpirationReset": {
            "type": "boolean",
            "example": true
          },
          "DocumentProcessingFailed": {
            "type": "boolean",
            "example": true
          },
          "TemplateProcessingFailed": {
            "type": "boolean",
            "example": true
          },
          "SignerWhatsappFailed": {
            "type": "boolean",
            "example": true
          },
          "ContractRenewalNotice": {
            "type": "boolean",
            "example": true
          },
          "ContractExpiring": {
            "type": "boolean",
            "example": true
          },
          "ContractExpired": {
            "type": "boolean",
            "example": true
          },
          "ContractRenewed": {
            "type": "boolean",
            "example": true
          },
          "SignerViewed": {
            "type": "boolean",
            "example": false
          },
          "SignerSigned": {
            "type": "boolean",
            "example": true
          },
          "SignerIdentityReviewRequired": {
            "description": "Turns off the push only; the identity review e-mail is always sent.",
            "type": "boolean",
            "example": true
          }
        },
        "type": "object"
      },
      "NotificationPolicy": {
        "description": "The workspace's notification rules, set by its owners.",
        "properties": {
          "editable": {
            "description": "Whether the requesting user is an owner and can change the policy.",
            "type": "boolean",
            "example": true
          },
          "owners_receive_all": {
            "description": "Owners also get the push alerts for every member's documents. Each owner's own preferences still apply.",
            "type": "boolean",
            "example": false
          },
          "events": {
            "$ref": "#/components/schemas/NotificationPolicyEvents"
          }
        },
        "type": "object"
      },
      "PushPreferences": {
        "description": "Document alerts sent as push notifications to the user's phones, keyed by notification type. `true` means the push is sent. Separate from the e-mail preferences. New keys may be added.",
        "properties": {
          "SignerViewed": {
            "description": "A signer opened the document for the first time.",
            "type": "boolean",
            "example": true
          },
          "SignerSigned": {
            "description": "A signer signed the document.",
            "type": "boolean",
            "example": true
          },
          "DocumentCompleted": {
            "description": "Every signer has signed and the document is certified.",
            "type": "boolean",
            "example": true
          },
          "SignerDeclined": {
            "description": "A signer declined to sign.",
            "type": "boolean",
            "example": true
          },
          "DocumentAboutToExpire": {
            "description": "The signature deadline is approaching.",
            "type": "boolean",
            "example": true
          },
          "DocumentExpired": {
            "description": "The signature deadline passed.",
            "type": "boolean",
            "example": true
          },
          "SignerIdentityReviewRequired": {
            "description": "A signer's identity awaits the sender's review. The e-mail for this is always sent.",
            "type": "boolean",
            "example": true
          },
          "SignerWhatsappFailed": {
            "description": "A WhatsApp notification to a signer could not be delivered.",
            "type": "boolean",
            "example": true
          }
        },
        "type": "object"
      },
      "OAuthTokenRequest": {
        "description": "Body of `POST /v1/oauth/token`. Sent form-encoded per RFC 6749, or as JSON.",
        "required": [
          "grant_type",
          "client_id"
        ],
        "properties": {
          "grant_type": {
            "description": "`urn:ietf:params:oauth:grant-type:token-exchange` is for internal service clients only (Assinafy's own MCP server) — an ordinary confidential or public client authenticates with it and always gets `invalid_client`, exactly as an unrecognized client would. Everyday integrators use `authorization_code` and `refresh_token`.",
            "type": "string",
            "enum": [
              "authorization_code",
              "refresh_token",
              "urn:ietf:params:oauth:grant-type:token-exchange"
            ]
          },
          "code": {
            "type": "string"
          },
          "redirect_uri": {
            "type": "string",
            "format": "uri"
          },
          "code_verifier": {
            "description": "RFC 7636: 43-128 characters from [A-Za-z0-9-._~]. Shorter values are rejected with `invalid_grant`.",
            "type": "string"
          },
          "refresh_token": {
            "type": "string"
          },
          "client_id": {
            "type": "string"
          },
          "client_secret": {
            "description": "Confidential clients only. Public clients authenticate with PKCE and are never issued a secret; the token-exchange grant requires a confidential, internal-service client and therefore always requires this.",
            "type": "string"
          },
          "resource": {
            "description": "RFC 8707 resource indicator. For `authorization_code`/`refresh_token`, optional; when present it must be the `resource` value published by /.well-known/oauth-protected-resource and must match the one sent to /authorize, otherwise `invalid_target`. For the token-exchange grant it is REQUIRED and must equal this API's own resource identifier exactly (never a front-end resource such as the MCP server), otherwise `invalid_target`.",
            "type": "string",
            "format": "uri"
          },
          "subject_token": {
            "description": "Token-exchange grant only. The front-end resource's access token being traded in. Must be a live, original (never itself exchanged) token minted for a resource this server issues tokens for, other than this API's own audience.",
            "type": "string"
          },
          "subject_token_type": {
            "description": "Token-exchange grant only. Required; only `urn:ietf:params:oauth:token-type:access_token` is supported.",
            "type": "string",
            "enum": [
              "urn:ietf:params:oauth:token-type:access_token"
            ]
          },
          "requested_token_type": {
            "description": "Token-exchange grant only. Optional; when present it must agree with the only type this server issues.",
            "type": "string",
            "enum": [
              "urn:ietf:params:oauth:token-type:access_token"
            ]
          }
        },
        "type": "object"
      },
      "OAuthRevokeRequest": {
        "description": "Body of `POST /v1/oauth/revoke`. Sent form-encoded per RFC 7009, or as JSON.",
        "required": [
          "token",
          "client_id"
        ],
        "properties": {
          "token": {
            "type": "string"
          },
          "token_type_hint": {
            "type": "string",
            "enum": [
              "access_token",
              "refresh_token"
            ]
          },
          "client_id": {
            "type": "string"
          },
          "client_secret": {
            "type": "string"
          }
        },
        "type": "object"
      }
    },
    "responses": {
      "BadRequest": {
        "description": "The request was rejected because of its current state or invalid input.",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 400
                    },
                    "message": {
                      "type": "string",
                      "example": "The request could not be processed."
                    }
                  },
                  "type": "object"
                }
              ]
            }
          }
        }
      },
      "Unauthorized": {
        "description": "Missing or invalid credentials.",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 401
                    },
                    "message": {
                      "type": "string",
                      "example": "Your request was made with invalid credentials."
                    }
                  },
                  "type": "object"
                }
              ]
            }
          }
        }
      },
      "Forbidden": {
        "description": "Authenticated but not allowed to perform this action.",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 403
                    },
                    "message": {
                      "type": "string",
                      "example": "You are not allowed to perform this action."
                    }
                  },
                  "type": "object"
                }
              ]
            }
          }
        }
      },
      "DocumentsReadForbidden": {
        "description": "An OAuth app's token lacks `documents:read` (`InsufficientScope`). Also a member deactivated in the workspace, a workspace scheduled for deletion, or an OAuth token of another workspace.",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 403
                    },
                    "code": {
                      "type": "string",
                      "enum": [
                        "InsufficientScope"
                      ]
                    }
                  },
                  "type": "object"
                }
              ]
            }
          }
        }
      },
      "DocumentsWriteForbidden": {
        "description": "An OAuth app's token lacks `documents:write` (`InsufficientScope`). Also a member deactivated in the workspace, a workspace scheduled for deletion, or an OAuth token of another workspace.",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 403
                    },
                    "code": {
                      "type": "string",
                      "enum": [
                        "InsufficientScope"
                      ]
                    }
                  },
                  "type": "object"
                }
              ]
            }
          }
        }
      },
      "EnvelopesFeatureForbidden": {
        "description": "The workspace's plan lacks the Envelopes feature, or a signer's method or embedded signing the plan lacks (`PlanRestricted`); or an OAuth app's token lacks `documents:write` (`InsufficientScope`). Also a member deactivated in the workspace, a workspace scheduled for deletion, or an OAuth token of another workspace.",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 403
                    },
                    "code": {
                      "type": "string",
                      "enum": [
                        "PlanRestricted",
                        "InsufficientScope"
                      ]
                    }
                  },
                  "type": "object"
                }
              ]
            }
          }
        }
      },
      "NotFound": {
        "description": "The requested resource does not exist.",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 404
                    },
                    "message": {
                      "type": "string",
                      "example": "The requested resource was not found."
                    }
                  },
                  "type": "object"
                }
              ]
            }
          }
        }
      },
      "SignerStepPending": {
        "description": "With a signer access code: the signer's step has not started yet (sequential signing).",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 400
                    },
                    "message": {
                      "type": "string",
                      "example": "This signer is in a pending step and has not been notified yet."
                    }
                  },
                  "type": "object"
                }
              ]
            }
          }
        }
      },
      "SignerAccessClosed": {
        "description": "With a signer access code: the code is closed, because its signer finished (for a document of an envelope, every document of it they are on) more than 30 minutes ago. It still shows the signer's status and asks for the certified copy (`GET /v1/sign`).",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 410
                    },
                    "message": {
                      "type": "string",
                      "example": "This signing link is closed. The signed document can be sent again from the signing page."
                    }
                  },
                  "type": "object"
                }
              ]
            }
          }
        }
      },
      "SignedRenderPending": {
        "description": "With `artifact=certificated`: another request was still rendering the page after 30 seconds, or the API was already rendering as many pages as it allows at once. Try again after the `Retry-After` seconds.",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 503
                    },
                    "message": {
                      "type": "string",
                      "example": "The page is still being rendered. Try again in a moment."
                    }
                  },
                  "type": "object"
                }
              ]
            }
          }
        }
      },
      "ValidationError": {
        "description": "One or more fields failed validation.",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 422
                    },
                    "message": {
                      "type": "string",
                      "example": "One or more fields failed validation."
                    }
                  },
                  "type": "object"
                }
              ]
            }
          }
        }
      },
      "DeletionRestrictions": {
        "description": "Deletion blocked by active restrictions. Each `restrictions` entry describes one blocker; resolve them individually, or retry with `force: true` to cancel blocking subscriptions/documents automatically.",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 400
                    },
                    "message": {
                      "type": "string",
                      "example": "Cannot delete while restrictions are active."
                    },
                    "restrictions": {
                      "type": "array",
                      "items": {
                        "properties": {
                          "code": {
                            "description": "Machine-readable restriction code. `PendingDocuments` only appears together with `ActivePaidSubscription`, never alone.",
                            "type": "string",
                            "enum": [
                              "ActivePaidSubscription",
                              "PendingDocuments"
                            ],
                            "example": "ActivePaidSubscription"
                          },
                          "message": {
                            "type": "string",
                            "example": "Account has an active paid subscription."
                          },
                          "account_ids": {
                            "description": "IDs of the accounts affected by this restriction.",
                            "type": "array",
                            "items": {
                              "type": "string"
                            },
                            "example": [
                              "vmvk6Urzus3byLD2qO"
                            ]
                          }
                        },
                        "type": "object"
                      }
                    }
                  },
                  "type": "object"
                }
              ]
            }
          }
        }
      },
      "NoContractTerms": {
        "description": "The document is not in the workspace (`Document not found.`), or carries no contract terms (on a read: neither its own nor those of a contract it amends whose document still exists). Only the second has a `code`, `NoContractTerms`, so a document without terms can be told from one that is not there.",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 404
                    },
                    "message": {
                      "type": "string",
                      "example": "This document has no contract terms."
                    },
                    "code": {
                      "description": "Present only when the document is in the workspace but carries no contract terms.",
                      "type": "string",
                      "enum": [
                        "NoContractTerms"
                      ],
                      "example": "NoContractTerms"
                    }
                  },
                  "type": "object"
                }
              ]
            },
            "example": {
              "status": 404,
              "message": "This document has no contract terms.",
              "code": "NoContractTerms"
            }
          }
        }
      },
      "ContractInputError": {
        "description": "The request breaks a contract rule. `errors` has one entry per problem found: the `field` as sent (`null` when the request as a whole is refused), a stable `code` to translate, and the `message` in the platform's language; `message` at the top is the first entry's. The contract's own rules on the terms, and the external references' items, report every field that breaks one, an entry per field (its first problem); the other checks (the body, its fields, the contract's state, a renewal's or a link's conditions) stop at the first problem they find.\n\n| Code | Field | Refused when |\n|------|-------|--------------|\n| `InvalidBody` | — | The body is not a JSON object (a list, a bare value), or a `PUT` of contract terms sends no attribute. |\n| `UnknownField` | the attribute | The endpoint does not take the attribute; one entry per attribute. |\n| `NotSingleValue` | the attribute | On a `PUT` of contract terms, a list sent for a term that takes one value (every term but `metadata` and `external_references`). On a link, the same list answers the term's own code. |\n| `Required` | `parent_document_id`, `relation_type`, `external_references[i].system`, `external_references[i].id` | A first link without `parent_document_id` (a pending link that was removed meanwhile) or `relation_type`; an `external_references` item without `system` or `id` (`i` is its place in the list sent, from 0). |\n| `Invalid` | the field | The value is not of the field's type or form: `status` not one of the four; `currency` not three letters; `auto_renew` not a boolean; `owner_id` not a user id; `value` not a number; `counterparty_name` holding HTML; `metadata` not an object; `external_references` not a list, an item's `system` (`external_references[i].system`) with characters other than letters, digits, `_`, `-` and `.`, or its `id` (`external_references[i].id`) with an emoji or another 4-byte character; `parent_document_id` not text; `relation_type` not `amendment`, `renewal` or `termination`; `term` not a whole number from 1; `reason` not text. On the list: a filter sent as a list, a `status` not one of the four, `ending_within_days` not a whole number. |\n| `TooSmall`, `TooBig` | the field | `value` below 0 or above 9999999999999.99; `renewal_term_months` outside 1 to 600; `notice_period_days` (and the list's `ending_within_days`) outside 0 to 3650. |\n| `TooLong` | the field | `counterparty_name` over 255 characters, counting each emoji as stored (several characters); `counterparty_taxpayer_id` over 20; `reason` over 500; `external_references[i].system` over 64 or `external_references[i].id` over 255. |\n| `TooMany` | `external_references` | More than 20 items. |\n| `TooManyDecimals` | `value` | More than two decimal places. |\n| `InvalidDate` | `start_date`, `end_date`, `updated_at`; the list's `end_date_from`, `end_date_to` | Not a date as `YYYY-MM-DD` (an impossible one such as `2026-02-30` included); `updated_at` not a date and time as the API writes it (`2026-10-23T12:00:00Z`, or with an offset). |\n| `DateTooEarly` | `start_date`, `end_date` | Before the year 1000. |\n| `DateTooLate` | `end_date` | A renewal by the term would pass 9999-12-31. |\n| `EndBeforeStart` | `end_date` | Before `start_date`. |\n| `InvalidTaxpayerId` | `counterparty_taxpayer_id` | Not a valid CPF or CNPJ (formatted or not). |\n| `NotAMember` | `owner_id` | Not an active member of the workspace. |\n| `TooManyKeys` | `metadata` | More than 50 keys. |\n| `InvalidKey` | `metadata` | A key that is not 1 to 40 letters, digits, `_`, `-` or `.`. |\n| `InvalidValue` | `metadata` | A value that is not plain text of at most 500 characters. |\n| `NumberUnderDateKey` | `metadata` | A number under a key ending in `_at`, or `stuck_since`: those keys hold text only. |\n| `ListKeys` | `metadata` | Keys `0`, `1`, `2`…, which would read back as a list. |\n| `DuplicateReference` | `external_references` | Two items the database takes for one (accents, `ß` and `ss`); items equal case aside are merged, not refused. |\n| `StatusNotSettable` | `status` | A tracked contract sent `expired` or `terminated`, or a closed one sent `draft` or `active`: a contract is closed by terminating it and reopened by renewing it. |\n| `CannotReturnToDraft` | `status` | An `active` contract sent `draft`. |\n| `ExpiredNeedsPastEndDate` | `end_date` | New terms with status `expired` and no `end_date` before today. |\n| `TerminatedNeedsEndDate` | `end_date` | New terms with status `terminated` and no `end_date`. |\n| `UnsignableDocument` | — | New terms, or a first link, on a cancelled, declined or expired document. Terms or a link already on one stay editable. |\n| `DocumentIsAmendment` | — | Terms of its own for a document linked as an amendment: its terms are the changes it makes. |\n| `DocumentHasOwnTerms` | — | A link for a document that tracks a contract of its own: a document is a contract or an amendment, never both. |\n| `ParentHasNoTerms` | `parent_document_id` | The parent named has no contract terms, nor amends a contract that has. |\n| `ParentTerminated` | `parent_document_id` | The parent contract is terminated. |\n| `NoTermChanged` | — | An `amendment` that changes no term, also by dropping its last one. A `renewal` or `termination` may change none. |\n| `TermNotAllowed` | the term | A term the relation type cannot change: a `renewal` changes only `end_date` and `value`, a `termination` only `end_date`. |\n| `AmendmentApplied` | — | The amendment applied when its document was signed and can no longer change. |\n| `TermOrEndDateRequired` | — | A renewal sends neither `term` nor `end_date`. |\n| `TermMismatch` | `term` | Not the contract's current term: the renewal already happened. |\n| `ContractTerminated` | — | A renewal of a terminated contract. |\n| `EndDateRequired` | `end_date` | A renewal by the term, or a `renewal` link without `end_date`, of a contract without an end date. |\n| `EndDateNotAfterCurrent` | `end_date` | A renewal, or a `renewal` link, to a date not after the current end date. |\n| `AlreadyTerminated` | — | A termination of a contract already terminated. |\n\nA body that is not valid JSON is refused before it reaches these endpoints, with the platform's `400` and no `errors`. A link is also checked against what applying it today would give the parent, so a delta the contract would refuse comes back with the contract's own codes.",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 400
                    },
                    "message": {
                      "description": "The first entry's message.",
                      "type": "string",
                      "example": "value must be no less than 0."
                    },
                    "errors": {
                      "type": "array",
                      "items": {
                        "required": [
                          "field",
                          "code",
                          "message"
                        ],
                        "properties": {
                          "field": {
                            "description": "The attribute or filter as sent; `null` when the request as a whole is refused.",
                            "type": [
                              "string",
                              "null"
                            ],
                            "example": "value"
                          },
                          "code": {
                            "description": "Stable, in English, one of the codes above.",
                            "type": "string",
                            "example": "TooSmall"
                          },
                          "message": {
                            "description": "In the platform's language.",
                            "type": "string",
                            "example": "value must be no less than 0."
                          }
                        },
                        "type": "object"
                      }
                    }
                  },
                  "type": "object"
                }
              ]
            },
            "example": {
              "status": 400,
              "message": "value must be no less than 0.",
              "errors": [
                {
                  "field": "value",
                  "code": "TooSmall",
                  "message": "value must be no less than 0."
                },
                {
                  "field": "currency",
                  "code": "Invalid",
                  "message": "currency is invalid."
                }
              ]
            }
          }
        }
      },
      "ContractChanged": {
        "description": "The write sent the `updated_at` it read the contract (or the amendment link) with, and the contract changed since: by another user, an integration or the hourly run. Nothing was written. Read it again, check the change still applies, and send it with the new `updated_at`.",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 409
                    },
                    "message": {
                      "type": "string",
                      "example": "The contract changed after it was read; read it again and resend the change."
                    },
                    "code": {
                      "type": "string",
                      "enum": [
                        "ContractChanged"
                      ],
                      "example": "ContractChanged"
                    }
                  },
                  "type": "object"
                }
              ]
            },
            "example": {
              "status": 409,
              "message": "The contract changed after it was read; read it again and resend the change.",
              "code": "ContractChanged"
            }
          }
        }
      },
      "ContractPlanRestricted": {
        "description": "Contract tracking is not available to the workspace (`PlanRestricted`): its current subscription lacks it (a custom contract that leaves it out, or an administrator's removal), or it is turned off for the whole service. It comes with every standard plan, the free plan too.",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 403
                    },
                    "message": {
                      "type": "string",
                      "example": "Contract tracking is not available for your current plan."
                    },
                    "code": {
                      "type": "string",
                      "enum": [
                        "PlanRestricted"
                      ],
                      "example": "PlanRestricted"
                    }
                  },
                  "type": "object"
                }
              ]
            },
            "example": {
              "status": 403,
              "message": "Contract tracking is not available for your current plan.",
              "code": "PlanRestricted"
            }
          }
        }
      },
      "CreditCeilingExceeded": {
        "description": "The request sent `max_total_credits` and costs more now: the client estimates again and decides. Nothing was created or charged.",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 409
                    },
                    "message": {
                      "type": "string",
                      "example": "This request costs 1.5 credits, more than the max_total_credits of 1."
                    },
                    "code": {
                      "type": "string",
                      "enum": [
                        "CreditCeilingExceeded"
                      ],
                      "example": "CreditCeilingExceeded"
                    },
                    "total_credits": {
                      "description": "What the request costs now.",
                      "type": "number",
                      "example": 1.5
                    },
                    "max_total_credits": {
                      "description": "The ceiling it was sent with.",
                      "type": "number",
                      "example": 1
                    }
                  },
                  "type": "object"
                }
              ]
            },
            "example": {
              "status": 409,
              "message": "This request costs 1.5 credits, more than the max_total_credits of 1.",
              "code": "CreditCeilingExceeded",
              "total_credits": 1.5,
              "max_total_credits": 1
            }
          }
        }
      },
      "ServerError": {
        "description": "Unexpected server error.",
        "content": {
          "application/json": {
            "schema": {
              "type": "object",
              "allOf": [
                {
                  "$ref": "#/components/schemas/ErrorEnvelope"
                },
                {
                  "properties": {
                    "status": {
                      "type": "integer",
                      "example": 500
                    },
                    "message": {
                      "type": "string",
                      "example": "An unexpected error occurred."
                    }
                  },
                  "type": "object"
                }
              ]
            }
          }
        }
      }
    },
    "parameters": {
      "AccountId": {
        "name": "accountId",
        "in": "path",
        "description": "Workspace account ID.",
        "required": true,
        "schema": {
          "type": "string"
        }
      },
      "DocumentId": {
        "name": "documentId",
        "in": "path",
        "description": "Document ID.",
        "required": true,
        "schema": {
          "type": "string"
        }
      },
      "EnvelopeId": {
        "name": "envelopeId",
        "in": "path",
        "description": "The envelope ID.",
        "required": true,
        "schema": {
          "type": "string"
        }
      },
      "Page": {
        "name": "page",
        "in": "query",
        "description": "Page number.",
        "schema": {
          "type": "integer",
          "minimum": 1,
          "example": 1
        }
      },
      "PerPage": {
        "name": "per-page",
        "in": "query",
        "description": "Records per page: 20 by default, at most 50; a larger value answers 50 a page.",
        "schema": {
          "type": "integer",
          "default": 20,
          "maximum": 50,
          "minimum": 1,
          "example": 25
        }
      },
      "Search": {
        "name": "search",
        "in": "query",
        "description": "Search term.",
        "schema": {
          "type": "string"
        }
      },
      "SignedRender": {
        "name": "artifact",
        "in": "query",
        "description": "`certificated`: the page, or the thumbnail, as it is in the signed document, with its signatures and stamps. Only for a certificated document, and only for the workspace's members and apps: `404` when the document isn't certificated or the caller uses a signer's access code. The first request for a page renders it with the pages around it, ten at a time, so it takes longer than the next ones; a request that finds the document's pages being rendered waits up to 30 seconds, then answers `503` with `Retry-After` if they still aren't ready; it answers `503` at once when the API is already rendering as many pages as it allows. Sent with `Cache-Control: no-store`. The certificate pages at the end of the signed file have no page ID and no render, and the visible seal of a document signed with a digital certificate is only in its `pades` file. Any other value is ignored.",
        "schema": {
          "type": "string"
        }
      }
    },
    "examples": {
      "AssignmentCreateVirtualFull": {
        "summary": "Create without input (method: virtual, with verification & notification methods)",
        "value": {
          "method": "virtual",
          "signers": [
            {
              "id": "615605f50e968054a5b7c9b8",
              "verification_method": "Email",
              "notification_methods": [
                "Email"
              ],
              "step": 1
            },
            {
              "id": "615605f50e968054a5b7c9b9",
              "verification_method": "Whatsapp",
              "notification_methods": [
                "Whatsapp"
              ],
              "step": 2
            }
          ],
          "expires_at": "2021-09-30T21:00:00Z"
        }
      },
      "AssignmentCreateCollectFull": {
        "summary": "Create with input fields (method: collect, with verification & notification methods)",
        "value": {
          "method": "collect",
          "signers": [
            {
              "id": "61521202f665dffcef5f6b24",
              "verification_method": "Email",
              "notification_methods": [
                "Email"
              ],
              "step": 1
            },
            {
              "id": "615212039529a822e24b6913",
              "verification_method": "Whatsapp",
              "notification_methods": [
                "Whatsapp"
              ],
              "step": 2
            }
          ],
          "entries": [
            {
              "page_id": "615213ed81b071f4293b2fc2",
              "fields": [
                {
                  "signer_id": "61521202f665dffcef5f6b24",
                  "field_id": "6152120297080d55bdd13197",
                  "display_settings": {
                    "left": 69,
                    "top": 282,
                    "width": 421,
                    "height": 45.86,
                    "fontFamily": "Arial",
                    "fontSize": 18,
                    "backgroundColor": "rgb(185, 218, 255)"
                  }
                },
                {
                  "signer_id": "615212039529a822e24b6913",
                  "field_id": "6152120297080d55bdd13197",
                  "display_settings": {
                    "left": 639,
                    "top": 285,
                    "width": 421,
                    "height": 45.86,
                    "fontFamily": "Arial",
                    "fontSize": 18,
                    "backgroundColor": "rgb(195, 230, 203)"
                  }
                }
              ]
            }
          ],
          "expires_at": "2021-09-30T21:00:00Z"
        }
      },
      "AssignmentCreateVirtual": {
        "summary": "Create without input (method: virtual)",
        "value": {
          "method": "virtual",
          "signers": [
            {
              "id": "615605f50e968054a5b7c9b8",
              "step": 1
            },
            {
              "id": "615605f50e968054a5b7c9b9",
              "step": 2
            }
          ],
          "expires_at": "2021-09-30T21:00:00Z"
        }
      },
      "AssignmentCreateCollect": {
        "summary": "Create with input fields (method: collect)",
        "value": {
          "method": "collect",
          "signers": [
            {
              "id": "61521202f665dffcef5f6b24",
              "step": 1
            },
            {
              "id": "615212039529a822e24b6913",
              "step": 2
            }
          ],
          "entries": [
            {
              "page_id": "615213ed81b071f4293b2fc2",
              "fields": [
                {
                  "signer_id": "61521202f665dffcef5f6b24",
                  "field_id": "6152120297080d55bdd13197",
                  "display_settings": {
                    "left": 69,
                    "top": 282,
                    "width": 421,
                    "height": 45.86,
                    "fontFamily": "Arial",
                    "fontSize": 18,
                    "backgroundColor": "rgb(185, 218, 255)"
                  }
                },
                {
                  "signer_id": "615212039529a822e24b6913",
                  "field_id": "6152120297080d55bdd13197",
                  "display_settings": {
                    "left": 639,
                    "top": 285,
                    "width": 421,
                    "height": 45.86,
                    "fontFamily": "Arial",
                    "fontSize": 18,
                    "backgroundColor": "rgb(195, 230, 203)"
                  }
                }
              ]
            }
          ],
          "expires_at": "2021-09-30T21:00:00Z"
        }
      },
      "AssignmentCreatedVirtual": {
        "summary": "Virtual assignment created",
        "value": {
          "status": 200,
          "message": "",
          "data": {
            "resource": "assignment",
            "id": "615605f8f0cc742d680c62c5",
            "sender_email": "sender@example.com",
            "method": "virtual",
            "expires_at": "2021-09-30T21:00:00Z",
            "message": null,
            "signers": [
              {
                "id": "615605f50e968054a5b7c9b8",
                "full_name": "John Dove",
                "email": "joe@example.com",
                "verification_method": "Email",
                "notification_methods": [
                  "Email"
                ],
                "step": 1,
                "notified": true,
                "completed": false
              },
              {
                "id": "615605f50e968054a5b7c9b9",
                "full_name": "Jane Doe",
                "email": "jane@example.com",
                "verification_method": "Whatsapp",
                "notification_methods": [
                  "Whatsapp"
                ],
                "step": 2,
                "notified": false,
                "completed": false
              }
            ],
            "copy_receivers": [],
            "items": [
              {
                "id": "615605f8e4a097c44247bd8e",
                "page": null,
                "signer": {
                  "id": "615605f50e968054a5b7c9b8",
                  "full_name": "John Dove",
                  "email": "joe@example.com"
                },
                "field": {
                  "id": "61521202f2f86152752c6a1b",
                  "name": "Virtual",
                  "type": "virtual"
                },
                "display_settings": [],
                "value": null,
                "completed": false
              }
            ],
            "summary": {
              "signer_count": 2,
              "completed_count": 0,
              "signers": [
                {
                  "id": "615605f50e968054a5b7c9b8",
                  "full_name": "John Dove",
                  "email": "joe@example.com",
                  "completed": false
                },
                {
                  "id": "615605f50e968054a5b7c9b9",
                  "full_name": "Jane Doe",
                  "email": "jane@example.com",
                  "completed": false
                }
              ]
            },
            "signing_urls": [
              {
                "signer_id": "615605f50e968054a5b7c9b8",
                "url": "https://api.assinafy.com.br/v1/sign/615213edf8a58f132e1b2384?email=joe@example.com"
              },
              {
                "signer_id": "615605f50e968054a5b7c9b9",
                "url": "https://api.assinafy.com.br/v1/sign/615213edf8a58f132e1b2384?email=jane@example.com"
              }
            ]
          }
        }
      },
      "AssignmentCreatedCollect": {
        "summary": "Collect assignment created",
        "value": {
          "status": 200,
          "message": "",
          "data": {
            "resource": "assignment",
            "id": "615606ef81d199996981dbce",
            "sender_email": "sender@example.com",
            "method": "collect",
            "expires_at": "2021-09-30T21:00:00Z",
            "message": null,
            "signers": [
              {
                "id": "61521202f665dffcef5f6b24",
                "full_name": "Kennith Kuphal",
                "email": "person@example.com",
                "verification_method": "Email",
                "notification_methods": [
                  "Email"
                ],
                "step": 1,
                "notified": true,
                "completed": false
              },
              {
                "id": "615212039529a822e24b6913",
                "full_name": "Sonny Bayer",
                "email": "person@example.com",
                "verification_method": "Whatsapp",
                "notification_methods": [
                  "Whatsapp"
                ],
                "step": 2,
                "notified": false,
                "completed": false
              }
            ],
            "copy_receivers": [],
            "items": [
              {
                "id": "615606efbb67641186c12330",
                "page": {
                  "id": "615213ed81b071f4293b2fc2",
                  "number": 1,
                  "height": 2100,
                  "width": 1275,
                  "download_url": "https://api.assinafy.com.br/v1/documents/615213edf8a58f132e1b2384/pages/615213ed81b071f4293b2fc2/download"
                },
                "signer": {
                  "id": "61521202f665dffcef5f6b24",
                  "full_name": "Kennith Kuphal",
                  "email": "person@example.com"
                },
                "field": {
                  "id": "6152120297080d55bdd13197",
                  "name": "Signature",
                  "type": "signature"
                },
                "display_settings": {
                  "top": 282,
                  "left": 69,
                  "width": 421,
                  "height": 45.86,
                  "fontSize": 18,
                  "fontFamily": "Arial",
                  "backgroundColor": "rgb(185, 218, 255)"
                },
                "value": null,
                "completed": false
              },
              {
                "id": "615606efcde1a39c9d21e30e",
                "page": {
                  "id": "615213ed81b071f4293b2fc2",
                  "number": 1,
                  "height": 2100,
                  "width": 1275,
                  "download_url": "https://api.assinafy.com.br/v1/documents/615213edf8a58f132e1b2384/pages/615213ed81b071f4293b2fc2/download"
                },
                "signer": {
                  "id": "615212039529a822e24b6913",
                  "full_name": "Sonny Bayer",
                  "email": "person@example.com"
                },
                "field": {
                  "id": "6152120297080d55bdd13197",
                  "name": "Signature",
                  "type": "signature"
                },
                "display_settings": {
                  "top": 285,
                  "left": 639,
                  "width": 421,
                  "height": 45.86,
                  "fontSize": 18,
                  "fontFamily": "Arial",
                  "backgroundColor": "rgb(195, 230, 203)"
                },
                "value": null,
                "completed": false
              }
            ],
            "summary": {
              "signer_count": 2,
              "completed_count": 0,
              "signers": [
                {
                  "id": "61521202f665dffcef5f6b24",
                  "full_name": "Kennith Kuphal",
                  "email": "person@example.com",
                  "completed": false
                },
                {
                  "id": "615212039529a822e24b6913",
                  "full_name": "Sonny Bayer",
                  "email": "person@example.com",
                  "completed": false
                }
              ]
            },
            "signing_urls": [
              {
                "signer_id": "61521202f665dffcef5f6b24",
                "url": "https://api.assinafy.com.br/v1/sign/615213edf8a58f132e1b2384?email=person@example.com"
              }
            ]
          }
        }
      },
      "ContractUpdateFull": {
        "summary": "Set every term",
        "value": {
          "status": "active",
          "counterparty_name": "Acme Ltda",
          "counterparty_taxpayer_id": "11.222.333/0001-81",
          "value": 120000.5,
          "currency": "BRL",
          "start_date": "2026-11-01",
          "end_date": "2027-10-31",
          "auto_renew": true,
          "renewal_term_months": 12,
          "notice_period_days": 30,
          "owner_id": "d6zqpbyog2v3xvxerwn8la94",
          "metadata": {
            "crm_stage": "won"
          },
          "external_references": [
            {
              "system": "hubspot",
              "id": "98765"
            }
          ]
        }
      },
      "ContractUpdatePartial": {
        "summary": "Change one term, clear another",
        "value": {
          "notice_period_days": 60,
          "counterparty_name": null
        }
      },
      "ContractUpdateReferences": {
        "summary": "Replace the external references",
        "value": {
          "external_references": [
            {
              "system": "hubspot",
              "id": "98765"
            },
            {
              "system": "erp",
              "id": "C-1042"
            }
          ]
        }
      },
      "Contract": {
        "summary": "The contract terms",
        "value": {
          "status": 200,
          "message": "",
          "data": {
            "resource": "document_contract",
            "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
            "status": "active",
            "counterparty_name": "Acme Ltda",
            "counterparty_taxpayer_id": "11222333000181",
            "value": 120000.5,
            "currency": "BRL",
            "start_date": "2026-11-01",
            "end_date": "2027-10-31",
            "auto_renew": true,
            "renewal_term_months": 12,
            "notice_period_days": 30,
            "term": 1,
            "owner_id": "d6zqpbyog2v3xvxerwn8la94",
            "metadata": {
              "crm_stage": "won"
            },
            "external_references": [
              {
                "system": "hubspot",
                "id": "98765"
              }
            ],
            "created_at": "2026-10-23T12:00:00Z",
            "updated_at": "2026-10-23T12:00:00Z"
          }
        }
      },
      "ContractList": {
        "summary": "A page of contracts",
        "value": {
          "status": 200,
          "message": "",
          "data": [
            {
              "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
              "status": "active",
              "counterparty_name": "Acme Ltda",
              "counterparty_taxpayer_id": "11222333000181",
              "value": 120000.5,
              "currency": "BRL",
              "start_date": "2026-11-01",
              "end_date": "2027-10-31",
              "auto_renew": true,
              "renewal_term_months": 12,
              "notice_period_days": 30,
              "term": 1,
              "owner_id": "d6zqpbyog2v3xvxerwn8la94",
              "metadata": {
                "crm_stage": "won"
              },
              "external_references": [
                {
                  "system": "hubspot",
                  "id": "98765"
                }
              ],
              "created_at": "2026-10-23T12:00:00Z",
              "updated_at": "2026-10-23T12:00:00Z"
            }
          ]
        }
      },
      "ContractListExpanded": {
        "summary": "A page of contracts with their documents (expand=document)",
        "value": {
          "status": 200,
          "message": "",
          "data": [
            {
              "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
              "status": "active",
              "counterparty_name": "Acme Ltda",
              "counterparty_taxpayer_id": "11222333000181",
              "value": 120000.5,
              "currency": "BRL",
              "start_date": "2026-11-01",
              "end_date": "2027-10-31",
              "auto_renew": true,
              "renewal_term_months": 12,
              "notice_period_days": 30,
              "term": 1,
              "owner_id": "d6zqpbyog2v3xvxerwn8la94",
              "metadata": {
                "crm_stage": "won"
              },
              "external_references": [
                {
                  "system": "hubspot",
                  "id": "98765"
                }
              ],
              "created_at": "2026-10-23T12:00:00Z",
              "updated_at": "2026-10-23T12:00:00Z",
              "document": {
                "id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "account_id": "d199996981dbd199996981db",
                "template_id": null,
                "name": "Contrato de prestação de serviços.pdf",
                "status": "certificated",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original"
                },
                "is_closed": true,
                "signing_url": "https://api.assinafy.com.br/v1/sign/66b2f0e1c3a4d5e6f7a8b9c0",
                "verification_code": "a3f1c9e07b2d4e8f9a6c5b1d0e7f3a2c9b8d4e6f",
                "verification_url": "https://www.assinafy.com.br/verificador/a3f1c9e07b2d4e8f9a6c5b1d0e7f3a2c9b8d4e6f",
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": "2026-10-20T14:00:00Z",
                "updated_at": "2026-10-22T09:00:00Z"
              }
            }
          ]
        }
      },
      "ContractRenewByTerm": {
        "summary": "Renew from term 1 by the renewal term, with a new value: safe to repeat",
        "value": {
          "term": 1,
          "value": 126000
        }
      },
      "ContractRenewExplicit": {
        "summary": "Renew to a date with a new value",
        "value": {
          "end_date": "2029-06-30",
          "value": 130000
        }
      },
      "ContractRenewed": {
        "summary": "The renewed contract",
        "value": {
          "status": 200,
          "message": "",
          "data": {
            "resource": "document_contract",
            "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
            "status": "active",
            "counterparty_name": "Acme Ltda",
            "counterparty_taxpayer_id": "11222333000181",
            "value": 130000,
            "currency": "BRL",
            "start_date": "2026-11-01",
            "end_date": "2029-06-30",
            "auto_renew": true,
            "renewal_term_months": 12,
            "notice_period_days": 30,
            "term": 2,
            "owner_id": "d6zqpbyog2v3xvxerwn8la94",
            "metadata": {
              "crm_stage": "won"
            },
            "external_references": [
              {
                "system": "hubspot",
                "id": "98765"
              }
            ],
            "created_at": "2026-10-23T12:00:00Z",
            "updated_at": "2027-10-15T09:30:00Z"
          }
        }
      },
      "ContractTerminate": {
        "summary": "Terminate with a reason",
        "value": {
          "reason": "Fornecedor substituído"
        }
      },
      "ContractTerminated": {
        "summary": "The terminated contract",
        "value": {
          "status": 200,
          "message": "",
          "data": {
            "resource": "document_contract",
            "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
            "status": "terminated",
            "counterparty_name": "Acme Ltda",
            "counterparty_taxpayer_id": "11222333000181",
            "value": 120000.5,
            "currency": "BRL",
            "start_date": "2026-11-01",
            "end_date": "2027-10-31",
            "auto_renew": true,
            "renewal_term_months": 12,
            "notice_period_days": 30,
            "term": 1,
            "owner_id": "d6zqpbyog2v3xvxerwn8la94",
            "metadata": {
              "crm_stage": "won"
            },
            "external_references": [
              {
                "system": "hubspot",
                "id": "98765"
              }
            ],
            "created_at": "2026-10-23T12:00:00Z",
            "updated_at": "2027-03-02T14:00:00Z"
          }
        }
      },
      "ContractAmendmentLink": {
        "summary": "Link an aditivo that changes the value and the end date",
        "value": {
          "parent_document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
          "relation_type": "amendment",
          "value": 130000,
          "end_date": "2028-06-30"
        }
      },
      "ContractAmendmentPending": {
        "summary": "The amendment, waiting for its signature",
        "value": {
          "status": 200,
          "message": "",
          "data": {
            "resource": "document_contract_amendment",
            "document_id": "7c3d1e2f4a5b6c7d8e9f0a1b",
            "document_name": "Aditivo 1 - Contrato de prestação de serviços.pdf",
            "parent_document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
            "relation_type": "amendment",
            "number": null,
            "status": "pending",
            "document_status": "pending_signature",
            "apply_error": null,
            "currency": "BRL",
            "terms": {
              "value": 130000,
              "end_date": "2028-06-30"
            },
            "changes": {
              "value": {
                "from": 120000.5,
                "to": 130000
              },
              "end_date": {
                "from": "2027-10-31",
                "to": "2028-06-30"
              },
              "term": {
                "from": 1,
                "to": 2
              }
            },
            "applied_at": null,
            "created_at": "2027-05-10T15:00:00Z",
            "updated_at": "2027-05-10T15:00:00Z"
          }
        }
      },
      "ContractAmendmentApplied": {
        "summary": "The amendment, applied to the parent",
        "value": {
          "status": 200,
          "message": "",
          "data": {
            "resource": "document_contract_amendment",
            "document_id": "7c3d1e2f4a5b6c7d8e9f0a1b",
            "document_name": "Aditivo 1 - Contrato de prestação de serviços.pdf",
            "parent_document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
            "relation_type": "amendment",
            "number": 1,
            "status": "applied",
            "document_status": "certificated",
            "apply_error": null,
            "currency": "BRL",
            "terms": {
              "value": 130000,
              "end_date": "2028-06-30"
            },
            "changes": {
              "value": {
                "from": 120000.5,
                "to": 130000
              },
              "end_date": {
                "from": "2027-10-31",
                "to": "2028-06-30"
              },
              "term": {
                "from": 1,
                "to": 2
              }
            },
            "applied_at": "2027-05-12T18:20:00Z",
            "created_at": "2027-05-10T15:00:00Z",
            "updated_at": "2027-05-12T18:20:00Z"
          }
        }
      },
      "ContractWithAmendments": {
        "summary": "The contract terms with their amendments",
        "value": {
          "status": 200,
          "message": "",
          "data": {
            "resource": "document_contract",
            "document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
            "status": "active",
            "counterparty_name": "Acme Ltda",
            "counterparty_taxpayer_id": "11222333000181",
            "value": 130000,
            "currency": "BRL",
            "start_date": "2026-11-01",
            "end_date": "2028-06-30",
            "auto_renew": true,
            "renewal_term_months": 12,
            "notice_period_days": 30,
            "term": 2,
            "owner_id": "d6zqpbyog2v3xvxerwn8la94",
            "metadata": {
              "crm_stage": "won"
            },
            "external_references": [
              {
                "system": "hubspot",
                "id": "98765"
              }
            ],
            "created_at": "2026-10-23T12:00:00Z",
            "updated_at": "2027-05-12T18:20:00Z",
            "amendments": [
              {
                "document_id": "7c3d1e2f4a5b6c7d8e9f0a1b",
                "document_name": "Aditivo 1 - Contrato de prestação de serviços.pdf",
                "parent_document_id": "66b2f0e1c3a4d5e6f7a8b9c0",
                "relation_type": "amendment",
                "number": 1,
                "status": "applied",
                "document_status": "certificated",
                "apply_error": null,
                "currency": "BRL",
                "terms": {
                  "value": 130000,
                  "end_date": "2028-06-30"
                },
                "changes": {
                  "value": {
                    "from": 120000.5,
                    "to": 130000
                  },
                  "end_date": {
                    "from": "2027-10-31",
                    "to": "2028-06-30"
                  },
                  "term": {
                    "from": 1,
                    "to": 2
                  }
                },
                "applied_at": "2027-05-12T18:20:00Z",
                "created_at": "2027-05-10T15:00:00Z",
                "updated_at": "2027-05-12T18:20:00Z"
              }
            ]
          }
        }
      }
    },
    "securitySchemes": {
      "bearerAuth": {
        "type": "http",
        "description": "Access token: `Authorization: Bearer {access_token}`.",
        "bearerFormat": "JWT",
        "scheme": "bearer"
      },
      "apiKeyAuth": {
        "type": "apiKey",
        "description": "Permanent API key (recommended for back-end integrations).",
        "name": "X-Api-Key",
        "in": "header"
      },
      "signerAccessCode": {
        "type": "apiKey",
        "description": "Signer access code used by signer-facing (Signing) endpoints instead of a user token: the code in the signer's signing link, sent as the `signer-access-code` query or body parameter. It can be used again and has no expiry; it stops working when the signing request is removed. Treat it as a secret.",
        "name": "signer-access-code",
        "in": "query"
      },
      "oauth2": {
        "type": "oauth2",
        "description": "OAuth 2.1 authorization code flow with mandatory PKCE (S256), for applications and AI assistants acting in a user's workspace with that user's permission — as opposed to `apiKeyAuth`/`bearerAuth`, which authenticate the workspace or user directly. A token carries only the scopes the user approved and works for one workspace. Endpoints are published by the authorization server at `https://auth.assinafy.com.br/.well-known/oauth-authorization-server`. An OAuth token cannot reach billing, account lifecycle, credential management or admin surfaces regardless of scope. A missing scope answers `403` with `WWW-Authenticate: Bearer error=\"insufficient_scope\"` naming it. See **OAuth Integration Guide** for the full flow.",
        "flows": {
          "authorizationCode": {
            "authorizationUrl": "https://auth.assinafy.com.br/oauth/authorize",
            "tokenUrl": "https://api.assinafy.com.br/v1/oauth/token",
            "refreshUrl": "https://api.assinafy.com.br/v1/oauth/token",
            "scopes": {
              "documents:read": "Read documents, their pages, tags, signers, assignments and activity, and the WhatsApp notifications sent for an assignment.",
              "documents:write": "Create, update and delete documents, and manage their signers, assignments and activity.",
              "templates:read": "Read reusable document templates, their pages, roles, fields and tags.",
              "templates:write": "Create, update and delete templates, their pages, roles, fields and tags.",
              "account:read": "Read the workspace's profile, theme and logo, its document KPIs (stats), its webhook subscription and endpoints, and which signing features it can use.",
              "webhooks:write": "Change the workspace's webhook subscription (delivery URL, events, contact email), deactivate it, manage webhook endpoints and re-send a delivery.",
              "openid": "Identify the authenticated user (OpenID Connect `sub` claim) and enable the `/v1/oauth/userinfo` endpoint.",
              "profile": "Include the user's name in the `id_token`/userinfo claims.",
              "email": "Include the user's email and its verification status in the `id_token`/userinfo claims.",
              "offline_access": "Request a refresh token, so the app keeps working after the user's session expires without prompting them again. Only granted to a client that explicitly asks for it."
            }
          }
        }
      }
    }
  }
}
```
