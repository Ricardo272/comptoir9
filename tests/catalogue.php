<?php
/**
 * Contrôle du catalogue, sur la base remplie par sql/seed.sql.
 * À lancer en ligne de commande :  php tests/catalogue.php
 * Code de sortie 0 si tout passe, 1 sinon.
 *
 * Les contrôles qui modifient des données le font dans une transaction
 * annulée à la fin : la base ressort intacte.
 */
if (PHP_SAPI !== 'cli') {
    exit;
}

require __DIR__ . '/../includes/donnees/rayons.php';
require __DIR__ . '/../includes/donnees/catalogue.php';

$echecs = 0;

function verifier(string $nom, mixed $obtenu, mixed $attendu, bool $silencieux = false): bool
{
    $ok = $obtenu === $attendu;
    if (!$silencieux) {
        echo ($ok ? 'OK     ' : 'ÉCHEC  ') . $nom
           . ($ok ? '' : ' : obtenu ' . json_encode($obtenu, JSON_UNESCAPED_UNICODE)
                       . ', attendu ' . json_encode($attendu, JSON_UNESCAPED_UNICODE)) . PHP_EOL;
    }
    return $ok;
}

// Le contrôle doit savoir échouer : le seed contient 80 produits, pas 81.
if (verifier('cas faux', count(produitsNouveautes(200)), 81, true)) {
    echo 'ÉCHEC  le contrôle accepte un résultat faux' . PHP_EOL;
    exit(1);
}
echo 'OK     le contrôle refuse un cas volontairement faux' . PHP_EOL;

// --- Rayons ---------------------------------------------------------------
$rayons = rayons();
$cas = [
    ['6 rayons, dont Bonnes affaires', count($rayons), 6],
    ['premier rayon',                  $rayons[0]['slug'], 'high-tech'],
    ['dernier rayon',                  $rayons[5]['slug'], 'bonnes-affaires'],
    ['4 sous-rayons par rayon',        array_map(fn ($r) => count($r['sous_rayons']), $rayons), [4, 4, 4, 4, 4, 0]],
    ['ordre des sous-rayons',          array_column($rayons[1]['sous_rayons'], 'slug'),
                                       ['cuisson', 'couteaux-ustensiles', 'petit-electromenager', 'art-de-la-table']],
];

// --- Produits de l'accueil -------------------------------------------------
$tous       = produitsNouveautes(200);
$nouveautes = produitsNouveautes(8);
$affaires   = produitsBonnesAffaires(4);
$remise     = fn ($p) => ($p['prix_barre'] - $p['prix']) / $p['prix_barre'];

$cas[] = ['80 produits visibles',        count($tous), 80];
$cas[] = ['8 nouveautés',                count($nouveautes), 8];
$cas[] = ['nouveautés = les 8 premiers', array_column($nouveautes, 'slug'), array_slice(array_column($tous, 'slug'), 0, 8)];
$cas[] = ['prix en centimes entiers',    is_int($tous[0]['prix']), true];
$cas[] = ['4 bonnes affaires',           count($affaires), 4];
$cas[] = ['toutes en promotion',         count(array_filter($affaires, fn ($p) => $p['prix_barre'] > $p['prix'])), 4];
$cas[] = ['toutes en stock',             count(array_filter($affaires, fn ($p) => $p['stock'] > 0)), 4];
$cas[] = ['remises décroissantes',       $remise($affaires[0]) >= $remise($affaires[3]), true];

// --- Boutiques -------------------------------------------------------------
$boutiques = boutiques();
$cas[] = ['5 boutiques',          count($boutiques), 5];
$cas[] = ['80 produits au total', array_sum(array_column($boutiques, 'nb_produits')), 80];

// --- Visibilité : boutique suspendue, produit retiré ou masqué ---------------
db()->beginTransaction();
db()->exec("UPDATE boutiques SET etat = 'suspendue' WHERE slug = 'braquet'");            // 14 produits
db()->exec("UPDATE produits SET etat = 'retire' WHERE slug = 'cable-usb-c-tresse-2m'");
db()->exec("UPDATE produits SET etat = 'masque' WHERE slug = 'jeu-cordee'");
$cas[] = ['suspendue, retiré, masqué : 64 visibles', count(produitsNouveautes(200)), 64];
$cas[] = ['boutique suspendue absente',              count(boutiques()), 4];
db()->rollBack();
$cas[] = ['base intacte après le test', count(produitsNouveautes(200)), 80];

// --- Droits de l'utilisateur du site : il ne doit pas pouvoir toucher à la structure
$refus = function (string $sql): bool {
    try {
        db()->exec($sql);
        return false;
    } catch (PDOException $erreur) {
        return true;
    }
};
$cas[] = ['DROP TABLE refusé',   $refus('DROP TABLE produits'), true];
$cas[] = ['CREATE TABLE refusé', $refus('CREATE TABLE intrus (id INT)'), true];
$cas[] = ['fausse promotion refusée (CHECK)',
          $refus("UPDATE produits SET prix_barre = prix - 1 WHERE slug = 'jeu-cordee'"), true];

foreach ($cas as [$nom, $obtenu, $attendu]) {
    if (!verifier($nom, $obtenu, $attendu)) {
        $echecs++;
    }
}

echo PHP_EOL . ($echecs === 0 ? 'Tout passe.' : $echecs . ' échec(s).') . PHP_EOL;
exit($echecs === 0 ? 0 : 1);
