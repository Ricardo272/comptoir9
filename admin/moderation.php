<?php
// Administration : modération des produits (masquer, démasquer)

require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../includes/admin/requetes.php';

$compte = exigerConnexion('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf();
    $produitId = filter_var($_POST['produit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $masquer = ($_POST['masquer'] ?? '') === '1';

    if ($produitId && masquerProduit($produitId, $masquer)) {
        flash($masquer ? 'Produit masqué : il n\'apparaît plus sur le site, et sa boutique ne peut pas le remettre en vente.'
                       : 'Produit démasqué : il est de nouveau en vente.');
    } else {
        flash('Ce produit ne peut pas être modifié.', 'erreur');
    }
    // On revient à la même boutique dans la liste
    rediriger('admin/moderation.php' . (isset($_POST['ancre']) && preg_match('/^b\d+$/', $_POST['ancre']) ? '#' . $_POST['ancre'] : ''));
}

$parBoutique = [];
foreach (listeProduitsModeration() as $p) {
    $parBoutique[$p['boutique']][] = $p;
}
$etiquettes = ['en_vente' => 'En vente', 'retire' => 'Retiré par la boutique', 'masque' => 'Masqué'];
$numero = 0;

$titreAdmin = 'Produits';
$pageAdmin  = 'moderation';
require __DIR__ . '/../includes/admin/haut.php';
?>

<h1>Produits</h1>
<p>Masquer un produit le retire du site sans le supprimer. Sa boutique le voit « masqué » et ne peut pas le remettre en vente.</p>

<nav aria-label="Boutiques">
    <ul class="sous-rayons" role="list">
<?php foreach (array_keys($parBoutique) as $i => $nom): ?>
        <li><a href="#b<?= $i ?>"><?= e($nom) ?></a></li>
<?php endforeach; ?>
    </ul>
</nav>

<?php foreach ($parBoutique as $nom => $produits): ?>
<section class="moderation" id="b<?= $numero ?>" aria-labelledby="titre-b<?= $numero ?>">
    <h2 id="titre-b<?= $numero ?>"><?= e($nom) ?><?= $produits[0]['etat_boutique'] === 'suspendue' ? ' <span class="pastille">Boutique suspendue</span>' : '' ?></h2>
    <ul class="liste-moderation" role="list">
<?php foreach ($produits as $p): ?>
        <li class="liste-moderation__ligne<?= $p['etat'] === 'masque' ? ' liste-moderation__ligne--masque' : '' ?>">
            <span class="liste-moderation__nom">
<?php if ($p['etat'] === 'en_vente' && $p['etat_boutique'] === 'active'): ?>
                <a href="<?= e(url('produit.php?produit=' . rawurlencode($p['slug']))) ?>"><?= e($p['nom']) ?></a>
<?php else: ?>
                <?= e($p['nom']) ?>
<?php endif; ?>
                <span class="pastille"><?= e($etiquettes[$p['etat']]) ?></span>
            </span>
            <form method="post" action="<?= e(url('admin/moderation.php')) ?>">
                <?= champCsrf() ?>
                <input type="hidden" name="produit" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="masquer" value="<?= $p['etat'] === 'masque' ? '0' : '1' ?>">
                <input type="hidden" name="ancre" value="b<?= $numero ?>">
                <button class="lien-bouton" type="submit"><?= $p['etat'] === 'masque' ? 'Démasquer' : 'Masquer' ?><span class="visuellement-cache"> : <?= e($p['nom']) ?></span></button>
            </form>
        </li>
<?php endforeach; ?>
    </ul>
</section>
<?php $numero++; ?>
<?php endforeach; ?>

<?php require __DIR__ . '/../includes/admin/bas.php'; ?>
