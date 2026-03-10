<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For PostgreSQL, we need to alter the enum type
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TYPE candidature_document_type ADD VALUE IF NOT EXISTS 'diplome_doctorat'");
            DB::statement("ALTER TYPE candidature_document_type ADD VALUE IF NOT EXISTS 'diplome_habilitation'");
            DB::statement("ALTER TYPE candidature_document_type ADD VALUE IF NOT EXISTS 'arrete_titularisation'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // PostgreSQL doesn't support removing values from an ENUM easily.
        // Usually, we would leave it or recreate the type, but for a simple fix, 
        // we'll just skip the down migration as these types are harmless.
    }
};
