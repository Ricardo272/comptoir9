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
require __DIR__ . '/../includes/panier.php';

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
$cas[] = ['plan du site : 64 produits seulement',     count(slugsProduits()), 64];
db()->rollBack();
$cas[] = ['base intacte après le test', count(produitsNouveautes(200)), 80];
$cas[] = ['plan du site : 80 produits',  count(slugsProduits()), 80];

// --- Recherche, filtres, tri, pagination ---------------------------------------
$total = fn (array $filtres) => rechercherProduits($filtres)['total'];
$sql   = fn (string $requete) => (int) db()->query($requete)->fetchColumn(); // comptage indépendant

$cas[] = ['sans filtre : 80, 4 pages',   array_slice(rechercherProduits([]), 0, 2), ['total' => 80, 'pages' => 4]];
$cas[] = ['page 1 : 24 produits',        count(rechercherProduits([])['produits']), 24];
$cas[] = ['page 4 : 8 produits',         count(rechercherProduits([], 4)['produits']), 8];
$cas[] = ['page 99 ramenée à la 4',      rechercherProduits([], 99)['page'], 4];
$cas[] = ['page 0 ramenée à la 1',       rechercherProduits([], 0)['page'], 1];
$cas[] = ['rayon cuisine : 16',          $total(['rayon' => trouverRayon('cuisine')]), 16];
$cas[] = ['sous-rayon cuisson : 4',      $total(['rayon' => trouverRayon('cuisson')]), 4];
$cas[] = ['bonnes affaires : 19',        $total(['rayon' => trouverRayon('bonnes-affaires')]), 19];
$cas[] = ['rayon inconnu',               trouverRayon('inconnu'), null];
$cas[] = ['boutique braquet : 14',       $total(['boutique' => 'braquet']), 14];
$cas[] = ['cuisine chez Maison Lin : 2', $total(['rayon' => trouverRayon('cuisine'), 'boutique' => 'maison-lin']), 2];
$cas[] = ['en stock : 75',               $total(['en_stock' => true]), 75];
$cas[] = ['de 10 à 20 €',                $total(['prix_min' => 1000, 'prix_max' => 2000]),
                                         $sql('SELECT COUNT(*) FROM produits WHERE prix BETWEEN 1000 AND 2000')];
$cas[] = ['recherche « lin »',           $total(['q' => 'lin']),
                                         $sql("SELECT COUNT(*) FROM produits WHERE nom LIKE '%lin%' OR description LIKE '%lin%'")];
$cas[] = ['recherche sans casse ni accent', $total(['q' => 'VELO']), $total(['q' => 'vélo'])];
$cas[] = ['recherche « vélo » trouve',   $total(['q' => 'vélo']) > 0, true];
$cas[] = ['deux mots : tous exigés',     $total(['q' => 'couteau office']), 1];
$cas[] = ['apostrophe',                  $total(['q' => "d'office"]), 1];
$cas[] = ['% cherché tel quel : 0',      $total(['q' => '%']), 0];
$cas[] = ['_ cherché tel quel : 0',      $total(['q' => '_']), 0];
$cas[] = ['tentative d\'injection : 0',  $total(['q' => "' OR 1=1 -- "]), 0];
$cas[] = ['tri prix croissant',          rechercherProduits(['tri' => 'prix-asc'])['produits'][0]['prix'],
                                         $sql('SELECT MIN(prix) FROM produits')];
$cas[] = ['tri prix décroissant',        rechercherProduits(['tri' => 'prix-desc'])['produits'][0]['prix'],
                                         $sql('SELECT MAX(prix) FROM produits')];
$cas[] = ['tri inconnu = nouveautés',    array_column(rechercherProduits(['tri' => 'p.id; DROP'])['produits'], 'slug'),
                                         array_column(rechercherProduits([])['produits'], 'slug')];

