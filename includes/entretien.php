<?php
/**
 * Entretien des données personnelles.
 */
require_once __DIR__ . '/db.php';

// Durée de conservation annoncée dans les mentions légales
const CONSERVATION_JOURS = 30;

/**
 * Supprime les commandes (avec leurs colis et leurs lignes) et les messages de
 * contact de plus de 30 jours. Appelée après chaque commande et chaque message :
 * pas besoin de tâche planifiée chez l'hébergeur.
 * shortcut: rien n'est supprimé tant que personne ne commande ni n'écrit,
 * passer à une tâche planifiée si le site reste longtemps sans activité.
 */
function purgerAnciennesDonnees(): void
{
    try {
        foreach (['commandes', 'messages'] as $table) {
            $requete = db()->prepare("DELETE FROM $table WHERE cree_le < NOW() - INTERVAL :jours DAY");
            $requete->bindValue('jours', CONSERVATION_JOURS, PDO::PARAM_INT);
            $requete->execute();
        }
    } catch (Throwable $erreur) {
        // Le nettoyage peut attendre la prochaine fois : on ne bloque pas la commande ou le message
        error_log('[Comptoir 9] nettoyage des données : ' . $erreur->getMessage());
    }
}
