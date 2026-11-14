<?php
// Page « À propos »
$pageId          = 'a-propos';
$pageTitle       = 'À propos';
$pageDescription = 'L\'histoire de Comptoir 9, place de marché créée à Lyon par deux anciens vendeurs, et ses trois engagements.';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<main id="contenu">
    <section class="section">
        <div class="conteneur conteneur--etroit prose">
            <h1>Deux vendeurs, un comptoir.</h1>
            <p class="chapeau">Comptoir 9 est né à Lyon en 2021, d'une idée simple : acheter en ligne
               auprès de vraies boutiques, sans avoir à ouvrir dix sites.</p>

            <h2>L'histoire</h2>
            <p>John Damien et Christophe Dubois ont passé quinze ans derrière un comptoir, l'un au rayon
               informatique, l'autre en cuisine et arts de la table. Ils y ont appris deux choses : un bon
               vendeur connaît ce qu'il vend, et un client revient quand on ne lui a rien caché.</p>
            <p>En ligne, ils retrouvaient rarement l'une et l'autre. Des vendeurs dont on ne sait rien,
               des frais qui apparaissent à la dernière étape, des délais qui s'allongent une fois la
               commande passée. Ils ont donc monté leur propre comptoir, avec des boutiques qu'ils
               connaissent.</p>

            <h2>Pourquoi « 9 »</h2>
            <p>Le premier bureau était au numéro 9 d'un passage des pentes de la Croix-Rousse.
               Le chiffre est resté sur la porte, puis sur l'enseigne.
               Nous y sommes toujours.</p>
        </div>
    </section>

    <section class="section section--filet">
        <div class="conteneur">
            <h2>Nos trois engagements</h2>
<?php require __DIR__ . '/includes/engagements.php'; ?>
        </div>
    </section>

    <section class="section section--filet">
        <div class="conteneur conteneur--etroit prose">
            <h2>Rejoindre le comptoir</h2>
            <p>Vous tenez une boutique indépendante et vous vendez des produits neufs ? Nous rencontrons
               chaque vendeur avant de lui ouvrir un rayon. Écrivez-nous à
               <a href="mailto:<?= e($site['email']) ?>"><?= e($site['email']) ?></a> en présentant
               votre boutique et ce que vous vendez.</p>
            <p><a class="bouton bouton--contour" href="<?= e(url('boutiques.php')) ?>">Voir les boutiques</a></p>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
