<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'issue_id', 'payload_json', 'user_context_json', 'breadcrumbs_json',
        'stacktrace_json', 'release', 'environment',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function payload(): array
    {
        return json_decode($this->payload_json, true) ?? [];
    }

    public function userContext(): array
    {
        return json_decode($this->user_context_json, true) ?? [];
    }

    public function breadcrumbs(): array
    {
        return json_decode($this->breadcrumbs_json, true) ?? [];
    }

    public function stacktrace(): array
    {
        return json_decode($this->stacktrace_json, true) ?? [];
    }
}
