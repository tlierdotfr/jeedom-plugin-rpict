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

require_once __DIR__ . '/../../../../core/php/core.inc.php';

class rpict extends eqLogic
{
    public static function getRpictInfo($_url)
    {
        $return = self::deamon_info();
        if ($return['state'] != 'ok') {
            return "";
        }
    }


    public static function changeLogLive($level)
    {
        sleep(1); // attend que le level ait eu le temps de s'écrire dans la bdd
        $value['cmd'] = 'changelog';
        $value['level'] = log::convertLogLevel(log::getLogLevel('rpict'));
        $socketport = config::byKey('socketport', __CLASS__, '55062');
        $value['apikey'] = jeedom::getApiKey(__CLASS__);
        self::sendToDaemon($value, 'serial', $socketport);
    }

    public static function sendToDaemon($params, $mode, $socketport)
    { // le mode peut être serial, mqtt ou prod
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] != 'ok') {
            throw new Exception("Le démon " . $mode . " n'est pas démarré");
        }
        $params['apikey'] = jeedom::getApiKey('rpict');
        $payLoad = json_encode($params);
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        socket_connect($socket, config::byKey('sockethost', 'rpict', '127.0.0.1'), $socketport);
        socket_write($socket, $payLoad, strlen($payLoad));
        socket_close($socket);
        return true;
    }

    /**
     * Test si la version est béta
     * @param bool $text
     * @return $isBeta
     */
    public static function isBeta($text = false)
    {
        $plugin = plugin::byId('rpict');
        $update = $plugin->getUpdate();
        $isBeta = false;
        if (is_object($update)) {
            $version = $update->getConfiguration('version');
            $isBeta = ($version && $version != 'stable');
        }

        if ($text) {
            return $isBeta ? 'beta' : 'stable';
        }
        return $isBeta;
    }

    /**
     * Creation objet sur reception de trame
     * @param string $adco
     * @return eqLogic
     */
    public static function createFromDef(string $nid)
    {
        $rpict = rpict::byLogicalId($nid, 'rpict');
        if (!is_object($rpict)) {
            $eqLogic = (new rpict())
                ->setName($nid);
        }
        $eqLogic->setLogicalId($nid)
            ->setEqType_name('rpict')
            ->setIsEnable(1)
            ->setIsVisible(1);
        $eqLogic->save();
        return $eqLogic;
    }

    /**
     * Creation commande sur reception de trame
     * @param $oADCO identifiant compteur
     * @param $oKey etiquette
     * @param $oValue valeur
     * @return Commande
     */
    public static function createCmdFromDef($oNId, $oKey, $oValue)
    {
        if (!isset($oKey) || !isset($oNId)) {
            log::add('rpict', 'error', '[RPICT]-----Information manquante pour ajouter l\'équipement : ' . print_r($oKey, true) . ' ' . print_r($oNId, true));
            return false;
        }
        $rpict = rpict::byLogicalId($oNId, 'rpict');
        if (!is_object($rpict)) {
            return false;
        }
        if ($rpict->getConfiguration('AutoCreateFromCompteur') == '1') {
            log::add('rpict', 'info', 'Création de la commande ' . $oKey . ' sur le NodeID ' . $oNId);
            $cmd = (new rpictCmd())
                ->setName($oKey)
                ->setLogicalId($oKey)
                ->setType('info');

            $cmd->setSubType('numeric')
                ->setDisplay('generic_type', 'GENERIC_INFO');

            $cmd->setEqLogic_id($rpict->id);
            $cmd->setConfiguration('info_rpict', $oKey);
            $cmd->setIsHistorized(0)->setIsVisible(0);
            $cmd->save();
            $cmd->event($oValue);
            return $cmd;
        }
    }

    /**
     * 
     * @param type $debug
     * @return boolean
     */
    public static function runDeamon($debug = false)
    {
        $rpictPath = realpath(dirname(__FILE__) . '/../../ressources');
        log::add('rpict', 'info', 'Démarrage daemon ');

        log::add('rpict', 'info', 'Démarrage compteur ');
        $modemVitesse         = config::byKey('modem_vitesse', 'rpict');
        $socketPort           = config::byKey('socketport', 'rpict', '55062');
        if (config::byKey('port', 'rpict') == "serie") {
            $port = config::byKey('modem_serie_addr', 'rpict');
        } else {
            $port = jeedom::getUsbMapping(config::byKey('port', 'rpict'));
            if (!file_exists($port)) {
                log::add('rpict', 'error', 'Le port n\'existe pas');
                return false;
            }
        }
        if ($modemVitesse == "") {
            $modemVitesse = '38400';
        }

        exec('sudo chmod 777 ' . $port . ' > /dev/null 2>&1');

        log::add('rpict', 'info', '---------- Informations de lancement ---------');
        log::add('rpict', 'info', 'Port modem : ' . $port);
        log::add('rpict', 'info', 'Socket : ' . $socketPort);
        log::add('rpict', 'info', '---------------------------------------------');

        $cmd          = 'nice -n 19 ' . $rpictPath . '/venv/bin/python3 ' . $rpictPath . '/rpict.py';
        //$cmd          = 'nice -n 19 /usr/bin/python3 ' . $rpictPath . '/rpict.py';
        $cmd         .= ' --port ' . $port;
        $cmd         .= ' --vitesse ' . $modemVitesse;
        $cmd         .= ' --apikey ' . jeedom::getApiKey('rpict');
        $cmd         .= ' --socketport ' . $socketPort;
        $cmd         .= ' --cycle ' . config::byKey('cycle', 'rpict', '0.3');
        $cmd         .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/rpict/core/php/jeeRpict.php';
        $cmd         .= ' --cyclesommeil ' . config::byKey('cycle_sommeil', 'rpict', '0.5');
        $cmd         .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel(__CLASS__));


        log::add('rpict', 'info', 'Exécution du service : ' . $cmd);
        $result = exec('nohup ' . $cmd . ' >> ' . log::getPathToLog('rpict_deamon') . ' 2>&1 &');
        if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
            log::add('rpict', 'error', '[RPICT]-----' . $result);
            return false;
        }
        sleep(2);
        if (!self::deamonRunning()) {
            sleep(10);
            if (!self::deamonRunning()) {
                log::add('rpict', 'error', '[RPICT] Impossible de lancer le démon RPICT, vérifiez le port', 'unableStartDeamon');
                return false;
            }
        }
        message::removeAll('rpict', 'unableStartDeamon');
        log::add('rpict', 'info', 'Service OK');
        log::add('rpict', 'info', '---------------------------------------------');
    }


    /**
     * 
     * @return boolean
     */
    public static function deamonRunning()
    {
        $result = exec("ps aux | grep rpict.py | grep -v grep | awk '{print $2}'");
        if ($result != "") {
            return true;
        }
        log::add('rpict', 'info', '[deamonRunning] Vérification de l\'état du service : NOK ');
        return false;
    }

    /**
     * 
     * @return array
     */
    public static function deamon_info()
    {
        $return               = array();
        $return['log']        = 'rpict';
        $return['state']      = 'nok';
        $returnmodem          = 'nok';
        $pidFile = jeedom::getTmpFolder('rpict') . '/rpict.pid';
        if (file_exists($pidFile)) {
            if (posix_getsid(trim(file_get_contents($pidFile)))) {
                log::add('rpict', 'debug', '[RPICT_deamon_infoserial] démon port modem 1 => ok');
                $returnmodem = 'ok';
            } else {
                log::add('rpict', 'error', "[RPICT_deamon_infoserial] le deamon port modem 1 s'est éteint");
                $returnmodem = 'nok';
                shell_exec('sudo rm -rf ' . $pidFile . ' 2>&1 > /dev/null;rm -rf ' . $pidFile . ' 2>&1 > /dev/null;');
            }
        }

        $return['launchable'] = 'ok';
        if ($returnmodem != 'nok') {
            $return['state'] = 'ok';
            $return['deamon_modem'] = $returnmodem;
        } else {
            $return['state'] = 'nok';
            $return['deamon_modem'] = $returnmodem;
        }
        log::add('rpict', 'debug', '[RPICT_deamon_modem] état : ' . $returnmodem);
        log::add('rpict', 'debug', '[RPICT_deamon] état global => retour: ' . $return['state']);
        return $return;
    }

    /**
     * appelé par jeedom pour démarrer le deamon
     */
    public static function deamon_start($debug = false)
    {
        log::add('rpict', 'info', '[deamon_start_modem] Démarrage du service');
        if (config::byKey('port', 'rpict') != "") {    // Si un port est sélectionné
            if (!self::deamonRunning()) {
                log::add('rpict', 'info', 'Lancement carte');
                self::runDeamon($debug);
            }
            message::removeAll('rpict', 'noRpictPort');
        } else {
            log::add('rpict', 'info', 'Pas d\'informations sur le port série');
        }
    }

    /**
     * appelé par jeedom pour arrêter le deamon
     */
    public static function deamon_stop()
    {
        $deamonKill = false;
        $deamonInfo = self::deamon_info();
        if ($deamonInfo['deamon_modem'] == 'ok') {
            $pidFile = jeedom::getTmpFolder('rpict') . '/rpict.pid';
            if (file_exists($pidFile)) {
                $pid  = intval(trim(file_get_contents($pidFile)));
                $kill = posix_kill($pid, 15);
                usleep(500);
                if ($kill) {
                    $deamonKill = true;
                    log::add('rpict', 'info', "[deamon_stop_serial] arrêt du service OK");
                } else {
                    system::kill($pid);
                }
            }
        }
        system::kill('rpict.py');
        $port = config::byKey('port', 'rpict');
        if ($port != "serie") {
            $port = jeedom::getUsbMapping(config::byKey('port', 'rpict'));
            system::fuserk(jeedom::getUsbMapping($port));
            sleep(1);
        }
    }

    public function preSave()
    {
        log::add('rpict', 'debug', '-------- PRESAVE --------');
        $this->setCategory('energy', 1);
        $cmd = $this->getCmd('info', 'HEALTH');
        if (is_object($cmd)) {
            $cmd->remove();
        }
    }

    public function postSave()
    {
        log::add('rpict', 'debug', '-------- Sauvegarde de l\'objet --------');
        foreach ($this->getCmd(null, null, true) as $cmd) {
            switch ($cmd->getConfiguration('info_rpict')) {
                default:
                    log::add('rpict', 'debug', '=> default');
                    if ($cmd->getDisplay('generic_type') == '') {
                        $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                    }
                    break;
            }
        }
        log::add('rpict', 'info', '==> Gestion des id des commandes');
        foreach ($this->getCmd('info') as $cmd) {
            log::add('rpict', 'debug', 'Commande : ' . $cmd->getConfiguration('info_rpict'));
            $cmd->setLogicalId($cmd->getConfiguration('info_rpict'));
            $cmd->save();
        }
        log::add('rpict', 'debug', '-------- Fin de la sauvegarde --------');

        if ($this->getConfiguration('AutoGenerateFields') == '1') {
            $this->AutoCreate();
        }

        $this->createOtherCmd();
    }

    public function preRemove()
    {
        log::add('rpict', 'debug', 'Suppression d\'un objet');
    }

    public function createOtherCmd()
    {
        log::add('rpict', 'debug', '-------- Santé --------');
        $array = array("HEALTH");
        foreach ($array as $value) {
            $cmd = $this->getCmd('info', $value);
            if (!is_object($cmd)) {
                log::add('rpict', 'debug', 'Santé => ' . $value);
                $cmd = new rpictCmd();
                $cmd->setName($value);
                $cmd->setEqLogic_id($this->id);
                $cmd->setLogicalId($value);
                $cmd->setType('info');
                $cmd->setConfiguration('info_rpict', $value);
                $cmd->setConfiguration('type', 'health');
                $cmd->setSubType('numeric');
                $cmd->setUnite('Wh');
                $cmd->setIsHistorized(0);
                //$cmd->setEventOnly(1);
                $cmd->setIsVisible(0);
                $cmd->save();
            }
        }
    }

    public function AutoCreate()
    {
        $this->setConfiguration('AutoGenerateFields', '0');
        $this->save();
    }

    /*     * ******** MANAGEMENT ZONE ******* */

    public static function dependancy_info()
    {
        $return                  = array();
        $return['log']           = 'rpict_update';
        $return['progress_file'] = '/tmp/jeedom/rpict/dependance';
        $return['state']         = (self::installationOk()) ? 'ok' : 'nok';
        return $return;
    }

    public static function installationOk()
    {
        try {
            $dependances_version = config::byKey('dependancy_version', 'rpict', 0);
            if (intval($dependances_version) >= 1.0) {
                return true;
            } else {
                config::save('dependancy_version', 1.0, 'rpict');
                return false;
            }
        } catch (\Exception $e) {
            return true;
        }
    }

    public static function dependancy_install()
    {
        log::remove(__CLASS__ . '_update');
        return array('script' => __DIR__ . '/../../ressources/install_#stype#.sh ' . jeedom::getTmpFolder('rpict') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
    }
}

class rpictCmd extends cmd
{

    public function execute($_options = null)
    {
    }
}
