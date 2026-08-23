#!/bin/bash
# Facture un appel : deduit le solde (prepaye) ou augmente la dette (postpaye)
# Usage : facturer.sh <extension> <duree_en_secondes>
# Appele automatiquement par le hangup handler du dialplan.

EXT="$1"
SEC="$2"

echo "SELECT facturer_appel(:'ext', :'sec'::integer);" \
  | psql -d a2billing -v ext="$EXT" -v sec="$SEC"
