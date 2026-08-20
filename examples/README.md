# Fivexer SDK examples

Runnable snippets used by the docs at [5xer.com/docs](https://5xer.com/docs/). Each file is a
complete, copy-pasteable example. Where they need a live workspace, they read credentials from
environment variables.

## Layout

```
examples/
  python/        Python 3.9+ examples (sync + async)
  java/          Java 11+ examples
  typescript/    TypeScript / Node 18+ examples
  php/           PHP 8.1+ examples
```

## Running

### Python

```bash
cd sdks/python
pip install -e .                  # or `pip install fivexer`
FIVEXER_BASE_URL=https://api.5xer.com FIVEXER_API_KEY=sk_test_... \
  python ../examples/python/quickstart.py
```

### Java

```bash
cd sdks/java
./gradlew publishToMavenLocal     # or resolve from Maven Central
javac -cp ~/.m2/repository/io/fivexer/fivexer-sdk/0.1.0/fivexer-sdk-0.1.0.jar:... \
  -d /tmp/java-ex ../examples/java/Quickstart.java
java -cp /tmp/java-ex:... Quickstart
```

### TypeScript

```bash
cd platform/packages/sdk
npm install
FIVEXER_BASE_URL=https://api.5xer.com FIVEXER_API_KEY=sk_test_... \
  npx tsx ../../../sdks/examples/typescript/quickstart.ts
```

### PHP

```bash
cd sdks/php
composer install
FIVEXER_BASE_URL=https://api.5xer.com FIVEXER_API_KEY=sk_test_... \
  php ../examples/php/quickstart.php
```

## Test policy

Every snippet is compiled (where applicable) and import-checked in CI. Examples that hit the live
API are exercised against the shared sandbox on release branches.
