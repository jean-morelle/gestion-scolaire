<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affectations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->restrictOnDelete();
            $table->foreignId('enseignant_id')->nullable()->constrained('enseignants')->nullOnDelete();
            $table->decimal('coefficient', 4, 2)->default(1);
            $table->unsignedTinyInteger('ordre_bulletin')->default(0);
            $table->timestamps();

            // RG-ENS-01 : un seul enseignant et un seul coefficient par matière dans une classe.
            $table->unique(['classe_id', 'matiere_id']);
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affectation_id')->constrained('affectations')->restrictOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->restrictOnDelete();
            $table->string('libelle', 100);
            $table->string('type', 20);
            $table->date('date_evaluation');
            $table->decimal('bareme', 5, 2)->default(20);
            $table->decimal('poids', 4, 2)->default(1);
            $table->string('statut', 20)->default('brouillon');
            $table->foreignId('validee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validee_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['affectation_id', 'periode_id']);
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->restrictOnDelete();
            $table->foreignId('inscription_id')->constrained('inscriptions')->restrictOnDelete();
            $table->decimal('valeur', 5, 2)->nullable();
            $table->boolean('est_absent')->default(false);
            $table->string('observation')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_id', 'inscription_id']);
        });

        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->restrictOnDelete();
            $table->foreignId('publiee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publiee_at');
            $table->timestamp('depubliee_at')->nullable();
            $table->timestamps();

            $table->unique(['classe_id', 'periode_id']);
        });

        Schema::create('appreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->restrictOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->restrictOnDelete();
            $table->text('appreciation_generale')->nullable();
            $table->string('mention', 50)->nullable();
            $table->foreignId('saisie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['inscription_id', 'periode_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appreciations');
        Schema::dropIfExists('publications');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('affectations');
    }
};
