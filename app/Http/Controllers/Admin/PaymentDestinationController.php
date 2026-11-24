<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentDestination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PaymentDestinationController extends Controller
{
    public function index() { return view('admin.payments.destinations', ['destinations' => PaymentDestination::latest()->get()]); }

    public function store(Request $request)
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(['gcash', 'maya', 'bank_transfer'])],
            'account_name' => ['required', 'string', 'max:255'], 'account_number' => ['required', 'string', 'max:100'],
            'bank' => ['nullable', 'string', 'max:100'], 'qr' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
            'verified_account' => ['accepted'],
        ]);
        $path = $request->file('qr')->store('payment-qr', 'uploads');
        abort_unless($path, 500, 'The QR upload could not be stored.');
        $destination = PaymentDestination::create([...collect($data)->except(['qr', 'verified_account'])->all(), 'qr_path' => $path, 'is_active' => true, 'updated_by' => $request->user()->id]);
        activity()->performedOn($destination)->causedBy($request->user())->event('payment_destination_added')->log('Verified resort payment instructions activated.');
        return back()->with('success', 'QR payment account added.');
    }

    public function toggle(Request $request, PaymentDestination $destination)
    {
        $request->validate(['is_active' => ['required', 'boolean'], 'verified_account' => $request->boolean('is_active') ? ['accepted'] : ['nullable']]);
        $destination->update(['is_active' => $request->boolean('is_active'), 'updated_by' => $request->user()->id]);
        activity()->performedOn($destination)->causedBy($request->user())->event('payment_destination_updated')->log('QR payment account visibility updated.');
        return back()->with('success', 'Payment account updated. Upload a replacement and disable the old account when details change.');
    }

    public function image(Request $request, PaymentDestination $destination)
    {
        abort_unless($destination->is_active || $request->user()->hasRole('Administrator'), 404);
        abort_unless(Storage::disk('uploads')->exists($destination->qr_path), 404);
        $disk = Storage::disk('uploads');
        if ($request->boolean('download')) {
            $extension = strtolower(pathinfo($destination->qr_path, PATHINFO_EXTENSION));
            $extension = in_array($extension, ['png', 'jpg', 'jpeg'], true) ? '.'.$extension : '';

            return $disk->download($destination->qr_path, "villa-mercedes-{$destination->method->value}-qr{$extension}");
        }

        return $disk->response($destination->qr_path, null, ['X-Content-Type-Options' => 'nosniff']);
    }
}
