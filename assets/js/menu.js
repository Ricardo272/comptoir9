/**
 * Menu.
 * 1. Menu mobile : le script ne fait que changer aria-expanded sur le bouton.
 *    Le CSS lit cet attribut pour ouvrir ou fermer le menu, et les lecteurs
 *    d'écran l'annoncent. Sans JavaScript, le menu reste affiché en liste.
 * 2. Sous-menus des rayons (<details>) : ils s'ouvrent sans JavaScript ;
 *    le script ajoute seulement la fermeture par Échap ou par un clic ailleurs.
 * 3. Fenêtre « Ajouté au panier » : ouverte sans script, rendue modale ici.
 */
{
    const bouton = document.querySelector('.nav-bouton');
    const rayonOuvert = () => document.querySelector('.rayon[open]');

    if (bouton) {
        const estOuvert = () => bouton.getAttribute('aria-expanded') === 'true';
        const basculer = (ouvrir) => bouton.setAttribute('aria-expanded', String(ouvrir));

        bouton.addEventListener('click', () => basculer(!estOuvert()));

        document.addEventListener('keydown', (evenement) => {
            if (evenement.key !== 'Escape') {
                return;
            }
            // Échap ferme d'abord le sous-menu ouvert, sinon le menu mobile,
            // et rend le focus à ce qui l'avait ouvert (utilisateur clavier).
            const rayon = rayonOuvert();
            if (rayon) {
                rayon.open = false;
                rayon.querySelector('summary').focus();
            } else if (estOuvert()) {
                basculer(false);
                bouton.focus();
            }
        });
    }

    // Clic en dehors d'un sous-menu ouvert : on le referme
    document.addEventListener('click', (evenement) => {
        const rayon = rayonOuvert();
        if (rayon && !rayon.contains(evenement.target)) {
            rayon.open = false;
        }
    });

    // 3. Fenêtre « Ajouté au panier » : le serveur l'envoie ouverte (elle marche sans script).
    //    Ici, on la rouvre en modale : fond assombri, focus gardé dedans, Échap pour fermer.
    const fenetre = document.querySelector('.fenetre-ajout[open]');
    if (fenetre && typeof fenetre.showModal === 'function') {
        fenetre.close();
        fenetre.showModal();
    }
}
