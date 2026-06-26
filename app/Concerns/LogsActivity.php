<?php

namespace App\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Adicione esta trait a qualquer Model para auditoria automática.
 *
 * Campos ignorados por padrão:
 *   - created_at, updated_at (ruído sem valor)
 *   - password (segurança)
 *
 * Uso:
 *   use App\Concerns\LogsActivity;
 *   class Demand extends Model { use LogsActivity; }
 */
trait LogsActivity
{
    /** Campos que NÃO devem aparecer nos logs. Sobrescreva no Model se necessário. */
    protected array $auditExclude = ['password', 'remember_token', 'created_at', 'updated_at'];

    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model) {
            $model->recordActivity('created', [], $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $oldValues = array_intersect_key($model->getOriginal(), $model->getDirty());
            $newValues = $model->getDirty();

            // Não registra se só changed campos ruidosos
            $meaningful = array_diff_key($newValues, array_flip($model->auditExclude));
            if (empty($meaningful)) {
                return;
            }

            $model->recordActivity('updated', $oldValues, $newValues);
        });

        static::deleted(function (Model $model) {
            $model->recordActivity('deleted', $model->getAttributes(), []);
        });
    }

    protected function recordActivity(string $event, array $oldValues, array $newValues): void
    {
        // Filtra campos ignorados
        $exclude   = $this->auditExclude ?? [];
        $oldValues = array_diff_key($oldValues, array_flip($exclude));
        $newValues = array_diff_key($newValues, array_flip($exclude));

        try {
            ActivityLog::create([
                'user_id'        => auth()->id(),
                'event'          => $event,
                'auditable_type' => static::class,
                'auditable_id'   => (string) $this->getKey(),
                'old_values'     => empty($oldValues) ? null : $oldValues,
                'new_values'     => empty($newValues) ? null : $newValues,
                'ip_address'     => request()?->ip(),
                'user_agent'     => request()?->userAgent(),
                'created_at'     => now(),
            ]);
        } catch (\Throwable) {
            // Log silencioso — auditoria nunca deve quebrar a operação principal
        }
    }
}
