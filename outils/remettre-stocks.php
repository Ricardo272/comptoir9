<?php
/**
 * Remet les stocks des produits de démonstration à leur valeur de départ
 * (colonne stock_demo, remplie par sql/seed.sql). Sans cela, les visiteurs
 * finiraient par vider la boutique de démonstration.
 *
 * Utilisation, depuis la racine du projet :
 *   php outils/remettre-stocks.php
 * À lancer à la main, ou par une tâche planifiée de l'hébergeur.
 *
 * Seulement en mode démo ('demo' => true dans config/site.php) : sur une vraie
 * boutique, ce script effacerait les ventes des stocks.
 * Les commandes ne sont pas touchées. Un produit ajouté depuis (sans stock_demo)
 * non plus.
 */
if (PHP_SAPI !== 'cli') {
    exit('À lancer en ligne de commande.');
}

require_once __DIR__ . '/../includes/db.php';

if (empty($site['demo'])) {
    fwrite(STDERR, "Le site n'est pas en mode démo : rien n'a été fait.\n");
    exit(1);
}

$modifies = db()->exec('UPDATE produits SET stock = stock_demo WHERE stock_demo IS NOT NULL AND stock <> stock_demo');
echo $modifies . " produit(s) remis à leur stock de départ.\n";
