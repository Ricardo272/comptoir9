<?php
/**
 * Tout le SQL de l'espace vendeur.
 *
 * RÈGLE : chaque fonction reçoit l'identifiant de la boutique du compte connecté
 * ($compte['boutique_id'], relu en base par compteConnecte()) et chaque requête
 * filtre dessus. Une valeur venue de l'adresse ou d'un formulaire (un id de colis,
 * de produit) n'est jamais crue seule : elle doit AUSSI appartenir à cette boutique.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../formulaire.php';
require_once __DIR__ . '/../donnees/rayons.php';


/* ==========================================================================
   Colis
   ========================================================================== */

/**
 * Les colis de la boutique, à préparer d'abord, avec l'adresse de livraison
 * (nécessaire pour expédier) et leurs lignes.
 * shortcut: limités aux 200 derniers, sans pagination (commandes supprimées après 30 jours).
 */
function colisDeLaBoutique(int $boutiqueId): array
{
    $requete = db()->prepare(
        "SELECT c.id, c.sous_total, c.port, c.etat, c.expedie_le,
                o.numero, o.prenom, o.nom, o.telephone, o.adresse, o.complement, o.code_postal, o.ville, o.cree_le
         FROM colis c
         JOIN commandes o ON o.id = c.commande_id
         WHERE c.boutique_id = :boutique
         ORDER BY c.etat = 'expedie', o.cree_le DESC, c.id DESC
         LIMIT 200"
    );
    $requete->execute(['boutique' => $boutiqueId]);
    $colis = array_column($requete->fetchAll(), null, 'id');

    if ($colis) {
        $lignes = db()->prepare(
            'SELECT l.colis_id, l.nom, l.prix, l.quantite
             FROM commande_lignes l JOIN colis c ON c.id = l.colis_id
             WHERE c.boutique_id = :boutique AND l.colis_id IN (' . implode(',', array_map('intval', array_keys($colis))) . ')
             ORDER BY l.id'
        );
        $lignes->execute(['boutique' => $boutiqueId]);
        foreach ($lignes->fetchAll() as $ligne) {
            $colis[$ligne['colis_id']]['lignes'][] = $ligne;
        }
    }

    return $colis;
}

/**
 * Marque un colis comme expédié. Renvoie false si le colis n'existe pas,
 * n'appartient pas à cette boutique, ou est déjà expédié.
 */
function expedierColis(int $colisId, int $boutiqueId): bool
{
    $requete = db()->prepare(
        "UPDATE colis SET etat = 'expedie', expedie_le = NOW()
         WHERE id = :id AND boutique_id = :boutique AND etat = 'en_preparation'"
    );
    $requete->execute(['id' => $colisId, 'boutique' => $boutiqueId]);
    return $requete->rowCount() === 1;
}


/* ==========================================================================
   Produits
   ========================================================================== */

/** Les produits de la boutique, tous états confondus, par sous-rayon puis par nom. */
function produitsDeLaBoutique(int $boutiqueId): array
{
    $requete = db()->prepare(
        'SELECT p.id, p.slug, p.nom, p.prix, p.prix_barre, p.stock, p.etat, r.nom AS rayon, r.slug AS rayon_slug
         FROM produits p JOIN rayons r ON r.id = p.rayon_id
         WHERE p.boutique_id = :boutique
         ORDER BY r.parent_id, r.ordre, p.nom'
    );
    $requete->execute(['boutique' => $boutiqueId]);
    return $requete->fetchAll();
}

/** Un produit de la boutique, ou null s'il n'existe pas ou appartient à une autre boutique. */
function produitDeLaBoutique(int $produitId, int $boutiqueId): ?array
{
    $requete = db()->prepare(
        'SELECT id, slug, nom, description, caracteristiques, prix, prix_barre, stock, delai_jours, rayon_id, etat
         FROM produits WHERE id = :id AND boutique_id = :boutique'
    );
    $requete->execute(['id' => $produitId, 'boutique' => $boutiqueId]);
    return $requete->fetch() ?: null;
}

/**
 * Retire de la vente, ou remet en vente, un produit de la boutique.
 * Un produit masqué par l'administration reste masqué : le vendeur n'y touche pas.
 */
function changerEtatProduit(int $produitId, int $boutiqueId, bool $enVente): bool
{
    $requete = db()->prepare(
        "UPDATE produits SET etat = :nouvel
         WHERE id = :id AND boutique_id = :boutique AND etat = :ancien"
    );
    $requete->execute([
        'nouvel'   => $enVente ? 'en_vente' : 'retire',
        'ancien'   => $enVente ? 'retire' : 'en_vente',
        'id'       => $produitId,
        'boutique' => $boutiqueId,
    ]);
    return $requete->rowCount() === 1;
}

/**
 * Prix saisi en euros, rendu en centimes : « 12,90 » → 1290 ; « 12 » → 1200 ;
 * « 12.9 » → 1290. null si la saisie n'est pas un prix (lettres, négatif, 3 décimales…).
 */
function lireCentimes(string $saisie): ?int
{
    $saisie = str_replace([' ', "\u{00A0}", "\u{202F}", '€'], '', trim($saisie));
    if (!preg_match('/^(\d{1,6})(?:[.,](\d{1,2}))?$/', $saisie, $m)) {
        return null;
    }
    return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
}

/** Centimes vers la saisie d'un champ : 1290 → « 12,90 ». */
function centimesPourChamp(?int $centimes): string
{
    return $centimes === null ? '' : intdiv($centimes, 100) . ',' . sprintf('%02d', $centimes % 100);
}

/**
 * Contrôle le formulaire d'un produit. Renvoie [valeurs prêtes pour la base, erreurs par champ].
 * Les valeurs gardent aussi la saisie brute des prix, pour réafficher le formulaire.
 */
