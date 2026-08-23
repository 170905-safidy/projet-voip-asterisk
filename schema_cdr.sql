-- =============================================================
-- Base : asterisk_cdr — journal des appels
-- =============================================================

CREATE USER asterisk_cdr WITH PASSWORD 'CHANGE_ME_cdr_password';
CREATE DATABASE asterisk_cdr OWNER asterisk_cdr;

\connect asterisk_cdr

CREATE TABLE cdr (
    id SERIAL PRIMARY KEY,
    calldate TIMESTAMP NOT NULL DEFAULT NOW(),
    src VARCHAR(80),
    dst VARCHAR(80),
    duration INTEGER,
    billsec INTEGER,
    disposition VARCHAR(45),
    channel VARCHAR(80)
);

-- IMPORTANT : la table doit appartenir a l'utilisateur applicatif,
-- pas seulement la base — sinon le module cdr_pgsql d'Asterisk ne
-- voit aucune colonne et genere une requete INSERT vide (voir doc,
-- Difficulte n8).
ALTER TABLE cdr OWNER TO asterisk_cdr;
