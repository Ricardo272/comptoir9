<?php
/**
 * Pied de page commun, puis fermeture du <body>.
 */
?>
<footer class="pied">
    <div class="conteneur pied__grille">

        <div class="pied__bloc">
            <p class="pied__nom"><?= e($site['nom']) ?></p>
            <p><?= e($site['slogan']) ?></p>
        </div>

        <div class="pied__bloc">
            <h2 class="pied__titre">Nous joindre</h2>
            <address>
                <?= e($site['adresse']['rue']) ?><br>
                <?= e($site['adresse']['code_postal'] . ' ' . $site['adresse']['ville']) ?><br>
                <a href="tel:<?= e(lienTelephone($site['telephone'])) ?>"><?= e($site['telephone']) ?></a><br>
                <a href="mailto:<?= e($site['email']) ?>"><?= e($site['email']) ?></a>
            </address>
        </div>

        <nav class="pied__bloc" aria-label="Rayons">
            <h2 class="pied__titre">Les rayons</h2>
            <ul class="pied__liste" role="list">
<?php foreach (rayons() as $rayon): ?>
                <li><a href="<?= e(urlRayon($rayon['slug'])) ?>"><?= e($rayon['nom']) ?></a></li>
<?php endforeach; ?>
            </ul>
        </nav>

        <nav class="pied__bloc" aria-label="Liens du pied de page">
            <h2 class="pied__titre">Le site</h2>
            <ul class="pied__liste" role="list">
<?php foreach ($site['navigation'] as $lien): ?>
                <li><a href="<?= e(url($lien['url'])) ?>"><?= e($lien['label']) ?></a></li>
<?php endforeach; ?>
                <li><a href="<?= e(url('suivi.php')) ?>">Suivre une commande</a></li>
                <li><a href="<?= e(url('cgv.php')) ?>">Conditions de vente</a></li>
                <li><a href="<?= e(url('mentions-legales.php')) ?>">Mentions légales</a></li>
            </ul>
        </nav>

    </div>

    <p class="pied__copyright">
        © <?= date('Y') ?> <?= e($site['nom']) ?> · Place de marché fictive, projet portfolio
    </p>
</footer>

<script src="<?= e(asset('assets/js/menu.js')) ?>" defer></script>
</body>
</html>
