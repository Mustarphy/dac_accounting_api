#!/bin/bash
# Runs the queue worker using PHP 8.5 — required for verification/reset
# emails to actually be sent (they're queued, not sent inline).
cd "$(dirname "$0")"
PATH="/c/php85:$PATH" php artisan queue:work
