#!/bin/bash
# Verifie si un compte prepaye a un solde suffisant pour appeler.
# Renvoie exactement "OK" ou "REFUS" (sans espace ni retour a la ligne).
# Usage : verifier_credit.sh <extension>
# Appele avant chaque Dial()/Queue() via ${SHELL(...)} dans le dialplan.

EXT="$1"

echo "SELECT CASE WHEN mode='prepaid' AND solde<=0 THEN 'REFUS' ELSE 'OK' END FROM comptes WHERE extension=:'ext';" \
  | psql -d a2billing -v ext="$EXT" -tA 2>/dev/null | tr -d '[:space:]'
