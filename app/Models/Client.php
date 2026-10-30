<?php

namespace App\Models;

use App\Concerns\HasCustomFields;
use App\Concerns\HasSequentialCode;
use App\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Client extends Model
{
    use HasCustomFields, HasFactory, HasSequentialCode, HasUuids, LogsActivity;

    protected string $sequentialCodePrefix = 'CLI';

    protected string $sequentialCodeColumn = 'code';

    protected $fillable = [
        'code',
        'name',
        'email',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function demands(): HasMany
    {
        return $this->hasMany(Demand::class);
    }
}
