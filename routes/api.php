<?php

use App\Http\Controllers\Api\AcsController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConnectionController;
use App\Http\Controllers\Api\CustomerPortal;
use App\Http\Controllers\Api\Dashboard;
use App\Http\Controllers\Api\EosController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LogsController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\NasController;
use App\Http\Controllers\Api\OpticalController;
use App\Http\Controllers\Api\PaymentSettingsController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\TemplatesController;
use App\Http\Controllers\Api\VpnController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WhatsappController;
use App\Http\Controllers\Api\WireguardController;

Route::post('/v1/login', [AuthController::class, 'login']);
Route::post('/v1/signup', [AuthController::class, 'signup']);
Route::post('/v1/send-token', [AuthController::class, 'sendToken']);
Route::post('/v1/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('v1')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/me/account-token', [AuthController::class, 'updateAccountToken']);
        Route::put('/me/whatsapp-token', [AuthController::class, 'updateWhatsappToken']);
        Route::put('/profile/update', [AuthController::class, 'updateProfile']);
        Route::put('/profile/password', [AuthController::class, 'updatePassword']);
        Route::post('/user/save-token', [AuthController::class, 'savePushToken']);

        // ======Engginer On SIte======
        Route::get('/engginer', [EosController::class, 'index']);
        Route::delete('/engginer/{id}', [EosController::class, 'destroy']);



        Route::get('/dashboard/stats', [Dashboard::class, 'stats']);
        Route::get('/dashboard/stats/ppp', [Dashboard::class, 'statsPppoe']);

        // ======Admin======
        Route::get('/admin', [AdminController::class, 'index']);
        Route::post('/admin', [AdminController::class, 'store']);
        Route::delete('/admin/{id}', [AdminController::class, 'destroy']);

        //======Start Master Data======
        // Area
        Route::get('/areas', [AreaController::class, 'index']);
        Route::post('/areas', [AreaController::class, 'store']);
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

        // Expense
        Route::prefix('expenses')->group(function () {
            Route::get('/', [ExpenseController::class, 'index']);
            Route::get('/summary', [ExpenseController::class, 'summary']);
            Route::get('/payment-admin', [ExpenseController::class, 'adminLedger']);
            Route::post('/setor', [ExpenseController::class, 'setorAdmin']);
            Route::post('/', [ExpenseController::class, 'store']);
            Route::delete('{id}', [ExpenseController::class, 'destroy']);
        });


        // ========== Payouts ==========
        Route::get('/payouts', [PayoutController::class, 'index']);
        Route::get('/payouts/stats', [PayoutController::class, 'stats']);
        Route::get('/payouts/{id}', [PayoutController::class, 'show']);
        Route::post('/payouts', [PayoutController::class, 'createPayout']);
        Route::post('/payouts/{id}/check-status', [PayoutController::class, 'checkStatus']);
        Route::delete('/payouts/{id}', [PayoutController::class, 'destroy']);

        Route::prefix('whatsapp')->group(function () {
            Route::get('/login', [WhatsAppController::class, 'loginQr']);
            Route::get('/status', [WhatsAppController::class, 'status']);
            Route::post('/broadcast/area', [WhatsAppController::class, 'broadcastArea']);
            Route::post('/broadcast/unpaid', [WhatsAppController::class, 'broadcastInvoice']);
            Route::get('/disconnect', [WhatsAppController::class, 'disconnect']);
            Route::get('/logs', [WhatsAppController::class, 'whatsappLog']);
        });

        Route::prefix('acs')->group(function () {
            Route::get('/', [AcsController::class, 'byGroup']);
            Route::post('/pppoe', [AcsController::class, 'searchPppoe']);
            Route::post('/sn', [AcsController::class, 'searchSn']);
            Route::post('/add-tag', [AcsController::class, 'addGroup']);
        });


        // Prefix /api/wireguard
        Route::prefix('wireguard')->group(function () {
            Route::get('/', [WireguardController::class, 'index']);
            Route::post('/', [WireguardController::class, 'store']);
            Route::delete('/{id}', [WireguardController::class, 'destroy']);
        });

        // Prefic New Invoice
        Route::prefix('inv')->group(function () {
            Route::get('/', [InvoiceController::class, 'index']);
            Route::get('/pdf/{inv_number}', [InvoiceController::class, 'generatePdf']);
            Route::get('/members/{id}', [InvoiceController::class, 'memberInvoices']);
            Route::get('/stats', [InvoiceController::class, 'stats']);
            Route::get('/paid', [InvoiceController::class, 'invoicePaid']);
            Route::post('/paid', [InvoiceController::class, 'manualPayment']);
            Route::post('/cancel', [InvoiceController::class, 'paymentCancel']);
            Route::post('/', [InvoiceController::class, 'store']);
            Route::post('/bulk', [InvoiceController::class, 'bulkInv']);
            Route::post('/bulk-payment', [InvoiceController::class, 'manualPaymentBulk']);
            Route::delete('/{id}', [InvoiceController::class, 'destroy']);
        });

        // Setting Invoice
        Route::prefix('payment-settings')->group(function () {
            Route::get('/', [PaymentSettingsController::class, 'index']);
            Route::put('/', [PaymentSettingsController::class, 'update']);
            // Route::delete('/{id}', [InvoiceController::class, 'destroy']);
        });


        // Template Whatsapp
        Route::prefix('templates')->group(function () {
            Route::get('/', [TemplatesController::class, 'index']);
            Route::get('/{type}', [TemplatesController::class, 'show']);
            Route::put('/{type}', [TemplatesController::class, 'update']);
            Route::post('/{type}/reset', [TemplatesController::class, 'reset']);
        });






        // Logs
        Route::get('/logs', [LogsController::class, 'index']);
    });
});


// Portal
Route::prefix('portal')->group(function () {
    // 1. Rute Publik (Login)
    Route::post('/auth/check', [CustomerPortal::class, 'checkIdentity']);
    Route::post('/auth/setup-pin', [CustomerPortal::class, 'setupPin']);
    Route::post('/auth/verify-pin', [CustomerPortal::class, 'verifyPin']);

    // 2. Rute Private (Butuh Token)
    Route::middleware('auth:sanctum')->group(function () {
        // Cukup panggil begini, URL-nya otomatis jadi /api/portal/dashboard
        Route::get('/dashboard', [CustomerPortal::class, 'index']);
        Route::put('/payment-settings', [PaymentSettingsController::class, 'update']);
    });
});
