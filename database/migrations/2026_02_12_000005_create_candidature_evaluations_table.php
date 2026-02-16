<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidature_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidature_id')->constrained('candidatures')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('criterion');
            $table->decimal('score', 6, 2)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['candidature_id', 'user_id', 'criterion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidature_evaluations');
    }
};
