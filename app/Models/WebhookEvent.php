<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores every processed webhook event id. Gateways retry webhooks,
 * so the same event can arrive more than once — this table makes
 * processing idempotent (unique index on event_id).
 */
class WebhookEvent extends Model
{
    protected $fillable = ['gateway', 'event_id', 'event_type', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
