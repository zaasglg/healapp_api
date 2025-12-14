<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'name',
        'type',
        'phone',
        'address',
    ];

    /**
     * Get the owner of the organization.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the patients belonging to the organization.
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class, 'organization_id');
    }

    /**
     * Get the employees (users) belonging to the organization.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(User::class, 'organization_id');
    }
}
