<?php
/**
 * Le panier : session, calcul des totaux, protections des actions.
 *
 * Le panier est gardé en session PHP sous la forme [id du produit => quantité].
 * Les prix et les stocks ne sont JAMAIS gardés en session : ils sont relus en
 * base à chaque affichage, pour que le panier dise toujours la vérité.
 */
require_once __DIR__ . '/formulaire.php';
require_once __DIR__ . '/donnees/catalogue.php';

const SESSION_PANIER = 'c9_panier';
const QUANTITE_MAX = 20; // par produit

/**
 * Ouvre la session du panier. Sans $creer, elle n'est ouverte que si le
 * visiteur a déjà le cookie : une simple visite ne dépose donc aucun cookie.
 * Seul le premier ajout au panier appelle cette fonction avec $creer = true.
 */
function ouvrirSessionPanier(bool $creer = false): bool
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }
    if (PHP_SAPI === 'cli' || (!$creer && !isset($_COOKIE[SESSION_PANIER]))) {
        return false;
    }

    session_name(SESSION_PANIER);
    // Refuse un identifiant de session inventé par le visiteur
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'path'     => '/',
        'httponly' => true,   // illisible en JavaScript
        'samesite' => 'Lax',  // pas envoyé par un formulaire d'un autre site
        'secure'   => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
    ]);
    session_start();

    return true;
}

/** [id du produit => quantité], vide si le visiteur n'a pas de panier. */
function quantitesPanier(): array
{
    return ouvrirSessionPanier() ? ($_SESSION['panier'] ?? []) : [];
}

function nombreArticlesPanier(): int
{
    return array_sum(quantitesPanier());
}

/** Jeton CSRF lié à la session : exigé pour modifier le panier et commander. */
function jetonSession(): string
{
    return $_SESSION['jeton'] ??= bin2hex(random_bytes(16));
}

function jetonSessionValide(mixed $jeton): bool
{
    return is_string($jeton) && isset($_SESSION['jeton']) && hash_equals($_SESSION['jeton'], $jeton);
}

/**
 * La requête vient-elle d'une page du site ? Le navigateur envoie l'en-tête
 * Origin avec tout formulaire POST : s'il nomme un autre site, on refuse.
 * (Absent chez de vieux navigateurs : le jeton reste alors la seule protection.)
 */
function requeteDuSite(): bool
{
    $origine = $_SERVER['HTTP_ORIGIN'] ?? null;
    if ($origine === null) {
        return true;
    }
    $hote = parse_url($origine, PHP_URL_HOST);
    $port = parse_url($origine, PHP_URL_PORT);
    return ($hote . ($port ? ':' . $port : '')) === ($_SERVER['HTTP_HOST'] ?? '');
}

/** Message affiché une seule fois, sur la prochaine page (après une redirection). */
function ajouterAvis(string $message): void
{
    $_SESSION['avis'][] = $message;
}

function lireAvis(): array
{
    $avis = $_SESSION['avis'] ?? [];
    unset($_SESSION['avis']);
    return $avis;
}

/** Les produits visibles parmi ces identifiants, indexés par identifiant. */
function produitsParId(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $requete = db()->prepare(
        "SELECT p.id, p.slug, p.nom, p.prix, p.stock,
                b.id AS boutique_id, b.nom AS boutique, b.slug AS boutique_slug
         FROM produits p
         JOIN boutiques b ON b.id = p.boutique_id
         WHERE p.etat = 'en_vente' AND b.etat = 'active'
           AND p.id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')
         ORDER BY b.id, p.nom'
    );
    $requete->execute(array_values($ids));
    return array_column($requete->fetchAll(), null, 'id');
}

/**
 * Calcule le panier à partir des quantités voulues et des produits relus en base.
 * Fonction pure (ni session, ni base) : c'est elle que tests/commande.php vérifie.
 *
 * - un produit absent de $produits (retiré, masqué, boutique suspendue) ou
 *   épuisé sort du panier ;
 * - une quantité trop grande est ramenée au stock (et à QUANTITE_MAX) ;
 * - frais de port : un forfait par boutique, offert dès le seuil atteint dans cette boutique.
 *
 * Renvoie :
 *   'boutiques'  [id => nom, slug, lignes [produit, quantite, total], sous_total, port, manque]
 *   'quantites'  les quantités corrigées, à remettre en session
 *   'avis'       ce qui a été corrigé, à dire au visiteur
 *   'articles', 'total_produits', 'total_port', 'total'
 */
function calculerPanier(array $produits, array $quantites, array $fraisPort): array
{
    $panier = ['boutiques' => [], 'quantites' => [], 'avis' => [],
               'articles' => 0, 'total_produits' => 0, 'total_port' => 0, 'total' => 0];

    foreach ($produits as $id => $produit) { // dans l'ordre des boutiques
        $voulue = (int) ($quantites[$id] ?? 0);
        if ($voulue < 1) {
            continue;
        }
        if ($produit['stock'] < 1) {
            $panier['avis'][] = '« ' . $produit['nom'] . ' » est épuisé : il a été retiré de votre panier.';
            continue;
        }
        $quantite = min($voulue, $produit['stock'], QUANTITE_MAX);
        if ($quantite < $voulue) {
            $panier['avis'][] = '« ' . $produit['nom'] . ' » : quantité ramenée à ' . $quantite
                              . ($quantite === $produit['stock'] ? ' (stock disponible).' : ' (maximum par produit).');
        }

        $boutique = &$panier['boutiques'][$produit['boutique_id']];
        $boutique ??= ['nom' => $produit['boutique'], 'slug' => $produit['boutique_slug'],
                       'lignes' => [], 'sous_total' => 0, 'port' => 0, 'manque' => 0];
        $boutique['lignes'][] = ['produit' => $produit, 'quantite' => $quantite, 'total' => $quantite * $produit['prix']];
        $boutique['sous_total'] += $quantite * $produit['prix'];
        unset($boutique); // fin de la référence

        $panier['quantites'][$id] = $quantite;
        $panier['articles'] += $quantite;
    }

    // Demandé dans le panier, mais plus parmi les produits visibles
    if (array_diff_key(array_filter($quantites, fn ($q) => $q >= 1), $produits)) {
        $panier['avis'][] = 'Un produit qui n\'est plus en vente a été retiré de votre panier.';
    }

    foreach ($panier['boutiques'] as &$boutique) {
        $offert = $boutique['sous_total'] >= $fraisPort['offerts_des'];
        $boutique['port'] = $offert ? 0 : $fraisPort['par_boutique'];
        $boutique['manque'] = $offert ? 0 : $fraisPort['offerts_des'] - $boutique['sous_total'];
        $panier['total_produits'] += $boutique['sous_total'];
        $panier['total_port'] += $boutique['port'];
    }
    unset($boutique);
    $panier['total'] = $panier['total_produits'] + $panier['total_port'];

    return $panier;
}

/**
 * Le panier du visiteur, recalculé avec les prix et les stocks du moment.
 * Les quantités corrigées sont remises en session.
 */
function chargerPanier(): array
{
    global $site;
    $quantites = quantitesPanier();
    $panier = calculerPanier(produitsParId(array_keys($quantites)), $quantites, $site['frais_port']);
    if ($quantites) {
        $_SESSION['panier'] = $panier['quantites'];
    }
    return $panier;
}
