<?php
/**
 * Crée un compte de l'espace de gestion, change son mot de passe, ou le supprime.
 * Il n'y a pas de page d'inscription : les comptes se créent uniquement ici.
 *
 * Utilisation, depuis la racine du projet :
 *   php outils/creer-compte.php christophe --admin              compte administrateur
 *   php outils/creer-compte.php braquet --boutique=braquet      compte de la boutique Braquet
 *   php outils/creer-compte.php braquet --supprimer             supprime le compte
 * Si le compte existe déjà, son mot de passe, son rôle et sa boutique sont remplacés
 * (et un éventuel blocage est levé).
 *
 * Le mot de passe est demandé au clavier, sans s'afficher (12 caractères minimum).
 * Il n'est jamais passé en argument : il resterait dans l'historique du terminal.
 * Pour un usage automatisé, il peut être passé dans la variable d'environnement C9_MDP.
 */

if (PHP_SAPI !== 'cli') {
    exit('À lancer en ligne de commande.');
}

require_once __DIR__ . '/../includes/db.php';

const MDP_LONGUEUR_MIN = 12;

/**
 * Lit une ligne au clavier sans l'afficher.
 * Windows : PowerShell lit la saisie masquée (Read-Host -AsSecureString).
 * Linux (serveur) : on coupe l'écho du terminal le temps de la saisie.
 */
function lireMotDePasse(string $invite): string
{
    echo $invite;
    if (PHP_OS_FAMILY === 'Windows') {
        $commande = 'powershell -NoProfile -Command "$s = Read-Host -AsSecureString; '
                  . '[Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))"';
        $saisie = (string) shell_exec($commande);
    } else {
        system('stty -echo');
        $saisie = (string) fgets(STDIN);
        system('stty echo');
        echo "\n";
    }
    return rtrim($saisie, "\r\n");
}

// Connexion testée d'emblée : sans ce try, l'erreur afficherait une trace
// contenant le mot de passe de la base (avec Xdebug ou display_errors).
try {
    db();
} catch (Throwable $e) {
    fwrite(STDERR, "La base ne répond pas : vérifiez que MySQL est démarré.\n");
    exit(1);
}

$options     = array_filter(array_slice($argv, 1), fn ($a) => str_starts_with($a, '--'));
$arguments   = array_values(array_diff(array_slice($argv, 1), $options));
$identifiant = $arguments[0] ?? '';
$boutique    = null;
foreach ($options as $option) {
    if (str_starts_with($option, '--boutique=')) {
        $boutique = substr($option, strlen('--boutique='));
    }
}
$admin     = in_array('--admin', $options, true);
$supprimer = in_array('--supprimer', $options, true);

if (!preg_match('/^[a-z0-9._-]{3,40}$/i', $identifiant) || ($admin + ($boutique !== null) + $supprimer) !== 1) {
    fwrite(STDERR, "Utilisation :\n"
                 . "  php outils/creer-compte.php <identifiant> --admin\n"
                 . "  php outils/creer-compte.php <identifiant> --boutique=<slug de la boutique>\n"
                 . "  php outils/creer-compte.php <identifiant> --supprimer\n"
                 . "Identifiant : 3 à 40 caractères (lettres, chiffres, . _ -).\n");
    exit(1);
}

// --- Suppression ---
if ($supprimer) {
    $requete = db()->prepare('DELETE FROM comptes WHERE identifiant = :identifiant');
    $requete->execute(['identifiant' => $identifiant]);
    echo $requete->rowCount() ? "Compte « $identifiant » supprimé.\n" : "Aucun compte « $identifiant ».\n";
    exit(0);
}

// --- Boutique d'un vendeur ---
$boutiqueId = null;
if ($boutique !== null) {
    $requete = db()->prepare('SELECT id FROM boutiques WHERE slug = :slug');
    $requete->execute(['slug' => $boutique]);
    $boutiqueId = $requete->fetchColumn();
    if ($boutiqueId === false) {
        $slugs = implode(', ', db()->query('SELECT slug FROM boutiques ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        fwrite(STDERR, "Aucune boutique « $boutique ». Boutiques existantes : $slugs\n");
        exit(1);
    }
}

// --- Mot de passe ---
$motDePasse = getenv('C9_MDP');

if ($motDePasse === false || $motDePasse === '') {
    $motDePasse = lireMotDePasse("Mot de passe pour « $identifiant » (" . MDP_LONGUEUR_MIN . " caractères minimum) : ");
    $confirmation = lireMotDePasse('Confirmez le mot de passe : ');

    if ($motDePasse !== $confirmation) {
        fwrite(STDERR, "Les deux mots de passe ne correspondent pas. Rien n'a été modifié.\n");
        exit(1);
    }
}

// Une lettre accentuée peut être déformée par la console Windows : on la refuse
// plutôt que d'enregistrer un mot de passe différent de celui qui a été tapé.
if (!mb_check_encoding($motDePasse, 'UTF-8') || preg_match('/[^\x20-\x7E]/', $motDePasse)) {
    fwrite(STDERR, "Le mot de passe ne doit contenir que des lettres sans accent, des chiffres et des signes courants. Rien n'a été modifié.\n");
    exit(1);
}
if (strlen($motDePasse) < MDP_LONGUEUR_MIN) {
    fwrite(STDERR, 'Mot de passe trop court (' . MDP_LONGUEUR_MIN . " caractères minimum). Rien n'a été modifié.\n");
    exit(1);
}

// --- Enregistrement : création, ou mise à jour si le compte existe déjà ---
$requete = db()->prepare(
    'INSERT INTO comptes (identifiant, mot_de_passe, role, boutique_id) VALUES (:identifiant, :mdp, :role, :boutique)
     ON DUPLICATE KEY UPDATE mot_de_passe = VALUES(mot_de_passe), role = VALUES(role),
                             boutique_id = VALUES(boutique_id), echecs = 0, bloque_jusqua = NULL'
);
$requete->execute([
    'identifiant' => $identifiant,
    'mdp'         => password_hash($motDePasse, PASSWORD_DEFAULT), // jamais le mot de passe en clair
    'role'        => $admin ? 'admin' : 'vendeur',
    'boutique'    => $boutiqueId === null ? null : (int) $boutiqueId,
]);

$quoi = $admin ? 'administrateur' : "vendeur de la boutique « $boutique »";
// rowCount() : 1 = ligne créée, 2 = ligne existante mise à jour (convention MySQL)
echo $requete->rowCount() === 1
    ? "Compte « $identifiant » créé ($quoi).\n"
    : "Compte « $identifiant » mis à jour ($quoi), blocage éventuel levé.\n";
