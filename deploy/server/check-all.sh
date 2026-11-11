#!/usr/bin/env bash
# Jalankan 01-05 dalam mode --dry-run dan tampilkan ringkasan. Tidak mengubah apa pun.
# Pakai: sudo ./check-all.sh              semua script (01-05)
#        sudo ./check-all.sh 03 04 05     hanya script tertentu
#        tambahkan -q untuk ringkasan saja
cd "$(dirname "$0")" || exit 1
[[ $EUID -eq 0 ]] || { echo "Jalankan dengan sudo: sudo $0"; exit 1; }
quiet=0; only=()
for a in "$@"; do
  case "$a" in
    -q) quiet=1 ;;
    0[1-5]) only+=("$a") ;;
    *) echo "Argumen tidak dikenal: $a (pakai -q dan/atau 01..05)"; exit 1 ;;
  esac
done
declare -A label=([0]='sesuai' [1]='PRA-SYARAT GAGAL' [2]='ada perubahan')
summary=(); worst=0
for s in 01-hardening.sh 02-firewall.sh 03-mariadb-install.sh 04-mariadb-config.sh 05-fail2ban.sh; do
  [[ ${#only[@]} -eq 0 || " ${only[*]} " == *" ${s%%-*} "* ]] || continue
  if [[ $quiet -eq 1 ]]; then ./"$s" --dry-run </dev/null >/dev/null 2>&1; rc=$?
  else printf '\n\033[1m######## %s --dry-run ########\033[0m\n' "$s"; ./"$s" --dry-run </dev/null; rc=$?; fi
  summary+=("$(printf '  %-24s %s' "$s" "${label[$rc]:-error (exit $rc)}")")
  [[ $rc -eq 1 || $rc -gt 2 ]] && worst=1
  [[ $rc -eq 2 && $worst -eq 0 ]] && worst=2
done
printf '\n\033[1mRingkasan\033[0m\n'; printf '%s\n' "${summary[@]}"
exit "$worst"
