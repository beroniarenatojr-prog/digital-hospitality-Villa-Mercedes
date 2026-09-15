<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupportChatMessageRequest;
use App\Services\SupportChatService;
use Illuminate\Http\JsonResponse;

class SupportChatController extends Controller
{
    public function __construct(
        private readonly SupportChatService $supportChat
    ) {}

    public function message(SupportChatMessageRequest $request): JsonResponse
    {
        return response()->json($this->supportChat->reply(
            $request->validated('message'),
            $request->validated('context'),
            $request->user(),
        ));
    }
}
