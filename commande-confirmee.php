<?php
// Confirmation : le ticket de la commande que le visiteur vient de passer.
// Le numéro vient de sa session, pas de l'adresse : personne d'autre ne peut l'afficher.
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/commande.php';

$numero = ouvrirSessionPanier() ? ($_SESSION['derniere_commande'] ?? null) : null;
$commande = $numero !== null ? commande($numero) : null;

if ($commande === null) {
    header('Location: ' . url('panier.php'), true, 303);
    exit;
}

$pageId      = 'commande';
$pageTitle   = 'Commande enregistrée';
$pageNoIndex = true;

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<main id="contenu">
    <section class="section">
        <div class="conteneur conteneur--etroit">
            <h1>Merci <?= e($commande['prenom']) ?>, votre commande est enregistrée.</h1>
            <p class="chapeau">Notez votre numéro de commande : avec votre e-mail, il permet de suivre vos colis.</p>

<?php require __DIR__ . '/includes/ticket.php'; ?>

            <p class="encadre"><strong>Démonstration :</strong> aucun paiement n'a été pris, aucun e-mail n'est
               envoyé et rien ne sera livré. Cette page est votre seul reçu.</p>

            <div class="groupe-boutons">
                <a class="bouton" href="<?= e(url('suivi.php?numero=' . rawurlencode($commande['numero']))) ?>">Suivre cette commande</a>
                <a class="bouton bouton--contour" href="<?= e(url('catalogue.php')) ?>">Continuer mes achats</a>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
