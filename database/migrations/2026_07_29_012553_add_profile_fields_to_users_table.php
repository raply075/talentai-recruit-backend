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
        Schema::table('users', function (Blueprint $table) {

            $table->string('avatar')->nullable()->after('password');

            $table->string('job_title')->nullable()->after('avatar');

            $table->text('bio')->nullable()->after('job_title');

            $table->string('location')->nullable()->after('bio');

            $table->string('linkedin_url')->nullable()->after('location');

            $table->string('github_url')->nullable()->after('linkedin_url');

            $table->string('website')->nullable()->after('github_url');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropColumn([
                'avatar',
                'job_title',
                'bio',
                'location',
                'linkedin_url',
                'github_url',
                'website',
            ]);

        });
    }
};