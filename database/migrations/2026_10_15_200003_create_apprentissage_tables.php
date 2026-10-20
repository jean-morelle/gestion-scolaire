<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affectation_id')->constrained('affectations')->restrictOnDelete();
            $table->string('titre', 150);
            $table->text('description')->nullable();
            $table->boolean('est_publie')->default(false);
            $table->timestamp('publie_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Chapitres, documents, exercices et liens d'un cours. Le type « quiz » pourra être ajouté plus tard.
        Schema::create('ressources_pedagogiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('titre', 150);
            $table->longText('contenu')->nullable();
            $table->string('url')->nullable();
            $table->string('fichier_chemin')->nullable();
            $table->string('fichier_nom')->nullable();
            $table->string('fichier_mime', 100)->nullable();
            $table->unsignedInteger('fichier_taille')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        Schema::create('devoirs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affectation_id')->constrained('affectations')->restrictOnDelete();
            $table->foreignId('cours_id')->nullable()->constrained('cours')->nullOnDelete();
            $table->string('titre', 150);
            $table->text('consignes');
            $table->string('fichier_chemin')->nullable();
            $table->string('fichier_nom')->nullable();
            $table->dateTime('date_limite');
            $table->decimal('bareme', 5, 2)->default(20);
            $table->boolean('remise_en_ligne')->default(true);
            $table->boolean('accepte_retard')->default(false);
            $table->boolean('est_publie')->default(false);
            $table->timestamp('publie_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['affectation_id', 'date_limite']);
        });

        Schema::create('remises_devoirs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devoir_id')->constrained('devoirs')->restrictOnDelete();
            $table->foreignId('inscription_id')->constrained('inscriptions')->restrictOnDelete();
            $table->text('commentaire')->nullable();
            $table->string('fichier_chemin')->nullable();
            $table->string('fichier_nom')->nullable();
            $table->string('fichier_mime', 100)->nullable();
            $table->unsignedInteger('fichier_taille')->nullable();
            $table->timestamp('remis_at');
            $table->boolean('en_retard')->default(false);
            $table->decimal('note', 5, 2)->nullable();
            $table->text('correction')->nullable();
            $table->foreignId('corrige_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrige_at')->nullable();
            $table->timestamps();

            $table->unique(['devoir_id', 'inscription_id']);
        });

        Schema::create('annonces', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 150);
            $table->text('contenu');
            $table->string('cible', 20)->index(); // tous, personnel, parents, eleves
            $table->foreignId('classe_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->timestamp('publiee_at')->index();
            $table->date('expire_le')->nullable();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annonces');
        Schema::dropIfExists('remises_devoirs');
        Schema::dropIfExists('devoirs');
        Schema::dropIfExists('ressources_pedagogiques');
        Schema::dropIfExists('cours');
    }
};
