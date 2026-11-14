<?php
/**
 * Produits et boutiques affichés sur l'accueil.
 *
 * PROVISOIRE (phase 2) : quelques exemples écrits ici pour monter les cartes.
 * En phase 4, ces fonctions liront les tables « produits » et « boutiques » ;
 * les clés des tableaux sont déjà celles des futures colonnes.
 * Prix en centimes.
 */
function produitsAccueil(): array
{
    return [
        [
            'slug' => 'casque-sans-fil-octet-s2', 'nom' => 'Casque sans fil Octet S2',
            'boutique' => 'Octet & Cie', 'prix' => 7990, 'prix_barre' => 9990, 'stock' => 14,
        ],
        [
            'slug' => 'cocotte-fonte-24', 'nom' => 'Cocotte en fonte émaillée 24 cm',
            'boutique' => 'La Casserole Bleue', 'prix' => 8900, 'prix_barre' => null, 'stock' => 2,
        ],
        [
            'slug' => 'parure-lin-lave-240', 'nom' => 'Parure de lit en lin lavé 240 × 220',
            'boutique' => 'Maison Lin', 'prix' => 12900, 'prix_barre' => null, 'stock' => 8,
        ],
        [
            'slug' => 'sacoche-guidon-6l', 'nom' => 'Sacoche de guidon étanche 6 L',
            'boutique' => 'Braquet', 'prix' => 3450, 'prix_barre' => 4200, 'stock' => 0,
        ],
    ];
}

function boutiquesAccueil(): array
{
    return [
        [
            'slug' => 'octet-et-cie', 'nom' => 'Octet & Cie', 'ville' => 'Villeurbanne', 'rayon' => 'High-tech',
            'accroche' => 'Du matériel choisi et testé par deux anciens techniciens de maintenance.',
        ],
        [
            'slug' => 'la-casserole-bleue', 'nom' => 'La Casserole Bleue', 'ville' => 'Lyon 2e', 'rayon' => 'Cuisine',
            'accroche' => 'Des ustensiles faits pour durer, conseillés par une ancienne cheffe de partie.',
        ],
    ];
}
