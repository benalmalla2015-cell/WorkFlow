<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Web\AdminPortalController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FactoryPortalController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\SalesPortalController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/firebase-messaging-sw.js', [NotificationController::class, 'serviceWorker']);

Route::get('/verify/{orderNumber}', [DashboardController::class, 'verifyOrder'])->name('orders.verify');

Route::post('/locale', [LocaleController::class, 'switch'])->name('locale.switch');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/attachments/{attachment}', [DocumentController::class, 'downloadAttachment'])->name('attachments.download');
    Route::get('/orders/{order}/quotation-pdf', [OrderController::class, 'downloadQuotationPdf'])->name('orders.quotation-pdf');
    Route::get('/orders/{order}/invoice-pdf', [OrderController::class, 'downloadInvoicePdf'])->name('orders.invoice-pdf');
    Route::get('/notifications/{notificationId}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/firebase-token', [NotificationController::class, 'syncFirebaseToken'])->name('notifications.firebase-token');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');

    Route::prefix('sales')->name('sales.')->middleware('role:sales,admin')->group(function () {
        Route::get('/orders', [SalesPortalController::class, 'index'])->name('orders.index');
        Route::get('/orders/create', [SalesPortalController::class, 'create'])->name('orders.create');
        Route::post('/orders', [SalesPortalController::class, 'store'])->name('orders.store');
        Route::get('/orders/{order}/edit', [SalesPortalController::class, 'edit'])->name('orders.edit');
        Route::get('/orders/{order}/adjustment-request', [SalesPortalController::class, 'createAdjustment'])->name('orders.adjustments.create');
        Route::post('/orders/{order}/adjustment-request', [SalesPortalController::class, 'storeAdjustment'])->name('orders.adjustments.store');
        Route::put('/orders/{order}', [SalesPortalController::class, 'update'])->name('orders.update');
        Route::post('/orders/{order}/submit', [SalesPortalController::class, 'submitToFactory'])->name('orders.submit');
        Route::post('/orders/{order}/customer-approval', [SalesPortalController::class, 'customerApproval'])->name('orders.customer-approval');
        Route::post('/orders/{order}/confirm-payment', [SalesPortalController::class, 'confirmPayment'])->name('orders.confirm-payment');
        Route::post('/orders/{order}/quotation', [SalesPortalController::class, 'generateQuotation'])->name('orders.quotation.generate');
        Route::get('/orders/{order}/quotation', [SalesPortalController::class, 'downloadQuotation'])->name('orders.quotation.download');
        Route::post('/orders/{order}/invoice', [SalesPortalController::class, 'generateInvoice'])->name('orders.invoice.generate');
        Route::get('/orders/{order}/invoice', [SalesPortalController::class, 'downloadInvoice'])->name('orders.invoice.download');
    });

    Route::prefix('factory')->name('factory.')->middleware('role:factory,admin')->group(function () {
        Route::get('/orders', [FactoryPortalController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}/edit', [FactoryPortalController::class, 'edit'])->name('orders.edit');
        Route::get('/orders/{order}/adjustment-request', [FactoryPortalController::class, 'createAdjustment'])->name('orders.adjustments.create');
        Route::post('/orders/{order}/adjustment-request', [FactoryPortalController::class, 'storeAdjustment'])->name('orders.adjustments.store');
        Route::put('/orders/{order}', [FactoryPortalController::class, 'update'])->name('orders.update');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/dashboard', [AdminPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/orders/{order}/review', [AdminPortalController::class, 'reviewOrder'])->name('orders.review');
        Route::get('/orders/{order}/pending-changes', [AdminPortalController::class, 'reviewPendingChange'])->name('orders.pending-changes.review');
        Route::post('/orders/{order}/pending-changes/approve', [AdminPortalController::class, 'approvePendingChange'])->name('orders.pending-changes.approve');
        Route::post('/orders/{order}/pending-changes/reject', [AdminPortalController::class, 'rejectPendingChange'])->name('orders.pending-changes.reject');
        Route::post('/orders/{order}/pending-changes/revision', [AdminPortalController::class, 'requestPendingChangeRevision'])->name('orders.pending-changes.revision');
        Route::post('/orders/{order}/request-adjustment', [AdminPortalController::class, 'requestOrderAdjustment'])->name('orders.request-adjustment');
        Route::post('/orders/{order}/reject', [AdminPortalController::class, 'rejectOrder'])->name('orders.reject');
        Route::post('/orders/{order}/workflow-stage', [AdminPortalController::class, 'updateWorkflowStage'])->name('orders.workflow-stage');
        Route::post('/orders/{order}/approve', [AdminPortalController::class, 'approveOrder'])->name('orders.approve');
        Route::get('/users', [AdminPortalController::class, 'users'])->name('users.index');
        Route::post('/users', [AdminPortalController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{user}', [AdminPortalController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [AdminPortalController::class, 'deleteUser'])->name('users.delete');
        Route::post('/users/{user}/toggle-status', [AdminPortalController::class, 'toggleUserStatus'])->name('users.toggle-status');
        Route::get('/settings', [AdminPortalController::class, 'settings'])->name('settings.index');
        Route::put('/settings', [AdminPortalController::class, 'updateSettings'])->name('settings.update');
        Route::get('/audit-logs', [AdminPortalController::class, 'auditLogs'])->name('audit-logs.index');
    });
});
