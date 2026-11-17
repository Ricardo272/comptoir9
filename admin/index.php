<?php
// Espace de gestion : chacun arrive sur sa première page selon son rôle

require_once __DIR__ . '/../includes/admin/auth.php';

$compte = exigerConnexion();
rediriger($compte['role'] === 'admin' ? 'admin/commandes.php' : 'admin/colis.php');
