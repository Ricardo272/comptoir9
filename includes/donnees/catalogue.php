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

/**
 * Retrouve un rayon ou un sous-rayon par son slug, dans la liste de rayons() :
 *   ['rayon' => le rayon, 'sous_rayon' => le sous-rayon ou null]
 * ou null si le slug n'existe pas.
 */
function trouverRayon(string $slug): ?array
{
    foreach (rayons() as $rayon) {
        if ($rayon['slug'] === $slug) {
            return ['rayon' => $rayon, 'sous_rayon' => null];
        }
        foreach ($rayon['sous_rayons'] as $sousRayon) {
            if ($sousRayon['slug'] === $slug) {
                return ['rayon' => $rayon, 'sous_rayon' => $sousRayon];
            }
        }
    }
    return null;
}

// Tris proposés : la clé vient de l'adresse, le SQL est écrit ici.
// Une valeur de l'adresse n'entre donc jamais dans la requête.
const TRIS_CATALOGUE = [
    'nouveaute' => ['Nouveautés',     'p.cree_le DESC, p.id DESC'],
    'prix-asc'  => ['Prix croissant', 'p.prix ASC, p.id'],
    'prix-desc' => ['Prix décroissant', 'p.prix DESC, p.id'],
];

const PRODUITS_PAR_PAGE = 24;

/**
 * Recherche dans le catalogue. Tous les filtres sont facultatifs :
 *   'rayon'      résultat de trouverRayon()
 *   'q'          mots cherchés dans le nom et la description (tous doivent y être)
 *   'boutique'   slug d'une boutique
 *   'prix_min', 'prix_max'  en centimes
 *   'en_stock'   true pour écarter les produits épuisés
 *   'tri'        une clé de TRIS_CATALOGUE
 * Renvoie ['total' => …, 'pages' => …, 'page' => …, 'produits' => […]].
 * Une page trop grande est ramenée à la dernière.
 */
function rechercherProduits(array $filtres, int $page = 1): array
{
    $conditions = '';
    $valeurs = [];

    $rayon = $filtres['rayon'] ?? null;
    if ($rayon && $rayon['sous_rayon']) {
        $conditions .= ' AND p.rayon_id = :rayon';
        $valeurs['rayon'] = $rayon['sous_rayon']['id'];
    } elseif ($rayon && $rayon['rayon']['id'] === null) {
        $conditions .= ' AND p.prix_barre IS NOT NULL'; // « Bonnes affaires »
    } elseif ($rayon) {
        $conditions .= ' AND p.rayon_id IN (SELECT id FROM rayons WHERE parent_id = :rayon)';
        $valeurs['rayon'] = $rayon['rayon']['id'];
    }

    // shortcut: recherche par LIKE (pas de pertinence, pas de fautes de frappe),
    // passer à un index FULLTEXT si le catalogue dépasse quelques milliers de produits.
    $mots = preg_split('/\s+/u', trim($filtres['q'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    foreach (array_slice($mots, 0, 5) as $i => $mot) {
        // % et _ sont des jokers pour LIKE : on les neutralise pour les chercher tels quels
        $motif = '%' . addcslashes($mot, '\%_') . '%';
        $conditions .= " AND (p.nom LIKE :nom$i OR p.description LIKE :desc$i)";
        $valeurs["nom$i"] = $motif;   // deux noms : une vraie requête préparée
        $valeurs["desc$i"] = $motif;  // n'accepte pas deux fois le même paramètre
    }

    if (($filtres['boutique'] ?? '') !== '') {
        $conditions .= ' AND b.slug = :boutique';
        $valeurs['boutique'] = $filtres['boutique'];
    }
    if (isset($filtres['prix_min'])) {
        $conditions .= ' AND p.prix >= :prix_min';
        $valeurs['prix_min'] = $filtres['prix_min'];
    }
    if (isset($filtres['prix_max'])) {
        $conditions .= ' AND p.prix <= :prix_max';
        $valeurs['prix_max'] = $filtres['prix_max'];
    }
    if (!empty($filtres['en_stock'])) {
        $conditions .= ' AND p.stock > 0';
    }

    $compte = db()->prepare(
        "SELECT COUNT(*) FROM produits p JOIN boutiques b ON b.id = p.boutique_id
         WHERE p.etat = 'en_vente' AND b.etat = 'active'" . $conditions
    );
    $compte->execute($valeurs);
    $total = (int) $compte->fetchColumn();

    $pages = max(1, (int) ceil($total / PRODUITS_PAR_PAGE));
    $page = min(max(1, $page), $pages);
    $tri = TRIS_CATALOGUE[$filtres['tri'] ?? ''] ?? TRIS_CATALOGUE['nouveaute'];

    $requete = db()->prepare(SQL_PRODUITS . $conditions . ' ORDER BY ' . $tri[1] . ' LIMIT :limite OFFSET :debut');
    foreach ($valeurs as $nom => $valeur) {
        $requete->bindValue($nom, $valeur, is_int($valeur) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $requete->bindValue('limite', PRODUITS_PAR_PAGE, PDO::PARAM_INT);
    $requete->bindValue('debut', ($page - 1) * PRODUITS_PAR_PAGE, PDO::PARAM_INT);
    $requete->execute();

    return ['total' => $total, 'pages' => $pages, 'page' => $page, 'produits' => $requete->fetchAll()];
}
