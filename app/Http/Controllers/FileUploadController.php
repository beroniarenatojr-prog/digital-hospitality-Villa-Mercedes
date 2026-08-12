<?php

namespace App\Http\Controllers;

use App\Http\Requests\FileUploadRequest;
use App\Services\ActivityLogService;
use App\Services\FileUploadService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class FileUploadController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly FileUploadService $fileUploadService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function store(FileUploadRequest $request): JsonResponse
    {
        $this->authorize('access-customer');

        $result = $this->fileUploadService->store($request->file('file'));

        $this->activityLogService->log('file_uploaded', $request->user(), [
            'path' => $result['path'],
            'original_name' => $result['original_name'],
        ]);

        return response()->json([
            'message' => 'File uploaded successfully.',
            'data' => $result,
        ]);
    }
}
