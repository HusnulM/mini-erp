# Mini ERP & POS — SaaS

Laravel 13 + MySQL 8, modular, **database-per-tenant**. Rancangan lengkap ada di *TDD Phase 0 — SaaS Foundation Mini ERP*.

## Status: Sprint 1 selesai

| Area | Isi |
| --- | --- |
| Project | Laravel 13, stancl/tenancy 3.10, nwidart/laravel-modules 13, spatie/laravel-permission 8, Horizon 5 |
| Docker | PHP-FPM 8.4, Nginx (wildcard `*.erp.localhost`), MySQL 8, Redis, Horizon, scheduler |
| Tenancy | 1 DB + 1 MySQL user per tenant, kredensial terenkripsi, identifikasi via domain, halaman "sedang disiapkan" / "tidak aktif" |
| Central DB | Semua tabel TDD §4: tenants, domains, plans, modules, module_dependencies, plan_modules, subscriptions, subscription_addons, tenant_modules, billing_*, provisioning_*, central_users |
| Modul | 8 modul (`Modules/*`) dengan `module.json`, registry + dependency resolver, `php artisan modules:sync` |
| Test | 29 test (unit + feature di MySQL asli), termasuk uji isolasi antar tenant |

Belum dikerjakan (sesuai rencana sprint): registrasi & job `ProvisionTenant` berlog (S2), ModuleManager + entitlement + middleware `module:` (S3), tabel core tenant lengkap + setup wizard (S4), billing (S5).

## Menjalankan dengan Docker

```bash
cp .env.example .env
make setup                      # build, composer install, key, migrate + seed central
make tenant slug=tokoabc name="Toko ABC"
```

Buka:

- Central: http://erp.localhost
- Tenant: http://tokoabc.erp.localhost

`*.localhost` otomatis mengarah ke 127.0.0.1 di Chrome/Firefox, jadi tidak perlu mengubah file hosts. Password operator pertama dicetak saat seeding (atau set `CENTRAL_ADMIN_PASSWORD` sebelum `make setup`).

```bash
make test                       # jalankan test (DB erp_central_test)
make tenant-delete slug=tokoabc # hapus tenant dev + database + user MySQL
```

`composer.lock` belum disertakan; `composer install` pertama akan membuatnya, lalu commit file itu.

## Tanpa Docker

Butuh PHP 8.3+ (pdo_mysql, bcmath, intl, redis, pcntl), MySQL 8, Redis. Buat akun MySQL seperti `docker/mysql/init/01-accounts.sql` (ganti host `%` sesuai kebutuhan), lalu:

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan erp:tenant:create tokoabc --name="Toko ABC"
php artisan serve   # akses dengan header Host: tokoabc.erp.localhost, atau pakai Nginx/Valet
```

## Struktur

```text
app/
├── Central/            ← dunia SaaS (central DB)
│   ├── Models/         Tenant, Domain, Plan, Module, Subscription, TenantModule, Billing*, Provisioning*, CentralUser
│   ├── Enums/          status tenant, subscription, modul, invoice, provisioning
│   ├── Provisioning/   TenantDatabaseProvisioner (langkah idempoten)
│   └── Console/        erp:tenant:create / erp:tenant:delete (helper dev)
├── Tenancy/            ← konteks tenant
│   ├── Database/       TenantDatabaseManager (MySQL user per tenant), TenantDatabaseConfig
│   ├── Middleware/     EnsureTenantIsActive
│   └── UlidGenerator.php
├── Contracts/          ModuleEntitlement (driver SaaS di Sprint 3, license di on-prem)
└── Support/Modules/    ModuleManifest, ModuleRegistry, ModuleServiceProvider, modules:sync
Modules/
├── Core/  MasterData/  Workflow/  Inventory/  Procurement/  Pos/  Finance/  Reporting/
│   └── module.json, app/Providers, database/migrations/tenant, routes/tenant.php, config, lang, views
database/migrations/    ← HANYA central DB
docker/                 ← php, nginx, mysql init
```

## Aturan penting

1. **Migrasi modul hanya untuk DB tenant.** Taruh di `Modules/{Nama}/database/migrations/tenant/`. `ModuleServiceProvider` sengaja tidak memanggil `loadMigrationsFrom()`, supaya `php artisan migrate` tidak pernah membuat tabel bisnis di central DB.
2. **Model central** extend `App\Central\Models\CentralModel` (koneksi `central`). **Model tenant** tidak menyetel `$connection`; tenancy yang memindahkan koneksi default ke DB tenant.
3. **Route tenant** ditulis di `Modules/{Nama}/routes/tenant.php`; otomatis dapat middleware `web` + `tenant`, dan nama route diberi prefix kode modul (`core.dashboard`). Route central di `routes/web.php`, terikat ke `CENTRAL_DOMAINS`.
4. **Semua modul dimuat untuk semua tenant.** Boleh-tidaknya tenant memakai modul diputuskan per request oleh `ModuleEntitlement` (Sprint 3), bukan oleh `modules_statuses.json`.
5. **`module.json` adalah sumber kebenaran katalog modul.** Jalankan `php artisan modules:sync` setiap deploy. Modul yang hilang dari kode ditandai `deprecated`, tidak dihapus.
6. **Akun MySQL:** `erp` hanya ke central DB; `provisioner` untuk CREATE DATABASE/USER/GRANT (simpan kredensialnya hanya di worker provisioning saat production); tiap tenant punya `u_t_{id}` yang hanya bisa mengakses `erp_t_{id}`.

## Catatan desain Sprint 1

- Nama DB dan user tenant memakai ULID tenant (`erp_t_01k…`, `u_t_01k…`), bukan nomor urut `erp_t_000123` seperti di TDD. Hasilnya tidak bisa ditebak, tidak perlu sequence, dan tetap di bawah batas 32 karakter untuk username MySQL.
- Kolom status disimpan sebagai `VARCHAR` dan di-cast ke PHP enum, bukan `ENUM` MySQL, supaya menambah status tidak butuh `ALTER TABLE`.
- Pembuatan DB tenant **tidak** dipicu event model (pipeline bawaan stancl dimatikan). Provisioning berjalan lewat langkah eksplisit dan idempoten di `TenantDatabaseProvisioner`; Sprint 2 membungkusnya dalam job queue dengan log per langkah dan retry.
