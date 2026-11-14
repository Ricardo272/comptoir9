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
// Identifiants lus en base : ils changent quand le seed est rejoué
$deuxIds = array_map('intval', db()->query('SELECT id FROM produits ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN));
$enBase = produitsParId([...$deuxIds, 999999]);
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

// ===========================================================================
// Commande (phase 5b). Ces contrôles écrivent en base : tout est défait à la fin.
// ===========================================================================
require __DIR__ . '/../includes/commande.php';

$bonnes = ['prenom' => ' Camille ', 'nom' => 'Martin', 'email' => 'Camille.Martin@exemple.test',
           'telephone' => '+33 6 12 34 56 78', 'adresse' => '3 rue des Essais', 'complement' => '',
           'code_postal' => '69001', 'ville' => 'Lyon'];
[$coordonnees, $erreurs] = verifierCoordonnees($bonnes);
$erreursDe = fn (array $modifs) => array_keys(verifierCoordonnees($modifs + $bonnes)[1]);

$cas[] = ['coordonnées valides',            $erreurs, []];
$cas[] = ['prénom nettoyé',                 $coordonnees['prenom'], 'Camille'];
$cas[] = ['téléphone normalisé',            $coordonnees['telephone'], '0612345678'];
$cas[] = ['téléphone facultatif',           $erreursDe(['telephone' => '']), []];
$cas[] = ['téléphone invalide',             $erreursDe(['telephone' => '12345']), ['telephone']];
$cas[] = ['e-mail invalide',                $erreursDe(['email' => 'camille@']), ['email']];
$cas[] = ['code postal invalide',           $erreursDe(['code_postal' => '6900A']), ['code_postal']];
$cas[] = ['tout vide : 6 champs en erreur', count(verifierCoordonnees([])[1]), 6];
$cas[] = ['tableau à la place d\'un texte', $erreursDe(['nom' => ['x']]), ['nom']];
$cas[] = ['UTF-8 invalide refusé',          $erreursDe(['ville' => "Ly\xFFon\xFE"]), ['ville']];
$cas[] = ['prénom trop long',               $erreursDe(['prenom' => str_repeat('a', 61)]), ['prenom']];

$numeros = array_map(fn () => numeroCommande(), range(1, 300));
$cas[] = ['numéros au bon format',  count(preg_grep('/^C9-[2-9A-HJ-NP-Z]{6}$/', $numeros)), 300];
$cas[] = ['300 numéros différents', count(array_unique($numeros)), 300];

// --- Enregistrement, sur de vrais produits du seed ------------------------------
$id    = fn (string $slug) => (int) db()->query("SELECT id FROM produits WHERE slug = '$slug'")->fetchColumn();
$stock = fn (int $produit) => (int) db()->query("SELECT stock FROM produits WHERE id = $produit")->fetchColumn();
$cable = $id('cable-usb-c-tresse-2m');   // Octet & Cie, 12,90 €, stock 60
$jeu   = $id('jeu-cordee');              // Dé à Six, 36,00 €, stock 4
$velo  = $id('velo-pliant-braquet');     // Braquet, 529,00 €, stock 1 : le dernier exemplaire
$stocksAvant = [$cable => $stock($cable), $jeu => $stock($jeu), $velo => $stock($velo)];
$commandesAvant = (int) db()->query('SELECT COUNT(*) FROM commandes')->fetchColumn();
$numerosCrees = [];

try {
    $quantites = [$cable => 2, $jeu => 1];
    $attendu = 2 * 1290 + 490 + 3600 + 490;   // deux boutiques sous le seuil : deux forfaits

    // Le total vu par le client ne correspond pas : rien n'est enregistré
    $refus = enregistrerCommande($coordonnees, $quantites, $attendu - 1, $port);
    $cas[] = ['total différent : commande refusée', isset($refus['numero']), false];
    $cas[] = ['total différent : stock intact',     $stock($cable), $stocksAvant[$cable]];

    $resultat = enregistrerCommande($coordonnees, $quantites, $attendu, $port);
    $numerosCrees[] = $resultat['numero'] ?? '';
    $commande = commande($resultat['numero'] ?? '');
    $lignes = array_merge(...array_values(array_column($commande['colis'], 'lignes')));

    $cas[] = ['commande enregistrée',          isset($resultat['numero']), true];
    $cas[] = ['total enregistré',              [$commande['total_produits'], $commande['total_port'], $commande['total']], [6180, 980, $attendu]];
    $cas[] = ['un colis par boutique',         array_column($commande['colis'], 'boutique'), ['Octet & Cie', 'Dé à Six']];
    $cas[] = ['colis en préparation',          etatCommande($commande), 'En préparation'];
    $cas[] = ['lignes : prix et quantités',    array_map(fn ($l) => [$l['prix'], $l['quantite']], $lignes), [[1290, 2], [3600, 1]]];
    $cas[] = ['stock décompté',                [$stock($cable), $stock($jeu)], [$stocksAvant[$cable] - 2, $stocksAvant[$jeu] - 1]];
    $cas[] = ['e-mail gardé tel quel',         $commande['email'], 'Camille.Martin@exemple.test'];

    // Le prix copié ne bouge plus quand le produit change
    db()->exec("UPDATE produits SET prix = 9999, nom = 'Autre nom' WHERE id = $cable");
    $apres = commande($resultat['numero']);
    $premiereLigne = reset($apres['colis'])['lignes'][0];
    $cas[] = ['prix et nom copiés à l\'achat', [$premiereLigne['prix'], $premiereLigne['nom']], [1290, 'Câble USB-C tressé 2 m']];
    db()->exec("UPDATE produits SET prix = 1290, nom = 'Câble USB-C tressé 2 m' WHERE id = $cable");

    // Suivi : numéro + e-mail
    $cas[] = ['suivi : bon e-mail, autre casse', commande($resultat['numero'], 'camille.martin@EXEMPLE.test') !== null, true];
    $cas[] = ['suivi : mauvais e-mail',          commande($resultat['numero'], 'autre@exemple.test'), null];
    $cas[] = ['suivi : numéro inconnu',          commande('C9-ZZZZZZ', 'camille.martin@exemple.test'), null];

    // Le dernier exemplaire : la première commande passe, la seconde non
    $premiere = enregistrerCommande($coordonnees, [$velo => 1], 52900, $port); // 529 € : port offert
    $numerosCrees[] = $premiere['numero'] ?? '';
    $seconde = enregistrerCommande($coordonnees, [$velo => 1], 52900, $port);
    $cas[] = ['dernier exemplaire : 1re commande passe',  isset($premiere['numero']), true];
    $cas[] = ['dernier exemplaire : 2e commande refusée', isset($seconde['numero']), false];
    $cas[] = ['2e commande : panier vidé, avec un avis',  [$seconde['panier']['quantites'], count($seconde['panier']['avis'])], [[], 1]];
    $cas[] = ['stock à 0, jamais négatif',                $stock($velo), 0];

    // Quantité supérieure au stock : refusée, rien n'est écrit
    $trop = enregistrerCommande($coordonnees, [$jeu => 15], 15 * 3600, $port);
    $cas[] = ['quantité > stock : commande refusée', isset($trop['numero']), false];
} finally {
    // On défait tout : commandes du test (colis et lignes partent en cascade) et stocks
    foreach (array_filter($numerosCrees) as $numero) {
        db()->prepare('DELETE FROM commandes WHERE numero = ?')->execute([$numero]);
    }
    foreach ($stocksAvant as $produit => $valeur) {
        db()->exec("UPDATE produits SET stock = $valeur WHERE id = $produit");
    }
    db()->exec("UPDATE produits SET prix = 1290, nom = 'Câble USB-C tressé 2 m' WHERE id = $cable");
}
$cas[] = ['base remise en état : commandes', (int) db()->query('SELECT COUNT(*) FROM commandes')->fetchColumn(), $commandesAvant];
$cas[] = ['base remise en état : stocks',    [$stock($cable), $stock($jeu), $stock($velo)], array_values($stocksAvant)];

foreach ($cas as [$nom, $obtenu, $attendu]) {
    if (!verifier($nom, $obtenu, $attendu)) {
        $echecs++;
    }
}

echo PHP_EOL . ($echecs === 0 ? 'Tout passe.' : $echecs . ' échec(s).') . PHP_EOL;
exit($echecs === 0 ? 0 : 1);
