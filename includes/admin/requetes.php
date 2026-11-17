<?php
/**
 * Tout le SQL des pages de l'administration (rôle admin).
 * Les requêtes de l'espace vendeur sont à part : elles filtrent toujours
 * sur la boutique du compte connecté.
 */
require_once __DIR__ . '/../db.php';

/**
 * Les commandes, les plus récentes d'abord, avec l'état de leurs colis.
 * shortcut: limitée aux 200 dernières, sans pagination ; les commandes sont
 * supprimées après 30 jours, ajouter une pagination si le volume grandit.
 */
function listeCommandes(): array
{
    // Toutes les colonnes de commandes sont dans le GROUP BY : MariaDB l'exige
    return db()->query(
        "SELECT o.numero, o.prenom, o.nom, o.total, o.cree_le,
                COUNT(c.id) AS nb_colis, SUM(c.etat = 'expedie') AS nb_expedies
         FROM commandes o
         JOIN colis c ON c.commande_id = o.id
         GROUP BY o.id, o.numero, o.prenom, o.nom, o.total, o.cree_le
         ORDER BY o.cree_le DESC, o.id DESC
         LIMIT 200"
    )->fetchAll();
}

/**
 * Les messages de contact : à traiter d'abord, puis les plus récents.
 * shortcut: limités aux 200 premiers, sans pagination (supprimés après 30 jours).
 */
function listeMessages(): array
{
    return db()->query(
        'SELECT id, nom, email, sujet, message, traite, cree_le FROM messages ORDER BY traite, cree_le DESC, id DESC LIMIT 200'
    )->fetchAll();
}

function nombreMessagesATraiter(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM messages WHERE traite = 0')->fetchColumn();
}

/** Marque un message traité (ou à traiter). Renvoie false si le message n'existe pas. */
function marquerMessage(int $id, bool $traite): bool
{
    $requete = db()->prepare('UPDATE messages SET traite = :traite WHERE id = :id');
    $requete->execute(['traite' => (int) $traite, 'id' => $id]);
    // rowCount() vaut 0 aussi quand rien ne change : on vérifie l'existence à part
    return $requete->rowCount() > 0
        || (bool) db()->query('SELECT 1 FROM messages WHERE id = ' . $id)->fetchColumn();
}

/** Les boutiques, avec leurs nombres de produits et de comptes vendeurs. */
function listeBoutiques(): array
{
    return db()->query(
        "SELECT b.id, b.slug, b.nom, b.ville, b.etat,
                (SELECT COUNT(*) FROM produits p WHERE p.boutique_id = b.id AND p.etat = 'en_vente') AS en_vente,
                (SELECT COUNT(*) FROM produits p WHERE p.boutique_id = b.id) AS produits,
                (SELECT COUNT(*) FROM comptes c WHERE c.boutique_id = b.id) AS comptes
         FROM boutiques b
         ORDER BY b.id"
    )->fetchAll();
}

/**
 * Suspend ou réactive une boutique. Suspendue, elle disparaît du site avec tous
 * ses produits (règle de visibilité de includes/donnees/catalogue.php) ; ses
 * produits et ses commandes restent en base. Renvoie false si rien n'a changé.
 */
function changerEtatBoutique(int $boutiqueId, bool $active): bool
{
    $requete = db()->prepare('UPDATE boutiques SET etat = :nouvel WHERE id = :id AND etat = :ancien');
    $requete->execute([
        'nouvel' => $active ? 'active' : 'suspendue',
        'ancien' => $active ? 'suspendue' : 'active',
        'id'     => $boutiqueId,
    ]);
    return $requete->rowCount() === 1;
}

/** Tous les produits, par boutique, pour la modération. */
function listeProduitsModeration(): array
{
    return db()->query(
        'SELECT p.id, p.slug, p.nom, p.prix, p.stock, p.etat, b.nom AS boutique, b.etat AS etat_boutique
         FROM produits p JOIN boutiques b ON b.id = p.boutique_id
         ORDER BY b.id, p.nom'
    )->fetchAll();
}

/**
 * Masque un produit (modération), ou le démasque. Démasqué, il revient en vente.
 * Un produit retiré par sa boutique peut aussi être masqué : le vendeur ne pourra
 * plus le remettre en vente. Renvoie false si rien n'a changé.
 */
function masquerProduit(int $produitId, bool $masquer): bool
{
    $requete = db()->prepare(
        $masquer
            ? "UPDATE produits SET etat = 'masque' WHERE id = :id AND etat <> 'masque'"
            : "UPDATE produits SET etat = 'en_vente' WHERE id = :id AND etat = 'masque'"
    );
    $requete->execute(['id' => $produitId]);
    return $requete->rowCount() === 1;
}
