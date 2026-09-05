<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TrainingApplicationStatus;
use App\Enums\TrainingBatchStatus;
use App\Exceptions\Training\TrainingScheduleConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TrainingBatchRequest;
use App\Models\Facility;
use App\Models\TrainingBatch;
use App\Services\TrainingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTrainingBatchController extends Controller
{
    public function __construct(
        private readonly TrainingService $service
    ) {}

    public function index(Request $request): View
    {
        $batches = TrainingBatch::with('facilities')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%' . $request->input('search') . '%';
                $q->where(fn ($inner) => $inner->where('cohort_code', 'like', $search)->orWhere('program', 'like', $search));
            })
            ->orderByDesc('start_date')
            ->paginate(15);

        $pendingApplications = \App\Models\TrainingApplication::where('status', TrainingApplicationStatus::Pending->value)->count();

        return view('admin.training-batches.index', compact('batches', 'pendingApplications'));
    }

    public function create(): View
    {
        return view('admin.training-batches.create', [
            'batch' => new TrainingBatch(['status' => TrainingBatchStatus::Planned->value]),
            'facilities' => $this->bookableFacilities(),
            'programs' => $this->programOptions(),
        ]);
    }

    public function store(TrainingBatchRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $facilityIds = $data['facility_ids'];
        unset($data['facility_ids']);

        try {
            $batch = $this->service->createBatch($request->user(), $data, $facilityIds);
        } catch (TrainingScheduleConflictException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.training-batches.show', $batch)
            ->with('success', "Training batch {$batch->cohort_code} scheduled.");
    }

    public function show(TrainingBatch $trainingBatch): View
    {
        $trainingBatch->load(['facilities.facilityType', 'applications']);

        return view('admin.training-batches.show', [
            'batch' => $trainingBatch,
            'conflicts' => $this->service->conflictsFor($trainingBatch, $trainingBatch->facilities->pluck('id')->all()),
        ]);
    }

    public function edit(TrainingBatch $trainingBatch): View
    {
        $trainingBatch->load('facilities');

        return view('admin.training-batches.edit', [
            'batch' => $trainingBatch,
            'facilities' => $this->bookableFacilities(),
            'programs' => $this->programOptions(),
        ]);
    }

    public function update(TrainingBatchRequest $request, TrainingBatch $trainingBatch): RedirectResponse
    {
        $data = $request->validated();
        $facilityIds = $data['facility_ids'];
        unset($data['facility_ids']);

        try {
            $this->service->updateBatch($request->user(), $trainingBatch, $data, $facilityIds);
        } catch (TrainingScheduleConflictException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.training-batches.show', $trainingBatch)
            ->with('success', 'Training batch updated.');
    }

    public function updateStatus(Request $request, TrainingBatch $trainingBatch): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', \Illuminate\Validation\Rule::in(TrainingBatchStatus::values())],
        ]);

        $this->service->changeStatus(
            $request->user(),
            $trainingBatch,
            TrainingBatchStatus::from($validated['status'])
        );

        return redirect()->back()->with('success', 'Training batch status updated.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Facility>
     */
    private function bookableFacilities()
    {
        return Facility::with('facilityType')
            ->where('is_active', true)
            ->orderBy('facility_type_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<string>
     */
    private function programOptions(): array
    {
        return collect(config('resort.training.programs', []))
            ->pluck('title')
            ->filter()
            ->values()
            ->all();
    }
}
