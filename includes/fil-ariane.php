<?php
/**
 * Fil d'Ariane. Attend $filAriane : une liste de [libellé, adresse],
 * de l'accueil à la page en cours. La dernière entrée n'est pas un lien.
 */
$dernier = array_key_last($filAriane);
?>
<nav class="fil-ariane" aria-label="Fil d'Ariane">
    <ol role="list">
<?php foreach ($filAriane as $i => [$libelle, $adresse]): ?>
<?php if ($i === $dernier): ?>
        <li aria-current="page"><?= e($libelle) ?></li>
<?php else: ?>
        <li><a href="<?= e($adresse) ?>"><?= e($libelle) ?></a></li>
<?php endif; ?>
<?php endforeach; ?>
    </ol>
</nav>
