<?php

namespace App\Support\Observability;

use Illuminate\Log\Logger;
use Monolog\Logger as MonologLogger;

final class ApplySensitiveLogRedaction
{
    public function __construct(private readonly SensitiveDataRedactor $redactor) {}

    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof MonologLogger) {
            $monolog->pushProcessor(new RedactSensitiveLogContext($this->redactor));
        }
    }
}
