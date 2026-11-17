<?php
/**
 * Tests de sécurité du site, vu de l'extérieur (requêtes HTTP), repris de Sharp Line.
 * Interroge l'adresse de config.local.php : à lancer en local, puis sur le serveur.
 *
 *   php tests/securite.php
 *
 * Code de sortie 1 si un test échoue. Les messages de contact créés par le
 * test sont supprimés à la fin ; les contrôles de la limite horaire et de la
 * suppression après 30 jours se font dans une transaction annulée.
 */

if (PHP_SAPI !== 'cli') {
    exit('À lancer en ligne de commande.');
}

require_once __DIR__ . '/../includes/contact.php';

$base = rtrim(configLocale()['url_absolue'], '/');
$echecs = 0;

function verifier(string $nom, bool $condition): bool
{
    global $echecs;
    echo ($condition ? '  ok      ' : '  ÉCHEC   ') . $nom . "\n";
    $echecs += $condition ? 0 : 1;
    return $condition;
}

/**
 * Requête HTTP sans suivre les redirections.
 * Retourne ['code' => 200, 'entetes' => ['content-type' => '…'], 'corps' => '…'].
 */
function requete(string $adresse, ?array $post = null, string $enTetes = ''): array
{
    $options = ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10, 'header' => $enTetes];
    if ($post !== null) {
        $options['method']  = 'POST';
        $options['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $options['content'] = http_build_query($post);
    }
    $corps = @file_get_contents($adresse, false, stream_context_create(['http' => $options]));
    $lignes = $http_response_header ?? [];

    preg_match('{HTTP/\S+ (\d{3})}', $lignes[0] ?? '', $m);
    $entetes = [];
    foreach (array_slice($lignes, 1) as $ligne) {
        [$nom, $valeur] = array_map('trim', explode(':', $ligne, 2) + [1 => '']);
        $entetes[strtolower($nom)] = $valeur;
    }
    return ['code' => (int) ($m[1] ?? 0), 'entetes' => $entetes, 'corps' => (string) $corps];
}

$compter = fn (string $table, string $condition = '1') => (int) db()->query("SELECT COUNT(*) FROM $table WHERE $condition")->fetchColumn();

echo "Site testé : $base\n";

// Le contrôle doit savoir échouer : une page absente ne répond pas 200.
ob_start();
$faux = verifier('cas faux', requete($base . '/nexiste-pas')['code'] === 200);
ob_end_clean();
$echecs = 0;
if ($faux) {
    echo "  ÉCHEC   le contrôle accepte un résultat faux\n";
    exit(1);
}
echo "  ok      le contrôle refuse un cas volontairement faux\n";

echo "Pages publiques : réponse 200, aucun cookie, aucune erreur PHP affichée\n";
foreach (['/', '/catalogue.php', '/catalogue.php?rayon=cuisine', '/produit.php?produit=jeu-cordee', '/boutiques.php',
          '/boutique.php?boutique=braquet', '/panier.php', '/suivi.php', '/a-propos.php', '/aide.php', '/contact.php',
          '/mentions-legales.php', '/cgv.php'] as $page) {
    $r = requete($base . $page);
    verifier("$page", $r['code'] === 200 && !isset($r['entetes']['set-cookie'])
        && !preg_match('/Warning|Notice|Deprecated|Fatal error/', $r['corps']));
}

echo "En-têtes de sécurité\n";
$accueil = requete($base . '/');
verifier('CSP présente', str_contains($accueil['entetes']['content-security-policy'] ?? '', "default-src 'self'"));
verifier('nosniff', ($accueil['entetes']['x-content-type-options'] ?? '') === 'nosniff');
verifier('X-Frame-Options', isset($accueil['entetes']['x-frame-options']));
verifier('Referrer-Policy', isset($accueil['entetes']['referrer-policy']));

echo "Dossiers internes : 404\n";
foreach (['/config/config.local.php', '/config/site.php', '/includes/db.php', '/sql/schema.sql', '/tests/securite.php', '/outils/'] as $chemin) {
    $r = requete($base . $chemin);
    verifier("$chemin : 404", $r['code'] === 404 && !str_contains($r['corps'], 'mot_de_passe'));
}
verifier('adresse inconnue : 404 (pas 200)', requete($base . '/nexiste-pas')['code'] === 404);
verifier('pas de liste de fichiers dans /assets/', requete($base . '/assets/')['code'] !== 200);
verifier('produit inconnu : 404', requete($base . '/produit.php?produit=inconnu')['code'] === 404);
verifier('rayon inconnu : 404', requete($base . '/catalogue.php?rayon=inconnu')['code'] === 404);

echo "Référencement : robots.txt, plan du site, données structurées\n";
$robots = requete($base . '/robots.txt');
verifier('robots.txt : panier et commande exclus, plan du site indiqué', $robots['code'] === 200
    && str_contains($robots['corps'], 'Disallow: /panier.php') && str_contains($robots['corps'], "Sitemap: $base/sitemap.xml"));
$plan = requete($base . '/sitemap.xml');
preg_match_all('{<loc>([^<]+)</loc>}', $plan['corps'], $m);
$adresses = array_map('html_entity_decode', $m[1]);
$produitsEnVente = $compter("produits p JOIN boutiques b ON b.id = p.boutique_id", "p.etat = 'en_vente' AND b.etat = 'active'");
verifier('sitemap.xml : XML valide, toutes les fiches produit', @simplexml_load_string($plan['corps']) !== false
    && count(preg_grep('/produit\.php/', $adresses)) === $produitsEnVente);

