# 7. Modèle logique des données (MLD)

Traduction relationnelle du MCD. Notation : **clé primaire** soulignée par `PK`, clés étrangères `#colonne → table`, contraintes d'unicité `UQ(...)`. Le détail des types et index est dans le [schéma relationnel final](08-schema-relationnel.md), généré depuis la base.

## Administration et comptes
- **etablissement** (PK id, nom, sigle, devise, …, parametres JSON) — une seule ligne par installation.
- **users** (PK id, name, email UQ, telephone UQ, password haché, est_actif, doit_changer_mot_de_passe, derniere_connexion_at)
- **roles**, **permissions**, **model_has_roles**, **role_has_permissions** (tables spatie/laravel-permission)
- **annees_scolaires** (PK id, libelle UQ, date_debut, date_fin, est_active, statut)
- **periodes** (PK id, #annee_scolaire_id → annees_scolaires, libelle, ordre, date_debut, date_fin, statut) — UQ(annee_scolaire_id, ordre)
- **sequences** (PK id, cle UQ, valeur) — compteurs verrouillés (matricules, reçus, dépenses)
- **journal_activites** (PK id, #user_id → users, action, sujet_type, sujet_id, anciennes_valeurs, nouvelles_valeurs, ip, user_agent, created_at) — ajout seul
- **notifications** (PK uuid, notifiable, type, data JSON, read_at) — table Laravel
- **annonces** (PK id, titre, contenu, cible, #classe_id → classes, publiee_at, expire_le, notifiee_at, #auteur_id → users)

## Scolarité
- **niveaux** (PK id, libelle UQ, cycle, ordre)
- **classes** (PK id, #annee_scolaire_id, #niveau_id, nom, serie, capacite, #professeur_principal_id → enseignants) — UQ(annee_scolaire_id, nom)
- **eleves** (PK id, matricule UQ, matricule_national UQ, nom, prenoms, sexe, date_naissance, …, statut, #user_id → users UQ)
- **responsables** (PK id, #user_id → users UQ, nom, prenoms, telephone, email, …)
- **eleve_responsable** (PK(#eleve_id, #responsable_id), lien, est_contact_principal, est_payeur)
- **inscriptions** (PK id, #eleve_id, #annee_scolaire_id, #classe_id, date_inscription, type, statut, date_sortie, motif_sortie, categorie_frais, redoublant) — **UQ(eleve_id, annee_scolaire_id)**
- **changements_classe** (PK id, #inscription_id, #ancienne_classe_id, #nouvelle_classe_id, date_effet, motif, #user_id)
- **documents_eleves** (PK id, #eleve_id, libelle, fichier_chemin, fichier_nom, fichier_mime, fichier_taille, #ajoute_par)

## Pédagogie et vie scolaire
- **enseignants** (PK id, #user_id UQ, matricule UQ, specialite, diplome, date_embauche, est_actif)
- **matieres** (PK id, code UQ, libelle, couleur) ; **enseignant_matiere** (PK(#enseignant_id, #matiere_id))
- **affectations** (PK id, #classe_id, #matiere_id, #enseignant_id, coefficient, ordre_bulletin) — **UQ(classe_id, matiere_id)**
- **emplois_du_temps** (PK id, #affectation_id, jour, heure_debut, heure_fin, salle)
- **evaluations** (PK id, #affectation_id, #periode_id, libelle, type, date_evaluation, bareme, poids, statut, #validee_par, validee_at, deverrouillee_at, #created_by)
- **notes** (PK id, #evaluation_id, #inscription_id, valeur, est_absent, observation) — **UQ(evaluation_id, inscription_id)**
- **appreciations** (PK id, #inscription_id, #periode_id, appreciation_generale, mention, #saisie_par) — UQ(inscription_id, periode_id)
- **publications** (PK id, #classe_id, #periode_id, #publiee_par, publiee_at, depubliee_at) — UQ(classe_id, periode_id)
- **bulletins** (PK id, #inscription_id, #periode_id, #publication_id, moyenne_generale, rang, effectif, mention, distinction, moyenne_annuelle, rang_annuel, appreciation, lignes JSON, assiduite JSON, version, #publie_par) — UQ(inscription_id, periode_id)
- **appels** (PK id, #classe_id, date_appel, creneau, #saisi_par) — **UQ(classe_id, date_appel, creneau)**
- **presences** (PK id, #appel_id, #inscription_id, statut, minutes_retard, remarque) — **UQ(appel_id, inscription_id)**
- **justificatifs** (PK id, #inscription_id, date_debut, date_fin, motif, fichier_chemin, #saisi_par) ; **justificatif_presence** (PK(#justificatif_id, #presence_id))

## Finances
- **types_frais** (PK id, code UQ, libelle, est_obligatoire)
- **grilles_frais** (PK id, #annee_scolaire_id, #niveau_id, #type_frais_id, categorie, montant) — UQ(annee_scolaire_id, niveau_id, type_frais_id, categorie)
- **tranches** (PK id, #grille_frais_id, libelle, montant, date_limite, ordre) — UQ(grille_frais_id, ordre) ; Σ montants = montant de la grille (règle applicative)
- **frais_eleves** (PK id, #inscription_id, #type_frais_id, #tranche_id, libelle, montant_du, date_limite)
- **remises** (PK id, #frais_eleve_id, montant, motif, #accordee_par, annulee_at, #annulee_par, motif_annulation)
- **paiements** (PK id, numero_recu UQ, #inscription_id, montant, date_paiement, mode, reference_externe, payeur_nom, #encaisse_par, statut, #annule_par, annule_at, motif_annulation)
- **imputations** (PK id, #paiement_id, #frais_eleve_id, montant) — UQ(paiement_id, frais_eleve_id)
- **categories_depenses** (PK id, libelle UQ) ; **depenses** (PK id, numero UQ, #categorie_depense_id, #annee_scolaire_id, libelle, montant, date_depense, mode, …, statut, annulation)

## Apprentissage
- **cours** (PK id, #affectation_id, titre, description, est_publie, publie_at, #created_by)
- **ressources_pedagogiques** (PK id, #cours_id, type, titre, contenu, url, fichier_*, ordre)
- **devoirs** (PK id, #affectation_id, #cours_id, titre, consignes, fichier_*, date_limite, bareme, remise_en_ligne, accepte_retard, est_publie, publie_at, #created_by)
- **remises_devoirs** (PK id, #devoir_id, #inscription_id, commentaire, fichier_*, remis_at, en_retard, note, correction, #corrige_par, corrige_at) — **UQ(devoir_id, inscription_id)**

## Intégrité référentielle
- Données historiques (inscriptions, notes, paiements, imputations, évaluations notées) : `ON DELETE RESTRICT`, la suppression d'un parent est refusée.
- Données dépendantes sans valeur historique propre (tranches d'une grille, créneaux d'une affectation, ressources d'un cours, présences d'un appel) : `ON DELETE CASCADE`.
- Références facultatives vers un utilisateur (auteur, correcteur…) : `ON DELETE SET NULL`, l'historique reste attribué tant que le compte existe (les comptes sont désactivés, pas supprimés).
- Montants : entiers non signés (unité monétaire), jamais de flottants. Notes, coefficients, barèmes : `DECIMAL`.
