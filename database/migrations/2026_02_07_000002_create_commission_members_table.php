<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commission_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_id')->constrained('commissions')->cascadeOnDelete();
            $table->string('nom');
            $table->string('prenom');
            $table->string('etablissement');
            $table->string('universite');
            $table->string('grade');
            $table->string('specialite');
            $table->string('email');
            $table->string('telephone');
            $table->boolean('is_president')->default(false);
            $table->timestamps();

            $table->unique(['commission_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_members');
    }
};
