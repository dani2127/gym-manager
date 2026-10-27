<?php

use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\BranchClosureController;
use App\Http\Controllers\Api\V1\Admin\BranchController;
use App\Http\Controllers\Api\V1\Admin\OpeningHoursController;
use App\Http\Controllers\Api\V1\Admin\SettingsController;
use App\Http\Controllers\Api\V1\Admin\StaffController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RegisterOrganizationController;
use App\Http\Controllers\Api\V1\Billing\InvoiceController;
use App\Http\Controllers\Api\V1\Billing\MembershipController;
use App\Http\Controllers\Api\V1\Billing\SaleController;
use App\Http\Controllers\Api\V1\Billing\TakingsController;
use App\Http\Controllers\Api\V1\Catalog\DiscountRuleController;
use App\Http\Controllers\Api\V1\Catalog\FeeController;
use App\Http\Controllers\Api\V1\Catalog\PlanController;
use App\Http\Controllers\Api\V1\Me\PhoneVerificationController;
use App\Http\Controllers\Api\V1\Me\ProfileController;
use App\Http\Controllers\Api\V1\Me\TelegramController;
use App\Http\Controllers\Api\V1\Members\MemberController;
use App\Http\Controllers\Api\V1\Members\MemberGroupController;
use App\Http\Controllers\Api\V1\Members\MemberPhotoController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\Webhooks\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', [MetaController::class, 'health']);
    Route::get('/meta/config', [MetaController::class, 'config']);

    // --- Public auth --------------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('/register', RegisterOrganizationController::class)->middleware('throttle:registration');
        Route::post('/login', [LoginController::class, 'store']);
        Route::post('/password/forgot', [PasswordResetController::class, 'sendCode'])->middleware('throttle:otp');
        Route::post('/password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:otp-verify');
    });

    Route::post('/webhooks/telegram', TelegramWebhookController::class);

    // --- Authenticated, tenant-scoped -------------------------------------
    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::post('/auth/logout', [LoginController::class, 'destroy']);

        Route::prefix('me')->group(function () {
            Route::get('/', [ProfileController::class, 'show']);
            Route::patch('/', [ProfileController::class, 'update']);
            Route::put('/password', [ProfileController::class, 'updatePassword']);
            Route::post('/telegram/link', [TelegramController::class, 'link']);
            Route::delete('/telegram', [TelegramController::class, 'unlink']);
            Route::post('/phone/verification', [PhoneVerificationController::class, 'send'])->middleware('throttle:otp');
            Route::post('/phone/verify', [PhoneVerificationController::class, 'verify'])->middleware('throttle:otp-verify');
        });

        // Staff workspaces (desk, admin, trainer).
        Route::middleware('staff')->group(function () {
            Route::apiResource('branches', BranchController::class)->except('destroy');
            Route::put('/branches/{branch}/opening-hours', [OpeningHoursController::class, 'update']);
            Route::get('/branches/{branch}/closures', [BranchClosureController::class, 'index']);
            Route::post('/branches/{branch}/closures', [BranchClosureController::class, 'store']);
            Route::delete('/branches/{branch}/closures/{closure}', [BranchClosureController::class, 'destroy']);
            Route::get('/branches/{branch}/settings', [SettingsController::class, 'showBranch']);
            Route::patch('/branches/{branch}/settings', [SettingsController::class, 'updateBranch']);

            Route::get('/settings', [SettingsController::class, 'show']);
            Route::patch('/settings', [SettingsController::class, 'update']);

            Route::apiResource('staff', StaffController::class)->except('destroy');

            Route::get('/audit-logs', [AuditLogController::class, 'index']);

            Route::apiResource('members', MemberController::class);
            Route::get('/members/{member}/photo', [MemberPhotoController::class, 'show']);
            Route::post('/members/{member}/photo', [MemberPhotoController::class, 'store']);
            Route::delete('/members/{member}/photo', [MemberPhotoController::class, 'destroy']);
            Route::post('/member-groups', [MemberGroupController::class, 'store']);
            Route::patch('/member-groups/{memberGroup}', [MemberGroupController::class, 'update']);
            Route::delete('/member-groups/{memberGroup}', [MemberGroupController::class, 'destroy']);
            Route::get('/members/{member}/memberships', [MembershipController::class, 'index']);

            // Catalog: everyone reads plans and fees; owners change them.
            Route::apiResource('plans', PlanController::class)->except('show');
            Route::apiResource('fees', FeeController::class)->except(['show', 'destroy']);
            Route::apiResource('discount-rules', DiscountRuleController::class)->except(['show', 'destroy']);

            // Desk sales and invoices.
            Route::post('/branches/{branch}/sales/quote', [SaleController::class, 'quote']);
            Route::post('/branches/{branch}/sales', [SaleController::class, 'store']);
            Route::get('/branches/{branch}/takings', TakingsController::class);
            Route::get('/invoices', [InvoiceController::class, 'index']);
            Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
            Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'pay']);
            Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void']);
            Route::get('/invoices/{invoice}/receipt', [InvoiceController::class, 'receipt']);
        });
    });
});
