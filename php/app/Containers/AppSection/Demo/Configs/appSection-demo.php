<?php

return [
    /*
     * Docker Compose enables demo data for the local stack. Tests and any
     * other environment start with roles and permissions only.
     */
    'enabled' => (bool) env('SEED_DEMO_DATA', false),

    // Fictional local accounts only; never used outside the demo stack.
    'password' => env('DEMO_PASSWORD', 'MerchantFlowDemo!2026'),

    /*
     * Multiplies the number of demo charges (about 22,000 at 1). Tests use a
     * small fraction to stay fast.
     */
    'charge_scale' => (float) env('DEMO_CHARGE_SCALE', 1),
];
