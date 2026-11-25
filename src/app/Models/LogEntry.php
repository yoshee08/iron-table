<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One log entry (spec section 8.2), a parking-lot topic or a moderator note.
 * Each entry has the real time (created_at), the simulated time and the user who recorded it.
 */
class LogEntry extends Model
{
    /**
     * The entry types and their fields: name => label, or name => [label, allowed values].
     */
    public const TYPES = [
        'communication' => ['label' => 'Communication log', 'fields' => [
            'direction' => ['Internal or external', ['internal', 'external']],
            'from' => 'From',
            'to' => 'To',
            'summary' => 'Summary',
        ]],
        'action' => ['label' => 'Action log', 'fields' => [
            'phase' => ['IR phase', ['preparation', 'detection', 'analysis', 'containment', 'eradication', 'recovery', 'post-incident']],
            'action' => 'Action',
            'reason' => 'Reason',
            'performed_by' => 'Performed by (plan role)',
            'systems' => 'Affected systems',
            'result' => 'Result',
            'status' => ['Status', ['planned', 'in progress', 'done']],
        ]],
        'validation' => ['label' => 'Validation log', 'fields' => [
            'validation_type' => 'Validation type',
            'system' => 'System',
            'method' => 'Method',
            'result' => 'Result',
            'performed_by' => 'Performed by',
            'notes' => 'Notes',
        ]],
        'parking' => ['label' => 'Parking lot', 'fields' => ['topic' => 'Topic for the debrief']],
        'note' => ['label' => 'Moderator note', 'fields' => ['note' => 'Note']],
    ];

    protected function casts(): array
    {
        return ['fields' => 'array'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function label(): string
    {
        return self::TYPES[$this->type]['label'];
    }
}
