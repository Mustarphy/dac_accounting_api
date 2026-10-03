#!/bin/bash
# Starts the Laravel dev server using PHP 8.5 (kept off the system PATH
# on purpose, so it doesn't clash with XAMPP's PHP 8.2 used elsewhere).
cd "$(dirname "$0")"
PATH="/c/php85:$PATH" php artisan serve --port=8000
