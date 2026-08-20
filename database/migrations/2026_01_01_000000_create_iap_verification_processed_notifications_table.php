<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Both Apple's App Store Server Notifications and Google's RTDN retry
     * delivery on anything but a clean 2xx — this table is what makes both
     * webhook handlers idempotent against those retries.
     */
    public function up(): void
    {
        Schema::create('iap_verification_processed_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('platform'); // 'ios' | 'android'
            $table->string('notification_id');
            $table->string('notification_type')->nullable();
            $table->timestamp('processed_at');

            // Named explicitly — Laravel's auto-generated name for this index exceeds
            // MySQL's 64-character identifier limit.
            $table->unique(['platform', 'notification_id'], 'iap_processed_notifications_platform_notification_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iap_verification_processed_notifications');
    }
};
