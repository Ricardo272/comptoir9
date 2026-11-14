<?php
// Page « Les boutiques » : la liste des vendeurs
$pageId          = 'boutiques';
$pageTitle       = 'Les boutiques';
$pageDescription = 'Les boutiques indépendantes de Comptoir 9 : chacune est rencontrée et vérifiée avant d\'ouvrir son rayon.';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/donnees/catalogue.php';
?>

<main id="contenu">
    <section class="section">
        <div class="conteneur">
            <h1>Les boutiques</h1>
            <p class="chapeau">Des commerçants indépendants, rencontrés un à un. Chaque boutique prépare
               et expédie elle-même ses produits.</p>
            <div class="grille grille--boutiques">
<?php foreach (boutiques() as $b): ?>
<?php require __DIR__ . '/includes/carte-boutique.php'; ?>
<?php endforeach; ?>
            </div>
            <p>Vous tenez une boutique ? <a href="<?= e(url('a-propos.php')) ?>">Rejoindre le comptoir</a>.</p>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
