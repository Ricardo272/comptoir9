<?php
/**
 * Contrôle du panier (puis de la commande, en phase 5b).
 * À lancer en ligne de commande :  php tests/commande.php
 * Code de sortie 0 si tout passe, 1 sinon.
 */
if (PHP_SAPI !== 'cli') {
    exit;
}

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

$port = ['par_boutique' => 490, 'offerts_des' => 6000];

// Produits fabriqués pour le test : le calcul ne dépend ni de la base ni de la session
$produit = fn (int $id, int $boutique, int $prix, int $stock) => [
    'id' => $id, 'slug' => "p$id", 'nom' => "Produit $id", 'prix' => $prix, 'stock' => $stock,
    'boutique_id' => $boutique, 'boutique' => "Boutique $boutique", 'boutique_slug' => "b$boutique",
];
$produits = [
    1 => $produit(1, 1, 2000, 10),
    2 => $produit(2, 1, 1999, 10),
    3 => $produit(3, 2, 6000, 10),
    4 => $produit(4, 2, 500, 2),
    5 => $produit(5, 3, 1000, 0),   // épuisé
];
$calcul = fn (array $quantites) => calculerPanier($produits, $quantites, $port);

// Le contrôle doit savoir échouer : 2 × 20,00 € + 4,90 € de port font 44,90 €, pas 40,00 €.
if (verifier('cas faux', $calcul([1 => 2])['total'], 4000, true)) {
    echo 'ÉCHEC  le contrôle accepte un résultat faux' . PHP_EOL;
    exit(1);
}
echo 'OK     le contrôle refuse un cas volontairement faux' . PHP_EOL;

$p = $calcul([1 => 2]);
$cas = [
    ['panier vide',                    $calcul([])['total'], 0],
    ['une ligne : produits',           $p['total_produits'], 4000],
    ['une ligne : port',               $p['total_port'], 490],
    ['une ligne : total',              $p['total'], 4490],
    ['une ligne : il manque 20 €',     $p['boutiques'][1]['manque'], 2000],
    ['une ligne : 2 articles',         $p['articles'], 2],
];

// Seuil de livraison offerte, par boutique
$juste = $calcul([1 => 3]);            // 60,00 € pile
$presque = $calcul([1 => 2, 2 => 1]);  // 59,99 €
$cas[] = ['60,00 € pile : port offert',     [$juste['total_port'], $juste['boutiques'][1]['manque']], [0, 0]];
$cas[] = ['59,99 € : port dû',              [$presque['total_port'], $presque['boutiques'][1]['manque']], [490, 1]];

// Deux boutiques : un colis et un forfait chacune, le seuil ne se cumule pas entre boutiques
$deux = $calcul([1 => 2, 3 => 1]);     // 40 € chez la 1, 60 € chez la 2
$cas[] = ['deux boutiques : deux colis',    count($deux['boutiques']), 2];
$cas[] = ['port : dû chez l\'une, offert chez l\'autre', [$deux['boutiques'][1]['port'], $deux['boutiques'][2]['port']], [490, 0]];
$cas[] = ['deux boutiques : total',         $deux['total'], 4000 + 6000 + 490];
$cas[] = ['100 € en deux boutiques de 50 € : deux ports', calculerPanier(
    [1 => $produit(1, 1, 5000, 5), 2 => $produit(2, 2, 5000, 5)], [1 => 1, 2 => 1], $port)['total_port'], 980];

// Stock et corrections
$trop = $calcul([4 => 9]);
$cas[] = ['quantité ramenée au stock',      [$trop['quantites'], count($trop['avis'])], [[4 => 2], 1]];
$cas[] = ['quantité ramenée au maximum',    calculerPanier([1 => $produit(1, 1, 100, 500)], [1 => 99], $port)['quantites'], [1 => QUANTITE_MAX]];
$epuise = $calcul([5 => 1, 1 => 1]);
$cas[] = ['produit épuisé retiré',          [$epuise['quantites'], count($epuise['avis'])], [[1 => 1], 1]];
$disparu = $calcul([99 => 1, 1 => 1]);
$cas[] = ['produit disparu retiré',         [$disparu['quantites'], count($disparu['avis'])], [[1 => 1], 1]];
$cas[] = ['quantité nulle ou négative ignorée', $calcul([1 => 0, 2 => -3])['articles'], 0];
$cas[] = ['aucun avis si rien à corriger',  $calcul([1 => 2, 3 => 1])['avis'], []];

// Produits relus en base : les prix viennent de la base, jamais du visiteur
$enBase = produitsParId([1, 2, 999999]);
$cas[] = ['produitsParId : 2 trouvés sur 3', count($enBase), 2];
$cas[] = ['produitsParId : prix entier',     is_int(reset($enBase)['prix']), true];
$cas[] = ['produitsParId : liste vide',      produitsParId([]), []];

// Jeton signé du bouton « Ajouter au panier »
$jeton = creerJeton();
$cas[] = ['jeton frais accepté sans délai',  verifierJeton($jeton, null, 0), 'ok'];
$cas[] = ['jeton frais refusé avec délai',   verifierJeton($jeton), 'trop_rapide'];
$cas[] = ['jeton falsifié refusé',           verifierJeton((time() - 10) . '.' . str_repeat('0', 64), null, 0), 'invalide'];
$cas[] = ['jeton de plus de 2 h refusé',     verifierJeton($jeton, time() + 7201, 0), 'invalide'];
$cas[] = ['jeton vide refusé',               verifierJeton('', null, 0), 'invalide'];

foreach ($cas as [$nom, $obtenu, $attendu]) {
    if (!verifier($nom, $obtenu, $attendu)) {
        $echecs++;
    }
}

echo PHP_EOL . ($echecs === 0 ? 'Tout passe.' : $echecs . ' échec(s).') . PHP_EOL;
exit($echecs === 0 ? 0 : 1);
