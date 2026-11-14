<?php
// Commande : coordonnées et récapitulatif (GET), validation (POST).
// Paiement simulé : la commande est enregistrée, aucun paiement n'est demandé.
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/commande.php';

$panier = chargerPanier();

// Panier vide (ou pas de panier du tout) : rien à commander
if (!$panier['boutiques']) {
    header('Location: ' . url('panier.php'), true, 303);
    exit;
}

$valeurs = array_fill_keys(['prenom', 'nom', 'email', 'telephone', 'adresse', 'complement', 'code_postal', 'ville'], '');
$erreurs = [];
$erreurGenerale = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$valeurs, $erreurs] = verifierCoordonnees($_POST);
    $totalVu = filter_var($_POST['total_vu'] ?? null, FILTER_VALIDATE_INT);

    if (!requeteDuSite() || !jetonSessionValide($_POST['jeton'] ?? null)) {
        $erreurGenerale = 'Votre session a expiré. Vérifiez le récapitulatif et validez à nouveau.';
    } elseif ($panier['avis'] || $totalVu !== $panier['total']) {
        // Le panier a changé depuis l'affichage (stock, prix) : on le remontre avant de valider
        $erreurGenerale = 'Votre panier a changé depuis l\'affichage du récapitulatif. Vérifiez-le, puis validez à nouveau.';
    } elseif (!$erreurs) {
        $resultat = enregistrerCommande($valeurs, $panier['quantites'], $totalVu, $site['frais_port']);

        if (isset($resultat['numero'])) {
            // Panier vidé, numéro gardé en session pour afficher le ticket,
            // puis redirection : recharger la page ne repasse pas la commande.
            $_SESSION['panier'] = [];
            $_SESSION['derniere_commande'] = $resultat['numero'];
            header('Location: ' . url('commande-confirmee.php'), true, 303);
            exit;
        }

        // Un autre client a été plus rapide (dernier exemplaire) ou un prix a changé
        $panier = $resultat['panier'];
        $_SESSION['panier'] = $panier['quantites'];
        if (!$panier['boutiques']) {
            foreach ($panier['avis'] as $message) {
                ajouterAvis($message);
            }
            header('Location: ' . url('panier.php'), true, 303);
            exit;
        }
        $erreurGenerale = 'Votre panier a changé pendant la validation. Vérifiez le récapitulatif, puis validez à nouveau.';
    }

    if ($erreurs || $erreurGenerale !== null) {
        http_response_code(422); // « contenu non traitable » : le formulaire est à corriger
    }
}

$pageId      = 'commande';
$pageTitle   = $erreurs ? 'Erreur dans le formulaire — Commande' : 'Commande';
$pageNoIndex = true;

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';

// Un champ de texte du formulaire, avec son libellé et son message d'erreur
$champ = function (string $nom, string $libelle, string $type, string $autocomplete, bool $obligatoire = true, string $classe = '')
    use ($valeurs, $erreurs): void {
?>
                    <p class="champ <?= e($classe) ?>">
                        <label for="<?= e($nom) ?>"><?= e($libelle) ?><?= $obligatoire ? '' : ' <span class="champ__facultatif">(facultatif)</span>' ?></label>
                        <input id="<?= e($nom) ?>" type="<?= e($type) ?>" name="<?= e($nom) ?>" value="<?= e($valeurs[$nom]) ?>"
                               autocomplete="<?= e($autocomplete) ?>"<?= $obligatoire ? ' required' : '' ?><?= attributsChamp($nom, $erreurs) ?>>
                        <?= messageErreur($nom, $erreurs) ?>

                    </p>
<?php
};
?>

<main id="contenu">
    <section class="section">
        <div class="conteneur">
            <h1>Commande</h1>

            <p class="encadre"><strong>Démonstration :</strong> la commande sera enregistrée, mais aucun paiement
               n'est demandé et rien ne sera livré. N'indiquez pas de vraies coordonnées.</p>

<?php if ($erreurGenerale !== null): ?>
            <p class="avis avis--erreur" role="alert"><?= e($erreurGenerale) ?></p>
<?php endif; ?>
<?php if ($erreurs): ?>
            <!-- Résumé des erreurs, annoncé dès l'affichage ; chaque lien mène au champ à corriger -->
            <div class="avis avis--erreur" role="alert">
                <p><?= count($erreurs) ?> champ<?= count($erreurs) > 1 ? 's sont' : ' est' ?> à corriger :</p>
                <ul>
<?php foreach ($erreurs as $nom => $message): ?>
                    <li><a href="#<?= e($nom) ?>"><?= e($message) ?></a></li>
<?php endforeach; ?>
                </ul>
            </div>
<?php endif; ?>
<?php foreach ($panier['avis'] as $message): ?>
            <p class="avis" role="status"><?= e($message) ?></p>
<?php endforeach; ?>

            <div class="commande">
                <!-- novalidate : les messages viennent du serveur, les mêmes pour tous les navigateurs -->
                <form class="commande__formulaire" action="<?= e(url('commande.php')) ?>" method="post" novalidate>
                    <input type="hidden" name="jeton" value="<?= e(jetonSession()) ?>">
                    <input type="hidden" name="total_vu" value="<?= (int) $panier['total'] ?>">

                    <h2>Vos coordonnées</h2>
<?php
$champ('prenom', 'Prénom', 'text', 'given-name');
$champ('nom', 'Nom', 'text', 'family-name');
$champ('email', 'E-mail', 'email', 'email');
$champ('telephone', 'Téléphone', 'tel', 'tel', false);
?>
                    <h2>Adresse de livraison</h2>
<?php
$champ('adresse', 'Numéro et rue', 'text', 'address-line1');
$champ('complement', 'Complément d\'adresse', 'text', 'address-line2', false);
$champ('code_postal', 'Code postal', 'text', 'postal-code', true, 'champ--court');
$champ('ville', 'Ville', 'text', 'address-level2');
?>
                    <p class="commande__note">Vos coordonnées servent uniquement à traiter cette commande et sont
                       supprimées après 30 jours. Voir les <a href="<?= e(url('mentions-legales.php')) ?>">mentions légales</a>.</p>

                    <p><button class="bouton bouton--accent" type="submit">Valider la commande — <span class="prix"><?= e(prix($panier['total'])) ?></span></button></p>
                </form>

                <section class="ticket" aria-labelledby="recap-titre">
                    <h2 class="ticket__titre" id="recap-titre">Récapitulatif</h2>
<?php foreach ($panier['boutiques'] as $colis): ?>
                    <div class="ticket__colis">
                        <h3>Colis expédié par <?= e($colis['nom']) ?></h3>
                        <ul role="list">
<?php foreach ($colis['lignes'] as $l): ?>
                            <li><span><?= (int) $l['quantite'] ?> × <?= e($l['produit']['nom']) ?></span> <span class="prix"><?= e(prix($l['total'])) ?></span></li>
<?php endforeach; ?>
                            <li><span>Livraison</span> <span class="prix"><?= $colis['port'] ? e(prix($colis['port'])) : 'Offerte' ?></span></li>
                        </ul>
                    </div>
<?php endforeach; ?>
                    <dl class="ticket__lignes">
                        <div class="ticket__total"><dt>Total</dt><dd class="prix"><?= e(prix($panier['total'])) ?></dd></div>
                    </dl>
                    <p><a href="<?= e(url('panier.php')) ?>">Modifier le panier</a></p>
                </section>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
