<?php

use App\Containers\AppSection\Authorization\Models\Permission;
use App\Containers\AppSection\Authorization\Models\Role;

return [
    'models' => [
        'permission' => Permission::class,
        'role' => Role::class,
    ],

    /*
     * These tables are a contract with the Go API, which reads them directly.
     * Renaming them requires the matching change in go-api/internal/access.
     */
    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    'column_names' => [
        'role_pivot_key' => null,
        'permission_pivot_key' => null,
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'team_id',
    ],

    'register_permission_check_method' => true,

    'register_octane_reset_listener' => false,

    'events_enabled' => false,

    'teams' => false,

    'use_passport_client_credentials' => false,

    'display_permission_in_exception' => false,

    'display_role_in_exception' => false,

    'enable_wildcard_permission' => false,

    'cache' => [
        'expiration_time' => DateInterval::createFromDateString('24 hours'),

        'key' => 'spatie.permission.cache',

        /*
         * Request-scoped cache. Nova attaches role and permission pivots with
         * plain Eloquent calls that do not flush Spatie's cache, and the Go API
         * never caches. A per-request cache keeps PHP as current as Go.
         */
        'store' => 'array',
    ],
];
