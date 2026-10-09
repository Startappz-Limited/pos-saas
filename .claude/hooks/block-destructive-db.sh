#!/usr/bin/env bash
#
# PreToolUse(Bash) guard: refuse database commands that destroy data.
#
# This project's .env points DB_HOST at a REMOTE SHARED database holding live
# sales, stock movements and cost layers. "It's only local" is never true here,
# so the hard rules in .claude/skills/laravel-database/SKILL.md are enforced
# mechanically rather than left to prose.
#
# Reads the hook payload on stdin; emits a PreToolUse deny decision on a match.
# Exits 0 either way — a non-match must never interrupt the turn.

set -uo pipefail

cmd="$(jq -r '.tool_input.command // ""' 2>/dev/null)" || exit 0
[ -z "$cmd" ] && exit 0

# Collapse whitespace and lowercase so "migrate:fresh", "migrate:fresh" and
# "MIGRATE:FRESH" all match the same patterns.
norm="$(printf '%s' "$cmd" | tr -s '[:space:]' ' ' | tr '[:upper:]' '[:lower:]')"

deny() {
  jq -n --arg reason "$1" '{
    hookSpecificOutput: {
      hookEventName: "PreToolUse",
      permissionDecision: "deny",
      permissionDecisionReason: $reason
    }
  }'
  exit 0
}

case "$norm" in
*migrate:fresh* | *"migrate --fresh"* | *migrate:refresh* | *migrate:reset* | *db:wipe*)
  deny "BLOCKED by .claude/hooks/block-destructive-db.sh — this drops or rolls back tables, and DB_HOST points at a remote shared database with live sales, stock and cost-layer data. Write an additive migration instead, and see .claude/skills/laravel-database/SKILL.md. If the user has explicitly authorised a reset, they must run it themselves."
  ;;
esac

case "$norm" in
*"migrate --force"*)
  deny "BLOCKED — 'migrate --force' applies migrations unprompted against a remote shared database. Run 'php artisan migrate:status' and confirm with the user before applying anything."
  ;;
esac

# db:seed is permitted only for one explicitly named seeder class.
case "$norm" in
*db:seed*)
  case "$norm" in
  *--class=*) : ;;
  *)
    deny "BLOCKED — bare 'db:seed' runs the full DatabaseSeeder against a remote shared database. Re-run with an explicit class (e.g. 'php artisan db:seed --class=PermissionSeeder') and only when the user asked for it."
    ;;
  esac
  ;;
esac

# Raw SQL equivalents, for when a client is invoked directly.
case "$norm" in
*"drop database"* | *"drop table"* | *"truncate table"*)
  deny "BLOCKED — raw destructive SQL against a remote shared database. If this is genuinely required, the user must run it themselves after taking a backup."
  ;;
esac

exit 0
