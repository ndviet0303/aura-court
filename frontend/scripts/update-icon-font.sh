#!/bin/sh
# Regenerates public/fonts/material-symbols-outlined.woff2 from scripts/material-symbols-icons.txt:
# a Google Fonts subset with only those icons and the axes the app uses (opsz 24, wght 400-600,
# FILL 0-1, GRAD 0). Run it after adding an icon to the list.
set -eu
cd "$(dirname "$0")/.."
names=$(sort -u scripts/material-symbols-icons.txt | paste -sd, -)
ua='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36'
css="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400..600,0..1,0&icon_names=$names&display=block"
url=$(curl -fsS -A "$ua" "$css" | grep -o 'https://[^)]*' | head -1)
out=public/fonts/material-symbols-outlined.woff2
curl -fsS -o "$out" "$url"
# Google drops icon_names past ~400 names and serves the whole ~1 MB font instead.
size=$(wc -c < "$out")
[ "$size" -lt 400000 ] || { echo "subset ignored by Google ($size bytes): trim the icon list" >&2; exit 1; }
echo "$out: $size bytes"
