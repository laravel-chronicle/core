<?php

declare(strict_types=1);

namespace Chronicle\Tests\Fakes\Prepare;

use Chronicle\Contracts\EntryExtension;
use Chronicle\Entry\PendingEntry;
use Chronicle\Pipeline\ExtensionStage;

/**
 * Turns Currency values in an entry's diff into the minor amount the
 * application stores, before Chronicle validates the entry.
 */
final class StoreCurrencyAsMinorAmount implements EntryExtension
{
    public function stage(): ExtensionStage
    {
        return ExtensionStage::PREPARE;
    }

    public function process(PendingEntry $entry): PendingEntry
    {
        $diff = $entry->attribute('diff');

        if (! is_array($diff)) {
            return $entry;
        }

        array_walk_recursive($diff, function (mixed &$value): void {
            if ($value instanceof Currency) {
                $value = $value->toMinorAmount();
            }
        });

        $entry->setAttribute('diff', $diff);

        return $entry;
    }
}
