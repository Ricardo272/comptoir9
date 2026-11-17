<?php
/**
 * Sécurité de l'espace de gestion : session, connexion, rôles, jeton CSRF, messages.
 * Inclus en tout premier par chaque page de admin/.
 * Repris de Sharp Line (lui-même repris de La Mangeoire), avec deux rôles :
 * 'admin' (tout le site) et 'vendeur' (sa seule boutique).
 * Les erreurs imprévues sont gérées par helpers.php (page 503).
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../formulaire.php';

const ADMIN_INACTIVITE      = 7200; // secondes : déconnexion après 2 h sans activité
const ADMIN_ECHECS_MAX      = 5;    // échecs de connexion d'affilée avant blocage
const ADMIN_BLOCAGE_MINUTES = 15;
// Empreinte de « mot de passe factice » : vérifiée quand l'identifiant n'existe pas
const ADMIN_EMPREINTE_FACTICE = '$2y$10$RSqeNre3FsvkRpvIkuwOc.5Nk5LenNg6UYGRJ7ykZxOvQFlvCjwJa';


/* ==========================================================================
   Session
   ========================================================================== */

/**
 * Démarre la session de l'espace de gestion, avec un cookie bien protégé.
 * Elle est distincte de celle du panier (autre nom, autre chemin).
 */
function demarrerSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Mode strict : PHP refuse un identifiant de session qu'il n'a pas créé lui-même
    ini_set('session.use_strict_mode', '1');

    session_name('c9_admin');
    session_set_cookie_params([
        'lifetime' => 0,                  // supprimé à la fermeture du navigateur
        'path'     => url('admin/'),      // envoyé SEULEMENT pour /admin/ : le site public n'en sait rien
        // HTTPS seulement, une fois en ligne (X-Forwarded-Proto : hébergeurs derrière un proxy)
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                      || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
        'httponly' => true,               // inaccessible au JavaScript
        'samesite' => 'Strict',           // jamais envoyé depuis un autre site
    ]);

    session_start();
}

/**
 * Le compte connecté, ou null :
 *   ['id', 'identifiant', 'role' => 'admin'|'vendeur', 'boutique_id', 'boutique']
 * Le rôle et la boutique sont relus en base à chaque page : un compte supprimé
 * ou modifié prend effet aussitôt, sans attendre la fin de la session.
 * Déconnecte au passage après 2 h d'inactivité.
 */
function compteConnecte(): ?array
{
    demarrerSession();

    if (empty($_SESSION['compte_id'])) {
        return null;
    }

    if (time() - ($_SESSION['derniere_activite'] ?? 0) > ADMIN_INACTIVITE) {
        deconnecter();
        return null;
    }

    $requete = db()->prepare(
        'SELECT c.id, c.identifiant, c.role, c.boutique_id, b.nom AS boutique
         FROM comptes c LEFT JOIN boutiques b ON b.id = c.boutique_id
         WHERE c.id = :id'
    );
    $requete->execute(['id' => $_SESSION['compte_id']]);
    $compte = $requete->fetch();

    if (!$compte) {
        deconnecter();
        return null;
    }

    $_SESSION['derniere_activite'] = time();
    return $compte;
}

/**
 * À appeler en tête de chaque page protégée : renvoie vers la connexion si besoin.
 * Avec $role, la page est réservée à ce rôle : un autre compte reçoit un refus (403).
 */
function exigerConnexion(?string $role = null): array
{
    global $site; // utilisé par la mise en page de la page de refus

    $compte = compteConnecte();

    if ($compte === null) {
        rediriger('admin/connexion.php');
    }

    if ($role !== null && $compte['role'] !== $role) {
        http_response_code(403);
        $titreAdmin = 'Accès refusé';
        $pageAdmin  = '';
        require __DIR__ . '/haut.php';
        echo '<h1>Accès refusé</h1>' . "\n"
           . '<p>Cette page est réservée à l\'administration de ' . e($site['nom']) . '.</p>' . "\n"
           . '<p><a href="' . e(url('admin/')) . '">Retour à votre espace</a></p>' . "\n";
        require __DIR__ . '/bas.php';
        exit;
    }

    return $compte;
}


/* ==========================================================================
   Connexion / déconnexion
   ========================================================================== */

/**
 * Tente une connexion. Retourne 'ok', 'incorrect' ou 'bloque'.
 */
