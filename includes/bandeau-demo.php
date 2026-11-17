<?php
/**
 * Bandeau du mode démo : affiché tant que 'demo' vaut true dans config/site.php.
 */
if (empty($site['demo'])) {
    return;
}
?>
<p class="bandeau-demo">
    <strong>Site de démonstration</strong> : Comptoir 9 est une boutique fictive, aucun paiement n'est pris et rien n'est livré.
    <a href="<?= e(url('admin/connexion.php')) ?>">Essayer l'espace de gestion</a>
</p>
