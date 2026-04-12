<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Authentication Routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');

// Order Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::put('/orders/{order}', [OrderController::class, 'update']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    
    // Order Actions
    Route::post('/orders/{order}/approve', [OrderController::class, 'approveOrder']);
    Route::post('/orders/{order}/customer-approval', [OrderController::class, 'customerApproval']);
    Route::post('/orders/{order}/confirm-payment', [OrderController::class, 'confirmPayment']);
});

// Document Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/orders/{order}/quotation', [DocumentController::class, 'generateQuotation']);
    Route::post('/orders/{order}/invoice', [DocumentController::class, 'generateInvoice']);
    Route::get('/orders/{order}/download-quotation', [DocumentController::class, 'downloadQuotation']);
    Route::get('/orders/{order}/download-invoice', [DocumentController::class, 'downloadInvoice']);
    Route::get('/attachments/{attachment}/download', [DocumentController::class, 'downloadAttachment']);
});
Route::get('/orders/verify/{orderNumber}', [DocumentController::class, 'verifyOrder']);

// Admin Routes
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    // User Management
    Route::get('/users', [AdminController::class, 'getUsers']);
    Route::post('/users', [AdminController::class, 'createUser']);
    Route::put('/users/{user}', [AdminController::class, 'updateUser']);
    Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
    Route::post('/users/{user}/toggle-status', [AdminController::class, 'toggleUserStatus']);
    
    // Settings
    Route::get('/settings', [AdminController::class, 'getSettings']);
    Route::put('/settings', [AdminController::class, 'updateSettings']);
    
    // Dashboard
    Route::get('/dashboard/stats', [AdminController::class, 'getDashboardStats']);
    Route::get('/audit-logs', [AdminController::class, 'getAuditLogs']);
    Route::get('/orders', [AdminController::class, 'getAllOrders']);
    Route::get('/profit-analysis', [AdminController::class, 'getProfitAnalysis']);
});

// File Upload Routes
Route::middleware('auth:sanctum')->post('/upload', function (Request $request) {
    $request->validate([
        'file' => 'required|file|max:10240',
        'type' => 'required|in:sales_upload,factory_upload',
    ]);

    if ($request->hasFile('file')) {
        $disk = config('workflow.uploads_disk', 'public');
        $folder = $request->type === 'sales_upload'
            ? config('workflow.sales_upload_root', 'sales_uploads')
            : config('workflow.factory_upload_root', 'factory_uploads');
        $path = $request->file('file')->store($folder, $disk);

        return response()->json([
            'message' => 'File uploaded successfully',
            'path' => $path,
            'disk' => $disk,
        ]);
    }

    return response()->json(['message' => 'No file uploaded'], 400);
});
