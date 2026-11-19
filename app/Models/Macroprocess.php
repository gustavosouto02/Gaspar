<?php

namespace App\Models;

use App\Concerns\HasCustomFields;
use App\Concerns\HasSequentialCode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Macroprocess extends Model
{
    use HasCustomFields, HasFactory, HasSequentialCode, HasUuids, SoftDeletes;

    protected string $sequentialCodePrefix = 'MAC';

    protected string $sequentialCodeColumn = 'code';

    protected $fillable = [
        'code',
        'name',
        'acronym',
        'description',
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
        $cleanName = trim((string) $this->name);

        if ($this->acronym) {
            $prefixPattern = '/^' . preg_quote(trim($this->acronym), '/') . '\s*-\s*/iu';
            $cleanName = (string) preg_replace($prefixPattern, '', $cleanName);
        }

        $cleanName = (string) preg_replace('/^macroprocesso\s+/iu', '', $cleanName);

        return $this->acronym
            ? "{$this->acronym} - Macroprocesso {$cleanName}"
            : "Macroprocesso {$cleanName}";
    }
}
