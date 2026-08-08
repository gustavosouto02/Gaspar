<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Macroprocess extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'acronym',
        'is_active',
        'value_chain_function',
        'created_by',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function processes()
    {
        return $this->hasMany(CustomEntity::class, 'macroprocess_id');
    }

    public function getFullDisplayNameAttribute(): string
    {
        return $this->acronym ? "{$this->acronym} - {$this->name}" : $this->name;
    }
}