// Chaque adresse du plan : 200, aucune erreur PHP, données structurées lisibles
$defauts = [];
$fichesProduit = 0;
foreach ($adresses as $adresse) {
    $r = requete($adresse);
    if ($r['code'] !== 200 || preg_match('/Warning|Notice|Deprecated|Fatal error/', $r['corps'])) {
        $defauts[] = "$adresse (code {$r['code']})";
        continue;
    }
    preg_match_all('{<script type="application/ld\+json">(.*?)</script>}s', $r['corps'], $blocs);
    foreach ($blocs[1] as $bloc) {
        $fiche = json_decode($bloc, true);
        if (!is_array($fiche) || ($fiche['@context'] ?? '') !== 'https://schema.org') {
            $defauts[] = "$adresse (JSON-LD illisible)";
        } elseif ($fiche['@type'] === 'Product') {
            // Le prix annoncé à Google doit être celui de la base, avec un point décimal
            $prix = db()->prepare('SELECT prix FROM produits WHERE slug = ?');
            $prix->execute([$fiche['sku']]);
            if ($fiche['offers']['price'] !== number_format($prix->fetchColumn() / 100, 2, '.', '')
                || $fiche['offers']['priceCurrency'] !== 'EUR') {
                $defauts[] = "$adresse (prix de la fiche)";
            }
            $fichesProduit++;
        }
    }
}
verifier(count($adresses) . ' adresses du plan : 200, sans erreur, fiches lisibles' . ($defauts ? ' — ' . implode(', ', array_slice($defauts, 0, 3)) : ''), !$defauts);
verifier("fiche Product avec le bon prix sur les $produitsEnVente produits", $fichesProduit === $produitsEnVente);
$accueilFiche = str_contains($accueil['corps'], '"@type":"Organization"');
$partage = requete($base . '/assets/img/partage.jpg');
verifier('accueil : fiche Organization et image de partage en ligne', $accueilFiche
    && str_contains($accueil['corps'], 'property="og:image"') && $partage['code'] === 200);

