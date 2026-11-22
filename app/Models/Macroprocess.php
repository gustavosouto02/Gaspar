<?php

namespace App\Models;

use App\Concerns\HasCustomFields;
use App\Concerns\HasSequentialCode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'manager_id',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    /**
     * Gestor responsável pelo macroprocesso
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function processes()
    {
        return $this->hasMany(CustomEntity::class, 'macroprocess_id');
    }

    /** Processos em sua versão vigente */
    public function currentProcesses()
    {
        return $this->processes()->current();
    }

    /** Versões antigas dos processos deste macroprocesso */
    public function inactiveProcessVersions()
    {
        return $this->processes()->inactiveVersions();
    }

    /** Nome sem o prefixo "SIGLA - " (e, se pedido, sem a palavra "Macroprocesso" inicial). */
    private function cleanName(bool $stripWord = true): string
    {
        $cleanName = trim((string) $this->name);

        if ($this->acronym) {
            $prefixPattern = '/^'.preg_quote(trim($this->acronym), '/').'\s*-\s*/iu';
            $cleanName = (string) preg_replace($prefixPattern, '', $cleanName);
        }

        return $stripWord ? (string) preg_replace('/^macroprocesso\s+/iu', '', $cleanName) : $cleanName;
    }

    public function getFullDisplayNameAttribute(): string
    {
        $cleanName = $this->cleanName();

        return $this->acronym
            ? "{$this->acronym} - Macroprocesso {$cleanName}"
            : "Macroprocesso {$cleanName}";
    }

    /** "SIGLA - Nome", sem repetir a sigla quando o nome já começa com ela. */
    public function getShortDisplayNameAttribute(): string
    {
        $cleanName = $this->cleanName(false);

        return $this->acronym ? "{$this->acronym} - {$cleanName}" : $cleanName;
    }
}
