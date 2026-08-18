<?php

namespace Asteriskpound\LaravelIapVerification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class ProcessedNotification extends Model
{
    public $timestamps = false;

    protected $table = 'iap_verification_processed_notifications';

    protected $fillable = ['platform', 'notification_id', 'notification_type', 'processed_at'];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public static function alreadyProcessed(string $platform, string $notificationId): bool
    {
        return self::where('platform', $platform)->where('notification_id', $notificationId)->exists();
    }

    public static function markProcessed(string $platform, string $notificationId, ?string $notificationType = null): void
    {
        self::firstOrCreate(
            ['platform' => $platform, 'notification_id' => $notificationId],
            ['notification_type' => $notificationType, 'processed_at' => now()],
        );
    }

    /**
     * Atomically claim a notification for processing via the unique
     * ['platform', 'notification_id'] constraint — returns true only for the
     * request that wins the race, so callers must dispatch events after (not
     * before) a successful claim. Two concurrent redeliveries of the same
     * notification both racing alreadyProcessed()+markProcessed() could both
     * see "not yet processed" and both dispatch; claiming via a single insert
     * that either succeeds or fails on the unique constraint closes that gap.
     */
    public static function claim(string $platform, string $notificationId, ?string $notificationType = null): bool
    {
        try {
            self::create([
                'platform' => $platform,
                'notification_id' => $notificationId,
                'notification_type' => $notificationType,
                'processed_at' => now(),
            ]);

            return true;
        } catch (QueryException) {
            return false;
        }
    }
}
