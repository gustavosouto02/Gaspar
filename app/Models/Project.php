<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'client_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function demands(): HasMany
    {
        return $this->hasMany(Demand::class);
    }
}
