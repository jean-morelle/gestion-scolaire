<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compte de connexion de l'élève (espace élève).
        Schema::table('eleves', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained('users')->nullOnDelete();
        });

        // Pièces du dossier scolaire (acte de naissance, certificat de scolarité...), stockées hors du dossier public.
        Schema::create('documents_eleves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->restrictOnDelete();
            $table->string('libelle', 100);
            $table->string('fichier_chemin');
            $table->string('fichier_nom');
            $table->string('fichier_mime', 100);
            $table->unsignedInteger('fichier_taille');
            $table->foreignId('ajoute_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Emploi du temps : une ligne = un créneau hebdomadaire d'une affectation (classe × matière × enseignant).
        Schema::create('emplois_du_temps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affectation_id')->constrained('affectations')->cascadeOnDelete();
            $table->unsignedTinyInteger('jour'); // 1 = lundi ... 6 = samedi
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('salle', 50)->nullable();
            $table->timestamps();

            $table->index(['jour', 'heure_debut']);
        });

        // Instantané d'un bulletin figé à la publication (moyennes, rang, lignes par matière).
        Schema::create('bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->restrictOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->restrictOnDelete();
            $table->foreignId('publication_id')->constrained('publications')->restrictOnDelete();
            $table->decimal('moyenne_generale', 6, 3)->nullable();
            $table->unsignedSmallInteger('rang')->nullable();
            $table->unsignedSmallInteger('effectif');
            $table->string('mention', 50)->nullable();
            $table->text('appreciation')->nullable();
            $table->json('lignes');
            $table->json('assiduite');
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['inscription_id', 'periode_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletins');
        Schema::dropIfExists('emplois_du_temps');
        Schema::dropIfExists('documents_eleves');
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
