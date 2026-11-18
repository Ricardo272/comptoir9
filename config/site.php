<?php
/**
 * Informations de Comptoir 9, utilisées sur tout le site.
 *
 * Ce fichier ne contient rien de secret : il est versionné dans Git.
 * L'adresse du site, les identifiants de la base et la clé secrète
 * sont dans config.local.php (exclu de Git).
 */

return [
    'nom'    => 'Comptoir 9',
    'slogan' => 'Tout le catalogue, un seul comptoir.',

    // Racine du site. « / » sur comptoir-9.test ; à changer si le site
    // est un jour hébergé dans un sous-dossier.
    'base_url' => '/',

    // Site de démonstration (portfolio) : bandeau « aucun paiement, aucune
    // livraison » et contenu jamais vide. Mettre false pour une vraie boutique.
    'demo' => true,

    // Code source public du site (lien dans les mentions légales)
    'code_source' => 'https://github.com/Ricardo272/comptoir9',

    'description' => 'Comptoir 9, la place de marché des boutiques indépendantes : high-tech, cuisine, '
                   . 'maison, sport et jeux. Vendeurs vérifiés, prix sans surprise, délai annoncé.',

    'adresse' => [
        'rue'         => '9, passage des Neuf-Comptoirs', // rue inventée
        'code_postal' => '69004',
        'ville'       => 'Lyon',
    ],

    // Numéro fictif : la tranche 01 99 00 xx xx est réservée par l'ARCEP aux
    // œuvres de fiction (plan national de numérotation) : elle n'appartient à personne.
    'telephone' => '01 99 00 48 84',
    // Le domaine .test est réservé : il ne peut appartenir à personne.
    'email'     => 'contact@comptoir9.test',
    'horaires_service' => 'du lundi au vendredi, de 9 h à 18 h',

    // Les 3 engagements (accueil et page À propos)
    'engagements' => [
        ['titre' => 'Des vendeurs vérifiés',
         'texte' => 'Chaque boutique est rencontrée avant d\'ouvrir son rayon : pas de vendeur anonyme.'],
        ['titre' => 'Des prix sans surprise',
         'texte' => 'Le prix affiché est le prix payé, et les frais de port sont annoncés dès le panier.'],
        ['titre' => 'Un délai annoncé',
         'texte' => 'Chaque fiche indique sous combien de jours la boutique expédie le produit.'],
    ],

    // Frais de port, en centimes : un forfait par boutique, offert quand le
    // montant acheté dans cette boutique atteint le seuil.
    'frais_port' => [
        'par_boutique' => 490,
        'offerts_des'  => 6000,
    ],

    // Liens secondaires (en-tête et pied de page). Les rayons, eux, sont
    // dans includes/donnees/rayons.php. « id » repère la page en cours.
    'navigation' => [
        ['id' => 'boutiques', 'label' => 'Les boutiques', 'url' => 'boutiques.php'],
        ['id' => 'a-propos',  'label' => 'À propos',      'url' => 'a-propos.php'],
        ['id' => 'aide',      'label' => 'Aide',          'url' => 'aide.php'],
        ['id' => 'contact',   'label' => 'Contact',       'url' => 'contact.php'],
    ],
];
