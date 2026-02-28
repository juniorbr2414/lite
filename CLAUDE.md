# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Idioma

Siempre responde al usuario en **español**.

## About

**Greenter Lite** is a minimal PHP library for generating and sending electronic tax invoices (comprobantes electrónicos) to SUNAT (Peru's tax authority). It wraps the core greenter packages into a single entry point.

## Commands

### Install dependencies
```bash
composer install
```

### Run all tests
```bash
vendor/bin/phpunit
```

### Run a single test file
```bash
vendor/bin/phpunit tests/Greenter/Factory/FeFactoryTest.php
```

### Run a single test method
```bash
vendor/bin/phpunit --filter testSendInvoice tests/Greenter/Factory/FeFactoryTest.php
```

### Run tests excluding integration tests (which require SUNAT connectivity)
```bash
vendor/bin/phpunit --exclude-group integration
```

### Run with coverage
```bash
vendor/bin/phpunit --coverage-clover build/logs/clover.xml
```

## Architecture

This library is a thin facade over four upstream packages:
- `greenter/core` — Model classes (`DocumentInterface`, response models, sale/summary/voided models)
- `greenter/xml` — XML builders (`InvoiceBuilder`, `NoteBuilder`, `SummaryBuilder`, `VoidedBuilder`)
- `greenter/xmldsig` — XML digital signing (`SignedXml`)
- `greenter/ws` — SOAP/web service clients and senders (`SoapClient`, `BillSender`, `SummarySender`, `ExtService`)

### Two entry points

**`See` class** (`src/Greenter/See.php`) — SOAP-based submission (legacy Sunat web services):
- Configure with `setClaveSOL()`, `setCertificate()`, `setService()`
- Sends documents via `send(DocumentInterface)` or pre-built XML via `sendXml()`/`sendXmlFile()`
- Uses `WsSenderResolver` to pick `BillSender` vs `SummarySender` based on document type
- Uses `XmlBuilderResolver` to find the appropriate builder by convention: `{ClassName}Builder` in `Greenter\Xml\Builder\` namespace

**`Api` class** (`src/Greenter/Api.php`) — REST API-based submission (SUNAT GRE / new API):
- Configure with `setApiCredentials()`, `setClaveSOL()`, `setCertificate()`
- Custom endpoints can be passed to constructor (useful for sandbox like nubefact GRE test)
- Returns `BaseResult`/`StatusResult` same as `See`

### Factory layer (`src/Greenter/Factory/`)

- **`FeFactory`** — coordinates builder + signer + sender; holds `lastXml` after signing
- **`XmlBuilderResolver`** — maps document class → XML builder class by naming convention; `Reversion` is special-cased to use `VoidedBuilder`
- **`WsSenderResolver`** — maps document class → `SummarySender` (for `Summary`, `Voided`, `Reversion`) or `BillSender`

### Document types and senders

| Document Class | Sender |
|---|---|
| `Invoice`, `Note` | `BillSender` (synchronous CDR) |
| `Summary`, `Voided`, `Reversion` | `SummarySender` (returns ticket for async polling) |
| `Despatch` | Via `Api` class only |

### Tests

- `tests/Greenter/Factory/FeFactoryBase.php` — base class with fixture helpers (company, invoice, note, summary, voided builders)
- Integration tests are tagged `@group integration` and require live SUNAT beta endpoints and the test certificate at `tests/Resources/SFSCert.pem`
- Non-integration tests (`FeFactoryTest`, `FeFactoryXmlTest`, `CeFactoryTest`) test XML generation only without network calls

### Adding support for a new document type

1. Add a model class in `greenter/core` (upstream package)
2. Add a builder class named `{ClassName}Builder` in `greenter/xml` (upstream package) — it will be auto-resolved by `XmlBuilderResolver`
3. If the document uses async submission, add its class to the `$summary` array in `WsSenderResolver`
