<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CoverLetterController;
use App\Http\Controllers\Api\InterviewController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ResumeController;

// Public Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {

    // Authentication
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'me']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar']);
    Route::put('/profile/password', [ProfileController::class, 'changePassword']);

    // Resume
    Route::post('/resumes/upload', [ResumeController::class, 'upload']);
    Route::get('/resumes', [ResumeController::class, 'index']);
    Route::get('/resumes/{resume}', [ResumeController::class, 'show']);
    Route::delete('/resumes/{resume}', [ResumeController::class, 'destroy']);

    // Resume Structure
    Route::post(
        '/resumes/structure',
        [CoverLetterController::class, 'structureResume']
    );

    // Cover Letter
    Route::post(
        '/cover-letters/generate',
        [CoverLetterController::class, 'generate']
    );

    // AI Interview
    Route::post(
        '/interview/generate',
        [InterviewController::class, 'generate']
    );
});