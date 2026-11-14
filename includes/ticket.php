<?php
/**
 * Ticket d'une commande : colis par colis, puis les totaux.
 * Attend $commande, une commande renvoyée par commande() (includes/commande.php).
 * Sert à la confirmation et au suivi.
 */
?>
<div class="ticket ticket--large">
    <p class="ticket__numero">Commande <strong class="prix"><?= e($commande['numero']) ?></strong></p>
    <p>Passée le <?= e(date('d/m/Y à H\hi', strtotime($commande['cree_le']))) ?> · <strong><?= e(etatCommande($commande)) ?></strong></p>

<?php foreach ($commande['colis'] as $colis): ?>
    <section class="ticket__colis">
        <h2 class="ticket__sous-titre">Colis expédié par <?= e($colis['boutique']) ?></h2>
        <p>
<?php if ($colis['etat'] === 'expedie'): ?>
            <span class="pastille pastille--promo">Expédié</span> le <?= e(date('d/m/Y', strtotime($colis['expedie_le']))) ?>
<?php else: ?>
            <span class="pastille">En préparation</span>
<?php endif; ?>
        </p>
        <ul role="list">
<?php foreach ($colis['lignes'] as $l): ?>
            <li><span><?= (int) $l['quantite'] ?> × <?= e($l['nom']) ?></span> <span class="prix"><?= e(prix($l['quantite'] * $l['prix'])) ?></span></li>
<?php endforeach; ?>
            <li><span>Livraison</span> <span class="prix"><?= $colis['port'] ? e(prix($colis['port'])) : 'Offerte' ?></span></li>
        </ul>
    </section>
<?php endforeach; ?>

    <dl class="ticket__lignes">
        <div><dt>Produits</dt><dd class="prix"><?= e(prix($commande['total_produits'])) ?></dd></div>
        <div><dt>Livraison</dt><dd class="prix"><?= $commande['total_port'] ? e(prix($commande['total_port'])) : 'Offerte' ?></dd></div>
        <div class="ticket__total"><dt>Total</dt><dd class="prix"><?= e(prix($commande['total'])) ?></dd></div>
    </dl>
</div>
