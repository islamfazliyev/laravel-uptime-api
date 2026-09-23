<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Monitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'check_interval',
        'status',
        'last_checked_at',
    ];

    public function pings(): HasMany
    {
        return $this->hasMany(Ping::class);
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
    
}
