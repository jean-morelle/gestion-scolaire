# Conception UML — EduGestion

Diagrammes au format **Mermaid** (fichiers texte modifiables, affichés par GitHub, GitLab, VS Code avec l'extension « Markdown Preview Mermaid », ou https://mermaid.live).
Ils décrivent l'application **telle qu'elle est implémentée** ; les noms entre `backquotes` renvoient au code.

| Fichier | Contenu |
|---|---|
| [01-cas-utilisation-general.md](01-cas-utilisation-general.md) | Diagramme de cas d'utilisation général |
| [02-cas-utilisation-modules.md](02-cas-utilisation-modules.md) | Cas d'utilisation détaillés des cinq modules |
| [03-diagramme-de-classes.md](03-diagramme-de-classes.md) | Diagramme de classes (modèles, services, Policies) |
| [04-sequences.md](04-sequences.md) | Séquences : authentification, inscription, notes et publication, paiement, consultation parent, remise de devoir |
| [05-activites.md](05-activites.md) | Activités : inscription, génération d'un bulletin, paiement des frais, remise et correction d'un devoir |
| [06-modele-conceptuel.md](06-modele-conceptuel.md) | Modèle conceptuel des données (MCD) |
| [07-modele-logique.md](07-modele-logique.md) | Modèle logique des données (MLD) |
| [08-schema-relationnel.md](08-schema-relationnel.md) | Schéma relationnel final, **généré depuis la base** (`php artisan ecole:doc-base`) |

Conventions : les acteurs sont les rôles de `App\Enums\Role` ; « Système » désigne les tâches planifiées (`routes/console.php`).
