# Mini ERP & POS — SaaS

Laravel 13 + MySQL 8, modular, **database-per-tenant**. Rancangan lengkap ada di *TDD Phase 0 — SaaS Foundation Mini ERP*.

## Status: Sprint 3 selesai

| Area | Isi |
| --- | --- |
| Project | Laravel 13, stancl/tenancy 3.10, nwidart/laravel-modules 13, spatie/laravel-permission 8, Horizon 5 |
| Docker | PHP-FPM 8.4, Nginx (wildcard `*.erp.localhost`), MySQL 8, Redis, Horizon, scheduler |
| Tenancy | 1 DB + 1 MySQL user per tenant, kredensial terenkripsi, identifikasi via domain, halaman "sedang disiapkan" / "tidak aktif" |
| Central DB | Semua tabel TDD §4: tenants, domains, plans, modules, module_dependencies, plan_modules, subscriptions, subscription_addons, tenant_modules, billing_*, provisioning_*, central_users |
| Modul | 8 modul (`Modules/*`) dengan `module.json`, registry + dependency resolver, `php artisan modules:sync` |
| Registrasi (S2) | Form di domain central (`/register`), validasi TDD §7, captcha Turnstile, cek password bocor (HIBP), verifikasi email via signed link, registrasi tak terverifikasi dihapus setelah 7 hari |
| Provisioning (S2) | Job `ProvisionTenant` di queue `provisioning`, 8 langkah tercatat di `provisioning_runs/steps`, maks 3 percobaan per langkah (backoff 10s/60s/300s), retry dari langkah yang gagal |
| Subscription (S2) | Verifikasi email → subscription `trialing` + `tenant_modules` sesuai plan (+ dependency) |
| Panel operator (S2) | `/admin` di domain central, guard `central`: login, daftar tenant + status, detail provisioning per langkah, tombol "Retry from step" |
| Entitlement (S3) | `SubscriptionEntitlement` (driver SaaS `ModuleEntitlement`): state active/readonly/inactive per modul dari `tenant_modules` + subscription, cache 5 menit, di-flush otomatis saat data langganan berubah |
| Modul (S3) | Middleware `module:{code}` di semua route modul, `ModuleManager` (aktivasi + dependency, nonaktifkan, add-on), install lewat run `install_module` yang berlog dan bisa di-retry, permission dan menu dinamis dari `module.json` |
| CI | GitHub Actions: Pint + test di MySQL 8 dan Redis 7 |
| Test | 110 test (unit + feature di MySQL asli), termasuk isolasi antar tenant, provisioning yang digagalkan lalu di-retry, dan acceptance criteria modul (403, menu, permission, add-on, readonly) |

Belum dikerjakan (sesuai rencana sprint): tabel core tenant lengkap + setup wizard (S4), billing (S5).

## Menjalankan dengan Docker

```bash
cp .env.example .env
make setup                      # build, composer install, key, migrate + seed central
make tenant slug=tokoabc name="Toko ABC"
```

Buka:

- Central: http://erp.localhost (registrasi: `/register`, panel operator: `/admin`)
- Tenant: http://tokoabc.erp.localhost (login: `/login`)

`make tenant` / `php artisan erp:tenant:create tokoabc --plan=STARTER --password=...` adalah jalan pintas dev: registrasi tanpa form, email, dan captcha, lalu provisioning sinkron dengan langkah yang sama seperti job (subscription, modul plan, user admin). Login admin dicetak di akhir. Alur sebenarnya: daftar di `/register`, klik link verifikasi di email (dengan `MAIL_MAILER=log`, link ada di `storage/logs/laravel.log`), lalu Horizon menjalankan `ProvisionTenant`.

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

## Tanpa Docker, di sesi cloud (tes)

```bash
apt-get install -y mysql-server redis-server
service mysql start && redis-server --daemonize yes
mysql -uroot -e "CREATE DATABASE erp_central; CREATE USER 'erp'@'%' IDENTIFIED BY 'secret'; GRANT ALL ON erp_central.* TO 'erp'@'%';"
mysql -uroot < docker/mysql/init/01-accounts.sql
composer install && cp .env.example .env && php artisan key:generate
php artisan test
```

