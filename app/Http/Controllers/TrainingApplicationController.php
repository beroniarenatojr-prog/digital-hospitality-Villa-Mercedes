<?php

namespace App\Http\Controllers;

use App\Exceptions\Training\TrainingApplicationException;
use App\Http\Requests\StoreTrainingApplicationRequest;
use App\Models\TrainingBatch;
use App\Services\TrainingService;
use Illuminate\Http\RedirectResponse;

class TrainingApplicationController extends Controller
{
    public function __construct(
        private readonly TrainingService $service
    ) {}

    public function store(StoreTrainingApplicationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $batch = TrainingBatch::findOrFail($data['training_batch_id']);
        unset($data['training_batch_id']);

        try {
            $application = $this->service->apply($batch, $data);
        } catch (TrainingApplicationException $e) {
            return redirect()->route('training')->withInput()->with('error', $e->getMessage());
        }

        $message = $application->status === \App\Enums\TrainingApplicationStatus::Waitlisted
            ? "This batch is full, so you have been added to the waitlist for {$batch->program}. We will contact you if a slot opens."
            : "Application received for {$batch->program} ({$batch->cohort_code}). The training office will contact you.";

        return redirect()->route('training')->with('success', $message);
    }
}
