<?php
// Espace vendeur : les produits de la boutique (liste, retrait et remise en vente)

require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../includes/admin/vendeur.php';

$compte = exigerConnexion('vendeur');
$boutiqueId = (int) $compte['boutique_id']; // jamais une valeur venue de l'adresse ou du formulaire

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf();
    $produitId = filter_var($_POST['produit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $enVente = ($_POST['en_vente'] ?? '') === '1';

    if ($produitId && changerEtatProduit($produitId, $boutiqueId, $enVente)) {
        flash($enVente ? 'Produit remis en vente.' : 'Produit retiré de la vente : il n\'apparaît plus sur le site.');
    } else {
        flash('Ce produit ne peut pas être modifié.', 'erreur');
    }
    rediriger('admin/produits.php');
}

$produits = produitsDeLaBoutique($boutiqueId);
$etiquettes = ['en_vente' => 'En vente', 'retire' => 'Retiré de la vente', 'masque' => 'Masqué par l\'administration'];

$titreAdmin = 'Mes produits';
$pageAdmin  = 'produits';
require __DIR__ . '/../includes/admin/haut.php';
?>

<h1>Mes produits</h1>
<p><?= count($produits) ?> produit<?= count($produits) > 1 ? 's' : '' ?> chez <?= e($compte['boutique']) ?>.</p>
<?php if ($compte['demo']): ?>
<p class="avis">En démonstration, on ne peut pas ajouter de produit ni changer les textes, car ils s'affichent
   sur le site public. Les prix, les stocks, les délais et la mise en vente se modifient librement.</p>
<?php else: ?>
<p><a class="bouton bouton--accent" href="<?= e(url('admin/produit.php')) ?>">Ajouter un produit</a></p>
<?php endif; ?>

<ul class="liste-admin" role="list">
<?php foreach ($produits as $p): ?>
    <li class="carte-admin<?= $p['etat'] !== 'en_vente' ? ' carte-admin--traite' : '' ?>">
        <h2 class="carte-admin__titre"><a href="<?= e(url('admin/produit.php?id=' . (int) $p['id'])) ?>"><?= e($p['nom']) ?></a></h2>
        <p><?= e($p['rayon']) ?> · <span class="prix"><?= e(prix((int) $p['prix'])) ?></span>
<?php if ($p['prix_barre']): ?>
           <s class="prix"><span class="visuellement-cache">Ancien prix : </span><?= e(prix((int) $p['prix_barre'])) ?></s>
<?php endif; ?>
           · stock : <?= (int) $p['stock'] ?></p>
        <p><span class="pastille<?= $p['etat'] === 'en_vente' ? ' pastille--promo' : '' ?>"><?= e($etiquettes[$p['etat']]) ?></span></p>
<?php if ($p['etat'] !== 'masque'): ?>
        <form method="post" action="<?= e(url('admin/produits.php')) ?>">
            <?= champCsrf() ?>
            <input type="hidden" name="produit" value="<?= (int) $p['id'] ?>">
            <input type="hidden" name="en_vente" value="<?= $p['etat'] === 'en_vente' ? '0' : '1' ?>">
            <button class="bouton bouton--contour bouton--petit" type="submit"><?= $p['etat'] === 'en_vente' ? 'Retirer de la vente' : 'Remettre en vente' ?><span class="visuellement-cache"> : <?= e($p['nom']) ?></span></button>
        </form>
<?php endif; ?>
    </li>
<?php endforeach; ?>
</ul>

<?php require __DIR__ . '/../includes/admin/bas.php'; ?>
