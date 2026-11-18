<?php
/**
 * Début de chaque page : <head> et ouverture du <body>.
 *
 * Variables que la page peut définir AVANT d'inclure ce fichier :
 *   $pageId          identifiant de la page (« boutiques », « contact »…) pour le menu
 *   $pageTitle       titre de la page (le nom du site est ajouté automatiquement)
 *   $pageDescription description pour les moteurs de recherche
 *   $pageNoIndex     true pour demander aux moteurs de ne pas indexer la page
 *   $pageCanonique   adresse officielle si elle a des paramètres (ex. « catalogue.php?rayon=cuisine »)
 *   $pageJsonLd      données structurées schema.org de la page (tableau, voir jsonLd() dans helpers.php)
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/donnees/rayons.php';
require_once __DIR__ . '/panier.php';

// La session doit s'ouvrir avant tout affichage. Elle ne s'ouvre que si le
// visiteur a déjà un panier : une simple visite ne dépose aucun cookie.
$articlesPanier = nombreArticlesPanier();
// Produit ajouté juste avant : la fenêtre « Ajouté au panier » s'affiche une fois (footer.php)
$ajoutPanier = lireAjout();

// Les rayons du menu sont lus ici, AVANT tout affichage : si la base ne répond
// pas, la page 503 peut encore remplacer toute la page (voir helpers.php).
// Lus plus tard, au milieu du menu, une page déjà en partie envoyée resterait
// tronquée avec un code 200. Le résultat est gardé en mémoire pour le menu.
rayons();

$pageId          = $pageId ?? '';
$pageDescription = $pageDescription ?? $site['description'];

// Accueil : « Comptoir 9 — La place de marché des boutiques indépendantes »
// Autres pages : « Contact — Comptoir 9 »
$titreComplet = isset($pageTitle)
    ? $pageTitle . ' — ' . $site['nom']
    : $site['nom'] . ' — La place de marché des boutiques indépendantes';

// Adresse officielle de la page (sans paramètres) : « /index.php » devient « / »
$fichierPage  = basename($_SERVER['SCRIPT_NAME']);
$urlCanonique = urlAbsolue($pageCanonique ?? ($fichierPage === 'index.php' ? '' : $fichierPage));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titreComplet) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="#0F4C3A">
<?php if (!empty($pageNoIndex)): ?>
    <meta name="robots" content="noindex, nofollow">
<?php else: ?>
    <!-- Adresse officielle de la page, pour éviter les doublons dans Google -->
    <link rel="canonical" href="<?= e($urlCanonique) ?>">
<?php endif; ?>
    <link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">

    <!-- Open Graph : aperçu quand le lien est partagé (WhatsApp, LinkedIn…) -->
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:site_name" content="<?= e($site['nom']) ?>">
    <meta property="og:title" content="<?= e($titreComplet) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:url" content="<?= e($urlCanonique) ?>">
    <meta property="og:image" content="<?= e(urlAbsolue('assets/img/partage.jpg')) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= e($site['nom'] . ' — ' . $site['slogan']) ?>">
    <meta name="twitter:card" content="summary_large_image">
<?php if (!empty($pageJsonLd)): ?>

    <!-- Données structurées (schema.org) : ce que Google lit pour décrire la page -->
    <?= jsonLd($pageJsonLd) ?>

<?php endif; ?>

    <!-- Signale au CSS que JavaScript fonctionne. Sans cette classe, le menu
         reste affiché en liste : le site marche aussi sans JavaScript.
         Ce script est autorisé par son empreinte dans .htaccess (CSP) :
         ne pas le modifier sans recalculer l'empreinte. -->
    <script>document.documentElement.classList.add('js');</script>

    <!-- Polices visibles dès le premier écran : chargées en priorité -->
    <link rel="preload" href="<?= e(url('assets/fonts/bricolage-grotesque-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= e(url('assets/fonts/inter-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>

    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
</head>
<body>
