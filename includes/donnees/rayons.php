<?php
/**
 * Les rayons et leurs sous-rayons, dans l'ordre d'affichage.
 *
 * PROVISOIRE (phase 1) : la liste est écrite ici. En phase 4, cette fonction
 * lira la table « rayons » ; les pages qui l'appellent ne changeront pas.
 *
 * Chaque « slug » est unique, rayons et sous-rayons confondus : il sert
 * d'adresse (catalogue.php?rayon=cuisson).
 */
function rayons(): array
{
    $liste = [
        'high-tech' => ['High-tech', [
            'ordinateurs-tablettes' => 'Ordinateurs et tablettes',
            'audio'                 => 'Audio',
            'accessoires-cables'    => 'Accessoires et câbles',
            'maison-connectee'      => 'Maison connectée',
        ]],
        'cuisine' => ['Cuisine', [
            'cuisson'              => 'Cuisson',
            'couteaux-ustensiles'  => 'Couteaux et ustensiles',
            'petit-electromenager' => 'Petit électroménager',
            'art-de-la-table'      => 'Art de la table',
        ]],
        'maison' => ['Maison', [
            'linge-de-lit'  => 'Linge de lit',
            'linge-de-bain' => 'Linge de bain',
            'decoration'    => 'Décoration',
            'luminaires'    => 'Luminaires',
        ]],
        'sport-velo' => ['Sport et vélo', [
            'velos'               => 'Vélos',
            'equipement-cycliste' => 'Équipement du cycliste',
            'entretien-pieces'    => 'Entretien et pièces',
            'plein-air'           => 'Plein air',
        ]],
        'jeux-jouets' => ['Jeux et jouets', [
            'jeux-de-societe' => 'Jeux de société',
            'jeux-de-cartes'  => 'Jeux de cartes',
            'puzzles'         => 'Puzzles',
            'jouets-en-bois'  => 'Jouets en bois',
        ]],
        // Pas de sous-rayon : tous les produits en promotion, tous rayons confondus.
        'bonnes-affaires' => ['Bonnes affaires', []],
    ];

    $rayons = [];
    foreach ($liste as $slug => [$nom, $sousRayons]) {
        $rayon = ['slug' => $slug, 'nom' => $nom, 'sous_rayons' => []];
        foreach ($sousRayons as $sousSlug => $sousNom) {
            $rayon['sous_rayons'][] = ['slug' => $sousSlug, 'nom' => $sousNom];
        }
        $rayons[] = $rayon;
    }

    return $rayons;
}

/** Adresse du catalogue pour un rayon ou un sous-rayon. */
function urlRayon(string $slug): string
{
    return url('catalogue.php?rayon=' . rawurlencode($slug));
}
