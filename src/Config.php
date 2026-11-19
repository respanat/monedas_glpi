<?php

/**
 * Monedas - configuración de moneda y formato por entidad.
 *
 * @license GPL-3.0-or-later
 */

namespace GlpiPlugin\Monedas;

use CommonDBTM;
use CommonGLPI;
use Entity;
use Glpi\Application\View\TemplateRenderer;
use Infocom;
use Session;

class Config extends CommonDBTM
{
    public static $rightname = 'config';

    /** Valores con los que se crea la configuración de la entidad raíz. */
    public const DEFAULTS = [
        'currency_code'       => 'COP',
        'symbol'              => '$',
        'symbol_position'     => 'before',
        'decimals'            => 0,
        'thousands_separator' => 'dot',
        'decimal_separator'   => 'comma',
        'show_code'           => 1,
    ];

    /** Código ISO => [nombre, símbolo sugerido, decimales sugeridos]. */
    public const CURRENCIES = [
        'COP' => ['Peso colombiano', '$', 0],
        'USD' => ['Dólar estadounidense', 'US$', 2],
        'EUR' => ['Euro', '€', 2],
        'MXN' => ['Peso mexicano', 'MX$', 2],
        'PEN' => ['Sol peruano', 'S/', 2],
        'CLP' => ['Peso chileno', '$', 0],
        'ARS' => ['Peso argentino', '$', 2],
        'BRL' => ['Real brasileño', 'R$', 2],
    ];

    /** Clave guardada => carácter real. */
    public const THOUSANDS_SEPARATORS = [
        'dot'   => '.',
        'comma' => ',',
        'space' => ' ',
        'none'  => '',
    ];

    public const DECIMAL_SEPARATORS = [
        'comma' => ',',
        'dot'   => '.',
    ];

    /** Campos de Infocom que guardan importes. */
    public const AMOUNT_FIELDS = ['value', 'warranty_value'];

    public static function getTypeName($nb = 0): string
    {
        return __('Moneda', 'monedas');
    }

    public static function getIcon(): string
    {
        return 'ti ti-coin';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if ($item instanceof Entity && Session::haveRight('config', READ)) {
            return self::createTabEntry(self::getTypeName());
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item instanceof Entity) {
            self::showForEntity($item);
        }
        return true;
    }

    /**
     * Configuración guardada directamente en la entidad (sin herencia).
     */
    public static function getStoredForEntity(int $entities_id): ?array
    {
        $config = new self();
        if ($config->getFromDBByCrit(['entities_id' => $entities_id])) {
            return $config->fields;
        }
        return null;
    }

    /**
     * Configuración efectiva: la de la entidad o la de su ancestro más cercano.
     * Agrega la clave "inherited_from" con el ID de la entidad de origen.
     */
    public static function getForEntity(int $entities_id): array
    {
        $current = $entities_id;
        $guard   = 0;

        while ($guard++ < 50) {
            $stored = self::getStoredForEntity($current);
            if ($stored !== null) {
                return $stored + ['inherited_from' => $current];
            }
            if ($current <= 0) {
                break;
            }
            $entity = new Entity();
            if (!$entity->getFromDB($current)) {
                break;
            }
            $current = (int) $entity->fields['entities_id'];
        }

        return self::DEFAULTS + ['entities_id' => $entities_id, 'inherited_from' => null];
    }

    /**
     * Datos mínimos que necesita el JavaScript del navegador.
     */
    public static function toClientConfig(array $cfg): array
    {
        return [
            'currency_code'   => $cfg['currency_code'],
            'symbol'          => $cfg['symbol'],
            'symbol_position' => $cfg['symbol_position'],
            'decimals'        => (int) $cfg['decimals'],
            'thousands'       => self::THOUSANDS_SEPARATORS[$cfg['thousands_separator']] ?? '.',
            'decimal'         => self::DECIMAL_SEPARATORS[$cfg['decimal_separator']] ?? ',',
            'show_code'       => (bool) $cfg['show_code'],
        ];
    }

