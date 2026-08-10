<?php

namespace Tests\Unit\Observability;

use App\Support\Observability\ApplySensitiveLogRedaction;
use App\Support\Observability\RedactSensitiveLogContext;
use App\Support\Observability\RequestIdentifier;
use App\Support\Observability\SensitiveDataRedactor;
use DateTimeImmutable;
use Illuminate\Log\Logger as LaravelLogger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Monolog\Processor\PsrLogMessageProcessor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StructuredLogRedactionTest extends TestCase
{
    public function test_channel_tap_attaches_the_sensitive_context_processor(): void
    {
        $monolog = new MonologLogger('redaction-test');
        $tap = new ApplySensitiveLogRedaction(new SensitiveDataRedactor);

        $tap(new LaravelLogger($monolog));

        $this->assertInstanceOf(RedactSensitiveLogContext::class, $monolog->getProcessors()[0]);
    }

    public function test_request_identifier_accepts_only_bounded_log_safe_values(): void
    {
        $this->assertSame('01J4ZYA6H7VQQ7DQ8R8M0T9F4Y', RequestIdentifier::normalize('01J4ZYA6H7VQQ7DQ8R8M0T9F4Y'));
        $this->assertSame('gateway.trace-1:span_2', RequestIdentifier::normalize('gateway.trace-1:span_2'));
        $this->assertNull(RequestIdentifier::normalize('contains whitespace'));
        $this->assertNull(RequestIdentifier::normalize("line\nbreak"));
        $this->assertNull(RequestIdentifier::normalize(str_repeat('a', 65)));
        $this->assertNull(RequestIdentifier::normalize('../unsafe'));
    }

    public function test_nested_sensitive_context_is_redacted_before_psr_interpolation_and_json_formatting(): void
    {
        $processor = new RedactSensitiveLogContext(new SensitiveDataRedactor);
        $this->assertInstanceOf(ProcessorInterface::class, $processor);
        $record = new LogRecord(
            datetime: new DateTimeImmutable('2026-08-10T10:00:00+00:00'),
            channel: 'stderr',
            level: Level::Error,
            message: 'Rejected password {password}',
            context: [
                'request_id' => 'safe-request-id',
                'password' => 'plain-text-secret',
                'nested' => [
                    'access_token' => 'bearer-secret',
                    'profile' => [
                        'email' => 'person@example.test',
                        'phone_number' => '081234567890',
                        'ktp' => '3174000000000001',
                        'nib_number' => '1234567890123',
                    ],
                    'payment' => [
                        'midtrans_server_key' => 'server-secret',
                        'signature_key' => 'signature-secret',
                        'raw_payload' => ['card_number' => '4111111111111111'],
                    ],
                    'document_path' => 'providers/12/private/ktp.pdf',
                    'query' => ['signature' => 'signed-bearer-secret'],
                    'signed_url' => 'https://example.test/private/file?signature=signed-url-secret',
                    'temporary_url' => 'https://object.test/file?token=temporary-url-secret',
                ],
                'exception' => new RuntimeException('exception contains person@example.test'),
            ],
        );

        $record = $processor($record);
        $record = (new PsrLogMessageProcessor)($record);
        $json = (new JsonFormatter(
            JsonFormatter::BATCH_MODE_JSON,
            true,
            false,
            false,
        ))->format($record);
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Rejected password [REDACTED]', $decoded['message']);
        $this->assertSame('safe-request-id', $decoded['context']['request_id']);
        $this->assertSame('[REDACTED]', $decoded['context']['password']);
        $this->assertSame('[REDACTED]', $decoded['context']['nested']['access_token']);
        $this->assertSame('[REDACTED]', $decoded['context']['nested']['profile']['email']);
        $this->assertSame('[REDACTED]', $decoded['context']['nested']['payment']['raw_payload']);
        $this->assertSame('[REDACTED]', $decoded['context']['nested']['query']['signature']);
        $this->assertSame('[REDACTED]', $decoded['context']['nested']['signed_url']);
        $this->assertSame('[REDACTED]', $decoded['context']['nested']['temporary_url']);
        $this->assertSame(RuntimeException::class, $decoded['context']['exception']['class']);
        $this->assertSame('ERROR', $decoded['level_name']);

        foreach ([
            'plain-text-secret',
            'bearer-secret',
            'person@example.test',
            '081234567890',
            '3174000000000001',
            '1234567890123',
            'server-secret',
            'signature-secret',
            '4111111111111111',
            'providers/12/private/ktp.pdf',
            'signed-bearer-secret',
            'signed-url-secret',
            'temporary-url-secret',
        ] as $sensitiveValue) {
            $this->assertStringNotContainsString($sensitiveValue, $json);
        }
    }
}
