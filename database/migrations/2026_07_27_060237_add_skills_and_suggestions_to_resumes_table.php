<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('resumes', function (Blueprint $table) {
        $table->json('skills')->nullable();
        $table->json('suggestions')->nullable();
    });
}

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropColumn([
                'skills',
                'suggestions',
            ]);
        });
    }
};