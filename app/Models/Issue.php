<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'fingerprint', 'title', 'culprit', 'level',
        'status', 'occurrence_count', 'affected_users_count',
        'first_seen_at', 'last_seen_at', 'resolved_in_release',
    ];

    protected $casts = [
        'occurrence_count' => 'integer',
        'affected_users_count' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class)->latest();
    }
}
