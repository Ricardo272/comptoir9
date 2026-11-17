<?php
// Administration : toutes les commandes (liste), ou une commande (?numero=C9-…)

require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../includes/admin/requetes.php';
require_once __DIR__ . '/../includes/commande.php';

$compte = exigerConnexion('admin');

// Une commande précise : le numéro est contrôlé avant d'aller en base
$numero = is_string($_GET['numero'] ?? null) ? mb_strtoupper(trim($_GET['numero'])) : '';
$commande = null;
if ($numero !== '') {
    $commande = preg_match('/^C9-[2-9A-HJ-NP-Z]{6}$/', $numero) ? commande($numero) : null;
    if ($commande === null) {
        http_response_code(404);
    }
}

$titreAdmin = $commande ? 'Commande ' . $commande['numero'] : 'Commandes';
$pageAdmin  = 'commandes';
require __DIR__ . '/../includes/admin/haut.php';
?>

<?php if ($numero !== ''): ?>
<p><a href="<?= e(url('admin/commandes.php')) ?>">← Toutes les commandes</a></p>
<?php if ($commande === null): ?>
<h1>Commande introuvable</h1>
<p>Aucune commande ne porte ce numéro. Elle a peut-être été supprimée (les commandes sont gardées 30 jours).</p>
<?php else: ?>
<h1>Commande <span class="prix"><?= e($commande['numero']) ?></span></h1>

<section class="carte-admin" aria-labelledby="client-titre">
    <h2 id="client-titre">Client</h2>
    <p><?= e($commande['prenom'] . ' ' . $commande['nom']) ?><br>
       <a href="mailto:<?= e($commande['email']) ?>"><?= e($commande['email']) ?></a>
<?php if ($commande['telephone']): ?>
       <br><?= e(implode(' ', str_split($commande['telephone'], 2))) ?>
<?php endif; ?>
    </p>
    <p><?= e($commande['adresse']) ?><?= $commande['complement'] !== '' ? '<br>' . e($commande['complement']) : '' ?><br>
       <?= e($commande['code_postal'] . ' ' . $commande['ville']) ?></p>
</section>

<?php require __DIR__ . '/../includes/ticket.php'; ?>
<?php endif; ?>

<?php else: ?>
<?php $commandes = listeCommandes(); ?>
<h1>Commandes</h1>
<p><?= count($commandes) ?> commande<?= count($commandes) > 1 ? 's' : '' ?> des 30 derniers jours.</p>

<?php if (!$commandes): ?>
<p>Aucune commande pour le moment.</p>
<?php else: ?>
<ul class="liste-admin" role="list">
<?php foreach ($commandes as $c): ?>
    <li class="carte-admin">
        <p class="carte-admin__titre"><a href="<?= e(url('admin/commandes.php?numero=' . rawurlencode($c['numero']))) ?>" class="prix"><?= e($c['numero']) ?></a>
           <span class="prix"><?= e(prix((int) $c['total'])) ?></span></p>
        <p><?= e($c['prenom'] . ' ' . $c['nom']) ?> · <?= e(date('d/m/Y à H\hi', strtotime($c['cree_le']))) ?></p>
        <p>
<?php if ((int) $c['nb_expedies'] === (int) $c['nb_colis']): ?>
            <span class="pastille pastille--promo">Expédiée</span>
<?php elseif ((int) $c['nb_expedies'] > 0): ?>
            <span class="pastille">Partiellement expédiée</span>
<?php else: ?>
            <span class="pastille">En préparation</span>
<?php endif; ?>
            <?= (int) $c['nb_expedies'] ?> colis expédié<?= $c['nb_expedies'] > 1 ? 's' : '' ?> sur <?= (int) $c['nb_colis'] ?>
        </p>
    </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin/bas.php'; ?>
