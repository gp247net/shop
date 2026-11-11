<?php

namespace GP247\Shop\Services\Catalog;

/**
 * Who writes shop data through the shared write services, and into which store. The store is
 * given explicitly — the services never read the admin session, so a CLI run or a sliced
 * background run (a data import) writes exactly where it was told to.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-catalog-write-service, US-SADM-historical-order-write
 * @aidlc-adr shop-admin_catalog-write-service
 */
final class WriteContext
{
    public const ACTOR_ADMIN = 'admin';
    public const ACTOR_IMPORT = 'import';
    public const ACTOR_API = 'api';

    /**
     * @param string   $storeId     Store the records belong to (GP247 store ids are strings).
     * @param string   $actor       One of the ACTOR_* constants.
     * @param string|null $adminUserId Admin account behind the write, when there is one (admin ids are UUIDs).
     */
    public function __construct(
        public readonly string $storeId,
        public readonly string $actor = self::ACTOR_ADMIN,
        public readonly ?string $adminUserId = null,
    ) {
        if (trim($storeId) === '') {
            throw new \InvalidArgumentException('A write needs a target store.');
        }
        if (!in_array($actor, [self::ACTOR_ADMIN, self::ACTOR_IMPORT, self::ACTOR_API], true)) {
            throw new \InvalidArgumentException('Unknown writer: ' . $actor);
        }
    }
}
