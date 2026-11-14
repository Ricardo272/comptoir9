<?php
/**
 * Les rayons et leurs sous-rayons, lus dans la table « rayons ».
 */
require_once __DIR__ . '/../db.php';

/**
 * Tous les rayons dans l'ordre d'affichage, chacun avec ses sous-rayons :
 *   [['id' => 2, 'slug' => 'cuisine', 'nom' => 'Cuisine',
 *     'sous_rayons' => [['id' => 21, 'slug' => 'cuisson', 'nom' => 'Cuisson'], …]], …]
 * Le dernier, « Bonnes affaires », n'est pas en base : c'est un rayon calculé
 * (tous les produits en promotion), sans identifiant ni sous-rayon.
 * Une seule requête par page : le résultat est gardé en mémoire.
 */
function rayons(): array
{
    static $rayons = null;

    if ($rayons === null) {
        // Les rayons (parent_id NULL) sortent avant leurs sous-rayons
        $lignes = db()->query(
            'SELECT id, parent_id, slug, nom FROM rayons ORDER BY parent_id IS NOT NULL, ordre, id'
        )->fetchAll();

        $rayons = [];
        foreach ($lignes as $ligne) {
            $rayon = ['id' => $ligne['id'], 'slug' => $ligne['slug'], 'nom' => $ligne['nom']];
            if ($ligne['parent_id'] === null) {
                $rayons[$ligne['id']] = $rayon + ['sous_rayons' => []];
            } elseif (isset($rayons[$ligne['parent_id']])) {
                $rayons[$ligne['parent_id']]['sous_rayons'][] = $rayon;
            }
        }

        $rayons = array_values($rayons);
        $rayons[] = ['id' => null, 'slug' => 'bonnes-affaires', 'nom' => 'Bonnes affaires', 'sous_rayons' => []];
    }

    return $rayons;
}

/** Adresse du catalogue pour un rayon ou un sous-rayon. */
function urlRayon(string $slug): string
{
    return url('catalogue.php?rayon=' . rawurlencode($slug));
}
