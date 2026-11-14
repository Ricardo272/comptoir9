<?php
/**
 * Carte d'une boutique. Attend $b, une ligne de boutiques() :
 * slug, nom, ville, specialite, accroche, nb_produits.
 */
?>
<article class="carte-boutique">
    <h3 class="carte-boutique__nom"><a href="<?= e(url('boutique.php?boutique=' . rawurlencode($b['slug']))) ?>"><?= e($b['nom']) ?></a></h3>
    <p class="carte-boutique__lieu"><?= e($b['specialite']) ?> · <?= e($b['ville']) ?></p>
    <p><?= e($b['accroche']) ?></p>
    <p class="carte-boutique__nombre"><?= (int) $b['nb_produits'] ?> produit<?= $b['nb_produits'] > 1 ? 's' : '' ?></p>
</article>
