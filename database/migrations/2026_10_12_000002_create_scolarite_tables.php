<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveaux', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 30)->unique();
            $table->string('cycle', 20);
            $table->unsignedTinyInteger('ordre');
            $table->timestamps();
        });

        Schema::create('enseignants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('matricule', 30)->nullable()->unique();
            $table->string('specialite', 100)->nullable();
            $table->boolean('est_actif')->default(true);
            $table->timestamps();
        });

        Schema::create('matieres', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('libelle', 100);
            $table->string('couleur', 7)->nullable();
            $table->timestamps();
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->restrictOnDelete();
            $table->foreignId('niveau_id')->constrained('niveaux')->restrictOnDelete();
            $table->string('nom', 30);
            $table->string('serie', 10)->nullable();
            $table->unsignedSmallInteger('capacite')->nullable();
            $table->foreignId('professeur_principal_id')->nullable()->constrained('enseignants')->nullOnDelete();
            $table->timestamps();

            $table->unique(['annee_scolaire_id', 'nom']);
        });

        Schema::create('eleves', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 20)->unique();
            $table->string('matricule_national', 30)->nullable()->unique();
            $table->string('nom', 100);
            $table->string('prenoms', 150);
            $table->char('sexe', 1);
            $table->date('date_naissance');
            $table->string('lieu_naissance', 100)->nullable();
            $table->string('nationalite', 50)->nullable();
            $table->string('adresse')->nullable();
            $table->string('photo_chemin')->nullable();
            $table->string('statut', 20)->default('actif');
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index(['nom', 'prenoms']);
        });

        Schema::create('responsables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('nom', 100);
            $table->string('prenoms', 150);
            $table->string('telephone', 30)->index();
            $table->string('telephone_2', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('profession', 100)->nullable();
            $table->string('adresse')->nullable();
            $table->timestamps();
        });

        Schema::create('eleve_responsable', function (Blueprint $table) {
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('responsable_id')->constrained('responsables')->cascadeOnDelete();
            $table->string('lien', 20);
            $table->boolean('est_contact_principal')->default(false);
            $table->boolean('est_payeur')->default(false);
            $table->timestamps();

            $table->primary(['eleve_id', 'responsable_id']);
        });

        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->restrictOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->restrictOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->restrictOnDelete();
            $table->date('date_inscription');
            $table->string('type', 20);
            $table->string('statut', 20)->default('inscrit');
            $table->date('date_sortie')->nullable();
            $table->string('motif_sortie')->nullable();
            $table->string('categorie_frais', 30)->nullable();
            $table->boolean('redoublant')->default(false);
            $table->timestamps();

            // RG-ELV-02 : une seule inscription par élève et par année.
            $table->unique(['eleve_id', 'annee_scolaire_id']);
            $table->index(['classe_id', 'statut']);
        });

        Schema::create('changements_classe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->restrictOnDelete();
            $table->foreignId('ancienne_classe_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('nouvelle_classe_id')->constrained('classes')->restrictOnDelete();
            $table->date('date_effet');
            $table->string('motif')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changements_classe');
        Schema::dropIfExists('inscriptions');
        Schema::dropIfExists('eleve_responsable');
        Schema::dropIfExists('responsables');
        Schema::dropIfExists('eleves');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('matieres');
        Schema::dropIfExists('enseignants');
        Schema::dropIfExists('niveaux');
    }
};
