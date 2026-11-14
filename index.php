<?php
// Accueil — les textes définitifs et les sections manquantes arrivent en phase 3.
$pageId = 'accueil';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/donnees/catalogue.php';

$port = $site['frais_port'];
?>

<main id="contenu">
    <section class="section">
        <div class="conteneur">
            <h1><?= e($site['slogan']) ?></h1>
            <p class="chapeau">Des boutiques indépendantes, vérifiées une à une, réunies derrière le même comptoir.</p>
            <p>Livraison <span class="prix"><?= e(prix($port['par_boutique'])) ?></span> par boutique,
               offerte dès <span class="prix"><?= e(prix($port['offerts_des'])) ?></span> d'achat dans la même boutique.</p>
            <div class="groupe-boutons">
                <a class="bouton bouton--accent" href="<?= e(urlRayon('bonnes-affaires')) ?>">Voir les bonnes affaires</a>
                <a class="bouton bouton--contour" href="<?= e(url('boutiques.php')) ?>">Découvrir les boutiques</a>
            </div>
        </div>
    </section>

    <section class="section section--filet">
        <div class="conteneur">
            <h2>Les rayons</h2>
            <ol class="rayons" role="list">
<?php foreach (rayons() as $rayon): ?>
                <li class="rayons__rayon">
                    <h3><a href="<?= e(urlRayon($rayon['slug'])) ?>"><?= e($rayon['nom']) ?></a></h3>
<?php if ($rayon['sous_rayons']): ?>
                    <p><?= e(implode(' · ', array_column($rayon['sous_rayons'], 'nom'))) ?></p>
<?php else: ?>
                    <p>Les prix barrés de tous les rayons.</p>
<?php endif; ?>
                </li>
<?php endforeach; ?>
            </ol>
        </div>
    </section>

    <section class="section section--filet">
        <div class="conteneur">
            <h2>Nouveautés</h2>
            <div class="grille">
<?php foreach (produitsAccueil() as $p): ?>
<?php require __DIR__ . '/includes/carte-produit.php'; ?>
<?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section section--filet">
        <div class="conteneur">
            <h2>Les boutiques</h2>
            <div class="grille">
<?php foreach (boutiquesAccueil() as $b): ?>
<?php require __DIR__ . '/includes/carte-boutique.php'; ?>
<?php endforeach; ?>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
