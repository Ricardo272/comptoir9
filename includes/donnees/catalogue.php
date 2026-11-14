<?php
/**
 * Tout le SQL du catalogue public : produits et boutiques.
 *
 * Règle commune : un produit n'est visible que s'il est « en_vente » ET que
 * sa boutique est « active ». Elle est écrite une seule fois, dans SQL_PRODUITS.
 */
require_once __DIR__ . '/../db.php';

// Colonnes attendues par includes/carte-produit.php, et la règle de visibilité.
// À compléter par « AND … ORDER BY … LIMIT … ».
const SQL_PRODUITS = "
    SELECT p.slug, p.nom, p.prix, p.prix_barre, p.stock, b.nom AS boutique
    FROM produits p
    JOIN boutiques b ON b.id = p.boutique_id
    WHERE p.etat = 'en_vente' AND b.etat = 'active'";

/** Les derniers produits mis en vente. */
function produitsNouveautes(int $nombre): array
{
    $requete = db()->prepare(SQL_PRODUITS . ' ORDER BY p.cree_le DESC, p.id DESC LIMIT :nombre');
    $requete->bindValue('nombre', $nombre, PDO::PARAM_INT);
    $requete->execute();
    return $requete->fetchAll();
}

/** Des produits en promotion encore en stock, les plus fortes remises d'abord. */
function produitsBonnesAffaires(int $nombre): array
{
    $requete = db()->prepare(
        SQL_PRODUITS . ' AND p.prix_barre IS NOT NULL AND p.stock > 0
         ORDER BY (p.prix_barre - p.prix) / p.prix_barre DESC, p.id LIMIT :nombre'
    );
    $requete->bindValue('nombre', $nombre, PDO::PARAM_INT);
    $requete->execute();
    return $requete->fetchAll();
}

/** Les boutiques actives, avec leur nombre de produits en vente. */
function boutiques(): array
{
    return db()->query(
        "SELECT b.slug, b.nom, b.ville, b.specialite, b.accroche,
                (SELECT COUNT(*) FROM produits p
                  WHERE p.boutique_id = b.id AND p.etat = 'en_vente') AS nb_produits
         FROM boutiques b
         WHERE b.etat = 'active'
         ORDER BY b.annee_arrivee, b.id"
    )->fetchAll();
}