$illustrations = db()->query('SELECT slug FROM rayons WHERE parent_id IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
$manquantes = array_filter($illustrations, function ($slug) use ($base) {
    $r = requete("$base/assets/img/rayons/$slug.svg");
    return $r['code'] !== 200 || !str_contains($r['entetes']['content-type'] ?? '', 'image/svg+xml');
});
verifier(count($illustrations) . ' illustrations de sous-rayon servies en SVG' . ($manquantes ? ' — manque : ' . implode(', ', $manquantes) : ''), !$manquantes);
verifier('fiche produit : image dans les données structurées, sans image de remplacement',
    str_contains($fichePage = requete($base . '/produit.php?produit=jeu-cordee')['corps'], '"image":"' . $base . '/assets/img/rayons/jeux-de-societe.svg"')
    && !str_contains($fichePage, 'image-remplacement'));

echo "Catalogue : saisies piégées\n";
$xss = requete($base . '/catalogue.php?q=' . rawurlencode('<script>alert(1)</script>'));
verifier('balise script dans la recherche : échappée', $xss['code'] === 200 && !str_contains($xss['corps'], '<script>alert(1)</script>'));
verifier('injection SQL dans la recherche : 0 produit, pas d\'erreur',
    str_contains(requete($base . '/catalogue.php?q=' . rawurlencode("' OR 1=1 -- "))['corps'], '<strong>0</strong> produit'));
verifier('tableau à la place d\'un texte : pas d\'erreur', requete($base . '/catalogue.php?q[]=x&rayon[]=y')['code'] === 200);

echo "Panier et commande\n";
$ajout = ['action' => 'ajouter', 'produit' => 'jeu-cordee', 'quantite' => '1'];
$r = requete($base . '/panier.php', $ajout);
verifier('ajout sans jeton : refusé (400), aucun cookie', $r['code'] === 400 && !isset($r['entetes']['set-cookie']));
$r = requete($base . '/panier.php', $ajout + ['jeton' => creerJeton()], "Origin: http://autre-site.test\r\n");
verifier('ajout depuis un autre site : refusé (400), aucun cookie', $r['code'] === 400 && !isset($r['entetes']['set-cookie']));
$r = requete($base . '/panier.php', $ajout + ['jeton' => creerJeton()]);
$cookie = $r['entetes']['set-cookie'] ?? '';
verifier('ajout normal : accepté, cookie HttpOnly et SameSite=Lax',
    $r['code'] === 303 && stripos($cookie, 'httponly') !== false && stripos($cookie, 'samesite=lax') !== false);
$session = 'Cookie: ' . explode(';', $cookie)[0] . "\r\n";
verifier('modifier le panier sans jeton de session : refusé (400)',
    requete($base . '/panier.php', ['action' => 'retirer', 'id' => '1'], $session)['code'] === 400);
$avant = $compter('commandes');
$r = requete($base . '/commande.php', ['prenom' => 'Test', 'nom' => 'Securite', 'email' => 'test@exemple.test',
    'adresse' => '1 rue du Test', 'code_postal' => '69001', 'ville' => 'Lyon', 'total_vu' => '4090'], $session);
verifier('commander sans jeton de session : refusé, rien en base', $r['code'] === 422 && $compter('commandes') === $avant);
verifier('commande.php sans panier : renvoie au panier', str_ends_with(requete($base . '/commande.php')['entetes']['location'] ?? '', '/panier.php'));
verifier('confirmation sans session : renvoie au panier', str_ends_with(requete($base . '/commande-confirmee.php')['entetes']['location'] ?? '', '/panier.php'));
$r = requete($base . '/suivi.php', ['numero' => 'C9-ZZZZZZ', 'email' => 'test@exemple.test']);
verifier('suivi d\'une commande inconnue : refusé, sans détail', $r['code'] === 422 && str_contains($r['corps'], 'Aucune commande ne correspond'));

echo "Formulaire de contact\n";
$marque = 'test-securite-' . bin2hex(random_bytes(4));   // repère pour retrouver et supprimer nos messages
$message = ['nom' => 'Test Sécurité', 'email' => 'test@exemple.test', 'sujet' => $marque,
            'message' => 'Message écrit par tests/securite.php, à supprimer.'];
// Jeton comme si le formulaire avait été affiché il y a 10 secondes
$jetonAncien = fn (int $age = 10) => (time() - $age) . '.' . signer('formulaire', (string) (time() - $age));
$nos = fn () => $compter('messages', "sujet = '$marque'");

try {
    $r = requete($base . '/contact.php', $message + ['jeton' => $jetonAncien(), 'site_web' => 'http://spam.test']);
    verifier('champ piège rempli : réponse normale, rien en base', $r['code'] === 303 && $nos() === 0);
    $r = requete($base . '/contact.php', $message + ['jeton' => creerJeton()]);
    verifier('envoyé en moins de 3 secondes : réponse normale, rien en base', $r['code'] === 303 && $nos() === 0);
    $r = requete($base . '/contact.php', $message + ['jeton' => (time() - 10) . '.' . str_repeat('0', 64)]);
    verifier('jeton falsifié : refusé (422), rien en base', $r['code'] === 422 && $nos() === 0);
    $r = requete($base . '/contact.php', $message + ['jeton' => $jetonAncien(7300)]);
    verifier('jeton de plus de 2 heures : refusé (422), rien en base', $r['code'] === 422 && $nos() === 0);
    $r = requete($base . '/contact.php', ['nom' => '', 'email' => 'pas-un-email', 'sujet' => $marque, 'message' => 'court', 'jeton' => $jetonAncien()]);
    verifier('champs invalides : refusé (422), 3 champs signalés',
        $r['code'] === 422 && substr_count($r['corps'], 'aria-invalid="true"') === 3 && $nos() === 0);

    $piege = '<script>alert(1)</script>';
    $r = requete($base . '/contact.php', ['message' => "Bonjour $piege, ceci est un test."] + $message + ['jeton' => $jetonAncien()]);
    verifier('message valide : enregistré, aucun cookie', $r['code'] === 303 && !isset($r['entetes']['set-cookie']) && $nos() === 1);
    $r = requete($base . '/contact.php', ['nom' => $piege, 'email' => 'x', 'sujet' => $marque, 'message' => $piege, 'jeton' => $jetonAncien()]);
    verifier('balise script réaffichée dans le formulaire : échappée', $r['code'] === 422 && !str_contains($r['corps'], $piege));

    // Limite horaire et suppression après 30 jours : dans une transaction annulée
    db()->beginTransaction();
    $inserer = db()->prepare('INSERT INTO messages (nom, email, sujet, message, cree_le) VALUES (?, ?, ?, ?, NOW() - INTERVAL ? DAY)');
    for ($i = $compter('messages', 'cree_le > NOW() - INTERVAL 1 HOUR'); $i < MESSAGES_PAR_HEURE; $i++) {
        $inserer->execute(['Remplissage', 'r@exemple.test', $marque, 'Message de remplissage.', 0]);
    }
    verifier('limite horaire atteinte : message refusé', enregistrerMessage(['nom' => 'Un de trop'] + $message) === false);
    db()->rollBack();

    db()->beginTransaction();
    $inserer->execute(['Vieux', 'v@exemple.test', $marque, 'Message de 31 jours.', 31]);
    $inserer->execute(['Récent', 'r@exemple.test', $marque, 'Message de 29 jours.', 29]);
    $commande = db()->prepare("INSERT INTO commandes (numero, prenom, nom, email, adresse, code_postal, ville, total_produits, total_port, total, cree_le)
                               VALUES (?, 'Test', 'Securite', 'test@exemple.test', '1 rue du Test', '69001', 'Lyon', 1000, 490, 1490, NOW() - INTERVAL ? DAY)");
    $commande->execute(['C9-VVVVVV', 31]);
    $commande->execute(['C9-RRRRRR', 29]);
    db()->exec("INSERT INTO colis (commande_id, boutique_id, sous_total, port)
                SELECT id, (SELECT MIN(id) FROM boutiques), 1000, 490 FROM commandes WHERE numero = 'C9-VVVVVV'");
    purgerAnciennesDonnees();
    verifier('31 jours : message supprimé ; 29 jours : gardé',
        $compter('messages', "nom = 'Vieux'") === 0 && $compter('messages', "nom = 'Récent'") === 1);
    verifier('31 jours : commande et son colis supprimés ; 29 jours : gardée',
        $compter('commandes', "numero = 'C9-VVVVVV'") === 0 && $compter('commandes', "numero = 'C9-RRRRRR'") === 1
        && $compter('colis c JOIN commandes o ON o.id = c.commande_id', "o.numero = 'C9-VVVVVV'") === 0);
    db()->rollBack();
} finally {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    db()->prepare('DELETE FROM messages WHERE sujet = ?')->execute([$marque]);
}
verifier('messages du test supprimés', $nos() === 0);

echo "Espace de gestion\n";
foreach (['/admin/', '/admin/commandes.php', '/admin/messages.php'] as $page) {
    verifier("$page sans connexion : renvoie vers la connexion",
        str_ends_with(requete($base . $page)['entetes']['location'] ?? '', '/admin/connexion.php'));
}

/** Ouvre la page de connexion : retourne [en-tête Cookie, jeton CSRF, Set-Cookie brut]. */
function pageConnexion(string $base): array
{
    $r = requete($base . '/admin/connexion.php');
    preg_match('/name="csrf" value="([0-9a-f]+)"/', $r['corps'], $m);
    $setCookie = $r['entetes']['set-cookie'] ?? '';
    return ['Cookie: ' . explode(';', $setCookie)[0] . "\r\n", $m[1] ?? '', $setCookie];
}

/** Se connecte : retourne l'en-tête Cookie de la session connectée, ou '' si refusé. */
function seConnecter(string $base, string $identifiant, string $motDePasse): array
{
    [$cookie, $csrf] = pageConnexion($base);
    $r = requete($base . '/admin/connexion.php', ['csrf' => $csrf, 'identifiant' => $identifiant, 'mot_de_passe' => $motDePasse], $cookie);
    $nouveau = isset($r['entetes']['set-cookie']) ? 'Cookie: ' . explode(';', $r['entetes']['set-cookie'])[0] . "\r\n" : '';
    return [$r, $nouveau, $cookie];
}

/** Le jeton CSRF d'une page de la session connectée. */
function csrfDe(string $adresse, string $cookie): string
{
    preg_match('/name="csrf" value="([0-9a-f]+)"/', requete($adresse, null, $cookie)['corps'], $m);
    return $m[1] ?? '';
}

[$cookie, $csrf, $setCookie] = pageConnexion($base);
verifier('cookie limité à /admin/, HttpOnly, SameSite=Strict',
    stripos($setCookie, 'path=/admin/') !== false && stripos($setCookie, 'httponly') !== false
    && stripos($setCookie, 'samesite=strict') !== false);
verifier('connexion sans jeton CSRF : refusée (400)',
    requete($base . '/admin/connexion.php', ['identifiant' => 'x', 'mot_de_passe' => 'y'], $cookie)['code'] === 400);

// Comptes jetables, mots de passe aléatoires jamais affichés
$mdpAdmin   = bin2hex(random_bytes(12));
$mdpVendeur = bin2hex(random_bytes(12));
$creer = db()->prepare('INSERT INTO comptes (identifiant, mot_de_passe, role, boutique_id) VALUES (?, ?, ?, ?)');
db()->exec("DELETE FROM comptes WHERE identifiant IN ('test-securite-admin', 'test-securite-vendeur')");
$creer->execute(['test-securite-admin', password_hash($mdpAdmin, PASSWORD_DEFAULT), 'admin', null]);
$creer->execute(['test-securite-vendeur', password_hash($mdpVendeur, PASSWORD_DEFAULT), 'vendeur',
    (int) db()->query("SELECT id FROM boutiques WHERE slug = 'braquet'")->fetchColumn()]);
$idMessage = null;

try {
    // --- Admin -----------------------------------------------------------------
    [$connexion, $admin, $avant] = seConnecter($base, 'test-securite-admin', $mdpAdmin);
    verifier('bonne connexion : redirection vers l\'espace', str_ends_with($connexion['entetes']['location'] ?? '', '/admin/'));
    verifier('nouvel identifiant de session après connexion', $admin !== '' && $admin !== $avant);
    verifier('admin : arrive sur les commandes', str_ends_with(requete($base . '/admin/', null, $admin)['entetes']['location'] ?? '', '/admin/commandes.php'));
    $pageCommandes = requete($base . '/admin/commandes.php', null, $admin);
    verifier('admin : commandes et messages accessibles, sans erreur PHP',
        $pageCommandes['code'] === 200 && requete($base . '/admin/messages.php', null, $admin)['code'] === 200
        && !preg_match('/Warning|Notice|Deprecated|Fatal error/', $pageCommandes['corps']));
    verifier('commande inconnue : 404', requete($base . '/admin/commandes.php?numero=C9-ZZZZZZ', null, $admin)['code'] === 404);
    verifier('numéro piégé : 404, sans erreur', requete($base . '/admin/commandes.php?numero=' . rawurlencode("' OR 1=1"), null, $admin)['code'] === 404);

    db()->exec("INSERT INTO messages (nom, email, sujet, message) VALUES ('Test', 'test@exemple.test', 'test-securite-admin', '<script>alert(1)</script>')");
    $idMessage = (int) db()->lastInsertId();
    $pageMessages = requete($base . '/admin/messages.php', null, $admin)['corps'];
    verifier('message affiché échappé dans l\'administration', str_contains($pageMessages, 'test-securite-admin')
        && !str_contains($pageMessages, '<script>alert(1)</script>'));
    verifier('marquer un message sans jeton CSRF : refusé (400)',
        requete($base . '/admin/messages.php', ['id' => $idMessage, 'traite' => '1'], $admin)['code'] === 400);
    $r = requete($base . '/admin/messages.php', ['csrf' => csrfDe($base . '/admin/messages.php', $admin), 'id' => $idMessage, 'traite' => '1'], $admin);
    verifier('marquer un message avec le jeton : enregistré',
        $r['code'] === 303 && (int) db()->query("SELECT traite FROM messages WHERE id = $idMessage")->fetchColumn() === 1);

    $csrfAdmin = csrfDe($base . '/admin/commandes.php', $admin);
    verifier('déconnexion sans jeton CSRF : refusée (400)', requete($base . '/admin/deconnexion.php', [], $admin)['code'] === 400);
    requete($base . '/admin/deconnexion.php', ['csrf' => $csrfAdmin], $admin);
    verifier('après déconnexion : l\'ancienne session ne vaut plus rien',
        str_ends_with(requete($base . '/admin/commandes.php', null, $admin)['entetes']['location'] ?? '', '/admin/connexion.php'));

    // --- Vendeur ---------------------------------------------------------------
    [, $vendeur] = seConnecter($base, 'test-securite-vendeur', $mdpVendeur);
    verifier('vendeur : arrive sur ses colis', str_ends_with(requete($base . '/admin/', null, $vendeur)['entetes']['location'] ?? '', '/admin/colis.php'));
    foreach (['/admin/commandes.php', '/admin/messages.php'] as $page) {
        $r = requete($base . $page, null, $vendeur);
        verifier("vendeur : $page refusée (403)", $r['code'] === 403 && !str_contains($r['corps'], 'test@exemple.test'));
    }
    verifier('vendeur : marquer un message refusé (403)',
        requete($base . '/admin/messages.php', ['csrf' => csrfDe($base . '/admin/commandes.php', $vendeur), 'id' => $idMessage, 'traite' => '0'], $vendeur)['code'] === 403
        && (int) db()->query("SELECT traite FROM messages WHERE id = $idMessage")->fetchColumn() === 1);
    db()->exec("DELETE FROM comptes WHERE identifiant = 'test-securite-vendeur'");
    verifier('compte supprimé : sa session tombe aussitôt',
        str_ends_with(requete($base . '/admin/commandes.php', null, $vendeur)['entetes']['location'] ?? '', '/admin/connexion.php'));

    // --- Mots de passe ---------------------------------------------------------
    $corps = '';
    for ($i = 1; $i <= 5; $i++) {
        $corps = seConnecter($base, 'test-securite-admin', 'faux-' . $i)[0]['corps'];
    }
    verifier('5 échecs : compte bloqué', str_contains($corps, 'Trop de tentatives'));
    verifier('bloqué : même le bon mot de passe est refusé',
        str_contains(seConnecter($base, 'test-securite-admin', $mdpAdmin)[0]['corps'], 'Trop de tentatives'));
    verifier('identifiant inconnu : même message qu\'un mauvais mot de passe',
        str_contains(seConnecter($base, 'inconnu-' . bin2hex(random_bytes(4)), 'x')[0]['corps'], 'Identifiant ou mot de passe incorrect'));
    verifier('mots de passe enregistrés sous forme d\'empreinte',
        !(int) db()->query("SELECT COUNT(*) FROM comptes WHERE mot_de_passe NOT LIKE '$2y$%'")->fetchColumn());
} finally {
    db()->exec("DELETE FROM comptes WHERE identifiant IN ('test-securite-admin', 'test-securite-vendeur')");
    if ($idMessage) {
        db()->exec("DELETE FROM messages WHERE id = $idMessage");
    }
}
verifier('comptes et message du test supprimés',
    !(int) db()->query("SELECT COUNT(*) FROM comptes WHERE identifiant LIKE 'test-securite-%'")->fetchColumn());
echo "Espace vendeur : chaque boutique ne voit et ne modifie que ce qui est à elle\n";
require_once __DIR__ . '/../includes/commande.php';

$idBoutique = fn (string $slug) => (int) db()->query("SELECT id FROM boutiques WHERE slug = '$slug'")->fetchColumn();
$idProduit  = fn (string $slug) => (int) db()->query("SELECT id FROM produits WHERE slug = '$slug'")->fetchColumn();
$valeur     = fn (string $sql) => db()->query($sql)->fetchColumn();
$kit   = $idProduit('kit-de-reparation-crevaison');   // Braquet
$cable = $idProduit('cable-usb-c-tresse-2m');          // Octet & Cie
$jeu   = $idProduit('jeu-cordee');                     // Dé à Six
$kitAvant = db()->query("SELECT * FROM produits WHERE id = $kit")->fetch();
$stocksAvant = db()->query("SELECT id, stock FROM produits WHERE id IN ($kit, $cable, $jeu)")->fetchAll(PDO::FETCH_KEY_PAIR);

$mdpBraquet = bin2hex(random_bytes(12));
$mdpDeASix  = bin2hex(random_bytes(12));
$mdpAdmin2  = bin2hex(random_bytes(12));
db()->exec("DELETE FROM comptes WHERE identifiant LIKE 'test-vendeur-%'");
$creer->execute(['test-vendeur-braquet', password_hash($mdpBraquet, PASSWORD_DEFAULT), 'vendeur', $idBoutique('braquet')]);
$creer->execute(['test-vendeur-deasix', password_hash($mdpDeASix, PASSWORD_DEFAULT), 'vendeur', $idBoutique('de-a-six')]);
$creer->execute(['test-vendeur-admin', password_hash($mdpAdmin2, PASSWORD_DEFAULT), 'admin', null]);
$numero = null;
$nouveau = null;

try {
    // Une commande chez trois boutiques : un colis chacune
    $quantites = [$kit => 1, $cable => 1, $jeu => 1];
    $total = calculerPanier(produitsParId(array_keys($quantites)), $quantites, $site['frais_port'])['total'];
    $numero = enregistrerCommande(['prenom' => 'Camille', 'nom' => 'Test', 'email' => 'camille@exemple.test', 'telephone' => '',
        'adresse' => '3 rue des Essais', 'complement' => '', 'code_postal' => '69001', 'ville' => 'Lyon'],
        $quantites, $total, $site['frais_port'])['numero'] ?? null;
    $colisDe = fn (string $boutique) => (int) $valeur("SELECT c.id FROM colis c JOIN commandes o ON o.id = c.commande_id
        WHERE o.numero = '$numero' AND c.boutique_id = " . $idBoutique($boutique));
    $etatColis = fn (int $id) => $valeur("SELECT etat FROM colis WHERE id = $id");

    // --- Colis -----------------------------------------------------------------
    [, $braquet] = seConnecter($base, 'test-vendeur-braquet', $mdpBraquet);
    $page = requete("$base/admin/colis.php", null, $braquet)['corps'];
    verifier('vendeur : voit son colis, pas ceux des autres boutiques', $numero !== null
        && str_contains($page, 'Kit de réparation crevaison') && !str_contains($page, 'Câble USB-C') && !str_contains($page, 'Cordée'));
    verifier('vendeur : voit l\'adresse de livraison de son colis', str_contains($page, '3 rue des Essais'));
    $csrfBraquet = csrfDe("$base/admin/colis.php", $braquet);
    verifier('expédier sans jeton CSRF : refusé (400)',
        requete("$base/admin/colis.php", ['colis' => $colisDe('braquet')], $braquet)['code'] === 400);
    requete("$base/admin/colis.php", ['csrf' => $csrfBraquet, 'colis' => $colisDe('octet-et-cie')], $braquet);
    verifier('expédier le colis d\'une autre boutique : refusé, rien ne change', $etatColis($colisDe('octet-et-cie')) === 'en_preparation');
    requete("$base/admin/colis.php", ['csrf' => $csrfBraquet, 'colis' => $colisDe('braquet')], $braquet);
    verifier('expédier son colis : enregistré', $etatColis($colisDe('braquet')) === 'expedie');
    verifier('le client voit « Partiellement expédiée » dans son suivi', str_contains(
        requete("$base/suivi.php", ['numero' => $numero, 'email' => 'camille@exemple.test'])['corps'], 'Partiellement expédiée'));
    [, $deASix] = seConnecter($base, 'test-vendeur-deasix', $mdpDeASix);
    $page = requete("$base/admin/colis.php", null, $deASix)['corps'];
    verifier('l\'autre vendeur ne voit que son propre colis', str_contains($page, 'Cordée') && !str_contains($page, 'Kit de réparation'));

    // --- Produits --------------------------------------------------------------
    $page = requete("$base/admin/produits.php", null, $braquet)['corps'];
    verifier('Mes produits : les 14 de Braquet, aucun d\'une autre boutique',
        substr_count($page, '<li class="carte-admin') === 14 && !str_contains($page, 'Cordée'));
    verifier('ouvrir le produit d\'une autre boutique : 404', requete("$base/admin/produit.php?id=$jeu", null, $braquet)['code'] === 404);

    $champs = ['nom' => 'Kit modifié par le test', 'rayon_id' => (string) $valeur("SELECT id FROM rayons WHERE slug = 'entretien-pieces'"),
               'description' => 'Description modifiée par le test.', 'caracteristiques' => "Ligne 1\nLigne 2",
               'prix' => '9,90', 'prix_barre' => '', 'stock' => '42', 'delai_jours' => '3'];
    requete("$base/admin/produit.php?id=$jeu", ['csrf' => $csrfBraquet] + $champs, $braquet);
    verifier('modifier le produit d\'une autre boutique : refusé, rien ne change',
        $valeur("SELECT nom FROM produits WHERE id = $jeu") === 'Cordée, jeu coopératif');
    $r = requete("$base/admin/produit.php?id=$kit", ['csrf' => $csrfBraquet] + $champs, $braquet);
    verifier('modifier son produit : enregistré (nom, prix en centimes, stock)', $r['code'] === 303
        && $valeur("SELECT CONCAT(nom, '|', prix, '|', stock) FROM produits WHERE id = $kit") === 'Kit modifié par le test|990|42');
    $r = requete("$base/admin/produit.php?id=$kit", ['csrf' => $csrfBraquet, 'prix_barre' => '5,00'] + $champs, $braquet);
    verifier('ancien prix plus bas que le prix : refusé (422), rien n\'est écrit',
        $r['code'] === 422 && $valeur("SELECT prix_barre FROM produits WHERE id = $kit") === null);
    $r = requete("$base/admin/produit.php?id=$kit", ['csrf' => $csrfBraquet, 'rayon_id' => (string) $valeur("SELECT id FROM rayons WHERE slug = 'cuisine'")] + $champs, $braquet);
    verifier('rayon parent au lieu d\'un sous-rayon : refusé (422)', $r['code'] === 422);
    $r = requete("$base/admin/produit.php?id=$kit", ['csrf' => $csrfBraquet, 'nom' => '<script>alert(1)</script>', 'prix' => 'abc'] + $champs, $braquet);
    verifier('saisie piégée : refusée et réaffichée échappée', $r['code'] === 422 && !str_contains($r['corps'], '<script>alert(1)</script>'));
    verifier('modifier sans jeton CSRF : refusé (400)', requete("$base/admin/produit.php?id=$kit", $champs, $braquet)['code'] === 400);

    // Ajout : une boutique glissée dans le formulaire est ignorée
    $r = requete("$base/admin/produit.php", ['csrf' => $csrfBraquet, 'nom' => 'Sonnette de vélo test-sécurité',
        'boutique_id' => (string) $idBoutique('de-a-six')] + $champs, $braquet);
    $nouveau = db()->query("SELECT id, slug, boutique_id, etat FROM produits WHERE nom = 'Sonnette de vélo test-sécurité'")->fetch() ?: null;
    verifier('ajouter un produit : rattaché à SA boutique (pas à celle du formulaire), en vente, slug sans accent',
        $r['code'] === 303 && $nouveau && (int) $nouveau['boutique_id'] === $idBoutique('braquet')
        && $nouveau['etat'] === 'en_vente' && $nouveau['slug'] === 'sonnette-de-velo-test-securite');
    verifier('le nouveau produit est en ligne', $nouveau && requete("$base/produit.php?produit={$nouveau['slug']}")['code'] === 200);
    requete("$base/admin/produits.php", ['csrf' => $csrfBraquet, 'produit' => $nouveau['id'] ?? 0, 'en_vente' => '0'], $braquet);
    verifier('retirer de la vente : la fiche publique répond 404', $nouveau && requete("$base/produit.php?produit={$nouveau['slug']}")['code'] === 404);
    requete("$base/admin/produits.php", ['csrf' => $csrfBraquet, 'produit' => $jeu, 'en_vente' => '0'], $braquet);
    verifier('retirer le produit d\'une autre boutique : refusé', $valeur("SELECT etat FROM produits WHERE id = $jeu") === 'en_vente');
    db()->exec('UPDATE produits SET etat = \'masque\' WHERE id = ' . (int) ($nouveau['id'] ?? 0));
    requete("$base/admin/produits.php", ['csrf' => $csrfBraquet, 'produit' => $nouveau['id'] ?? 0, 'en_vente' => '1'], $braquet);
    verifier('produit masqué par l\'admin : le vendeur ne peut pas le remettre en vente',
        $valeur('SELECT etat FROM produits WHERE id = ' . (int) ($nouveau['id'] ?? 0)) === 'masque');

    [, $admin2] = seConnecter($base, 'test-vendeur-admin', $mdpAdmin2);
    verifier('admin : les pages vendeur lui sont refusées (403)',
        requete("$base/admin/colis.php", null, $admin2)['code'] === 403 && requete("$base/admin/produit.php", null, $admin2)['code'] === 403);
} finally {
    if ($numero) {
        db()->prepare('DELETE FROM commandes WHERE numero = ?')->execute([$numero]);
    }
    if ($nouveau) {
        db()->exec('DELETE FROM produits WHERE id = ' . (int) $nouveau['id']);
    }
    $remettre = db()->prepare('UPDATE produits SET nom = :nom, description = :description, caracteristiques = :caracteristiques,
        prix = :prix, prix_barre = :prix_barre, stock = :stock, delai_jours = :delai_jours, rayon_id = :rayon_id WHERE id = :id');
    $remettre->execute(array_intersect_key($kitAvant, array_flip(['nom', 'description', 'caracteristiques', 'prix', 'prix_barre', 'stock', 'delai_jours', 'rayon_id', 'id'])));
    foreach ($stocksAvant as $id => $stock) {
        db()->exec("UPDATE produits SET stock = $stock WHERE id = $id");
    }
    db()->exec("DELETE FROM comptes WHERE identifiant LIKE 'test-vendeur-%'");
}
verifier('base remise en état : produit, stocks, comptes, commande',
    db()->query("SELECT * FROM produits WHERE id = $kit")->fetch() == $kitAvant
    && db()->query("SELECT id, stock FROM produits WHERE id IN ($kit, $cable, $jeu)")->fetchAll(PDO::FETCH_KEY_PAIR) == $stocksAvant
    && !(int) $valeur("SELECT COUNT(*) FROM comptes WHERE identifiant LIKE 'test-vendeur-%'")
    && !(int) $valeur("SELECT COUNT(*) FROM produits WHERE nom LIKE '%test-sécurité%'"));
echo "Outils de l'admin : suspendre une boutique, masquer un produit\n";
$mdpModo    = bin2hex(random_bytes(12));
$mdpVendeur = bin2hex(random_bytes(12));
db()->exec("DELETE FROM comptes WHERE identifiant LIKE 'test-moderation-%'");
$creer->execute(['test-moderation-admin', password_hash($mdpModo, PASSWORD_DEFAULT), 'admin', null]);
$creer->execute(['test-moderation-braquet', password_hash($mdpVendeur, PASSWORD_DEFAULT), 'vendeur', $idBoutique('braquet')]);
$braquetId = $idBoutique('braquet');
$gravel = 'velo-gravel-braquet-g1';

try {
    foreach (['/admin/boutiques.php', '/admin/moderation.php'] as $page) {
        verifier("$page sans connexion : renvoie vers la connexion",
            str_ends_with(requete($base . $page)['entetes']['location'] ?? '', '/admin/connexion.php'));
    }
    [, $modo] = seConnecter($base, 'test-moderation-admin', $mdpModo);
    [, $vendeurBraquet] = seConnecter($base, 'test-moderation-braquet', $mdpVendeur);
    verifier('vendeur : Boutiques et Produits (modération) refusées (403)',
        requete("$base/admin/boutiques.php", null, $vendeurBraquet)['code'] === 403
        && requete("$base/admin/moderation.php", null, $vendeurBraquet)['code'] === 403);
    $pages = [requete("$base/admin/boutiques.php", null, $modo), requete("$base/admin/moderation.php", null, $modo)];
    verifier('admin : Boutiques (5) et Produits (80) affichées, sans erreur PHP',
        $pages[0]['code'] === 200 && substr_count($pages[0]['corps'], '<li class="carte-admin') === 5
        && $pages[1]['code'] === 200 && substr_count($pages[1]['corps'], '<li class="liste-moderation__ligne') === 80
        && !preg_match('/Warning|Notice|Deprecated|Fatal error/', $pages[0]['corps'] . $pages[1]['corps']));
    $csrfModo = csrfDe("$base/admin/boutiques.php", $modo);

    // --- Suspendre une boutique ------------------------------------------------
    verifier('suspendre sans jeton CSRF : refusé (400)',
        requete("$base/admin/boutiques.php", ['boutique' => $braquetId, 'active' => '0'], $modo)['code'] === 400);
    verifier('vendeur : suspendre une boutique refusé (403), rien ne change',
        requete("$base/admin/boutiques.php", ['csrf' => csrfDe("$base/admin/colis.php", $vendeurBraquet), 'boutique' => $braquetId, 'active' => '0'], $vendeurBraquet)['code'] === 403
        && $valeur("SELECT etat FROM boutiques WHERE id = $braquetId") === 'active');
    requete("$base/admin/boutiques.php", ['csrf' => $csrfModo, 'boutique' => $braquetId, 'active' => '0'], $modo);
    verifier('boutique suspendue : sa fiche, ses produits et son filtre disparaissent du site',
        $valeur("SELECT etat FROM boutiques WHERE id = $braquetId") === 'suspendue'
        && requete("$base/boutique.php?boutique=braquet")['code'] === 404
        && requete("$base/produit.php?produit=$gravel")['code'] === 404
        && requete("$base/catalogue.php?boutique=braquet")['code'] === 404
        && !str_contains(requete("$base/sitemap.xml")['corps'], $gravel));
    verifier('boutique suspendue : son vendeur garde l\'accès à son espace',
        requete("$base/admin/produits.php", null, $vendeurBraquet)['code'] === 200);
    requete("$base/admin/boutiques.php", ['csrf' => $csrfModo, 'boutique' => $braquetId, 'active' => '1'], $modo);
    verifier('boutique réactivée : de nouveau en ligne',
        requete("$base/boutique.php?boutique=braquet")['code'] === 200 && requete("$base/produit.php?produit=$gravel")['code'] === 200);

    // --- Masquer un produit -----------------------------------------------------
    $idGravel = $idProduit($gravel);
    requete("$base/admin/moderation.php", ['csrf' => $csrfModo, 'produit' => $idGravel, 'masquer' => '1', 'ancre' => 'b3'], $modo);
    verifier('produit masqué : sa fiche publique répond 404',
        $valeur("SELECT etat FROM produits WHERE id = $idGravel") === 'masque' && requete("$base/produit.php?produit=$gravel")['code'] === 404);
    verifier('produit masqué : sa boutique le voit « masqué », sans bouton pour le remettre',
        str_contains(requete("$base/admin/produits.php", null, $vendeurBraquet)['corps'], 'Masqué par l&#039;administration'));
    $r = requete("$base/admin/moderation.php", ['csrf' => $csrfModo, 'produit' => $idGravel, 'masquer' => '1', 'ancre' => '"><script>'], $modo);
    verifier('ancre piégée dans la redirection : ignorée', !str_contains($r['entetes']['location'] ?? '', 'script'));
    requete("$base/admin/moderation.php", ['csrf' => $csrfModo, 'produit' => $idGravel, 'masquer' => '0'], $modo);
    verifier('produit démasqué : de nouveau en vente',
        $valeur("SELECT etat FROM produits WHERE id = $idGravel") === 'en_vente' && requete("$base/produit.php?produit=$gravel")['code'] === 200);
} finally {
    db()->exec("UPDATE boutiques SET etat = 'active' WHERE id = $braquetId");
    db()->exec("UPDATE produits SET etat = 'en_vente' WHERE slug = '$gravel'");
    db()->exec("DELETE FROM comptes WHERE identifiant LIKE 'test-moderation-%'");
}
echo "\n" . ($echecs === 0 ? 'Tout passe.' : "$echecs échec(s).") . "\n";
exit($echecs === 0 ? 0 : 1);
