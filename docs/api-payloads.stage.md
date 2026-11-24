# Staging REST request and response payloads

Source: [staging OpenAPI](https://api.angry-pisco.stage.assinafy.com.br/v1/docs/openapi.json). Base URL: `https://api.angry-pisco.stage.assinafy.com.br/v1`.

Use the [production payload reference](api-payloads.md) for operations and shared components not listed here. The following operations replace those definitions on staging; each block contains its complete published request and response schemas. Resolve `$ref` through this file's component dictionary first, then the production dictionary. Availability on staging does not establish production availability.

SDK 2.9.2 exposes the production resources. Scheduled sends, watchers, signer capacities, notification history and envelope reminder estimates currently require direct requests through the configured WordPress HTTP transport; they have no dedicated SDK methods or native admin controls. Use a staging-only `Configuration` and `WpHttpClient` when developing against these endpoints. Never reuse a production credential or reinterpret stored production records on staging.

The ordinary WordPress connection screen selects production or sandbox; its hosted OAuth connection uses production. These staging definitions describe the REST contract, not an additional connection option.

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
                    "description": "How the signer's identity is verified before signing. `Email` (default) sends a one-time code to the signer's email; `Whatsapp` sends the code over WhatsApp — the verification itself is not billed, but it requires the WhatsApp notification channel, so the signer costs 0.45 credits (available only on paid subscriptions); `DigitalCertificate` has the signer sign with their own ICP-Brasil certificate (A1/A3) — it requires the Digital Certificate feature, the signer must have a CPF or CNPJ in `government_id`, must be alone in its signing step, needs `method: virtual` (a `collect` assignment with a `DigitalCertificate` signer returns `400`), and is charged 0.45 credits per signer. A CPF requires that person's certificate (an e-CPF, or an e-CNPJ naming them as legal representative); a CNPJ requires an e-CNPJ for that company, from any of its representatives. `IdDocumentFaceMatchBasic` verifies the signer from a photo of their government ID, a photo of them holding it and a liveness check — it requires the ID Document + Face Match feature, the signer must have a valid CPF in `government_id`, and is charged a per-signer fee. Omit to default to `Email`.",
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
                  },
                  "sign_as": {
                    "description": "The capacity the signer signs in (\"assinar como\"): a party, a witness, a guarantor… `Sign`, plain signing, when left out or `null`. Any other value prints on the signer's row of the certificate page as the sender's statement (for `Witness`, \"Indicado pelo remetente como testemunha\"); neither the invitation nor the signing page shows it. Fixed once the assignment is created and read back with **Get signer capacities**; no other answer or webhook carries it. Matched exactly; anything else, or two values for one signer, answers `400`.",
                    "type": [
                      "string",
                      "null"
                    ],
                    "enum": [
                      "Sign",
                      "Approve",
                      "Acknowledge",
                      "Party",
                      "Witness",
                      "Intervening",
                      "Receipt",
                      "IssuerEndorserGuarantor",
                      "IssuerEndorserGuarantorSurety",
                      "Surety",
                      "PartySurety",
                      "JointDebtor",
                      "PartyJointDebtor",
                      "ContractingParty",
                      "ContractedParty",
                      "Landlord",
                      "Tenant",
                      "Buyer",
                      "Seller",
                      "AttorneyInFact",
                      "LegalRepresentative",
                      "Employer",
                      "Employee",
                      null
                    ],
                    "example": "Witness"
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
| WhatsApp notification | depends on the plan, see the table below |
| Digital certificate signature (per signer) | 0.45 credits |
| Identity document verification (per signer) | depends on the plan, see the table below |

WhatsApp notification, per plan:

| Plan | Price per signer |
|------|------------------|
| Pro | 0.4 credits |
| Other plans and enterprise contracts | 0.45 credits |

Identity document verification, per plan:

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
                  "sign_as": {
                    "description": "Checked as **Create assignment** checks it (`400` for anything else); `null` reads as `Sign`; it doesn't change the price.",
                    "type": [
                      "string",
                      "null"
                    ],
                    "enum": [
                      "Sign",
                      "Approve",
                      "Acknowledge",
                      "Party",
                      "Witness",
                      "Intervening",
                      "Receipt",
                      "IssuerEndorserGuarantor",
                      "IssuerEndorserGuarantorSurety",
                      "Surety",
                      "PartySurety",
                      "JointDebtor",
                      "PartyJointDebtor",
                      "ContractingParty",
                      "ContractedParty",
                      "Landlord",
                      "Tenant",
                      "Buyer",
                      "Seller",
                      "AttorneyInFact",
                      "LegalRepresentative",
                      "Employer",
                      "Employee",
                      null
                    ]
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

## `GET /v1/documents/{documentId}/signer-capacities`

Get signer capacities

The capacity each signer of the document's assignment signs in, as the sender gave it with `sign_as` on **Create assignment**: `Sign` for a signer given none. In the order **Get document** lists the assignment's signers; an empty list for a document not sent yet. Reachable with the `documents:read` OAuth scope.

```json
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
      "description": "The signers' capacities",
      "content": {
        "application/json": {
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
                        "signer_id",
                        "sign_as"
                      ],
                      "properties": {
                        "signer_id": {
                          "type": "string",
                          "example": "615605f50e968054a5b7c9b8"
                        },
                        "sign_as": {
                          "type": "string",
                          "enum": [
                            "Sign",
                            "Approve",
                            "Acknowledge",
                            "Party",
                            "Witness",
                            "Intervening",
                            "Receipt",
                            "IssuerEndorserGuarantor",
                            "IssuerEndorserGuarantorSurety",
                            "Surety",
                            "PartySurety",
                            "JointDebtor",
                            "PartyJointDebtor",
                            "ContractingParty",
                            "ContractedParty",
                            "Landlord",
                            "Tenant",
                            "Buyer",
                            "Seller",
                            "AttorneyInFact",
                            "LegalRepresentative",
                            "Employer",
                            "Employee"
                          ],
                          "example": "Witness"
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
      "$ref": "#/components/responses/Forbidden"
    },
    "404": {
      "description": "The document is not in the workspace.",
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

## `GET /v1/documents/{documentId}/watchers`

List a document's watchers

The contacts set with **Set a document's watchers**, in the order they were set. A deleted contact is left out.

```json
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
      "description": "The document's watchers",
      "content": {
        "application/json": {
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
    "403": {
      "$ref": "#/components/responses/Forbidden",
      "description": "An OAuth application token, a deactivated membership or a role refuses it."
    },
    "404": {
      "description": "The document is not in the workspace.",
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

## `PUT /v1/documents/{documentId}/watchers`

Set a document's watchers

Replaces the document's watchers: up to 10 contacts of the workspace, each with an e-mail address, who follow the document without signing it. Each is e-mailed once the document ends: declined (naming who declined), expired or signed by all. The e-mail names the document and its sender, and nothing else of it: no link, no file. It is queued, so it goes out shortly after. Naming a contact here is what lets them receive it; the workspace's notification settings don't apply to them. A signer named as a watcher gets this e-mail besides their own. They can be set before the document is sent and changed until it ends; an empty list removes them all.

```json
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
            "watcher_ids"
          ],
          "properties": {
            "watcher_ids": {
              "description": "Contact ids, as **Create signer** answers them; one given twice counts once.",
              "type": "array",
              "items": {
                "type": "string",
                "minLength": 1
              },
              "maxItems": 10,
              "example": [
                "62d6ee35c7741ca4006b9e11"
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
      "description": "The document's watchers",
      "content": {
        "application/json": {
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
    "400": {
      "description": "`watcher_ids` missing or not a list of ids; more than 10; an id that is not a contact of the workspace or a contact without an e-mail address; or the document has ended (expired, signed by all, declined, cancelled or failed).",
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
            "message": "A document takes at most 10 watchers."
          }
        }
      }
    },
    "403": {
      "$ref": "#/components/responses/Forbidden",
      "description": "An OAuth application token, a deactivated membership or a role refuses it."
    },
    "404": {
      "description": "The document is not in the workspace.",
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

## `POST /v1/accounts/{accountId}/envelopes/{envelopeId}/signers/{signerId}/estimate-resend-cost`

Estimate envelope reminder cost

What reminding a signer of the whole envelope would cost, sending and charging nothing. The reminder is one message per notification method for all of the envelope's documents, so it costs what one document's reminder costs, whatever the number of documents, at the workspace's reminder prices: nothing by e-mail and the WhatsApp resend price by WhatsApp, unless its plan prices them otherwise. Answers as a document's reminder estimate. It does not check whether a reminder would be accepted now: the reminder itself refuses a closed envelope (`409`), and a signer with nothing left to sign or one reminded too recently or too often (`400`).

```json
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
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `GET /v1/documents/{documentId}/notification-history`

Get notification history with delivery times

Each signer of the document's assignment, in the order **Get document** lists them, with its `notification_history` as **Get document** gives it, each entry adding `delivered_at` and `opened_at`, `null` until then: an e-mail's first delivery and its first open or click; a WhatsApp message's delivery and read as WhatsApp last reported them. `opened_at` is a hint, not proof: a mail app or a link scanner can open a message before anyone reads it, and blocked images or WhatsApp read receipts turned off hide an open. An empty list for a document not sent yet. Reachable with the `documents:read` OAuth scope.

```json
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
      "description": "The signers' notification history",
      "content": {
        "application/json": {
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
                        "signer_id",
                        "notification_history"
                      ],
                      "properties": {
                        "signer_id": {
                          "type": "string",
                          "example": "615605f50e968054a5b7c9b8"
                        },
                        "notification_history": {
                          "type": "array",
                          "items": {
                            "required": [
                              "delivered_at",
                              "opened_at"
                            ],
                            "type": "object",
                            "allOf": [
                              {
                                "$ref": "#/components/schemas/NotificationHistoryEntry"
                              },
                              {
                                "properties": {
                                  "delivered_at": {
                                    "type": [
                                      "string",
                                      "null"
                                    ],
                                    "format": "date-time",
                                    "example": "2026-07-07T12:00:05Z"
                                  },
                                  "opened_at": {
                                    "type": [
                                      "string",
                                      "null"
                                    ],
                                    "format": "date-time",
                                    "example": "2026-07-07T12:30:00Z"
                                  }
                                },
                                "type": "object"
                              }
                            ]
                          }
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
      "$ref": "#/components/responses/Forbidden"
    },
    "404": {
      "description": "The document is not in the workspace.",
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

## `PUT /v1/public/documents/{documentId}/send-token`

Send access token for public document

Sends the signer this document's signature request again, by e-mail, with its existing link, and its verification code when the signer's verification method uses one: nothing is renewed. A `200` means the request was accepted, not that the e-mail was delivered. The document must be pending signature and within its deadline, and `recipient` must be the e-mail address of a signer of the document who has not signed yet and whose turn has come. A signer gets it through this route at most once every two minutes, whoever asks; an envelope's documents share the signer's one interval. A send that failed may still have gone out, so it counts, unless it was refused before sending. Public endpoint.

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
              "description": "The signer's e-mail address, as text.",
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
      "description": "The document is not pending signature, or its deadline has passed; `channel` or `recipient` is missing or not text; `channel` is not `email`; or the signer already signed, their step has not started (sequential signing), or they are not notified by e-mail.",
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
                    "example": "recipient must be a non-empty string."
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
      "description": "The document doesn't exist, or `recipient` is not one of its signers.",
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
                    "example": "Signer not found."
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
      "description": "The signer was sent their invitation through this route less than two minutes ago; `Retry-After` gives the seconds left.",
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
                    "example": "The signing link was just sent. Wait a couple of minutes before asking again."
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

## `POST /v1/documents/{documentId}/scheduled-sends`

Schedule an assignment

Keeps a **Create assignment** request and makes it at `send_at`, as the member who scheduled it, through the same checks and charges as **Create assignment** then. Nothing is sent or charged now, and nothing changes for the document: it can still be sent at once, and the scheduled send then fails when it goes. A document takes one pending scheduled send at a time, counting one being sent. The request is checked now as **Estimate assignment cost** checks it (the method, each signer's methods, the plan's features), and each signer named must be a contact of the workspace; the balance is checked when it is sent. A job checks every five minutes and makes the sends that are due. A send that can't be made then becomes `failed` with the reason, nothing is charged, and the member who scheduled it is e-mailed; if that member has left the workspace, it is not made as anyone else: it fails and the workspace's active owners are e-mailed. **List scheduled sends**, **Cancel a scheduled send** and **Send a scheduled send now** follow it.

```json
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
          "description": "A **Create assignment** body (`method`, `signers`, `entries`, `message`, `expires_at` and the rest, as that route takes them) plus `send_at`.",
          "required": [
            "send_at",
            "signers"
          ],
          "properties": {
            "send_at": {
              "description": "When to send: ISO 8601 with its offset (none means UTC), or a Unix time. From one hour to 90 days ahead.",
              "example": "2026-11-20T09:00:00-03:00",
              "oneOf": [
                {
                  "type": "string",
                  "format": "date-time"
                },
                {
                  "type": "integer"
                }
              ]
            },
            "method": {
              "description": "As in **Create assignment**; `virtual` when left out.",
              "type": "string",
              "enum": [
                "virtual",
                "collect"
              ],
              "example": "virtual"
            },
            "signers": {
              "description": "As in **Create assignment**; each must be a contact of the workspace.",
              "type": "array",
              "items": {
                "type": "object"
              }
            },
            "expires_at": {
              "description": "As in **Create assignment**, an absolute time (ISO 8601 or a Unix time), or null for none; at least two hours after `send_at`, since the request must still have an hour left when it is made.",
              "oneOf": [
                {
                  "type": "string",
                  "format": "date-time"
                },
                {
                  "type": "integer"
                },
                {
                  "type": "null"
                }
              ]
            }
          },
          "type": "object",
          "additionalProperties": true
        }
      }
    }
  },
  "responses": {
    "200": {
      "description": "The scheduled send",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/ScheduledSend"
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
      "description": "`send_at` missing, unreadable, less than an hour or more than 90 days ahead; an `expires_at` less than two hours after it; the document already has an assignment, or a scheduled send pending or being sent; or the request refused as **Estimate assignment cost** refuses it.",
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
            "message": "The scheduled send must be at least one hour ahead."
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
    "404": {
      "description": "The document is not in the workspace, or a signer named is not a contact of it.",
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

## `GET /v1/accounts/{accountId}/scheduled-sends`

List scheduled sends

The workspace's scheduled sends, assignments and envelopes, newest first, whatever their status. Paged: 20 per page by default; the `X-Pagination-Total-Count`, `X-Pagination-Page-Count`, `X-Pagination-Current-Page` and `X-Pagination-Per-Page` headers describe the full list. An OAuth app needs `documents:write` here, not only `documents:read`: a failed send's `failure_reason` can say the workspace's balance ran out or a member left, which an app learns only from a request it can make.

```json
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
      "name": "document_id",
      "in": "query",
      "description": "Only the sends of this document.",
      "required": false,
      "schema": {
        "type": "string",
        "example": "615601fab04c0a3147bb1246"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The workspace's scheduled sends",
      "content": {
        "application/json": {
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
                      "$ref": "#/components/schemas/ScheduledSend"
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
      "description": "`document_id` not text.",
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
            "message": "Give document_id as a document id.",
            "errors": [
              {
                "field": "document_id",
                "code": "Invalid",
                "message": "Give document_id as a document id."
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

## `POST /v1/accounts/{accountId}/scheduled-sends`

Schedule an envelope

Keeps a **Create envelope** request and makes it at `send_at`, as the member who scheduled it, through the same checks and charges as **Create envelope** then. Nothing is created, sent or charged now, and nothing changes for its documents: each can still be sent at once, and the scheduled send then fails when it goes. A document takes one pending scheduled send at a time, counting one being sent. The request is checked now as **Estimate envelope cost** checks it (`403` with code `PlanRestricted` without the Envelopes feature, `400` with `errors` on the same fields); the balance is checked when it is sent. A job checks every five minutes and makes the sends that are due; failures go as for **Schedule an assignment**: `failed` with the reason, nothing charged, the member who scheduled it e-mailed, or the workspace's active owners if that member has left. An `Idempotency-Key` is not read here: each call keeps one send. An assignment is scheduled on its document (**Schedule an assignment**).

```json
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
          "description": "A **Create envelope** body plus `send_at`; each document's `expires_at` at least two hours after `send_at`.",
          "allOf": [
            {
              "$ref": "#/components/schemas/EnvelopeCreateRequest"
            },
            {
              "required": [
                "send_at"
              ],
              "properties": {
                "send_at": {
                  "description": "When to send: ISO 8601 with its offset (none means UTC), or a Unix time. From one hour to 90 days ahead.",
                  "example": "2026-11-20T09:00:00-03:00",
                  "oneOf": [
                    {
                      "type": "string",
                      "format": "date-time"
                    },
                    {
                      "type": "integer"
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
  "responses": {
    "200": {
      "description": "The scheduled send",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/ScheduledSend"
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
      "description": "`send_at` missing, unreadable, less than an hour or more than 90 days ahead; a document's `expires_at` less than two hours after it; a document already assigned or in a scheduled send pending or being sent; or the request refused as **Estimate envelope cost** refuses it, in `errors`.",
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
            "message": "The scheduled send must be at least one hour ahead."
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
    "401": {
      "$ref": "#/components/responses/Unauthorized"
    },
    "500": {
      "$ref": "#/components/responses/ServerError"
    }
  }
}
```

## `PUT /v1/accounts/{accountId}/scheduled-sends/{scheduledSendId}/cancel`

Cancel a scheduled send

Cancels a `pending` scheduled send; nothing is sent or charged. A send already being sent, sent, failed or cancelled answers `409`.

```json
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
      "name": "scheduledSendId",
      "in": "path",
      "description": "The scheduled send ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The cancelled scheduled send",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/ScheduledSend"
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
      "description": "The workspace does not exist, or the scheduled send is not the workspace's.",
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
            "message": "Scheduled send not found."
          }
        }
      }
    },
    "409": {
      "description": "Only a `pending` scheduled send can be cancelled.",
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
            "status": 409,
            "message": "Only a pending scheduled send can be cancelled."
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

## `PUT /v1/accounts/{accountId}/scheduled-sends/{scheduledSendId}/send`

Send a scheduled send now

Makes a `pending` scheduled send's request now rather than at `send_at`: as **Create assignment** or **Create envelope** would make it then, as the member who scheduled it, with the same checks and charges. A refusal answers as that route would answer the member who scheduled it: no documents or credits left, a signer removed, the document already sent, the plan without Envelopes, a `max_total_credits` below the cost now (`409` `CreditCeilingExceeded`), that member gone from the workspace (`400`) or the workspace's deletion pending (`403`); the send then stays `pending`, to go at `send_at` unless cancelled, and no one is e-mailed. A send already being sent, sent, failed or cancelled answers `409`.

```json
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
      "name": "scheduledSendId",
      "in": "path",
      "description": "The scheduled send ID.",
      "required": true,
      "schema": {
        "type": "string"
      }
    }
  ],
  "responses": {
    "200": {
      "description": "The scheduled send, `sent`, with the assignment or envelope it created",
      "content": {
        "application/json": {
          "schema": {
            "type": "object",
            "allOf": [
              {
                "$ref": "#/components/schemas/Envelope"
              },
              {
                "properties": {
                  "data": {
                    "$ref": "#/components/schemas/ScheduledSend"
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
      "description": "The request refused as **Create assignment** or **Create envelope** refuses it, or the member who scheduled it has left the workspace. The send stays `pending`.",
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
            "message": "Account does not have enough documents."
          }
        }
      }
    },
    "403": {
      "description": "The workspace's plan lacks a feature the request uses (`PlanRestricted`), or an OAuth app's token lacks `documents:write` (`InsufficientScope`); also a deactivated membership, a role, or the workspace's deletion pending. The send stays `pending`.",
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
    "404": {
      "description": "The workspace does not exist, or the scheduled send is not the workspace's; or the document an assignment sends no longer exists (`Document not found.`), and the send stays `pending`.",
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
            "message": "Scheduled send not found."
          }
        }
      }
    },
    "409": {
      "description": "Only a `pending` scheduled send can be sent now; or the request's `max_total_credits` is below what it costs now (`CreditCeilingExceeded`, nothing charged), and the send stays `pending`.",
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
                  "code": {
                    "description": "Only for the ceiling.",
                    "type": "string",
                    "enum": [
                      "CreditCeilingExceeded"
                    ]
                  },
                  "total_credits": {
                    "description": "With `CreditCeilingExceeded`: what the request costs now.",
                    "type": "number",
                    "example": 1.5
                  },
                  "max_total_credits": {
                    "description": "With `CreditCeilingExceeded`: the ceiling it was scheduled with.",
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
            "message": "Only a pending scheduled send can be sent now."
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
      "description": "A digital-certificate signer has not yet confirmed their data (`SignerDataUnconfirmed`) or accepted the terms, the signer's step has not started yet (sequential signing), or, with the code of a document of an envelope, `document_id` is no document id.",
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
                  },
                  "code": {
                    "type": "string",
                    "enum": [
                      "SignerDataUnconfirmed"
                    ],
                    "example": "SignerDataUnconfirmed"
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

Sign a document with input fields (collect method): submit the signer's item values, completing their items. An item left out is filled when it can be: a read-only field (the signature date) takes its own value and an optional one stays empty; one the signer must give is refused. Signing again after the signer has signed changes nothing and returns `200` with the current assignment, even once that signature closed the document: a retry after a lost response is safe. See **Signing In Your App** for the value of each field type. For **virtual** assignments the signer must first confirm their data via `PUT /v1/documents/{documentId}/signers/confirm-data`, otherwise this returns `400` (Signer data must be confirmed before signing). On a `collect` assignment the signer must have both a `signature` and an `initial` image stored (`POST /v1/signature`), even when the document has no `initial` field; otherwise this returns `400`. Signers whose verification method is `DigitalCertificate` cannot use this endpoint — their signature must be produced through `POST /v1/signers/certificate/start` + `/complete`, and this returns `400`. The request body is a JSON array of item entries; on a `virtual` assignment it is ignored, so send an empty array. Uses the signer access code. With the code of a document of an envelope, it acts on the signer's request on the document the route names, if that document is in the same envelope, carrying the code's e-mail or WhatsApp verification there.

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
            "description": "One item entry. Collect method only: a virtual assignment takes an empty array.",
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
                "description": "String representation of the value. A `signature` or `initial` item is stamped with the signer's stored image of that type, not with this value. A `signatureDate` item ignores it: the server records the date and time of signing, in UTC-3 (São Paulo time).",
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
      "description": "Signing result: the assignment as the signer reads it in their list of documents, with no `signing_urls`, only their own `items`, and the other parties' contact details and ID number null.",
      "content": {
        "application/json": {
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
      "description": "Signer data must be confirmed before signing (virtual assignments; `SignerDataUnconfirmed`), the signer has no stored `signature` or `initial` image (collect assignments), the signer must sign with a digital certificate through the digital certificate endpoints (`DigitalCertificateRequired`), the signer's code or identity check is not verified (`EmailVerificationRequired`, `WhatsappVerificationRequired`, `IdentityVerificationRequired`), an entry names a page, field or item that is not one of this signer's, the entries leave out a field the signer must give (named in the message), or the signer's step has not started yet (sequential signing). Nothing is signed when an entry is refused.",
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
                  },
                  "code": {
                    "type": "string",
                    "enum": [
                      "SignerDataUnconfirmed",
                      "DigitalCertificateRequired",
                      "EmailVerificationRequired",
                      "WhatsappVerificationRequired",
                      "IdentityVerificationRequired"
                    ],
                    "example": "SignerDataUnconfirmed"
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
      "description": "A listed document is not a virtual document of this signer, is not pending signature, waits for an earlier signing step, or, for the code of a document of an envelope, is not part of that envelope; the signer must first confirm their data (`SignerDataUnconfirmed`); or the signer signs with a digital certificate (`DigitalCertificateRequired`).",
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
                  },
                  "code": {
                    "type": "string",
                    "enum": [
                      "SignerDataUnconfirmed",
                      "DigitalCertificateRequired"
                    ],
                    "example": "SignerDataUnconfirmed"
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

## `PUT /v1/signers/accept-terms`

Accept terms (signer)

Record that the signer accepted the terms of use (https://www.assinafy.com.br/termos-de-uso; `has_accepted_terms: true` on `confirm-data` records it too). Enforced only for `DigitalCertificate` signers, who are refused the document with `400` (`TermsNotAccepted`) until they accept; for the other methods it is recorded as evidence. Uses the signer access code.

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
      "description": "The signer's verification is not done yet (e.g. the e-mail code), a digital-certificate signer has not confirmed their data (`SignerDataUnconfirmed`), or the signer's step has not started yet (sequential signing).",
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
                  },
                  "code": {
                    "type": "string",
                    "enum": [
                      "SignerDataUnconfirmed"
                    ],
                    "example": "SignerDataUnconfirmed"
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

Upload the signer's signature (or initials) image as the raw request body, a PNG or JPEG file, with no size limit. The collect method stamps the `signature` and `initial` items with these images and needs both before signing; the virtual method draws the signature on the certificate page when there is one. Uses the signer access code. The image is stored on the signer, one per `type`, and serves all of their documents: an upload replaces the previous image of its type, and a document is stamped with the image stored when it is certificated, so documents signed earlier and not yet certificated get the new one too. An image larger than 1000 px on a side is scaled down to fit; transparency is kept.

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
      "description": "Image type.",
      "schema": {
        "type": "string",
        "enum": [
          "signature",
          "initial"
        ]
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
      },
      "image/jpeg": {
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
      "description": "The image is not a PNG or JPEG file (`code` `SignatureImageInvalid`), or it is blank (`SignatureImageBlank`); or the signer's step has not started yet (sequential signing).",
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

## `GET /v1/signers/{signerId}/documents`

List signer's documents

List the documents a signer is party to. Accepts either the signer access code or the account's own credentials (API key or bearer token) — with account credentials, the signer must belong to that account's workspace. With the signer access code, `signerId` must be the code's own signer (otherwise `403`), only documents whose signing step has been reached are listed (for the code of a document of an envelope, only that envelope's documents), and each assignment leaves out `signing_urls`, has only the signer's own `items`, and gives every other signer and copy receiver, in `signers`, `copy_receivers` and `summary.signers`, with `email`, `whatsapp_phone_number` and `government_id` null, `notification_history` empty, and `identity_verification_status` null where present.

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
      "description": "With a signer access code, the signer's step has not started yet (sequential signing), or a digital-certificate signer has not yet confirmed their data (`SignerDataUnconfirmed`) or accepted the terms; or `envelope_id` is not text, an entry of `errors`.",
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
                  "code": {
                    "type": "string",
                    "enum": [
                      "SignerDataUnconfirmed"
                    ],
                    "example": "SignerDataUnconfirmed"
                  }
                },
                "type": "object"
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
      "description": "With a signer access code, the signer's step has not started yet (sequential signing), or a digital-certificate signer has not yet confirmed their data (`SignerDataUnconfirmed`) or accepted the terms; or `envelope_id` is not text, an entry of `errors`.",
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
                  "code": {
                    "type": "string",
                    "enum": [
                      "SignerDataUnconfirmed"
                    ],
                    "example": "SignerDataUnconfirmed"
                  }
                },
                "type": "object"
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

## Component dictionary

```json
{
  "components": {
    "schemas": {
      "ScheduledSend": {
        "description": "A **Create assignment** or **Create envelope** request kept until `send_at`, then made as that route makes it, by the member who scheduled it.",
        "required": [
          "id",
          "type",
          "document_ids",
          "status",
          "send_at",
          "assignment_id",
          "envelope_id",
          "failure_reason",
          "created_at",
          "processed_at"
        ],
        "properties": {
          "resource": {
            "type": "string",
            "example": "scheduled_send"
          },
          "id": {
            "type": "string",
            "example": "64f1a2b3c4d5e6f708192a3b"
          },
          "type": {
            "description": "What it creates.",
            "type": "string",
            "enum": [
              "assignment",
              "envelope"
            ],
            "example": "assignment"
          },
          "document_ids": {
            "description": "The documents it sends: one for an assignment, the envelope's for an envelope. A deleted document is left out.",
            "type": "array",
            "items": {
              "type": "string"
            },
            "example": [
              "615601fab04c0a3147bb1246"
            ]
          },
          "status": {
            "description": "`pending` until `send_at` (or **Send a scheduled send now**); `processing` while its request is being made; then `sent` (the request was made; its invitations go out right after), `failed` or, if cancelled while pending, `cancelled`. A send whose run was interrupted while `processing` made nothing, and fails within about an hour.",
            "type": "string",
            "enum": [
              "pending",
              "processing",
              "sent",
              "cancelled",
              "failed"
            ],
            "example": "pending"
          },
          "send_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-11-20T12:00:00Z"
          },
          "assignment_id": {
            "description": "The assignment it created, once an `assignment` is `sent`.",
            "type": [
              "string",
              "null"
            ],
            "example": null
          },
          "envelope_id": {
            "description": "The envelope it created, once an `envelope` is `sent`; `null` again once the envelope is deleted.",
            "type": [
              "string",
              "null"
            ],
            "example": null
          },
          "failure_reason": {
            "description": "Why it was not sent, once `failed`: the refusal the create route answered then (no documents or credits left, a signer removed, the document already sent, the plan without Envelopes), that the workspace is being deleted, or that the member who scheduled it left the workspace. Written in Portuguese.",
            "type": [
              "string",
              "null"
            ],
            "example": null
          },
          "created_at": {
            "type": "string",
            "format": "date-time",
            "example": "2026-11-14T18:00:00Z"
          },
          "processed_at": {
            "description": "When it was sent, failed or cancelled.",
            "type": [
              "string",
              "null"
            ],
            "format": "date-time",
            "example": null
          }
        },
        "type": "object"
      }
    },
    "examples": {
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
                "url": "https://api.angry-pisco.stage.assinafy.com.br/v1/sign/615213edf8a58f132e1b2384?email=joe@example.com"
              },
              {
                "signer_id": "615605f50e968054a5b7c9b9",
                "url": "https://api.angry-pisco.stage.assinafy.com.br/v1/sign/615213edf8a58f132e1b2384?email=jane@example.com"
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
                  "download_url": "https://api.angry-pisco.stage.assinafy.com.br/v1/documents/615213edf8a58f132e1b2384/pages/615213ed81b071f4293b2fc2/download"
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
                  "download_url": "https://api.angry-pisco.stage.assinafy.com.br/v1/documents/615213edf8a58f132e1b2384/pages/615213ed81b071f4293b2fc2/download"
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
                "url": "https://api.angry-pisco.stage.assinafy.com.br/v1/sign/615213edf8a58f132e1b2384?email=person@example.com"
              }
            ]
          }
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
                  "original": "https://api.angry-pisco.stage.assinafy.com.br/v1/documents/66b2f0e1c3a4d5e6f7a8b9c0/download/original"
                },
                "is_closed": true,
                "signing_url": "https://api.angry-pisco.stage.assinafy.com.br/v1/sign/66b2f0e1c3a4d5e6f7a8b9c0",
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
      }
    },
    "securitySchemes": {
      "oauth2": {
        "type": "oauth2",
        "description": "OAuth 2.1 authorization code flow with mandatory PKCE (S256), for applications and AI assistants acting in a user's workspace with that user's permission — as opposed to `apiKeyAuth`/`bearerAuth`, which authenticate the workspace or user directly. A token carries only the scopes the user approved and works for one workspace. Endpoints are published by the authorization server at `https://auth.frosty-treacle.stage.assinafy.com.br/.well-known/oauth-authorization-server`. An OAuth token cannot reach billing, account lifecycle, credential management or admin surfaces regardless of scope. A missing scope answers `403` with `WWW-Authenticate: Bearer error=\"insufficient_scope\"` naming it. See **OAuth Integration Guide** for the full flow.",
        "flows": {
          "authorizationCode": {
            "authorizationUrl": "https://auth.frosty-treacle.stage.assinafy.com.br/oauth/authorize",
            "tokenUrl": "https://api.elated-rosettes.stage.assinafy.com.br/v1/oauth/token",
            "refreshUrl": "https://api.elated-rosettes.stage.assinafy.com.br/v1/oauth/token",
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
