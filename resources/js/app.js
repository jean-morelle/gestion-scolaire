import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Recherche globale de l'en-tête : suggestions pendant la frappe (le serveur filtre selon les droits).
Alpine.data('recherche', (url) => ({
    q: '',
    resultats: [],
    ouvert: false,
    async chercher() {
        if (this.q.trim().length < 2) {
            this.resultats = [];
            this.ouvert = false;
            return;
        }
        const reponse = await fetch(`${url}?q=${encodeURIComponent(this.q)}`, { headers: { Accept: 'application/json' } });
        if (reponse.ok) {
            this.resultats = (await reponse.json()).resultats;
            this.ouvert = true;
        }
    },
}));

Alpine.start();

// Confirmation avant les opérations sensibles : <form data-confirm="Message ?">.
document.addEventListener('submit', (event) => {
    const formulaire = event.target;
    const message = formulaire.dataset.confirm;
    if (message && !window.confirm(message)) {
        event.preventDefault();
        return;
    }
    // Évite les doubles soumissions (paiements, publications...).
    formulaire.querySelectorAll('button[type="submit"]').forEach((bouton) => {
        if (!formulaire.hasAttribute('data-sans-blocage')) {
            setTimeout(() => (bouton.disabled = true), 0);
        }
    });
});

// Saisie des notes en grille : Entrée passe au champ suivant.
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || !event.target.matches('[data-grille] input[type="text"], [data-grille] input[type="number"]')) {
        return;
    }
    event.preventDefault();
    const champs = [...event.target.closest('[data-grille]').querySelectorAll('input[type="text"], input[type="number"]')];
    const suivant = champs[champs.indexOf(event.target) + 1];
    if (suivant) {
        suivant.focus();
        suivant.select();
    }
});
