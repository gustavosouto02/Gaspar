<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class CustomRecordType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(CustomField::class, 'custom_record_type_custom_field', 'custom_record_type_id', 'custom_field_id')
                    ->withPivot('field_order')
                    ->orderByPivot('field_order')
                    ->withTimestamps();
    }
}
