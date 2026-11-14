#!/bin/sh
set -eu

plugin_dir=$(CDPATH= cd -- "$(dirname -- "$0")/../wordpress-plugin" && pwd)
tmp_dir=$(mktemp -d)
trap 'rm -rf "$tmp_dir"' EXIT
cd "$plugin_dir"
zip -q -X -r "$tmp_dir/interdeft-geo.zip" codeclinch-geo \
  -x '*/.DS_Store' '*/__MACOSX/*'
unzip -tq "$tmp_dir/interdeft-geo.zip"
mv -f "$tmp_dir/interdeft-geo.zip" "$plugin_dir/interdeft-geo.zip"
printf '%s\n' "$plugin_dir/interdeft-geo.zip"
