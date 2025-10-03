<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'department_id',
        'title',
        'description',
        'level',
        'salary_min',
        'salary_max',
        'reports_to_position_id',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function reportsToPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'reports_to_position_id');
    }

    public function subordinatePositions(): HasMany
    {
        return $this->hasMany(Position::class, 'reports_to_position_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_positions')
            ->withPivot(['is_primary', 'assigned_at', 'expires_at'])
            ->withTimestamps();
    }

    public function userPositions(): HasMany
    {
        return $this->hasMany(UserPosition::class);
    }

    public function activeUsers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('expires_at', '>', now())
            ->orWherePivotNull('expires_at');
    }

    public function primaryUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('is_primary', true);
    }

    public function scopeForDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function getFullTitleAttribute()
    {
        return $this->department
            ? $this->title . ' - ' . $this->department->name
            : $this->title;
    }

    public function getAllSubordinates()
    {
        $subordinates = collect();

        foreach ($this->subordinatePositions as $subordinate) {
            $subordinates->push($subordinate);
            $subordinates = $subordinates->merge($subordinate->getAllSubordinates());
        }

        return $subordinates;
    }

    public function getHierarchyPath()
    {
        $path = collect([$this]);
        $current = $this;

        while ($current->reportsToPosition) {
            $current = $current->reportsToPosition;
            $path->prepend($current);
        }

        return $path;
    }

    public function isTopLevel()
    {
        return is_null($this->reports_to_position_id);
    }

    public function getDepth()
    {
        $depth = 0;
        $current = $this;

        while ($current->reportsToPosition) {
            $depth++;
            $current = $current->reportsToPosition;
        }

        return $depth;
    }
}