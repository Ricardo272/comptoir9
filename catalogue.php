<?php
// Catalogue : un rayon, un sous-rayon, les bonnes affaires ou une recherche,
// avec filtres, tri et pagination. Tout passe par l'adresse (GET) : la page
// marche sans JavaScript et une recherche peut être mise en favori.
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/donnees/rayons.php';
require_once __DIR__ . '/includes/donnees/catalogue.php';

// --- Lecture de l'adresse : chaque valeur est contrôlée avant de servir ------
// Texte : une chaîne (pas un tableau), en UTF-8 valide, de longueur limitée.
$texte = function (string $cle, int $longueur): string {
    $valeur = $_GET[$cle] ?? '';
    if (!is_string($valeur) || !mb_check_encoding($valeur, 'UTF-8')) {
        return '';
    }
    return mb_substr(trim($valeur), 0, $longueur);
};
// Prix saisi en euros entiers, rendu en centimes ; null si absent ou invalide.
$euros = function (string $cle): ?int {
    $valeur = filter_var($_GET[$cle] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
    return $valeur === false || $valeur === null ? null : $valeur * 100;
};

$slugRayon = $texte('rayon', 60);
$rayonChoisi = $slugRayon === '' ? null : trouverRayon($slugRayon);

$boutiques = boutiques();
$slugBoutique = $texte('boutique', 60);

// Rayon ou boutique inconnus : vraie page 404, plutôt qu'une liste vide
if (($slugRayon !== '' && $rayonChoisi === null)
    || ($slugBoutique !== '' && !in_array($slugBoutique, array_column($boutiques, 'slug'), true))) {
    require __DIR__ . '/404.php';
    exit;
}

$filtres = [
    'rayon'    => $rayonChoisi,
    'q'        => $texte('q', 80),
    'boutique' => $slugBoutique,
    'prix_min' => $euros('prix_min'),
    'prix_max' => $euros('prix_max'),
    'en_stock' => isset($_GET['en_stock']),
    'tri'      => isset(TRIS_CATALOGUE[$texte('tri', 20)]) ? $texte('tri', 20) : 'nouveaute',
];
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 1]]);

$resultat = rechercherProduits($filtres, $page);

// --- Adresses : on ne garde que les paramètres utiles --------------------------
$parametres = array_filter([
    'rayon'    => $slugRayon,
    'q'        => $filtres['q'],
    'boutique' => $slugBoutique,
    'prix_min' => $filtres['prix_min'] === null ? '' : $filtres['prix_min'] / 100,
    'prix_max' => $filtres['prix_max'] === null ? '' : $filtres['prix_max'] / 100,
    'en_stock' => $filtres['en_stock'] ? '1' : '',
    'tri'      => $filtres['tri'] === 'nouveaute' ? '' : $filtres['tri'],
], fn ($valeur) => $valeur !== '');

$urlPage = fn (int $numero) => url('catalogue.php?' . http_build_query($parametres + ($numero > 1 ? ['page' => $numero] : [])));

// --- Titre et fil d'Ariane ---------------------------------------------------
$filAriane = [['Accueil', url()]];
if ($rayonChoisi) {
    $filAriane[] = [$rayonChoisi['rayon']['nom'], urlRayon($rayonChoisi['rayon']['slug'])];
    if ($rayonChoisi['sous_rayon']) {
        $filAriane[] = [$rayonChoisi['sous_rayon']['nom'], urlRayon($rayonChoisi['sous_rayon']['slug'])];
    }
}
$titre = $rayonChoisi ? ($rayonChoisi['sous_rayon']['nom'] ?? $rayonChoisi['rayon']['nom']) : 'Tout le catalogue';
if ($filtres['q'] !== '') {
    $titre = 'Résultats pour « ' . $filtres['q'] . ' »';
    $filAriane[] = ['Recherche', ''];
} elseif (!$rayonChoisi) {
    $filAriane[] = ['Catalogue', ''];
}

$pageId     = 'catalogue';
$pageTitle  = $titre . ($resultat['page'] > 1 ? ' — page ' . $resultat['page'] : '');
$rechercheQ = $filtres['q'];
// Une page de rayon sans filtre a sa propre adresse officielle ; une recherche
// ou un filtrage n'a pas à être indexé (il y en a une infinité).
if (array_diff_key($parametres, ['rayon' => 1]) || $resultat['page'] > 1) {
    $pageNoIndex = true;
} elseif ($rayonChoisi) {
    $pageCanonique = 'catalogue.php?rayon=' . rawurlencode($slugRayon);
}
if ($rayonChoisi) {
    $pageDescription = $titre . ' sur Comptoir 9 : des produits neufs vendus par des boutiques indépendantes vérifiées.';
}

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<main id="contenu">
    <section class="section">
        <div class="conteneur">
<?php require __DIR__ . '/includes/fil-ariane.php'; ?>
            <h1><?= e($titre) ?></h1>

