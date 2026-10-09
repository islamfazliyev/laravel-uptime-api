<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Monitor extends Model
{
    use Notifiable;

    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'keyword',
        'certificate_check_enabled',
        'certificate_expiration_date',
        'certificate_status',
        'is_paused',
        'check_interval',
        'status',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'certificate_check_enabled' => 'boolean',
            'certificate_expiration_date' => 'datetime',
            'is_paused' => 'boolean',
            'last_checked_at' => 'datetime',
            'check_interval' => 'integer',
        ];
    }

    public function pings(): HasMany
    {
        return $this->hasMany(Ping::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function routeNotificationForMail($notification)
    {
        // For testing, send to a predefined email or the owner's email
        // We'll return an admin email from env or a fallback
        return env('ALERT_EMAIL_ADDRESS', 'admin@example.com');
    }
}
