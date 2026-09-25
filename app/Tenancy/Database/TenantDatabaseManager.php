<?php

namespace App\Tenancy\Database;

use InvalidArgumentException;
use Stancl\Tenancy\Contracts\ManagesDatabaseUsers;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\DatabaseConfig;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;

/**
 * MySQL manager for database-per-tenant with one MySQL account per tenant.
 *
 * Differences from stancl's PermissionControlledMySQLDatabaseManager:
 *  - every statement is idempotent (IF [NOT] EXISTS), so a provisioning step
 *    can be retried safely (TDD §7);
 *  - identifiers are validated and the password is quoted by PDO;
 *  - the grant list and the account host come from config/erp.php.
 */
class TenantDatabaseManager extends MySQLDatabaseManager implements ManagesDatabaseUsers
{
    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        return $this->ensureDatabase($tenant->database()->getName())
            && $this->createUser($tenant->database());
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        $this->dropDatabase($tenant->database()->getName());

        return $this->deleteUser($tenant->database());
    }

    public function ensureDatabase(string $name): bool
    {
        $name = $this->identifier($name, 64);
        $charset = $this->database()->getConfig('charset') ?: 'utf8mb4';
        $collation = $this->database()->getConfig('collation') ?: 'utf8mb4_unicode_ci';

        return $this->database()->statement(
            "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET `{$charset}` COLLATE `{$collation}`"
        );
    }

    public function dropDatabase(string $name): bool
    {
        return $this->database()->statement('DROP DATABASE IF EXISTS `'.$this->identifier($name, 64).'`');
    }

    public function createUser(DatabaseConfig $databaseConfig): bool
    {
        $database = $this->identifier($databaseConfig->getName(), 64);
        $username = $this->identifier((string) $databaseConfig->getUsername(), 32);
        $password = (string) $databaseConfig->getPassword();

        if ($password === '') {
            throw new InvalidArgumentException('Tenant database password is empty.');
        }

        $account = $this->account($username);
        $quoted = $this->database()->getPdo()->quote($password);
        $db = $this->database();

        $db->statement("CREATE USER IF NOT EXISTS {$account} IDENTIFIED BY {$quoted}");
        // Re-assert the password so a retried step always matches what is stored.
        $db->statement("ALTER USER {$account} IDENTIFIED BY {$quoted}");

        $grants = implode(', ', config('erp.tenant_database.grants'));

        return $db->statement("GRANT {$grants} ON `{$database}`.* TO {$account}");
    }

    public function deleteUser(DatabaseConfig $databaseConfig): bool
    {
        $username = $databaseConfig->getUsername();

        if (! $username) {
            return true;
        }

        return $this->database()->statement('DROP USER IF EXISTS '.$this->account($this->identifier($username, 32)));
    }

    public function userExists(string $username): bool
    {
        return (bool) $this->database()->selectOne(
            'SELECT COUNT(*) AS c FROM mysql.user WHERE user = ? AND host = ?',
            [$username, $this->userHost()]
        )->c;
    }

    public function databaseExists(string $name): bool
    {
        return (bool) $this->database()->selectOne(
            'SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$name]
        )->c;
    }

    protected function account(string $username): string
    {
        return "'{$username}'@'".str_replace("'", '', $this->userHost())."'";
    }

    protected function userHost(): string
    {
        return (string) config('erp.tenant_database.user_host', '%');
    }

    protected function identifier(string $value, int $maxLength): string
    {
        if (! preg_match('/^[a-z0-9_]+$/', $value) || strlen($value) > $maxLength) {
            throw new InvalidArgumentException("Invalid MySQL identifier [{$value}].");
        }

        return $value;
    }
}
