<?php
/**
 * Fil d'Ariane. Attend $filAriane : une liste de [libellé, adresse],
 * de l'accueil à la page en cours. La dernière entrée n'est pas un lien.
 * Le même fil est donné à Google en données structurées (BreadcrumbList).
 */
$dernier = array_key_last($filAriane);

$elements = [];
foreach ($filAriane as $i => [$libelle, $adresse]) {
    $element = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $libelle];
    if ($i !== $dernier) {
        // Les adresses du fil partent de la racine (« /catalogue.php?… ») : Google les veut complètes
        $element['item'] = rtrim(configLocale()['url_absolue'], '/') . $adresse;
    }
    $elements[] = $element;
}
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
<?= jsonLd(['@type' => 'BreadcrumbList', 'itemListElement' => $elements]) ?>

