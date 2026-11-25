<?php

/**
 * Monedas - compra de un activo en moneda extranjera.
 *
 * Guarda, por cada registro de información financiera (Infocom), la moneda en
 * que se compró, el valor original y la tasa de cambio usada. El precio de
 * compra de GLPI (Infocom.value) sigue en moneda local y es el que usa la
 * amortización: se calcula como valor original × tasa.
 *
 * @license GPL-3.0-or-later
 */

namespace GlpiPlugin\Monedas;

use CommonDBTM;
use Infocom;
use Session;

class Purchase extends CommonDBTM
{
    /** Campos extra que el formulario envía junto con el Infocom. */
    public const INPUT_CURRENCY = '_monedas_currency';
    public const INPUT_ORIGINAL = '_monedas_original_value';
    public const INPUT_RATE     = '_monedas_rate';
    public const INPUT_DATE     = '_monedas_rate_date';

    public static function getTypeName($nb = 0): string
    {
        return _n('Compra en moneda extranjera', 'Compras en moneda extranjera', $nb, 'monedas');
    }

    public static function getForInfocom(int $infocoms_id): ?array
    {
        if ($infocoms_id <= 0) {
            return null;
        }
        $purchase = new self();
        if ($purchase->getFromDBByCrit(['infocoms_id' => $infocoms_id])) {
            return $purchase->fields;
        }
        return null;
    }

    /**
     * Monedas extranjeras disponibles para una configuración local.
     */
    public static function getForeignCurrencies(array $cfg): array
    {
        $list = [];
        foreach (Config::CURRENCIES as $code => [$name, $symbol, $decimals]) {
            if ($code === $cfg['currency_code']) {
                continue;
            }
            $list[] = ['code' => $code, 'name' => $name, 'symbol' => $symbol, 'decimals' => $decimals];
        }
        return $list;
    }

    /**
     * Lee los datos de moneda extranjera enviados con el Infocom.
     * Devuelve null si se eligió la moneda local o si los datos no son válidos.
     *
     * @return array{currency_code: string, original_value: string, exchange_rate: string, rate_date: ?string}|null
     */
    public static function readInput(array $input, array $cfg): ?array
    {
        $code = strtoupper(trim((string) ($input[self::INPUT_CURRENCY] ?? '')));
        if ($code === '' || $code === $cfg['currency_code'] || !isset(Config::CURRENCIES[$code])) {
            return null;
        }

        $original = self::toNumber($input[self::INPUT_ORIGINAL] ?? '', $cfg);
        $rate     = self::toNumber($input[self::INPUT_RATE] ?? '', $cfg);
        if ($original === null || $rate === null || (float) $rate <= 0 || (float) $original < 0) {
            return null;
        }

        $date = trim((string) ($input[self::INPUT_DATE] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = null;
        }

        return [
            'currency_code'  => $code,
            'original_value' => $original,
            'exchange_rate'  => $rate,
            'rate_date'      => $date,
        ];
    }

    private static function toNumber(mixed $value, array $cfg): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        return is_numeric($value) ? $value : Config::parse($value, $cfg);
    }

    /**
     * Antes de guardar el Infocom: si la compra es en moneda extranjera, el
     * precio de compra en moneda local se calcula en el servidor.
     */
    public static function preSave(Infocom $item): void
    {
        // Primero: importes escritos con formato local pasan a número.
        Config::normalizeInfocom($item);

        if (!is_array($item->input) || !array_key_exists(self::INPUT_CURRENCY, $item->input)) {
            return;
        }
        $cfg  = Config::getForEntity(self::entityOf($item));
        $data = self::readInput($item->input, $cfg);
        if ($data !== null) {
            $item->input['value'] = number_format((float) $data['original_value'] * (float) $data['exchange_rate'], 4, '.', '');
        }
    }

    /**
     * Después de guardar el Infocom: crea, actualiza o borra el registro de compra.
     */
    public static function postSave(Infocom $item): void
    {
        if (!is_array($item->input) || !array_key_exists(self::INPUT_CURRENCY, $item->input)) {
            return;
        }
        $infocoms_id = (int) $item->getID();
        if ($infocoms_id <= 0) {
            return;
        }

        $cfg      = Config::getForEntity(self::entityOf($item));
        $data     = self::readInput($item->input, $cfg);
        $purchase = new self();
        $exists   = $purchase->getFromDBByCrit(['infocoms_id' => $infocoms_id]);

        if ($data === null) {
            // Se eligió la moneda local: ya no hay compra en moneda extranjera.
            if ($exists) {
                $purchase->delete(['id' => $purchase->getID()], true);
            }
            return;
        }

        if ($exists) {
            $purchase->update(['id' => $purchase->getID()] + $data);
        } else {
            $purchase->add(['infocoms_id' => $infocoms_id] + $data);
        }
    }

    /**
     * Al purgar el Infocom se borra también su registro de compra.
     */
    public static function purge(Infocom $item): void
    {
        $purchase = new self();
        if ($purchase->getFromDBByCrit(['infocoms_id' => (int) $item->getID()])) {
            $purchase->delete(['id' => $purchase->getID()], true);
        }
    }

    private static function entityOf(Infocom $item): int
    {
        return (int) ($item->input['entities_id']
            ?? $item->fields['entities_id']
            ?? Session::getActiveEntity());
    }
}
