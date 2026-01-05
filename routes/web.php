<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AksesController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FreeradiusController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\mapsController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MitraController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnlinePppController;
use App\Http\Controllers\OpticalController;
use App\Http\Controllers\PProfileController;
use App\Http\Controllers\RadiusController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TransactionController;

use App\Http\Controllers\VpnController;
use App\Http\Controllers\WhatsappController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ConnectionController;
use Illuminate\Support\Facades\Route;


// Webhook Xendit
Route::prefix('notification')->group(function () {
    Route::post('/payment', [notificationController::class, 'notifPayment']);
    Route::post('/payout', [notificationController::class, 'notifPayoutLink']);
});

Route::get('/', function () {
    return view('errors.404');
});

Route::middleware('guest')->group(
    function () {
        // Route::redirect('/', '/login');

        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
            ->name('password.request');

        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
            ->name('password.email');

        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
            ->name('password.reset');

        Route::post('reset-password', [NewPasswordController::class, 'store'])
            ->name('password.store');
    }
);

Route::get('/horizon', function () {
    return redirect('/horizon/dashboard');
});



Route::middleware('isSignin')->group(
    function () {
        Route::get('/login', [AuthController::class, 'index'])->name('login');
        Route::post('/login', [AuthController::class, 'login']);
    }
);


