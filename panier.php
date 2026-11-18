<?php
// Le panier : affichage (GET) et actions (POST : ajouter, modifier, retirer).
// Après une action réussie, on redirige (Post/Redirect/Get) : recharger la page ne rejoue
// pas l'action. Un ajout renvoie sur la page d'où il vient ; le reste, sur le panier.
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/panier.php';

$erreur = null;
$destination = null; // page de retour après un ajout (voir pageDeRetour())

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // Quantité : un entier de 0 à QUANTITE_MAX, sinon null
    $quantite = filter_var($_POST['quantite'] ?? null, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0, 'max_range' => QUANTITE_MAX]]);
    $quantite = $quantite === false ? null : $quantite;

    if (!requeteDuSite()) {
        $erreur = 'Cette demande ne vient pas du site : elle a été refusée.';
    } elseif ($action === 'ajouter') {
        // Premier ajout : le visiteur n'a pas encore de session, donc pas de jeton
        // de session. Le formulaire porte un jeton signé avec l'heure (voir formulaire.php).
        $slug = is_string($_POST['produit'] ?? null) ? $_POST['produit'] : '';
        $produit = $slug !== '' ? produit($slug) : null;

        if (!is_string($_POST['jeton'] ?? null) || verifierJeton($_POST['jeton'], null, 0) !== 'ok') {
            $erreur = 'Le formulaire a expiré. Revenez sur la fiche du produit et réessayez.';
        } elseif ($produit === null || $quantite === null || $quantite < 1) {
            $erreur = 'Ce produit ne peut pas être ajouté au panier.';
        } elseif ($produit['stock'] < 1) {
            $erreur = '« ' . $produit['nom'] . ' » est épuisé.';
        } else {
            ouvrirSessionPanier(true); // le seul endroit où le cookie est déposé
            $deja = $_SESSION['panier'][$produit['id']] ?? 0;
            $_SESSION['panier'][$produit['id']] = min($deja + $quantite, $produit['stock'], QUANTITE_MAX);

            // Le client reste sur sa page, où une fenêtre lui propose de continuer ou d'aller au panier
            $destination = pageDeRetour($_POST['retour'] ?? null);
            if ($destination !== null) {
                $_SESSION['ajout'] = $produit['nom'];
                // L'ancre ramène à la carte du produit : on ne perd pas sa place dans un long catalogue
                $destination .= '#produit-' . $produit['slug'];
            } else {
                ajouterAvis('« ' . $produit['nom'] . ' » a été ajouté à votre panier.');
            }
        }
    } elseif ($action === 'modifier' || $action === 'retirer') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

        if (!ouvrirSessionPanier() || !jetonSessionValide($_POST['jeton'] ?? null)) {
            $erreur = 'Votre session a expiré. Rechargez la page et réessayez.';
        } elseif (!$id || !isset($_SESSION['panier'][$id]) || ($action === 'modifier' && $quantite === null)) {
            $erreur = 'Cette ligne du panier ne peut pas être modifiée.';
        } elseif ($action === 'retirer' || $quantite === 0) {
            unset($_SESSION['panier'][$id]);
            ajouterAvis('Le produit a été retiré de votre panier.');
        } else {
            $_SESSION['panier'][$id] = $quantite; // ramenée au stock à l'affichage
        }
    } else {
        $erreur = 'Action inconnue.';
    }

    if ($erreur === null) {
        header('Location: ' . ($destination ?? url('panier.php')), true, 303);
        exit;
    }
    http_response_code(400);
}

$panier = chargerPanier();
$avis = array_merge(session_status() === PHP_SESSION_ACTIVE ? lireAvis() : [], $panier['avis']);

$pageId      = 'panier';
$pageTitle   = 'Votre panier';
$pageNoIndex = true;

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<main id="contenu">
    <section class="section">
        <div class="conteneur">
            <h1>Votre panier</h1>

<?php if ($erreur !== null): ?>
            <p class="avis avis--erreur" role="alert"><?= e($erreur) ?></p>
<?php endif; ?>
<?php if ($avis): ?>
            <!-- role="status" : annoncé par les lecteurs d'écran sans interrompre -->
            <div class="avis" role="status">
<?php foreach ($avis as $message): ?>
                <p><?= e($message) ?></p>
<?php endforeach; ?>
            </div>
<?php endif; ?>

<?php if (!$panier['boutiques']): ?>
            <p>Votre panier est vide.</p>
            <p><a class="bouton" href="<?= e(url('catalogue.php')) ?>">Parcourir le catalogue</a></p>
