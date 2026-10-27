#!/bin/bash
# smoke.sh <base_url> <cookiejar> <outfile>
# GETs OPAC/API pages and every admin page in admin_pages.txt, and records
# "<http code>|<path>|<PHP error markers found in the body>" per line.
set -u
B=$1; C=$2; O=$3; : > "$O"
check() {
  local body code flags
  body=$(curl -s -b "$C" -c "$C" -w '\n__CODE__%{http_code}' "$B/$1"); code=${body##*__CODE__}
  flags=$(echo "$body" | grep -oE "Fatal error|Uncaught [A-Za-z\\]+|Deprecated|Warning</b>|Warning:|Notice:|Oops Error|TypeError|ValueError|ArgumentCountError|Exception</|Undefined (array key|variable|index|property)" | sort | uniq -c | tr -s ' ' | tr '\n' ';')
  echo "$code|$1|$flags" >> "$O"
}
for u in "index.php" "index.php?search=search&keywords=a" "index.php?p=show_detail&id=1" "index.php?p=show_detail&id=1&inXML=true" \
  "index.php?p=news" "index.php?p=libinfo" "index.php?p=help" "index.php?p=librarian" "index.php?p=member" "index.php?p=visitor" \
  "index.php?p=peta" "index.php?p=cite&id=1" "index.php?p=advsearch" "index.php?p=login" "oai.php?verb=Identify" \
  "oai2.php?verb=ListRecords&metadataPrefix=oai_dc" "index.php?resultXML=true&search=search&keywords=a" \
  "index.php?JSONLD=true&search=search&keywords=a" \
  "lib/minigalnano/createthumb.php?filename=images/docs/the_definitive_guide_to_mysql5.jpg&width=120" \
  "api/biblio/popular" "api/biblio/latest"; do check "$u"; done
while read -r p; do [ -n "$p" ] && check "$p"; done < "$(dirname "$0")/admin_pages.txt"
