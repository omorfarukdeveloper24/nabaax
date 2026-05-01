<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    protected $fillable = [
        'type',
        'source',
        'message',
        'trace',
        'context',
        'job_class',
        'job_params',
        'retry_count',
        'max_retries',
        'status',
        'resolved_by',
        'resolved_at',
        'admin_note',
        'email_sent',
    ];

    protected $casts = [
        'context'    => 'array',
        'job_params' => 'array',
        'email_sent' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    // Critical কিনা check
    public function isCritical(): bool
    {
        return $this->retry_count >= $this->max_retries;
    }

    // Retry করা যাবে কিনা
    public function canRetry(): bool
    {
        return $this->status !== 'resolved';
    }

    // Scopes — filter করার জন্য
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeCritical($query)
    {
        return $query->where('status', 'critical');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeRetrying($query)
    {
        return $query->where('status', 'retrying');
    }
}