// --- Fiche produit et fiche boutique ------------------------------------------
$cocotte = produit('cocotte-fonte-emaillee-24');
$autres  = autresProduits($cocotte['boutique_id'], $cocotte['id'], 4);
$cas[] = ['produit : prix et boutique',  [$cocotte['prix'], $cocotte['boutique_slug'], $cocotte['rayon_slug']], [8900, 'la-casserole-bleue', 'cuisson']];
$cas[] = ['produit : 3 caractéristiques', count(explode("
", $cocotte['caracteristiques'])), 3];
$cas[] = ['produit inconnu',             produit('inconnu'), null];
$cas[] = ['produit : slug vide',         produit(''), null];
$cas[] = ['4 autres produits',           count($autres), 4];
$cas[] = ['autres : sans le produit lui-même', in_array($cocotte['slug'], array_column($autres, 'slug'), true), false];
$cas[] = ['autres : même boutique',      array_unique(array_column($autres, 'boutique')), ['La Casserole Bleue']];
$braquet = boutique('braquet');
$cas[] = ['boutique : 14 produits',      (int) $braquet['nb_produits'], 14];
$cas[] = ['boutique : délai moyen',      (int) $braquet['delai_moyen'], $sql('SELECT ROUND(AVG(delai_jours)) FROM produits WHERE boutique_id = 4')];
$cas[] = ['boutique inconnue',           boutique('inconnue'), null];

db()->beginTransaction();
db()->exec("UPDATE boutiques SET etat = 'suspendue' WHERE slug = 'braquet'");
db()->exec("UPDATE produits SET etat = 'masque' WHERE slug = 'cocotte-fonte-emaillee-24'");
db()->exec("UPDATE produits SET etat = 'retire' WHERE slug = 'jeu-cordee'");
$cas[] = ['boutique suspendue : fiche absente',       boutique('braquet'), null];
$cas[] = ['produit d\x27une boutique suspendue : absent', produit('velo-gravel-braquet-g1'), null];
$cas[] = ['produit masqué : absent',                  produit('cocotte-fonte-emaillee-24'), null];
$cas[] = ['produit retiré : absent',                  produit('jeu-cordee'), null];
db()->rollBack();
$cas[] = ['produit de retour après le test', produit('jeu-cordee') !== null, true];

// --- Images des produits : chaque produit a une illustration qui existe -------------------
$enBasePanier = produitsParId(array_map('intval', db()->query('SELECT id FROM produits ORDER BY id LIMIT 1')->fetchAll(PDO::FETCH_COLUMN)));
$sansImage = array_filter(produitsNouveautes(200), fn ($p) => !is_file(__DIR__ . '/../assets/img/' . cheminImageProduit($p)));
$cas[] = ['image : aucun produit sans fichier',        array_column($sansImage, 'slug'), []];
$cas[] = ['image : illustration du sous-rayon',        cheminImageProduit(['slug' => 'sans-photo', 'rayon_slug' => 'cuisson']), 'rayons/cuisson.svg'];
$cas[] = ['image : illustration au texte vide',        str_contains(imageProduit(['slug' => 'sans-photo', 'rayon_slug' => 'cuisson', 'nom' => 'Cocotte'], 600), 'alt=""'), true];
$cas[] = ['image : pas d\'image de remplacement',      str_contains(imageProduit($cocotte, 600), 'image-remplacement'), false];
$cas[] = ['image : la fiche et le panier ont le sous-rayon', [$cocotte['rayon_slug'], reset($enBasePanier)['rayon_slug'] !== ''], ['cuisson', true]];
// Une vraie photo déposée prend le pas sur l'illustration (fichier créé puis supprimé)
$photo = __DIR__ . '/../assets/img/produits/test-photo-temporaire.jpg';
@mkdir(dirname($photo), 0755, true);
file_put_contents($photo, 'x');
$cas[] = ['image : la photo prend le pas',             cheminImageProduit(['slug' => 'test-photo-temporaire', 'rayon_slug' => 'cuisson']), 'produits/test-photo-temporaire.jpg'];
$cas[] = ['image : photo décrite par le nom',          str_contains(imageProduit(['slug' => 'test-photo-temporaire', 'rayon_slug' => 'cuisson', 'nom' => 'Cocotte'], 600), 'alt="Cocotte"'), true];
unlink($photo);
@rmdir(dirname($photo));

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
