<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidature_activites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidature_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['enseignement', 'recherche']);
            $table->string('category');
            $table->string('subcategory');
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->index(['candidature_id', 'type']);
            $table->unique(['candidature_id', 'type', 'category', 'subcategory'], 'unique_activite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidature_activites');
    }
};
