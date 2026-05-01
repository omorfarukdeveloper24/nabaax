<?php

namespace App\Services;

use App\Models\ErrorLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ErrorLogService
{
    // সব জায়গা থেকে এই function call হবে
    public static function log(
        string $type,
        string $source,
        string $message,
        \Throwable $exception = null,
        array $context = [],
        string $jobClass = null,
        array $jobParams = [],
        int $maxRetries = 3
    ): ErrorLog {
        try {
            $error = ErrorLog::create([
                'type'        => $type,
                'source'      => $source,
                'message'     => $message,
                'trace'       => $exception ? $exception->getTraceAsString() : null,
                'context'     => $context,
                'job_class'   => $jobClass,
                'job_params'  => $jobParams,
                'retry_count' => 0,
                'max_retries' => $maxRetries,
                'status'      => 'open',
                'email_sent'  => false,
            ]);

            return $error;

        } catch (\Exception $e) {
            // ErrorLogService নিজে fail করলে Laravel log-এ যাবে
            Log::error("ErrorLogService::log failed: " . $e->getMessage());
            throw $e;
        }
    }

    // Job fail হলে call হবে — retry count বাড়াবে
    public static function jobFailed(
        ErrorLog $error,
        \Throwable $exception = null
    ): void {
        $retryCount = $error->retry_count + 1;
        $isCritical = $retryCount >= $error->max_retries;

        $error->update([
            'retry_count' => $retryCount,
            'status'      => $isCritical ? 'critical' : 'retrying',
            'message'     => $exception ? $exception->getMessage() : $error->message,
            'trace'       => $exception ? $exception->getTraceAsString() : $error->trace,
        ]);

        // Critical হলে email পাঠাও
        if ($isCritical && !$error->email_sent) {
            self::sendCriticalEmail($error);
        }
    }

    // Admin retry করলে call হবে
    public static function retry(ErrorLog $error): void
    {
        if (!$error->canRetry()) return;
        if (!$error->job_class || empty($error->job_params)) return;

        try {
            $jobClass  = $error->job_class;
            $jobParams = $error->job_params;

            // Job class exist করে কিনা check
            if (!class_exists($jobClass)) {
                Log::error("Retry failed: Job class {$jobClass} not found");
                return;
            }

            // Job dispatch করো
            $job = new $jobClass(...array_values($jobParams));
            dispatch($job)->onQueue('default');

            $error->update(['status' => 'retrying']);

        } catch (\Exception $e) {
            Log::error("ErrorLogService::retry failed: " . $e->getMessage());
        }
    }

    // Admin resolve করলে call হবে
    public static function resolve(ErrorLog $error, int $adminId, string $note = null): void
    {
        $error->update([
            'status'      => 'resolved',
            'resolved_by' => $adminId,
            'resolved_at' => now(),
            'admin_note'  => $note,
        ]);
    }

    // Critical error হলে admin-কে email পাঠাবে
    private static function sendCriticalEmail(ErrorLog $error): void
    {
        try {
            $adminEmail = config('app.admin_email', 'omorf4662@gmail.com');

            Mail::raw(
                "Critical Error Alert!\n\n" .
                "Source: {$error->source}\n" .
                "Message: {$error->message}\n" .
                "Retry Count: {$error->retry_count}/{$error->max_retries}\n" .
                "Time: {$error->created_at}\n\n" .
                "Context: " . json_encode($error->context, JSON_PRETTY_PRINT) . "\n\n" .
                "Dashboard: " . config('app.url') . "/admin/error-logs/{$error->id}",
                function ($mail) use ($adminEmail, $error) {
                    $mail->to($adminEmail)
                         ->subject("[CRITICAL] {$error->source} failed after {$error->retry_count} retries");
                }
            );

            $error->update(['email_sent' => true]);

        } catch (\Exception $e) {
            Log::error("Critical email send failed: " . $e->getMessage());
        }
    }

    // Dashboard-এর জন্য stats
    public static function getStats(): array
    {
        return [
            'total'    => ErrorLog::count(),
            'critical' => ErrorLog::critical()->count(),
            'open'     => ErrorLog::open()->count(),
            'retrying' => ErrorLog::retrying()->count(),
            'resolved' => ErrorLog::resolved()->count(),
        ];
    }
}