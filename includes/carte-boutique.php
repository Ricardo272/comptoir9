<?php
/**
 * Carte d'une boutique. Attend $b : slug, nom, ville, rayon, accroche.
 */
?>
<article class="carte-boutique">
    <h3 class="carte-boutique__nom"><a href="<?= e(url('boutique.php?boutique=' . rawurlencode($b['slug']))) ?>"><?= e($b['nom']) ?></a></h3>
    <p class="carte-boutique__lieu"><?= e($b['rayon']) ?> · <?= e($b['ville']) ?></p>
    <p><?= e($b['accroche']) ?></p>
</article>
