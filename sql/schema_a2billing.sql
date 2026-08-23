-- =============================================================
-- Base : a2billing — comptes, offres, codes de recharge,
-- facturation prepayee/postpayee, liaison avec la base CDR
-- =============================================================

CREATE USER a2billing_user WITH PASSWORD 'CHANGE_ME_billing_password';
CREATE DATABASE a2billing OWNER a2billing_user;

\connect a2billing

-- ---------- Tables ----------

CREATE TABLE comptes (
    id SERIAL PRIMARY KEY,
    extension VARCHAR(20) UNIQUE NOT NULL,
    mode VARCHAR(10) NOT NULL DEFAULT 'prepaid',
    solde NUMERIC(10,2) NOT NULL DEFAULT 0,
    dette NUMERIC(10,2) NOT NULL DEFAULT 0,
    cout_par_minute NUMERIC(6,2) NOT NULL DEFAULT 5.00
);

CREATE TABLE offres (
    code_offre VARCHAR(30) PRIMARY KEY,
    montant_credit NUMERIC(10,2) NOT NULL,
    description TEXT
);

CREATE TABLE codes_recharge (
    code VARCHAR(30) PRIMARY KEY,
    montant NUMERIC(10,2) NOT NULL,
    code_offre VARCHAR(30) REFERENCES offres(code_offre)
);

ALTER TABLE comptes OWNER TO a2billing_user;
ALTER TABLE offres OWNER TO a2billing_user;
ALTER TABLE codes_recharge OWNER TO a2billing_user;

-- ---------- Donnees de test (a adapter) ----------

INSERT INTO comptes (extension, mode, solde, cout_par_minute) VALUES
    ('6001', 'prepaid', 1000.00, 5.00),
    ('6002', 'postpaid', 0.00, 5.00);

INSERT INTO offres (code_offre, montant_credit, description) VALUES
    ('OFFRE10', 10000.00, 'Recharge 10000 Ar'),
    ('OFFRE5', 5000.00, 'Recharge 5000 Ar');

-- ---------- Fonctions ----------

CREATE OR REPLACE FUNCTION facturer_appel(p_extension VARCHAR, p_billsec INTEGER)
RETURNS TEXT AS $$
DECLARE
    v_cout NUMERIC;
    v_mode VARCHAR;
BEGIN
    SELECT cout_par_minute, mode INTO v_cout, v_mode
    FROM comptes WHERE extension = p_extension;

    IF NOT FOUND THEN
        RETURN 'Compte introuvable : ' || p_extension;
    END IF;

    v_cout := ROUND((p_billsec / 60.0) * v_cout, 2);

    IF v_mode = 'prepaid' THEN
        UPDATE comptes SET solde = solde - v_cout WHERE extension = p_extension;
    ELSE
        UPDATE comptes SET dette = dette + v_cout WHERE extension = p_extension;
    END IF;

    RETURN 'Facture ' || v_cout || ' Ar pour ' || p_extension;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION utiliser_code_recharge(p_extension VARCHAR, p_code VARCHAR)
RETURNS TEXT AS $$
DECLARE
    v_montant NUMERIC;
    v_mode VARCHAR;
BEGIN
    SELECT montant INTO v_montant FROM codes_recharge WHERE code = p_code;
    IF NOT FOUND THEN
        RETURN 'Code invalide ou deja utilise : ' || p_code;
    END IF;

    SELECT mode INTO v_mode FROM comptes WHERE extension = p_extension;
    IF NOT FOUND THEN
        RETURN 'Compte introuvable : ' || p_extension;
    END IF;

    IF v_mode = 'prepaid' THEN
        UPDATE comptes SET solde = solde + v_montant WHERE extension = p_extension;
    ELSE
        UPDATE comptes SET dette = GREATEST(dette - v_montant, 0) WHERE extension = p_extension;
    END IF;

    DELETE FROM codes_recharge WHERE code = p_code;

    RETURN 'Recharge de ' || v_montant || ' Ar effectuee pour ' || p_extension;
END;
$$ LANGUAGE plpgsql;

-- ---------- Liaison avec la base CDR (postgres_fdw) ----------
-- A executer une fois la base asterisk_cdr deja creee (voir schema_cdr.sql)

CREATE EXTENSION IF NOT EXISTS postgres_fdw;

CREATE SERVER serveur_cdr
FOREIGN DATA WRAPPER postgres_fdw
OPTIONS (host 'localhost', dbname 'asterisk_cdr', port '5432');

CREATE USER MAPPING FOR a2billing_user
SERVER serveur_cdr
OPTIONS (user 'asterisk_cdr', password 'CHANGE_ME_cdr_password');

CREATE FOREIGN TABLE cdr_distant (
    id INTEGER,
    calldate TIMESTAMP,
    src VARCHAR(80),
    dst VARCHAR(80),
    duration INTEGER,
    billsec INTEGER,
    disposition VARCHAR(45),
    channel VARCHAR(80)
) SERVER serveur_cdr OPTIONS (schema_name 'public', table_name 'cdr');

GRANT USAGE ON FOREIGN SERVER serveur_cdr TO a2billing_user;
GRANT SELECT ON cdr_distant TO a2billing_user;
