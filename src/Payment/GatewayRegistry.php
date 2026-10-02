<?php

namespace GP247\Shop\Payment;

use GP247\Shop\Payment\Contracts\PaymentGateway;

/**
 * Reads `gp247-config.payment.gateways`, where payment plugins declare what they can
 * do. A gateway whose driver class is missing is hidden from every choice and
 * reported once per request.
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-gateway-capability
 * @aidlc-adr payment-request_gateway-capability-contract
 */
class GatewayRegistry
{
    /** @var array<string, bool> */
    private array $reported = [];

    /**
     * @return array<string, array<string, mixed>> key => definition (only usable ones).
     */
    public function all(): array
    {
        $out = [];
        foreach ((array) config('gp247-config.payment.gateways', []) as $key => $definition) {
            if (!is_string($key) || !is_array($definition)) {
                continue;
            }
            if ($this->driver($key) === null) {
                continue;
            }
            $out[$key] = $definition;
        }

        return $out;
    }

    /**
     * @param string $key
     * @return string
     */
    public function label(string $key): string
    {
        $label = (string) ($this->definition($key)['label'] ?? '');
        if ($label === '') {
            return $key;
        }
        $rendered = function_exists('gp247_language_render') ? (string) gp247_language_render($label) : $label;

        return $rendered !== '' ? $rendered : $key;
    }

    /**
     * @param string $key
     * @return array<int, string>
     */
    public function capabilities(string $key): array
    {
        $declared = (array) ($this->definition($key)['capabilities'] ?? []);

        return array_values(array_intersect(
            [PaymentGateway::CAP_COLLECT, PaymentGateway::CAP_REFUND, PaymentGateway::CAP_PAYOUT],
            $declared
        ));
    }

    public function supports(string $key, string $capability): bool
    {
        return in_array($capability, $this->capabilities($key), true) && $this->driver($key) !== null;
    }

    /**
     * Usable gateways that declare a capability.
     *
     * @param string $capability
     * @return array<string, string> key => label.
     */
    public function withCapability(string $capability): array
    {
        $out = [];
        foreach (array_keys($this->all()) as $key) {
            if (in_array($capability, $this->capabilities($key), true)) {
                $out[$key] = $this->label($key);
            }
        }

        return $out;
    }

    /**
     * @param string $key
     * @return PaymentGateway|null Null when unregistered or the driver class is gone.
     */
    public function driver(string $key): ?PaymentGateway
    {
        $class = $this->definition($key)['driver'] ?? null;
        if ($class === null || $class === '') {
            return null;
        }
        if ($class instanceof PaymentGateway) {
            return $class;
        }
        if (!is_string($class) || !class_exists($class)) {
            $this->reportOnce($key, 'driver class missing');

            return null;
        }
        $instance = app($class);
        if (!$instance instanceof PaymentGateway) {
            $this->reportOnce($key, "{$class} does not implement PaymentGateway");

            return null;
        }

        return $instance;
    }

    /**
     * WHY not config("…gateways.{$key}"): a key may contain dots (e.g. "vendor.pay"),
     * which dot-notation would split into nested keys.
     *
     * @param string $key
     * @return array<string, mixed>
     */
    private function definition(string $key): array
    {
        $definition = ((array) config('gp247-config.payment.gateways', []))[$key] ?? null;

        return is_array($definition) ? $definition : [];
    }

    private function reportOnce(string $key, string $why): void
    {
        if (isset($this->reported[$key])) {
            return;
        }
        $this->reported[$key] = true;
        if (function_exists('gp247_report')) {
            gp247_report("[payment-request] gateway \"{$key}\" hidden — {$why}.");
        }
    }
}
