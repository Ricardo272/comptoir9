<?php
// Espace de gestion : entrée en démonstration, par les boutons de la page de connexion
// (uniquement en POST, avec le jeton CSRF de cette page)

require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../includes/demo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !demoDisponible()) {
    rediriger('admin/connexion.php');
}

verifierCsrf();
$role = is_string($_POST['role'] ?? null) ? $_POST['role'] : '';

if (!entrerEnDemo($role)) {
    flash('La démonstration n\'est pas disponible.', 'erreur');
    rediriger('admin/connexion.php');
}

rediriger('admin/');
