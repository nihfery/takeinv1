<?php

namespace App\Support\Observability;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class RedactSensitiveLogContext implements ProcessorInterface
{
    public function __construct(private readonly SensitiveDataRedactor $redactor) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->redactor->redact($record->context),
            extra: $this->redactor->redact($record->extra),
        );
    }
}
