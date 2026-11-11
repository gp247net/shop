<?php

namespace GP247\Shop\Services\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Write service of a store-owned record that has a name and an alias derived from it
 * (brand, supplier), with the rules of its admin screen. Payload: `form` with the record's
 * columns; an empty alias is derived from the name; on edit a field that is absent is left
 * as stored. Options: those of {@see CatalogWriter}.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-catalog-write-service
 * @aidlc-adr shop-admin_catalog-write-service
 */
abstract class NamedRecordWriter extends CatalogWriter
{
    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    /**
     * Columns written from the form => default value on create.
     *
     * @return array<string, mixed>
     */
    abstract protected function columns(): array;

    /**
     * Rules other than the alias rule, keyed `form.<field>`.
     *
     * @return array<string, mixed>
     */
    abstract protected function fieldRules(): array;

    /**
     * Language keys of the field labels, keyed by rule path.
     *
     * @return array<string, string>
     */
    abstract public function labelKeys(): array;

    /**
     * Cast a column value the way the screen stores it.
     *
     * @param string $column
     * @param mixed $value
     * @return mixed
     */
    protected function cast(string $column, $value)
    {
        return match ($column) {
            'sort' => (int) ($value ?? 0),
            'status' => empty($value) ? 0 : 1,
            default => $value ?? '',
        };
    }

    /**
     * Create ($id null) or edit the record.
     *
     * @param WriteContext $ctx Owner store (on edit: the record's own store).
     * @param array<string, mixed> $payload
     * @param string|null $id
     * @param array<string, mixed> $options
     * @return Model
     * @throws \Illuminate\Validation\ValidationException
     */
    public function save(WriteContext $ctx, array $payload, ?string $id = null, array $options = []): Model
    {
        $current = $id !== null ? $this->ownedOrFail($this->modelClass(), $id, $ctx) : null;
        $given = $this->prepare($this->formOf($payload, $options), $current, (bool) ($options['validated'] ?? false));
        if (!($options['validated'] ?? false)) {
            $errors = $this->errors($given, $current);
            if ($errors !== []) {
                $this->fail($errors);
            }
        }

        return $this->transaction(function () use ($ctx, $given, $current): Model {
            $attributes = [];
            foreach ($this->columns() as $column => $default) {
                if ($current === null || array_key_exists($column, $given)) {
                    $attributes[$column] = $this->cast($column, array_key_exists($column, $given) ? $given[$column] : $default);
                }
            }
            if ($current !== null) {
                // Store is immutable on edit — store_id is never written (ADR 1-1).
                $current->update($attributes);

                return $current;
            }
            $attributes['store_id'] = $ctx->storeId;

            return $this->modelClass()::create($attributes);
        });
    }

    /**
     * Check a payload without writing.
     *
     * @param WriteContext $ctx
     * @param array<string, mixed> $payload
     * @param string|null $id
     * @param array<string, mixed> $options
     * @return array<string, array<int, string>>
     */
    public function validate(WriteContext $ctx, array $payload, ?string $id = null, array $options = []): array
    {
        $current = $id !== null ? $this->modelClass()::find($id) : null;
        if ($id !== null && ($current === null || (string) $current->store_id !== $ctx->storeId)) {
            return ['store_id' => [(string) gp247_language_render('admin.store.related_wrong_store')]];
        }

        return $this->errors($this->prepare($this->formOf($payload, $options), $current, false), $current);
    }

    /**
     * Validation rules of the screen's form.
     *
     * @param string|null $id Record being edited.
     * @return array<string, mixed>
     */
    public function rules(?string $id): array
    {
        return array_merge($this->fieldRules(), [
            'form.alias' => ['required', 'string', 'max:100', Rule::unique((new ($this->modelClass()))->getTable(), 'alias')->ignore($id)],
        ]);
    }

    /**
     * @param array<string, mixed> $form
     * @param Model|null $current
     * @param bool $validated The caller already derived the alias.
     * @return array<string, mixed>
     */
    private function prepare(array $form, ?Model $current, bool $validated): array
    {
        if (!$validated && ($current === null || array_key_exists('alias', $form))) {
            $name = (string) ($form['name'] ?? ($current !== null ? $current->name : ''));
            $form['alias'] = self::alias((string) ($form['alias'] ?? ''), $name);
        }

        return $form;
    }

    /**
     * @param array<string, mixed> $given
     * @param Model|null $current
     * @return array<string, array<int, string>>
     */
    private function errors(array $given, ?Model $current): array
    {
        $stored = [];
        if ($current !== null) {
            foreach (array_keys($this->columns()) as $column) {
                $stored[$column] = $current->{$column};
            }
        }
        $labels = array_map(fn (string $key): string => $this->label($key), $this->labelKeys());

        return $this->check(
            ['form' => array_merge($stored, $given)],
            $this->rules($current !== null ? (string) $current->getKey() : null),
            $this->localizedMessages($labels, ['required', 'min', 'max', 'numeric', 'string', 'email', 'url']),
            $labels,
        );
    }
}
