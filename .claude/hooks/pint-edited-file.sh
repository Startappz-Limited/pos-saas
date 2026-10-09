#!/usr/bin/env bash
#
# PostToolUse(Write|Edit) formatter: run Laravel Pint on the file just written.
#
# The project standard is "run vendor/bin/pint before finishing" — doing it per
# edit means it actually happens. Only .php files are touched; anything else,
# or any failure, exits quietly so the turn is never interrupted.

set -uo pipefail

root="${CLAUDE_PROJECT_DIR:-.}"
pint="$root/vendor/bin/pint"

file="$(jq -r '.tool_response.filePath // .tool_input.file_path // ""' 2>/dev/null)" || exit 0

[ -z "$file" ] && exit 0
[ -x "$pint" ] || exit 0

case "$file" in
*.php) ;;
*) exit 0 ;;
esac

[ -f "$file" ] || exit 0

# Pint resolves pint.json relative to cwd, so run it from the project root.
(cd "$root" && "$pint" --quiet "$file" >/dev/null 2>&1) || true

exit 0
