<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CoverLetterController;
use App\Http\Controllers\Api\InterviewController;
use App\Http\Controllers\Api\ResumeController;

// Public Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/resumes/upload', [ResumeController::class, 'upload']);

    Route::get('/resumes', [ResumeController::class, 'index']);
    Route::get('/resumes/{resume}', [ResumeController::class, 'show']);
    Route::delete('/resumes/{resume}', [ResumeController::class, 'destroy']);

    // Untuk melihat hasil Resume Structure
    Route::post(
        '/resumes/structure',
        [CoverLetterController::class, 'structureResume']
    );

    // Untuk generate Cover Letter
    Route::post(
        '/cover-letters/generate',
        [CoverLetterController::class, 'generate']
    );

    // Untuk generate Interview Simulation
    Route::post(
        '/interview/generate',
        [InterviewController::class, 'generate']
    );
});