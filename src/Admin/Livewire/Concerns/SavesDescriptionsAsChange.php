<?php

namespace GP247\Shop\Admin\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Saving the multilingual texts of a record counts as a change of the record when the texts
 * differ from what was stored: its `updated_at` moves, so REST API clients reading
 * "changed since" see it. The texts live in their own table (rewritten on every save),
 * which the record's own model never notices. For managers using HasMultilingualDescriptions.
 *
 * @aidlc-unit api-admin-write
 * @aidlc-story US-api-admin-write-shop-events
 */
trait SavesDescriptionsAsChange
{
    /**
     * Save the texts of $record; touch it when they changed.
     *
     * @param Model $record
     * @return bool Whether the texts changed.
     */
    protected function saveDescriptionsMarkingChange(Model $record): bool
    {
        $before = $this->storedDescriptions($record->getKey());
        $this->saveDescriptions($record->getKey());
        if ($before === $this->storedDescriptions($record->getKey())) {
            return false;
        }
        $record->touch();

        return true;
    }

    /**
     * Stored texts of a record: language => field => value.
     *
     * @param int|string $foreignId
     * @return array<string, array<string, string>>
     */
    private function storedDescriptions($foreignId): array
    {
        $fields = $this->multilingualFields();
        $out = [];
        foreach ($this->descriptionModelClass()::where($this->descriptionForeignKey(), $foreignId)->get() as $row) {
            foreach ($fields as $field) {
                $out[(string) $row->lang][$field] = (string) $row->{$field};
            }
        }
        ksort($out);

        return $out;
    }
}
