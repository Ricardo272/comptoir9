<?php
// Page d'erreur 404, affichée par Apache pour toute adresse inconnue (voir .htaccess).

// Indispensable : sans ce code, le navigateur et Google recevraient « 200 OK »
// et prendraient cette page d'erreur pour une vraie page du site.
http_response_code(404);

$pageTitle   = 'Page introuvable';
$pageNoIndex = true;

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<main id="contenu" class="erreur">
    <section class="section">
        <div class="conteneur erreur__contenu">
            <!-- Le grand « 404 » est décoratif : le vrai titre est le h1 -->
            <p class="erreur__code" aria-hidden="true">404</p>
            <h1>Ce rayon n'existe pas.</h1>
            <p>La page que vous cherchez n'existe pas, ou plus. Le reste du comptoir est ouvert.</p>
            <p><a class="bouton" href="<?= e(url()) ?>">Retour à l'accueil</a></p>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
