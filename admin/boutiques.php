<?php
// Administration : les boutiques (suspendre, réactiver)

require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../includes/admin/requetes.php';

$compte = exigerConnexion('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf();
    $boutiqueId = filter_var($_POST['boutique'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $active = ($_POST['active'] ?? '') === '1';

    if ($boutiqueId && changerEtatBoutique($boutiqueId, $active)) {
        flash($active ? 'Boutique réactivée : elle et ses produits sont de nouveau en ligne.'
                      : 'Boutique suspendue : elle et ses produits ont disparu du site.');
    } else {
        flash('Cette boutique ne peut pas être modifiée.', 'erreur');
    }
    rediriger('admin/boutiques.php');
}

$boutiques = listeBoutiques();

$titreAdmin = 'Boutiques';
$pageAdmin  = 'boutiques';
require __DIR__ . '/../includes/admin/haut.php';
?>

<h1>Boutiques</h1>
<p>Une boutique suspendue disparaît du site avec tous ses produits. Ses commandes et ses données restent en base,
   et son vendeur garde l'accès à son espace.</p>
<p>Les comptes des vendeurs se créent en ligne de commande : <code>php outils/creer-compte.php &lt;identifiant&gt; --boutique=&lt;slug&gt;</code>.</p>

<ul class="liste-admin" role="list">
<?php foreach ($boutiques as $b): ?>
    <li class="carte-admin<?= $b['etat'] === 'suspendue' ? ' carte-admin--traite' : '' ?>">
        <h2 class="carte-admin__titre"><?= e($b['nom']) ?></h2>
        <p><span class="pastille<?= $b['etat'] === 'active' ? ' pastille--promo' : '' ?>"><?= $b['etat'] === 'active' ? 'En ligne' : 'Suspendue' ?></span>
           <?= e($b['ville']) ?></p>
        <p><?= (int) $b['en_vente'] ?> produit<?= $b['en_vente'] > 1 ? 's' : '' ?> en vente sur <?= (int) $b['produits'] ?>
           · <?= (int) $b['comptes'] ?> compte<?= $b['comptes'] > 1 ? 's' : '' ?> vendeur</p>
<?php if ($b['etat'] === 'active'): ?>
        <p><a href="<?= e(url('boutique.php?boutique=' . rawurlencode($b['slug']))) ?>">Voir sur le site</a></p>
<?php endif; ?>
        <form method="post" action="<?= e(url('admin/boutiques.php')) ?>">
            <?= champCsrf() ?>
            <input type="hidden" name="boutique" value="<?= (int) $b['id'] ?>">
            <input type="hidden" name="active" value="<?= $b['etat'] === 'active' ? '0' : '1' ?>">
            <button class="bouton bouton--contour bouton--petit" type="submit"><?= $b['etat'] === 'active' ? 'Suspendre' : 'Réactiver' ?><span class="visuellement-cache"> : <?= e($b['nom']) ?></span></button>
        </form>
    </li>
<?php endforeach; ?>
</ul>

<?php require __DIR__ . '/../includes/admin/bas.php'; ?>
