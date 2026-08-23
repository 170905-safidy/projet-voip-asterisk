# Projet VoIP / IPBX sous Asterisk

Système téléphonique complet construit avec **Asterisk**, incluant un serveur vocal interactif (IVR), un routage multi-services avec repli automatique d'agents, une conférence téléphonique, un journal d'appels (CDR) et un système de facturation prépayé/postpayé avec contrôle de crédit en temps réel — le tout sur **PostgreSQL**.

## Documentation complète

👉 [**Documentation technique complète (PDF, 65 pages)**](docs/Documentation_Technique_Projet_VOIP.pdf)

Elle couvre : méthodologie de réalisation, installation pas à pas, toutes les commandes exécutées, configuration commentée ligne par ligne, chronologie complète, historique de tous les bugs rencontrés (cause, diagnostic, solution), et bonnes pratiques.

## Architecture

```
Softphone (SIP) → Asterisk → IVR (Google TTS) → Dialplan → Service / Queue
                                                                  │
                                            ┌─────────────────────┴───────────────┐
                                            ▼                                     ▼
                                   CDR → PostgreSQL                    Facturation → PostgreSQL
                                   (base asterisk_cdr)                  (base a2billing, liée par
                                                                          postgres_fdw)
```

Schéma détaillé : [`docs/Architecture_Reseau.png`](docs/Architecture_Reseau.png)

## Fonctionnalités

- IVR vocal généré dynamiquement (Google TTS)
- Routage vers 3 services (commercial, support, comptabilité)
- Repli automatique vers un agent libre si le premier est occupé (`Queue`)
- Conférence téléphonique à 3+ participants (`ConfBridge`)
- Mise en attente et transfert d'appel
- Journal d'appels (CDR) automatique dans PostgreSQL
- Facturation **prépayée** (déduction de solde) et **postpayée** (accumulation de dette)
- Contrôle de crédit en temps réel (blocage d'appel si solde prépayé ≤ 0)
- Codes de recharge : génération, composition téléphonique directe (`#XXX#`), suppression automatique après usage
- Console web de supervision (comptes + CDR en quasi temps réel), en HTTPS

## Structure du dépôt

```
├── docs/       → documentation technique (PDF) + schéma d'architecture
├── config/     → fichiers de configuration Asterisk (PJSIP, dialplan, CDR, files d'attente)
├── scripts/    → scripts d'installation et de facturation
├── sql/        → schémas des deux bases PostgreSQL (CDR + facturation)
└── web/        → console de supervision (PHP)
```

## Installation rapide

1. Suivre `scripts/install_asterisk.sh` sur une VM Ubuntu Server 24.04 LTS
2. Copier les fichiers de `config/` dans `/etc/asterisk/` et les inclure via `#include` (voir doc, section 3)
3. Créer les bases avec les fichiers de `sql/`
4. Copier `config/.env.example` en `.env`, remplir les vrais identifiants, adapter les scripts de `scripts/` et `web/index.php` en conséquence
5. Voir la documentation complète pour le détail de chaque étape

## Sécurité

Tous les mots de passe présents dans ce dépôt sont des **placeholders** (`CHANGE_ME_...`) — ne jamais les utiliser tels quels. Voir `config/.env.example` pour la liste des identifiants à définir.

## Contexte

Projet académique (VoIP/IPBX), réalisé étape par étape avec documentation méthodique de chaque bug rencontré et de sa résolution.
