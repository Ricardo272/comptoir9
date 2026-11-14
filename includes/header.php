<?php
/**
 * En-tête commun : lien d'évitement, logo, recherche, panier,
 * menu des rayons, puis le bandeau du mode démo.
 */
?>
<a class="lien-evitement" href="#contenu">Aller au contenu</a>

<header class="entete">
    <div class="conteneur entete__barre">
        <a class="logo" href="<?= e(url()) ?>" aria-label="<?= e($site['nom']) ?>, accueil">
            <span class="logo__mot">Comptoir</span><span class="logo__neuf">9</span>
        </a>

        <!-- La recherche est un simple formulaire GET : elle marche sans JavaScript -->
        <form class="recherche" action="<?= e(url('catalogue.php')) ?>" method="get" role="search">
            <label class="visuellement-cache" for="recherche-q">Rechercher un produit</label>
            <input class="recherche__champ" id="recherche-q" type="search" name="q" placeholder="Rechercher un produit" maxlength="80">
            <button class="recherche__bouton" type="submit">Rechercher</button>
        </form>

        <a class="entete__panier" href="<?= e(url('panier.php')) ?>"<?= $pageId === 'panier' ? ' aria-current="page"' : '' ?>>Panier</a>
    </div>

    <div class="conteneur entete__menu">
        <!-- Bouton du menu mobile. Il doit rester JUSTE AVANT la navigation :
             le CSS s'appuie sur cet ordre (sélecteur « + »). -->
        <button class="nav-bouton" type="button" aria-expanded="false" aria-controls="nav-liste">
            <span class="nav-bouton__icone" aria-hidden="true"></span>
            <span>Rayons et menu</span>
        </button>

        <nav class="nav" aria-label="Navigation principale">
            <!-- role="list" : Safari retire la sémantique de liste quand on supprime les puces en CSS -->
            <ul class="nav__liste" id="nav-liste" role="list">
<?php foreach (rayons() as $rayon): ?>
<?php if ($rayon['sous_rayons']): ?>
                <li class="nav__rayon">
                    <!-- <details> : le sous-menu s'ouvre au clic ou au clavier, sans JavaScript.
                         name="rayons" : ouvrir un rayon referme les autres. -->
                    <details class="rayon" name="rayons">
                        <summary class="nav__lien"><?= e($rayon['nom']) ?></summary>
                        <ul class="rayon__liste" role="list">
                            <li><a class="rayon__lien rayon__lien--tout" href="<?= e(urlRayon($rayon['slug'])) ?>">Tout le rayon <?= e($rayon['nom']) ?></a></li>
<?php foreach ($rayon['sous_rayons'] as $sousRayon): ?>
                            <li><a class="rayon__lien" href="<?= e(urlRayon($sousRayon['slug'])) ?>"><?= e($sousRayon['nom']) ?></a></li>
<?php endforeach; ?>
                        </ul>
                    </details>
                </li>
<?php else: ?>
                <li class="nav__rayon"><a class="nav__lien nav__lien--affaires" href="<?= e(urlRayon($rayon['slug'])) ?>"><?= e($rayon['nom']) ?></a></li>
<?php endif; ?>
<?php endforeach; ?>
<?php foreach ($site['navigation'] as $i => $lien): ?>
                <li class="nav__secondaire<?= $i === 0 ? ' nav__secondaire--premier' : '' ?>"><a class="nav__lien" href="<?= e(url($lien['url'])) ?>"<?= $lien['id'] === $pageId ? ' aria-current="page"' : '' ?>><?= e($lien['label']) ?></a></li>
<?php endforeach; ?>
            </ul>
        </nav>
    </div>
</header>
<?php require __DIR__ . '/bandeau-demo.php'; ?>