// Route::get('/billing/invoice/{id}', [InvoiceController::class, 'show'])->name('billing.invoicePdf');
// Route::get('/billing/invoice', [{}])



Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::resource('/dashboard', DashboardController::class);

    // Area List
    Route::get('/areas/list', [AreaController::class, 'getAreaList']);


    // Role Super Admin
    // Route::resource('/mitra', mitraController::class);
    Route::get('/mitra', [MitraController::class, 'index'])->name('mitra.index');
    Route::get('/mitra/show/{id}', [MitraController::class, 'show'])->name('mitra.show');
    Route::get('/mitra/read', [MitraController::class, 'getData'])->name('mitra.getData');
    Route::post('/mitra', [MitraController::class, 'store'])->name('mitra.store');
    Route::delete('/mitra/{id}', [MitraController::class, 'destroy'])->name('mitra.destroy');
    Route::resource('/maps', mapsController::class);

    Route::get('/logs', [ActivityLogController::class, 'index'])->name('logs.index');
    Route::get('/logs/read', [ActivityLogController::class, 'getData'])->name('logs.getData');

    // Role Mitra
    // Mitra Service
    Route::prefix('ppp')->group(function () {
        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::get('/members/read', [MemberController::class, 'getData'])->name('members.getData');
        Route::put('/members/update/{id}', [MemberController::class, 'update']);
        Route::put('members/{id}/payment-detail', [MemberController::class, 'updatePaymentDetail'])->name('members.updatePD');

        // Route PPPoE - Now using ConnectionController
        Route::get('/pppoe', [ConnectionController::class, 'index'])->name('pppoe.index');
        Route::get('/pppoe/read', [ConnectionController::class, 'getData'])->name('pppoe.getData');
        Route::get('/pppoe/session/{username}', [ConnectionController::class, 'getSession'])->name('pppoe.getSession');
        Route::get('/pppoe/profile-price/{profileId}', [ConnectionController::class, 'getProfilePrice'])->name('pppoe.profile-price');
        Route::post('/pppoe/import', [ConnectionController::class, 'import'])->name('pppoe.import');
        Route::get('/pppoe/import/status/{batchId}', [ConnectionController::class, 'importStatus'])->name('import.status');

        Route::post('/pppoe/store', [ConnectionController::class, 'store'])->name('pppoe.store');
        Route::post('/pppoe/create-with-member', [ConnectionController::class, 'createWithMember'])->name('connection.create-with-member');
        Route::put('/pppoe/update/{id}', [ConnectionController::class, 'update'])->name('pppoe.update');
        Route::put('/pppoe/update/isolir/{id}', [ConnectionController::class, 'assignIsolirIp'])->name('pppoe.isolir');
        Route::post('/pppoe/disconnect/{id}', [ConnectionController::class, 'sendDisconnect'])->name('pppoe.disconnect');
        Route::delete('/pppoe/{id}', [ConnectionController::class, 'destroy'])->name('pppoe.destroy');
        Route::get('/pppoe/profile-price/{profileId}', [ConnectionController::class, 'getProfilePrice'])->name('pppoe.profile-price');

        // Route Online PPPoE
        Route::get('/online', [OnlinePppController::class, 'index'])->name('online.index');
        Route::get('/online/read', [OnlinePppController::class, 'getData'])->name('online.getData');

        Route::resource('/profiles', PProfileController::class);
        Route::get('/ppp/profiles/data', [PProfileController::class, 'getData'])->name('profiles.getData');
        Route::get('/settings', [SettingsController::class, 'settingPPP'])->name('ppp.settings');
        Route::put('/settings/{id}', [SettingsController::class, 'UpdateSetPPP'])->name('ppp.settings.update');
        Route::put('/settings/billing/{id}', [SettingsController::class, 'BillingSettings'])->name('ppp.settings.bill');
    });

    Route::prefix('server')->group(function () {
        Route::resource('/vpn', vpnController::class);
        Route::resource('/radius', radiusController::class);
    });

    Route::prefix('master-data')->group(function () {
        Route::resource('/optical', OpticalController::class);

        // Area
        Route::resource('/area', AreaController::class);
        Route::post('/area/assign-technician', [AreaController::class, 'assignTechnician'])
            ->name('area.assignTechnician');
        // Route::post('/area/unassign-technician', [AreaController::class, 'unassignTechnician'])
        //     ->name('area.unassignTechnician');
    });

    Route::group(['prefix' => 'tools/whatsapp'], function () {
        // Main page
        Route::get('/', [WhatsappController::class, 'index'])->name('whatsapp.index');

        // API Key management
        Route::post('/save-api-key', [WhatsappController::class, 'saveApiKey'])->name('whatsapp.saveapi');

        // Status and connection
        Route::get('/status', [WhatsappController::class, 'getStatus'])->name('whatsapp.status');
        Route::post('/test', [WhatsappController::class, 'testConnection'])->name('whatsapp.test');

        // Templates management
        Route::post('/templates/get', [WhatsappController::class, 'getTemplates'])->name('whatsapp.templates.get');
        Route::post('/templates/save', [WhatsappController::class, 'saveTemplate'])->name('whatsapp.templates.save');
        Route::post('/templates/reset', [WhatsappController::class, 'resetTemplate'])->name('whatsapp.templates.reset');

        // Broadcasting - TAMBAHKAN INI
        Route::post('/broadcast/count', [WhatsappController::class, 'getBroadcastCount'])->name('whatsapp.broadcast.count');
        Route::post('/broadcast', [WhatsappController::class, 'sendBroadcast'])->name('whatsapp.broadcast');

        // Message logs
        Route::get('/logs', [WhatsappController::class, 'getMessageLogs'])->name('whatsapp.logs');
    });

    Route::prefix('settings')->group(function () {
        Route::put('/profile', [AksesController::class, 'updateProfile'])->name('admin.updateProfile');
        Route::resource('/admin', AksesController::class);
        // Route::resource('/area', AreaController::class);
    });

    // Tambahkan route ini di web.php dalam Route::prefix('billing')->group(function () {

    Route::prefix('billing')->group(function () {
        Route::delete('/{id}', [InvoiceController::class, 'destroy']);

        // Check Invoice
        Route::post('/check', [InvoiceController::class, 'checkInvoice']);

        // Date Range Statistics (NEW)
        Route::get('/stats/daterange', [InvoiceController::class, 'getDateRangeStats'])->name('billing.daterangeStats');

        // Keep existing routes
        Route::get('/filter/years', [InvoiceController::class, 'getAvailableYears'])->name('billing.years');
        Route::get('/filter/months/{year}', [InvoiceController::class, 'getAvailableMonths'])->name('billing.months');
        Route::get('/stats/monthly', [InvoiceController::class, 'getMonthlyStats'])->name('billing.monthlyStats');

        Route::get('/setting', [TransactionController::class, 'settings'])->name('billing.setting');
        Route::post('/payout', [TransactionController::class, 'payout'])->name('billing.payout');
        Route::get('/payout/read', [TransactionController::class, 'getData'])->name('billing.payHistory');

        // Paid
        Route::get('/transaction', [InvoiceController::class, 'transaction'])->name('billing.transaction');
        Route::post('/paid/cancel', [InvoiceController::class, 'payCancel'])->name('billing.cancel');

        // Unpaid
        Route::get('/invoice', [InvoiceController::class, 'invoice'])->name('billing.invoice');
        Route::get('/unpaid', [InvoiceController::class, 'invoice'])->name('billing.unpaid');
        Route::get('/unpaid/read', [InvoiceController::class, 'getData'])->name('billing.getData');
        Route::post('/create_invoice', [InvoiceController::class, 'createInv'])->name('billing.create');
        Route::post('/generate', [InvoiceController::class, 'generateAll'])->name('billing.generate');
        Route::post('/unpaid/pay', [InvoiceController::class, 'payManual'])->name('billing.pay');
    });

    Route::prefix('accounting')->group(function () {
        Route::get('/', [AccountingController::class, 'index'])->name('accounting.index');
        Route::get('/data', [AccountingController::class, 'getData'])->name('accounting.getData');
        Route::get('/show/{id}', [AccountingController::class, 'show'])->name('accounting.show');
        Route::get('/stats', [AccountingController::class, 'getStats'])->name('accounting.getStats');
        Route::get('/export', [AccountingController::class, 'export'])->name('accounting.export');

        // Add Income & Expense
        Route::post('/expense/store', [AccountingController::class, 'storeExpense'])->name('accounting.storeExpense');
        Route::post('/income/store', [AccountingController::class, 'storeOtherIncome'])->name('accounting.storeOtherIncome');

        // Delete (only for mitra)
        Route::delete('/destroy/{id}', [AccountingController::class, 'destroy'])->name('accounting.destroy');
    });



    // Activity Log
    Route::middleware(['web', 'auth'])
        ->get('/logs', [ActivityLogController::class, 'index'])
        ->name('logs.index');



    Route::prefix('freeradius')->group(function () {
        Route::get('/', [freeradiusController::class, 'index'])->name('freeradius.index');
        Route::post('/', [freeradiusController::class, 'store'])->name('freeradius.store');
        Route::delete('/{id}', [freeradiusController::class, 'destroy'])->name('freeradius.destroy');
        // Route::post('/paid/cancel', [InvoiceController::class, 'payCancel'])->name('billing.cancel');

        // // Unpaid
        // Route::get('/unpaid', [InvoiceController::class, 'unpaid'])->name('billing.unpaid');
        // Route::get('/unpaid/read', [InvoiceController::class, 'getData'])->name('billing.getData');

        // Route::post('/unpaid/pay', [InvoiceController::class, 'payManual'])->name('billing.pay');

        // // Transaction
        // Route::get('/transaction', [transactionController::class, 'index'])->name('billing.transaction');
    });


    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');


    // Route::middleware('admin-role:superadmin')->group(function () {
    //     Route::get('/dashboard/super', [DashboardSuper::class, 'index'])->name('dashboard.superadmin');
    // });
    // Route::middleware('admin-role:mitra')->group(function () {
    //     Route::get('/dashboard/mitra', [DashboardMitra::class, 'index'])->name('dashboard.mitra');
    // });
    // Route::middleware('admin-role:kasir')->group(function () {
    //     Route::get('/dashboard/kasir', [DashboardKasir::class, 'index'])->name('dashboard.kasir');
    // });
    // Route::middleware('admin-role:teknisi')->group(function () {
    //     Route::get('/dashboard/teknisi', [DashboardTeknisi::class, 'index'])->name('dashboard.teknisi');
    // });
});

Route::post('/webhook/xendit/invoice', [InvoiceController::class, 'xenditCallback'])->name('xendit.callback');
// Route::middleware('auth')->group(function () {
//     Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//     Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//     Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
// });

// require __DIR__ . '/auth.php';
