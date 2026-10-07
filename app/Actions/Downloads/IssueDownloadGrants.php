<?php

declare(strict_types=1);

namespace App\Actions\Downloads;

use App\Models\DownloadGrant;
use App\Models\Order;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns a paid order into download links.
 *
 * Runs synchronously inside the OrderPaid transaction: a customer who has paid
 * must not be waiting on a queue worker to earn their files. See §5.2 and §8.3.
 */
final class IssueDownloadGrants
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /** @return Collection<int, DownloadGrant> the plaintext tokens, keyed by grant id */
    public function __invoke(Order $order): Collection
    {
        $order->loadMissing('items');

        $hours = (int) $this->settings->get(
            $order->is_free ? 'download.free_expiry_hours' : 'download.expiry_hours',
            $order->is_free ? 24 : 72,
        );

        $max = (int) $this->settings->get('download.max_downloads', 5);
        $expiresAt = now()->addHours($hours);

        return DB::transaction(function () use ($order, $expiresAt, $max): Collection {
            $grants = collect();

            foreach ($order->items as $item) {
                // The manifest was frozen at purchase. Re-reading the product's
                // current files here would change what an old order delivers.
                foreach ($item->file_manifest as $fileId) {
                    $existing = DownloadGrant::query()
                        ->where('order_item_id', $item->getKey())
                        ->where('product_file_id', $fileId)
                        ->first();

                    // Issuing twice for the same line would hand out a second
                    // live link for the same file.
                    if ($existing !== null) {
                        $grants->push($existing);

                        continue;
                    }

                    $token = self::newToken();

                    $grant = DownloadGrant::create([
                        'order_id' => $order->getKey(),
                        'order_item_id' => $item->getKey(),
                        'product_file_id' => $fileId,
                        'token_hash' => self::hash($token),
                        'expires_at' => $expiresAt,
                        'max_downloads' => $max,
                    ]);

                    // Exists here and in the email, never in the database.
                    $grant->plainToken = $token;

                    $grants->push($grant);
                }
            }

            return $grants;
        });
    }

    /** 256 bits. Enumeration is not a threat worth modelling against this. */
    public static function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
