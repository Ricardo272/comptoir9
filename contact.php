<?php
// Page « Contact » : coordonnées et formulaire de message.
// Le formulaire n'utilise ni session ni cookie : il est protégé par un champ
// piège, un jeton signé qui porte l'heure d'affichage, et une limite par heure.
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/contact.php';

$valeurs = ['nom' => '', 'email' => '', 'sujet' => '', 'message' => ''];
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$valeurs, $erreurs, $spam] = verifierMessage($_POST);

    if (!$spam && !$erreurs && !enregistrerMessage($valeurs)) {
        $erreurs['formulaire'] = 'Trop de messages ont été envoyés cette heure-ci. Réessayez un peu plus tard, ou appelez-nous.';
    }
    if (!$erreurs) {
        // Un robot reçoit la même réponse qu'un humain : il ne sait pas qu'il a été écarté.
        // Redirection : recharger la page ne renvoie pas le message.
        header('Location: ' . url('contact.php?envoye=1'), true, 303);
        exit;
    }
    http_response_code(422);
}

$envoye = isset($_GET['envoye']);

$pageId          = 'contact';
$pageTitle       = $erreurs ? 'Erreur dans le formulaire — Contact' : 'Contact';
$pageDescription = 'Joindre le service client de Comptoir 9 : téléphone, e-mail, adresse, horaires et formulaire de message.';
$pageCanonique   = 'contact.php';

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

            <h2 id="message-titre">Nous écrire</h2>

<?php if ($envoye): ?>
            <p class="avis" role="status"><strong>Votre message est bien enregistré.</strong>
               Sur ce site de démonstration, il ne recevra pas de réponse.</p>
<?php endif; ?>
<?php if ($erreurs): ?>
            <!-- Résumé des erreurs, annoncé dès l'affichage ; chaque lien mène au champ à corriger -->
            <div class="avis avis--erreur" role="alert">
                <p><?= count($erreurs) > 1 ? count($erreurs) . ' points sont à corriger :' : 'Un point est à corriger :' ?></p>
                <ul>
<?php foreach ($erreurs as $nom => $texte): ?>
                    <li><?php if ($nom !== 'formulaire'): ?><a href="#<?= e($nom) ?>"><?= e($texte) ?></a><?php else: ?><?= e($texte) ?><?php endif; ?></li>
<?php endforeach; ?>
                </ul>
            </div>
<?php endif; ?>

            <p>Une question sur une commande ou une livraison ? La page
               <a href="<?= e(url('aide.php')) ?>">Aide</a> répond aux plus fréquentes.</p>

            <!-- novalidate : les messages viennent du serveur, les mêmes pour tous les navigateurs -->
            <form class="formulaire" action="<?= e(url('contact.php')) ?>#message-titre" method="post" novalidate aria-labelledby="message-titre">
                <input type="hidden" name="jeton" value="<?= e(creerJeton()) ?>">

                <!-- Champ piège : invisible pour les humains, rempli par les robots -->
                <div class="champ-piege" aria-hidden="true">
                    <label for="site_web">Ne pas remplir ce champ</label>
                    <input type="text" id="site_web" name="site_web" tabindex="-1" autocomplete="off">
                </div>

                <p class="champ">
                    <label for="nom">Nom</label>
                    <input id="nom" type="text" name="nom" value="<?= e($valeurs['nom']) ?>" autocomplete="name" required<?= attributsChamp('nom', $erreurs) ?>>
                    <?= messageErreur('nom', $erreurs) ?>

                </p>
                <p class="champ">
                    <label for="email">E-mail</label>
                    <input id="email" type="email" name="email" value="<?= e($valeurs['email']) ?>" autocomplete="email" required<?= attributsChamp('email', $erreurs) ?>>
                    <?= messageErreur('email', $erreurs) ?>

                </p>
                <p class="champ">
                    <label for="sujet">Sujet</label>
                    <input id="sujet" type="text" name="sujet" value="<?= e($valeurs['sujet']) ?>" maxlength="120" required<?= attributsChamp('sujet', $erreurs) ?>>
                    <?= messageErreur('sujet', $erreurs) ?>

                </p>
                <p class="champ">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="6" maxlength="3000" required<?= attributsChamp('message', $erreurs) ?>><?= e($valeurs['message']) ?></textarea>
                    <?= messageErreur('message', $erreurs) ?>

                </p>

                <p class="commande__note">Votre nom, votre e-mail et votre message servent uniquement à vous répondre et sont
                   supprimés après 30 jours. Voir les <a href="<?= e(url('mentions-legales.php')) ?>">mentions légales</a>.</p>

                <p><button class="bouton" type="submit">Envoyer le message</button></p>
            </form>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
