<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversationSession extends Model
{
    use Prunable;

    public function prunable()
    {
        return static::where('started_at', '<', now()->subDay());
    }

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'lang', 'scenario_key', 'topic_label', 'started_at', 'last_message_at', 'ended_at', 'summary',
        'error_analysis', 'analyzed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_message_at' => 'datetime',
        'ended_at' => 'datetime',
        'error_analysis' => 'array',
        'analyzed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function turns(): HasMany
    {
        return $this->hasMany(ConversationTurn::class, 'session_id');
    }
}
