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
                    "example": "100000000000000000000001"
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
      "description": "The document is not pending signature, the signer is not part of the assignment, has already signed, or waits for an earlier signing step; or the account lacks credits for the reminder."
    },
    "404": {
      "description": "The assignment or signer was not found on this document."
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
Evidence is a summary kept by Assinafy (no images or biometrics). `evidence_available` is false when the provider keeps none or it could not be fetched yet. While the review is open, `images` lists the evidence images the reviewer can open with **Signer identity review image**. A verification the provider approved directly, without a review, shows no evidence here (`evidence_available` is false); its evidence is on the `signer_identity_verified` activity. Reachable with the `documents:read` OAuth scope, but the images and the decision are not available to OAuth applications (`403`): the review is made by a person in Assinafy.

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

One evidence image of a signer identity verification that is `InReview`, for the account's reviewer: the document photos (or the digital document PDF) and the face photo. Fetched from the verification provider on each request and never stored by Assinafy. Available only while the review is open; once it is decided, every image returns 404. `name` must be one of the review's `images`. Not available to OAuth applications (`403`): the images are for the person reviewing.

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

Approves or rejects a signer whose identity verification is `InReview`, while the document is `pending_signature` or `pending_identity_review`. Approved: once every signer has signed and no other signer is under review, the document is certificated; the certificate states the approval by review and every difference found. Rejected: the document is cancelled (`rejected_by_user`, with the reviewer as the user) and every signer and copy receiver gets the cancellation notice; the note is never shown to them. The signer gets no message about the decision itself. The decision is also reported to the verification provider. Not available to OAuth applications (`403`): the certificate names the person who reviewed, so the decision is made in Assinafy.

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
      "description": "Invalid decision, the verification is not `InReview`, or the document is no longer awaiting signatures."
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

Change the authenticated user's password.

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

Creates the document's contract terms, or changes only the attributes sent (see the request body for what `null` does). `external_references`, when sent, replaces the whole list. Records a `document_contract_updated` event when the terms change; a retry that changes nothing records none. The body must be a JSON object with at least one attribute. With `parent_document_id`, the document is instead linked as an amendment, renewal or termination of that contract (or of any document in its family, resolved to the root) and the other attributes are the terms it changes; a later `PUT` on a pending amendment merges into that delta (a `null` drops a key) and `parent_document_id: null` unlinks it; every change to the link records `document_contract_amendment_updated` on this document. The delta applies to the parent once the document is certificated (at once, if it already is), in signing order (the order the documents' certifications were recorded in, fixed for each once it is; a certificated document with none recorded waits, and is placed by its certification time an hour on): instruments signed earlier apply first, one that cannot holds the later ones back, and an instrument signed before one already applied is blocked instead of applied over it. Applying records `document_contract_amended`; an applied amendment is read-only. A cancelled, declined or expired document takes no new terms and no new link; terms or a link already on one stay and can still change. Send the `updated_at` you read with the write to have it refused (`409`) if the contract or link changed meanwhile. Needs a plan with contract tracking (`403` otherwise).

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
      "description": "The workspace's plan does not include contract tracking.",
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
                  }
                },
                "type": "object"
              }
            ]
          },
          "example": {
            "status": 403,
            "message": "Contract tracking is not available for your current plan."
          }
        }
      }
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
                    "$ref": "#/components/schemas/DocumentContract"
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

Starts the contract's next term: `end_date` moves by `renewal_term_months` (12 when unset), or to the `end_date` sent, which must be after the current one. A `value` sent replaces the current one. Reopens an expired contract; a terminated one returns `400`. Steps `term`, so the lifecycle alerts run again for the new term. Records a `document_contract_renewed` event. Needs a plan with contract tracking (`403` otherwise). The body names the `term` the renewal starts from (the contract's current one) or the `end_date` it renews to, so a renewal answered with a `5xx` error, or whose answer was lost, is safe to send again: the repeat is refused with `400`, as the contract is then in its next term and that date is no longer after the current one (or with `409`, when it sends the `updated_at` it read).

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
                    "$ref": "#/components/schemas/DocumentContract"
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
      "description": "The workspace's plan does not include contract tracking.",
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
                  }
                },
                "type": "object"
              }
            ]
          },
          "example": {
            "status": 403,
            "message": "Contract tracking is not available for your current plan."
          }
        }
      }
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

Closes the contract: its status becomes `terminated` and the lifecycle job stops alerting on it. Records a `document_contract_terminated` event carrying the `reason`. Needs a plan with contract tracking (`403` otherwise).

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
                    "$ref": "#/components/schemas/DocumentContract"
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
      "description": "The workspace's plan does not include contract tracking.",
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
                  }
                },
                "type": "object"
              }
            ]
          },
          "example": {
            "status": 403,
            "message": "Contract tracking is not available for your current plan."
          }
        }
      }
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
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
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

Delete a document by its ID. Only documents in a deletable status can be removed (see GET /v1/documents/statuses).

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

