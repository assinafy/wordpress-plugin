# Contribuindo

*Português · [Read in English](CONTRIBUTING.en.md)*

## Preparando o ambiente

```bash
git clone https://github.com/assinafy/wordpress-plugin.git
cd wordpress-plugin
composer install
```

O `composer install` executa o [Strauss](https://github.com/BrianHenryIE/strauss) por meio de um hook
`post-install-cmd`, que copia o SDK para `vendor-prefixed/` e prefixa o logger PSR embutido
sob `Assinafy\WP\Vendor\`. O próprio SDK mantém o namespace `Assinafy\SDK\`. O plugin carrega `vendor-prefixed/autoload.php`, nunca
`vendor/autoload.php`, então **o plugin não inicializa enquanto o `composer install` não tiver rodado.**

Um WordPress completo para clicar e testar exige Docker:

```bash
npm install
npm run start          # http://localhost:8888, admin/password
npm run stop
```

## As verificações

```bash
composer phpcs             # WordPress coding standards: escaping, sanitização, nonces, i18n
composer phpcbf            # o autofixer do PHPCS, o único destes que edita seus arquivos
composer phpstan           # análise estática, nível 8
composer phpmd             # complexidade e código morto em src/, integrations/, addons/, assinafy.php e uninstall.php
composer test              # suíte de unidade, não exige WordPress
composer test:integration  # precisa de WP_TESTS_DIR, ou rode via wp-env

composer check             # phpcs, phpstan, phpmd e a suíte de unidade
```

`composer phpcs`, `composer phpstan` e `composer phpmd` são gates de merge e nunca podem falhar.
O time de revisão do wordpress.org rejeita com mais frequência por saída sem escape, entrada sem
sanitização e nonces ausentes, que é exatamente o que os dois primeiros pegam; o PHPMD pega o
método que cresceu além do ponto em que alguém ainda consegue lê-lo.

Nunca enfraqueça um gate para fazê-lo passar. Um apontamento do PHPMD é resolvido com refatoração,
ou respondido em `phpmd.xml.dist` com uma justificativa escrita e o escopo mais estreito que a
expresse — elevar o limite de uma regra é melhor que excluir a regra, e ambos são melhores que um
`@SuppressWarnings` inline.

A suíte de unidade roda sobre o autoloader do Composer mais o `tests/wp-stubs.php` e não precisa de
WordPress, banco de dados nem Docker. Mantenha assim: tudo que exigir um WordPress de verdade
pertence a `tests/Integration/`, que roda sob o `phpunit-integration.xml.dist`.

```bash
npm run test:php:unit
npm run test:php:integration
```

## Regras da casa

- A propriedade das integrações e o contrato público de adapters estão documentados em [docs/integrations.md](docs/integrations.md).
  Serviços do core não podem ramificar por nome de plugin host. A composição dos adapters embutidos fica em `integrations/`.
  Pacotes dedicados vivem em `addons/`, cada um com seu próprio README, readme.txt, licença e testes.
  Faça o build do core primeiro, depois `bin/build-addons.sh`; add-ons nunca podem entrar no ZIP do core.
  Testes de hosts gratuitos usam CF7/WPForms Lite de verdade. Marque dublês de hosts licenciados como testes de contrato
  e não afirme, a partir deles, certificação de runtime para Gravity Forms/Elementor Pro/WPForms Pro.
- PHP 8.2 é o piso. `declare(strict_types=1);` em todo arquivo, `final class` a menos que a
  extensão seja um requisito declarado, injeção via construtor e nenhum singleton exceto `Plugin`.
- `defined( 'ABSPATH' ) || exit;` é a primeira instrução executável de todo arquivo PHP.
- Toda leitura de `$_POST` e `$_GET` passa por `wp_unslash()` e depois é sanitizada. Todo echo é
  escapado no ponto de saída, nunca antes.
- Mensagens de exceção são escapadas onde são **exibidas**, não onde são lançadas.
- Nada traduzido pode rodar antes do `init`. Não existe chamada a `load_plugin_textdomain()` —
  o WordPress carrega as traduções just in time.
- `src/Documents/DocumentRecord.php` é o único arquivo autorizado a declarar uma chave de post
  meta, e `src/Settings.php` é o único autorizado a nomear uma option. As chaves pelas quais uma
  query precisa buscar são constantes em `DocumentRecord`; `src/Documents/DocumentIndex.php` usa
  essas constantes em vez de repetir as strings. O `uninstall.php` lê `Settings::OPTIONS` em vez de
  repetir a lista, e um teste garante que os dois concordam.
- Nenhuma dependência de runtime nova sem discussão. O plugin distribui o SDK e nada mais;
  em particular não distribui cliente HTTP algum, porque um cliente vendorizado colide com outros plugins.

## Trabalhando com a API

Aponte um site de desenvolvimento para o ambiente de testes (sandbox) selecionando-o nas
configurações do plugin, ou no `wp-config.php`:

```php
define( 'ASSINAFY_API_KEY', 'your-sandbox-key' );
define( 'ASSINAFY_ACCOUNT_ID', 'your-sandbox-account-id' );
```

Nunca faça commit de uma chave, de um account id ou de um e-mail real. Use `example.com` e
`example.test` em código, testes, fixtures e documentação.

Duas coisas sobre a API são fáceis de errar e valem a leitura antes de mudar qualquer coisa
que as toque:

- **Uma resposta `200` ainda pode ser um erro.** O corpo carrega o próprio `status`, e um `200`
  envolvendo `{"status": 422}` é uma falha. O `WpHttpClient` lança exceção nesse caso; não crie um
  caminho de código que confie apenas no status HTTP.
- **As entregas de webhook não são assinadas.** Não há HMAC nem segredo compartilhado. Uma entrega
  é um indício de que algo mudou, nunca uma fonte de verdade — busque o documento de novo e grave a
  partir da resposta.

## Pull requests

1. O `composer check` passa.
2. Comportamento novo vem com teste. Uma correção de bug vem com o teste que falha sem ela.
3. Métodos públicos carregam um docblock com os payloads de request e response quando tocam a
   API, condizente com o que a API de fato retorna.
4. O `CHANGELOG.md` ganha uma entrada em `## [Unreleased]`.
5. Descreva a mudança como ela está agora. Notas de versão e documentação não são um registro de
   como o código chegou até ali.

## Publicando uma versão

1. A versão aparece em três arquivos e eles precisam concordar: o header `Version:` em `assinafy.php`,
   a `ASSINAFY_VERSION` e o `Stable tag:` do `readme.txt`.
2. Mova as entradas de `## [Unreleased]` para a nova versão no `CHANGELOG.md`.
3. `bin/build-zip.sh` — ele se recusa a gerar o build se essas versões divergirem, se qualquer
   fonte do Guzzle chegar à árvore ou se o `vendor/` sem prefixo for empacotado.
4. Rode o Plugin Check contra a árvore gerada.
5. Marque a tag `v<version>` e faça push. Isso dispara o workflow de release.
