<?php

namespace GP247\Shop\Payment;

use GP247\Shop\Payment\Contracts\PurposeResolver;
use GP247\Shop\Payment\Models\PaymentRequest;

/**
 * Reads `gp247-config.payment.purposes`, the registry where packages and plugins
 * declare what a payment request can be for. Degrades softly: an unregistered key
 * still has a label (the key), and a resolver whose class is gone resolves to null
 * so old requests stay readable while no money can be recorded against them
 * (RISK-TECH-payment-request-orphan-purpose).
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-purpose-registry
 * @aidlc-adr payment-request_gateway-capability-contract
 */
class PurposeRegistry
{
    /** @var array<string, bool> Keys already reported as broken in this request. */
    private array $reported = [];

    /** @var array<string, bool> Memoised owner availability per purpose. */
    private array $available = [];

    /**
     * @return array<string, array<string, mixed>> key => definition.
     */
    public function all(): array
    {
        $all = (array) config('gp247-config.payment.purposes', []);
        $out = [];
        foreach ($all as $key => $definition) {
            if (!is_string($key) || !is_array($definition)) {
                continue;
            }
            $out[$key] = $definition;
        }

        return $out;
    }

    /**
     * @param string $key
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * @param string $key
     * @return string Rendered label, or the key itself when unregistered.
     */
    public function label(string $key): string
    {
        $definition = $this->get($key);
        if ($definition === null || empty($definition['label'])) {
            return $key;
        }
        $label = (string) $definition['label'];
        $rendered = function_exists('gp247_language_render') ? (string) gp247_language_render($label) : $label;

        return $rendered !== '' ? $rendered : $key;
    }

    /**
     * @param string $key
     * @return array<int, string> Directions the purpose allows ("in", "out").
     */
    public function directions(string $key): array
    {
        $definition = $this->get($key);
        $directions = (array) ($definition['directions'] ?? []);

        return array_values(array_intersect([PaymentRequest::DIRECTION_IN, PaymentRequest::DIRECTION_OUT], $directions));
    }

    public function allowsDirection(string $key, string $direction): bool
    {
        return in_array($direction, $this->directions($key), true);
    }

    /**
     * The record a purpose must be linked to, when it declares one under `subject`:
     * `types` (subject_type => label key), `label` and `help` (label keys) of the id field.
     *
     * @param string $key
     * @return array{types: array<string, string>, label: string, help: string}|null Rendered labels; null for a free purpose.
     */
    public function subject(string $key): ?array
    {
        $spec = $this->get($key)['subject'] ?? null;
        if (!is_array($spec) || empty($spec['types']) || !is_array($spec['types'])) {
            return null;
        }
        $render = fn ($text) => function_exists('gp247_language_render') ? (string) gp247_language_render((string) $text) : (string) $text;
        $types = [];
        foreach ($spec['types'] as $type => $label) {
            if (is_string($type) && $type !== '') {
                $types[$type] = $render($label ?: $type);
            }
        }
        if ($types === []) {
            return null;
        }

        return [
            'types' => $types,
            'label' => $render($spec['label'] ?? reset($types)),
            'help' => isset($spec['help']) ? $render($spec['help']) : '',
        ];
    }

    public function allowsOver(string $key): bool
    {
        return (bool) ($this->get($key)['allow_over'] ?? false);
    }

    /**
     * Purposes an admin may pick for a direction.
     *
     * @param string $direction
     * @return array<string, string> key => label.
     */
    public function optionsFor(string $direction): array
    {
        $out = [];
        foreach (array_keys($this->all()) as $key) {
            if ($this->allowsDirection($key, $direction) && $this->isAvailable($key)) {
                $out[$key] = $this->label($key);
            }
        }

        return $out;
    }

    /**
     * The owner of a purpose, or null when the purpose has no owner (free) or its
     * class no longer exists.
     *
     * @param string $key
     * @return PurposeResolver|null
     */
    public function resolver(string $key): ?PurposeResolver
    {
        $definition = $this->get($key);
        $class = $definition['resolver'] ?? null;
        if ($class === null || $class === '') {
            return null;
        }
        if ($class instanceof PurposeResolver) {
            return $class;
        }
        if (!is_string($class) || !class_exists($class)) {
            $this->reportOnce($key, 'resolver class missing: ' . (is_string($class) ? $class : gettype($class)));

            return null;
        }
        $instance = app($class);
        if (!$instance instanceof PurposeResolver) {
            $this->reportOnce($key, "{$class} does not implement PurposeResolver");

            return null;
        }

        return $instance;
    }

    /**
     * Whether money can be recorded for the purpose right now: it is registered and
     * either has no owner by design or its owner can be reached.
     *
     * @param string $key
     * @return bool
     */
    public function isSettleable(string $key): bool
    {
        $definition = $this->get($key);
        if ($definition === null) {
            return false;
        }
        if (!$this->isAvailable($key)) {
            return false;
        }
        $class = $definition['resolver'] ?? null;
        if ($class === null || $class === '') {
            return true;
        }

        return $this->resolver($key) !== null;
    }

    /**
     * Whether the purpose's owner is installed at the database level: its definition
     * may declare `available` (a [class, method] callable — its tables exist). Code of
     * a package or plugin can be present while its install step was never run.
     *
     * @param string $key
     * @return bool True when no check is declared.
     */
    public function isAvailable(string $key): bool
    {
        if (array_key_exists($key, $this->available)) {
            return $this->available[$key];
        }
        $check = $this->get($key)['available'] ?? null;
        $ok = true;
        if ($check !== null) {
            try {
                $ok = is_callable($check) && (bool) $check();
            } catch (\Throwable $e) {
                $ok = false;
            }
            if (!$ok) {
                $this->reportOnce($key, 'owner not installed (availability check failed)');
            }
        }

        return $this->available[$key] = $ok;
    }

    private function reportOnce(string $key, string $why): void
    {
        if (isset($this->reported[$key])) {
            return;
        }
        $this->reported[$key] = true;
        if (function_exists('gp247_report')) {
            gp247_report("[payment-request] purpose \"{$key}\" unavailable — {$why}. Requests keep reading; no money can be recorded for it.");
        }
    }
}
