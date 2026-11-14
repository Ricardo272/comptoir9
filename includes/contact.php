<?php
/**
 * Le formulaire de contact : contrôle, protections contre les robots, enregistrement.
 * Le message est seulement enregistré en base (lu dans l'administration) :
 * aucun e-mail n'est envoyé.
 */
require_once __DIR__ . '/formulaire.php';
require_once __DIR__ . '/entretien.php';

// Au-delà, le formulaire se ferme pour une heure. La limite vaut pour tout le
// site : on n'enregistre pas l'adresse IP des visiteurs, on ne peut donc pas
// compter par personne.
const MESSAGES_PAR_HEURE = 10;

/**
 * Contrôle le message. Renvoie [valeurs nettoyées, erreurs par champ, $spam].
 * $spam = true : champ piège rempli ou formulaire envoyé en moins de 3 secondes.
 * On fait alors semblant d'avoir réussi, sans rien enregistrer.
 */
function verifierMessage(array $post, ?int $maintenant = null): array
{
    $v = lireChamps($post, ['nom', 'email', 'sujet', 'message']);

    $jeton = verifierJeton(is_string($post['jeton'] ?? null) ? $post['jeton'] : '', $maintenant);
    if (($post['site_web'] ?? '') !== '' || $jeton === 'trop_rapide') {
        return [$v, [], true];
    }

    $erreurs = [];
    if ($jeton === 'invalide') {
        $erreurs['formulaire'] = 'Le formulaire a expiré. Vérifiez votre message puis envoyez-le à nouveau.';
    }
    if (mb_strlen($v['nom']) < 2 || mb_strlen($v['nom']) > 80) {
        $erreurs['nom'] = 'Indiquez votre nom (2 à 80 caractères).';
    }
    if (mb_strlen($v['email']) > 254 || !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $erreurs['email'] = 'Indiquez une adresse e-mail valide, par exemple prenom@exemple.fr.';
    }
    if (mb_strlen($v['sujet']) < 3 || mb_strlen($v['sujet']) > 120) {
        $erreurs['sujet'] = 'Indiquez le sujet de votre message (3 à 120 caractères).';
    }
    if (mb_strlen($v['message']) < 10 || mb_strlen($v['message']) > 3000) {
        $erreurs['message'] = 'Écrivez votre message (10 à 3000 caractères).';
    }

    return [$v, $erreurs, false];
}

/**
 * Enregistre un message contrôlé. Renvoie false si la limite horaire est atteinte.
 */
function enregistrerMessage(array $valeurs): bool
{
    $recents = (int) db()->query('SELECT COUNT(*) FROM messages WHERE cree_le > NOW() - INTERVAL 1 HOUR')->fetchColumn();
    if ($recents >= MESSAGES_PAR_HEURE) {
        return false;
    }

    db()->prepare('INSERT INTO messages (nom, email, sujet, message) VALUES (:nom, :email, :sujet, :message)')
        ->execute($valeurs);
    purgerAnciennesDonnees();

    return true;
}
