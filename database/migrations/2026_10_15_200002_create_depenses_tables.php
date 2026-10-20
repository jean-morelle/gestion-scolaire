<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories_depenses', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 100)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('depenses', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->foreignId('categorie_depense_id')->constrained('categories_depenses')->restrictOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->restrictOnDelete();
            $table->string('libelle', 150);
            $table->unsignedBigInteger('montant');
            $table->date('date_depense')->index();
            $table->string('mode', 20);
            $table->string('beneficiaire', 150)->nullable();
            $table->string('reference_piece', 100)->nullable();
            $table->string('justificatif_chemin')->nullable();
            $table->foreignId('saisi_par')->constrained('users')->restrictOnDelete();
            $table->string('statut', 20)->default('valide')->index();
            $table->foreignId('annulee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('annulee_at')->nullable();
            $table->string('motif_annulation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depenses');
        Schema::dropIfExists('categories_depenses');
    }
};
