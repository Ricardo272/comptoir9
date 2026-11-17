<?php
// Page « Mentions légales »
$pageId          = 'mentions';
$pageTitle       = 'Mentions légales';
$pageDescription = 'Mentions légales, données personnelles et cookies du site Comptoir 9.';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';

// L'hébergeur dépend de l'hébergement, pas du code : il est dans config.local.php
$hebergeur = configLocale()['hebergeur'] ?? null;
?>

<main id="contenu">
    <section class="section">
        <div class="conteneur conteneur--etroit prose">
            <h1>Mentions légales</h1>

            <div class="encadre">
                <p>
                    <strong><?= e($site['nom']) ?> est une place de marché fictive.</strong>
                    Ce site a été réalisé par Ricardo R. dans le cadre de son portfolio de développeur web.
                    L'entreprise, les personnes, les boutiques, les produits, l'adresse et le numéro de
                    téléphone sont inventés : aucun paiement n'est pris et rien n'est livré.
                </p>
            </div>

            <h2>Éditeur du site</h2>
            <p>
                Ricardo R., développeur web.<br>
                Directeur de la publication : Ricardo R.
            </p>

            <h2>Hébergement</h2>
<?php if ($hebergeur): ?>
            <p>
                <?= e($hebergeur['nom']) ?><br>
                <?= e($hebergeur['adresse']) ?><br>
                <a href="<?= e($hebergeur['site']) ?>" rel="noopener"><?= e($hebergeur['site']) ?></a>
            </p>
<?php else: ?>
            <p>Ce site n'est pas encore hébergé en ligne.</p>
<?php endif; ?>

            <h2>Propriété intellectuelle</h2>
            <p>
                Les textes, la mise en page et le code du site sont la propriété de leur auteur.
                Les polices Bricolage Grotesque, Inter et DM Mono sont distribuées sous licence libre
                SIL Open Font License 1.1.
            </p>

            <h2>Données personnelles</h2>
            <p>
                Une commande recueille votre prénom, votre nom, votre e-mail, votre adresse de livraison
                et, si vous le souhaitez, votre téléphone. Un message de contact recueille votre nom,
                votre e-mail, le sujet et votre message.
            </p>
            <ul>
                <li><strong>Pourquoi :</strong> uniquement pour traiter votre commande ou répondre à votre message.</li>
                <li><strong>Sur quelle base :</strong> l'exécution de votre commande ou de votre demande
                    (article 6.1.b du RGPD).</li>
                <li><strong>Qui y a accès :</strong> Comptoir 9 et, pour une commande, les boutiques qui
                    expédient vos produits : chacune voit seulement ses propres colis, avec le nom, l'adresse
                    de livraison et le téléphone, sans votre e-mail. Vos données ne sont ni vendues ni
                    transmises à d'autres tiers.
                    Le site n'enregistre pas votre adresse IP avec vos commandes ni avec vos messages.</li>
                <li><strong>Combien de temps :</strong> trente jours, puis elles sont supprimées automatiquement.</li>
            </ul>
            <p>
                Vous pouvez à tout moment demander l'accès à vos données, leur rectification ou leur
                suppression, ou vous opposer à leur traitement, en écrivant à
                <a href="mailto:<?= e($site['email']) ?>"><?= e($site['email']) ?></a>.
                Vous pouvez aussi adresser une réclamation à la CNIL
                (<a href="https://www.cnil.fr" rel="noopener">cnil.fr</a>).
            </p>

            <h2>Cookies</h2>
            <p>
                Tant que vous ne faites que visiter le site, il ne dépose <strong>aucun cookie</strong>.
                Il n'utilise aucun outil de mesure d'audience ni de publicité, et les polices sont
                hébergées sur le site lui-même : aucune donnée n'est transmise à un tiers.
            </p>
            <p>
                Un seul cookie est déposé, <strong>au moment où vous ajoutez un premier produit au panier</strong> :
                il sert à retenir le contenu de ce panier jusqu'à la commande. La CNIL range ce type de
                traceur parmi ceux qui ne sont pas soumis au consentement
                (<a href="https://www.cnil.fr/fr/cookies-et-autres-traceurs/que-dit-la-loi" rel="noopener">Cookies et traceurs : que dit la loi ?</a>),
                c'est pourquoi le site n'affiche pas de bandeau.
            </p>
            <p>
                L'espace vendeur et l'administration, réservés aux boutiques et à Comptoir 9, utilisent
                un cookie de session strictement nécessaire à la connexion.
            </p>

            <p class="prose__maj">Dernière mise à jour : novembre 2026.</p>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
