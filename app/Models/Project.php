<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'dsn_key', 'environment', 'discord_webhook_url',
        'telegram_bot_token', 'telegram_chat_id',
    ];

    public function issues()
    {
        return $this->hasMany(Issue::class);
    }

    public function traces()
    {
        return $this->hasMany(PerformanceTrace::class);
    }
}
