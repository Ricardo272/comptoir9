<?php
/**
 * Démonstration de l'espace de gestion, pour les visiteurs du portfolio.
 *
 * Deux boutons de la page de connexion ouvrent l'espace sans mot de passe, en admin
 * ou en vendeur (Octet & Cie). Chaque nouveau visiteur trouve le même tableau de
 * bord : à son entrée, si personne n'a utilisé la démo depuis DEMO_PAUSE_MINUTES,
 * les données reviennent à leur état de départ.
 *
 * Rien de tout cela n'existe si 'demo' vaut false dans config/site.php.
 */
require_once __DIR__ . '/commande.php';
require_once __DIR__ . '/admin/auth.php'; // DEMO_PAUSE_MINUTES

// Les deux comptes de démonstration (créés par sql/seed.sql)
const DEMO_COMPTES = ['admin' => 'demo-admin', 'boutique' => 'demo-boutique'];

function demoDisponible(): bool
{
    global $site;
    return !empty($site['demo']);
}

/** Quelqu'un a-t-il utilisé la démo pendant les DEMO_PAUSE_MINUTES dernières minutes ? */
function demoEnCours(): bool
{
    $requete = db()->prepare(
        'SELECT COUNT(*) FROM comptes WHERE demo = 1 AND derniere_activite > NOW() - INTERVAL :minutes MINUTE'
    );
    $requete->bindValue('minutes', DEMO_PAUSE_MINUTES, PDO::PARAM_INT);
    $requete->execute();
    return (int) $requete->fetchColumn() > 0;
}

/**
 * Remet les données dans l'état de départ de la démonstration :
 *   - produits : retour aux valeurs de produits_depart, produits ajoutés supprimés
 *     (les identifiants ne changent pas : les paniers en cours restent valables) ;
 *   - boutiques toutes en ligne ;
 *   - commandes et messages effacés, puis 4 commandes et 3 messages de démonstration.
 * Les vrais comptes (admin, vendeurs) ne sont pas touchés.
 */
