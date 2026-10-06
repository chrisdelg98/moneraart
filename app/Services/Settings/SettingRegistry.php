<?php

declare(strict_types=1);

namespace App\Services\Settings;

use Illuminate\Support\Collection;

/**
 * The single place every setting is declared.
 *
 * Sandbox and live PayPal credentials live under separate keys on purpose:
 * flipping the mode toggle must never destroy the other environment's keys,
 * and a mis-set toggle must never reach the wrong API with the wrong secret.
 * See docs/implementation_plan.md §3.4.
 */
final class SettingRegistry
{
    /** @var array<string, SettingDefinition>|null */
    private static ?array $cache = null;

    /** @return array<string, SettingDefinition> */
    public static function all(): array
    {
        return self::$cache ??= collect(self::definitions())
            ->keyBy(fn (SettingDefinition $d) => $d->key)
            ->all();
    }

    public static function find(string $key): ?SettingDefinition
    {
        return self::all()[$key] ?? null;
    }

    /** @return Collection<int, SettingDefinition> */
    public static function group(string $group): Collection
    {
        return collect(self::all())->filter(fn ($d) => $d->group === $group)->values();
    }

    public static function isSecret(string $key): bool
    {
        return self::find($key)->secret ?? false;
    }

    /** @return list<SettingDefinition> */
    private static function definitions(): array
    {
        return [
            // ── Setup ────────────────────────────────────────────────────
            new SettingDefinition(
                key: 'setup.completed',
                group: 'setup',
                type: 'boolean',
                default: false,
                validation: 'boolean',
            ),
            new SettingDefinition(
                key: 'setup.step',
                group: 'setup',
                type: 'integer',
                default: 1,
                validation: 'integer|min:1|max:6',
            ),

            // ── Store identity ───────────────────────────────────────────
            new SettingDefinition(
                key: 'store.name',
                group: 'store',
                default: 'Monera Art',
                validation: 'required|string|max:120',
                label: 'Store name',
                config: 'store.name',
            ),
            new SettingDefinition(
                key: 'store.legal_entity',
                group: 'store',
                validation: 'nullable|string|max:191',
                label: 'Legal / business name',
                help: 'Appears in your terms and privacy policy.',
            ),
            new SettingDefinition(
                key: 'store.support_email',
                group: 'store',
                validation: 'required|email|max:191',
                label: 'Support email',
            ),
            new SettingDefinition(
                key: 'store.country',
                group: 'store',
                validation: 'nullable|string|size:2',
                label: 'Country',
                help: 'Sets the jurisdiction named in your legal pages.',
            ),
            new SettingDefinition(
                key: 'store.currency',
                group: 'store',
                default: 'USD',
                validation: 'required|string|size:3',
                label: 'Currency',
            ),
            new SettingDefinition(
                key: 'store.timezone',
                group: 'store',
                default: 'UTC',
                validation: 'required|timezone',
                label: 'Timezone',
            ),
            new SettingDefinition(
                key: 'store.default_locale',
                group: 'store',
                default: 'en',
                validation: 'required|in:en,es',
                label: 'Default language',
            ),

            // ── Payments: PayPal ─────────────────────────────────────────
            new SettingDefinition(
                key: 'paypal.enabled',
                group: 'payments',
                type: 'boolean',
                default: false,
                validation: 'boolean',
            ),
            new SettingDefinition(
                key: 'paypal.mode',
                group: 'payments',
                default: 'sandbox',
                validation: 'required|in:sandbox,live',
                label: 'Mode',
                config: 'paypal.mode',
            ),
            new SettingDefinition(
                key: 'paypal.sandbox.client_id',
                group: 'payments',
                validation: 'nullable|string|max:191',
                label: 'Sandbox client ID',
                config: 'paypal.sandbox.client_id',
            ),
            new SettingDefinition(
                key: 'paypal.sandbox.client_secret',
                group: 'payments',
                secret: true,
                validation: 'nullable|string|max:191',
                label: 'Sandbox secret',
                config: 'paypal.sandbox.client_secret',
            ),
            new SettingDefinition(
                key: 'paypal.sandbox.webhook_id',
                group: 'payments',
                validation: 'nullable|string|max:64',
                label: 'Sandbox webhook ID',
                help: 'Registered automatically. You should never need to set this by hand.',
                config: 'paypal.sandbox.webhook_id',
            ),
            new SettingDefinition(
                key: 'paypal.live.client_id',
                group: 'payments',
                validation: 'nullable|string|max:191',
                label: 'Live client ID',
                config: 'paypal.live.client_id',
            ),
            new SettingDefinition(
                key: 'paypal.live.client_secret',
                group: 'payments',
                secret: true,
                validation: 'nullable|string|max:191',
                label: 'Live secret',
                config: 'paypal.live.client_secret',
            ),
            new SettingDefinition(
                key: 'paypal.live.webhook_id',
                group: 'payments',
                validation: 'nullable|string|max:64',
                label: 'Live webhook ID',
                help: 'Registered automatically. You should never need to set this by hand.',
                config: 'paypal.live.webhook_id',
            ),

            // ── Downloads ────────────────────────────────────────────────
            new SettingDefinition(
                key: 'download.expiry_hours',
                group: 'downloads',
                type: 'integer',
                default: 72,
                validation: 'required|integer|min:1|max:8760',
                label: 'Link validity (hours)',
            ),
            new SettingDefinition(
                key: 'download.max_downloads',
                group: 'downloads',
                type: 'integer',
                default: 5,
                validation: 'required|integer|min:1|max:100',
                label: 'Downloads allowed per file',
            ),
            new SettingDefinition(
                key: 'download.free_expiry_hours',
                group: 'downloads',
                type: 'integer',
                default: 24,
                validation: 'required|integer|min:1|max:8760',
                label: 'Link validity for free products (hours)',
            ),
            new SettingDefinition(
                key: 'download.allow_self_service_renewal',
                group: 'downloads',
                type: 'boolean',
                default: true,
                validation: 'boolean',
                label: 'Let customers request new links themselves',
            ),

            // ── Storage ──────────────────────────────────────────────────
            new SettingDefinition(
                key: 'storage.driver',
                group: 'storage',
                default: 'local',
                validation: 'required|in:local,r2,s3',
                label: 'Where product files are stored',
            ),
            new SettingDefinition(
                key: 'storage.r2.account_id',
                group: 'storage',
                validation: 'nullable|string|max:191',
                label: 'R2 account ID',
            ),
            new SettingDefinition(
                key: 'storage.r2.access_key_id',
                group: 'storage',
                validation: 'nullable|string|max:191',
                label: 'R2 access key ID',
            ),
            new SettingDefinition(
                key: 'storage.r2.secret_access_key',
                group: 'storage',
                secret: true,
                validation: 'nullable|string|max:191',
                label: 'R2 secret access key',
            ),
            new SettingDefinition(
                key: 'storage.r2.bucket',
                group: 'storage',
                validation: 'nullable|string|max:191',
                label: 'R2 bucket',
            ),

            // ── Email ────────────────────────────────────────────────────
            new SettingDefinition(
                key: 'mail.host',
                group: 'email',
                validation: 'nullable|string|max:191',
                label: 'SMTP host',
                config: 'mail.mailers.smtp.host',
            ),
            new SettingDefinition(
                key: 'mail.port',
                group: 'email',
                type: 'integer',
                default: 587,
                validation: 'nullable|integer|min:1|max:65535',
                label: 'SMTP port',
                config: 'mail.mailers.smtp.port',
            ),
            new SettingDefinition(
                key: 'mail.username',
                group: 'email',
                validation: 'nullable|string|max:191',
                label: 'SMTP username',
                config: 'mail.mailers.smtp.username',
            ),
            new SettingDefinition(
                key: 'mail.password',
                group: 'email',
                secret: true,
                validation: 'nullable|string|max:191',
                label: 'SMTP password',
                config: 'mail.mailers.smtp.password',
            ),
            new SettingDefinition(
                key: 'mail.encryption',
                group: 'email',
                default: 'tls',
                validation: 'nullable|in:tls,ssl,none',
                label: 'Encryption',
                config: 'mail.mailers.smtp.scheme',
            ),
            new SettingDefinition(
                key: 'mail.from_address',
                group: 'email',
                validation: 'nullable|email|max:191',
                label: 'Send from',
                config: 'mail.from.address',
            ),

            // ── AI assistance (§4.7.5.1) ─────────────────────────────────
            new SettingDefinition(
                key: 'ai.provider',
                group: 'ai',
                default: 'none',
                validation: 'required|in:none,anthropic,openai,gemini,deepl',
                label: 'Provider',
                help: 'Leave as None to keep every field manual.',
            ),
            new SettingDefinition(
                key: 'ai.api_key',
                group: 'ai',
                secret: true,
                validation: 'nullable|string|max:255',
                label: 'API key',
            ),
            new SettingDefinition(
                key: 'ai.model',
                group: 'ai',
                validation: 'nullable|string|max:120',
                label: 'Model',
                help: 'Fetched from the provider when the key is valid.',
            ),
            new SettingDefinition(
                key: 'ai.feature.translations',
                group: 'ai',
                type: 'boolean',
                default: true,
                validation: 'boolean',
                label: 'Draft translations',
            ),
            new SettingDefinition(
                key: 'ai.feature.seo_copy',
                group: 'ai',
                type: 'boolean',
                default: true,
                validation: 'boolean',
                label: 'SEO copy suggestions',
            ),
            new SettingDefinition(
                key: 'ai.feature.alt_text',
                group: 'ai',
                type: 'boolean',
                default: true,
                validation: 'boolean',
                label: 'Alt text suggestions',
            ),
            new SettingDefinition(
                key: 'ai.feature.descriptions',
                group: 'ai',
                type: 'boolean',
                default: false,
                validation: 'boolean',
                label: 'Product description drafts',
            ),

            // ── SEO ──────────────────────────────────────────────────────
            new SettingDefinition(
                key: 'seo.allow_indexing',
                group: 'seo',
                type: 'boolean',
                default: false,
                validation: 'boolean',
                label: 'Allow search engines to index this store',
                help: 'Keep this off during a soft launch.',
            ),
        ];
    }
}