## Registrasi & provisioning (Sprint 2)

```text
POST /register ──► tenant pending + domain ──► email verifikasi (signed link, 7 hari)
GET  /register/verify/{tenant}/{hash} ──► subscription trialing + tenant_modules (installing)
                                     ──► provisioning_run + 8 step ──► ProvisionTenant (queue provisioning)
ProvisionTenant: reserve_names → create_database → create_db_user → migrate_core
                 → install_modules → seed_defaults → create_admin → finalize ──► email "sistem siap"
```

- **Langkah** ada di `App\Central\Provisioning\ProvisioningRunner`; job hanya memanggil `execute()`. Semua langkah idempoten: `install_modules` melewati modul yang sudah ada di `installed_modules`, `create_admin` memakai `firstOrCreate` by email, email "siap" dikirim sekali.
- **Gagal**: langkah yang error ditandai `failed` dengan pesan error, lalu job di-`release()` dengan backoff 10s/60s/300s dan melanjutkan dari langkah itu (langkah `done` tidak diulang). Setelah 3 percobaan: run dan tenant `failed`, operator (role owner/support) dapat email, customer melihat halaman "sedang disiapkan".
- **Retry from step** (panel operator, role owner/support): mereset langkah itu dan sesudahnya ke `pending`, lalu dispatch ulang. Langkah sebelumnya harus sudah `done`.
- **Password admin** langsung di-hash saat registrasi, disimpan terenkripsi di `tenants.data`, dan dihapus dari central setelah `create_admin`.
- Satu run hanya diproses satu worker (cache lock `provisioning-run:{id}`).
- Registrasi tak terverifikasi dihapus oleh `php artisan erp:registrations:purge` (dijadwalkan harian 02:15; butuh `schedule:work`/cron).
- Captcha: Cloudflare Turnstile (`TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`). Nilai default di `.env.example` adalah test key Cloudflare yang selalu lolos; ganti di production.

## Modul & entitlement (Sprint 3)

| State | Kapan | Akibat |
| --- | --- | --- |
| `active` | baris `tenant_modules` active, subscription trialing/active/past_due | akses penuh |
| `readonly` | baris readonly, `expires_at` lewat, atau subscription expired/cancelled | GET boleh; POST/PUT/DELETE → 403 dengan pesan; menu diberi tanda "(baca saja)" |
| `inactive` | belum ada baris, atau inactive/installing/failed | route 403, menu tidak tampil |

- **Aktivasi** (`ModuleManager::activate`): modul harus termasuk core, plan, atau add-on yang masih berjalan. Dependency yang belum aktif ikut diaktifkan kalau ter-entitle; kalau tidak, aktivasi ditolak dengan pesan yang menyebut modul yang kurang. Modul ditandai `installing`, lalu run `install_module` (langkah `install:{code}`) dijalankan di queue `provisioning` dengan retry yang sama seperti provisioning.
- **Install modul**: migrasi tenant → permission dari `module.json` (`entity.*` → view/create/update/delete, lihat `erp.permission_actions`) diberikan ke `SUPER ADMIN` → `Installer::seed()` kalau manifest punya `installer` → `installed_modules` → `tenant_modules` active.
- **Nonaktifkan**: ditolak untuk modul core dan selama masih ada modul aktif yang membutuhkannya. Data dan tabel tetap ada, dan aktivasi ulang tidak memigrasi ulang.
- **Menu**: key `menu` di `module.json` (`label`, `route`, `permission`, `order`). `MenuBuilder` hanya menampilkan item dari modul yang bisa dibaca dan yang user punya permission-nya.
- **Panel operator**: tabel modul per tenant dengan tombol Aktifkan / Nonaktifkan (owner, support) dan Tambah add-on (owner, billing). Tambah add-on juga menambahkan add-on dependency yang belum termasuk plan (mis. procurement → workflow); ini jalan pintas sampai billing di S5.

