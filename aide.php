<?php
// Page « Aide » : livraison, retours, paiement, questions fréquentes
$pageId          = 'aide';
$pageTitle       = 'Aide';
$pageDescription = 'Livraison, retours, paiement et questions fréquentes sur Comptoir 9.';

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';

$port = $site['frais_port'];

// Questions fréquentes : [question, réponse]
$questions = [
    ['Faut-il créer un compte pour commander ?',
     'Non. Vous commandez avec votre nom, votre e-mail et votre adresse de livraison, sans mot de passe à retenir.'],
    ['Comment suivre ma commande ?',
     'Avec le numéro reçu à la fin de la commande et votre e-mail, sur la page « Suivre une commande ». Vous y voyez l\'état de chaque colis.'],
    ['Pourquoi vais-je recevoir plusieurs colis ?',
     'Chaque boutique expédie elle-même ses produits. Une commande passée auprès de trois boutiques arrive donc en trois colis, chacun avec son propre délai.'],
    ['Comment sont calculés les frais de port ?',
     'Un forfait de ' . prix($port['par_boutique']) . ' par boutique, offert quand vos achats dans cette boutique atteignent ' . prix($port['offerts_des']) . '. Le détail est affiché dans le panier avant de valider.'],
    ['Un produit est épuisé : reviendra-t-il ?',
     'C\'est la boutique qui gère son stock. Si le produit revient, il réapparaît en vente sur sa fiche.'],
    ['Qui contacter en cas de problème ?',
     'Le service client de Comptoir 9, quel que soit le vendeur : nous faisons le lien avec la boutique.'],
    ['Puis-je vraiment acheter sur ce site ?',
     'Non. Comptoir 9 est un site de démonstration : aucun paiement n\'est pris et rien n\'est livré.'],
];
?>

<main id="contenu">
    <section class="section">
        <div class="conteneur conteneur--etroit prose">
            <h1>Aide</h1>

            <h2 id="livraison">Livraison</h2>
            <ul>
                <li>Frais de port : <span class="prix"><?= e(prix($port['par_boutique'])) ?></span> par boutique,
                    offerts dès <span class="prix"><?= e(prix($port['offerts_des'])) ?></span> d'achat dans la même boutique.</li>
                <li>Délai : chaque fiche produit indique sous combien de jours la boutique expédie.</li>
                <li>Un colis par boutique : chaque vendeur prépare et expédie ses propres produits.</li>
            </ul>

            <h2 id="retours">Retours</h2>
            <p>Pour un achat en ligne, vous disposez de <strong>quatorze jours</strong> pour changer d'avis,
               sans avoir à donner de raison (article L. 221-18 du Code de la consommation). Le
               remboursement intervient au plus tard quatorze jours après que nous avons été informés
               de votre décision.</p>

            <h2 id="paiement">Paiement</h2>
            <p>Comptoir 9 est un site de démonstration : la commande est enregistrée, mais
               <strong>aucun paiement n'est demandé</strong> et aucun moyen de paiement n'est saisi.</p>

            <h2 id="questions">Questions fréquentes</h2>
            <!-- <details> : chaque réponse s'ouvre au clic ou au clavier, sans JavaScript -->
            <div class="faq">
<?php foreach ($questions as [$question, $reponse]): ?>
                <details class="faq__question">
                    <summary><?= e($question) ?></summary>
                    <p><?= e($reponse) ?></p>
                </details>
<?php endforeach; ?>
            </div>

            <p>Vous ne trouvez pas la réponse ? <a href="<?= e(url('contact.php')) ?>">Contactez le service client</a>.</p>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
