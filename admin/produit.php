<?php
// Espace vendeur : ajouter un produit (sans ?id) ou modifier un produit de la boutique (?id=…)

require_once __DIR__ . '/../includes/admin/auth.php';
require_once __DIR__ . '/../includes/admin/vendeur.php';

$compte = exigerConnexion('vendeur');
$boutiqueId = (int) $compte['boutique_id']; // jamais une valeur venue de l'adresse ou du formulaire

// Le produit à modifier doit appartenir à la boutique : sinon, il « n'existe pas » (404)
$produitId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$produit = null;
if (isset($_GET['id'])) {
    $produit = $produitId ? produitDeLaBoutique($produitId, $boutiqueId) : null;
    if ($produit === null) {
        http_response_code(404);
        $titreAdmin = 'Produit introuvable';
        $pageAdmin  = 'produits';
        require __DIR__ . '/../includes/admin/haut.php';
        echo '<h1>Produit introuvable</h1>' . "\n"
           . '<p><a href="' . e(url('admin/produits.php')) . '">← Mes produits</a></p>' . "\n";
        require __DIR__ . '/../includes/admin/bas.php';
        exit;
    }
}

// Valeurs du formulaire : celles du produit, ou vides pour un nouveau produit
$valeurs = [
    'nom'              => $produit['nom'] ?? '',
    'rayon_id'         => (string) ($produit['rayon_id'] ?? ''),
    'description'      => $produit['description'] ?? '',
    'caracteristiques' => $produit['caracteristiques'] ?? '',
    'prix'             => centimesPourChamp($produit['prix'] ?? null),
    'prix_barre'       => centimesPourChamp($produit['prix_barre'] ?? null),
    'stock'            => (string) ($produit['stock'] ?? '0'),
    'delai_jours'      => (string) ($produit['delai_jours'] ?? '2'),
];
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf();
    [$propre, $erreurs] = verifierProduit($_POST);
    $valeurs = $propre['saisie'];

    if (!$erreurs) {
        if ($produit === null) {
            ajouterProduit($propre, $boutiqueId);
            flash('Produit ajouté et mis en vente.');
        } else {
            modifierProduit($produit['id'], $propre, $boutiqueId);
            flash('Produit enregistré.');
        }
        rediriger('admin/produits.php');
    }
    http_response_code(422);
}

$libelles = [
    'nom' => 'Nom', 'rayon_id' => 'Rayon', 'description' => 'Description', 'caracteristiques' => 'Caractéristiques',
    'prix' => 'Prix', 'prix_barre' => 'Ancien prix', 'stock' => 'Stock', 'delai_jours' => 'Délai d\'expédition',
];

$titreAdmin = ($erreurs ? 'Erreur — ' : '') . ($produit ? 'Modifier ' . $produit['nom'] : 'Ajouter un produit');
$pageAdmin  = 'produits';
require __DIR__ . '/../includes/admin/haut.php';
?>

<p><a href="<?= e(url('admin/produits.php')) ?>">← Mes produits</a></p>
<h1><?= $produit ? 'Modifier le produit' : 'Ajouter un produit' ?></h1>
<?php if ($produit && $produit['etat'] === 'en_vente'): ?>
<p><a href="<?= e(url('produit.php?produit=' . rawurlencode($produit['slug']))) ?>">Voir la fiche sur le site</a></p>
<?php endif; ?>

<?php if ($erreurs): ?>
<div class="avis avis--erreur" role="alert">
    <p><?= count($erreurs) > 1 ? count($erreurs) . ' champs sont à corriger :' : 'Un champ est à corriger :' ?></p>
    <ul>
<?php foreach ($erreurs as $nom => $texte): ?>
        <li><a href="#<?= e($nom) ?>"><?= e($libelles[$nom] ?? $nom) ?> : <?= e($texte) ?></a></li>
