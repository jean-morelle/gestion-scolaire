<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les montants sont des entiers dans l'unité monétaire de l'établissement (RG-FIN-01).
     */
    public function up(): void
    {
        Schema::create('types_frais', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('libelle', 100);
            $table->boolean('est_obligatoire')->default(true);
            $table->timestamps();
        });

        Schema::create('grilles_frais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->restrictOnDelete();
            $table->foreignId('niveau_id')->constrained('niveaux')->restrictOnDelete();
            $table->foreignId('type_frais_id')->constrained('types_frais')->restrictOnDelete();
            // Chaîne vide = toutes catégories (un NULL échapperait à l'index unique).
            $table->string('categorie', 30)->default('');
            $table->unsignedBigInteger('montant');
            $table->timestamps();

            $table->unique(['annee_scolaire_id', 'niveau_id', 'type_frais_id', 'categorie'], 'grilles_frais_unique');
        });

        Schema::create('tranches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grille_frais_id')->constrained('grilles_frais')->cascadeOnDelete();
            $table->string('libelle', 50);
            $table->unsignedBigInteger('montant');
            $table->date('date_limite');
            $table->unsignedTinyInteger('ordre');
            $table->timestamps();

            $table->unique(['grille_frais_id', 'ordre']);
        });

        Schema::create('frais_eleves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->restrictOnDelete();
            $table->foreignId('type_frais_id')->constrained('types_frais')->restrictOnDelete();
            $table->foreignId('tranche_id')->nullable()->constrained('tranches')->nullOnDelete();
            $table->string('libelle', 100);
            $table->unsignedBigInteger('montant_du');
            $table->date('date_limite');
            $table->timestamps();

            $table->index(['inscription_id', 'date_limite']);
        });

        Schema::create('remises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frais_eleve_id')->constrained('frais_eleves')->restrictOnDelete();
            $table->unsignedBigInteger('montant');
            $table->string('motif');
            $table->foreignId('accordee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('annulee_at')->nullable();
            $table->foreignId('annulee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif_annulation')->nullable();
            $table->timestamps();
        });

        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->string('numero_recu', 30)->unique();
            $table->foreignId('inscription_id')->constrained('inscriptions')->restrictOnDelete();
            $table->unsignedBigInteger('montant');
            $table->date('date_paiement')->index();
            $table->string('mode', 20);
            $table->string('reference_externe', 100)->nullable();
            $table->string('payeur_nom', 150)->nullable();
            $table->foreignId('encaisse_par')->constrained('users')->restrictOnDelete();
            $table->string('statut', 20)->default('valide')->index();
            $table->foreignId('annule_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('annule_at')->nullable();
            $table->string('motif_annulation')->nullable();
            $table->timestamps();
        });

        Schema::create('imputations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paiement_id')->constrained('paiements')->restrictOnDelete();
            $table->foreignId('frais_eleve_id')->constrained('frais_eleves')->restrictOnDelete();
            $table->unsignedBigInteger('montant');
            $table->timestamps();

            $table->unique(['paiement_id', 'frais_eleve_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imputations');
        Schema::dropIfExists('paiements');
        Schema::dropIfExists('remises');
        Schema::dropIfExists('frais_eleves');
        Schema::dropIfExists('tranches');
        Schema::dropIfExists('grilles_frais');
        Schema::dropIfExists('types_frais');
    }
};
