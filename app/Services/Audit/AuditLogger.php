<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Support\BlindIndex;
use Illuminate\Database\Eloquent\Model;

/**
 * Records sensitive and administrative operations.
 *
 * Called from Actions rather than controllers, so an operation is logged
 * whatever triggered it — the admin UI, a console command or a job. See §19.
 */
final class AuditLogger
{
    /** @param array<string, mixed> $metadata */
    public function record(string $action, ?Model $subject = null, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $subject !== null ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            // Hashed, like everywhere else: an audit trail should not become a
            // second store of personal data.
            'ip_hash' => BlindIndex::ip(request()->ip()),
            'user_agent_hash' => BlindIndex::userAgent(request()->userAgent()),
            'metadata' => $metadata !== [] ? $metadata : null,
            'created_at' => now(),
        ]);
    }
}
