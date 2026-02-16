<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidature_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidature_id')->constrained('candidatures')->cascadeOnDelete();
            $table->decimal('audition_score', 6, 2)->nullable();
            $table->decimal('final_score', 6, 2)->nullable();
            $table->longText('pv_text')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('candidature_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidature_results');
    }
};
