<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Expand the enum to include attestation types and per-type signed variants
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE candidature_documents MODIFY COLUMN type ENUM(
                'profile_pdf',
                'enseignements_pdf',
                'pfe_pdf',
                'activite_attestation',
                'signed_document',
                'attestation_ens_pdf',
                'attestation_rech_pdf',
                'signed_profile',
                'signed_enseignements',
                'signed_pfe',
                'signed_attestation_ens',
                'signed_attestation_rech'
            ) NOT NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("DROP TYPE IF EXISTS candidature_document_type CASCADE");
            DB::statement("CREATE TYPE candidature_document_type AS ENUM (
                'profile_pdf',
                'enseignements_pdf',
                'pfe_pdf',
                'activite_attestation',
                'signed_document',
                'attestation_ens_pdf',
                'attestation_rech_pdf',
                'signed_profile',
                'signed_enseignements',
                'signed_pfe',
                'signed_attestation_ens',
                'signed_attestation_rech'
            )");
            DB::statement("ALTER TABLE candidature_documents ALTER COLUMN type TYPE candidature_document_type USING type::text::candidature_document_type");
        }

        // Add a nullable column to reference the generated doc that a signed upload relates to
        Schema::table('candidature_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('generated_document_id')->nullable()->after('activite_id');
            $table->foreign('generated_document_id')
                  ->references('id')
                  ->on('candidature_documents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('candidature_documents', function (Blueprint $table) {
            $table->dropForeign(['generated_document_id']);
            $table->dropColumn('generated_document_id');
        });

        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE candidature_documents MODIFY COLUMN type ENUM(
                'profile_pdf',
                'enseignements_pdf',
                'pfe_pdf',
                'activite_attestation',
                'signed_document'
            ) NOT NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("DROP TYPE IF EXISTS candidature_document_type CASCADE");
            DB::statement("CREATE TYPE candidature_document_type AS ENUM (
                'profile_pdf',
                'enseignements_pdf',
                'pfe_pdf',
                'activite_attestation',
                'signed_document'
            )");
            DB::statement("ALTER TABLE candidature_documents ALTER COLUMN type TYPE candidature_document_type USING type::text::candidature_document_type");
        }
    }
};
