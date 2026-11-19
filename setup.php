<?php

/**
 * Monedas - Formato de moneda para la información financiera de GLPI.
 *
 * @license GPL-3.0-or-later
 */

use Glpi\Plugin\Hooks;
use GlpiPlugin\Monedas\Config;

define('PLUGIN_MONEDAS_VERSION', '0.1.0');
define('PLUGIN_MONEDAS_MIN_GLPI', '11.0.0');
define('PLUGIN_MONEDAS_MAX_GLPI', '11.0.99');

/**
 * Registro del plugin: pestaña en Entidades, JS/CSS y normalización de importes.
 */
function plugin_init_monedas(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['monedas'] = true;

    if (!Plugin::isPluginActive('monedas')) {
        return;
    }

    // Pestaña "Moneda" en el formulario de cada entidad.
    Plugin::registerClass(Config::class, ['addtabon' => Entity::class]);

    // Página de configuración desde Configuración > Plugins.
    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['monedas'] = 'front/config.php';
    }

    // Formateo en el navegador de los campos de la información financiera.
    $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['monedas'] = ['js/monedas.js'];
    $PLUGIN_HOOKS[Hooks::ADD_CSS]['monedas']        = ['css/monedas.css'];

    // Red de seguridad: si llega un importe con formato (ej. "4.109.900"),
    // se convierte a número antes de guardarlo.
    $PLUGIN_HOOKS[Hooks::PRE_ITEM_ADD]['monedas']    = [Infocom::class => [Config::class, 'normalizeInfocom']];
    $PLUGIN_HOOKS[Hooks::PRE_ITEM_UPDATE]['monedas'] = [Infocom::class => [Config::class, 'normalizeInfocom']];
}

function plugin_version_monedas(): array
{
    return [
        'name'         => 'Monedas',
        'version'      => PLUGIN_MONEDAS_VERSION,
        'author'       => 'Ramiro España',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/respanat/monedas_glpi',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_MONEDAS_MIN_GLPI,
                'max' => PLUGIN_MONEDAS_MAX_GLPI,
            ],
            'php' => [
                'min' => '8.2',
            ],
        ],
    ];
}

function plugin_monedas_check_prerequisites(): bool
{
    return true;
}

function plugin_monedas_check_config($verbose = false): bool
{
    return true;
}