    /**
     * Formatea un número con símbolo y código, ej. "$ 4.109.900 COP".
     */
    public static function format(float $amount, array $cfg): string
    {
        $client = self::toClientConfig($cfg);
        $number = number_format(
            abs($amount),
            $client['decimals'],
            $client['decimal'],
            $client['thousands'] === ' ' ? "\u{00A0}" : $client['thousands']
        );
        if ($amount < 0) {
            $number = '-' . $number;
        }

        $out = $client['symbol_position'] === 'after'
            ? $number . "\u{00A0}" . $client['symbol']
            : $client['symbol'] . "\u{00A0}" . $number;

        if ($client['show_code']) {
            $out .= ' ' . $client['currency_code'];
        }
        return $out;
    }

    /**
     * Convierte un importe escrito con formato local a número.
     * Devuelve null si no se puede interpretar.
     *
     * Misma lógica que MonedasFmt.parse() en public/js/monedas.js.
     */
    public static function parse(string $value, array $cfg): ?string
    {
        $client = self::toClientConfig($cfg);
        $t = $client['thousands'];
        $d = $client['decimal'];

        $s = preg_replace('/[^\d.,\-\s\x{00A0}\x{202F}]/u', '', $value);
        $s = preg_replace('/[\s\x{00A0}\x{202F}]/u', '', (string) $s);
        if ($s === '' || $s === '-') {
            return null;
        }

        if ($t !== '' && $t !== ' ') {
            $parts = explode($t, $s);
            // "1250.5" con miles = "." -> se entiende como decimal (no son 3 dígitos).
            if (count($parts) === 2 && !str_contains($s, $d) && strlen($parts[1]) !== 3) {
                $s = $parts[0] . '.' . $parts[1];
            } else {
                $s = implode('', $parts);
            }
        }
        $s = str_replace($d, '.', $s);

        return is_numeric($s) ? $s : null;
    }

    /**
     * Hook pre_item_add / pre_item_update de Infocom: si un importe llega con
     * formato (porque el JS no cargó, por API, etc.) se normaliza a número.
     */
    public static function normalizeInfocom(Infocom $item): void
    {
        if (!is_array($item->input)) {
            return;
        }

        $entities_id = (int) ($item->input['entities_id']
            ?? $item->fields['entities_id']
            ?? Session::getActiveEntity());
        $cfg = null;

        foreach (self::AMOUNT_FIELDS as $field) {
            if (!isset($item->input[$field]) || !is_string($item->input[$field])) {
                continue;
            }
            $raw = trim($item->input[$field]);
            if ($raw === '' || is_numeric($raw)) {
                continue;
            }
            $cfg ??= self::getForEntity($entities_id);
            $parsed = self::parse($raw, $cfg);
            if ($parsed !== null) {
                $item->input[$field] = $parsed;
            }
        }
    }

    /**
     * Valida y limpia los datos enviados desde el formulario.
     *
     * @return array{0: array, 1: string[]} [datos limpios, errores]
     */
    public static function sanitizeInput(array $input): array
    {
        $errors = [];
        $data   = [];

        $code = strtoupper(trim((string) ($input['currency_code'] ?? '')));
        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            $errors[] = __('El código de moneda debe tener 3 letras (ej. COP).', 'monedas');
        }
        $data['currency_code'] = $code;

        $symbol = trim((string) ($input['symbol'] ?? ''));
        if ($symbol === '' || mb_strlen($symbol) > 10) {
            $errors[] = __('El símbolo es obligatorio y tiene máximo 10 caracteres.', 'monedas');
        }
        $data['symbol'] = $symbol;

        $position = (string) ($input['symbol_position'] ?? 'before');
        $data['symbol_position'] = in_array($position, ['before', 'after'], true) ? $position : 'before';

        $decimals = (int) ($input['decimals'] ?? 0);
        $data['decimals'] = max(0, min(4, $decimals));

