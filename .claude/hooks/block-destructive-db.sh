#!/usr/bin/env bash
#
# PreToolUse(Bash) guard: refuse database commands that change or destroy data
# outside a test database.
#
# Development works as though the system is live: every database except a test
# database is treated as holding live data (CLAUDE.md, "Live-data rule"). The
# development database (.env) is UAT, which holds data, so no command may
# change it without the user.
#
# lerd runs PHP inside a container, so shell prefixes such as
# "DB_DATABASE=x_testing php artisan ..." never reach PHP: artisan always uses
# the .env database unless told otherwise with --database=. So:
#   - artisan commands that change a database are allowed only with
#     --database=<connection whose name ends in _testing>;
#   - the mysql/mysqldump clients run on the host and honour the database named
#     in the command, so they're allowed against a database whose name ends in
#     _testing, provided no live database or host is named.
#
# Reads the hook payload on stdin; emits a PreToolUse deny decision on a match.
# Exits 0 either way: a non-match must never interrupt the turn.

set -uo pipefail

cmd="$(jq -r '.tool_input.command // ""' 2>/dev/null)" || exit 0
[ -z "$cmd" ] && exit 0

# Collapse whitespace and lowercase so "MIGRATE:FRESH" matches "migrate:fresh".
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

# Live databases and servers: UAT (development: `fitness` on 64.227.44.46), the
# production servers (`pos`, `ecohub_pos` on 46.101.89.24) and the legacy local
# `fitness_center`. Names match only as whole words, so project paths such as
# /fitness-center/ don't count.
live_db_pattern='(^|[ =.`"'"'"'])(fitness|pos|ecohub_pos|fitness_center|pos_saas_dev)([ ;.`"'"'"']|$)|64\.227\.44\.46|46\.101\.89\.24'

names_live_db() {
  printf '%s' "$norm" | grep -Eq "$live_db_pattern"
}

# --- artisan ----------------------------------------------------------------

# "php artisan migrate:status" and "php artisan test" only read or use the
# test configuration; everything else under migrate/db: changes a database.
artisan_changes_db() {
  printf '%s' "$norm" | grep -Eq 'artisan [^;&|]*(migrate(:fresh|:refresh|:reset|:rollback|:install)?( |$|;|&)|db:wipe|db:seed|db:restore-snapshot|dataset:build|saas:import-legacy)' \
    || printf '%s' "$norm" | grep -Eq 'artisan::call\(\\?["'"'"'](migrate|db:wipe|db:seed|db:restore-snapshot|dataset:build|saas:import-legacy)'
}

# On the command line (--database=x_testing) or in an Artisan::call() array
# ('--database' => 'x_testing'). Every --database in the command must be one.
targets_test_connection() {
  local all ok
  all="$(printf '%s' "$norm" | grep -Eo -- '--database\\?["'"'"']?( ?=>? ?|=)\\?["'"'"']?[a-z0-9_]+' | wc -l)"
  ok="$(printf '%s' "$norm" | grep -Eo -- '--database\\?["'"'"']?( ?=>? ?|=)\\?["'"'"']?[a-z0-9_]+_testing( |$|;|&|\\?["'"'"'])' | wc -l)"
  [ "$all" -gt 0 ] && [ "$all" -eq "$ok" ]
}

if artisan_changes_db; then
  if targets_test_connection && ! names_live_db; then
    exit 0
  fi
  deny "BLOCKED by .claude/hooks/block-destructive-db.sh: this artisan command changes a database, and without --database=<connection ending in _testing> it runs against the .env database (UAT), which holds live data. Shell prefixes like DB_DATABASE=... don't reach lerd's PHP. Use a _testing connection, or ask the user to run it after taking a snapshot (php artisan db:snapshot)."
fi

# --- raw SQL: SQL clients and tinker -----------------------------------------

# The mysql client honours the database named in the command, so a client
# command that names only a _testing database may change it freely.
if printf '%s' "$norm" | grep -Eq '(^|[ ;&|])(mysql|mariadb|mysqldump)( |$)' \
  && printf '%s' "$norm" | grep -Eq '[a-z0-9_]+_testing\b' \
  && ! names_live_db; then
  exit 0
fi

case "$norm" in
*"drop database"* | *"drop table"* | *"drop schema"* | *schema::drop* | *schema::dropifexists*)
  deny "BLOCKED: dropping tables or databases in a live database. If this is genuinely required, the user must run it themselves after taking a snapshot."
  ;;
esac

# TRUNCATE, with or without the TABLE keyword, in SQL or Eloquent.
if printf '%s' "$norm" | grep -Eq '(^|[^a-z_])truncate( table)? [`"a-z_]|->truncate\(|::truncate\('; then
  deny "BLOCKED: TRUNCATE empties a table in a live database. If this is genuinely required, the user must run it themselves after taking a snapshot."
fi

# DELETE FROM with no WHERE clause empties the table just like TRUNCATE.
if printf '%s' "$norm" | grep -Eq '(^|[^a-z_])delete from [`"a-z_.]+' \
  && ! printf '%s' "$norm" | grep -Eq '(^|[^a-z_])delete from [^;]* where '; then
  deny "BLOCKED: DELETE FROM without a WHERE clause empties a table in a live database. Add a WHERE clause, or ask the user to run it after taking a snapshot."
fi

exit 0
