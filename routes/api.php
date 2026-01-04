<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConnectionController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LogsController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\NasController;
use App\Http\Controllers\Api\OpticalController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\VpnController;
use App\Http\Controllers\Api\WhatsAppApiController;
use App\Http\Controllers\WhatsappController;
use Illuminate\Support\Facades\Route;

// WhatsApp API Routes
Route::post('/coba/send-message', [WhatsappController::class, 'testConnection']);
Route::post('/whatsapp/webhook/{groupId?}', [WhatsAppApiController::class, 'webhook']);

// Public Routes (No Auth Required)
Route::post('/v1/login', [AuthController::class, 'login']);
Route::post('/v1/signup', [AuthController::class, 'signup']);

// superadmin areas for signup dropdown (public access)
Route::get('/v1/areas/superadmin-areas', [AreaController::class, 'getSuperadminAreas']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('v1')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // ======Admin/Mitra Management (superadmin only)======
        Route::middleware('role:superadmin')->group(function () {
            Route::get('/admin/mitras', [AdminController::class, 'index']);
            Route::post('/admin/mitras', [AdminController::class, 'store']);
            Route::put('/admin/mitras/{id}', [AdminController::class, 'update']);
            Route::delete('/admin/mitras/{id}', [AdminController::class, 'destroy']);
        });

        //======Start Master Data======
        // Area
        Route::get('/areas', [AreaController::class, 'index']);
        Route::post('/areas', [AreaController::class, 'store']);
        Route::put('/areas/{id}', [AreaController::class, 'update']);
        Route::post('/areas/assign', [AreaController::class, 'assignTechnician']);
        Route::delete('/areas/{id}', [AreaController::class, 'destroy']);

        // Optical/ODP
        Route::get('/opticals', [OpticalController::class, 'index']);
        Route::post('/opticals', [OpticalController::class, 'store']);
        Route::put('/opticals/{id}', [OpticalController::class, 'update']);
        Route::delete('/opticals/{id}', [OpticalController::class, 'destroy']);
        //======End Master Data======

        //======Start NAS======
        // VPN
        Route::get('/nas/vpn', [VpnController::class, 'index']);
        Route::post('/nas/vpn', [VpnController::class, 'store']);
        Route::delete('/nas/vpn/{id}', [VpnController::class, 'destroy']);

        // NAS
        Route::get('/nas', [NasController::class, 'index']);
        Route::post('/nas', [NasController::class, 'store']);
        Route::delete('/nas/{id}', [NasController::class, 'destroy']);
        //======End NAS======

        // ==========Start PPP-DHCP=========
        // Session
        Route::get('/session-ppp', [SessionController::class, 'index']);

        //=== Connections ===
        Route::get('/connections', [ConnectionController::class, 'index']);
        Route::get('/connections/stats', [ConnectionController::class, 'stats']);
        Route::get('/connections/import-template', [ConnectionController::class, 'downloadImportTemplate']);

        //IMPORT
        Route::post('/connections/import', [ConnectionController::class, 'importConnections']);
        Route::get('/connections/import/batches', [ConnectionController::class, 'getImportBatches']);
        Route::get('/connections/import/batch/{batchId}', [ConnectionController::class, 'getImportBatchStatus']);
        Route::get('/connections/import/batch/{batchId}/errors', [ConnectionController::class, 'getImportErrors']);
        Route::delete('/connections/import/batch/{batchId}', [ConnectionController::class, 'deleteImportBatch']);

        // Create connection (basic - without member)
        Route::post('/connections', [ConnectionController::class, 'store']);

        // Create connection WITH member and payment detail
        Route::post('/connections/with-member', [ConnectionController::class, 'storeWithMember']);

        Route::put('/connections/{id}', [ConnectionController::class, 'update']);
        Route::post('/connections/{id}/toggle-isolir', [ConnectionController::class, 'toggleIsolir']);
        Route::delete('/connections/{id}', [ConnectionController::class, 'destroy']);
        Route::get('/connections/{username}/sessions', [ConnectionController::class, 'getSessions']);

        //=== Members ===
        Route::get('/members', [MemberController::class, 'index']);
        Route::get('/members/stats', [MemberController::class, 'stats']);
        Route::get('/members/{id}', [MemberController::class, 'show']);
        Route::put('/members/{id}', [MemberController::class, 'update']);
        Route::put('/members/{id}/payment-detail', [MemberController::class, 'updatePaymentDetail']);
        Route::get('/members/{id}/invoices', [MemberController::class, 'getInvoices']);

        // Profiles
        Route::get('/profiles', [ProfileController::class, 'index']);
        Route::post('/profiles', [ProfileController::class, 'store']);
        Route::put('/profiles/{id}', [ProfileController::class, 'update']);
        Route::delete('/profiles/{id}', [ProfileController::class, 'destroy']);
        // ==========End PPP-DHCP=========

        //=== Invoices ===
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/stats', [InvoiceController::class, 'stats']);
        Route::get('/invoices/date-range-stats', [InvoiceController::class, 'getDateRangeStats']);
        Route::post('/invoices/create', [InvoiceController::class, 'createInv']);
        Route::post('/invoices/generate-all', [InvoiceController::class, 'generateAll']);
        Route::post('/invoices/{id}/pay-manual', [InvoiceController::class, 'payManual']);
        Route::post('/invoices/{id}/cancel-payment', [InvoiceController::class, 'payCancel']);
        Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy']);

        // ========== Payouts ==========
        Route::get('/payouts', [PayoutController::class, 'index']);
        Route::get('/payouts/stats', [PayoutController::class, 'stats']);
        Route::get('/payouts/{id}', [PayoutController::class, 'show']);
        Route::post('/payouts', [PayoutController::class, 'createPayout']);
        Route::post('/payouts/{id}/check-status', [PayoutController::class, 'checkStatus']);
        Route::delete('/payouts/{id}', [PayoutController::class, 'destroy']);

        // Logs
        Route::get('/logs', [LogsController::class, 'index']);
    });
});
