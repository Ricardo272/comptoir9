<?php
// Fiche d'une boutique : boutique.php?boutique=<slug>
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/donnees/rayons.php';
require_once __DIR__ . '/includes/donnees/catalogue.php';

$slug = $_GET['boutique'] ?? '';
$boutique = is_string($slug) && $slug !== '' ? boutique($slug) : null;

// Boutique inconnue ou suspendue : vraie page 404
if ($boutique === null) {
    require __DIR__ . '/404.php';
    exit;
}

$resultat = rechercherProduits(['boutique' => $boutique['slug']]);
$urlCatalogue = url('catalogue.php?boutique=' . rawurlencode($boutique['slug']));

$filAriane = [
    ['Accueil', url()],
    ['Les boutiques', url('boutiques.php')],
    [$boutique['nom'], ''],
];

$pageId          = 'boutiques';
$pageTitle       = $boutique['nom'];
$pageDescription = $boutique['nom'] . ', ' . $boutique['ville'] . ' : ' . $boutique['accroche'];
$pageCanonique   = 'boutique.php?boutique=' . rawurlencode($boutique['slug']);

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<main id="contenu">
    <section class="section">
        <div class="conteneur conteneur--etroit prose">
<?php require __DIR__ . '/includes/fil-ariane.php'; ?>
            <h1><?= e($boutique['nom']) ?></h1>
            <p class="chapeau"><?= e($boutique['accroche']) ?></p>
            <p><?= e($boutique['presentation']) ?></p>

            <dl class="reperes">
                <div><dt>Ville</dt><dd><?= e($boutique['ville']) ?></dd></div>
                <div><dt>Spécialité</dt><dd><?= e($boutique['specialite']) ?></dd></div>
                <div><dt>Sur Comptoir 9 depuis</dt><dd><?= (int) $boutique['annee_arrivee'] ?></dd></div>
<?php if ($boutique['nb_produits'] > 0): ?>
                <div><dt>Expédition</dt><dd><?= (int) $boutique['delai_moyen'] ?> jours ouvrés en moyenne</dd></div>
<?php endif; ?>
            </dl>
        </div>
    </section>

    <section class="section section--filet">
        <div class="conteneur">
            <h2>Ses produits (<?= (int) $boutique['nb_produits'] ?>)</h2>
<?php if ($resultat['produits']): ?>
            <div class="grille">
<?php foreach ($resultat['produits'] as $p): ?>
<?php require __DIR__ . '/includes/carte-produit.php'; ?>
<?php endforeach; ?>
            </div>
            <p><a href="<?= e($urlCatalogue) ?>">Filtrer et trier les produits de <?= e($boutique['nom']) ?></a></p>
<?php else: ?>
            <p>Cette boutique n'a aucun produit en vente pour le moment.</p>
<?php endif; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
