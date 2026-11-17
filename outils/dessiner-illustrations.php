<?php
/**
 * Fabrique les illustrations des sous-rayons : assets/img/rayons/<slug>.svg.
 * Un produit sans photo affiche l'illustration de son sous-rayon (voir
 * cheminImageProduit() dans includes/donnees/catalogue.php).
 *
 * Utilisation, depuis la racine du projet :
 *   php outils/dessiner-illustrations.php
 * À relancer après avoir modifié un dessin ci-dessous. Les fichiers produits
 * sont versionnés : le site n'a pas besoin de ce script pour fonctionner.
 *
 * Toutes les illustrations partagent le même cadre (fond papier, disque jaune)
 * et le même trait (encre, bouts ronds) : seules les formes changent.
 * Dans une forme, « P » remplit en papier, « E » en encre, « J » trace en jaune.
 */
if (PHP_SAPI !== 'cli') {
    exit('À lancer en ligne de commande.');
}

const P = 'fill="#F5F3EE"';
const E = 'fill="#16211D"';
const J = 'stroke="#FFC531"';

$dessins = [
    // --- High-tech ---------------------------------------------------------
    'ordinateurs-tablettes' =>
        '<rect x="170" y="190" width="260" height="170" rx="12" ' . P . '/>'
      . '<path d="M130 400H470l-22 34H152Z" ' . P . '/>',
    'audio' =>
        '<path d="M180 330v-30a120 120 0 0 1 240 0v30"/>'
      . '<rect x="150" y="320" width="62" height="104" rx="22" ' . P . '/>'
      . '<rect x="388" y="320" width="62" height="104" rx="22" ' . P . '/>',
    'accessoires-cables' =>
        '<rect x="272" y="130" width="56" height="44" ' . P . '/>'
      . '<rect x="246" y="174" width="108" height="130" rx="12" ' . P . '/>'
      . '<path d="M300 304v50c0 70-100 40-100 110"/>',
    'maison-connectee' =>
        '<path d="M170 310 300 190 430 310V440H170Z" ' . P . '/>'
      . '<path d="M252 366a68 68 0 0 1 96 0"/><path d="M276 394a34 34 0 0 1 48 0"/>'
      . '<circle cx="300" cy="418" r="5" ' . E . '/>',

    // --- Cuisine -------------------------------------------------------------
    'cuisson' =>
        '<path d="M190 282a110 62 0 0 1 220 0Z" ' . P . '/>'
      . '<rect x="170" y="282" width="260" height="140" rx="30" ' . P . '/>'
      . '<path d="M156 282H444"/><path d="M170 326h-42"/><path d="M430 326h42"/>'
      . '<circle cx="300" cy="206" r="13" ' . P . '/>',
    'couteaux-ustensiles' =>
        '<path d="M262 348 432 178q14 118-92 172Z" ' . P . '/>'
      . '<path d="M172 438 262 348" stroke-width="38"/>',
    'petit-electromenager' =>
        '<path d="M374 268q78 22 12 124"/>'
      . '<path d="M210 432 230 232H370l20 200Z" ' . P . '/>'
      . '<path d="M240 232a60 30 0 0 1 120 0Z" ' . P . '/>'
      . '<path d="M228 262 176 240"/>',
    'art-de-la-table' =>
        '<circle cx="300" cy="300" r="112" ' . P . '/><circle cx="300" cy="300" r="64"/>'
      . '<path d="M146 190v236"/><path d="M124 190v56a22 22 0 0 0 44 0v-56"/>'
      . '<path d="M454 190v236"/><path d="M454 190q34 62 0 116"/>',

    // --- Maison ----------------------------------------------------------------
    'linge-de-lit' =>
        '<rect x="150" y="206" width="300" height="90" rx="14" ' . P . '/>'
      . '<rect x="182" y="250" width="104" height="52" rx="14" ' . P . '/>'
      . '<rect x="130" y="292" width="340" height="92" rx="14" ' . P . '/>'
      . '<path d="M152 384v40"/><path d="M448 384v40"/>',
    'linge-de-bain' =>
        '<rect x="208" y="200" width="184" height="224" ' . P . '/>'
      . '<path d="M146 200H454"/><path d="M208 356h184"/><path d="M208 384h184"/>',
    'decoration' =>
        '<path d="M300 252V158"/><path d="M300 208q42-34 66-10"/><path d="M300 184q-42-34-66-10"/>'
      . '<path d="M252 252h96q-12 42 30 92 30 62-30 92h-96q-60-30-30-92 42-50 30-92Z" ' . P . '/>',
    'luminaires' =>
        '<path d="M300 300V404"/>'
      . '<path d="M218 300 250 188H350l32 112Z" ' . P . '/>'
      . '<path d="M228 424a72 22 0 0 1 144 0Z" ' . P . '/>',

    // --- Sport et vélo -----------------------------------------------------------
    'velos' =>
        '<circle cx="196" cy="372" r="72" ' . P . '/><circle cx="404" cy="372" r="72" ' . P . '/>'
      . '<path d="M196 372 268 258H372L404 372"/><path d="M268 258 312 372 372 258"/><path d="M196 372H312"/>'
      . '<path d="M246 250h48"/><path d="M372 258 362 222h30"/>',
    'equipement-cycliste' =>
        '<path d="M160 362a140 134 0 0 1 280 0Z" ' . P . '/>'
      . '<path d="M160 362H472"/><path d="M232 304 252 252"/><path d="M300 292v-62"/><path d="M368 304 348 252"/>'
      . '<path d="M232 362l40 62 40-62"/>',
    'entretien-pieces' =>
        '<path d="M200 430 350 280" stroke-width="32"/>'
      . '<circle cx="382" cy="248" r="56" ' . P . '/>'
      . '<path d="M386 244 442 188" stroke-width="36" ' . J . '/>',
    'plein-air' =>
        '<circle cx="404" cy="206" r="30" ' . P . '/>'
      . '<path d="M128 432 250 250l82 122 48-70 92 130Z" ' . P . '/>'
      . '<path d="M224 290l26 20 26-18"/>',

    // --- Jeux et jouets -------------------------------------------------------------
    'jeux-de-societe' =>
        '<rect x="200" y="200" width="200" height="200" rx="30" ' . P . '/>'
      . '<g stroke="none" ' . E . '><circle cx="250" cy="250" r="15"/><circle cx="350" cy="250" r="15"/>'
      . '<circle cx="300" cy="300" r="15"/><circle cx="250" cy="350" r="15"/><circle cx="350" cy="350" r="15"/></g>',
    'jeux-de-cartes' =>
        '<rect x="186" y="200" width="150" height="212" rx="16" ' . P . ' transform="rotate(-12 261 306)"/>'
      . '<g transform="rotate(10 345 296)"><rect x="270" y="190" width="150" height="212" rx="16" ' . P . '/>'
      . '<path d="M345 250l30 46-30 46-30-46Z" stroke="none" ' . E . '/></g>',
    'puzzles' =>
        '<path d="M200 240h70a30 30 0 1 1 60 0h70v70a30 30 0 1 1 0 60v70H200Z" ' . P . '/>',
    'jouets-en-bois' =>
        '<rect x="200" y="236" width="42" height="56" ' . P . '/>'
      . '<rect x="166" y="290" width="186" height="92" rx="10" ' . P . '/>'
      . '<rect x="352" y="228" width="92" height="154" rx="10" ' . P . '/>'
      . '<rect x="377" y="254" width="42" height="42"/>'
      . '<circle cx="226" cy="402" r="34" ' . P . '/><circle cx="318" cy="402" r="34" ' . P . '/><circle cx="404" cy="402" r="34" ' . P . '/>',
];

$dossier = __DIR__ . '/../assets/img/rayons';
if (!is_dir($dossier)) {
    mkdir($dossier, 0755, true);
}

foreach ($dessins as $slug => $formes) {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600">'
         . '<rect width="600" height="600" fill="#F5F3EE"/>'
         . '<circle cx="300" cy="300" r="214" fill="#FFC531"/>'
         . '<g fill="none" stroke="#16211D" stroke-width="14" stroke-linecap="round" stroke-linejoin="round">'
         . $formes
         . '</g></svg>' . "\n";

    // Un SVG mal formé ne s'afficherait pas : on le relit avant de l'écrire
    if (@simplexml_load_string($svg) === false) {
        fwrite(STDERR, "Dessin mal formé : $slug\n");
        exit(1);
    }
    file_put_contents("$dossier/$slug.svg", $svg);
}

echo count($dessins) . " illustrations écrites dans assets/img/rayons/.\n";
