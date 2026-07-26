<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::create('resume_analyses', function (Blueprint $table) {

        $table->id();

        $table->foreignId('resume_id')
            ->constrained()
            ->cascadeOnDelete();

        $table->text('summary')->nullable();

        $table->json('strength')->nullable();

        $table->json('weakness')->nullable();

        $table->json('missing_skills')->nullable();

        $table->json('suggestions')->nullable();

        $table->json('recommended_roles')->nullable();

        $table->longText('raw_response')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resume_analyses');
    }
};
