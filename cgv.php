<?php
// Page « Conditions générales de vente » — version courte, de démonstration
$pageId          = 'cgv';
$pageTitle       = 'Conditions de vente';
$pageDescription = 'Conditions générales de vente de démonstration du site Comptoir 9.';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';

$port = $site['frais_port'];
?>

<main id="contenu">
    <section class="section">
        <div class="conteneur conteneur--etroit prose">
            <h1>Conditions de vente</h1>

            <div class="encadre">
                <p>
                    <strong>Texte de démonstration.</strong> <?= e($site['nom']) ?> est une place de marché
                    fictive : aucune vente n'a réellement lieu. Ces conditions montrent ce que contiendrait
                    la page d'une vraie boutique ; elles n'ont pas été rédigées par un juriste.
                </p>
            </div>

            <h2>1. Qui vend</h2>
            <p>Chaque produit est vendu par la boutique indiquée sur sa fiche. Comptoir 9 met en relation
               l'acheteur et la boutique, enregistre la commande et assure le service client.</p>

            <h2>2. Prix</h2>
            <p>Les prix sont affichés en euros, toutes taxes comprises. Le prix payé est celui affiché
               au moment de la validation de la commande.</p>

            <h2>3. Commande</h2>
            <p>La commande se passe sans compte. Elle est enregistrée quand vous la validez, et un numéro
               vous est remis pour la suivre. Un produit n'est réservé qu'à ce moment-là : s'il a été vendu
               entre-temps, il est retiré de votre panier et vous en êtes informé avant de valider.</p>

            <h2>4. Paiement</h2>
            <p>Sur ce site de démonstration, aucun paiement n'est demandé et aucun moyen de paiement n'est saisi.</p>

            <h2>5. Livraison</h2>
            <p>Chaque boutique expédie ses propres produits : une commande peut donc arriver en plusieurs colis.
               Les frais de port sont de <span class="prix"><?= e(prix($port['par_boutique'])) ?></span> par boutique,
               offerts dès <span class="prix"><?= e(prix($port['offerts_des'])) ?></span> d'achat dans la même boutique.
               Le délai d'expédition est indiqué sur chaque fiche produit.</p>

            <h2>6. Droit de rétractation</h2>
            <p>Vous disposez de quatorze jours pour exercer votre droit de rétractation, sans avoir à motiver
               votre décision (article L. 221-18 du Code de la consommation).</p>

            <h2>7. Données personnelles</h2>
            <p>Les données recueillies pour une commande et leur durée de conservation sont décrites dans les
               <a href="<?= e(url('mentions-legales.php')) ?>">mentions légales</a>.</p>

            <p class="prose__maj">Dernière mise à jour : novembre 2026.</p>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