Download a document artifact. Artifact types: original, certificated, certificate-page, pades, bundle. The pades artifact (signers' ICP-Brasil signatures + platform certification box) is only present on documents that had digital-certificate signers; `bundle` is a zip of the original, certificated and certificate-page artifacts, plus the pades artifact on documents that have one.

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
                "example": "100000000000000000000002"
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
                              "example": "10000000000000000000000000000003"
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
                        "example": "10000000000000000000000000000003"
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
              "example": "10000000000000000000000000000003"
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

Revokes an access or refresh token. Every token outcome always returns 200 — including a token that does not exist, is already revoked, or is malformed — so the endpoint can never be used to probe whether a token exists. The one exception is failed client authentication, which returns 401.

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
    }
  ],
  "parameters": [
    {
      "$ref": "#/components/parameters/DocumentId"
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
              "example": "person1@example.com"
            },
            "whatsapp_phone_number": {
              "description": "E.164; normalized on save.",
              "type": "string",
              "example": "+12025550123"
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

**Verification integrity:** `email` / `whatsapp_phone_number` cannot be changed while the signer has verified that channel on an in-flight (not yet certificated) document — the response is `400` naming the offending document(s). Already-certificated documents do not block updates. Changing a channel that has *unverified* in-flight requests rotates their access/verification codes (invalidating previously sent links/OTPs); use the resend endpoint to redeliver. `full_name` can always be updated.

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
              "description": "Can't be changed or cleared while the signer has verified it on an in-flight document (`400`).",
              "type": "string",
              "format": "email",
              "example": "person1@example.com"
            },
            "whatsapp_phone_number": {
              "description": "E.164; normalized on save, and compared after normalizing, so the same number in another format is not a change. Can't be changed or cleared while the signer has verified it on an in-flight document (`400`).",
              "type": "string",
              "example": "+12025550123"
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

**One live session per signer:** `can_start` is true before a provider session exists, and again once that session is Expired -- the capture link lapsed before anything was submitted, so the signer may open a new one. Once a session is Pending, In Review, Approved, Declined or Abandoned it is false. A signer who reaches Declined or Abandoned cannot restart here; the account must start a whole new process (new document, new assignment).

**In review:** the signer continues -- views, confirms their data and signs -- while the account reviews the result. The document is certificated only after the account approves every signer under review; if it rejects one, the document is cancelled and every signer receives the cancellation notice.

```json
{
  "security": [
    {
      "signerAccessCode": []
    }
  ],
  "responses": {
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
                        "description": "True when no session exists yet, or the last one Expired.",
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

**One live session per signer:** this succeeds when no session exists yet, or when the last one Expired (the capture link lapsed before anything was submitted); the expired session is replaced by a new one. Otherwise -- Pending, In Review, Approved, Declined or Abandoned -- it returns `400`. A resend never destroys or replaces a live provider session, and the account is billed once per signer at assignment creation, never again on a restart. Recovering from a Declined or Abandoned verification means the account must start a whole new process (new document, new assignment).

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
                        "example": "100000000000000000000004"
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
      "description": "Consent missing, or this signer already has a verification session that is not Expired."
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

Return the document and the signer's assignment items, scoped to the signer access code. The response adds `current_signer`: this signer, with their `verification_method`, `notification_methods` and `completed` (whether they have already signed).

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

Retrieve the document a signer has been invited to sign, using the signer access code. Marks the document as viewed. The assignment carries only this signer's items, and leaves out the other signers, their signing links (`signing_urls`), the copy receivers and the signing `summary`. The response adds `current_signer`: this signer, with their `verification_method`, `notification_methods` and `completed` (whether they have already signed). Returns 409 while the document is still being prepared (retry with backoff).

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
      "description": "A digital-certificate signer has not yet confirmed their data or accepted the terms."
    },
    "409": {
      "description": "The document is not ready to be viewed yet."
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

Sign a document with input fields (collect method): submit the signer's item values, completing their items. Signing again after the signer has signed changes nothing and returns the current assignment. For **virtual** assignments the signer must first confirm their data via `PUT /v1/documents/{documentId}/signers/confirm-data`, otherwise this returns `400` (Signer data must be confirmed before signing). Signers whose verification method is `DigitalCertificate` cannot use this endpoint — their signature must be produced through `POST /v1/signers/certificate/start` + `/complete`, and this returns `400`. The request body is a JSON array of item entries. Uses the signer access code.

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
                "example": "100000000000000000000005"
              },
              "fieldId": {
                "description": "Field associated with the item.",
                "type": "string",
                "example": "100000000000000000000004"
              },
              "pageId": {
                "description": "The page ID.",
                "type": "string",
                "example": "100000000000000000000006"
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
      "description": "Signer data must be confirmed before signing (virtual assignments), the signer must sign with a digital certificate through the digital certificate endpoints, or an entry names a page, field or item that is not one of this signer's. Nothing is signed when an entry is refused."
    },
    "404": {
      "description": "The assignment is not the one the access code belongs to."
    },
    "409": {
      "description": "The document is not ready to be signed yet."
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

The signer declines to sign the document, giving a reason. Uses the signer access code.

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
    "404": {
      "description": "The assignment is not the one this access code was issued for."
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

Sign several documents in one request, for a signer with multiple pending documents. Each document must be prepared for the **virtual** signature method. Uses the signer access code.

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
      "description": "A listed document is not a virtual document of this signer, is not pending signature, or waits for an earlier signing step."
    },
    "404": {
      "description": "A listed document ID does not exist in the workspace. The message names the unknown IDs."
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
      "description": "A listed document is not this signer's, is not pending signature, or waits for an earlier signing step. Nothing is declined."
    },
    "404": {
      "description": "A listed document ID does not exist in the workspace. The message names the unknown IDs."
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

Submit the verification code (OTP) sent to the signer to unlock the signing flow. Uses the signer access code.

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
    "200": {
      "description": "Code verified",
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

## `PUT /v1/documents/{documentId}/signers/confirm-data`

Confirm signer data

The signer confirms or updates their data before signing. Uses the signer access code.

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
            "full_name": {
              "type": "string"
            },
            "email": {
              "type": "string",
              "format": "email"
            },
            "government_id": {
              "description": "Signer's CPF/CNPJ; digits only on save.",
              "type": "string",
              "example": "39053344705"
            }
          },
          "type": "object"
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "Data confirmed",
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
    "200": {
      "description": "Terms accepted",
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
    "200": {
      "description": "Signature stored",
      "content": {
        "application/json": {
          "schema": {
            "$ref": "#/components/schemas/Envelope"
          }
        }
      }
    },
    "400": {
      "description": "The image is not a PNG or JPEG file, or it is blank."
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

List the documents a signer is party to. Accepts either the signer access code or the account's own credentials (API key or bearer token) — with account credentials, the signer must belong to that account's workspace. With the signer access code, `signerId` must be the code's own signer (otherwise `403`), only documents whose signing step has been reached are listed, and each assignment leaves out `signing_urls`.

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
      "$ref": "#/components/parameters/Page"
    },
    {
      "$ref": "#/components/parameters/PerPage"
    }
  ],
  "responses": {
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
      "description": "The access code belongs to another signer."
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

Search the documents a signer is party to (compact representation). Accepts either the signer access code or the account's own credentials (API key or bearer token) — with account credentials, the signer must belong to that account's workspace. With the signer access code, `signerId` must be the code's own signer (otherwise `403`), only documents whose signing step has been reached are listed, and each assignment leaves out `signing_urls`.

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
      "$ref": "#/components/parameters/Search"
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
    "403": {
      "description": "The access code belongs to another signer."
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

Download signer's document artifact

Download an artifact of a document the signer is party to. Public (signer-link) endpoint. Artifact types: original, certificated, certificate-page, pades, bundle. The pades artifact (signers' ICP-Brasil signatures + platform certification box) is only present on documents that had digital-certificate signers; `bundle` is a zip of the original, certificated and certificate-page artifacts, plus the pades artifact on documents that have one.

```json
{
  "security": [],
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
    "404": {
      "$ref": "#/components/responses/NotFound"
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
                    "example": "100000000000000000000000007"
                  },
                  "id": {
                    "description": "An existing signer's ID. Send either `id` or `email`; `id` wins when both are sent, and an unknown `id` returns `400`.",
                    "type": "string",
                    "example": "100000000000000000000000008"
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
                    "example": "+12025550123"
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
                    "example": "100000000000000000000000009"
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
                    "example": "100000000000000000000000007"
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
              "example": "person2@example.com"
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
      "name": "per-page",
      "in": "query",
      "description": "Items per page (default: 20).",
      "schema": {
        "type": "integer",
        "default": 20
      }
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
      "description": "The webhook subscription is inactive or the event type is not subscribed."
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

Replace the endpoint's signing secret and return the new one. The old secret stops working immediately, so update your receiver right away. Returns `400` when signing is disabled. Requires the `webhooks:write` OAuth scope.

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

## Webhook `document_uploaded`

document_uploaded

A user uploaded a document. **Subject:** User · **Object:** Document · **Payload:** none.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "email": "person3@example.com",
            "telephone": "+12025550123",
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
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "uploaded",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original"
            },
            "is_closed": false,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
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
```

## Webhook `document_metadata_ready`

document_metadata_ready

The document was normalized to PDF and its pages are available, so it can be prepared. **Subject:** User · **Object:** Document · **Payload:** none.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "email": "person3@example.com",
            "telephone": "+12025550123",
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
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "metadata_ready",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
            },
            "is_closed": false,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
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
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `document_prepared`

document_prepared

A user prepared the document (assigned its fields to signers). **Subject:** User · **Object:** Document · **Payload:** none.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "email": "person3@example.com",
            "telephone": "+12025550123",
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
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "pending_signature",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
            },
            "is_closed": false,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": null,
            "verification_url": null,
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790000300,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790000300,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false,
                    "completed": false
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `assignment_created`

assignment_created

A user created an assignment (a signature request) for the document. The payload is a snapshot of the creator profile. **Subject:** User · **Object:** Document · **Payload:** `user_name`, `user_email`, `user_telephone`.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "user_email": "person3@example.com",
            "user_telephone": "+12025550123"
          },
          "origin": {
            "ip": "203.0.113.7",
            "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
          },
          "created_at": 1790000300,
          "subject": {
            "id": "d6zqpbyog2v3xvxerwn8la94",
            "name": "Mariana Costa",
            "email": "person3@example.com",
            "telephone": "+12025550123",
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
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "pending_signature",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
            },
            "is_closed": false,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": null,
            "verification_url": null,
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790000300,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790000300,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false,
                    "completed": false
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `signature_requested`

signature_requested

A signer was asked to sign the document; sent once per signer. **Subject:** User · **Object:** Document · **Payload:** `signer_full_name`, `signer_email`, `signer_whatsapp_phone_number`, `notification_method` (`email`, `whatsapp` or `bypass`).

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "signer_whatsapp_phone_number": "+12025550123",
            "notification_method": "email"
          },
          "origin": null,
          "created_at": 1790000301,
          "subject": {
            "id": "d6zqpbyog2v3xvxerwn8la94",
            "name": "Mariana Costa",
            "email": "person3@example.com",
            "telephone": "+12025550123",
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
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "pending_signature",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
            },
            "is_closed": false,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": null,
            "verification_url": null,
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790000300,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790000301,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false,
                    "completed": false
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `signer_created`

signer_created

A user created a signer. **Subject:** User · **Object:** Signer · **Payload:** `signer_full_name`.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "email": "person3@example.com",
            "telephone": "+12025550123",
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
            "id": "100000000000000000000015",
            "full_name": "João da Silva",
            "email": "joao.silva@example.com",
            "whatsapp_phone_number": "+12025550123",
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
```

## Webhook `signer_email_verified`

signer_email_verified

The signer confirmed their email with the verification code sent for this document. **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`, `signer_email`.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "id": "100000000000000000000015",
            "full_name": "João da Silva",
            "email": "joao.silva@example.com",
            "whatsapp_phone_number": "+12025550123",
            "government_id": null,
            "has_accepted_terms": true,
            "type": "Signer"
          },
          "object": {
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "pending_signature",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
            },
            "is_closed": false,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": null,
            "verification_url": null,
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790000300,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790003700,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false,
                    "completed": false
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `signer_whatsapp_verified`

signer_whatsapp_verified

The signer confirmed their WhatsApp number with the verification code sent for this document. **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`, `signer_whatsapp_phone_number`.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "signer_whatsapp_phone_number": "+12025550123"
          },
          "origin": {
            "ip": "203.0.113.7",
            "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
          },
          "created_at": 1790003700,
          "subject": {
            "id": "100000000000000000000015",
            "full_name": "João da Silva",
            "email": "joao.silva@example.com",
            "whatsapp_phone_number": "+12025550123",
            "government_id": null,
            "has_accepted_terms": true,
            "type": "Signer"
          },
          "object": {
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "pending_signature",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
            },
            "is_closed": false,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": null,
            "verification_url": null,
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790000300,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790003700,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false,
                    "completed": false
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `signer_data_confirmed`

signer_data_confirmed

The signer confirmed their data before signing. **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`, `signer_email`, `signer_whatsapp_phone_number`, `verification_method`.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "signer_whatsapp_phone_number": "+12025550123",
            "verification_method": "Email"
          },
          "origin": {
            "ip": "203.0.113.7",
            "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36"
          },
          "created_at": 1790003800,
          "subject": {
            "id": "100000000000000000000015",
            "full_name": "João da Silva",
            "email": "joao.silva@example.com",
            "whatsapp_phone_number": "+12025550123",
            "government_id": null,
            "has_accepted_terms": true,
            "type": "Signer"
          },
          "object": {
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "pending_signature",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
            },
            "is_closed": false,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": null,
            "verification_url": null,
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790000300,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790003800,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false,
                    "completed": false
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `signer_viewed_document`

signer_viewed_document

The signer opened the document for the first time. **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "id": "100000000000000000000015",
            "full_name": "João da Silva",
            "email": "joao.silva@example.com",
            "whatsapp_phone_number": "+12025550123",
            "government_id": null,
            "has_accepted_terms": true,
            "type": "Signer"
          },
          "object": {
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "pending_signature",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
            },
            "is_closed": false,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": null,
            "verification_url": null,
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790000300,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790003600,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false,
                    "completed": false
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `signer_identity_verified`

signer_identity_verified

The signer passed identity-document verification (directly, or approved by the account after review). **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`, and `evidence` when the provider keeps one: what was read off the document (`document_type`, `document_name`), `cpf_match`, `name_similarity` (0-1), for each check its `name`, `result` and `score`, and `evidence_sha256`. No images.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
              "evidence_sha256": "1000000000000000000000000000000000000000000000000000000000000018"
            }
          },
          "origin": null,
          "created_at": 1790003600,
          "subject": {
            "id": "100000000000000000000015",
            "full_name": "João da Silva",
            "email": "joao.silva@example.com",
            "whatsapp_phone_number": "+12025550123",
            "government_id": null,
            "has_accepted_terms": true,
            "type": "Signer"
          },
          "object": {
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "pending_signature",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
            },
            "is_closed": false,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": null,
            "verification_url": null,
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790000300,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790003600,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false,
                    "completed": false
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `signer_identity_verification_failed`