<?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- novalidate : les messages viennent du serveur, les mêmes pour tous les navigateurs -->
<form class="formulaire" method="post" action="<?= e(url('admin/produit.php' . ($produit ? '?id=' . (int) $produit['id'] : ''))) ?>" novalidate>
    <?= champCsrf() ?>

    <p class="champ">
        <label for="nom">Nom</label>
        <input id="nom" type="text" name="nom" value="<?= e($valeurs['nom']) ?>" maxlength="120" required<?= attributsChamp('nom', $erreurs) ?>>
        <?= messageErreur('nom', $erreurs) ?>

    </p>

    <p class="champ">
        <label for="rayon_id">Rayon</label>
        <select id="rayon_id" name="rayon_id" required<?= attributsChamp('rayon_id', $erreurs) ?>>
            <option value="">Choisir…</option>
<?php foreach (rayons() as $rayonDuMenu): ?>
<?php if ($rayonDuMenu['sous_rayons']): ?>
            <optgroup label="<?= e($rayonDuMenu['nom']) ?>">
<?php foreach ($rayonDuMenu['sous_rayons'] as $sousRayonDuMenu): ?>
                <option value="<?= (int) $sousRayonDuMenu['id'] ?>"<?= (string) $sousRayonDuMenu['id'] === $valeurs['rayon_id'] ? ' selected' : '' ?>><?= e($sousRayonDuMenu['nom']) ?></option>
<?php endforeach; ?>
            </optgroup>
<?php endif; ?>
<?php endforeach; ?>
        </select>
        <?= messageErreur('rayon_id', $erreurs) ?>

    </p>

    <p class="champ">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3" maxlength="500" required<?= attributsChamp('description', $erreurs) ?>><?= e($valeurs['description']) ?></textarea>
        <?= messageErreur('description', $erreurs) ?>

    </p>

    <p class="champ">
        <label for="caracteristiques">Caractéristiques</label>
        <textarea id="caracteristiques" name="caracteristiques" rows="4" required<?= attributsChamp('caracteristiques', $erreurs, 'aide-caracteristiques') ?>><?= e($valeurs['caracteristiques']) ?></textarea>
        <span class="champ__aide" id="aide-caracteristiques">Une par ligne, 8 au plus.</span>
        <?= messageErreur('caracteristiques', $erreurs) ?>

    </p>

    <div class="champs-cote">
        <p class="champ">
            <label for="prix">Prix (€)</label>
            <input id="prix" type="text" name="prix" value="<?= e($valeurs['prix']) ?>" inputmode="decimal" required<?= attributsChamp('prix', $erreurs, 'aide-prix') ?>>
            <span class="champ__aide" id="aide-prix">Par exemple 12,90</span>
            <?= messageErreur('prix', $erreurs) ?>

        </p>
        <p class="champ">
            <label for="prix_barre">Ancien prix (€) <span class="champ__facultatif">(promotion, facultatif)</span></label>
            <input id="prix_barre" type="text" name="prix_barre" value="<?= e($valeurs['prix_barre']) ?>" inputmode="decimal"<?= attributsChamp('prix_barre', $erreurs) ?>>
            <?= messageErreur('prix_barre', $erreurs) ?>

        </p>
        <p class="champ">
            <label for="stock">Stock</label>
            <input id="stock" type="number" name="stock" value="<?= e($valeurs['stock']) ?>" min="0" max="9999" inputmode="numeric" required<?= attributsChamp('stock', $erreurs) ?>>
            <?= messageErreur('stock', $erreurs) ?>

        </p>
        <p class="champ">
            <label for="delai_jours">Expédié sous (jours)</label>
            <input id="delai_jours" type="number" name="delai_jours" value="<?= e($valeurs['delai_jours']) ?>" min="1" max="30" inputmode="numeric" required<?= attributsChamp('delai_jours', $erreurs) ?>>
            <?= messageErreur('delai_jours', $erreurs) ?>

        </p>
    </div>

    <p><button class="bouton" type="submit"><?= $produit ? 'Enregistrer' : 'Ajouter et mettre en vente' ?></button></p>
</form>

<?php require __DIR__ . '/../includes/admin/bas.php'; ?>