## Struktur

```text
app/
├── Central/            ← dunia SaaS (central DB)
│   ├── Models/         Tenant, Domain, Plan, Module, Subscription, TenantModule, Billing*, Provisioning*, CentralUser
│   ├── Enums/          status tenant, subscription, modul, invoice, provisioning
│   ├── Provisioning/   TenantDatabaseProvisioner (operasi DB idempoten), ProvisioningRunner (langkah + retry),
│   │                   ModuleInstaller, TenantAdminCreator
│   ├── Registration/   RegisterTenant, VerifyRegistration, CaptchaVerifier (Turnstile)
│   ├── Entitlement/    SubscriptionEntitlement, EntitlementSnapshot, EntitlementCache
│   ├── Modules/        ModuleManager
│   ├── Jobs/           ProvisionTenant
│   ├── Http/           RegistrationController, Admin/* (panel operator)
│   ├── Notifications/  VerifyRegistrationEmail, TenantReady, ProvisioningFailed
│   └── Console/        erp:registrations:purge, erp:tenant:create / erp:tenant:delete (helper dev)
├── Tenancy/            ← konteks tenant
│   ├── Database/       TenantDatabaseManager (MySQL user per tenant), TenantDatabaseConfig
│   ├── Middleware/     EnsureTenantIsActive, EnsureModuleIsActive (`module:`)
│   └── UlidGenerator.php
├── Contracts/          ModuleEntitlement (driver SaaS di Sprint 3, license di on-prem)
├── Support/Modules/    ModuleManifest, ModuleRegistry, ModuleServiceProvider, MenuBuilder, Installer, modules:sync
└── Support/CentralRoutes.php  nama route central per domain + helper central_route()
Modules/
├── Core/  MasterData/  Workflow/  Inventory/  Procurement/  Pos/  Finance/  Reporting/
│   └── module.json, app/Providers, database/migrations/tenant, routes/tenant.php, config, lang, views
routes/central.php      ← route central (didaftarkan per domain oleh routes/web.php)
database/migrations/    ← HANYA central DB
docker/                 ← php, nginx, mysql init
```

## Aturan penting

1. **Migrasi modul hanya untuk DB tenant.** Taruh di `Modules/{Nama}/database/migrations/tenant/`. `ModuleServiceProvider` sengaja tidak memanggil `loadMigrationsFrom()`, supaya `php artisan migrate` tidak pernah membuat tabel bisnis di central DB.
2. **Model central** extend `App\Central\Models\CentralModel` (koneksi `central`). **Model tenant** tidak menyetel `$connection`; tenancy yang memindahkan koneksi default ke DB tenant.
3. **Route tenant** ditulis di `Modules/{Nama}/routes/tenant.php`; otomatis dapat middleware `web` + `tenant`, dan nama route diberi prefix kode modul (`core.dashboard`). Route central di `routes/central.php`, didaftarkan sekali per domain di `CENTRAL_DOMAINS`; buat link-nya dengan `central_route('admin.tenants.index')`, bukan `route()`.
4. **Semua modul dimuat untuk semua tenant.** Boleh-tidaknya tenant memakai modul diputuskan per request oleh `ModuleEntitlement` (middleware `module:{code}`, dipasang otomatis di route modul), bukan oleh `modules_statuses.json`. Jangan cek tabel subscription atau `APP_MODE` langsung; route yang harus jalan apa pun state modulnya (mis. login) memakai `->withoutMiddleware('module:{code}')`.
5. **`module.json` adalah sumber kebenaran katalog modul.** Jalankan `php artisan modules:sync` setiap deploy. Modul yang hilang dari kode ditandai `deprecated`, tidak dihapus.
6. **Akun MySQL:** `erp` hanya ke central DB; `provisioner` untuk CREATE DATABASE/USER/GRANT (simpan kredensialnya hanya di worker provisioning saat production); tiap tenant punya `u_t_{id}` yang hanya bisa mengakses `erp_t_{id}`.

