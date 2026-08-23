#!/bin/bash
# =============================================================
# Installation d'Asterisk + dependances pour l'IVR (Google TTS)
# A executer sur une VM Ubuntu Server 24.04 LTS, avec sudo
# Usage : sudo bash install_asterisk.sh
# =============================================================

set -e

echo ">> Mise a jour du systeme..."
apt-get update
apt-get upgrade -y

echo ">> Installation d'Asterisk et des outils de base..."
apt-get install -y asterisk asterisk-core-sounds-fr-gsm

echo ">> Installation des dependances pour le script Google TTS (googletts.agi)..."
apt-get install -y perl libwww-perl liblwp-protocol-https-perl sox libsox-fmt-mp3 mpg123 wget

echo ">> Recherche du bon dossier AGI (ne jamais supposer un chemin par convention)..."
AGI_DIR=$(grep "astagidir" /etc/asterisk/asterisk.conf | awk -F'=> ' '{print $2}' | tr -d ' \t')
if [ -z "$AGI_DIR" ]; then
    echo "!! Impossible de determiner astagidir automatiquement, verifier manuellement :"
    echo "   grep astagidir /etc/asterisk/asterisk.conf"
    exit 1
fi
echo "   Dossier AGI detecte : $AGI_DIR"

echo ">> Recuperation du script googletts.agi (projet zaf/asterisk-googletts)..."
mkdir -p "$AGI_DIR"
wget -O "$AGI_DIR/googletts.agi" \
  https://raw.githubusercontent.com/zaf/asterisk-googletts/master/googletts.agi
chmod +x "$AGI_DIR/googletts.agi"
chown asterisk:asterisk "$AGI_DIR/googletts.agi"

echo ">> Verification du service Asterisk..."
systemctl enable asterisk
systemctl restart asterisk
systemctl status asterisk --no-pager

echo ""
echo "=============================================================="
echo " Installation terminee."
echo " Prochaines etapes :"
echo "  1. Copier les fichiers de config/ dans /etc/asterisk/"
echo "  2. Les inclure via #include dans pjsip.conf et extensions.conf"
echo "  3. sudo asterisk -rx \"core reload\""
echo "=============================================================="
