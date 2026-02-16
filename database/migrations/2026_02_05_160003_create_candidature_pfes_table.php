<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidature_pfes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidature_id')->constrained()->cascadeOnDelete();
            $table->string('annee_universitaire');
            $table->string('intitule');
            $table->string('niveau');
            $table->integer('volume_horaire');
            $table->timestamps();

            $table->index('candidature_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidature_pfes');
    }
};
