<?php
// Consignes pour les robots des moteurs de recherche, servies à l'adresse
// /robots.txt (voir .htaccess). Écrit en PHP pour que l'adresse du plan du site
// vienne de config.local.php : rien à modifier ici en changeant d'hébergeur.
require_once __DIR__ . '/includes/helpers.php';

header('Content-Type: text/plain; charset=utf-8');
?>
User-agent: *
# Pages propres à chaque visiteur : rien à indexer
Disallow: /panier.php
Disallow: /commande.php
Disallow: /commande-confirmee.php
Disallow: /admin/

Sitemap: <?= urlAbsolue('sitemap.xml') ?>

