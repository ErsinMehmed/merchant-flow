<?php

return [
    /*
     * Docker Compose enables demo data for the local stack. Tests and any
     * other environment start with roles and permissions only.
     */
    'enabled' => (bool) env('SEED_DEMO_DATA', false),

    // Fictional local accounts only; never used outside the demo stack.
    'password' => env('DEMO_PASSWORD', 'MerchantFlowDemo!2026'),
];
