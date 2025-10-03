<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'email',
        'invited_by',
        'role_ids',
        'position_id',
        'token_hash',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'role_ids' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isAccepted();
    }

    public function scopeForEmail($query, $email)
    {
        return $query->where('email', $email);
    }

    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')
            ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeAccepted($query)
    {
        return $query->whereNotNull('accepted_at');
    }

    public function accept()
    {
        $this->update(['accepted_at' => now()]);
    }
}