## Catatan desain Sprint 1

- Nama DB dan user tenant memakai ULID tenant (`erp_t_01k…`, `u_t_01k…`), bukan nomor urut `erp_t_000123` seperti di TDD. Hasilnya tidak bisa ditebak, tidak perlu sequence, dan tetap di bawah batas 32 karakter untuk username MySQL.
- Kolom status disimpan sebagai `VARCHAR` dan di-cast ke PHP enum, bukan `ENUM` MySQL, supaya menambah status tidak butuh `ALTER TABLE`.
- Pembuatan DB tenant **tidak** dipicu event model (pipeline bawaan stancl dimatikan). Provisioning berjalan lewat langkah eksplisit dan idempoten di `TenantDatabaseProvisioner`; Sprint 2 membungkusnya dalam job queue dengan log per langkah dan retry.

## Catatan desain Sprint 2

- **Panel operator memakai Blade biasa**, bukan Filament: tidak menambah dependency besar, mudah dites, dan cukup untuk list/detail/retry. Kalau nanti pindah ke Filament (TDD §2), logika retry sudah ada di `ProvisioningRunner::retryFrom()` sehingga UI tinggal memanggilnya.
- Langkah `warm_up` dari TDD tidak dibuat: cache entitlement baru ada di Sprint 3. Tambahkan ke `ProvisioningRunner::STEPS` saat itu (run lama tidak terpengaruh karena langkah disimpan per run).
- Retry per langkah dijalankan lewat `release()` job, bukan `$tries`/`$backoff` Laravel, supaya jatah 3 percobaan berlaku per langkah dan langkah yang sudah `done` tidak diulang.
- Semua registrasi mulai sebagai **trial** (`trial_days` dari plan). Pembayaran di awal untuk plan berbayar menyusul bersama payment gateway (S5, TDD open question).
- `install_modules` sementara memakai `ModuleInstaller` (migrasi + `installed_modules` + `tenant_modules` active). Sinkron permission dan `Installer::seed()` per modul dipindah ke ModuleManager di Sprint 3.
- Tabel spatie/laravel-permission sekarang ikut migrasi Core tenant; `seed_defaults` membuat role `SUPER ADMIN` (role default lain, currency, pajak, sequence menyusul bersama tabelnya di S4). Cache permission diberi key per tenant supaya worker yang memproses banyak tenant tidak mencampur role.
- Login tenant (`/login`) sengaja minimal agar link di email "sistem siap" bisa dipakai; lock akun, 2FA, dan redirect ke setup wizard di S4.

## Catatan desain Sprint 3

- Snapshot entitlement di-cache sebagai **array biasa**, bukan objek: Laravel 13 menolak unserialize objek dari cache (`cache.serializable_classes = false`). Store `array` untuk test diset `serialize => true` supaya test berperilaku seperti Redis; bug ini sempat hanya muncul di Redis.
- Cache entitlement memakai `Cache::store()` tanpa tag tenant, sehingga key yang sama bisa di-flush dari konteks central (event billing, panel operator). Flush otomatis lewat trait `FlushesEntitlementCache` di `TenantModule`, `Subscription`, dan `SubscriptionAddon`; update lewat query builder mentah **tidak** mem-flush.
- Kegagalan run `install_module` tidak mengubah status tenant: tenant tetap bisa dipakai, hanya modulnya yang `failed` sampai di-retry.
- Route modul non-core baru berisi halaman placeholder (dibangun di Phase 1). Dashboard masih publik seperti Sprint 1; menu tampil kalau user login. Redirect ke setup wizard dan `EnsureSetupCompleted` di S4.
- Pengecualian POS dari TDD §8 (shift yang sedang terbuka boleh ditutup saat readonly) dan job terjadwal yang dilewati untuk modul inactive dibuat bersama modulnya di Phase 1.
- Belum ada notifikasi ke admin tenant saat modul selesai diaktifkan.
