<?php
// Espace de gestion : connexion (admin et vendeurs)

require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../includes/demo.php';

// Déjà connecté : direction son espace
if (compteConnecte()) {
    rediriger('admin/');
}

$identifiant = '';
$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Le jeton CSRF protège aussi la connexion : sans lui, un autre site pourrait
    // vous connecter à SON compte à votre insu (« login CSRF »).
    verifierCsrf();

    $identifiant = is_string($_POST['identifiant'] ?? null) ? trim($_POST['identifiant']) : '';
    $motDePasse  = is_string($_POST['mot_de_passe'] ?? null) ? $_POST['mot_de_passe'] : '';

    $resultat = connecter($identifiant, $motDePasse);

    if ($resultat === 'ok') {
        rediriger('admin/');
    }

    // Même message pour un identifiant inconnu ou un mauvais mot de passe.
    // Seul « Trop de tentatives » révèle qu'un compte existe (assumé : voir auth.php).
    $erreur = $resultat === 'bloque'
        ? 'Trop de tentatives. Réessayez dans ' . ADMIN_BLOCAGE_MINUTES . ' minutes.'
        : 'Identifiant ou mot de passe incorrect.';
}

$titreAdmin = 'Connexion';
$pageAdmin  = '';
require __DIR__ . '/../includes/admin/haut.php';
?>

<section class="connexion" aria-labelledby="titre-connexion">
    <p class="connexion__logo"><span class="logo__mot">Comptoir</span><span class="logo__neuf">9</span></p>
    <h1 id="titre-connexion" class="connexion__titre">Espace de gestion</h1>
<?php if (demoDisponible()): ?>
    <div class="essai">
        <h2 class="essai__titre">Essayer l'espace de gestion</h2>
        <p>Sans mot de passe, avec des commandes et des messages de démonstration. Vous pouvez tout essayer :
           le visiteur suivant retrouvera le même point de départ.</p>
        <div class="groupe-boutons">
<?php foreach (['admin' => 'En tant qu\'administrateur', 'boutique' => 'En tant que boutique (Octet & Cie)'] as $roleDemo => $libelleDemo): ?>
            <form method="post" action="<?= e(url('admin/demo.php')) ?>">
                <?= champCsrf() ?>

                <input type="hidden" name="role" value="<?= e($roleDemo) ?>">
                <button class="bouton bouton--accent" type="submit"><?= e($libelleDemo) ?></button>
            </form>
<?php endforeach; ?>
        </div>
    </div>

    <h2 class="essai__titre">Accès de l'équipe et des boutiques</h2>
<?php else: ?>
    <p>Réservé à l'équipe de <?= e($site['nom']) ?> et aux boutiques du comptoir.</p>
<?php endif; ?>

<?php if ($erreur): ?>
    <p class="avis avis--erreur" role="alert"><?= e($erreur) ?></p>
<?php endif; ?>

    <form class="formulaire" method="post" action="<?= e(url('admin/connexion.php')) ?>">
        <?= champCsrf() ?>

        <p class="champ">
            <label for="identifiant">Identifiant</label>
            <input type="text" id="identifiant" name="identifiant" value="<?= e($identifiant) ?>"
                   required autocomplete="username" autocapitalize="none" spellcheck="false">
        </p>

        <p class="champ">
            <label for="mot_de_passe">Mot de passe</label>
            <input type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="current-password">
        </p>

        <p><button class="bouton" type="submit">Se connecter</button></p>
    </form>
</section>

<?php require __DIR__ . '/../includes/admin/bas.php'; ?>
