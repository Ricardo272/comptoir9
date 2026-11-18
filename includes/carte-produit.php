<?php
/**
 * Carte d'un produit. Attend $p : slug, nom, boutique, prix, prix_barre, stock.
 *
 * Toute la carte est cliquable, mais elle ne contient qu'UN lien (le nom) :
 * le CSS étend sa zone de clic à la carte. Le bouton d'ajout passe au-dessus.
 * L'ajout renvoie sur la page en cours (champ « retour », vérifié par pageDeRetour()).
 */
$epuise = $p['stock'] <= 0;
?>
<article class="carte-produit<?= $epuise ? ' carte-produit--epuise' : '' ?>" id="produit-<?= e($p['slug']) ?>">
    <div class="carte-produit__photo">
        <?= imageProduit($p, 600) ?>

<?php if ($epuise): ?>
        <p class="pastille pastille--epuise">Épuisé</p>
<?php elseif ($p['prix_barre']): ?>
        <p class="pastille pastille--promo">Promo</p>
<?php elseif ($p['stock'] <= 3): ?>
        <p class="pastille">Plus que <?= (int) $p['stock'] ?></p>
<?php endif; ?>
    </div>
    <h3 class="carte-produit__nom"><a href="<?= e(url('produit.php?produit=' . rawurlencode($p['slug']))) ?>"><?= e($p['nom']) ?></a></h3>
    <p class="carte-produit__boutique">Vendu par <?= e($p['boutique']) ?></p>
    <p class="carte-produit__prix">
        <span class="etiquette"><span class="etiquette__fond prix"><?= e(prix($p['prix'])) ?></span></span>
<?php if ($p['prix_barre']): ?>
        <!-- <s> n'est pas annoncé par les lecteurs d'écran : on le dit en toutes lettres -->
        <s class="prix prix--barre"><span class="visuellement-cache">Ancien prix : </span><?= e(prix($p['prix_barre'])) ?></s>
<?php endif; ?>
    </p>
<?php if (!$epuise): ?>
    <form class="carte-produit__ajout" action="<?= e(url('panier.php')) ?>" method="post">
        <input type="hidden" name="action" value="ajouter">
        <input type="hidden" name="produit" value="<?= e($p['slug']) ?>">
        <input type="hidden" name="quantite" value="1">
        <input type="hidden" name="jeton" value="<?= e(creerJeton()) ?>">
        <input type="hidden" name="retour" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
        <button class="bouton bouton--accent bouton--petit" type="submit">Ajouter au panier<span class="visuellement-cache"> : <?= e($p['nom']) ?></span></button>
    </form>
<?php else: ?>
    <!-- Bouton inactif, comme sur la fiche : les prix restent alignés d'une carte à l'autre -->
    <p class="carte-produit__ajout"><button class="bouton bouton--petit" type="button" disabled>Épuisé</button></p>
<?php endif; ?>
</article>
