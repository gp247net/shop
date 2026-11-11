<?php

namespace GP247\Shop\Services\Catalog;

use GP247\Core\Models\AdminLanguage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Shared plumbing of the catalog / customer write services: one transaction per save (a
 * savepoint when the caller already holds one), the alias rule, validation that reports
 * errors under the same keys as the admin form (`form.*`, `desc.*`), multilingual texts and
 * the same-store guard on edit.
 *
 * Every writer accepts the same options:
 *  - `validated` (bool, default false): the caller already validated the payload with
 *    {@see rules()} (the admin screens do, through Livewire) — skip the second pass.
 *  - `clean` (bool, default true): run gp247_clean() over `form`. The admin screens hand over
 *    a form that ResourcePanel already cleaned, so they pass false (cleaning twice escapes twice).
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-catalog-write-service
 * @aidlc-adr shop-admin_catalog-write-service
 */
abstract class CatalogWriter
{
    /**
     * Run $fn in one transaction on the shop connection; nested in the caller's
     * transaction it becomes a savepoint, so the caller can roll both back together.
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    protected function transaction(callable $fn)
    {
        return DB::connection(defined('GP247_DB_CONNECTION') ? GP247_DB_CONNECTION : null)->transaction($fn);
    }

    /**
     * The URL alias rule of the admin screens: the given alias, or the fallback text when
     * it is empty, slugged and cut to 100 characters.
     *
     * @param string $alias    Alias as given (may be empty).
     * @param string $fallback Text to derive from (the first language's name, or the name).
     * @return string
     */
    public static function alias(string $alias, string $fallback): string
    {
        $source = $alias !== '' ? $alias : $fallback;

        return (string) gp247_word_limit(gp247_word_format_url($source), 100);
    }

    /**
     * Active language codes, in the order the admin screens list them.
     *
     * @return array<int, string>
     */
    protected function languageCodes(): array
    {
        return array_map('strval', array_keys(AdminLanguage::getListActive()->all()));
    }

    /**
     * Validate $data and return the errors (field => messages); empty when valid.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     * @return array<string, array<int, string>>
     */
    protected function check(array $data, array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = Validator::make($data, $rules, $messages, $attributes);

        return $validator->fails() ? $validator->errors()->messages() : [];
    }

    /**
     * Throw the errors as a ValidationException (keys kept as given).
     *
     * @param array<string, array<int, string>|string> $errors
     * @return never
     * @throws ValidationException
     */
    protected function fail(array $errors): never
    {
        throw ValidationException::withMessages($errors);
    }

    /**
     * A field label as plain text (some language strings carry presentational markup).
     *
     * @param string $key Language key.
     * @return string
     */
    protected function label(string $key): string
    {
        return trim(strip_tags(html_entity_decode((string) gp247_language_render($key), ENT_QUOTES)));
    }

    /**
     * Localised messages for the rules, keyed `<field>.<rule>`, from the shared validation.* keys.
     *
     * @param array<string, string> $labels field => plain label
     * @param array<int, string>    $rules
     * @return array<string, string>
     */
    protected function localizedMessages(array $labels, array $rules): array
    {
        $messages = [];
        foreach ($labels as $field => $label) {
            foreach ($rules as $rule) {
                $messages[$field . '.' . $rule] = gp247_language_render('validation.' . $rule, ['attribute' => $label]);
            }
        }

        return $messages;
    }

    /**
     * Load the record to edit; it must belong to the context's store (a writer never edits
     * another store's row, whoever calls it).
     *
     * @template M of Model
     * @param class-string<M> $modelClass
     * @param string $id
     * @param WriteContext $ctx
     * @return M
     * @throws ValidationException When the record belongs to another store.
     */
    protected function ownedOrFail(string $modelClass, string $id, WriteContext $ctx): Model
    {
        $model = $modelClass::findOrFail($id);
        if ((string) $model->store_id !== $ctx->storeId) {
            $this->fail(['store_id' => [(string) gp247_language_render('admin.store.related_wrong_store')]]);
        }

        return $model;
    }

    /**
     * Rewrite the multilingual texts of a record (delete then insert one row per active
     * language). A language (or a field of a language) missing from $given keeps its stored
     * text, so a writer that only knows one language or one field does not blank the others;
     * the admin screens always send every field of every language. Plain fields are XSS-cleaned, rich fields kept as authored.
     *
     * @param class-string $modelClass  Description model.
     * @param string       $foreignKey  Column pointing at the record.
     * @param int|string   $foreignId
     * @param array<int, string> $fields
     * @param array<int, string> $richFields
     * @param array<string, array<string, mixed>> $given lang => field => value
     * @return bool Whether the stored texts changed.
     */
    protected function writeDescriptions(string $modelClass, string $foreignKey, $foreignId, array $fields, array $richFields, array $given): bool
    {
        $before = $this->storedDescriptions($modelClass, $foreignKey, $foreignId, $fields);

        $rows = [];
        foreach ($this->languageCodes() as $code) {
            $row = [$foreignKey => $foreignId, 'lang' => $code];
            foreach ($fields as $field) {
                // A language or a field that is absent keeps its stored text.
                $value = array_key_exists($code, $given) && array_key_exists($field, (array) $given[$code])
                    ? (string) $given[$code][$field]
                    : (string) ($before[$code][$field] ?? '');
                $row[$field] = in_array($field, $richFields, true) ? $value : gp247_clean($value);
            }
            $rows[] = $row;
        }

        $modelClass::where($foreignKey, $foreignId)->delete();
        if ($rows !== []) {
            // WHY: query-builder insert (not Eloquent create) so the composite-key
            // description models persist reliably in one statement.
            $modelClass::insert($rows);
        }

        return $before !== $this->storedDescriptions($modelClass, $foreignKey, $foreignId, $fields);
    }

    /**
     * The given texts completed with the stored ones, for validating an edit that sends only
     * some languages / fields (the rules require a name in every language that is sent).
     *
     * @param class-string $modelClass
     * @param string $foreignKey
     * @param int|string $foreignId
     * @param array<int, string> $fields
     * @param array<string, array<string, mixed>> $given
     * @return array<string, array<string, mixed>>
     */
    protected function descriptionsForCheck(string $modelClass, string $foreignKey, $foreignId, array $fields, array $given): array
    {
        $stored = $this->storedDescriptions($modelClass, $foreignKey, $foreignId, $fields);
        foreach ($given as $code => $texts) {
            $given[$code] = array_merge($stored[$code] ?? [], (array) $texts);
        }

        return $given;
    }

    /**
     * Stored texts of a record: lang => field => value.
     *
     * @param class-string $modelClass
     * @param string $foreignKey
     * @param int|string $foreignId
     * @param array<int, string> $fields
     * @return array<string, array<string, string>>
     */
    private function storedDescriptions(string $modelClass, string $foreignKey, $foreignId, array $fields): array
    {
        $out = [];
        foreach ($modelClass::where($foreignKey, $foreignId)->get() as $row) {
            foreach ($fields as $field) {
                $out[(string) $row->lang][$field] = (string) $row->{$field};
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * The form part of a payload, cleaned unless the caller says it already is.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function formOf(array $payload, array $options): array
    {
        $form = (array) ($payload['form'] ?? []);

        return ($options['clean'] ?? true) ? (array) gp247_clean($form) : $form;
    }
}
