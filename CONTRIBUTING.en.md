# Contributing

*[Leia em português](CONTRIBUTING.md) · English*

## Getting set up

```bash
git clone https://github.com/assinafy/wordpress-plugin.git
cd wordpress-plugin
composer install
```

`composer install` runs [Strauss](https://github.com/BrianHenryIE/strauss) through a
`post-install-cmd` hook, which copies the SDK into `vendor-prefixed/` and scopes its bundled PSR logger
under `Assinafy\WP\Vendor\`. The SDK itself retains the `Assinafy\SDK\` namespace. The plugin loads `vendor-prefixed/autoload.php`, never
`vendor/autoload.php`, so **the plugin will not boot until `composer install` has run.**

A full WordPress to click around in needs Docker:

```bash
npm install
npm run start          # http://localhost:8888, admin/password
npm run stop
```

## The checks

```bash
composer phpcs             # WordPress coding standards: escaping, sanitising, nonces, i18n
composer phpcbf            # the PHPCS autofixer, the only one of these that edits your files
composer phpstan           # static analysis, level 8
composer phpmd             # complexity and dead code in src/, integrations/, addons/, assinafy.php and uninstall.php
composer test              # unit suite, no WordPress required
composer test:integration  # needs WP_TESTS_DIR, or run it through wp-env

composer check             # phpcs, phpstan, phpmd and the unit suite
```

`composer phpcs`, `composer phpstan` and `composer phpmd` are merge gates and are never allowed
to fail. The wordpress.org review team rejects most often for unescaped output, unsanitised input
and missing nonces, which is exactly what the first two catch; PHPMD catches the method that has
grown past the point anyone can read it.

Never weaken a gate to make it pass. A PHPMD finding is refactored away, or answered in
`phpmd.xml.dist` with a written reason and the narrowest scope that expresses it — a raised
threshold on one rule beats excluding the rule, and both beat an inline `@SuppressWarnings`.

The unit suite runs on Composer's autoloader plus `tests/wp-stubs.php` and needs no WordPress,
no database and no Docker. Keep it that way: anything requiring a real WordPress belongs in
`tests/Integration/`, which runs under `phpunit-integration.xml.dist`.

```bash
npm run test:php:unit
npm run test:php:integration
```

## House rules

- Integration ownership and the public adapter contract are documented in [docs/integrations.en.md](docs/integrations.en.md).
  Core services must not branch on host plugin names. Bundled adapter composition belongs in `integrations/`.
  Dedicated packages live in `addons/`, each with its own README, readme.txt, license and tests.
  Build core first, then `bin/build-addons.sh`; add-ons must never enter the core ZIP.
  Free-host tests use actual CF7/WPForms Lite. Mark licensed-host doubles as contract tests,
  and do not claim Gravity Forms/Elementor Pro/WPForms Pro runtime certification from them.
- PHP 8.2 is the floor. `declare(strict_types=1);` in every file, `final class` unless
  extension is a stated requirement, constructor injection, and no singleton except `Plugin`.
- `defined( 'ABSPATH' ) || exit;` is the first executable statement of every PHP file.
- Every `$_POST` and `$_GET` read is `wp_unslash()`-ed and then sanitised. Every echo is escaped
  at the point of output, never earlier.
- Exception messages are escaped where they are **displayed**, not where they are thrown.
- Nothing translated may run before `init`. There is no `load_plugin_textdomain()` call —
  WordPress loads translations just in time.
- `src/Documents/DocumentRecord.php` is the only file allowed to declare a post-meta key, and
  `src/Settings.php` is the only file allowed to name an option. The keys a query has to
  search by are constants on `DocumentRecord`; `src/Documents/DocumentIndex.php` uses those
  rather than repeating the strings. `uninstall.php` reads `Settings::OPTIONS` rather than
  repeating the list, and a test asserts the two agree.
- No new runtime dependency without a discussion. The plugin ships the SDK and nothing else;
  in particular it ships no HTTP client, because a vendored one collides with other plugins.

## Working against the API

Point a development site at the sandbox by selecting it in the plugin settings, or in
`wp-config.php`:

```php
define( 'ASSINAFY_API_KEY', 'your-sandbox-key' );
define( 'ASSINAFY_ACCOUNT_ID', 'your-sandbox-account-id' );
```

Never commit a key, an account id, or a real email address. Use `example.com` and
`example.test` in code, tests, fixtures and documentation.

Two things about the API are easy to get wrong and are worth reading before changing anything
that touches it:

- **A `200` response can still be an error.** The body carries its own `status`, and a `200`
  wrapping `{"status": 422}` is a failure. `WpHttpClient` raises on it; do not add a code path
  that trusts the HTTP status alone.
- **Webhook deliveries are unsigned.** There is no HMAC and no shared secret. A delivery is a
  hint that something changed, never a source of truth — re-fetch the document and write from
  the response.

## Pull requests

1. `composer check` passes.
2. New behaviour comes with a test. A bug fix comes with the test that fails without it.
3. Public methods carry a docblock with the request and response payload when they touch the
   API, matching what the API actually returns.
4. `CHANGELOG.md` gets an entry under `## [Unreleased]`.
5. Describe the change as it now is. Release notes and documentation are not a record of how
   the code got there.

## Releasing

1. The version appears in three files and they must agree: the `Version:` header in `assinafy.php`,
   `ASSINAFY_VERSION`, and `Stable tag:` in `readme.txt`.
2. Move the `## [Unreleased]` entries under the new version in `CHANGELOG.md`.
3. `bin/build-zip.sh` — it refuses to build if those versions disagree, if any Guzzle source
   reached the tree, or if the unprefixed `vendor/` was packaged.
4. Run Plugin Check against the built tree.
5. Tag `v<version>` and push. That fires the release workflow.
