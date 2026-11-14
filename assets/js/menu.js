/**
 * Menu.
 * 1. Menu mobile : le script ne fait que changer aria-expanded sur le bouton.
 *    Le CSS lit cet attribut pour ouvrir ou fermer le menu, et les lecteurs
 *    d'écran l'annoncent. Sans JavaScript, le menu reste affiché en liste.
 * 2. Sous-menus des rayons (<details>) : ils s'ouvrent sans JavaScript ;
 *    le script ajoute seulement la fermeture par Échap ou par un clic ailleurs.
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
}