signer_identity_verification_failed

The signer's identity-document verification ended without approval. **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`, `reason` (`provider_declined`, `identity_binding_failed`, `workflow_capability_mismatch`, `reviewer_rejected`).

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "id": "100000000000000000000015",
            "full_name": "João da Silva",
            "email": "joao.silva@example.com",
            "whatsapp_phone_number": "+12025550123",
            "government_id": null,
            "has_accepted_terms": true,
            "type": "Signer"
          },
          "object": {
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "pending_signature",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
            },
            "is_closed": false,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": null,
            "verification_url": null,
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790000300,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790003600,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false,
                    "completed": false
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `signer_identity_review_required`

signer_identity_review_required

The provider could not decide the signer's identity verification and it awaits the account's review. The signer can still sign; the document is certificated only after the account approves (status `pending_identity_review` once every signer has signed). **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`, `reason_codes`.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "id": "100000000000000000000015",
            "full_name": "João da Silva",
            "email": "joao.silva@example.com",
            "whatsapp_phone_number": "+12025550123",
            "government_id": null,
            "has_accepted_terms": true,
            "type": "Signer"
          },
          "object": {
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "pending_signature",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
            },
            "is_closed": false,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": null,
            "verification_url": null,
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790000300,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790003600,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false,
                    "completed": false
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `signer_identity_review_approved`

signer_identity_review_approved

A user of the account approved the signer's identity after review. `reason_codes` are the differences the provider found and the reviewer accepted. **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`, `reviewer_id`, `reviewer_name`, `reason_codes`.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "id": "100000000000000000000015",
            "full_name": "João da Silva",
            "email": "joao.silva@example.com",
            "whatsapp_phone_number": "+12025550123",
            "government_id": null,
            "has_accepted_terms": true,
            "type": "Signer"
          },
          "object": {
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "pending_signature",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
            },
            "is_closed": false,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": null,
            "verification_url": null,
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790000300,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790003600,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false,
                    "completed": false
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `signer_identity_review_rejected`

signer_identity_review_rejected

A user of the account rejected the signer's identity after review. The document is cancelled (`user_rejected_document` follows, with the reviewer as the user). `note` is the reviewer's optional note, HTML-escaped (`&` arrives as `&amp;`). **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`, `reviewer_id`, `reviewer_name`, `note`.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "id": "100000000000000000000015",
            "full_name": "João da Silva",
            "email": "joao.silva@example.com",
            "whatsapp_phone_number": "+12025550123",
            "government_id": null,
            "has_accepted_terms": true,
            "type": "Signer"
          },
          "object": {
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "rejected_by_user",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
            },
            "is_closed": false,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": null,
            "verification_url": null,
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790000300,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790003600,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false,
                    "completed": false
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `signer_signed_document`

signer_signed_document

The signer signed the document. **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`; a signer using a digital certificate adds `verification_method` (`DigitalCertificate`), `certificate_holder`, `certificate_type`, `cpf`, `cnpj`, `serial_number`, `issuer` and `signed_at` (Unix time).

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "id": "100000000000000000000015",
            "full_name": "João da Silva",
            "email": "joao.silva@example.com",
            "whatsapp_phone_number": "+12025550123",
            "government_id": null,
            "has_accepted_terms": true,
            "type": "Signer"
          },
          "object": {
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "certificating",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
            },
            "is_closed": false,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": null,
            "verification_url": null,
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790003900,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790003900,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": true
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": true,
                    "completed": true
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `signer_rejected_document`

