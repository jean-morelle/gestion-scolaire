<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une installation = un établissement : cette table ne contient qu'une ligne.
        Schema::create('etablissement', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('sigle', 30)->nullable();
            $table->string('adresse')->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('logo_chemin')->nullable();
            $table->string('devise', 10)->default('FCFA');
            $table->string('slogan')->nullable();
            $table->string('chef_etablissement')->nullable();
            $table->json('parametres')->nullable();
            $table->timestamps();
        });

        Schema::create('annees_scolaires', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 20)->unique();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->boolean('est_active')->default(false)->index();
            $table->string('statut', 20)->default('preparation');
            $table->timestamps();
        });

        Schema::create('periodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->restrictOnDelete();
            $table->string('libelle', 50);
            $table->unsignedTinyInteger('ordre');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('statut', 20)->default('ouverte');
            $table->timestamps();

            $table->unique(['annee_scolaire_id', 'ordre']);
        });

        Schema::create('journal_activites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100)->index();
            $table->nullableMorphs('sujet');
            $table->string('description')->nullable();
            $table->json('anciennes_valeurs')->nullable();
            $table->json('nouvelles_valeurs')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // Compteurs (reçus, matricules) lus avec SELECT ... FOR UPDATE.
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 50)->unique();
            $table->unsignedBigInteger('valeur')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
        Schema::dropIfExists('journal_activites');
        Schema::dropIfExists('periodes');
        Schema::dropIfExists('annees_scolaires');
        Schema::dropIfExists('etablissement');
    }
};