function reinitialiserDemo(): void
{
    global $site;
    $pdo = db();

    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM commandes'); // colis et lignes partent avec (ON DELETE CASCADE)
        $pdo->exec('DELETE FROM messages');
        $pdo->exec('DELETE FROM produits WHERE id NOT IN (SELECT id FROM produits_depart)');
        $pdo->exec(
            'UPDATE produits p JOIN produits_depart d ON d.id = p.id
             SET p.boutique_id = d.boutique_id, p.rayon_id = d.rayon_id, p.slug = d.slug, p.nom = d.nom,
                 p.description = d.description, p.caracteristiques = d.caracteristiques, p.prix = d.prix,
                 p.prix_barre = d.prix_barre, p.stock = d.stock, p.delai_jours = d.delai_jours,
                 p.etat = d.etat, p.cree_le = d.cree_le'
        );
        $pdo->exec("UPDATE boutiques SET etat = 'active'");
        $pdo->exec('UPDATE comptes SET echecs = 0, bloque_jusqua = NULL WHERE demo = 1');
        $pdo->commit();
    } catch (Throwable $erreur) {
        $pdo->rollBack();
        throw $erreur;
    }

    // --- Commandes de démonstration : 2 à expédier, 2 déjà parties ----------------------
    // Toutes ont un colis Octet & Cie : le vendeur de démonstration a donc lui aussi
    // 2 colis à préparer et 2 colis expédiés.
    $commandes = [
        // [client, produits, il y a combien d'heures, expédiée ?]
        [['Camille', 'Martin', 'camille.martin@exemple.test', '0199009101', '3 rue des Essais', 'Bâtiment B', '69001', 'Lyon'],
         ['casque-sans-fil-octet-s2', 'cocotte-fonte-emaillee-24'], 2, false],
        [['Alex', 'Durand', 'alex.durand@exemple.test', '', '18 avenue des Tests', '', '69100', 'Villeurbanne'],
         ['chargeur-secteur-65w', 'jeu-les-traboules'], 6, false],
        [['Inès', 'Roux', 'ines.roux@exemple.test', '0199009102', '7 place de la Démo', '', '38000', 'Grenoble'],
         ['hub-usb-c-7-ports'], 30, true],
        [['Sam', 'Petit', 'sam.petit@exemple.test', '', '42 chemin des Exemples', 'Appartement 12', '74000', 'Annecy'],
         ['ecouteurs-sans-fil-octet-bud', 'parure-de-lit-lin-lave-240'], 76, true],
    ];
    $idParSlug = db()->prepare('SELECT id FROM produits WHERE slug = :slug');
    foreach ($commandes as [$client, $slugs, $heures, $expediee]) {
        $quantites = [];
        foreach ($slugs as $slug) {
            $idParSlug->execute(['slug' => $slug]);
            $quantites[(int) $idParSlug->fetchColumn()] = 1;
        }
        $total = calculerPanier(produitsParId(array_keys($quantites)), $quantites, $site['frais_port'])['total'];
        $coordonnees = array_combine(['prenom', 'nom', 'email', 'telephone', 'adresse', 'complement', 'code_postal', 'ville'], $client);
        $numero = enregistrerCommande($coordonnees, $quantites, $total, $site['frais_port'])['numero'] ?? null;
        if ($numero === null) {
            throw new RuntimeException('Démonstration : une commande de départ n\'a pas pu être enregistrée.');
        }

        // Dates dans le passé, pour un tableau de bord vraisemblable
        $dater = db()->prepare('UPDATE commandes SET cree_le = NOW() - INTERVAL :heures HOUR WHERE numero = :numero');
        $dater->bindValue('heures', $heures, PDO::PARAM_INT);
        $dater->bindValue('numero', $numero);
        $dater->execute();
        if ($expediee) {
            $expedier = db()->prepare(
                "UPDATE colis c JOIN commandes o ON o.id = c.commande_id
                 SET c.etat = 'expedie', c.expedie_le = o.cree_le + INTERVAL 20 HOUR
                 WHERE o.numero = :numero"
            );
            $expedier->execute(['numero' => $numero]);
        }
    }

    // --- Messages de démonstration : 2 à traiter, 1 traité ---------------------------------
    $message = db()->prepare(
        'INSERT INTO messages (nom, email, sujet, message, traite, cree_le)
         VALUES (:nom, :email, :sujet, :message, :traite, NOW() - INTERVAL :heures HOUR)'
    );
    foreach ([
        ['Alex Durand', 'alex.durand@exemple.test', 'Question sur ma livraison',
         "Bonjour,\nj'ai commandé un chargeur et un jeu ce matin. Les deux colis arriveront-ils le même jour ?\nMerci !", 0, 1],
        ['Atelier Graine', 'contact@atelier-graine.test', 'Vendre nos céramiques sur Comptoir 9',
         "Bonjour,\nnous sommes un atelier de céramique à Lyon et aimerions ouvrir un rayon chez vous. Comment se passe la rencontre avec les boutiques ?", 0, 26],
        ['Sam Petit', 'sam.petit@exemple.test', 'Merci pour la parure',
         "Bonjour,\ncolis bien reçu, la parure est superbe. Merci à Maison Lin pour le petit mot.", 1, 50],
    ] as [$nom, $email, $sujet, $texte, $traite, $heures]) {
        $message->bindValue('nom', $nom);
        $message->bindValue('email', $email);
        $message->bindValue('sujet', $sujet);
        $message->bindValue('message', $texte);
        $message->bindValue('traite', $traite, PDO::PARAM_INT);
        $message->bindValue('heures', $heures, PDO::PARAM_INT);
        $message->execute();
    }
}

/**
 * Ouvre une session de démonstration ('admin' ou 'boutique'), après une remise à
 * zéro si personne n'utilise la démo en ce moment. La session doit être démarrée
 * (includes/admin/auth.php). Renvoie false si la démo n'est pas disponible.
 */
function entrerEnDemo(string $role): bool
{
    if (!demoDisponible() || !isset(DEMO_COMPTES[$role])) {
        return false;
    }

    // shortcut: deux visiteurs qui entrent à la même seconde peuvent lancer deux remises
    // à zéro ; le résultat est le même, seule la numérotation des commandes change.
    if (!demoEnCours()) {
        reinitialiserDemo();
    }

    $requete = db()->prepare('SELECT id FROM comptes WHERE identifiant = :identifiant AND demo = 1');
    $requete->execute(['identifiant' => DEMO_COMPTES[$role]]);
    $id = $requete->fetchColumn();
    if ($id === false) {
        return false;
    }

    // Comme une vraie connexion : nouvel identifiant de session, nouveau jeton CSRF
    session_regenerate_id(true);
    $_SESSION['compte_id'] = (int) $id;
    $_SESSION['derniere_activite'] = time();
    unset($_SESSION['csrf']);
    db()->prepare('UPDATE comptes SET derniere_activite = NOW() WHERE id = :id')->execute(['id' => $id]);

    return true;
}
