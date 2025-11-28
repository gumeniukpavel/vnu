<?php
namespace App\Http\Controllers\Api;

use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class MeController
{
    public function __construct(
        private UserService $userService
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $userDto = $this->userService->getUserDto($request->user()->id);
        
        if (!$userDto) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json([
            'id' => $userDto->id,
            'name' => $userDto->name,
            'email' => $userDto->email,
        ]);
    }
}
