<?php
// Accueil — version « fondations » (phase 1) : le vrai contenu arrive en phase 3.
$pageId = 'accueil';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';

$port = $site['frais_port'];
?>

<main id="contenu">
    <section class="section">
        <div class="conteneur">
            <h1><?= e($site['slogan']) ?></h1>
            <p class="chapeau">Des boutiques indépendantes, vérifiées une à une, réunies derrière le même comptoir.</p>
            <p>Livraison <span class="prix"><?= e(prix($port['par_boutique'])) ?></span> par boutique,
               offerte dès <span class="prix"><?= e(prix($port['offerts_des'])) ?></span> d'achat dans la même boutique.</p>
        </div>
    </section>

    <section class="section">
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
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
