<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deployment mode
    |--------------------------------------------------------------------------
    | saas   : multi-tenant, central DB + one database per tenant (TDD ADR-01).
    | onprem : single fixed tenant, entitlement from a signed license (TDD §10).
    |          Not implemented yet; the switch exists so code can branch in one
    |          place (ModuleEntitlement binding) instead of everywhere.
    */
    'mode' => env('APP_MODE', 'saas'),

    /*
    | Domains that serve the central app (landing, registration, operator
    | panel). Any other host is treated as a tenant domain.
    */
    'central_domains' => array_values(array_filter(array_map('trim',
        explode(',', env('CENTRAL_DOMAINS', 'erp.localhost,localhost,127.0.0.1'))
    ))),

    /*
    | Tenants get "{slug}.{tenant_base_domain}".
    */
    'tenant_base_domain' => env('TENANT_BASE_DOMAIN', 'erp.localhost'),

    /*
    | Slugs that can never be registered as a tenant subdomain.
    */
    'reserved_subdomains' => [
        'www', 'api', 'app', 'admin', 'mail', 'smtp', 'billing', 'help', 'support',
        'status', 'docs', 'blog', 'static', 'assets', 'cdn', 'dev', 'staging',
        'test', 'demo', 'central', 'root', 'system',
    ],

    'tenant_database' => [
        // Database name = prefix + zero-padded sequence, e.g. erp_t_000123.
        'prefix' => env('TENANT_DB_PREFIX', 'erp_t_'),
        // MySQL username = user_prefix + same sequence, e.g. u_t_000123.
        'user_prefix' => env('TENANT_DB_USER_PREFIX', 'u_t_'),
        // Host part of the tenant MySQL account ('%' or the app server's IP/subnet).
        'user_host' => env('TENANT_DB_USER_HOST', '%'),
        // Connection used for CREATE DATABASE / CREATE USER / GRANT.
        'provisioner_connection' => 'provisioner',
        // Privileges granted to each tenant user on its own database only.
        'grants' => [
            'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'INDEX',
            'DROP', 'REFERENCES', 'CREATE TEMPORARY TABLES', 'LOCK TABLES',
            'CREATE VIEW', 'SHOW VIEW', 'TRIGGER',
        ],
    ],

    /*
    | Modules that every tenant always has (cannot be deactivated).
    */
    'core_modules' => ['core', 'master'],

    'modules_path' => base_path('Modules'),

    /*
    | Self-service registration (TDD §7).
    */
    'registration' => [
        // Unverified registrations are deleted after this many days.
        'unverified_ttl_days' => 7,
        // Minimum admin password length; passwords are also checked against
        // the Have I Been Pwned range API (k-anonymity, only a hash prefix is sent).
        'password_min' => 10,
        'billing_cycles' => ['monthly', 'yearly'],
    ],

    /*
    | ProvisionTenant job (TDD §7 "Penanganan gagal").
    */
    'provisioning' => [
        'queue' => 'provisioning',
        // Attempts per step, and the delay (seconds) before attempt 2, 3, ...
        'max_attempts' => 3,
        'backoff' => [10, 60, 300],
        // Role given to the first tenant user.
        'admin_role' => 'SUPER ADMIN',
    ],
];
