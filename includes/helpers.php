<?php
/**
 * Chargé en premier par chaque page (via head.php).
 * Charge les informations du site et définit les petites fonctions communes.
 */

date_default_timezone_set('Europe/Paris');

// En ligne, une erreur PHP n'est jamais affichée au visiteur (elle reste dans le
// journal) : certains hébergeurs l'affichent par défaut. En local (.test), on la voit.
if (PHP_SAPI !== 'cli' && !str_ends_with($_SERVER['HTTP_HOST'] ?? '', '.test')) {
    ini_set('display_errors', '0');
}

$site = require __DIR__ . '/../config/site.php';

/**
 * Toute erreur non prévue (base injoignable, requête en échec…) arrive ici.
 * On écrit le message dans le journal, jamais à l'écran : il peut contenir
 * des détails techniques. On ne journalise pas la trace complète, qui peut
 * contenir les arguments des fonctions (et donc le mot de passe de la base).
 */
set_exception_handler(function (Throwable $erreur) use ($site): void {
    error_log('[Comptoir 9] ' . get_class($erreur) . ' : ' . $erreur->getMessage());

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Erreur : ' . $erreur->getMessage() . PHP_EOL);
        exit(1);
    }

    // On jette le début de page déjà produit mais pas encore envoyé (tampon de sortie)
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Rien d'envoyé au navigateur : vraie page 503. Sinon, un simple message.
    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 300');
        require __DIR__ . '/erreur-503.php';
    } else {
        echo '<p class="bandeau-demo">Une erreur est survenue, réessayez dans quelques minutes.</p>';
    }
});

/**
 * Configuration locale (config/config.local.php) : adresse du site,
 * identifiants de la base, clé secrète. Lue une seule fois, puis gardée en mémoire.
 */
function configLocale(): array
{
    static $config = null;

    if ($config === null) {
        $fichier = __DIR__ . '/../config/config.local.php';
        if (!is_file($fichier)) {
            throw new RuntimeException('config/config.local.php introuvable : copier config.example.php.');
        }
        $config = require $fichier;
    }

    return $config;
}

/**
 * Échappe un texte avant de l'afficher dans le HTML.
 * Empêche qu'un texte contenant des balises soit interprété par le navigateur.
 */
