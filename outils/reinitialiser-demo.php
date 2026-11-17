<?php
/**
 * Remet la démonstration dans son état de départ : produits, boutiques, commandes
 * et messages (voir reinitialiserDemo() dans includes/demo.php).
 * Le site le fait déjà tout seul à l'entrée d'un visiteur en démo ; ce script sert
 * à le faire à la main, par exemple avant des captures d'écran.
 *
 * Utilisation, depuis la racine du projet :
 *   php outils/reinitialiser-demo.php
 *
 * Seulement en mode démo ('demo' => true dans config/site.php) : sur une vraie
 * boutique, ce script effacerait les commandes et les messages.
 */
if (PHP_SAPI !== 'cli') {
    exit('À lancer en ligne de commande.');
}

require_once __DIR__ . '/../includes/demo.php';

if (!demoDisponible()) {
    fwrite(STDERR, "Le site n'est pas en mode démo : rien n'a été fait.\n");
    exit(1);
}

reinitialiserDemo();
echo "Démonstration remise à zéro : " . db()->query('SELECT COUNT(*) FROM commandes')->fetchColumn() . ' commandes, '
   . db()->query('SELECT COUNT(*) FROM messages')->fetchColumn() . " messages.\n";
