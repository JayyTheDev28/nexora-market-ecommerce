<?php

namespace App\Services;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class IdempotencyService
{
    /**
     * Execute an action with idempotency protection.
     */
    public static function run(?string $key, int $userId, string $action, Closure $callback): mixed
    {
        if (empty($key)) {
            return $callback();
        }

        $existing = IdempotencyKey::where('key', $key)->where('user_id', $userId)->first();
        if ($existing && $existing->response_body !== null) {
            // Already processed; return cached response
            $body = $existing->response_body;
            $code = $existing->response_code ?? 200;

            if (isset($body['redirect_url'])) {
                return redirect($body['redirect_url'])->with($body['session_flash'] ?? []);
            }

            return response()->json($body, $code);
        }

        $record = IdempotencyKey::firstOrCreate([
            'key' => $key,
            'user_id' => $userId,
        ], [
            'action' => $action,
            'created_at' => now(),
        ]);

        $result = $callback();

        if ($result instanceof JsonResponse) {
            $record->update([
                'response_code' => $result->getStatusCode(),
                'response_body' => $result->getData(true),
            ]);
        } elseif ($result instanceof RedirectResponse) {
            $record->update([
                'response_code' => $result->getStatusCode(),
                'response_body' => [
                    'redirect_url' => $result->getTargetUrl(),
                    'session_flash' => session()->all(),
                ],
            ]);
        }

        return $result;
    }
}
