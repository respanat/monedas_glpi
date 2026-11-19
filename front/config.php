<?php

/**
 * Monedas - acceso desde Configuración > Plugins: abre la pestaña "Moneda"
 * de la entidad raíz.
 *
 * @license GPL-3.0-or-later
 */

use GlpiPlugin\Monedas\Config;

Session::checkRight('config', UPDATE);

Html::redirect(Entity::getFormURLWithID(0) . '&forcetab=' . urlencode(Config::class . '$1'));
