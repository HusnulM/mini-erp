-- Runs once, when the MySQL volume is first created.
-- 'erp' (created by MYSQL_USER) owns only the central database.
-- 'provisioner' creates tenant databases and per-tenant users; it must hold
-- every privilege it grants (config/erp.php tenant_database.grants) WITH GRANT OPTION.

CREATE DATABASE IF NOT EXISTS erp_central_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON erp_central_test.* TO 'erp'@'%';

CREATE USER IF NOT EXISTS 'provisioner'@'%' IDENTIFIED BY 'secret';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES,
      CREATE TEMPORARY TABLES, LOCK TABLES, CREATE VIEW, SHOW VIEW, TRIGGER,
      CREATE USER
  ON *.* TO 'provisioner'@'%' WITH GRANT OPTION;

FLUSH PRIVILEGES;
