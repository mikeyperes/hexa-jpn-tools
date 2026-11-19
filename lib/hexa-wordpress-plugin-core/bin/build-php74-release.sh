#!/usr/bin/env bash
# Build a PHP 7.4-compatible release zip of one Hexa plugin.
#
#   build-php74-release.sh <plugin-git-dir> <git-ref> <folder-name> <out.zip>
#
# Exports <git-ref> of the plugin (with its bundled Core) to a temporary
# folder, rewrites it to PHP 7.4 syntax with Rector (bin/rector-php74.php),
# sets "Requires PHP: 7.4", lints every file with PHP 7.4 and zips it as
# <folder-name>/. Source is never changed. Needs:
#   RECTOR  path to the rector binary (default /root/tools/rector/vendor/bin/rector)
#   PHP_RUN PHP 8.x used to run Rector   (default /opt/cpanel/ea-php84/root/usr/bin/php)
#   PHP_74  PHP 7.4 used to lint         (default /opt/cpanel/ea-php74/root/usr/bin/php)
set -euo pipefail

src="${1:?plugin git dir}"; ref="${2:?git ref}"; folder="${3:?folder name}"; out="${4:?output zip}"
RECTOR="${RECTOR:-/root/tools/rector/vendor/bin/rector}"
PHP_RUN="${PHP_RUN:-/opt/cpanel/ea-php84/root/usr/bin/php}"
PHP_74="${PHP_74:-/opt/cpanel/ea-php74/root/usr/bin/php}"
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

work="$(mktemp -d)"; trap 'rm -rf "$work"' EXIT
git -C "$src" archive --format=tar --prefix="$folder/" "$ref" | tar -x -C "$work"
plugin="$work/$folder"
# Tests and developer tooling are not shipped.
find "$plugin" -type d \( -name tests -o -name .github \) -prune -exec rm -rf {} +

HEXA_RECTOR_PATH="$plugin" "$PHP_RUN" "$RECTOR" process --config "$here/rector-php74.php" --no-progress-bar --no-diffs >/dev/null

# The plugin header's PHP minimum follows the build.
for main in "$plugin"/*.php; do
  sed -i -E 's/^([[:space:]]*\*?[[:space:]]*Requires PHP:[[:space:]]*).*/\17.4/' "$main"
done
# Core's package hash covers src/; recompute it for the rewritten copy.
if [[ -f "$plugin/lib/hexa-wordpress-plugin-core/PACKAGE_HASH" ]]; then
  "$PHP_RUN" -r 'require $argv[1]."/bootstrap.php"; file_put_contents($argv[1]."/PACKAGE_HASH", HexaPluginCorePackageRegistry::source_hash($argv[1]));' "$plugin/lib/hexa-wordpress-plugin-core"
fi

failed=0
while IFS= read -r -d '' file; do
  if ! "$PHP_74" -l "$file" >/dev/null 2>&1; then
    failed=$((failed + 1)); "$PHP_74" -l "$file" 2>&1 | grep -m1 -i "parse error" >&2 || true
  fi
done < <(find "$plugin" -name '*.php' -print0)
if (( failed > 0 )); then
  echo "PHP 7.4 lint failed in $failed file(s); no zip written." >&2
  exit 1
fi

rm -f "$out"
( cd "$work" && zip -qr "$out" "$folder" )
echo "built $out (PHP 7.4 compatible, $(find "$plugin" -name '*.php' | wc -l) PHP files)"
