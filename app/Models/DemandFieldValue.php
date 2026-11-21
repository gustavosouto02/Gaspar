<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DemandFieldValue extends Model
{
    use HasUuids;

    protected $fillable = [
        'demand_id',
        'custom_field_id',
        'value',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function demand(): BelongsTo
    {
        return $this->belongsTo(Demand::class);
    }

    public function customField(): BelongsTo
    {
        // Campo excluído continua dono do valor: o histórico da demanda não se perde
        return $this->belongsTo(CustomField::class)->withTrashed();
    }
}
