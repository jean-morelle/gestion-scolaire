<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RG-NOT-11 : après un déverrouillage, chaque modification de note est historisée (valeur avant/après).
        Schema::table('evaluations', function (Blueprint $table) {
            $table->timestamp('deverrouillee_at')->nullable()->after('validee_at');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropColumn('deverrouillee_at');
        });
    }
};
