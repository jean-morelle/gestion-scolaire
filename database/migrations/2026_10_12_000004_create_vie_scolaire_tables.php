<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->restrictOnDelete();
            $table->date('date_appel');
            $table->string('creneau', 20);
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // RG-PRE-01 : un seul appel par classe, date et créneau.
            $table->unique(['classe_id', 'date_appel', 'creneau']);
        });

        Schema::create('presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appel_id')->constrained('appels')->cascadeOnDelete();
            $table->foreignId('inscription_id')->constrained('inscriptions')->restrictOnDelete();
            $table->string('statut', 20);
            $table->unsignedSmallInteger('minutes_retard')->nullable();
            $table->string('remarque')->nullable();
            $table->timestamps();

            // RG-PRE-03 : une seule ligne par élève et par appel.
            $table->unique(['appel_id', 'inscription_id']);
            $table->index(['inscription_id', 'statut']);
        });

        Schema::create('justificatifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->restrictOnDelete();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('motif');
            $table->string('fichier_chemin')->nullable();
            $table->foreignId('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('justificatif_presence', function (Blueprint $table) {
            $table->foreignId('justificatif_id')->constrained('justificatifs')->cascadeOnDelete();
            $table->foreignId('presence_id')->constrained('presences')->cascadeOnDelete();

            $table->primary(['justificatif_id', 'presence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justificatif_presence');
        Schema::dropIfExists('justificatifs');
        Schema::dropIfExists('presences');
        Schema::dropIfExists('appels');
    }
};
