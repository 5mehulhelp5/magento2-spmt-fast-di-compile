# Magento 2 Fast DI Compile module

Companion Magento module for [speedupmate/di-compiler](https://github.com/speedupmate/di-compiler/), a Rust replacement for Magento's `bin/magento setup:di:compile`.

This module keeps the normal Magento command name and swaps in the Rust compiler when the binary is available.

## What it does

- Replaces Magento's `setup:di:compile` command loader entry with a fast-di-compile wrapper.
- Runs the Rust binary at `rust/di-compiler/target/release/fast-di-compile`.
- Passes Magento's project root to the binary with `--magento-root`.
- Forwards supported fast-di-compile options from Magento CLI.
- Constrains forwarded path options to paths inside the Magento project root.
- Uses the Magento CLI PHP binary for fallback reflection.
- Runs the Rust compiler with a 15-second timeout and sanitized console output.
- Falls back to Magento's standard PHP compiler when the binary is missing or untrusted.
- Provides `--standard` to run Magento's standard PHP compiler explicitly.

## Get started

You need a running Magento installation and a built `fast-di-compile` binary.

- Magento or Adobe Commerce.
- This module installed and enabled.
- The Rust compiler binary built from [speedupmate/di-compiler](https://github.com/speedupmate/di-compiler/).

### 1. Installation

For local development in this repository, the package is installed through the root Composer path repository:

```bash
composer require spmt/magento2-spmt-fast-di-compile:dev-develop
bin/magento module:enable Spmt_FastDiCompile
```

The module source lives in `./src`.

### 2. Build the Rust compiler

From the Magento project root:

```bash
cd rust/di-compiler
cargo build --release -p fast-di-compile
```

The module expects the executable at:

```text
rust/di-compiler/target/release/fast-di-compile
```

See [speedupmate/di-compiler](https://github.com/speedupmate/di-compiler/) for Docker build instructions and host-platform notes.

### 3. Compile your Magento project

Run the normal Magento command:

```bash
bin/magento setup:di:compile
```

If the Rust binary exists, is executable, and passes the trust checks, this module runs `fast-di-compile`.

To force Magento's standard PHP compiler:

```bash
bin/magento setup:di:compile --standard
```

## Useful options

| Option | What it does |
| --- | --- |
| `--standard` | Run Magento's standard PHP compiler instead of the Rust compiler. |
| `--output var/tmp/fast-di-output` | Set the generated output folder. Must resolve inside the Magento project root. |
| `--jobs 8` | Set parallel workers. Accepted range is `1` through `256`. |
| `--fallback-php /usr/local/bin/php` | Set the PHP executable used by the Rust compiler for fallback reflection. Must resolve to the same PHP binary running Magento CLI. |
| `--incremental` | Enable incremental compilation. |
| `--dry-run` | Run without writing generated files. |
| `--validate` | Validate output against PHP ground truth. |
| `--php-generated generated` | Set the PHP ground-truth generated directory used with `--validate`. Must resolve inside the Magento project root. |
| `--compare-archive` | Compare generated output against an archive baseline. |
| `--archive-root var/tmp/magento-di-baseline` | Set the archive root containing `_code` and `_metadata`. Must resolve inside the Magento project root. |
| `--compare-report-dir var/tmp/fast-di-output/diff` | Set where archive diff reports are written. Must resolve inside the Magento project root. |
| `--compare-fail-on-diff` | Exit with failure when archive comparison finds differences. |
| `--ignore-constructor-integrity` | Continue when constructor integrity validation fails. |
| `-v`, `-vv`, `-vvv` | Forward verbose mode to the Rust compiler as `--verbose`. |

## How it works under the hood

Magento builds console commands through `Magento\Framework\Console\CommandLoader\Aggregate`.

This module registers a preference for that aggregate loader. When Magento asks for `setup:di:compile`, the module checks for an executable Rust binary at `BP/rust/di-compiler/target/release/fast-di-compile`.

If the binary is available and trusted, the module returns a wrapper command named `setup:di:compile`. The binary must resolve inside `BP/rust/di-compiler/target/release`, must not be symlinked, and its path components must not be writable by group or other users.

The wrapper runs the Rust binary with the canonical Magento root, sets Magento's root as the process working directory, passes the current Magento CLI PHP binary to `--fallback-php`, strips common secret-bearing environment variables, streams sanitized stdout and stderr back to Magento's console output, and returns Magento success or failure codes based on the Rust process exit code. The Rust process timeout is 15 seconds.

If the binary is not available or fails the trust checks, the original Magento command loader handles the command unchanged.

## Debugging

Start with verbose output:

```bash
bin/magento setup:di:compile -v
```

To compare Rust output with Magento's standard compiler, first create a baseline using Magento's compiler:

```bash
bin/magento setup:di:compile --standard
mkdir -p var/tmp/magento-di-baseline
cp -R generated/code var/tmp/magento-di-baseline/_code
cp -R generated/metadata var/tmp/magento-di-baseline/_metadata
```

Then run the Rust compiler through Magento and compare against the baseline:

```bash
bin/magento setup:di:compile \
  --output var/tmp/fast-di-output \
  --compare-archive \
  --archive-root var/tmp/magento-di-baseline
```

Reports are written to `var/tmp/fast-di-output/diff/` unless `--compare-report-dir` is provided.

For compiler internals and lower-level debugging notes, see [speedupmate/di-compiler](https://github.com/speedupmate/di-compiler/).

## Development

Module source is under `src/`.

Useful checks from the Magento installation root:

```bash
composer install
vendor/bin/phpunit --configuration phpunit.xml.dist
vendor/bin/phpcs --standard=Magento2 --extensions=php,xml ./src
vendor/bin/phpcbf --standard=Magento2 --extensions=php,xml ./src
xmllint --noout ./src/etc/di.xml
xmllint --noout ./src/etc/module.xml
```

The GitHub Actions workflow for this package runs PHPUnit inside Docker Official PHP Alpine images for PHP 8.3/Symfony 6.4, PHP 8.4/Symfony 6.4, and PHP 8.5/Symfony 7.4. It also runs Magento Coding Standard validation in `php:8.4-cli-alpine`.

From the Magento root, verify the module is enabled:

```bash
bin/magento module:status Spmt_FastDiCompile
```

## License and copyright

Copyright © 2026 Anton Siniorg.

magento2-spmt-fast-di-compile is released under the [MIT License](LICENSE). Use it, improve it, and share it.

