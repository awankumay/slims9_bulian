#!/bin/bash
# security-check.sh
# Static checks for security fixes that must not regress. No server needed.
# Exit code 1 when any check fails.
set -u
cd "$(dirname "$0")/../.."
fail=0
check() { # check <description> <command that must succeed>
  if eval "$2" >/dev/null 2>&1; then echo "OK   $1"; else echo "FAIL $1"; fail=1; fi
}

check "integer casts are applied to the value, not to isset()" \
  "! grep -rqE '\(int(eger)?\) *isset' --include=*.php admin lib api plugins template"
check "vocabulary iframe requires a staff session" \
  "grep -q 'session_check.inc.php' admin/modules/master_file/iframe_vocabolary_control.php"
for f in theme membercard_theme plugin_action mailsetting backup_config ucsetting register_site envinfo; do
  check "system/$f.php requires the system privilege" \
    "grep -q \"havePrivilege('system', 'w')\" admin/modules/system/$f.php"
done
check "bulian-kampus prints no raw theme option" \
  "! grep -rq 'bk_html(' template/bulian-kampus"
check "member card template name is a single directory" \
  "grep -q \"basename(utility::filterData('template'\" admin/modules/system/membercard_theme.php"

exit $fail
