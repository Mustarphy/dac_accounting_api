#!/bin/bash
# Run any artisan command with PHP 8.5, e.g.: ./artisan.sh migrate:status
cd "$(dirname "$0")"
PATH="/c/php85:$PATH" php artisan "$@"
