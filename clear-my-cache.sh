#!/usr/bin/env bash

set -euo pipefail

TARGET="/var/www/lookup.com"

# حماية إضافية من أي خطأ بالمسار
if [[ "$TARGET" != "/var/www/lookup.com" ]]; then
    echo "Invalid target directory"
    exit 1
fi

if [[ ! -d "$TARGET" ]]; then
    echo "Directory does not exist"
    exit 1
fi

# احذف كل ما بداخل المجلد، وليس المجلد نفسه
find "$TARGET" -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +

echo "Cache cleared successfully"