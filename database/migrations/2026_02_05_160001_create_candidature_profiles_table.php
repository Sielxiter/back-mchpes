<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidature_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidature_id')->constrained()->cascadeOnDelete();
            $table->string('nom');
            $table->string('prenom');
            $table->string('email');
            $table->date('date_naissance');
            $table->string('etablissement');
            $table->string('ville');
            $table->string('departement');
            $table->string('grade_actuel');
            $table->date('date_recrutement_es');
            $table->date('date_recrutement_fp')->nullable();
            $table->string('numero_som');
            $table->string('telephone');
            $table->string('specialite');
            $table->boolean('exactitude_info')->default(false);
            $table->boolean('acceptation_termes')->default(false);
            $table->boolean('is_complete')->default(false);
            $table->timestamps();

            $table->unique('candidature_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidature_profiles');
    }
};
