<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Contracts\Repositories\VenueSuspensionRepositoryInterface;
use App\Contracts\Storage\PartnerDocumentStorageInterface;
use App\Models\SuspensionRemediation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * D14: an owner submits remediation for an active suspension. Each submission is a new immutable row; it never lifts
 * the suspension. Files are stored only after every check passes and are deleted again if the transaction fails.
 * Lock order: venue, venue_suspensions, then the insert into suspension_remediations (partial unique as backstop).
 */
class SubmitSuspensionRemediation
{
    public function __construct(
        private readonly VenueSuspensionRepositoryInterface $suspensions,
        private readonly PartnerDocumentStorageInterface $storage,
        private readonly DurableNotificationServiceInterface $notifications,
    ) {}

    /** @param  list<UploadedFile>  $files */
    public function execute(User $owner, int $organizationId, int $venueId, int $suspensionId, string $content, array $files): SuspensionRemediation
    {
        $stored = [];
        try {
            return DB::transaction(function () use ($owner, $organizationId, $venueId, $suspensionId, $content, $files, &$stored): SuspensionRemediation {
                $venue = $this->suspensions->venue($venueId, true);
                abort_unless((int) $venue->organization_id === $organizationId, 404, 'Cơ sở không thuộc tổ chức này.');
                $suspension = $this->suspensions->find($suspensionId, true);
                abort_unless((int) $suspension->venue_id === $venueId, 404, 'Quyết định đình chỉ không thuộc cơ sở này.');
                abort_if($suspension->isLifted(), 409, 'Quyết định đình chỉ đã được gỡ.');
                abort_if((bool) $this->suspensions->latestRemediation($suspensionId)?->blocksResubmission(), 409, 'Yêu cầu khắc phục đang chờ xem xét hoặc đã được chấp thuận.');

                foreach ($files as $file) {
                    $stored[] = $this->storage->storePrivate($file, (int) $owner->id, "suspension_{$suspensionId}", 'private/remediations');
                }
                $remediation = $this->suspensions->createRemediation([
                    'venue_suspension_id' => $suspensionId,
                    'content' => $content,
                    'files' => array_map(fn (array $file): array => [
                        'path' => $file['file_path'], 'name' => $file['file_name'], 'mime_type' => $file['mime_type'],
                        'size' => $file['file_size'], 'hash' => $file['file_hash'],
                    ], $stored),
                    'status' => 'pending',
                    'submitted_by' => $owner->id,
                    'submitted_at' => now(),
                ]);
                $this->notifications->recordAudit($owner->id, 'venue.remediation.submitted', $suspension, ['remediation_id' => $remediation->id]);
                $this->notifications->notifyRemediationSubmitted($remediation->setRelation('suspension', $suspension->setRelation('venue', $venue)));

                return $remediation;
            });
        } catch (UniqueConstraintViolationException) {
            $this->cleanup($stored);
            abort(409, 'Yêu cầu khắc phục đang chờ xem xét.');
        } catch (\Throwable $exception) {
            $this->cleanup($stored);
            throw $exception;
        }
    }

    private function cleanup(array $stored): void
    {
        foreach ($stored as $file) {
            $this->storage->cleanupFailedUpload($file['file_path']);
        }
    }
}
