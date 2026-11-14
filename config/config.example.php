<?php
/**
 * Modèle de configuration locale.
 *
 * Copier ce fichier en « config.local.php » (même dossier) et y mettre les vraies
 * valeurs. config.local.php est exclu de Git : le mot de passe et la clé
 * ne doivent jamais se retrouver dans le dépôt.
 */

return [
    // Adresse complète du site, sans « / » final (lien canonique, partage, Google).
    // En ligne : l'adresse définitive, en https.
    'url_absolue' => 'http://comptoir-9.test',

    // Rempli en phase 4 (base de données)
    'db' => [
        'hote'         => 'localhost',
        'port'         => 3306,
        'base'         => 'comptoir9',
        'utilisateur'  => 'comptoir9',
        'mot_de_passe' => 'CHANGER_CE_MOT_DE_PASSE',
    ],

    // Clé secrète : signe les jetons des formulaires.
    // Une longue chaîne aléatoire : php -r "echo bin2hex(random_bytes(32));"
    'secret' => 'CHANGER_CETTE_CLE',

    // Hébergeur, affiché dans les mentions légales. Il dépend de l'hébergement,
    // pas du code : laisser null tant que le site n'est pas en ligne.
    // Exemple : ['nom' => '…', 'adresse' => '…', 'site' => 'https://…']
    'hebergeur' => null,
];
