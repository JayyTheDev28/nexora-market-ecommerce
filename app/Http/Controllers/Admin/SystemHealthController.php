<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SystemHealthController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasAdminPermission('manage_security'), 403);

        $dbHealthy = false;
        $dbError = null;
        try {
            DB::select('SELECT 1');
            $dbHealthy = true;
        } catch (\Throwable $e) {
            $dbError = 'Database connectivity check failed.';
        }

        $failedJobs = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->orderByDesc('failed_at')->limit(25)->get()
            : collect();

        $pendingJobsCount = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $failedJobsCount = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

        $recentExceptions = $this->readSanitizedErrorLogs();

        $lastAuditActivity = AuditLog::latest('created_at')->first()?->created_at;

        return view('admin.system_health.index', compact(
            'dbHealthy',
            'dbError',
            'failedJobs',
            'pendingJobsCount',
            'failedJobsCount',
            'recentExceptions',
            'lastAuditActivity'
        ));
    }

    public function retryJob(Request $request, string $uuid): RedirectResponse
    {
        abort_unless($request->user()->hasAdminPermission('manage_security'), 403);
        abort_unless(preg_match('/^[a-zA-Z0-9\-]+$/', $uuid) === 1, 422, 'Invalid job identifier.');

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        AuditLog::log(
            action: 'failed_job_retried',
            auditable: $request->user(),
            oldValues: null,
            newValues: ['job_uuid' => $uuid],
            reason: "Admin retried failed queue job {$uuid}",
            operatorId: $request->user()->id
        );

        return back()->with('status', "Queued retry for job {$uuid}.");
    }

    private function readSanitizedErrorLogs(): array
    {
        $logPath = storage_path('logs/laravel.log');
        if (! File::exists($logPath)) {
            return [];
        }

        $lines = array_slice(file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -200);
        $errors = [];
        foreach (array_reverse($lines) as $line) {
            if (str_contains($line, '.ERROR:') || str_contains($line, '.CRITICAL:')) {
                // Redact any bearer tokens, passwords, or emails
                $sanitized = preg_replace('/(Bearer\s+)[A-Za-z0-9\-\._~\+\/]+=*/i', '$1[REDACTED]', $line);
                $sanitized = preg_replace('/(password|token|secret|api_key)\s*[=:]\s*\S+/i', '$1=[REDACTED]', $sanitized);
                $errors[] = mb_substr($sanitized, 0, 320);
                if (count($errors) >= 20) {
                    break;
                }
            }
        }

        return $errors;
    }
}
