<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';


function rpict_install() {
    $core_version = '1.1.1';
    if (!file_exists(dirname(__FILE__) . '/info.json')) {
        log::add('rpict','warning','Pas de fichier info.json');
        goto step2;
    }
    $data = json_decode(file_get_contents(dirname(__FILE__) . '/info.json'), true);
    if (!is_array($data)) {
        log::add('rpict','warning','Impossible de décoder le fichier info.json');
        goto step2;
    }
    try {
        $core_version = $data['pluginVersion'];
    } catch (\Exception $e) {

    }
    step2:
    if (rpict::deamonRunning()) {
        rpict::deamon_stop();
    }

    message::removeAll('Rpict');
    message::add('Rpict', 'Installation du plugin Rpict terminée, vous êtes en version ' . $core_version . '.', null, null);
    //cache::set('rpict::current_core','2.610', 0);
}

function rpict_update() {
    log::add('rpict','debug','rpict_update');
    $core_version = '1.1.1';
    if (!file_exists(dirname(__FILE__) . '/info.json')) {
        log::add('rpict','warning','Pas de fichier info.json');
        goto step2;
    }
    $data = json_decode(file_get_contents(dirname(__FILE__) . '/info.json'), true);
    if (!is_array($data)) {
        log::add('rpict','warning','Impossible de décoder le fichier info.json');
        goto step2;
    }
    try {
        $core_version = $data['pluginVersion'];
    } catch (\Exception $e) {
        log::add('rpict','warning','Pas de version de plugin');
    }
    step2:
    if (rpict::deamonRunning()) {
        rpict::deamon_stop();
    }
    message::add('Rpict', 'Mise à jour du plugin Rpict en cours...', null, null);
    log::add('rpict','info','*****************************************************');
    log::add('rpict','info','*********** Mise à jour du plugin rpict **********');
    log::add('rpict','info','*****************************************************');
    log::add('rpict','info','**        Core version    : '. $core_version. '                  **');
    log::add('rpict','info','*****************************************************');

    message::removeAll('Rpict');
    message::add('Rpict', 'Mise à jour du plugin Rpict terminée, vous êtes en version ' . $core_version . '.', null, null);
}

function rpict_remove() {
    if (rpict::deamonRunning()) {
        rpict::deamon_stop();
    }
    message::removeAll('Rpict');
    message::add('Rpict', 'Désinstallation du plugin Rpict terminée, vous pouvez de nouveau relever les index à la main ;)', null, null);
}
