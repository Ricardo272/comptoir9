<?php
/**
 * Contrôle des fonctions communes. À lancer en ligne de commande :
 *   php tests/helpers.php
 * Code de sortie 0 si tout passe, 1 sinon.
 */
if (PHP_SAPI !== 'cli') {
    exit;
}

require __DIR__ . '/../includes/helpers.php';

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

// Le contrôle doit savoir échouer : un cas volontairement faux doit être refusé.
if (verifier('cas faux', prix(490), '4,90 €', true)) { // espace ordinaire au lieu de l'insécable
    echo 'ÉCHEC  le contrôle accepte un résultat faux' . PHP_EOL;
    exit(1);
}
echo 'OK     le contrôle refuse un cas volontairement faux' . PHP_EOL;

$nbsp = "\u{00A0}";
$fine = "\u{202F}";

$cas = [
    ['prix 490',     prix(490),     "4,90{$nbsp}€"],
    ['prix 6000',    prix(6000),    "60,00{$nbsp}€"],
    ['prix 5',       prix(5),       "0,05{$nbsp}€"],
    ['prix 0',       prix(0),       "0,00{$nbsp}€"],
    ['prix 129900',  prix(129900),  "1{$fine}299,00{$nbsp}€"],
    ['prix négatif', prix(-250),    "-2,50{$nbsp}€"],
    ['e() balise',   e('<script>alert("x")</script>'), '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;'],
    ['e() apostrophe', e("l'été"),  'l&#039;été'],
    ['e() null',     e(null),       ''],
    ['url()',        url(),         '/'],
    ['url page',     url('panier.php'), '/panier.php'],
    ['url barre',    url('/panier.php'), '/panier.php'],
    ['urlAbsolue',   urlAbsolue('panier.php'), rtrim(configLocale()['url_absolue'], '/') . '/panier.php'],
    ['téléphone',    lienTelephone('01 99 00 48 84'), '+33199004884'],
    ['asset absent', asset('assets/absent.css'), '/assets/absent.css?v=0'],
];

// image() : fichier absent → SVG de remplacement, valide même avec un chemin piégé
$img = image('absent/<piege>&".jpg', '<b>"alt"', 600, 400);
preg_match('/src="(data:image\/svg\+xml,[^"]+)"/', $img, $src);
$svg = rawurldecode(substr(html_entity_decode($src[1] ?? ''), strlen('data:image/svg+xml,')));
$cas[] = ['image src data:',      isset($src[1]), true];
$cas[] = ['image SVG bien formé', @simplexml_load_string($svg) !== false, true];
$cas[] = ['image alt échappé',    str_contains($img, 'alt="&lt;b&gt;&quot;alt&quot;"'), true];
$cas[] = ['image dimensions',     str_contains($img, 'width="600" height="400"'), true];
$cas[] = ['image classe',         str_contains($img, 'class="image-remplacement"'), true];
$cas[] = ['image paresseuse',     str_contains($img, 'loading="lazy"'), true];

foreach ($cas as [$nom, $obtenu, $attendu]) {
    if (!verifier($nom, $obtenu, $attendu)) {
        $echecs++;
    }
}

echo PHP_EOL . ($echecs === 0 ? 'Tout passe.' : $echecs . ' échec(s).') . PHP_EOL;
exit($echecs === 0 ? 0 : 1);
