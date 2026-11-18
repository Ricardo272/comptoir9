<?php
/**
 * Fenêtre « Ajouté au panier », affichée une fois après un ajout (voir panier.php).
 * Attend $ajoutPanier (nom du produit) et $articlesPanier (head.php).
 *
 * Sans JavaScript : le serveur l'envoie déjà ouverte ; « Continuer mes achats » la ferme
 * grâce à <form method="dialog">, que le navigateur gère seul.
 * Avec JavaScript (menu.js) : elle devient modale (fond assombri, Échap pour fermer).
 */
?>
<dialog class="fenetre-ajout" open aria-labelledby="fenetre-ajout-titre">
    <h2 class="fenetre-ajout__titre" id="fenetre-ajout-titre">Ajouté à votre panier</h2>
    <p class="fenetre-ajout__produit"><?= e($ajoutPanier) ?></p>
    <p class="fenetre-ajout__total">Votre panier contient <?= $articlesPanier ?> article<?= $articlesPanier > 1 ? 's' : '' ?>.</p>
    <div class="fenetre-ajout__actions">
        <form method="dialog">
            <button class="bouton bouton--contour" type="submit" autofocus>Continuer mes achats</button>
        </form>
        <a class="bouton bouton--accent" href="<?= e(url('panier.php')) ?>">Voir le panier</a>
    </div>
</dialog>