function connecter(string $identifiant, string $motDePasse): string
{
    $requete = db()->prepare(
        'SELECT id, identifiant, mot_de_passe, echecs, bloque_jusqua FROM comptes WHERE identifiant = :identifiant'
    );
    $requete->execute(['identifiant' => $identifiant]);
    $compte = $requete->fetch();

    if (!$compte) {
        // Identifiant inconnu : on vérifie quand même une empreinte factice (fixe),
        // pour que la réponse prenne le même temps qu'avec un vrai compte.
        password_verify($motDePasse, ADMIN_EMPREINTE_FACTICE);
        return 'incorrect';
    }

    $maintenant = new DateTimeImmutable();

    // shortcut: le blocage vise le compte, pas le visiteur (pas d'IP stockée) : un inconnu
    // peut garder un compte bloqué ; passer à un délai par visiteur pour une vraie boutique.
    if ($compte['bloque_jusqua'] !== null && new DateTimeImmutable($compte['bloque_jusqua']) > $maintenant) {
        return 'bloque';
    }

    if (!password_verify($motDePasse, $compte['mot_de_passe'])) {
        // « echecs + 1 » calculé par MySQL en une seule opération : des essais
        // envoyés en même temps sont tous comptés (lire puis réécrire en perdrait).
        db()->prepare('UPDATE comptes SET echecs = echecs + 1 WHERE id = :id')->execute(['id' => $compte['id']]);

        $blocage = db()->prepare(
            'UPDATE comptes SET echecs = 0, bloque_jusqua = :fin WHERE id = :id AND echecs >= :max'
        );
        $blocage->execute([
            'fin' => $maintenant->modify('+' . ADMIN_BLOCAGE_MINUTES . ' minutes')->format('Y-m-d H:i:s'),
            'id'  => $compte['id'],
            'max' => ADMIN_ECHECS_MAX,
        ]);

        return $blocage->rowCount() > 0 ? 'bloque' : 'incorrect';
    }

    // Réussite. Si PHP recommande désormais un algorithme plus solide,
    // on recalcule l'empreinte (possible seulement maintenant : on a le mot de passe).
    if (password_needs_rehash($compte['mot_de_passe'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE comptes SET mot_de_passe = :mdp WHERE id = :id')->execute([
            'mdp' => password_hash($motDePasse, PASSWORD_DEFAULT),
            'id'  => $compte['id'],
        ]);
    }

    db()->prepare('UPDATE comptes SET echecs = 0, bloque_jusqua = NULL WHERE id = :id')
        ->execute(['id' => $compte['id']]);

    // Nouvel identifiant de session : une session obtenue AVANT la connexion
    // (par un tiers, par exemple) ne vaut plus rien après.
    session_regenerate_id(true);
    $_SESSION['compte_id'] = (int) $compte['id'];
    $_SESSION['derniere_activite'] = time();
    unset($_SESSION['csrf']); // nouveau jeton CSRF pour la session connectée

    return 'ok';
}

function deconnecter(): void
{
    demarrerSession();
    $_SESSION = [];

    // On supprime aussi le cookie dans le navigateur
    $parametres = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $parametres['path'],
        'secure'   => $parametres['secure'],
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_destroy();
}


/* ==========================================================================
   Jeton CSRF
   Un jeton aléatoire, gardé en session et glissé dans chaque formulaire.
   Un site malveillant peut pousser votre navigateur à envoyer un formulaire
   vers /admin/, mais il ne peut pas connaître ce jeton : la demande est refusée.
   ========================================================================== */

function jetonCsrf(): string
{
    demarrerSession();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function champCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(jetonCsrf()) . '">';
}

/**
 * Arrête tout si le jeton envoyé ne correspond pas à celui de la session.
 */
function verifierCsrf(): void
{
    demarrerSession();
    $envoye = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';

    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $envoye)) {
        http_response_code(400);
        exit('Demande refusée : la page a expiré. Revenez en arrière, rechargez la page et recommencez.');
    }
}


/* ==========================================================================
   Messages « flash » : affichés une seule fois, après une redirection
   ========================================================================== */

function flash(string $message, string $type = 'succes'): void
{
    demarrerSession();
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function lireFlash(): ?array
{
    demarrerSession();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Redirection après un envoi de formulaire (schéma Post/Redirect/Get).
 */
function rediriger(string $chemin): never
{
    header('Location: ' . url($chemin), true, 303);
    exit;
}
