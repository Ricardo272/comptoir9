<?php
// Page « Contact » : coordonnées. Le formulaire de message arrive en phase 5.
$pageId          = 'contact';
$pageTitle       = 'Contact';
$pageDescription = 'Joindre le service client de Comptoir 9 : téléphone, e-mail, adresse et horaires.';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<main id="contenu">
    <section class="section">
        <div class="conteneur conteneur--etroit prose">
            <h1>Contact</h1>
            <p class="chapeau">Un seul service client pour toutes les boutiques du comptoir.</p>

            <h2>Service client</h2>
            <p>Ouvert <?= e($site['horaires_service']) ?>.</p>
            <ul>
                <li>Téléphone : <a href="tel:<?= e(lienTelephone($site['telephone'])) ?>"><?= e($site['telephone']) ?></a></li>
                <li>E-mail : <a href="mailto:<?= e($site['email']) ?>"><?= e($site['email']) ?></a></li>
            </ul>

            <h2>Adresse</h2>
            <address>
                <?= e($site['nom']) ?><br>
                <?= e($site['adresse']['rue']) ?><br>
                <?= e($site['adresse']['code_postal'] . ' ' . $site['adresse']['ville']) ?>
            </address>
            <p>Nos bureaux ne reçoivent pas de public et aucun produit n'y est stocké :
               chaque boutique expédie depuis ses propres locaux.</p>

            <p>Une question sur une commande ou une livraison ? La page
               <a href="<?= e(url('aide.php')) ?>">Aide</a> répond aux plus fréquentes.</p>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
