<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Matières qu'un enseignant est habilité à enseigner (guide les affectations, non bloquant).
        Schema::create('enseignant_matiere', function (Blueprint $table) {
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->restrictOnDelete();
            $table->timestamps();

            $table->primary(['enseignant_id', 'matiere_id']);
        });

        Schema::table('enseignants', function (Blueprint $table) {
            $table->string('diplome', 100)->nullable()->after('specialite');
            $table->date('date_embauche')->nullable()->after('diplome');
        });
    }

    public function down(): void
    {
        Schema::table('enseignants', function (Blueprint $table) {
            $table->dropColumn(['diplome', 'date_embauche']);
        });
        Schema::dropIfExists('enseignant_matiere');
    }
};
