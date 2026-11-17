<?php
// Espace vendeur : les colis à préparer et à expédier

require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../includes/admin/vendeur.php';

$compte = exigerConnexion('vendeur');
$boutiqueId = (int) $compte['boutique_id']; // jamais une valeur venue de l'adresse ou du formulaire

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf();
    $colisId = filter_var($_POST['colis'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($colisId && expedierColis($colisId, $boutiqueId)) {
        flash('Colis marqué comme expédié : le client le voit dans son suivi.');
    } else {
        flash('Ce colis ne peut pas être marqué comme expédié.', 'erreur');
    }
    rediriger('admin/colis.php');
}

$colis = colisDeLaBoutique($boutiqueId);
$aPreparer = count(array_filter($colis, fn ($c) => $c['etat'] === 'en_preparation'));

$titreAdmin = 'Mes colis';
$pageAdmin  = 'colis';
require __DIR__ . '/../includes/admin/haut.php';
?>

<h1>Mes colis</h1>
<p><?= $aPreparer ?> colis à préparer pour <?= e($compte['boutique']) ?>.</p>

<?php if (!$colis): ?>
<p>Aucun colis pour le moment.</p>
<?php else: ?>
<ul class="liste-admin" role="list">
<?php foreach ($colis as $c): ?>
    <li class="carte-admin<?= $c['etat'] === 'expedie' ? ' carte-admin--traite' : '' ?>">
        <h2 class="carte-admin__titre"><span class="prix"><?= e($c['numero']) ?></span>
            <span class="prix"><?= e(prix((int) $c['sous_total'])) ?></span></h2>
        <p>
<?php if ($c['etat'] === 'expedie'): ?>
            <span class="pastille">Expédié</span> le <?= e(date('d/m/Y', strtotime($c['expedie_le']))) ?>
<?php else: ?>
            <span class="pastille pastille--promo">À préparer</span>
<?php endif; ?>
            · commandé le <?= e(date('d/m/Y à H\hi', strtotime($c['cree_le']))) ?>
        </p>
        <ul class="carte-admin__lignes" role="list">
<?php foreach ($c['lignes'] ?? [] as $l): ?>
            <li><?= (int) $l['quantite'] ?> × <?= e($l['nom']) ?></li>
<?php endforeach; ?>
        </ul>
        <address class="carte-admin__texte">
            <?= e($c['prenom'] . ' ' . $c['nom']) ?><br>
            <?= e($c['adresse']) ?><?= $c['complement'] !== '' ? '<br>' . e($c['complement']) : '' ?><br>
            <?= e($c['code_postal'] . ' ' . $c['ville']) ?>
<?php if ($c['telephone']): ?>
            <br><?= e(implode(' ', str_split($c['telephone'], 2))) ?>
<?php endif; ?>
        </address>
<?php if ($c['etat'] === 'en_preparation'): ?>
        <form method="post" action="<?= e(url('admin/colis.php')) ?>">
            <?= champCsrf() ?>
            <input type="hidden" name="colis" value="<?= (int) $c['id'] ?>">
            <button class="bouton bouton--petit" type="submit">Marquer comme expédié<span class="visuellement-cache"> : <?= e($c['numero']) ?></span></button>
        </form>
<?php endif; ?>
    </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin/bas.php'; ?>