function verifierProduit(array $post): array
{
    $v = lireChamps($post, ['nom', 'rayon_id', 'description', 'caracteristiques', 'prix', 'prix_barre', 'stock', 'delai_jours']);
    $erreurs = [];

    if (mb_strlen($v['nom']) < 3 || mb_strlen($v['nom']) > 120) {
        $erreurs['nom'] = 'Indiquez le nom du produit (3 à 120 caractères).';
    }

    // Le sous-rayon doit être l'un de ceux du site (jamais un rayon parent, ni un id inventé)
    $sousRayons = array_merge(...array_column(rayons(), 'sous_rayons'));
    $rayonId = filter_var($v['rayon_id'], FILTER_VALIDATE_INT);
    if (!in_array($rayonId, array_column($sousRayons, 'id'), true)) {
        $erreurs['rayon_id'] = 'Choisissez le rayon du produit.';
    }

    if (mb_strlen($v['description']) < 10 || mb_strlen($v['description']) > 500) {
        $erreurs['description'] = 'Écrivez une courte description (10 à 500 caractères).';
    }

    // Caractéristiques : une par ligne, lignes vides ignorées
    $lignes = array_values(array_filter(array_map('trim', preg_split('/\R/u', $v['caracteristiques']))));
    if (!$lignes || count($lignes) > 8 || max(array_map('mb_strlen', $lignes)) > 100) {
        $erreurs['caracteristiques'] = 'Indiquez 1 à 8 caractéristiques, une par ligne (100 caractères au plus chacune).';
    }

    $prix = lireCentimes($v['prix']);
    if ($prix === null || $prix < 1) {
        $erreurs['prix'] = 'Indiquez un prix, par exemple 12,90.';
    }
    $prixBarre = null;
    if ($v['prix_barre'] !== '') {
        $prixBarre = lireCentimes($v['prix_barre']);
        if ($prixBarre === null) {
            $erreurs['prix_barre'] = 'Indiquez un ancien prix, par exemple 15,90, ou laissez le champ vide.';
        } elseif ($prix !== null && $prixBarre <= $prix) {
            $erreurs['prix_barre'] = 'L\'ancien prix doit être plus élevé que le prix : sinon ce n\'est pas une promotion.';
        }
    }

    $stock = filter_var($v['stock'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9999]]);
    if ($stock === false) {
        $erreurs['stock'] = 'Indiquez un stock entre 0 et 9999.';
    }
    $delai = filter_var($v['delai_jours'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 30]]);
    if ($delai === false) {
        $erreurs['delai_jours'] = 'Indiquez un délai d\'expédition entre 1 et 30 jours.';
    }

    $pourLaBase = [
        'nom'              => $v['nom'],
        'rayon_id'         => $rayonId,
        'description'      => $v['description'],
        'caracteristiques' => implode("\n", $lignes),
        'prix'             => $prix,
        'prix_barre'       => $prixBarre,
        'stock'            => $stock,
        'delai_jours'      => $delai,
    ];

    return [['saisie' => $v] + $pourLaBase, $erreurs];
}

/**
 * Slug d'un nouveau produit, tiré de son nom et unique :
 * « Vélo équipé » → « velo-equipe », puis « velo-equipe-2 » s'il est déjà pris.
 */
function slugUnique(string $nom): string
{
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $nom)), '-');
    $base = substr($base, 0, 100) ?: 'produit';
    $existe = db()->prepare('SELECT 1 FROM produits WHERE slug = :slug');
    for ($i = 1, $slug = $base; ; $slug = $base . '-' . ++$i) {
        $existe->execute(['slug' => $slug]);
        if (!$existe->fetchColumn()) {
            // shortcut: deux créations simultanées du même nom peuvent viser le même slug ;
            // la clé unique refuse alors la seconde (page 503). Suffisant pour une petite boutique.
            return $slug;
        }
    }
}

/** Ajoute un produit à la boutique, en vente. Renvoie son identifiant. */
function ajouterProduit(array $p, int $boutiqueId): int
{
    db()->prepare(
        'INSERT INTO produits (boutique_id, rayon_id, slug, nom, description, caracteristiques, prix, prix_barre, stock, delai_jours)
         VALUES (:boutique, :rayon_id, :slug, :nom, :description, :caracteristiques, :prix, :prix_barre, :stock, :delai_jours)'
    )->execute([
        'boutique' => $boutiqueId,
        'slug'     => slugUnique($p['nom']),
    ] + array_intersect_key($p, array_flip(['rayon_id', 'nom', 'description', 'caracteristiques', 'prix', 'prix_barre', 'stock', 'delai_jours'])));
    return (int) db()->lastInsertId();
}

/**
 * Modifie un produit de la boutique. Le slug ne change pas : l'adresse du produit reste la même.
 * Renvoie false si le produit n'appartient pas à cette boutique.
 */
function modifierProduit(int $produitId, array $p, int $boutiqueId): bool
{
    if (produitDeLaBoutique($produitId, $boutiqueId) === null) {
        return false;
    }
    db()->prepare(
        'UPDATE produits SET rayon_id = :rayon_id, nom = :nom, description = :description, caracteristiques = :caracteristiques,
                             prix = :prix, prix_barre = :prix_barre, stock = :stock, delai_jours = :delai_jours
         WHERE id = :id AND boutique_id = :boutique'
    )->execute([
        'id'       => $produitId,
        'boutique' => $boutiqueId,
    ] + array_intersect_key($p, array_flip(['rayon_id', 'nom', 'description', 'caracteristiques', 'prix', 'prix_barre', 'stock', 'delai_jours'])));
    return true;
}