<?php if ($rayonChoisi && $rayonChoisi['rayon']['sous_rayons']): ?>
            <nav aria-label="Sous-rayons de <?= e($rayonChoisi['rayon']['nom']) ?>">
                <ul class="sous-rayons" role="list">
<?php foreach ($rayonChoisi['rayon']['sous_rayons'] as $sousRayon): ?>
                    <li><a href="<?= e(urlRayon($sousRayon['slug'])) ?>"<?= $sousRayon['slug'] === $slugRayon ? ' aria-current="page"' : '' ?>><?= e($sousRayon['nom']) ?></a></li>
<?php endforeach; ?>
                </ul>
            </nav>
<?php endif; ?>

            <form class="filtres" action="<?= e(url('catalogue.php')) ?>" method="get" aria-label="Filtrer et trier les produits">
<?php if ($slugRayon !== ''): ?>
                <input type="hidden" name="rayon" value="<?= e($slugRayon) ?>">
<?php endif; ?>
<?php if ($filtres['q'] !== ''): ?>
                <input type="hidden" name="q" value="<?= e($filtres['q']) ?>">
<?php endif; ?>
                <p class="champ">
                    <label for="filtre-boutique">Boutique</label>
                    <select id="filtre-boutique" name="boutique">
                        <option value="">Toutes</option>
<?php foreach ($boutiques as $b): ?>
                        <option value="<?= e($b['slug']) ?>"<?= $b['slug'] === $slugBoutique ? ' selected' : '' ?>><?= e($b['nom']) ?></option>
<?php endforeach; ?>
                    </select>
                </p>
                <p class="champ champ--court">
                    <label for="filtre-prix-min">Prix mini (€)</label>
                    <input id="filtre-prix-min" type="number" name="prix_min" min="0" max="100000" step="1" inputmode="numeric" value="<?= e((string) ($parametres['prix_min'] ?? '')) ?>">
                </p>
                <p class="champ champ--court">
                    <label for="filtre-prix-max">Prix maxi (€)</label>
                    <input id="filtre-prix-max" type="number" name="prix_max" min="0" max="100000" step="1" inputmode="numeric" value="<?= e((string) ($parametres['prix_max'] ?? '')) ?>">
                </p>
                <p class="champ">
                    <label for="filtre-tri">Trier par</label>
                    <select id="filtre-tri" name="tri">
<?php foreach (TRIS_CATALOGUE as $cle => [$libelle]): ?>
                        <option value="<?= e($cle) ?>"<?= $cle === $filtres['tri'] ? ' selected' : '' ?>><?= e($libelle) ?></option>
<?php endforeach; ?>
                    </select>
                </p>
                <p class="champ champ--case">
                    <input id="filtre-stock" type="checkbox" name="en_stock" value="1"<?= $filtres['en_stock'] ? ' checked' : '' ?>>
                    <label for="filtre-stock">En stock seulement</label>
                </p>
                <p class="filtres__actions">
                    <button class="bouton" type="submit">Filtrer</button>
                    <a href="<?= e($slugRayon !== '' ? urlRayon($slugRayon) : url('catalogue.php')) ?>">Effacer les filtres</a>
                </p>
            </form>

            <!-- role="status" : le nombre de résultats est annoncé par les lecteurs d'écran -->
            <p class="resultats" role="status">
                <strong><?= $resultat['total'] ?></strong> produit<?= $resultat['total'] > 1 ? 's' : '' ?>
<?php if ($resultat['pages'] > 1): ?>
                · page <?= $resultat['page'] ?> sur <?= $resultat['pages'] ?>
<?php endif; ?>
            </p>

<?php if ($resultat['produits']): ?>
            <div class="grille">
<?php foreach ($resultat['produits'] as $p): ?>
<?php require __DIR__ . '/includes/carte-produit.php'; ?>
<?php endforeach; ?>
            </div>
<?php else: ?>
            <p>Aucun produit ne correspond. Essayez un autre mot, ou
               <a href="<?= e(url('catalogue.php')) ?>">parcourez tout le catalogue</a>.</p>
<?php endif; ?>

<?php if ($resultat['pages'] > 1): ?>
            <nav class="pagination" aria-label="Pages de résultats">
                <ul role="list">
<?php if ($resultat['page'] > 1): ?>
                    <li><a href="<?= e($urlPage($resultat['page'] - 1)) ?>" rel="prev">Précédente</a></li>
<?php endif; ?>
<?php for ($numero = 1; $numero <= $resultat['pages']; $numero++): ?>
                    <li><a href="<?= e($urlPage($numero)) ?>"<?= $numero === $resultat['page'] ? ' aria-current="page"' : '' ?>><span class="visuellement-cache">Page </span><?= $numero ?></a></li>
<?php endfor; ?>
<?php if ($resultat['page'] < $resultat['pages']): ?>
                    <li><a href="<?= e($urlPage($resultat['page'] + 1)) ?>" rel="next">Suivante</a></li>
<?php endif; ?>
                </ul>
            </nav>
<?php endif; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
