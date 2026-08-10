<?php

namespace App\Support\Observability;

use DateTimeInterface;
use Throwable;

final class SensitiveDataRedactor
{
    private const REDACTED = '[REDACTED]';

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'address',
        'access_token',
        'api_key',
        'authorization',
        'bearer',
        'card_number',
        'client_key',
        'client_secret',
        'cookie',
        'cvc',
        'cvv',
        'email',
        'full_name',
        'id_token',
        'ktp',
        'midtrans_client_key',
        'midtrans_server_key',
        'midtrans_signature_key',
        'mobile',
        'nib',
        'nib_number',
        'password',
        'password_confirmation',
        'payload',
        'payment_payload',
        'phone',
        'phone_number',
        'raw_body',
        'raw_payload',
        'raw_response',
        'refresh_token',
        'request_body',
        'secret',
        'server_key',
        'session',
        'set_cookie',
        'signature',
        'signature_key',
        'signed_url',
        'temporary_url',
        'document_note',
        'document_path',
        'token',
    ];

    public function redact(array $data): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $redacted[$key] = self::REDACTED;

                continue;
            }

            $redacted[$key] = $this->redactValue($value);
        }

        return $redacted;
    }

    private function redactValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->redact($value);
        }

        if ($value instanceof Throwable) {
            return [
                'class' => $value::class,
                'code' => $value->getCode(),
            ];
        }

        if (is_object($value) && ! $value instanceof DateTimeInterface) {
            return ['class' => $value::class];
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '.'], '_', $key));

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($normalized === $sensitiveKey || str_ends_with($normalized, '_'.$sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