function e(?string $texte): string
{
    return htmlspecialchars($texte ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Construit une adresse à partir de la racine du site.
 * url('panier.php') → « /panier.php » ; url() → « / » (accueil).
 * Fonctionne aussi depuis admin/, contrairement à un lien relatif.
 */
function url(string $chemin = ''): string
{
    global $site;
    return rtrim($site['base_url'], '/') . '/' . ltrim($chemin, '/');
}

/**
 * Adresse complète : urlAbsolue('panier.php') → « http://comptoir-9.test/panier.php ».
 * Indispensable quand l'adresse est lue hors du site (Google, réseaux sociaux).
 */
function urlAbsolue(string $chemin = ''): string
{
    return rtrim(configLocale()['url_absolue'], '/') . url($chemin);
}

/**
 * Adresse d'un fichier CSS, JS ou image, suivie de sa date de modification :
 * asset('assets/js/menu.js') → « /assets/js/menu.js?v=1762712345 ».
 * Quand le fichier change, l'adresse change : le navigateur ne peut plus
 * se servir d'une ancienne version gardée en cache.
 */
function asset(string $chemin): string
{
    $fichier = __DIR__ . '/../' . $chemin;
    $version = is_file($fichier) ? filemtime($fichier) : 0;
    return url($chemin) . '?v=' . $version;
}

/**
 * Transforme « 01 99 00 48 84 » en « +33199004884 » pour un lien tel:.
 */
function lienTelephone(string $numero): string
{
    $chiffres = preg_replace('/\D/', '', $numero);
    return '+33' . substr($chiffres, 1);
}

/**
 * Prix à la française, à partir d'un montant en CENTIMES (entier) :
 * 490 → « 4,90 € » ; 6000 → « 60,00 € » ; 129900 → « 1 299,00 € ».
 * Toujours deux décimales, pour que les prix s'alignent comme sur un ticket.
 * Les espaces sont insécables : le prix ne se coupe jamais en fin de ligne.
 */
function prix(int $centimes): string
{
    // Pas de division flottante : on sépare euros et centimes en entiers.
    $signe = $centimes < 0 ? '-' : '';
    $euros = intdiv(abs($centimes), 100);
    $reste = abs($centimes) % 100;
    return $signe . number_format($euros, 0, ',', "\u{202F}") . ',' . sprintf('%02d', $reste) . "\u{00A0}€";
}

/**
 * Affiche une image du dossier assets/img/.
 *
 * Si le fichier n'existe pas encore, affiche à la place une image de remplacement
 * aux mêmes proportions, avec le chemin attendu écrit dessus.
 * Le jour où la vraie photo arrive, il suffit de la déposer au bon endroit.
 *
 * @param string $chemin       chemin dans assets/img/, ex. « produits/cocotte-fonte-24.jpg »
 * @param string $alt          description de l'image ; vide si le texte voisin la décrit déjà
 * @param int    $largeur      largeur réelle de l'image, en pixels
 * @param int    $hauteur      hauteur réelle de l'image, en pixels
 * @param string $classe       classe CSS éventuelle
 * @param bool   $prioritaire  true pour une image visible dès l'ouverture de la page
 */
function image(string $chemin, string $alt, int $largeur, int $hauteur, string $classe = '', bool $prioritaire = false): string
{
    // width et height : le navigateur réserve la place avant le chargement,
    // la page ne « saute » pas.
    $attributs = sprintf('alt="%s" width="%d" height="%d"', e($alt), $largeur, $hauteur);
    $attributs .= $prioritaire ? ' fetchpriority="high"' : ' loading="lazy" decoding="async"';

    if (is_file(__DIR__ . '/../assets/img/' . $chemin)) {
        $src = asset('assets/img/' . $chemin);
    } else {
        $src = imageRemplacement($chemin, $largeur, $hauteur);
        $classe = trim($classe . ' image-remplacement');
    }

    if ($classe !== '') {
        $attributs .= ' class="' . e($classe) . '"';
    }

    return '<img src="' . e($src) . '" ' . $attributs . '>';
}

/**
 * Fabrique une image SVG de remplacement (fond papier rayé + chemin attendu)
 * et la renvoie sous forme d'adresse « data: » : aucun fichier n'est créé.
 */
function imageRemplacement(string $chemin, int $largeur, int $hauteur): string
{
    $taille = max(14, (int) round(min($largeur, $hauteur) / 20));
    $milieu = $hauteur / 2;

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $largeur . ' ' . $hauteur . '">'
         . '<defs><pattern id="r" width="18" height="18" patternUnits="userSpaceOnUse" patternTransform="rotate(-45)">'
         . '<line x1="0" y1="0" x2="0" y2="18" stroke="#0F4C3A" stroke-opacity=".14" stroke-width="3"/></pattern></defs>'
         . '<rect width="100%" height="100%" fill="#F5F3EE"/>'
         . '<rect width="100%" height="100%" fill="url(#r)"/>'
         . '<g font-family="Segoe UI, Arial, sans-serif" text-anchor="middle" fill="#16211D">'
         . '<text x="50%" y="' . ($milieu - $taille * 0.4) . '" font-size="' . $taille . '" font-weight="700">IMAGE DE REMPLACEMENT</text>'
         . '<text x="50%" y="' . ($milieu + $taille) . '" font-size="' . round($taille * 0.8) . '">'
         . htmlspecialchars($chemin, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</text>'
         . '</g></svg>';

    return 'data:image/svg+xml,' . rawurlencode($svg);
}
