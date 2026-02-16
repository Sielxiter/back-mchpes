<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('current_step')->default(1);
            $table->enum('status', ['draft', 'submitted', 'blocked', 'approved', 'rejected'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique('user_id'); // One candidature per user
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidatures');
    }
};
