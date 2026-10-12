<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Contracts;

/**
 * The platform's reference list of phones (brand, model, aliases, year, storage, network), kept by
 * the platform admins. Status: published (in use), review (to check before use), hidden.
 */
interface PhoneCatalog
{
    public const STATUSES = ['published', 'review', 'hidden'];

    public const NETWORKS = ['2G', '3G', '4G', '5G'];

    /**
     * @param  array{q?: ?string, brand?: ?string, status?: ?string}  $filters
     * @return array{phones: list<array<string, mixed>>, brands: array<string, int>, statuses: array<string, int>, total: int}
     */
    public function browse(array $filters = []): array;

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array;

    /**
     * Adds (no id) or changes a phone. Refuses a brand + model already in the list (`phone_exists`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function save(?string $id, array $data, ?string $by = null): array;

    public function delete(string $id): bool;

    /** @return array<string, mixed>|null a published phone */
    public function findBySlug(string $slug): ?array;

    /**
     * @param  list<string>  $ids
     * @return array<string, array<string, mixed>> id => phone, whatever its status
     */
    public function many(array $ids): array;

    /** How many still need checking (the admin menu's badge). */
    public function toReview(): int;

    /** @return list<array<string, mixed>> Published phones, by brand then newest. */
    public function published(): array;
}
