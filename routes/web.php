<?php

use App\Http\Controllers\Admin\AdminFacilityController;
use App\Http\Controllers\Admin\AdminFaqCategoryController;
use App\Http\Controllers\Admin\AdminFaqController;
use App\Http\Controllers\Admin\AdminInquiryController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminRateController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminReservationController;
use App\Http\Controllers\Customer\CustomerInquiryController;
use App\Http\Controllers\Customer\CustomerPaymentController;
use App\Http\Controllers\Customer\CustomerReservationController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/rooms', [PageController::class, 'rooms'])->name('rooms');
Route::get('/halls', [PageController::class, 'halls'])->name('halls');
Route::get('/packages', [PageController::class, 'packages'])->name('packages');

Route::get('/facilities/{facility}', [FacilityController::class, 'show'])->name('facilities.show');
Route::get('/faq', [FaqController::class, 'index'])->name('faq');

// Customer Routes (Verified omitted based on Phase 1 implementation where MustVerifyEmail is not used)
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('customer')->name('customer.')->group(function () {
        Route::get('/reservations', [CustomerReservationController::class, 'index'])->name('reservations.index');
        Route::post('/reservations', [CustomerReservationController::class, 'store'])->name('reservations.store');
        Route::get('/reservations/{reservation}', [CustomerReservationController::class, 'show'])->name('reservations.show');
        Route::patch('/reservations/{reservation}/reschedule', [CustomerReservationController::class, 'reschedule'])->name('reservations.reschedule');
        Route::patch('/reservations/{reservation}/cancel', [CustomerReservationController::class, 'cancel'])->name('reservations.cancel');

        Route::get('/reservations/{reservation}/payments', [CustomerPaymentController::class, 'index'])->name('payments.index');
        Route::post('/reservations/{reservation}/payments', [CustomerPaymentController::class, 'store'])->name('payments.store');
        Route::get('/payments/{payment}/proof/download', [CustomerPaymentController::class, 'downloadProof'])->name('payments.download');

        Route::get('/inquiries', [CustomerInquiryController::class, 'index'])->name('inquiries.index');
        Route::post('/inquiries', [CustomerInquiryController::class, 'store'])->name('inquiries.store');
        Route::get('/inquiries/{inquiry}', [CustomerInquiryController::class, 'show'])->name('inquiries.show');
    });
});

// Administrator Routes
Route::middleware(['auth', 'role:Administrator'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('dashboard');

    // Facilities
    Route::resource('facilities', AdminFacilityController::class);
    Route::patch('facilities/{facility}/status', [AdminFacilityController::class, 'toggleStatus'])->name('facilities.status');

    // Rates
    Route::resource('rates', AdminRateController::class)->except(['create', 'edit', 'destroy', 'store']);
    Route::post('facilities/{facility}/rates', [AdminRateController::class, 'store'])->name('rates.store');
    Route::patch('rates/{rate}/status', [AdminRateController::class, 'toggleStatus'])->name('rates.status');

    // Reservations
    Route::get('reservations', [AdminReservationController::class, 'index'])->name('reservations.index');
    Route::post('reservations', [AdminReservationController::class, 'storeWalkIn'])->name('reservations.store');
    Route::get('reservations/{reservation}', [AdminReservationController::class, 'show'])->name('reservations.show');
    Route::patch('reservations/{reservation}/approve', [AdminReservationController::class, 'approve'])->name('reservations.approve');
    Route::patch('reservations/{reservation}/reject', [AdminReservationController::class, 'reject'])->name('reservations.reject');
    Route::patch('reservations/{reservation}/cancel', [AdminReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::patch('reservations/{reservation}/reschedule', [AdminReservationController::class, 'reschedule'])->name('reservations.reschedule');

    // Payments
    Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
    Route::patch('payments/{payment}/verify', [AdminPaymentController::class, 'verify'])->name('payments.verify');
    Route::patch('payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('payments.reject');
    Route::get('payments/{payment}/proof/download', [AdminPaymentController::class, 'downloadProof'])->name('payments.download');

    // Inquiries
    Route::get('inquiries', [AdminInquiryController::class, 'index'])->name('inquiries.index');
    Route::get('inquiries/{inquiry}', [AdminInquiryController::class, 'show'])->name('inquiries.show');
    Route::post('inquiries/{inquiry}/respond', [AdminInquiryController::class, 'respond'])->name('inquiries.respond');
    Route::patch('inquiries/{inquiry}/status', [AdminInquiryController::class, 'updateStatus'])->name('inquiries.status');

    // FAQs
    Route::resource('faq-categories', AdminFaqCategoryController::class)->except(['create', 'edit', 'destroy', 'show']);
    Route::patch('faq-categories/{faq_category}/status', [AdminFaqCategoryController::class, 'toggleStatus'])->name('faq-categories.status');
    Route::resource('faqs', AdminFaqController::class)->except(['create', 'edit', 'destroy', 'show']);
    Route::patch('faqs/{faq}/status', [AdminFaqController::class, 'toggleStatus'])->name('faqs.status');

    // Reports
    Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
});

require __DIR__.'/auth.php';
