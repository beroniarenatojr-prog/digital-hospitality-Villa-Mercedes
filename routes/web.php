<?php

use App\Enums\Role;
use App\Http\Controllers\Admin\AdminActivityLogController;
use App\Http\Controllers\Admin\AdminCustomerController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDecisionBoardController;
use App\Http\Controllers\Admin\AdminFacilityController;
use App\Http\Controllers\Admin\AdminFaqCategoryController;
use App\Http\Controllers\Admin\AdminFaqController;
use App\Http\Controllers\Admin\AdminInquiryController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminPagePhotoController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminRateController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminReservationController;
use App\Http\Controllers\Admin\AdminResortServiceController;
use App\Http\Controllers\ArrivalPassController;
use App\Http\Controllers\AvailabilitySearchController;
use App\Http\Controllers\Customer\CustomerBookingController;
use App\Http\Controllers\Customer\CustomerInquiryController;
use App\Http\Controllers\Customer\CustomerNotificationController;
use App\Http\Controllers\Customer\CustomerPaymentController;
use App\Http\Controllers\Customer\CustomerReservationController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationIntentController;
use App\Http\Controllers\ResortController;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/home/featured', [PageController::class, 'featured'])->name('home.featured');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/reviews', [PageController::class, 'reviews'])->name('reviews');
Route::get('/gallery', [PageController::class, 'gallery'])->name('gallery');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::redirect('/reserve-space', '/rooms')->name('reserve-space');
Route::get('/rooms', [PageController::class, 'rooms'])->name('rooms');
Route::get('/halls', [PageController::class, 'halls'])->name('halls');
Route::get('/packages', [PageController::class, 'packages'])->name('packages');
Route::get('/cottages', [PageController::class, 'cottages'])->name('cottages');
Route::get('/pools', [PageController::class, 'pools'])->name('pools');
Route::get('/resort', [ResortController::class, 'show'])->name('resort.show');
Route::get('/resort/cottages/availability', [ResortController::class, 'cottageAvailability'])
    ->middleware('throttle:60,1')
    ->name('resort.cottages.availability');
Route::get('/availability/search', AvailabilitySearchController::class)
    ->middleware('throttle:60,1')
    ->name('availability.search');

Route::get('/facilities/{facility}', [FacilityController::class, 'show'])->name('facilities.show');
Route::get('/book/confirm/{draft}', fn () => abort(410, 'Please sign in and start a reservation from the service page.'))
    ->middleware('auth')
    ->name('customer.booking.magic-link.consume');
Route::get('/reservation/{facility}', [CustomerBookingController::class, 'create'])->middleware('auth')->name('customer.booking.create');
Route::get('/book/{facility}', function (Request $request, Facility $facility) {
    return redirect()->route('customer.booking.create', ['facility' => $facility, ...$request->only('check_in', 'check_out', 'arrival', 'departure', 'guests', 'date', 'session')]);
})->middleware('auth')->name('customer.booking.legacy');
Route::post('/book/{facility}/calculate', [CustomerBookingController::class, 'calculate'])->middleware('auth')->name('customer.booking.calculate');
Route::get('/facilities/{facility}/availability', [CustomerBookingController::class, 'availability'])->name('facilities.availability');
Route::post('/facilities/{facility}/availability', [CustomerBookingController::class, 'calculate'])->middleware('throttle:60,1')->name('facilities.availability.calculate');
Route::post('/reservation-intent', [ReservationIntentController::class, 'store'])->middleware('throttle:30,1')->name('reservation.intent');
Route::post('/book/{facility}/magic-link', fn () => abort(410, 'Please use the authenticated reservation form.'))
    ->middleware('auth')
    ->name('customer.booking.magic-link.send');
Route::redirect('/facilities', '/rooms')->name('facilities.index');
Route::get('/faq', [FaqController::class, 'index'])->name('faq.index');
Route::get('/arrival/{reservation:reservation_code}', [ArrivalPassController::class, 'show'])
    ->middleware('signed')
    ->name('reservations.arrival-pass');

