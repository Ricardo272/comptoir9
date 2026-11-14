-- ==========================================================================
-- Structure de la base Comptoir 9
-- À exécuter en root, dans la base comptoir9, UNE SEULE FOIS à l'installation.
-- ATTENTION : supprime les tables existantes et toutes leurs données.
-- ==========================================================================

SET NAMES utf8mb4;

-- On supprime d'abord les tables qui dépendent des autres (clés étrangères)
DROP TABLE IF EXISTS produits;
DROP TABLE IF EXISTS boutiques;
DROP TABLE IF EXISTS rayons;


-- --------------------------------------------------------------------------
-- Rayons et sous-rayons, dans la même table.
-- parent_id NULL = rayon ; sinon = sous-rayon de ce rayon (deux niveaux, pas plus).
-- slug : unique pour les deux niveaux, il sert d'adresse (catalogue.php?rayon=cuisson).
-- « Bonnes affaires » n'est pas ici : c'est un rayon calculé (produits en promotion).
-- --------------------------------------------------------------------------
CREATE TABLE rayons (
    id         SMALLINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    parent_id  SMALLINT UNSIGNED  NULL,
    slug       VARCHAR(60)        NOT NULL,
    nom        VARCHAR(60)        NOT NULL,
    ordre      TINYINT UNSIGNED   NOT NULL DEFAULT 0,  -- ordre d'affichage
    PRIMARY KEY (id),
    UNIQUE KEY rayons_slug (slug),
    CONSTRAINT rayons_parent FOREIGN KEY (parent_id) REFERENCES rayons (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------------------------
-- Boutiques (les vendeurs)
-- etat = 'suspendue' : la boutique et tous ses produits disparaissent du site.
-- --------------------------------------------------------------------------
CREATE TABLE boutiques (
    id             SMALLINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    slug           VARCHAR(60)        NOT NULL,
    nom            VARCHAR(60)        NOT NULL,
    ville          VARCHAR(60)        NOT NULL,
    specialite     VARCHAR(60)        NOT NULL,          -- ex. « High-tech »
    accroche       VARCHAR(160)       NOT NULL,          -- une phrase, sur les cartes
    presentation   TEXT               NOT NULL,          -- sur la fiche boutique
    annee_arrivee  SMALLINT UNSIGNED  NOT NULL,          -- arrivée sur Comptoir 9
    etat           ENUM('active', 'suspendue') NOT NULL DEFAULT 'active',
    PRIMARY KEY (id),
    UNIQUE KEY boutiques_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------------------------
-- Produits
-- prix, prix_barre : en CENTIMES (entiers), jamais en FLOAT (valeur approchée).
-- prix_barre : l'ancien prix d'un produit en promotion, NULL sinon.
-- rayon_id : toujours un sous-rayon.
-- caracteristiques : une par ligne.
-- etat : 'retire' = retiré de la vente par la boutique ; 'masque' = masqué par l'admin.
-- La photo n'a pas de colonne : c'est assets/img/produits/<slug>.jpg.
-- --------------------------------------------------------------------------
CREATE TABLE produits (
    id                INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    boutique_id       SMALLINT UNSIGNED  NOT NULL,
    rayon_id          SMALLINT UNSIGNED  NOT NULL,
    slug              VARCHAR(120)       NOT NULL,
    nom               VARCHAR(120)       NOT NULL,
    description       TEXT               NOT NULL,
    caracteristiques  TEXT               NOT NULL,
    prix              INT UNSIGNED       NOT NULL,
    prix_barre        INT UNSIGNED       NULL,
    stock             SMALLINT UNSIGNED  NOT NULL DEFAULT 0,
    delai_jours       TINYINT UNSIGNED   NOT NULL,       -- délai d'expédition annoncé
    etat              ENUM('en_vente', 'retire', 'masque') NOT NULL DEFAULT 'en_vente',
    cree_le           DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY produits_slug (slug),
    KEY produits_rayon (rayon_id),
    KEY produits_boutique (boutique_id),
    CONSTRAINT produits_boutique FOREIGN KEY (boutique_id) REFERENCES boutiques (id),
    CONSTRAINT produits_rayon FOREIGN KEY (rayon_id) REFERENCES rayons (id),
    -- Un prix barré plus bas que le prix serait une fausse promotion
    CONSTRAINT produits_promo CHECK (prix_barre IS NULL OR prix_barre > prix)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
