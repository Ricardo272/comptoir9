<?php
// Plan du site pour les moteurs de recherche, servi à l'adresse /sitemap.xml
// (voir .htaccess). Il est fabriqué à partir de la base : un produit masqué
// ou une boutique suspendue en sort d'eux-mêmes.
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/donnees/rayons.php';
require_once __DIR__ . '/includes/donnees/catalogue.php';

$adresses = ['', 'catalogue.php', 'boutiques.php', 'a-propos.php', 'aide.php', 'contact.php',
             'suivi.php', 'cgv.php', 'mentions-legales.php'];

foreach (rayons() as $rayonDuPlan) {
    $adresses[] = 'catalogue.php?rayon=' . rawurlencode($rayonDuPlan['slug']);
    foreach ($rayonDuPlan['sous_rayons'] as $sousRayonDuPlan) {
        $adresses[] = 'catalogue.php?rayon=' . rawurlencode($sousRayonDuPlan['slug']);
    }
}
foreach (boutiques() as $b) {
    $adresses[] = 'boutique.php?boutique=' . rawurlencode($b['slug']);
}
foreach (slugsProduits() as $slug) {
    $adresses[] = 'produit.php?produit=' . rawurlencode($slug);
}

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($adresses as $adresse): ?>
    <url><loc><?= htmlspecialchars(urlAbsolue($adresse), ENT_XML1, 'UTF-8') ?></loc></url>
<?php endforeach; ?>
</urlset>
