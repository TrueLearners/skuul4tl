<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class SchoolIntegration extends Model
{
    protected $fillable = [
        'school_id',
        'moodle_base_url',
        'moodle_token',
        'moodle_enabled',
        'moodle_last_tested_at',
    ];

    protected $casts = [
        'moodle_enabled' => 'boolean',
        'moodle_last_tested_at' => 'datetime',
    ];

    public function setMoodleTokenAttribute(?string $value): void
    {
        $this->attributes['moodle_token'] = filled($value) ? Crypt::encryptString($value) : null;
    }

    public function getMoodleTokenAttribute(?string $value): ?string
    {
        return filled($value) ? Crypt::decryptString($value) : null;
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
