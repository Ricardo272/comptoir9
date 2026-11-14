<?php
// Suivi d'une commande, sans compte : numéro de commande + e-mail.
// Le formulaire est envoyé en POST : l'e-mail n'apparaît ainsi ni dans
// l'adresse, ni dans l'historique du navigateur, ni dans les journaux du serveur.
// Lecture seule : aucune donnée n'est modifiée, il n'y a donc pas de jeton.
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/commande.php';

// Le lien de la page de confirmation pré-remplit le numéro (jamais l'e-mail)
$valeurs = lireChamps($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET, ['numero', 'email']);
$valeurs['numero'] = mb_strtoupper($valeurs['numero']);
$commande = null;
$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (preg_match('/^C9-[2-9A-HJ-NP-Z]{6}$/', $valeurs['numero']) && $valeurs['email'] !== '') {
        $commande = commande($valeurs['numero'], $valeurs['email']);
    }
    if ($commande === null) {
        // Même message que le numéro soit inconnu ou que l'e-mail ne corresponde
        // pas : on ne révèle pas quels numéros existent.
        $erreur = 'Aucune commande ne correspond à ce numéro et à cet e-mail. Vérifiez-les, ou contactez le service client.';
        http_response_code(422);
    }
}

$pageId          = 'suivi';
$pageTitle       = 'Suivre une commande';
$pageDescription = 'Suivez l\'état de vos colis Comptoir 9 avec votre numéro de commande et votre e-mail.';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<main id="contenu">
    <section class="section">
        <div class="conteneur conteneur--etroit">
            <h1>Suivre une commande</h1>

<?php if ($commande !== null): ?>
            <p class="chapeau">Chaque boutique expédie son colis : ils peuvent arriver à des dates différentes.</p>
<?php require __DIR__ . '/includes/ticket.php'; ?>
            <p>Livraison à : <?= e($commande['prenom'] . ' ' . $commande['nom']) ?>,
               <?= e($commande['adresse']) ?><?= $commande['complement'] !== '' ? ', ' . e($commande['complement']) : '' ?>,
               <?= e($commande['code_postal'] . ' ' . $commande['ville']) ?>.</p>
            <p><a href="<?= e(url('suivi.php')) ?>">Suivre une autre commande</a></p>
<?php else: ?>
            <p class="chapeau">Indiquez le numéro reçu à la fin de votre commande et l'e-mail utilisé.</p>

<?php if ($erreur !== null): ?>
            <p class="avis avis--erreur" role="alert"><?= e($erreur) ?></p>
<?php endif; ?>

            <form class="formulaire" action="<?= e(url('suivi.php')) ?>" method="post">
                <p class="champ">
                    <label for="numero">Numéro de commande</label>
                    <input id="numero" type="text" name="numero" value="<?= e($valeurs['numero']) ?>" required
                           autocomplete="off" autocapitalize="characters" maxlength="9" aria-describedby="aide-numero">
                    <span class="champ__aide" id="aide-numero">Par exemple C9-7K4M2X</span>
                </p>
                <p class="champ">
                    <label for="email">E-mail de la commande</label>
                    <input id="email" type="email" name="email" value="<?= e($valeurs['email']) ?>" required autocomplete="email">
                </p>
                <p><button class="bouton" type="submit">Voir ma commande</button></p>
            </form>
<?php endif; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
