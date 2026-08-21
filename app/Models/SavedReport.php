<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SavedReport extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'filters_json',
    ];

    protected $casts = [
        'filters_json' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
