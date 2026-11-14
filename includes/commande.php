<?php
/**
 * La commande : contrôle des coordonnées, enregistrement, lecture pour le
 * ticket de confirmation et le suivi. Tout le SQL des commandes est ici.
 */
require_once __DIR__ . '/panier.php';

/**
 * Contrôle les coordonnées saisies. Renvoie [valeurs nettoyées, erreurs par champ].
 * Le contrôle se fait ici, côté serveur : les attributs HTML du formulaire
 * (required, type="email"…) ne sont qu'un confort, on peut les contourner.
 */
function verifierCoordonnees(array $post): array
{
    $v = lireChamps($post, ['prenom', 'nom', 'email', 'telephone', 'adresse', 'complement', 'code_postal', 'ville']);
    $erreurs = [];
    $longueur = fn (string $champ) => mb_strlen($v[$champ]);

    if ($longueur('prenom') < 2 || $longueur('prenom') > 60) {
        $erreurs['prenom'] = 'Indiquez votre prénom (2 à 60 caractères).';
    }
    if ($longueur('nom') < 2 || $longueur('nom') > 60) {
        $erreurs['nom'] = 'Indiquez votre nom (2 à 60 caractères).';
    }
    if ($longueur('email') > 254 || !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $erreurs['email'] = 'Indiquez une adresse e-mail valide, par exemple prenom@exemple.fr.';
    }

    // Téléphone facultatif. Accepte « 06 12 34 56 78 », « 06.12.34.56.78 », « +33 6 12 34 56 78 » ;
    // enregistré en 10 chiffres.
    if ($v['telephone'] !== '') {
        $chiffres = preg_replace('/^\+33/', '0', preg_replace('/[\s.\-]/', '', $v['telephone']));
        if (preg_match('/^0[1-9]\d{8}$/', $chiffres)) {
            $v['telephone'] = $chiffres;
        } else {
            $erreurs['telephone'] = 'Indiquez un numéro à 10 chiffres, par exemple 06 12 34 56 78, ou laissez le champ vide.';
        }
    }

    if ($longueur('adresse') < 5 || $longueur('adresse') > 160) {
        $erreurs['adresse'] = 'Indiquez le numéro et le nom de la rue.';
    }
    if ($longueur('complement') > 160) {
        $erreurs['complement'] = 'Le complément d\'adresse est trop long (160 caractères au plus).';
    }
    if (!preg_match('/^\d{5}$/', $v['code_postal'])) {
        $erreurs['code_postal'] = 'Indiquez un code postal à 5 chiffres.';
    }
    if ($longueur('ville') < 2 || $longueur('ville') > 80) {
        $erreurs['ville'] = 'Indiquez votre ville.';
    }

    return [$v, $erreurs];
}

/**
 * Numéro de commande : « C9- » suivi de 6 caractères tirés au hasard.
 * Sans 0, O, 1 ni I, qu'on confond en les recopiant. Environ un milliard de
 * numéros possibles ; le suivi demande en plus l'e-mail de la commande.
 * shortcut: pas de nouveau tirage si le numéro existe déjà (une chance sur un
 * milliard : la commande échoue alors en page 503), à ajouter si le volume grandit.
 */
function numeroCommande(): string
{
    $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $numero = 'C9-';
    for ($i = 0; $i < 6; $i++) {
        $numero .= $alphabet[random_int(0, 31)]; // random_int : hasard imprévisible
    }
    return $numero;
}

/**
 * Enregistre la commande. Tout se passe dans une transaction : soit tout est
 * écrit (commande, colis, lignes, stocks), soit rien.
 *
 * Avant d'écrire, les produits sont relus ET verrouillés : si un prix ou un
 * stock a changé depuis l'affichage du récapitulatif, rien n'est enregistré
 * et le visiteur revoit son panier corrigé. $totalVu est le total qu'il avait
 * sous les yeux en validant.
 *
 * Renvoie ['numero' => 'C9-…'] si la commande est passée,
 * sinon ['panier' => le panier recalculé] pour le lui remontrer.
 */
