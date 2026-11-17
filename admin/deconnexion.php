<?php
// Espace de gestion : déconnexion (uniquement par formulaire POST, avec jeton CSRF)

require_once __DIR__ . '/../includes/admin/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rediriger('admin/');
}

verifierCsrf();

// Un visiteur de démonstration qui se déconnecte libère la démo : le suivant la retrouvera
// dans son état de départ sans attendre 20 minutes (voir includes/demo.php)
$compte = compteConnecte();
if ($compte && $compte['demo']) {
    db()->prepare('UPDATE comptes SET derniere_activite = NULL WHERE id = :id')->execute(['id' => $compte['id']]);
}

deconnecter();
rediriger('admin/connexion.php');
