<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['company_id', 'app_id', 'status', 'trial_ends_at', 'suspended_at', 'config', 'activated_by'])]

class CompanyApp extends Pivot
{
    use HasUuids;

    public $table        = 'company_apps';
    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = true;

    protected function casts(): array
    {
        return [
            'config'        => 'array',
            'trial_ends_at' => 'datetime',
            'suspended_at'  => 'datetime',
            'approved_at'   => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }
}
