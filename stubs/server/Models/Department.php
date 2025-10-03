<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'parent_department_id',
        'manager_id',
    ];

    public function parentDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_department_id');
    }

    public function childDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_department_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_departments')
            ->withPivot(['is_primary', 'assigned_at', 'expires_at'])
            ->withTimestamps();
    }

    public function scopeRootDepartments($query)
    {
        return $query->whereNull('parent_department_id');
    }

    public function getHierarchyAttribute()
    {
        $hierarchy = [];
        $current = $this;

        while ($current) {
            array_unshift($hierarchy, $current->name);
            $current = $current->parentDepartment;
        }

        return implode(' > ', $hierarchy);
    }
}