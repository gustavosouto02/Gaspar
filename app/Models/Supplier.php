<?php

namespace App\Models;

use App\Concerns\HasCustomFields;
use App\Concerns\HasSequentialCode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Supplier extends Model
{
    use HasCustomFields, HasFactory, HasSequentialCode, HasUuids, SoftDeletes;

    protected string $sequentialCodePrefix = 'FOR';

    protected string $sequentialCodeColumn = 'code';

    protected $fillable = [
        'code',
        'name',
        'phone',
        'email',
        'business',
        'description',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }
}
