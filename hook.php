<?php

/**
 * Monedas - instalación y desinstalación.
 *
 * @license GPL-3.0-or-later
 */

use GlpiPlugin\Monedas\Config;
use GlpiPlugin\Monedas\Purchase;

function plugin_monedas_install(): bool
{
    /** @var DBmysql $DB */
    global $DB;

    $migration = new Migration(PLUGIN_MONEDAS_VERSION);

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $sign      = DBConnection::getDefaultPrimaryKeySignOption();

    $table = Config::getTable();
    if (!$DB->tableExists($table)) {
        $DB->doQuery(
            "CREATE TABLE `$table` (
                `id` int {$sign} NOT NULL AUTO_INCREMENT,
                `entities_id` int {$sign} NOT NULL DEFAULT '0',
                `currency_code` varchar(3) NOT NULL DEFAULT 'COP',
                `symbol` varchar(10) NOT NULL DEFAULT '$',
                `symbol_position` varchar(10) NOT NULL DEFAULT 'before',
                `decimals` tinyint NOT NULL DEFAULT '0',
                `thousands_separator` varchar(10) NOT NULL DEFAULT 'dot',
                `decimal_separator` varchar(10) NOT NULL DEFAULT 'comma',
                `show_code` tinyint NOT NULL DEFAULT '1',
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `entities_id` (`entities_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC"
        );

        // Configuración inicial en la entidad raíz: pesos colombianos sin decimales.
        $DB->insert($table, Config::DEFAULTS + ['entities_id' => 0]);
    }

    // v0.2: compras en moneda extranjera (una fila por Infocom).
    $table = Purchase::getTable();
    if (!$DB->tableExists($table)) {
        $DB->doQuery(
            "CREATE TABLE `$table` (
                `id` int {$sign} NOT NULL AUTO_INCREMENT,
                `infocoms_id` int {$sign} NOT NULL DEFAULT '0',
                `currency_code` varchar(3) NOT NULL,
                `original_value` decimal(20,4) NOT NULL DEFAULT '0.0000',
                `exchange_rate` decimal(20,6) NOT NULL DEFAULT '0.000000',
                `rate_date` date DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `infocoms_id` (`infocoms_id`),
                KEY `currency_code` (`currency_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC"
        );
    }

    $migration->executeMigration();
    return true;
}

function plugin_monedas_uninstall(): bool
{
    /** @var DBmysql $DB */
    global $DB;

    foreach ([Config::getTable(), Purchase::getTable()] as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`");
        }
    }
    return true;
}
