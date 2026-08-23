#!/bin/bash
# Applique un code de recharge : ajoute le credit (prepaye) ou
# rembourse la dette (postpaye), puis supprime le code utilise.
# Usage : recharger.sh <extension> <code>
# Appele automatiquement quand un code #XXX# est compose au telephone.

EXT="$1"
CODE="$2"

echo "SELECT utiliser_code_recharge(:'ext', :'code');" \
  | psql -d a2billing -v ext="$EXT" -v code="$CODE"
