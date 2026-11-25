<?php

/**
 * Monedas - guarda la configuración de moneda de una entidad.
 *
 * @license GPL-3.0-or-later
 */

use GlpiPlugin\Monedas\Config;

Session::checkRight('config', UPDATE);

$entities_id = (int) ($_POST['entities_id'] ?? -1);
if ($entities_id < 0 || !Session::haveAccessToEntity($entities_id)) {
    // Html::displayRightError() ya no existe en GLPI 12.
    if (class_exists(\Glpi\Exception\Http\AccessDeniedHttpException::class)) {
        throw new \Glpi\Exception\Http\AccessDeniedHttpException();
    }
    Html::displayRightError();
}

if (isset($_POST['reset'])) {
    if (Config::resetForEntity($entities_id)) {
        Session::addMessageAfterRedirect(__('La entidad vuelve a heredar la configuración de moneda.', 'monedas'));
    } else {
        Session::addMessageAfterRedirect(__('La entidad raíz no puede heredar.', 'monedas'), false, ERROR);
    }
    Html::back();
}

if (isset($_POST['update'])) {
    [$data, $errors] = Config::sanitizeInput($_POST);

    if ($errors !== []) {
        foreach ($errors as $error) {
            Session::addMessageAfterRedirect($error, false, ERROR);
        }
    } elseif (Config::saveForEntity($entities_id, $data)) {
        Session::addMessageAfterRedirect(__('Configuración de moneda guardada.', 'monedas'));
    } else {
        Session::addMessageAfterRedirect(__('No se pudo guardar la configuración.', 'monedas'), false, ERROR);
    }
}

Html::back();
