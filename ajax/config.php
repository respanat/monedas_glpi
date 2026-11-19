<?php

/**
 * Monedas - devuelve en JSON la configuración de moneda que aplica a un activo
 * (o a la entidad activa si no se indica activo).
 *
 * GET ?itemtype=Computer&items_id=156
 *
 * @license GPL-3.0-or-later
 */

use GlpiPlugin\Monedas\Config;

Session::checkLoginUser();

header('Content-Type: application/json; charset=UTF-8');
Html::header_nocache();

$entities_id = Session::getActiveEntity();

$itemtype = (string) ($_GET['itemtype'] ?? '');
$items_id = (int) ($_GET['items_id'] ?? 0);

if ($itemtype !== '' && $items_id > 0) {
    $item = getItemForItemtype($itemtype);
    if ($item instanceof CommonDBTM && $item->can($items_id, READ)) {
        $entities_id = (int) $item->getEntityID();
    }
}

echo json_encode(Config::toClientConfig(Config::getForEntity((int) $entities_id)));
