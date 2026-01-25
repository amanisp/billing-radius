<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConnectionController;
use App\Http\Controllers\Api\Dashboard;
use App\Http\Controllers\Api\FakturController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LogsController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\NasController;
use App\Http\Controllers\Api\OpticalController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\VpnController;
use App\Http\Controllers\Api\WhastappApi;
use App\Http\Controllers\WhatsappController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WhatsappController as ApiWhatsappController;

Route::post('/v1/login', [AuthController::class, 'login']);
Route::post('/v1/signup', [AuthController::class, 'signup']);
Route::post('/v1/send-token', [AuthController::class, 'sendToken']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('v1')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/me/whatsapp-token', [AuthController::class, 'updateWhatsappToken']);


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


        // ========== INVOICES (Faktur) ==========
        Route::get('/invoices', [FakturController::class, 'index']);
        Route::get('/invoices/member/{id}', [FakturController::class, 'invoiceByMemberId']);
        Route::post('/invoices', [FakturController::class, 'manualPayment']);
        Route::get('/invoices/details/{id}', [FakturController::class, 'fakturDetail']);
        Route::get('/invoices/stats', [FakturController::class, 'stats']);
        Route::get('/invoices/pdf/{inv_number}', [FakturController::class, 'single']);
        Route::get('/invoices/paid/all', [FakturController::class, 'invoicePaid']);
        Route::delete('/invoices/cancel/{id}', [FakturController::class, 'paymentCancel']);
        // ========== End INVOICES (Faktur) ==========
        // ========== INVOICES - WhatsApp AUTO NOTIFICATION ==========
        Route::prefix('invoice')->group(function () {
            Route::get('/', [InvoiceController::class, 'index']);
            Route::get('/stats', [InvoiceController::class, 'stats']);
            Route::get('/{id}', [InvoiceController::class, 'show']);
            Route::post('/pay', [InvoiceController::class, 'payInvoice']);
            Route::post('/{id}/resend-wa', [InvoiceController::class, 'resendWhatsappNotification']);
            Route::get('/{id}/wa-status', [InvoiceController::class, 'checkWhatsappStatus']);
        });

        // ========== Payouts ==========
        Route::get('/payouts', [PayoutController::class, 'index']);
        Route::get('/payouts/stats', [PayoutController::class, 'stats']);
        Route::get('/payouts/{id}', [PayoutController::class, 'show']);
        Route::post('/payouts', [PayoutController::class, 'createPayout']);
        Route::post('/payouts/{id}/check-status', [PayoutController::class, 'checkStatus']);
        Route::delete('/payouts/{id}', [PayoutController::class, 'destroy']);

        Route::prefix('whatsapp')->group(function () {

            // ========== Device Management ==========
            Route::post('/generate-qr', [ApiWhatsappController::class, 'generateQR']);
            Route::get('/device-info', [ApiWhatsappController::class, 'deviceInfo']);
            Route::post('/logout-device', [ApiWhatsappController::class, 'logoutDevice']);
            Route::post('/delete-device', [ApiWhatsappController::class, 'deleteDevice']);

            // ========== Messaging ==========
            Route::post('/send-message', [ApiWhatsappController::class, 'sendMessage']);
            Route::post('/send-media', [ApiWhatsappController::class, 'sendMedia']);
            Route::post('/send-sticker', [ApiWhatsappController::class, 'sendSticker']);
            Route::post('/send-button', [ApiWhatsappController::class, 'sendButton']);
            Route::post('/send-list', [ApiWhatsappController::class, 'sendList']);
            Route::post('/send-poll', [ApiWhatsappController::class, 'sendPoll']);
            Route::post('/send-location', [ApiWhatsappController::class, 'sendLocation']);
            Route::post('/send-vcard', [ApiWhatsappController::class, 'sendVCard']);
            Route::post('/send-product', [ApiWhatsappController::class, 'sendProduct']);
            Route::post('/send-text-channel', [ApiWhatsappController::class, 'sendTextChannel']);

            // ========== Number & User Info ==========
            Route::post('/check-number', [ApiWhatsappController::class, 'checkNumber']);
            Route::get('/user-info', [ApiWhatsappController::class, 'getUserInfo']);

            // ========== Legacy/Test Endpoints ==========
            Route::post('/coba/send-message', [ApiWhatsappController::class, 'testConnection']);
        });


        // Logs
        Route::get('/logs', [LogsController::class, 'index']);
    });
});
