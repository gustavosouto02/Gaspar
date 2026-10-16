<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DemandComment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'demand_id',
        'user_id',
        'comment',
        'is_internal',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    protected static function booted(): void
    {
        static::created(function (DemandComment $comment) {
            $demand = $comment->demand;
            if (! $demand) {
                return;
            }

            $author = $comment->user;
            $authorName = $author ? $author->name : 'Usuário';
            $commentText = \Illuminate\Support\Str::limit(strip_tags($comment->comment), 100);

            try {
                // Notifica o responsável se não foi ele quem comentou
                if ($demand->assignee && $demand->assignee->id !== $comment->user_id) {
                    $demand->assignee->notify(new \App\Notifications\DemandActivityNotification(
                        $demand,
                        "Novo relato adicionado por {$authorName}: \"{$commentText}\""
                    ));
                }

                // Notifica o solicitante se não foi ele quem comentou
                if ($demand->requester && $demand->requester->id !== $comment->user_id) {
                    $demand->requester->notify(new \App\Notifications\DemandActivityNotification(
                        $demand,
                        "Novo relato adicionado por {$authorName}: \"{$commentText}\""
                    ));
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Falha ao enviar notificação de novo relato: {$e->getMessage()}");
            }
        });
    }

    public function demand(): BelongsTo
    {
        return $this->belongsTo(Demand::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

