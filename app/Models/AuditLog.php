<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'company_id', 'app_id', 'action', 'entity_type', 'entity_id', 'payload', 'ip_address', 'user_agent', 'request_id'])]

class AuditLog extends Model
{
    use HasUuids;

    // Append-only — never updated
    public $timestamps   = false;
    public $incrementing = false;
    protected $keyType   = 'string';


    protected function casts(): array
    {
        return [
            'payload'    => 'array',
            'created_at' => 'datetime',
        ];
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }
}
