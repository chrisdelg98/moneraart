<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Services\Settings\SettingsRepository;
use Throwable;

/**
 * Decides which disk a newly uploaded product file is written to.
 *
 * `product_files.disk` is per file, which is what makes switching drivers safe:
 * files already sold keep the disk they were stored on and keep downloading,
 * while new uploads go wherever the setting now points. Nothing has to be moved
 * for the store to keep working. See §8.6.
 */
final class StorageManager
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /** One of local, r2 or s3. */
    public function driver(): string
    {
        $driver = (string) $this->settings->get('storage.driver', config('store.storage.driver', 'local'));

        return in_array($driver, ['local', 'r2', 's3'], true) ? $driver : 'local';
    }

    /**
     * The filesystem disk new files are written to.
     *
     * Named differently from the driver on purpose: the local driver writes to
     * the `private` disk, which is the one configured with serve => false.
     */
    public function diskName(): string
    {
        return match ($this->driver()) {
            'r2' => 'r2',
            's3' => 's3',
            default => 'private',
        };
    }

    /** Whether every value R2 needs has been supplied. */
    public function hasCredentials(): bool
    {
        foreach (['account_id', 'access_key_id', 'secret_access_key', 'bucket'] as $key) {
            if (blank($this->settings->get("storage.r2.{$key}"))) {
                return false;
            }
        }

        return true;
    }

    /** Whether a disk hands out pre-signed URLs rather than streaming through us. */
    public function isRemote(): bool
    {
        return $this->driver() !== 'local';
    }

    /**
     * Pushes credentials saved in the admin into the filesystem config.
     *
     * Without this the r2 disk reads .env only, and an owner who pastes their
     * keys into the admin would see nothing happen until a deploy.
     */
    public function configure(): void
    {
        try {
            if (! $this->settings->has('storage.r2.bucket')) {
                return;
            }

            $account = (string) $this->settings->get('storage.r2.account_id');
            $bucket = (string) $this->settings->get('storage.r2.bucket');
            $key = (string) $this->settings->get('storage.r2.access_key_id');
            $secret = (string) $this->settings->get('storage.r2.secret_access_key');
        } catch (Throwable) {
            // Before the settings table exists — install, or a fresh migrate.
            return;
        }

        if ($account === '' || $bucket === '' || $key === '' || $secret === '') {
            return;
        }

        config([
            'filesystems.disks.r2.key' => $key,
            'filesystems.disks.r2.secret' => $secret,
            'filesystems.disks.r2.bucket' => $bucket,
            'filesystems.disks.r2.endpoint' => "https://{$account}.r2.cloudflarestorage.com",
        ]);
    }
}
