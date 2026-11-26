<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Console\Commands;

use App\Services\AuditLog;
use Illuminate\Console\Command;

/**
 * Checks the hash chain of the audit log (spec SEC-06).
 */
class VerifyAuditLog extends Command
{
    protected $signature = 'iron-table:audit-verify';

    protected $description = 'Check the hash chain of the audit log';

    public function handle(AuditLog $audit): int
    {
        $result = $audit->verify();

        if ($result['broken'] !== null) {
            $this->error("Audit log: the chain breaks at event {$result['broken']}. An event was changed or deleted.");

            return self::FAILURE;
        }

        $this->info("Audit log: {$result['count']} events. The chain is complete.");

        return self::SUCCESS;
    }
}
