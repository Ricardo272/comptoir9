<?php
// Administration : messages du formulaire de contact

require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../includes/admin/requetes.php';

$compte = exigerConnexion('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $traite = ($_POST['traite'] ?? '') === '1';

    if ($id && marquerMessage($id, $traite)) {
        flash($traite ? 'Message marqué comme traité.' : 'Message remis à traiter.');
    } else {
        flash('Ce message n\'existe plus.', 'erreur');
    }
    rediriger('admin/messages.php');
}

$messages = listeMessages();
$aTraiter = count(array_filter($messages, fn ($m) => !$m['traite']));

$titreAdmin = 'Messages';
$pageAdmin  = 'messages';
require __DIR__ . '/../includes/admin/haut.php';
?>

<h1>Messages</h1>
<p><?= $aTraiter ?> message<?= $aTraiter > 1 ? 's' : '' ?> à traiter. Les messages sont supprimés après 30 jours.</p>

<?php if (!$messages): ?>
<p>Aucun message pour le moment.</p>
<?php else: ?>
<ul class="liste-admin" role="list">
<?php foreach ($messages as $m): ?>
    <li class="carte-admin<?= $m['traite'] ? ' carte-admin--traite' : '' ?>">
        <h2 class="carte-admin__titre"><?= e($m['sujet']) ?></h2>
        <p><?= $m['traite'] ? '<span class="pastille">Traité</span>' : '<span class="pastille pastille--promo">À traiter</span>' ?>
           <?= e($m['nom']) ?> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>
           · <?= e(date('d/m/Y à H\hi', strtotime($m['cree_le']))) ?></p>
        <!-- e() puis nl2br() : les retours à la ligne sont gardés, aucune balise n'est interprétée -->
        <p class="carte-admin__texte"><?= nl2br(e($m['message'])) ?></p>
        <form method="post" action="<?= e(url('admin/messages.php')) ?>">
            <?= champCsrf() ?>
            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <input type="hidden" name="traite" value="<?= $m['traite'] ? '0' : '1' ?>">
            <button class="bouton bouton--contour bouton--petit" type="submit"><?= $m['traite'] ? 'Remettre à traiter' : 'Marquer comme traité' ?><span class="visuellement-cache"> : <?= e($m['sujet']) ?></span></button>
        </form>
    </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin/bas.php'; ?>
