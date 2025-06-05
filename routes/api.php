<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DisputeController;
use App\Http\Controllers\Api\GuaranteeController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\RestitutionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::get('me', [AuthController::class, 'me']);

    // User routes
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::put('users/{user}', [UserController::class, 'update']);
    Route::delete('users/{user}', [UserController::class, 'destroy']);
    Route::post('users/{user}/subscribe', [UserController::class, 'subscribe']);
    Route::post('verify/email', [UserController::class, 'verifyEmail']);
    Route::post('verify/phone', [UserController::class, 'verifyPhone']);

    // Guarantee routes
    Route::get('guarantees', [GuaranteeController::class, 'index']);
    Route::post('guarantees', [GuaranteeController::class, 'store']);
    Route::get('guarantees/{guarantee}', [GuaranteeController::class, 'show']);
    Route::put('guarantees/{guarantee}/status', [GuaranteeController::class, 'updateStatus']);
    Route::post('guarantees/{guarantee}/consent', [GuaranteeController::class, 'consent']);
    Route::post('guarantees/{guarantee}/accept', [GuaranteeController::class, 'accept']);

    // Dispute routes
    Route::get('disputes', [DisputeController::class, 'index']);
    Route::post('disputes', [DisputeController::class, 'store']);
    Route::get('disputes/{dispute}', [DisputeController::class, 'show']);
    Route::post('disputes/{dispute}/defense', [DisputeController::class, 'submitDefense']);
    Route::post('disputes/{dispute}/resolve', [DisputeController::class, 'resolve']);

    // Restitution routes
    Route::post('restitutions/{restitution}/process', [RestitutionController::class, 'process']);
    Route::post('restitutions/{restitution}/complete', [RestitutionController::class, 'complete']);

    // Profile routes
    Route::prefix('profiles')->group(function () {
        Route::post('/', [ProfileController::class, 'store']);
        Route::get('/{profile}', [ProfileController::class, 'show']);
        Route::put('/{profile}', [ProfileController::class, 'update']);
        Route::post('/{profile}/verify', [ProfileController::class, 'verify'])->middleware('role:arbitrator');
    });

    // Verification routes
    Route::post('/verify-nin', [VerificationController::class, 'verifyNIN']);
    Route::post('/verify-bvn', [VerificationController::class, 'verifyBVN']);
    Route::post('/verify-nin-otp', [VerificationController::class, 'verifyNINOTP']);
    Route::post('/verify-bvn-otp', [VerificationController::class, 'verifyBVNOTP']);
    Route::get('/verification-status', [VerificationController::class, 'getVerificationStatus']);

    // Business routes
    Route::prefix('businesses')->group(function () {
        Route::post('/', [BusinessController::class, 'store']);
        Route::get('/{business}', [BusinessController::class, 'show']);
        Route::put('/{business}', [BusinessController::class, 'update']);
        Route::post('/{business}/verify', [BusinessController::class, 'verify'])->middleware('role:arbitrator');
        Route::get('/{business}/members', [BusinessController::class, 'listMembers']);
        Route::post('/{business}/members', [BusinessController::class, 'addMember']);
        Route::delete('/{business}/members', [BusinessController::class, 'removeMember']);
        Route::post('/{business}/leave', [BusinessController::class, 'leaveBusiness']);
    });
}); 