function enregistrerCommande(array $coordonnees, array $quantites, int $totalVu, array $fraisPort): array
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $panier = calculerPanier(produitsParId(array_keys($quantites), true), $quantites, $fraisPort);

        if (!$panier['boutiques'] || $panier['avis'] || $panier['total'] !== $totalVu) {
            $pdo->rollBack();
            return ['panier' => $panier];
        }

        $commande = $pdo->prepare(
            'INSERT INTO commandes (numero, prenom, nom, email, telephone, adresse, complement, code_postal, ville,
                                    total_produits, total_port, total)
             VALUES (:numero, :prenom, :nom, :email, :telephone, :adresse, :complement, :code_postal, :ville,
                     :total_produits, :total_port, :total)'
        );
        $numero = numeroCommande();
        $commande->execute([
            'numero'         => $numero,
            'prenom'         => $coordonnees['prenom'],
            'nom'            => $coordonnees['nom'],
            'email'          => $coordonnees['email'],
            'telephone'      => $coordonnees['telephone'] !== '' ? $coordonnees['telephone'] : null,
            'adresse'        => $coordonnees['adresse'],
            'complement'     => $coordonnees['complement'],
            'code_postal'    => $coordonnees['code_postal'],
            'ville'          => $coordonnees['ville'],
            'total_produits' => $panier['total_produits'],
            'total_port'     => $panier['total_port'],
            'total'          => $panier['total'],
        ]);
        $commandeId = (int) $pdo->lastInsertId();

        $colis = $pdo->prepare('INSERT INTO colis (commande_id, boutique_id, sous_total, port) VALUES (?, ?, ?, ?)');
        $ligne = $pdo->prepare('INSERT INTO commande_lignes (colis_id, produit_id, nom, prix, quantite) VALUES (?, ?, ?, ?, ?)');
        // « AND stock >= ? » : même si le verrou manquait, le stock ne peut pas devenir négatif
        $stock = $pdo->prepare('UPDATE produits SET stock = stock - ? WHERE id = ? AND stock >= ?');

        foreach ($panier['boutiques'] as $boutiqueId => $boutique) {
            $colis->execute([$commandeId, $boutiqueId, $boutique['sous_total'], $boutique['port']]);
            $colisId = (int) $pdo->lastInsertId();

            foreach ($boutique['lignes'] as $l) {
                $ligne->execute([$colisId, $l['produit']['id'], $l['produit']['nom'], $l['produit']['prix'], $l['quantite']]);
                $stock->execute([$l['quantite'], $l['produit']['id'], $l['quantite']]);
                if ($stock->rowCount() !== 1) {
                    throw new RuntimeException('Stock insuffisant pour le produit ' . $l['produit']['id']);
                }
            }
        }

        $pdo->commit();
        return ['numero' => $numero];
    } catch (Throwable $erreur) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erreur; // page 503 (voir helpers.php) : rien n'a été enregistré
    }
}

/**
 * Une commande avec ses colis et leurs lignes, ou null.
 * Avec $email, la commande n'est renvoyée que si l'e-mail correspond (suivi) :
 * connaître un numéro ne suffit pas à lire l'adresse de quelqu'un.
 */
function commande(string $numero, ?string $email = null): ?array
{
    $requete = db()->prepare('SELECT * FROM commandes WHERE numero = :numero');
    $requete->execute(['numero' => $numero]);
    $commande = $requete->fetch();

    if (!$commande || ($email !== null && mb_strtolower($email) !== mb_strtolower($commande['email']))) {
        return null;
    }

    $requete = db()->prepare(
        'SELECT c.id, c.sous_total, c.port, c.etat, c.expedie_le, b.nom AS boutique
         FROM colis c JOIN boutiques b ON b.id = c.boutique_id
         WHERE c.commande_id = :id ORDER BY c.id'
    );
    $requete->execute(['id' => $commande['id']]);
    $commande['colis'] = array_column($requete->fetchAll(), null, 'id');

    $requete = db()->prepare(
        'SELECT l.colis_id, l.nom, l.prix, l.quantite
         FROM commande_lignes l JOIN colis c ON c.id = l.colis_id
         WHERE c.commande_id = :id ORDER BY l.id'
    );
    $requete->execute(['id' => $commande['id']]);
    foreach ($requete->fetchAll() as $l) {
        $commande['colis'][$l['colis_id']]['lignes'][] = $l;
    }

    return $commande;
}

/** État d'ensemble d'une commande, d'après ses colis. */
function etatCommande(array $commande): string
{
    $expedies = count(array_filter($commande['colis'], fn ($c) => $c['etat'] === 'expedie'));
    if ($expedies === 0) {
        return 'En préparation';
    }
    return $expedies === count($commande['colis']) ? 'Expédiée' : 'Partiellement expédiée';
}
