<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une annonce programmée n'est notifiée qu'une fois sa date de publication atteinte (commande annonces:diffuser).
        Schema::table('annonces', function (Blueprint $table) {
            $table->timestamp('notifiee_at')->nullable()->after('expire_le');
        });
    }

    public function down(): void
    {
        Schema::table('annonces', function (Blueprint $table) {
            $table->dropColumn('notifiee_at');
        });
    }
};
