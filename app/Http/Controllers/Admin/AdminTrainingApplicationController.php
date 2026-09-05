<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TrainingApplicationStatus;
use App\Exceptions\Training\TrainingApplicationException;
use App\Http\Controllers\Controller;
use App\Models\TrainingApplication;
use App\Services\TrainingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminTrainingApplicationController extends Controller
{
    public function __construct(
        private readonly TrainingService $service
    ) {}

    public function index(Request $request): View
    {
        $applications = TrainingApplication::with('trainingBatch')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('batch'), fn ($q) => $q->where('training_batch_id', $request->input('batch')))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.training-applications.index', compact('applications'));
    }

    public function updateStatus(Request $request, TrainingApplication $trainingApplication): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(TrainingApplicationStatus::values())],
            'staff_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->service->decideApplication(
                $request->user(),
                $trainingApplication,
                TrainingApplicationStatus::from($validated['status']),
                $validated['staff_notes'] ?? null
            );
        } catch (TrainingApplicationException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Application updated.');
    }
}
