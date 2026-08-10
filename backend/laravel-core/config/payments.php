<?php

return [
    // Development/demo compatibility only. Production must rely on an
    // authoritative gateway notification or server-side status verification.
    'allow_customer_manual_confirmation' => (bool) env(
        'ALLOW_CUSTOMER_MANUAL_PAYMENT_CONFIRMATION',
        false,
    ),
];