        $thousands = (string) ($input['thousands_separator'] ?? 'dot');
        $data['thousands_separator'] = array_key_exists($thousands, self::THOUSANDS_SEPARATORS) ? $thousands : 'dot';

        $decimal = (string) ($input['decimal_separator'] ?? 'comma');
        $data['decimal_separator'] = array_key_exists($decimal, self::DECIMAL_SEPARATORS) ? $decimal : 'comma';

        if (self::THOUSANDS_SEPARATORS[$data['thousands_separator']] === self::DECIMAL_SEPARATORS[$data['decimal_separator']]) {
            $errors[] = __('El separador de miles y el decimal no pueden ser el mismo.', 'monedas');
        }

        $data['show_code'] = empty($input['show_code']) ? 0 : 1;

        return [$data, $errors];
    }

    /**
     * Guarda (crea o actualiza) la configuración propia de una entidad.
     */
    public static function saveForEntity(int $entities_id, array $data): bool
    {
        $config = new self();
        if ($config->getFromDBByCrit(['entities_id' => $entities_id])) {
            return (bool) $config->update(['id' => $config->getID()] + $data);
        }
        return (bool) $config->add(['entities_id' => $entities_id] + $data);
    }

    /**
     * Elimina la configuración propia para que la entidad vuelva a heredar.
     */
    public static function resetForEntity(int $entities_id): bool
    {
        if ($entities_id === 0) {
            return false; // La raíz siempre necesita una configuración.
        }
        $config = new self();
        if ($config->getFromDBByCrit(['entities_id' => $entities_id])) {
            return (bool) $config->delete(['id' => $config->getID()], true);
        }
        return true;
    }

    /**
     * URL pública del plugin (GLPI 11 sirve los plugins bajo /plugins/<clave>).
     */
    public static function getPluginUrl(string $path = ''): string
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;
        return rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/monedas' . $path;
    }

    public static function showForEntity(Entity $entity): void
    {
        $entities_id = (int) $entity->getID();
        $stored      = self::getStoredForEntity($entities_id);
        $effective   = self::getForEntity($entities_id);

        $inherited_name = null;
        if ($stored === null && $effective['inherited_from'] !== null) {
            $inherited_name = \Dropdown::getDropdownName('glpi_entities', (int) $effective['inherited_from']);
        }

        $currencies = [];
        foreach (self::CURRENCIES as $code => [$name, $symbol, $decimals]) {
            $currencies[$code] = [
                'label'    => sprintf('%s – %s', $code, $name),
                'symbol'   => $symbol,
                'decimals' => $decimals,
            ];
        }

        TemplateRenderer::getInstance()->display('@monedas/config.html.twig', [
            'form_url'        => self::getPluginUrl('/front/config.form.php'),
            'entities_id'     => $entities_id,
            'config'          => $effective,
            'client_config'   => self::toClientConfig($effective),
            'is_stored'       => $stored !== null,
            'inherited_name'  => $inherited_name,
            'canedit'         => Session::haveRight('config', UPDATE),
            'currencies'      => $currencies,
            'thousands_opts'  => [
                'dot'   => __('Punto (1.000.000)', 'monedas'),
                'comma' => __('Coma (1,000,000)', 'monedas'),
                'space' => __('Espacio (1 000 000)', 'monedas'),
                'none'  => __('Ninguno (1000000)', 'monedas'),
            ],
            'decimal_opts'    => [
                'comma' => __('Coma (0,50)', 'monedas'),
                'dot'   => __('Punto (0.50)', 'monedas'),
            ],
            'samples'         => [
                ['label' => __('Precio de compra', 'monedas'), 'value' => 4109900],
                ['label' => __('Valor neto contable', 'monedas'), 'value' => 1137833.43],
                ['label' => __('Valor con centavos', 'monedas'), 'value' => 1250.5],
            ],
        ]);
    }
}
