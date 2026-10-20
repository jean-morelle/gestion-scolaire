<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulletins', function (Blueprint $table) {
            // Décision du conseil de classe (tableau d'honneur, avertissement…), distincte de la mention calculée.
            $table->string('distinction', 100)->nullable()->after('mention');
            // Renseignées sur le bulletin de la dernière période (RG-NOT-08).
            $table->decimal('moyenne_annuelle', 6, 3)->nullable()->after('distinction');
            $table->unsignedSmallInteger('rang_annuel')->nullable()->after('moyenne_annuelle');
            $table->foreignId('publie_par')->nullable()->after('version')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bulletins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('publie_par');
            $table->dropColumn(['distinction', 'moyenne_annuelle', 'rang_annuel']);
        });
    }
};
