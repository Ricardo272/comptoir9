<?php
/**
 * Haut des pages de l'espace de gestion.
 *
 * Variables attendues :
 *   $titreAdmin   titre de la page
 *   $pageAdmin    identifiant de la page pour le menu (« commandes », « messages »…)
 *   $compte       compte connecté (compteConnecte()), ou absent sur la page de connexion
 */
$compte ??= null;

// Le menu dépend du rôle : un vendeur ne voit jamais les pages de l'admin
$liensAdmin = match ($compte['role'] ?? null) {
    'admin'   => [
        'commandes' => ['Commandes', 'admin/commandes.php'],
        'messages'  => ['Messages',  'admin/messages.php'],
    ],
    'vendeur' => [],
    default   => [],
};
$flash = lireFlash();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0F4C3A">
    <title><?= e($titreAdmin) ?> — Gestion — <?= e($site['nom']) ?></title>
    <link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="admin">
<a class="lien-evitement" href="#contenu">Aller au contenu</a>

<?php if ($compte): ?>
<header class="admin-entete">
    <div class="conteneur admin-entete__interieur">
        <p class="admin-entete__nom">
            <?= e($site['nom']) ?>
            <span class="admin-entete__role"><?= $compte['role'] === 'admin' ? 'Admin' : e($compte['boutique']) ?></span>
        </p>

        <nav class="admin-entete__nav" aria-label="Gestion">
            <ul class="admin-nav" role="list">
<?php foreach ($liensAdmin as $id => [$libelle, $chemin]): ?>
                <li><a href="<?= e(url($chemin)) ?>"<?= $id === $pageAdmin ? ' aria-current="page"' : '' ?>><?= e($libelle) ?></a></li>
<?php endforeach; ?>
                <li><a href="<?= e(url()) ?>">Voir le site</a></li>
            </ul>
        </nav>

        <!-- Déconnexion en POST avec jeton CSRF : un simple lien (GET) pourrait
             être déclenché par une image piégée sur un autre site -->
        <form class="admin-entete__deconnexion" method="post" action="<?= e(url('admin/deconnexion.php')) ?>">
            <?= champCsrf() ?>
            <button type="submit">Déconnexion<span class="visuellement-cache"> de <?= e($compte['identifiant']) ?></span></button>
        </form>
    </div>
</header>
<?php endif; ?>

<main id="contenu" class="conteneur admin-page">
<?php if ($flash): ?>
    <p class="avis<?= $flash['type'] === 'erreur' ? ' avis--erreur' : '' ?>" role="status"><?= e($flash['message']) ?></p>
<?php endif; ?>
