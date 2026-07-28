<?php

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ApiResponse
{
    protected function ok(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return $this->respond($data, $message, $status);
    }

    protected function created(mixed $data = null, ?string $message = 'Created successfully.'): JsonResponse
    {
        return $this->respond($data, $message, 201);
    }

    protected function noContent(?string $message = 'Deleted successfully.'): JsonResponse
    {
        return $this->respond(null, $message, 200);
    }

    protected function fail(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    private function respond(mixed $data, ?string $message, int $status): JsonResponse
    {
        $payload = ['success' => true];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if ($data instanceof ResourceCollection) {
            return $data->additional($payload)->response()->setStatusCode($status);
        }

        if ($data instanceof JsonResource) {
            return $data->additional($payload)->response()->setStatusCode($status);
        }

        $payload['data'] = $data;

        return response()->json($payload, $status);
    }
}