// Customer routes stay auth-only. Registration OTP verifies email before User creation,
// so verified middleware is intentionally not applied to this group.
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();

        if ($user instanceof User && $user->hasRole(Role::Administrator->value)) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('customer.reservations.index');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('customer')->name('customer.')->group(function () {
        Route::get('/facilities/{facility}', [FacilityController::class, 'show'])->name('facilities.show');
        Route::get('/reservations/new', \App\Http\Controllers\Customer\ReservationSelectionController::class)->name('reservations.new');
        Route::post('/reservations/{reservation}/review', [\App\Http\Controllers\Customer\ReviewController::class, 'store'])->name('reviews.store');
        Route::get('/reservations', [CustomerReservationController::class, 'index'])->name('reservations.index');
        Route::post('/reservations', [CustomerReservationController::class, 'store'])->name('reservations.store');
        Route::get('/reservations/{reservation}', [CustomerReservationController::class, 'show'])->name('reservations.show');
        Route::post('/reservations/{reservation}/calculate-reschedule', [CustomerReservationController::class, 'calculateReschedule'])->name('reservations.calculate-reschedule');
        Route::patch('/reservations/{reservation}/reschedule', [CustomerReservationController::class, 'reschedule'])->name('reservations.reschedule');
        Route::patch('/reservations/{reservation}/cancel', [CustomerReservationController::class, 'cancel'])->name('reservations.cancel');

        Route::get('/reservations/{reservation}/payments', [CustomerPaymentController::class, 'index'])->name('payments.index');
        Route::post('/reservations/{reservation}/payments', [CustomerPaymentController::class, 'store'])->name('payments.store');
        Route::post('/reservations/{reservation}/payments/checkout', [CustomerPaymentController::class, 'checkout'])->name('payments.checkout');
        Route::get('/payments/{payment}/return', [CustomerPaymentController::class, 'returnFromGateway'])->withTrashed()->name('payments.return');
        Route::get('/payments/{payment}/cancel', [CustomerPaymentController::class, 'cancelGateway'])->name('payments.cancel');
        Route::get('/payments/{payment}/proof', [CustomerPaymentController::class, 'viewProof'])->name('payments.view');
        Route::get('/payments/{payment}/proof/download', [CustomerPaymentController::class, 'downloadProof'])->name('payments.download');

        Route::get('/inquiries', [CustomerInquiryController::class, 'index'])->name('inquiries.index');
        Route::post('/inquiries', [CustomerInquiryController::class, 'store'])->name('inquiries.store');
        Route::get('/inquiries/{inquiry}', [CustomerInquiryController::class, 'show'])->name('inquiries.show');

        Route::get('/notifications', [CustomerNotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{id}/open', [CustomerNotificationController::class, 'open'])->name('notifications.open');
        Route::post('/notifications/read-all', [CustomerNotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-as-read');
        Route::post('/notifications/{id}/read', [CustomerNotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
    });
});

// Administrator Routes
Route::get('/payment-destinations/{destination}/qr', [\App\Http\Controllers\Admin\PaymentDestinationController::class, 'image'])->middleware('auth')->name('payment-destinations.image');
Route::middleware(['administrator'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/payment-destinations', [\App\Http\Controllers\Admin\PaymentDestinationController::class, 'index'])->name('payment-destinations.index');
    Route::post('/payment-destinations', [\App\Http\Controllers\Admin\PaymentDestinationController::class, 'store'])->name('payment-destinations.store');
    Route::patch('/payment-destinations/{destination}', [\App\Http\Controllers\Admin\PaymentDestinationController::class, 'toggle'])->name('payment-destinations.toggle');
    Route::get('/reviews', [\App\Http\Controllers\Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reservations/{reservation}/deposit-top-up', [AdminPaymentController::class, 'requestTopUp'])->name('payments.top-up');
    Route::patch('/reviews/{review}', [\App\Http\Controllers\Admin\ReviewController::class, 'update'])->name('reviews.update');
    Route::post('/reservations/{reservation}/payments', [AdminPaymentController::class, 'storeOnSite'])->name('payments.onsite');
    Route::redirect('/', '/admin/dashboard');
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}/open', [AdminNotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [AdminNotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-as-read');

    // Facilities
    Route::resource('facilities', AdminFacilityController::class);
    Route::patch('facilities/{facility}/status', [AdminFacilityController::class, 'changeStatus'])->name('facilities.status');
    Route::post('facilities/{facility}/amenities', [AdminFacilityController::class, 'syncAmenities'])->name('facilities.amenities');
    Route::post('facilities/{facility}/components', [AdminFacilityController::class, 'syncComponents'])->name('facilities.components');
    Route::delete('facilities/{facility}/images/{image}', [AdminFacilityController::class, 'destroyImage'])->name('facilities.images.destroy');

    Route::get('photos', [AdminPagePhotoController::class, 'index'])->name('photos.index');
    Route::post('photos', [AdminPagePhotoController::class, 'store'])->name('photos.store');
    Route::patch('photos/{page_photo}', [AdminPagePhotoController::class, 'update'])->name('photos.update');
    Route::delete('photos/{page_photo}', [AdminPagePhotoController::class, 'destroy'])->name('photos.destroy');

    Route::get('resort-services', [AdminResortServiceController::class, 'edit'])->name('resort-services.edit');
    Route::put('resort-services', [AdminResortServiceController::class, 'update'])->name('resort-services.update');

    // Rates
    Route::get('rates', [AdminRateController::class, 'index'])->name('rates.index');
    Route::get('rates/create', [AdminRateController::class, 'create'])->name('rates.create');
    Route::get('rates/{rate}', [AdminRateController::class, 'show'])->name('rates.show');
    Route::get('rates/{rate}/edit', [AdminRateController::class, 'edit'])->name('rates.edit');
    Route::put('rates/{rate}', [AdminRateController::class, 'update'])->name('rates.update');
    Route::post('facilities/{facility}/rates', [AdminRateController::class, 'store'])->name('rates.store');
    Route::patch('rates/{rate}/activate', [AdminRateController::class, 'activate'])->name('rates.activate');
    Route::patch('rates/{rate}/deactivate', [AdminRateController::class, 'deactivate'])->name('rates.deactivate');

    // Reservations
    Route::get('reservations', [AdminReservationController::class, 'index'])->name('reservations.index');
    Route::post('reservations', [AdminReservationController::class, 'storeWalkIn'])->name('reservations.store');
    Route::get('reservations/{reservation}', [AdminReservationController::class, 'show'])->name('reservations.show');
    Route::patch('reservations/{reservation}/approve', [AdminReservationController::class, 'approve'])->name('reservations.approve');
    Route::patch('reservations/{reservation}/reject', [AdminReservationController::class, 'reject'])->name('reservations.reject');
    Route::patch('reservations/{reservation}/cancel', [AdminReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::patch('reservations/{reservation}/confirm-withdrawal', [AdminReservationController::class, 'confirmWithdrawal'])->name('reservations.confirm-withdrawal');
    Route::patch('reservations/{reservation}/complete', [AdminReservationController::class, 'complete'])->name('reservations.complete');
    Route::patch('reservations/{reservation}/check-in', [AdminReservationController::class, 'checkIn'])->name('reservations.check-in');
    Route::patch('reservations/{reservation}/check-out', [AdminReservationController::class, 'checkOut'])->name('reservations.check-out');
    Route::post('reservations/{reservation}/calculate-reschedule', [AdminReservationController::class, 'calculateReschedule'])->name('reservations.calculate-reschedule');
    Route::patch('reservations/{reservation}/reschedule', [AdminReservationController::class, 'reschedule'])->name('reservations.reschedule');

    // Payments
    Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
    Route::patch('payments/{payment}/verify', [AdminPaymentController::class, 'verify'])->name('payments.verify');
    Route::patch('payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('payments.reject');
    Route::get('payments/{payment}/proof', [AdminPaymentController::class, 'viewProof'])->name('payments.view');
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

    // Customers
    Route::get('customers', [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');

    // Owner Decision Board
    Route::get('decision-board', [AdminDecisionBoardController::class, 'index'])->name('decision-board');

    // Reports
    Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');

    Route::get('activity-log', [AdminActivityLogController::class, 'index'])->name('activity-log.index');
});

require __DIR__.'/auth.php';
