<?php
/**
 * Les 3 engagements, en trois colonnes (accueil et page À propos).
 */
?>
<ul class="engagements" role="list">
<?php foreach ($site['engagements'] as $engagement): ?>
    <li class="engagements__item">
        <h3><?= e($engagement['titre']) ?></h3>
        <p><?= e($engagement['texte']) ?></p>
    </li>
<?php endforeach; ?>
</ul>