signer_rejected_document

The signer declined to sign the document. **Subject:** Signer · **Object:** Document · **Payload:** `signer_full_name`.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "id": "100000000000000000000015",
            "full_name": "João da Silva",
            "email": "joao.silva@example.com",
            "whatsapp_phone_number": "+12025550123",
            "government_id": null,
            "has_accepted_terms": true,
            "type": "Signer"
          },
          "object": {
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "rejected_by_signer",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
            },
            "is_closed": true,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": null,
            "verification_url": null,
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790003900,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790003900,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false,
                    "completed": false
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `user_rejected_document`

user_rejected_document

A user of the account cancelled the document. **Subject:** User · **Object:** Document · **Payload:** `user_name`.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "email": "person3@example.com",
            "telephone": "+12025550123",
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
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "rejected_by_user",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
            },
            "is_closed": true,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": null,
            "verification_url": null,
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790003900,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790003900,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false,
                    "completed": false
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `document_ready`

document_ready

The last signer signed and the signed document is available: its status is `certificated` and `artifacts` holds the signed files. **Subject:** Account · **Object:** Document · **Payload:** none.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
                "email": "person3@example.com",
                "telephone": "+12025550123",
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
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "certificated",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail",
              "certificated": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/certificated",
              "certificate-page": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/certificate-page",
              "bundle": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/bundle"
            },
            "is_closed": true,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": "1000000000000000000000000000000000000019",
            "verification_url": "https://www.assinafy.com.br/verificador/1000000000000000000000000000000000000019",
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790003960,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790003960,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": true
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": true,
                    "completed": true
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `document_processing_failed`

document_processing_failed

The document could not be processed (invalid or unreadable file). **Subject:** Account · **Object:** Document · **Payload:** `error_message`.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
                "email": "person3@example.com",
                "telephone": "+12025550123",
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
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "failed",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original"
            },
            "is_closed": true,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
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
```

## Webhook `template_created`

template_created

A user created a template. **Subject:** User · **Object:** Template · **Payload:** none.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "email": "person3@example.com",
            "telephone": "+12025550123",
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
            "id": "100000000000000000000020",
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
```

## Webhook `template_processed`

template_processed

