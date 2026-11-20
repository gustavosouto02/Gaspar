<?php

namespace App\Models;

use App\Concerns\HasCustomFields;
use App\Concerns\HasSequentialCode;
use App\Enums\UserRoleEnum;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasCustomFields, HasFactory, HasSequentialCode, HasUuids, Notifiable, SoftDeletes;

    protected string $sequentialCodePrefix = 'USR';

    protected string $sequentialCodeColumn = 'code';

    /**
     * As colunas que podem ser preenchidas pelo admin
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'email',
        'password',
        'user_role',
        'phone',
        'process_role_id',
        'is_active',
    ];

    /**
     * As colunas que devem ser ocultas
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Gerar uuid7
     */
    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    /**
     * Determine if the user can access the Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Verifica se o usuário possui papel de Administrador.
     */
    public function isAdmin(): bool
    {
        return $this->user_role === UserRoleEnum::ADMIN;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'user_role' => UserRoleEnum::class,
            'is_active' => 'boolean',
        ];
    }

    public function projects(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user');
    }

    public function processRole(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProcessRole::class, 'process_role_id');
    }

    /**
     * Cadastros dos quais o usuário é Gestor (um cadastro pode ter vários gestores)
     */
    public function managedRecordTypes(): BelongsToMany
    {
        return $this->belongsToMany(CustomRecordType::class, 'custom_record_type_manager', 'user_id', 'custom_record_type_id')
            ->withTimestamps();
    }

    /**
     * Macroprocessos dos quais o usuário é Gestor
     */
    public function managedMacroprocesses(): HasMany
    {
        return $this->hasMany(Macroprocess::class, 'manager_id');
    }

    /**
     * Processos dos quais o usuário é Gestor
     */
    public function managedProcesses(): HasMany
    {
        return $this->hasMany(CustomEntity::class, 'manager_id');
    }

    /**
     * Deixa o usuário sem nada sob sua gestão: macroprocessos e processos ficam
     * "Sem gestor" (manager_id nulo) e ele sai da pivô dos cadastros.
     * `manager_id` é anulável: a obrigatoriedade do gestor vale na criação.
     * Itens na lixeira também são limpos (voltam sem gestor se restaurados) e a
     * atualização passa pelo model para o log de atividade registrar o "Sem gestor".
     */
    public function clearManagement(): void
    {
        $this->managedMacroprocesses()->withTrashed()->get()->each->update(['manager_id' => null]);
        $this->managedProcesses()->withTrashed()->get()->each->update(['manager_id' => null]);
        $this->managedRecordTypes()->detach();
    }

    protected static function booted(): void
    {
        // Quem deixa de ser GESTOR ou é desativado deixa de gerenciar. O soft delete
        // não dispara nullOnDelete/cascadeOnDelete da FK, então o vínculo é limpo aqui.
        static::updated(function (User $user) {
            $deixouDeSerGestor = $user->wasChanged('user_role') && $user->user_role !== UserRoleEnum::GESTOR;
            $foiDesativado = $user->wasChanged('is_active') && ! $user->is_active;

            if ($deixouDeSerGestor || $foiDesativado) {
                $user->clearManagement();
            }
        });

        static::deleted(fn (User $user) => $user->clearManagement());
    }

    public function processes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(CustomEntity::class, 'process_members', 'user_id', 'entity_id')
            ->withPivot('process_role_id')
            ->withTimestamps();
    }
}
