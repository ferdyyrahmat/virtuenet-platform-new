<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemNotification extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'icon',
        'url',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function send(User|int $user, string $title, string $message, string $type = 'info', string $icon = 'mdi-bell-outline', ?string $url = null): self
    {
        $userId = $user instanceof User ? $user->id : $user;

        $notif = self::create([
            'user_id' => $userId,
            'title'   => $title,
            'message' => $message,
            'type'    => $type,
            'icon'    => $icon,
            'url'     => $url,
            'is_read' => false,
        ]);

        return $notif;
    }
}
