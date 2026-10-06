# REST request and response payloads

Source: [Assinafy OpenAPI](https://api.assinafy.com.br/v1/docs/openapi.json). Paths include `/v1`; SDK base URLs are `https://api.assinafy.com.br/v1` and `https://sandbox.assinafy.com.br/v1`.

Each operation includes its full published parameter, body, success and error schemas. JSON blocks are schemas, not requests: `required`, `enum`, `nullable`, constraints and examples describe the permitted payload. Binary bodies remain bytes. Examples use reserved contact domains. Supply credentials, codes and identifiers from your own connection.

Every `$ref` points to a complete shared definition in the [component dictionary](#component-dictionary). Keeping shared shapes together avoids inconsistent copies. For SDK signatures, examples and unwrapped return values, see [SDK reference](sdk-reference.md). Runtime account entitlements determine feature availability.

Root security: `[]`. Operation security overrides this setting; an empty list denotes a public operation.

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

List the activities recorded for a document. Each entry carries an event-specific `payload` snapshot (keys vary per event) and the request `origin` (`ip`, `user-agent`).

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

List the assignments belonging to the authenticated user's current account.

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
                    "description": "How the signer's identity is verified before signing. `Email` (default) sends a one-time code to the signer's email; `Whatsapp` sends the code over WhatsApp — the verification itself is not billed, but it requires the WhatsApp notification channel, so the signer costs 0.45 credits (available only on paid subscriptions); `DigitalCertificate` has the signer sign with their own ICP-Brasil certificate (A1/A3) — it requires the Digital Certificate feature, the signer must have a CPF or CNPJ in `government_id`, must be alone in its signing step, and is charged 2 credits per signer. A CPF requires that person's certificate (an e-CPF, or an e-CNPJ naming them as legal representative); a CNPJ requires an e-CNPJ for that company, from any of its representatives. Omit to default to `Email`.",
                    "type": "string",
                    "enum": [
                      "Email",
                      "Whatsapp",
                      "DigitalCertificate"
                    ],
                    "example": "Email"
                  },
                  "notification_methods": {
                    "description": "How the signer is told a signature is being requested. **Exactly one method per signer** — the array shape is historical, and sending two returns `400`. The method must also be compatible with `verification_method`: `Email` verification takes `Email`, `Whatsapp` verification takes `Whatsapp`, and `DigitalCertificate` takes either. WhatsApp incurs an additional cost and is available only on paid subscriptions. Omit it to have it inferred from `verification_method`, or `{\"Email\"}` when neither is sent. See **Verification & Notification Methods** for the full pairing table.",
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
              "description": "Required for `collect`: field placements per page.",
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
              "description": "ISO 8601; default is no expiration. Must be at least one hour in the future.",
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
| Digital certificate signature (per signer) | 2 credits |

Verification methods are not priced separately — every line in the `breakdown` is a notification or a signature. A `Whatsapp`-verified signer therefore shows up as a WhatsApp notification, because that channel is mandatory for that verification method. A `DigitalCertificate` signer adds the digital-certificate signature cost **on top of** its notification cost; it appears in the `breakdown` under the `SignatureDigitalCertificate` code.

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
                    "description": "Verification method to price. `Whatsapp` forces the WhatsApp notification, so it prices at 0.45 credits per signer; `DigitalCertificate` adds the per-signer signature cost on top of its notification.",
                    "type": "string",
                    "enum": [
                      "Email",
                      "Whatsapp",
                      "DigitalCertificate"
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

Resend the signature-request notification to a specific signer of an assignment.

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

Estimate the cost of resending the signature request to a signer.

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

## `PUT /v1/documents/{documentId}/assignments/{assignmentId}/reset-expiration`

Reset assignment expiration

Set a new expiration date for an assignment.

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
          "properties": {
            "expires_at": {
              "description": "New expiration date (ISO 8601). Must be at least one hour in the future.",
              "type": "string",
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

## `GET /v1/accounts/{accountId}/documents`

List documents

List documents of the workspace.

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
      "description": "Sort by `name` or `updated_at`.",
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

Get a document by its ID. `decline_reason` is only present when the access token belongs to the document's creator.

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

When `include_standard` is enabled, records of type `signature`, `initial` and `signatureDate` are also returned.

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
              "type": "string",
              "nullable": true
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
              "type": "string",
              "nullable": true
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

List the possible field types. `cpf` expects 11 digits; `cnpj` accepts 14-char values (letters A-Z allowed in positions 1–12 per the CNPJ Alfanumérico rule; check digits 13–14 stay numeric). Punctuation is ignored during validation.

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

## `GET /v1/users/self/notification-preferences`

Get my notification preferences

Which owner-facing document notifications the authenticated user receives by e-mail. All nine keys are always returned; everything defaults to `true`. Account and security e-mail (welcome, password reset, invitations, account deletion) is not configurable and never appears here.

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

Merges the supplied map into the authenticated user's preferences. Send only the keys you want to change — omitted keys keep their current value. Setting a key to `false` stops that e-mail for this user in every account they belong to. Returns the full map. An unknown code, a non-boolean value, or an empty body is rejected with 400 and nothing is written.

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
                "type": "string",
                "nullable": true
              },
              "scope": {
                "description": "The scope of the ACCESS token. `offline_access` is a request-time signal rather than a permission, so it never appears here even when it was requested.",
                "type": "string",
                "example": "documents:read"
              },
              "id_token": {
                "description": "A signed OIDC id_token (RS256). Present only when the openid scope was granted.",
                "type": "string",
                "nullable": true
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
                "type": "string",
                "example": "Maria Silva",
                "nullable": true
              },
              "email": {
                "type": "string",
                "format": "email",
                "nullable": true
              },
              "email_verified": {
                "type": "boolean",
                "nullable": true
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

Send a one-time access token (email/WhatsApp) to view a public document. Public endpoint.

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
    "content": {
      "application/json": {
        "schema": {
          "properties": {
            "email": {
              "type": "string",
              "format": "email"
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
            "$ref": "#/components/schemas/Envelope"
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

Update a signer's information.

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

Return the signer identified by the signer access code, including the `has_signature`/`has_initial`/`is_signature_reusable` flags.

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

## `GET /v1/signers/{signerId}/document`

Get signer's document

Return the document and the signer's assignment items, scoped to the signer access code.

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

Retrieve the document a signer has been invited to sign, using the signer access code. Marks the document as viewed. Returns 409 while the document is still being prepared (retry with backoff).

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

Sign a document with input fields (collect method): submit the signer's item values, completing their items. For **virtual** assignments the signer must first confirm their data via `PUT /v1/documents/{documentId}/signers/confirm-data`, otherwise this returns `400` (Signer data must be confirmed before signing). Signers whose verification method is `DigitalCertificate` cannot use this endpoint — their signature must be produced through `POST /v1/signers/certificate/start` + `/complete`, and this returns `400`. The request body is a JSON array of item entries. Uses the signer access code.

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
      "description": "Signer data must be confirmed before signing (virtual assignments), or the signer must sign with a digital certificate through the digital certificate endpoints."
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

List the documents a signer is party to. Uses the signer access code.

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

Search the documents a signer is party to (compact representation). Uses the signer access code.

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

Precomputed per-account document-funnel KPIs. `granularity=monthly` (default) returns the last 12 months, most recent first; `granularity=daily` with `month=YYYY-MM` returns that month's days. Series are zero-filled.

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

## `GET /v1/users/self/stats`

My cross-account document KPIs

The authenticated user's document-funnel KPIs summed across all accounts they currently belong to. `granularity=monthly` (default) returns the last 12 months, most recent first; `granularity=daily` with `month=YYYY-MM` returns that month's days. Series are zero-filled.

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
              "type": "string",
              "example": "ff8800",
              "nullable": true
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
              "type": "string",
              "example": "00aa55",
              "nullable": true
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

Generate a new document from a template, creating its assignment in the same call. Provide one signer entry per template role; the signers must already exist in the account.

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
                  "role_id",
                  "id"
                ],
                "properties": {
                  "role_id": {
                    "description": "The template role ID associated with the signer.",
                    "type": "string",
                    "example": "fa8c14f32d732271e071998246e"
                  },
                  "id": {
                    "description": "The signer ID. The signer must already exist in the account.",
                    "type": "string",
                    "example": "fa8c140cb49b79f940aab95fddd"
                  },
                  "verification_method": {
                    "description": "Verification method for this signer. If provided without notification_methods, the notification method is inferred. Defaults to Email. `DigitalCertificate` has the signer sign with their own ICP-Brasil certificate — it requires the Digital Certificate feature, the signer must have a CPF or CNPJ in `government_id` (a CPF requires that person's certificate; a CNPJ requires an e-CNPJ for that company), must be alone in its signing step, and is charged 2 credits per signer. Only applies to signers (not copy receivers).",
                    "type": "string",
                    "enum": [
                      "Email",
                      "Whatsapp",
                      "DigitalCertificate"
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
              "description": "Editor field values to bake into the generated document.",
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
              "description": "Assignment expiration date (ISO 8601). No expiration by default. Must be at least one hour in the future.",
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
                    "example": "fa8c14f32d732271e071998246e"
                  },
                  "verification_method": {
                    "description": "Verification method. If provided without notification_methods, the notification method is inferred. Defaults to Email. Verification is never billed on its own — the cost comes from the notification it is paired with, so `Whatsapp` verification requires the WhatsApp notification and costs 0.45 credits per signer. `DigitalCertificate` adds the per-signer signature cost (2 credits) on top of the notification cost.",
                    "type": "string",
                    "enum": [
                      "Email",
                      "Whatsapp",
                      "DigitalCertificate"
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

Returns the profile of the user owning the access token.

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

Retrieve the current webhook subscription for the account — which events it is subscribed to and the delivery configuration. Requires the `account:read` OAuth scope.

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

Update the webhook subscription settings for the account — which events are monitored, whether delivery is enabled, and the delivery/contact details. Requires the `webhooks:write` OAuth scope.

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
              "description": "The URL that will receive events.",
              "type": "string",
              "format": "uri",
              "example": "http://example.com?test=1"
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

Deactivate the webhook integration for the account. While inactive, no events are sent to the configured endpoint. Requires the `webhooks:write` OAuth scope.

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

Retrieve the delivery history for webhooks sent to the account's configured endpoint — use it to monitor status, debug failures, and verify payloads. Pagination is returned in the `X-Pagination-*` response headers. Requires the `documents:read` OAuth scope.

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

Manually retry a webhook delivery for a specific entry, without waiting for automatic retries. Returns the newly created dispatch entry.

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

## Component dictionary

References use the original OpenAPI `#/components/...` names.

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
            "type": "object",
            "example": null,
            "nullable": true
          }
        },
        "type": "object"
      },
      "ApiKey": {
        "properties": {
          "api_key": {
            "type": "string",
            "example": "mIpe_zdJfKUpMK9Va3XuYgzPXMxz49fIaRCWXseVkpVAX608A9j3i_D67qU5qW3M",
            "nullable": true
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
            "type": "string",
            "example": "17989206641",
            "nullable": true
          },
          "government_id": {
            "type": "string",
            "example": "15774136604",
            "nullable": true
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
            "type": "string",
            "format": "date-time",
            "example": null,
            "nullable": true
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
            "type": "string",
            "format": "email",
            "example": "person@example.com",
            "nullable": true
          },
          "whatsapp_phone_number": {
            "description": "E.164 format; normalized on save.",
            "type": "string",
            "example": "+5548999990000",
            "nullable": true
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
            "exclusiveMinimum": true,
            "example": 421,
            "minimum": 0
          },
          "height": {
            "description": "Height of the placement rectangle, in page-image pixels.",
            "type": "number",
            "format": "float",
            "exclusiveMinimum": true,
            "example": 45.86,
            "minimum": 0
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
            "exclusiveMinimum": true,
            "example": 22,
            "minimum": 0
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
            "type": "string",
            "example": null,
            "nullable": true
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
          "decline_reason": {
            "type": "string",
            "example": null,
            "nullable": true
          },
          "declined_by": {
            "oneOf": [
              {
                "$ref": "#/components/schemas/Signer"
              }
            ],
            "nullable": true
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
            "description": "Expanded assignment data when included via ?expand=assignment; null otherwise.",
            "nullable": true,
            "allOf": [
              {
                "$ref": "#/components/schemas/Assignment"
              }
            ]
          },
          "pages": {
            "type": "array",
            "items": {
              "$ref": "#/components/schemas/DocumentPage"
            }
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
            "example": "6401df46d6a6b0c692d9ec49"
          },
          "name": {
            "type": "string",
            "example": "Acme Inc."
          },
          "primary_color": {
            "type": "string",
            "example": "aabbcc",
            "nullable": true
          },
          "secondary_color": {
            "type": "string",
            "example": "112233",
            "nullable": true
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
            "type": "string",
            "nullable": true
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
            "type": "string",
            "example": "ff8800",
            "nullable": true
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
                "type": "string",
                "example": "Email",
                "nullable": true
              },
              "notification_methods": {
                "type": "array",
                "items": {
                  "type": "string"
                },
                "example": [
                  "Email"
                ],
                "nullable": true
              },
              "step": {
                "description": "Sequential signing step (defaults to 1).",
                "type": "integer",
                "example": 1,
                "nullable": true
              },
              "notified": {
                "type": "boolean",
                "nullable": true
              },
              "completed": {
                "description": "Only present in account-owner contexts.",
                "type": "boolean",
                "nullable": true
              },
              "notification_history": {
                "description": "Per-channel delivery history for this signer (email + WhatsApp), most-recent send order.",
                "type": "array",
                "items": {
                  "$ref": "#/components/schemas/NotificationHistoryEntry"
                },
                "nullable": true
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
            "type": "string",
            "nullable": true
          },
          "error_message": {
            "type": "string",
            "nullable": true
          },
          "sent_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-07-07T12:00:00Z",
            "nullable": true
          },
          "failed_at": {
            "type": "string",
            "format": "date-time",
            "example": null,
            "nullable": true
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
              }
            ],
            "nullable": true
          },
          "signer": {
            "description": "Signer responsible for this item.",
            "type": "object"
          },
          "field": {
            "description": "Field definition associated with the item.",
            "type": "object",
            "nullable": true
          },
          "display_settings": {
            "description": "Rendering metadata for the item. Collect items use the DisplaySettings schema; virtual and legacy items may return an empty or non-object value."
          },
          "value": {
            "description": "Captured value when completed.",
            "nullable": true
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
          "sender_email": {
            "type": "string",
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
            "type": "string",
            "format": "date-time",
            "example": null,
            "nullable": true
          },
          "message": {
            "type": "string",
            "nullable": true
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
        "description": "Cost breakdown for an assignment plus current account balances.",
        "properties": {
          "documents": {
            "description": "Documents consumed (always 1).",
            "type": "integer",
            "example": 1
          },
          "credits": {
            "description": "Total notification credits needed.",
            "type": "number"
          },
          "needs_extra_document": {
            "description": "True when the plan's document allowance is exhausted and an extra document will be charged from credits.",
            "type": "boolean"
          },
          "extra_document_cost": {
            "description": "Credits charged for the extra document when `needs_extra_document` is true.",
            "type": "number",
            "example": 1
          },
          "total_credits": {
            "type": "number"
          },
          "breakdown": {
            "type": "array",
            "items": {
              "$ref": "#/components/schemas/CostEstimateBreakdownItem"
            }
          },
          "document_balance": {
            "type": "number"
          },
          "credit_balance": {
            "type": "number"
          },
          "has_sufficient_resources": {
            "type": "boolean"
          },
          "blocking_reason": {
            "type": "string",
            "enum": [
              "PendingPayment",
              "InsufficientDocuments",
              "InsufficientCredits"
            ],
            "example": null,
            "nullable": true
          },
          "message": {
            "type": "string",
            "nullable": true
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
            "type": "string",
            "nullable": true
          },
          "message": {
            "description": "Default invitation message.",
            "type": "string",
            "nullable": true
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
          "url": {
            "description": "Webhook endpoint URL.",
            "type": "string",
            "example": "http://example.com?test=1",
            "nullable": true
          },
          "email": {
            "description": "Contact email for delivery notices.",
            "type": "string",
            "example": "person@example.com",
            "nullable": true
          },
          "updated_at": {
            "type": "string",
            "format": "date-time",
            "example": "2023-05-10T14:58:24Z",
            "nullable": true
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
          "endpoint": {
            "description": "URL that received the request.",
            "type": "string",
            "example": "https://example.com/webhook",
            "nullable": true
          },
          "payload": {
            "description": "JSON payload sent to the endpoint.",
            "type": "object",
            "nullable": true
          },
          "delivered": {
            "description": "Whether delivery succeeded.",
            "type": "boolean",
            "example": true
          },
          "http_status": {
            "description": "HTTP status returned (null if connection failed).",
            "type": "integer",
            "example": 200,
            "nullable": true
          },
          "response_body": {
            "description": "Endpoint response body, truncated to 2000 chars.",
            "type": "string",
            "example": "OK",
            "nullable": true
          },
          "error": {
            "description": "Delivery error message, if any.",
            "type": "string",
            "example": null,
            "nullable": true
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
            "type": "string",
            "example": "aabbcc",
            "nullable": true
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
            "type": "string",
            "example": "63ddb172402799bfc991d10d",
            "nullable": true
          },
          "agreement_code": {
            "description": "Agreement code printed on the document certificate.",
            "type": "string",
            "example": "550E8400-E29B-41D4-A716-446655440000",
            "nullable": true
          },
          "status": {
            "type": "string",
            "example": "certificated",
            "nullable": true
          },
          "page_count": {
            "type": "string",
            "example": "1",
            "nullable": true
          },
          "signer_count": {
            "type": "string",
            "example": "1",
            "nullable": true
          },
          "completed_count": {
            "type": "integer",
            "example": 1,
            "nullable": true
          },
          "completed_at": {
            "type": "string",
            "format": "date-time",
            "example": "2023-01-27T19:27:44Z",
            "nullable": true
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
        "description": "A document activity event.",
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
            "description": "Event-specific payload snapshot. Keys vary per event.",
            "type": "object",
            "nullable": true
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
            "type": "object",
            "nullable": true
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
            "description": "Unix timestamp when sent.",
            "type": "integer",
            "example": 1710000000
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
              },
              {
                "signer_id": "615212039529a822e24b6913",
                "url": "https://api.assinafy.com.br/v1/sign/615213edf8a58f132e1b2384?email=person@example.com"
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
        "description": "One-time signer access code used by signer-facing (Signing) endpoints instead of a user token.",
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
              "webhooks:write": "Change the workspace's webhook subscription (delivery URL, events, contact email) and deactivate it.",
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
