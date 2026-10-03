#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
php .cache/vendor/phpunit/phpunit/phpunit --testdox
python tests/concurrency.py
python tests/test_webhook_receiver.py
# Browser is opt-in: requires a separate LOCAL demo, Playwright and a running web server.
if [[ "${LAGOS_BROWSER_TESTS:-0}" = 1 ]]; then python tests/browser.py; python tests/browser_expansion.py; python tests/browser_support.py; python tests/browser_native.py; python tests/browser_aapanel.py; python tests/browser_documents.py; python tests/browser_ptero_ai.py; python tests/browser_web_site.py; fi