The template was processed and is ready to use. **Subject:** User · **Object:** Template · **Payload:** none.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "email": "person3@example.com",
            "telephone": "+12025550123",
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
            "id": "100000000000000000000020",
            "name": "Contrato padrão",
            "document_name": "Contrato padrão.pdf",
            "message": null,
            "status": "Ready",
            "pages": [
              {
                "id": "100000000000000000000021",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/templates/100000000000000000000020/pages/100000000000000000000021/download",
                "fields": []
              }
            ],
            "roles": [
              {
                "id": "100000000000000000000022",
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
```

## Webhook `template_processing_failed`

template_processing_failed

The template could not be processed. **Subject:** Account · **Object:** Template · **Payload:** `error_message`.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
                "email": "person3@example.com",
                "telephone": "+12025550123",
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
            "id": "100000000000000000000020",
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
```

## Webhook `document_contract_updated`

document_contract_updated

A user created or changed the contract terms of the document. Read them with `GET /v1/accounts/{accountId}/documents/{documentId}/contract`. Also sent by the hourly run when it hands the contract of an owner who left the workspace to another owner. Also sent whenever the terms change by other means (see the `by_system` example: subject Account, no `user_name`) — right after `document_contract_amended`, `document_contract_renewed`, `document_contract_expired` or `document_contract_terminated` — with the subject of that event (Account on the hourly or queued run, without `user_name`). **Subject:** User, or Account · **Object:** Document · **Payload:** `user_name` (User subject only), `end_date` (the end date after the change; null when none), `changed` (the fields of the contract resource that changed, such as `["value", "end_date", "term"]`; for terms just created, every field that differs from a contract with no terms).

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
                "email": "person3@example.com",
                "telephone": "+12025550123",
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
                "id": "100000000000000000000011",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "certificated",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
                },
                "is_closed": true,
                "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790090000,
                "assignment": {
                  "id": "100000000000000000000014",
                  "document_id": "100000000000000000000011",
                  "created_at": 1790003900,
                  "sender_email": "person3@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "100000000000000000000015",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+12025550123",
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
                      "id": "100000000000000000000016",
                      "page": null,
                      "signer": {
                        "id": "100000000000000000000015",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+12025550123",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "100000000000000000000017",
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
                        "id": "100000000000000000000015",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+12025550123",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "100000000000000000000015",
                      "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "100000000000000000000012",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
                  },
                  {
                    "id": "100000000000000000000013",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
                    "email": "person3@example.com",
                    "telephone": "+12025550123",
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
                "id": "100000000000000000000011",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "certificated",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
                },
                "is_closed": true,
                "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790090000,
                "assignment": {
                  "id": "100000000000000000000014",
                  "document_id": "100000000000000000000011",
                  "created_at": 1790003900,
                  "sender_email": "person3@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "100000000000000000000015",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+12025550123",
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
                      "id": "100000000000000000000016",
                      "page": null,
                      "signer": {
                        "id": "100000000000000000000015",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+12025550123",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "100000000000000000000017",
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
                        "id": "100000000000000000000015",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+12025550123",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "100000000000000000000015",
                      "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "100000000000000000000012",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
                  },
                  {
                    "id": "100000000000000000000013",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `document_contract_renewal_notice`

document_contract_renewal_notice

The contract's notice period started: its end date minus its notice period is today, Brasília time. Sent once per term, on the hourly run that first finds it due, so a contract whose notice period already started gets it on the next run. **Subject:** Account · **Object:** Document · **Payload:** `end_date`.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
                "email": "person3@example.com",
                "telephone": "+12025550123",
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
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "certificated",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail",
              "certificated": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/certificated",
              "certificate-page": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/certificate-page",
              "bundle": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/bundle"
            },
            "is_closed": true,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": "1000000000000000000000000000000000000019",
            "verification_url": "https://www.assinafy.com.br/verificador/1000000000000000000000000000000000000019",
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790100000,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790003960,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": true
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": true,
                    "completed": true
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `document_contract_expiring`

document_contract_expiring

The contract ends within `days_before` days: 45 and 30. Each is sent once per term, on the hourly run that first finds it due; when both are already due (a contract tracked late), only the nearer one is sent. **Subject:** Account · **Object:** Document · **Payload:** `days_before`, `end_date`.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
                "email": "person3@example.com",
                "telephone": "+12025550123",
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
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "certificated",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail",
              "certificated": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/certificated",
              "certificate-page": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/certificate-page",
              "bundle": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/bundle"
            },
            "is_closed": true,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": "1000000000000000000000000000000000000019",
            "verification_url": "https://www.assinafy.com.br/verificador/1000000000000000000000000000000000000019",
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790100000,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790003960,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": true
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": true,
                    "completed": true
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `document_contract_expired`

document_contract_expired

The contract ended without auto-renew; its status is now `expired`. Sent on the hourly run after the end date passes, Brasília time. **Subject:** Account · **Object:** Document · **Payload:** `end_date`, `changed` (the contract fields that moved: `["status"]`).

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
                "email": "person3@example.com",
                "telephone": "+12025550123",
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
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "certificated",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail",
              "certificated": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/certificated",
              "certificate-page": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/certificate-page",
              "bundle": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/bundle"
            },
            "is_closed": true,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": "1000000000000000000000000000000000000019",
            "verification_url": "https://www.assinafy.com.br/verificador/1000000000000000000000000000000000000019",
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790100000,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790003960,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": true
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": true,
                    "completed": true
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `document_contract_renewed`

document_contract_renewed

The contract started its next term: by auto-renew on the run after it ended (subject Account, see the `by_auto_renew` example) or by a user through the renew endpoint (subject User, `user_name` in the payload). `end_date` is the new one, and the `term` on the contract stepped. **Subject:** Account or User · **Object:** Document · **Payload:** `end_date`, `changed` (the contract fields that moved, such as `["end_date", "term"]`); `user_name` when a user renewed.

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
                "email": "person3@example.com",
                "telephone": "+12025550123",
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
                "id": "100000000000000000000011",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "certificated",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
                },
                "is_closed": true,
                "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790100000,
                "assignment": {
                  "id": "100000000000000000000014",
                  "document_id": "100000000000000000000011",
                  "created_at": 1790003900,
                  "sender_email": "person3@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "100000000000000000000015",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+12025550123",
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
                      "id": "100000000000000000000016",
                      "page": null,
                      "signer": {
                        "id": "100000000000000000000015",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+12025550123",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "100000000000000000000017",
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
                        "id": "100000000000000000000015",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+12025550123",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "100000000000000000000015",
                      "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "100000000000000000000012",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
                  },
                  {
                    "id": "100000000000000000000013",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
                    "email": "person3@example.com",
                    "telephone": "+12025550123",
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
                "id": "100000000000000000000011",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "certificated",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
                },
                "is_closed": true,
                "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790100000,
                "assignment": {
                  "id": "100000000000000000000014",
                  "document_id": "100000000000000000000011",
                  "created_at": 1790003900,
                  "sender_email": "person3@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "100000000000000000000015",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+12025550123",
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
                      "id": "100000000000000000000016",
                      "page": null,
                      "signer": {
                        "id": "100000000000000000000015",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+12025550123",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "100000000000000000000017",
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
                        "id": "100000000000000000000015",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+12025550123",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "100000000000000000000015",
                      "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "100000000000000000000012",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
                  },
                  {
                    "id": "100000000000000000000013",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `document_contract_terminated`

document_contract_terminated

A user terminated the contract; its status is now `terminated`. **Subject:** User · **Object:** Document · **Payload:** `user_name`, `reason` (null when none was given), `end_date`, `changed` (the contract fields that moved: `["status"]`).

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
            "email": "person3@example.com",
            "telephone": "+12025550123",
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
            "id": "100000000000000000000011",
            "account_id": "65f1c2a9b3e4d5f6",
            "template_id": null,
            "name": "Contrato de Prestação de Serviços.pdf",
            "status": "certificated",
            "artifacts": {
              "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
              "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail"
            },
            "is_closed": true,
            "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
            "verification_code": null,
            "verification_url": null,
            "decline_reason": null,
            "declined_by": null,
            "tags": [],
            "created_at": 1790000000,
            "updated_at": 1790100000,
            "assignment": {
              "id": "100000000000000000000014",
              "document_id": "100000000000000000000011",
              "created_at": 1790003900,
              "sender_email": "person3@example.com",
              "method": "virtual",
              "expires_at": 1792592000,
              "message": "Olá João, segue o contrato para assinatura.",
              "signers": [
                {
                  "id": "100000000000000000000015",
                  "full_name": "João da Silva",
                  "email": "joao.silva@example.com",
                  "whatsapp_phone_number": "+12025550123",
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
                  "id": "100000000000000000000016",
                  "page": null,
                  "signer": {
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false
                  },
                  "field": {
                    "id": "100000000000000000000017",
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
                    "id": "100000000000000000000015",
                    "full_name": "João da Silva",
                    "email": "joao.silva@example.com",
                    "whatsapp_phone_number": "+12025550123",
                    "government_id": null,
                    "has_accepted_terms": false,
                    "completed": false
                  }
                ]
              },
              "signing_urls": [
                {
                  "signer_id": "100000000000000000000015",
                  "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                }
              ]
            },
            "pages": [
              {
                "id": "100000000000000000000012",
                "number": 1,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
              },
              {
                "id": "100000000000000000000013",
                "number": 2,
                "height": 2200,
                "width": 1700,
                "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `document_contract_amended`

document_contract_amended

A signed amendment, renewal or termination document updated the contract terms of the parent document; `changes` lists every field that moved as `{from, to}`, `null` when the instrument moves none (the parent is then left as it was and no `document_contract_updated` follows). Sent seconds after the amendment document is certificated (a job on its `document_ready` event) or by the hourly contract run that catches up, both with subject Account; or, for a document already signed when linked, by the user who linked it (subject User, `user_name` in the payload; see the `by_user` example). **Subject:** Account or User · **Object:** Document (the parent) · **Payload:** `document_id` (the amendment document), `relation_type` (`amendment`, `renewal` or `termination`), `number`, `changes`, `end_date` (the parent's end date after the change), `changed` (the names of the parent's fields that moved, as `document_contract_updated` lists them; `[]` when none did).

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
                "document_id": "100000000000000000000023",
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
                    "email": "person3@example.com",
                    "telephone": "+12025550123",
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
                "id": "100000000000000000000011",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "certificated",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail",
                  "certificated": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/certificated",
                  "certificate-page": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/certificate-page",
                  "bundle": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/bundle"
                },
                "is_closed": true,
                "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
                "verification_code": "1000000000000000000000000000000000000019",
                "verification_url": "https://www.assinafy.com.br/verificador/1000000000000000000000000000000000000019",
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790100000,
                "assignment": {
                  "id": "100000000000000000000014",
                  "document_id": "100000000000000000000011",
                  "created_at": 1790003960,
                  "sender_email": "person3@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "100000000000000000000015",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+12025550123",
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
                      "id": "100000000000000000000016",
                      "page": null,
                      "signer": {
                        "id": "100000000000000000000015",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+12025550123",
                        "government_id": null,
                        "has_accepted_terms": true
                      },
                      "field": {
                        "id": "100000000000000000000017",
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
                        "id": "100000000000000000000015",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+12025550123",
                        "government_id": null,
                        "has_accepted_terms": true,
                        "completed": true
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "100000000000000000000015",
                      "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "100000000000000000000012",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
                  },
                  {
                    "id": "100000000000000000000013",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
                "document_id": "100000000000000000000023",
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
                "email": "person3@example.com",
                "telephone": "+12025550123",
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
                "id": "100000000000000000000011",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Contrato de Prestação de Serviços.pdf",
                "status": "certificated",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/thumbnail",
                  "certificated": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/certificated",
                  "certificate-page": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/certificate-page",
                  "bundle": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/bundle"
                },
                "is_closed": true,
                "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000011",
                "verification_code": "1000000000000000000000000000000000000019",
                "verification_url": "https://www.assinafy.com.br/verificador/1000000000000000000000000000000000000019",
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790100000,
                "assignment": {
                  "id": "100000000000000000000014",
                  "document_id": "100000000000000000000011",
                  "created_at": 1790003960,
                  "sender_email": "person3@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "100000000000000000000015",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+12025550123",
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
                      "id": "100000000000000000000016",
                      "page": null,
                      "signer": {
                        "id": "100000000000000000000015",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+12025550123",
                        "government_id": null,
                        "has_accepted_terms": true
                      },
                      "field": {
                        "id": "100000000000000000000017",
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
                        "id": "100000000000000000000015",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+12025550123",
                        "government_id": null,
                        "has_accepted_terms": true,
                        "completed": true
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "100000000000000000000015",
                      "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "100000000000000000000012",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
                  },
                  {
                    "id": "100000000000000000000013",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
```

## Webhook `document_contract_amendment_updated`

document_contract_amendment_updated

A user linked the document to a contract as its amendment, renewal or termination, changed the terms the link carries, or unlinked it (`parent_document_id` null, see the `unlinked` example). Sent for every change to the link (a retry that changes nothing is not sent), a signed instrument included: a signed instrument blocked from applying can still have its recorded delta corrected, and this is the record of who did. **Subject:** User · **Object:** Document (the instrument) · **Payload:** `user_name`, `parent_document_id`, `relation_type`, `terms` (the delta as it now stands; `null` after an unlink), `changed` (which of `parent_document_id`, `relation_type` and `terms` moved: all three on a first link and on an unlink, `["terms"]` when only the delta changed).

```json
{
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
      "example": "10000000000000000000000000000010"
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
      "description": "Sent only when signing is enabled on the endpoint: `v1,<base64 HMAC-SHA256>`. See **Verifying signatures**.",
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
                "parent_document_id": "100000000000000000000011",
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
                "email": "person3@example.com",
                "telephone": "+12025550123",
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
                "id": "100000000000000000000023",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Aditivo ao Contrato de Prestação de Serviços.pdf",
                "status": "pending_signature",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000023/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000023/thumbnail"
                },
                "is_closed": false,
                "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000023",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790090000,
                "assignment": {
                  "id": "100000000000000000000014",
                  "document_id": "100000000000000000000011",
                  "created_at": 1790003900,
                  "sender_email": "person3@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "100000000000000000000015",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+12025550123",
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
                      "id": "100000000000000000000016",
                      "page": null,
                      "signer": {
                        "id": "100000000000000000000015",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+12025550123",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "100000000000000000000017",
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
                        "id": "100000000000000000000015",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+12025550123",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "100000000000000000000015",
                      "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "100000000000000000000012",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
                  },
                  {
                    "id": "100000000000000000000013",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
                "email": "person3@example.com",
                "telephone": "+12025550123",
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
                "id": "100000000000000000000023",
                "account_id": "65f1c2a9b3e4d5f6",
                "template_id": null,
                "name": "Aditivo ao Contrato de Prestação de Serviços.pdf",
                "status": "pending_signature",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000023/download/original",
                  "thumbnail": "https://api.assinafy.com.br/v1/documents/100000000000000000000023/thumbnail"
                },
                "is_closed": false,
                "signing_url": "https://app.assinafy.com.br/sign/100000000000000000000023",
                "verification_code": null,
                "verification_url": null,
                "decline_reason": null,
                "declined_by": null,
                "tags": [],
                "created_at": 1790000000,
                "updated_at": 1790090000,
                "assignment": {
                  "id": "100000000000000000000014",
                  "document_id": "100000000000000000000011",
                  "created_at": 1790003900,
                  "sender_email": "person3@example.com",
                  "method": "virtual",
                  "expires_at": 1792592000,
                  "message": "Olá João, segue o contrato para assinatura.",
                  "signers": [
                    {
                      "id": "100000000000000000000015",
                      "full_name": "João da Silva",
                      "email": "joao.silva@example.com",
                      "whatsapp_phone_number": "+12025550123",
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
                      "id": "100000000000000000000016",
                      "page": null,
                      "signer": {
                        "id": "100000000000000000000015",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+12025550123",
                        "government_id": null,
                        "has_accepted_terms": false
                      },
                      "field": {
                        "id": "100000000000000000000017",
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
                        "id": "100000000000000000000015",
                        "full_name": "João da Silva",
                        "email": "joao.silva@example.com",
                        "whatsapp_phone_number": "+12025550123",
                        "government_id": null,
                        "has_accepted_terms": false,
                        "completed": false
                      }
                    ]
                  },
                  "signing_urls": [
                    {
                      "signer_id": "100000000000000000000015",
                      "url": "https://app.assinafy.com.br/sign/100000000000000000000011?email=joao.silva%40example.com"
                    }
                  ]
                },
                "pages": [
                  {
                    "id": "100000000000000000000012",
                    "number": 1,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000012/download"
                  },
                  {
                    "id": "100000000000000000000013",
                    "number": 2,
                    "height": 2200,
                    "width": 1700,
                    "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/pages/100000000000000000000013/download"
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
            "example": "100000000000000000000024"
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
            "example": "100000000000000000000025"
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
            "example": "person1@example.com"
          },
          "whatsapp_phone_number": {
            "description": "E.164 format; normalized on save.",
            "type": [
              "string",
              "null"
            ],
            "example": "+12025550123"
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
            "example": "100000000000000000000026"
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
            "example": "100000000000000000000027"
          },
          "account_id": {
            "type": "string",
            "example": "100000000000000000000028"
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
            "example": "1000000000000000000000000000000000000019"
          },
          "verification_url": {
            "description": "The public verification page for this document. `null` until the document is `certificated`.",
            "type": [
              "string",
              "null"
            ],
            "format": "uri",
            "example": "https://www.assinafy.com.br/verificador/1000000000000000000000000000000000000019"
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
      "Account": {
        "description": "A workspace account (organization).",
        "properties": {
          "resource": {
            "type": "string",
            "example": "account"
          },
          "id": {
            "type": "string",
            "example": "100000000000000000000024"
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
            "example": "100000000000000000000004"
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
            "example": "100000000000000000000000029"
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
        "description": "The contract terms tracked on a document.",
        "properties": {
          "resource": {
            "type": "string",
            "example": "document_contract"
          },
          "document_id": {
            "type": "string",
            "example": "100000000000000000000011"
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
            "description": "Up to 50 keys of your own, each holding text of at most 500 characters; `null` when there are none. A key ending in `_at` cannot hold a number: the API returns numbers under such keys as dates.",
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
            "description": "IDs outside systems use for this contract, ordered by system and id.",
            "type": "array",
            "items": {
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
            "description": "Only on the single-contract read: the documents that amend, renew or terminate this contract, the applied ones first by `number`, then the pending ones.",
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
        "description": "A document that amends, renews or terminates a contract: its link and the terms it changes. The parent's terms take the delta once this document is certificated; until then the parent is untouched.",
        "properties": {
          "resource": {
            "type": "string",
            "example": "document_contract_amendment"
          },
          "document_id": {
            "description": "This document, the instrument.",
            "type": "string",
            "example": "100000000000000000000023"
          },
          "document_name": {
            "description": "This document's name.",
            "type": "string",
            "example": "Aditivo 1 - Contrato de prestação de serviços.pdf"
          },
          "parent_document_id": {
            "description": "The contract document whose terms change.",
            "type": "string",
            "example": "100000000000000000000011"
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
        "properties": {
          "signer_id": {
            "type": "string"
          },
          "url": {
            "type": "string",
            "example": "https://api.assinafy.com.br/v1/sign/doc1?email=joe@example.com"
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
            "type": "string",
            "enum": [
              "sent",
              "failed"
            ],
            "example": "sent"
          },
          "error_code": {
            "type": [
              "string",
              "null"
            ]
          },
          "error_message": {
            "type": [
              "string",
              "null"
            ]
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
            "example": null
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
            "example": "100000000000000000000030"
          },
          "document_id": {
            "description": "The document this assignment belongs to. `null` only if the document was deleted.",
            "type": [
              "string",
              "null"
            ],
            "example": "100000000000000000000027"
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
        "properties": {
          "documents": {
            "description": "Documents consumed (always 1).",
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
            "description": "Documents left in the plan.",
            "type": "number",
            "example": 0
          },
          "credit_balance": {
            "description": "Credits available.",
            "type": "number",
            "example": 10
          },
          "has_sufficient_resources": {
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
            "example": "100000000000000000000000031"
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
            "example": "person2@example.com"
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
            "example": "10000000000000000000000000000032"
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
            "example": "10000000000000000000000000000032"
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
            "example": "10000000000000000000000000000010"
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
            "description": "`scheduled`: the delivery of the event as it happened; `probe`: the one delivery we send to a paused endpoint to see whether it answers; `manual`: asked for through **Retry webhook delivery**.",
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
            "description": "Event type. See **List webhook event types**.",
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
            "description": "Who performed the action: a `User`, `Signer` or `Account`, plus a `type` property naming it.",
            "type": "object",
            "additionalProperties": true
          },
          "object": {
            "description": "What the action was performed on: a `Document`, `Signer` or `Template` with its relations expanded, plus a `type` property naming it.",
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
            "description": "Hex color without leading `#`.",
            "type": "string",
            "example": "aabbcc"
          },
          "secondary_color": {
            "type": [
              "string",
              "null"
            ],
            "example": "aabbcc"
          },
          "logo": {
            "description": "URL to the account logo.",
            "type": "string",
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
            "example": "100000000000000000000002"
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
            "example": "1000000000000000000000000000000000000033"
          },
          "id": {
            "type": [
              "string",
              "null"
            ],
            "example": "100000000000000000000034"
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
            "type": [
              "string",
              "null"
            ],
            "format": "date-time",
            "example": "2023-01-27T19:27:44Z"
          },
          "verified_at": {
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
            "description": "Event-specific snapshot taken when the event happened, with the same keys as the webhook of that event (see **Webhook Payloads**). The common ones: `user_name` (`assignment_created`, `user_rejected_document`); `signer_full_name` (every `signer_*` event and `signature_requested`); `signer_email`, `signer_whatsapp_phone_number` and `notification_method` (`signature_requested`); `reviewer_name` (identity review decisions); `error_message` (`document_processing_failed`). `message` already carries the actor's name, translated, so clients can show it as is. A `*_at` key inside the payload is returned as an ISO 8601 string here, as in every API response, but as a Unix integer in webhook bodies.",
            "type": [
              "object",
              "null"
            ]
          },
          "origin": {
            "description": "Request origin when available.",
            "properties": {
              "ip": {
                "type": "string",
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
            "example": "+12025550123"
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
            "example": "1000000000000000000000000035"
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
            "example": "1000000000000000000000000000000000000000000000000000000000000018"
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
        "description": "The request breaks a contract rule. `errors` has one entry per problem found: the `field` as sent (`null` when the request as a whole is refused), a stable `code` to translate, and the `message` in the platform's language; `message` at the top is the first entry's. The contract's own rules on the terms, and the external references' items, report every field that breaks one, an entry per field (its first problem); the other checks (the body, its fields, the contract's state, a renewal's or a link's conditions) stop at the first problem they find.\n\n| Code | Field | Refused when |\n|------|-------|--------------|\n| `InvalidBody` | — | The body is not a JSON object (a list, a bare value), or a `PUT` of contract terms sends no attribute. |\n| `UnknownField` | the attribute | The endpoint does not take the attribute; one entry per attribute. |\n| `NotSingleValue` | the attribute | On a `PUT` of contract terms, a list sent for a term that takes one value (every term but `metadata` and `external_references`). On a link, the same list answers the term's own code. |\n| `Required` | `parent_document_id`, `relation_type`, `external_references[i].system`, `external_references[i].id` | A first link without `parent_document_id` (a pending link that was removed meanwhile) or `relation_type`; an `external_references` item without `system` or `id` (`i` is its place in the list sent, from 0). |\n| `Invalid` | the field | The value is not of the field's type or form: `status` not one of the four; `currency` not three letters; `auto_renew` not a boolean; `owner_id` not a user id; `value` not a number; `counterparty_name` holding HTML; `metadata` not an object; `external_references` not a list, an item's `system` (`external_references[i].system`) with characters other than letters, digits, `_`, `-` and `.`, or its `id` (`external_references[i].id`) with an emoji or another 4-byte character; `parent_document_id` not text; `relation_type` not `amendment`, `renewal` or `termination`; `term` not a whole number from 1; `reason` not text. On the list: a filter sent as a list, a `status` not one of the four, `ending_within_days` not a whole number. |\n| `TooSmall`, `TooBig` | the field | `value` below 0 or above 9999999999999.99; `renewal_term_months` outside 1 to 600; `notice_period_days` (and the list's `ending_within_days`) outside 0 to 3650. |\n| `TooLong` | the field | `counterparty_name` over 255 characters, counting each emoji as stored (several characters); `counterparty_taxpayer_id` over 20; `reason` over 500; `external_references[i].system` over 64 or `external_references[i].id` over 255. |\n| `TooMany` | `external_references` | More than 20 items. |\n| `TooManyDecimals` | `value` | More than two decimal places. |\n| `InvalidDate` | `start_date`, `end_date`, `updated_at`; the list's `end_date_from`, `end_date_to` | Not a date as `YYYY-MM-DD` (an impossible one such as `2026-02-30` included); `updated_at` not a date and time as the API writes it (`2026-10-23T12:00:00Z`, or with an offset). |\n| `DateTooEarly` | `start_date`, `end_date` | Before the year 1000. |\n| `DateTooLate` | `end_date` | A renewal by the term would pass 9999-12-31. |\n| `EndBeforeStart` | `end_date` | Before `start_date`. |\n| `InvalidTaxpayerId` | `counterparty_taxpayer_id` | Not a valid CPF or CNPJ (formatted or not). |\n| `NotAMember` | `owner_id` | Not an active member of the workspace. |\n| `TooManyKeys` | `metadata` | More than 50 keys. |\n| `InvalidKey` | `metadata` | A key that is not 1 to 40 letters, digits, `_`, `-` or `.`. |\n| `InvalidValue` | `metadata` | A value that is not plain text of at most 500 characters. |\n| `NumberUnderDateKey` | `metadata` | A number under a key ending in `_at`, or `stuck_since`, which the API would return as a date. |\n| `ListKeys` | `metadata` | Keys `0`, `1`, `2`…, which would read back as a list. |\n| `DuplicateReference` | `external_references` | Two items the database takes for one (accents, `ß` and `ss`); items equal case aside are merged, not refused. |\n| `StatusNotSettable` | `status` | A tracked contract sent `expired` or `terminated`, or a closed one sent `draft` or `active`: a contract is closed by terminating it and reopened by renewing it. |\n| `CannotReturnToDraft` | `status` | An `active` contract sent `draft`. |\n| `ExpiredNeedsPastEndDate` | `end_date` | New terms with status `expired` and no `end_date` before today. |\n| `TerminatedNeedsEndDate` | `end_date` | New terms with status `terminated` and no `end_date`. |\n| `UnsignableDocument` | — | New terms, or a first link, on a cancelled, declined or expired document. Terms or a link already on one stay editable. |\n| `DocumentIsAmendment` | — | Terms of its own for a document linked as an amendment: its terms are the changes it makes. |\n| `DocumentHasOwnTerms` | — | A link for a document that tracks a contract of its own: a document is a contract or an amendment, never both. |\n| `ParentHasNoTerms` | `parent_document_id` | The parent named has no contract terms, nor amends a contract that has. |\n| `ParentTerminated` | `parent_document_id` | The parent contract is terminated. |\n| `NoTermChanged` | — | An `amendment` that changes no term, also by dropping its last one. A `renewal` or `termination` may change none. |\n| `TermNotAllowed` | the term | A term the relation type cannot change: a `renewal` changes only `end_date` and `value`, a `termination` only `end_date`. |\n| `AmendmentApplied` | — | The amendment applied when its document was signed and can no longer change. |\n| `TermOrEndDateRequired` | — | A renewal sends neither `term` nor `end_date`. |\n| `TermMismatch` | `term` | Not the contract's current term: the renewal already happened. |\n| `ContractTerminated` | — | A renewal of a terminated contract. |\n| `EndDateRequired` | `end_date` | A renewal by the term, or a `renewal` link without `end_date`, of a contract without an end date. |\n| `EndDateNotAfterCurrent` | `end_date` | A renewal, or a `renewal` link, to a date not after the current end date. |\n| `AlreadyTerminated` | — | A termination of a contract already terminated. |\n\nA body that is not valid JSON is refused before it reaches these endpoints, with the platform's `400` and no `errors`. A link is also checked against what applying it today would give the parent, so a delta the contract would refuse comes back with the contract's own codes.",
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
        "description": "Records per page (max 100).",
        "schema": {
          "type": "integer",
          "maximum": 100,
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
      }
    },
    "examples": {
      "AssignmentCreateVirtualFull": {
        "summary": "Create without input (method: virtual, with verification & notification methods)",
        "value": {
          "method": "virtual",
          "signers": [
            {
              "id": "100000000000000000000001",
              "verification_method": "Email",
              "notification_methods": [
                "Email"
              ],
              "step": 1
            },
            {
              "id": "100000000000000000000036",
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
              "id": "100000000000000000000037",
              "verification_method": "Email",
              "notification_methods": [
                "Email"
              ],
              "step": 1
            },
            {
              "id": "100000000000000000000038",
              "verification_method": "Whatsapp",
              "notification_methods": [
                "Whatsapp"
              ],
              "step": 2
            }
          ],
          "entries": [
            {
              "page_id": "100000000000000000000006",
              "fields": [
                {
                  "signer_id": "100000000000000000000037",
                  "field_id": "100000000000000000000004",
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
                  "signer_id": "100000000000000000000038",
                  "field_id": "100000000000000000000004",
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
              "id": "100000000000000000000001",
              "step": 1
            },
            {
              "id": "100000000000000000000036",
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
              "id": "100000000000000000000037",
              "step": 1
            },
            {
              "id": "100000000000000000000038",
              "step": 2
            }
          ],
          "entries": [
            {
              "page_id": "100000000000000000000006",
              "fields": [
                {
                  "signer_id": "100000000000000000000037",
                  "field_id": "100000000000000000000004",
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
                  "signer_id": "100000000000000000000038",
                  "field_id": "100000000000000000000004",
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
            "id": "100000000000000000000039",
            "sender_email": "sender@example.com",
            "method": "virtual",
            "expires_at": "2021-09-30T21:00:00Z",
            "message": null,
            "signers": [
              {
                "id": "100000000000000000000001",
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
                "id": "100000000000000000000036",
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
                "id": "100000000000000000000040",
                "page": null,
                "signer": {
                  "id": "100000000000000000000001",
                  "full_name": "John Dove",
                  "email": "joe@example.com"
                },
                "field": {
                  "id": "100000000000000000000041",
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
                  "id": "100000000000000000000001",
                  "full_name": "John Dove",
                  "email": "joe@example.com",
                  "completed": false
                },
                {
                  "id": "100000000000000000000036",
                  "full_name": "Jane Doe",
                  "email": "jane@example.com",
                  "completed": false
                }
              ]
            },
            "signing_urls": [
              {
                "signer_id": "100000000000000000000001",
                "url": "https://api.assinafy.com.br/v1/sign/100000000000000000000042?email=joe@example.com"
              },
              {
                "signer_id": "100000000000000000000036",
                "url": "https://api.assinafy.com.br/v1/sign/100000000000000000000042?email=jane@example.com"
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
            "id": "100000000000000000000030",
            "sender_email": "sender@example.com",
            "method": "collect",
            "expires_at": "2021-09-30T21:00:00Z",
            "message": null,
            "signers": [
              {
                "id": "100000000000000000000037",
                "full_name": "Kennith Kuphal",
                "email": "person4@example.com",
                "verification_method": "Email",
                "notification_methods": [
                  "Email"
                ],
                "step": 1,
                "notified": true,
                "completed": false
              },
              {
                "id": "100000000000000000000038",
                "full_name": "Sonny Bayer",
                "email": "person5@example.com",
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
                "id": "100000000000000000000043",
                "page": {
                  "id": "100000000000000000000006",
                  "number": 1,
                  "height": 2100,
                  "width": 1275,
                  "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000042/pages/100000000000000000000006/download"
                },
                "signer": {
                  "id": "100000000000000000000037",
                  "full_name": "Kennith Kuphal",
                  "email": "person4@example.com"
                },
                "field": {
                  "id": "100000000000000000000004",
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
                "id": "100000000000000000000005",
                "page": {
                  "id": "100000000000000000000006",
                  "number": 1,
                  "height": 2100,
                  "width": 1275,
                  "download_url": "https://api.assinafy.com.br/v1/documents/100000000000000000000042/pages/100000000000000000000006/download"
                },
                "signer": {
                  "id": "100000000000000000000038",
                  "full_name": "Sonny Bayer",
                  "email": "person5@example.com"
                },
                "field": {
                  "id": "100000000000000000000004",
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
                  "id": "100000000000000000000037",
                  "full_name": "Kennith Kuphal",
                  "email": "person4@example.com",
                  "completed": false
                },
                {
                  "id": "100000000000000000000038",
                  "full_name": "Sonny Bayer",
                  "email": "person5@example.com",
                  "completed": false
                }
              ]
            },
            "signing_urls": [
              {
                "signer_id": "100000000000000000000037",
                "url": "https://api.assinafy.com.br/v1/sign/100000000000000000000042?email=person4@example.com"
              },
              {
                "signer_id": "100000000000000000000038",
                "url": "https://api.assinafy.com.br/v1/sign/100000000000000000000042?email=person5@example.com"
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
            "document_id": "100000000000000000000011",
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
              "document_id": "100000000000000000000011",
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
              "document_id": "100000000000000000000011",
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
                "id": "100000000000000000000011",
                "account_id": "100000000000000000000028",
                "template_id": null,
                "name": "Contrato de prestação de serviços.pdf",
                "status": "certificated",
                "artifacts": {
                  "original": "https://api.assinafy.com.br/v1/documents/100000000000000000000011/download/original"
                },
                "is_closed": true,
                "signing_url": "https://api.assinafy.com.br/v1/sign/100000000000000000000011",
                "verification_code": "1000000000000000000000000000000000000019",
                "verification_url": "https://www.assinafy.com.br/verificador/1000000000000000000000000000000000000019",
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
            "document_id": "100000000000000000000011",
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
            "document_id": "100000000000000000000011",
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
          "parent_document_id": "100000000000000000000011",
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
            "document_id": "100000000000000000000023",
            "document_name": "Aditivo 1 - Contrato de prestação de serviços.pdf",
            "parent_document_id": "100000000000000000000011",
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
            "document_id": "100000000000000000000023",
            "document_name": "Aditivo 1 - Contrato de prestação de serviços.pdf",
            "parent_document_id": "100000000000000000000011",
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
            "document_id": "100000000000000000000011",
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
                "document_id": "100000000000000000000023",
                "document_name": "Aditivo 1 - Contrato de prestação de serviços.pdf",
                "parent_document_id": "100000000000000000000011",
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
              "account:read": "Read the workspace's profile, theme and logo.",
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