<?php else: ?>
            <p><?= $panier['articles'] ?> article<?= $panier['articles'] > 1 ? 's' : '' ?>,
               expédié<?= $panier['articles'] > 1 ? 's' : '' ?> en <?= count($panier['boutiques']) ?> colis :
               chaque boutique envoie ses propres produits.</p>

<?php foreach ($panier['boutiques'] as $colis): ?>
            <section class="colis" aria-labelledby="colis-<?= e($colis['slug']) ?>">
                <h2 class="colis__titre" id="colis-<?= e($colis['slug']) ?>">
                    Expédié par <a href="<?= e(url('boutique.php?boutique=' . rawurlencode($colis['slug']))) ?>"><?= e($colis['nom']) ?></a>
                </h2>

                <ul class="colis__lignes" role="list">
<?php foreach ($colis['lignes'] as $ligne): ?>
<?php $article = $ligne['produit']; ?>
                    <li class="ligne">
                        <?= imageProduit($article, 160, 'ligne__photo') ?>

                        <div class="ligne__texte">
                            <p class="ligne__nom"><a href="<?= e(url('produit.php?produit=' . rawurlencode($article['slug']))) ?>"><?= e($article['nom']) ?></a></p>
                            <p><span class="prix"><?= e(prix($article['prix'])) ?></span> l'unité</p>
                        </div>

                        <form class="ligne__quantite" action="<?= e(url('panier.php')) ?>" method="post">
                            <input type="hidden" name="action" value="modifier">
                            <input type="hidden" name="id" value="<?= (int) $article['id'] ?>">
                            <input type="hidden" name="jeton" value="<?= e(jetonSession()) ?>">
                            <label for="quantite-<?= (int) $article['id'] ?>">Quantité<span class="visuellement-cache"> de <?= e($article['nom']) ?></span></label>
                            <input id="quantite-<?= (int) $article['id'] ?>" type="number" name="quantite" inputmode="numeric"
                                   min="0" max="<?= min($article['stock'], QUANTITE_MAX) ?>" value="<?= (int) $ligne['quantite'] ?>">
                            <button class="bouton bouton--contour bouton--petit" type="submit">Mettre à jour</button>
                        </form>

                        <p class="ligne__total"><span class="visuellement-cache">Total de la ligne : </span><span class="prix"><?= e(prix($ligne['total'])) ?></span></p>

                        <form class="ligne__retirer" action="<?= e(url('panier.php')) ?>" method="post">
                            <input type="hidden" name="action" value="retirer">
                            <input type="hidden" name="id" value="<?= (int) $article['id'] ?>">
                            <input type="hidden" name="jeton" value="<?= e(jetonSession()) ?>">
                            <button class="lien-bouton" type="submit">Retirer<span class="visuellement-cache"> <?= e($article['nom']) ?></span></button>
                        </form>
                    </li>
<?php endforeach; ?>
                </ul>

                <dl class="colis__totaux">
                    <div><dt>Sous-total</dt><dd class="prix"><?= e(prix($colis['sous_total'])) ?></dd></div>
                    <div><dt>Livraison</dt><dd class="prix"><?= $colis['port'] ? e(prix($colis['port'])) : 'Offerte' ?></dd></div>
                </dl>
<?php if ($colis['manque']): ?>
                <p class="colis__astuce">Encore <span class="prix"><?= e(prix($colis['manque'])) ?></span> d'achat chez
                   <?= e($colis['nom']) ?> et la livraison de ce colis est offerte.</p>
<?php endif; ?>
            </section>
<?php endforeach; ?>

            <section class="ticket" aria-labelledby="ticket-titre">
                <h2 class="ticket__titre" id="ticket-titre">Récapitulatif</h2>
                <dl class="ticket__lignes">
                    <div><dt>Produits</dt><dd class="prix"><?= e(prix($panier['total_produits'])) ?></dd></div>
                    <div><dt>Livraison</dt><dd class="prix"><?= $panier['total_port'] ? e(prix($panier['total_port'])) : 'Offerte' ?></dd></div>
                    <div class="ticket__total"><dt>Total</dt><dd class="prix"><?= e(prix($panier['total'])) ?></dd></div>
                </dl>
                <p><a class="bouton bouton--accent" href="<?= e(url('commande.php')) ?>">Passer la commande</a></p>
                <p class="ticket__note">Site de démonstration : aucun paiement ne sera demandé.</p>
            </section>
<?php endif; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
