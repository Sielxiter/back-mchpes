<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidature_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activite_id')->nullable()->constrained('candidature_activites')->cascadeOnDelete();
            $table->enum('type', [
                'profile_pdf',
                'enseignements_pdf',
                'pfe_pdf',
                'activite_attestation',
                'signed_document'
            ]);
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('path');
            $table->string('hash')->nullable(); // For integrity verification
            $table->boolean('is_verified')->default(false);
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->index('candidature_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidature_documents');
    }
};
