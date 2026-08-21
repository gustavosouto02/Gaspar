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
        'number',
        'name',
        'description',
        'is_active',
        'entity_id',
        'process_status_id',
        'start_date_forecast',
        'end_date_forecast',
        'end_date',
        'contact_name',
        'manager_name',
        'sponsor_name',
        'scrum_master_name',
        'product_owner_name',
        'budget',
        'spent',
        'sponsor_evaluation',
        'attachments',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date_forecast' => 'date',
        'end_date_forecast' => 'date',
        'end_date' => 'date',
        'budget' => 'decimal:2',
        'spent' => 'decimal:2',
        'attachments' => 'array',
    ];

    protected static function booted()
    {
        static::updating(function ($project) {
            // Check if status is being changed to something that sounds like 'Encerrado'
            if ($project->isDirty('process_status_id') && $project->process_status_id) {
                $status = \App\Models\ProcessStatus::find($project->process_status_id);
                if ($status && in_array(strtolower($status->name), ['encerrada', 'encerrado', 'concluída', 'concluído', 'finalizado', 'quitada'])) {
                    // Check if there are demands not closed
                    $openDemandsCount = $project->demands()->whereHas('processStatus', function($q) {
                        $q->whereNotIn('name', ['Encerrada', 'Encerrado', 'Concluída', 'Concluído', 'Finalizado', 'Quitada']);
                    })->count();

                    if ($openDemandsCount > 0) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'process_status_id' => 'Não é possível encerrar o projeto. Existem ' . $openDemandsCount . ' demandas abertas atreladas a ele.',
                        ]);
                    }
                }
            }
        });
    }

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function entity(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CustomEntity::class, 'entity_id');
    }

    public function processStatus(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProcessStatus::class, 'process_status_id');
    }

    public function demands(): HasMany
    {
        return $this->hasMany(Demand::class);
    }

    public function members(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user');
    }

    public function progresses(): HasMany
    {
        return $this->hasMany(ProjectProgress::class)->latest();
    }
}
