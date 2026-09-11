<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deadline extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_on' => 'date'];
    }

    public function policyInstrument(): BelongsTo
    {
        return $this->belongsTo(PolicyInstrument::class);
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** Date shown to users, respecting the declared precision. */
    public function displayDate(): string
    {
        if ($this->date_label) {
            return $this->date_label;
        }
        if (! $this->due_on) {
            return 'Date not yet set';
        }

        return match ($this->date_precision) {
            'year' => $this->due_on->format('Y'),
            'month' => $this->due_on->format('F Y'),
            default => $this->due_on->format('j F Y'),
        };
    }

    public function isUpcoming(): bool
    {
        return $this->due_on !== null && $this->due_on->isFuture() && $this->deadline_status === 'scheduled';
    }
}
