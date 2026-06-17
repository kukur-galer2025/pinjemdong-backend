<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RentalController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\UserVerificationController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\Admin\RentalManagementController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\ProductManagementController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserAddressController;
use App\Http\Controllers\Api\RentalPackageController;

/*
|--------------------------------------------------------------------------
| Public Routes (No Auth Required)
|--------------------------------------------------------------------------
*/
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'googleCallback']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// Public catalog
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);
Route::post('/products/{slug}/check-availability', [ProductController::class, 'checkAvailability']);
Route::get('/bank-accounts', [BankAccountController::class, 'index']);
Route::get('/products/{slug}/reviews', [ReviewController::class, 'getByProduct']);

// Packages
Route::get('/packages', [RentalPackageController::class, 'index']);
Route::get('/packages/{slug}', [RentalPackageController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Auth Required)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Auth & Profile
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'changePassword']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // KYC Verification
    Route::post('/verification/submit', [UserVerificationController::class, 'submit']);
    Route::get('/verification/status', [UserVerificationController::class, 'status']);

    // Wishlist
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/{productId}', [WishlistController::class, 'toggle']);

    // Rentals
    Route::get('/rentals', [RentalController::class, 'index']);
    Route::get('/rentals/{id}', [RentalController::class, 'show']);
    Route::post('/rentals', [RentalController::class, 'store']);
    Route::post('/rentals/{id}/cancel', [RentalController::class, 'cancel']);

    // Payments
    Route::post('/rentals/{rentalId}/payments', [PaymentController::class, 'uploadProof']);
    Route::get('/rentals/{rentalId}/payments', [PaymentController::class, 'getByRental']);

    // Reviews
    Route::post('/reviews', [ReviewController::class, 'store']);
    
    // Chats
    Route::get('/chats', [App\Http\Controllers\Api\ChatController::class, 'index']);
    Route::post('/chats', [App\Http\Controllers\Api\ChatController::class, 'store']);
    Route::post('/chats/read', [App\Http\Controllers\Api\ChatController::class, 'markAsRead']);

    // User Addresses
    Route::get('/user/addresses', [UserAddressController::class, 'index']);
    Route::post('/user/addresses', [UserAddressController::class, 'store']);
    Route::put('/user/addresses/{id}', [UserAddressController::class, 'update']);
    Route::delete('/user/addresses/{id}', [UserAddressController::class, 'destroy']);

    // Notifications (changed to user-alerts to bypass adblockers)
    Route::get('/user-alerts', [NotificationController::class, 'index']);
    Route::post('/user-alerts/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::post('/user-alerts/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);

    /*
    |----------------------------------------------------------------------
    | Admin Routes
    |----------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware('admin')->group(function () {
        // Dashboard
        Route::get('/stats', [DashboardController::class, 'stats']);

        // KYC Management
        Route::get('/kyc', [DashboardController::class, 'kycList']);
        Route::put('/kyc/{id}', [DashboardController::class, 'kycUpdate']);

        // Payment Management
        Route::get('/payments', [DashboardController::class, 'paymentList']);
        Route::put('/payments/{id}/confirm', [RentalManagementController::class, 'confirmPayment']);

        // Rental Management
        Route::get('/rentals', [RentalManagementController::class, 'index']);
        Route::get('/rentals/export', [RentalManagementController::class, 'exportExcel']);
        Route::get('/rentals/export/pdf', [RentalManagementController::class, 'exportPdf']);
        Route::get('/rentals/{id}/invoice', [RentalManagementController::class, 'generateInvoice']);
        Route::put('/rentals/{id}/status', [RentalManagementController::class, 'updateStatus']);

        // User Management
        Route::get('/users', [DashboardController::class, 'userList']);
        Route::put('/users/{id}/blacklist', [RentalManagementController::class, 'blacklistUser']);

        // Product Management
        Route::get('/products', [ProductManagementController::class, 'index']);
        Route::get('/products/{id}', [ProductManagementController::class, 'show']);
        Route::post('/products', [ProductManagementController::class, 'store']);
        Route::put('/products/{id}', [ProductManagementController::class, 'update']);
        Route::delete('/products/{id}', [ProductManagementController::class, 'destroy']);
        Route::delete('/products/{id}/images/{imageId}', [ProductManagementController::class, 'destroyImage']);
        Route::put('/products/{id}/images/{imageId}/primary', [ProductManagementController::class, 'setPrimaryImage']);

        // Review Management
        Route::get('/reviews', [\App\Http\Controllers\Api\Admin\ReviewManagementController::class, 'index']);
        Route::put('/reviews/{id}/reply', [\App\Http\Controllers\Api\Admin\ReviewManagementController::class, 'reply']);

        // Package Management
        Route::get('/packages', [\App\Http\Controllers\Api\Admin\PackageManagementController::class, 'index']);
        Route::get('/packages/{id}', [\App\Http\Controllers\Api\Admin\PackageManagementController::class, 'show']);
        Route::post('/packages', [\App\Http\Controllers\Api\Admin\PackageManagementController::class, 'store']);
        Route::post('/packages/{id}', [\App\Http\Controllers\Api\Admin\PackageManagementController::class, 'update']); // Using POST with _method=PUT to support FormData
        Route::delete('/packages/{id}', [\App\Http\Controllers\Api\Admin\PackageManagementController::class, 'destroy']);
        // Category Management
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
    });
});
