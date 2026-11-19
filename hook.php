<?php

/**
 * Monedas - instalación y desinstalación.
 *
 * @license GPL-3.0-or-later
 */

use GlpiPlugin\Monedas\Config;

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

    $migration->executeMigration();
    return true;
}

function plugin_monedas_uninstall(): bool
{
    /** @var DBmysql $DB */
    global $DB;

    $table = Config::getTable();
    if ($DB->tableExists($table)) {
        $DB->doQuery("DROP TABLE `$table`");
    }
    return true;
}
