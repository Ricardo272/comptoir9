<?php
// Fiche d'un produit : produit.php?produit=<slug>
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/donnees/rayons.php';
require_once __DIR__ . '/includes/donnees/catalogue.php';
require_once __DIR__ . '/includes/panier.php';

$slug = $_GET['produit'] ?? '';
$produit = is_string($slug) && $slug !== '' ? produit($slug) : null;

// Produit inconnu, retiré, masqué, ou boutique suspendue : vraie page 404
if ($produit === null) {
    require __DIR__ . '/404.php';
    exit;
}

$emplacement = trouverRayon($produit['rayon_slug']); // son rayon et son sous-rayon
$autres = autresProduits($produit['boutique_id'], $produit['id'], 4);
$urlBoutique = url('boutique.php?boutique=' . rawurlencode($produit['boutique_slug']));
$port = $site['frais_port'];

$filAriane = [
    ['Accueil', url()],
    [$emplacement['rayon']['nom'], urlRayon($emplacement['rayon']['slug'])],
    [$emplacement['sous_rayon']['nom'], urlRayon($emplacement['sous_rayon']['slug'])],
    [$produit['nom'], ''],
];

$pageId          = 'produit';
$pageTitle       = $produit['nom'];
$pageDescription = $produit['description'] . ' Vendu par ' . $produit['boutique'] . ' sur Comptoir 9.';
$pageCanonique   = 'produit.php?produit=' . rawurlencode($produit['slug']);
$pageJsonLd = [
    '@type'       => 'Product',
    'name'        => $produit['nom'],
    'description' => $produit['description'],
    'sku'         => $produit['slug'],
    'offers'      => [
        '@type'         => 'Offer',
        'url'           => urlAbsolue($pageCanonique),
        'price'         => number_format($produit['prix'] / 100, 2, '.', ''), // « 79.90 » : point décimal exigé
        'priceCurrency' => 'EUR',
        'availability'  => 'https://schema.org/' . ($produit['stock'] > 0 ? 'InStock' : 'OutOfStock'),
        'seller'        => ['@type' => 'Organization', 'name' => $produit['boutique']],
    ],
];

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<main id="contenu">
    <section class="section">
        <div class="conteneur">
<?php require __DIR__ . '/includes/fil-ariane.php'; ?>

            <div class="fiche">
                <div class="fiche__photo">
                    <?= image('produits/' . $produit['slug'] . '.jpg', $produit['nom'], 600, 600, '', true) ?>

                </div>

                <div class="fiche__infos">
                    <h1 class="fiche__nom"><?= e($produit['nom']) ?></h1>
                    <p>Vendu et expédié par <a href="<?= e($urlBoutique) ?>"><?= e($produit['boutique']) ?></a>,
                       <?= e($produit['boutique_ville']) ?></p>

                    <p class="fiche__prix">
                        <span class="etiquette"><span class="etiquette__fond prix"><?= e(prix($produit['prix'])) ?></span></span>
<?php if ($produit['prix_barre']): ?>
                        <s class="prix prix--barre"><span class="visuellement-cache">Ancien prix : </span><?= e(prix($produit['prix_barre'])) ?></s>
<?php endif; ?>
                    </p>

                    <ul class="fiche__points" role="list">
<?php if ($produit['stock'] <= 0): ?>
                        <li><span class="pastille pastille--epuise">Épuisé</span> Ce produit n'est plus disponible pour le moment.</li>
<?php elseif ($produit['stock'] <= 3): ?>
                        <li><span class="pastille">Plus que <?= (int) $produit['stock'] ?></span> en stock</li>
<?php else: ?>
                        <li><strong>En stock</strong></li>
<?php endif; ?>
                        <li>Expédié sous <?= (int) $produit['delai_jours'] ?> jour<?= $produit['delai_jours'] > 1 ? 's' : '' ?> ouvré<?= $produit['delai_jours'] > 1 ? 's' : '' ?> par <?= e($produit['boutique']) ?></li>
                        <li>Livraison <span class="prix"><?= e(prix($port['par_boutique'])) ?></span>, offerte dès
                            <span class="prix"><?= e(prix($port['offerts_des'])) ?></span> d'achat chez <?= e($produit['boutique']) ?></li>
                    </ul>

<?php if ($produit['stock'] > 0): ?>
                    <form class="fiche__achat" action="<?= e(url('panier.php')) ?>" method="post">
                        <input type="hidden" name="action" value="ajouter">
                        <input type="hidden" name="produit" value="<?= e($produit['slug']) ?>">
                        <input type="hidden" name="jeton" value="<?= e(creerJeton()) ?>">
                        <p class="champ champ--court">
                            <label for="quantite">Quantité</label>
                            <input id="quantite" type="number" name="quantite" inputmode="numeric" min="1"
                                   max="<?= min($produit['stock'], QUANTITE_MAX) ?>" value="1" required>
                        </p>
                        <button class="bouton bouton--accent" type="submit">Ajouter au panier</button>
                    </form>
<?php else: ?>
                    <!-- Le bouton reste visible mais inactif : on comprend pourquoi on ne peut pas acheter -->
                    <p><button class="bouton bouton--accent" type="button" disabled>Ajouter au panier</button></p>
<?php endif; ?>

                    <h2 class="fiche__titre">Description</h2>
                    <p><?= e($produit['description']) ?></p>

                    <h2 class="fiche__titre">Caractéristiques</h2>
                    <ul>
<?php foreach (explode("\n", $produit['caracteristiques']) as $caracteristique): ?>
                        <li><?= e(trim($caracteristique)) ?></li>
<?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </section>

<?php if ($autres): ?>
    <section class="section section--filet">
        <div class="conteneur">
            <h2>D'autres produits de <?= e($produit['boutique']) ?></h2>
            <div class="grille">
<?php foreach ($autres as $p): ?>
<?php require __DIR__ . '/includes/carte-produit.php'; ?>
<?php endforeach; ?>
            </div>
            <p><a href="<?= e($urlBoutique) ?>">Voir la boutique <?= e($produit['boutique']) ?></a></p>
        </div>
    </section>
<?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
