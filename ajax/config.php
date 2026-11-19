<?php

/**
 * Monedas - devuelve en JSON la configuración de moneda que aplica a un activo
 * (o a la entidad activa si no se indica activo), las monedas extranjeras
 * disponibles y, si existe, la compra en moneda extranjera del activo.
 *
 * GET ?itemtype=Computer&items_id=156
 *
 * @license GPL-3.0-or-later
 */

use GlpiPlugin\Monedas\Config;
use GlpiPlugin\Monedas\Purchase;

Session::checkLoginUser();

header('Content-Type: application/json; charset=UTF-8');
Html::header_nocache();

$entities_id = Session::getActiveEntity();
$purchase    = null;
$buy_date    = null;

$itemtype = (string) ($_GET['itemtype'] ?? '');
$items_id = (int) ($_GET['items_id'] ?? 0);

if ($itemtype !== '' && $items_id > 0) {
    $item = getItemForItemtype($itemtype);
    if ($item instanceof CommonDBTM && $item->can($items_id, READ)) {
        $entities_id = (int) $item->getEntityID();

        $infocom = new Infocom();
        if ($infocom->getFromDBforDevice($itemtype, $items_id)) {
            $buy_date = $infocom->fields['buy_date'] ?: null;
            $row      = Purchase::getForInfocom((int) $infocom->getID());
            if ($row !== null) {
                $purchase = [
                    'currency_code'  => $row['currency_code'],
                    'original_value' => (float) $row['original_value'],
                    'exchange_rate'  => (float) $row['exchange_rate'],
                    'rate_date'      => $row['rate_date'],
                ];
            }
        }
    }
}

$cfg = Config::getForEntity((int) $entities_id);

echo json_encode(Config::toClientConfig($cfg) + [
    'foreign_currencies' => Purchase::getForeignCurrencies($cfg),
    'purchase'           => $purchase,
    'buy_date'           => $buy_date,
]);
