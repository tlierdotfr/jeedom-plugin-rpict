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

    public static function createCmdFromDef($_oNId, $_oKey, $_oValue)
    {
        if (!isset($_oKey)) {
            log::add('rpict', 'error', 'Information manquante pour ajouter l\'équipement : ' . print_r($_oKey, true));
            return false;
        }
        if (!isset($_oNId)) {
            log::add('rpict', 'error', 'Information manquante pour ajouter l\'équipement : ' . print_r($_oNId, true));
            return false;
        }
        $rpict = rpict::byLogicalId($_oNId, 'rpict');
        if (!is_object($rpict)) {
            return false;
        }
        if ($rpict->getConfiguration('AutoCreateFromCompteur') == '1') {
            log::add('rpict', 'info', 'Création de la commande ' . $_oKey . ' sur le NodeID ' . $_oNId);
            $cmd = (new rpictCmd())
                    ->setName($_oKey)
                    ->setLogicalId($_oKey)
                    ->setType('info');
            $cmd->setEqLogic_id($rpict->id);
            $cmd->setConfiguration('info_rpict', $_oKey);
            $cmd->setSubType('numeric')
                    ->setDisplay('generic_type', 'GENERIC_INFO');
            $cmd->setIsHistorized(0)
                    ->setIsVisible(0);
            $cmd->save();
            $cmd->event($_oValue);
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
        log::add('rpict', 'info', 'Démarrage RPICT');
        $rpictPath            = realpath(dirname(__FILE__) . '/../../ressources');
        $modemSerieAddr       = config::byKey('port', 'rpict');
        $modemVitesse         = config::byKey('modem_vitesse', 'rpict');
        if ($modemSerieAddr == "serie") {
            $port = config::byKey('modem_serie_addr', 'rpict');
        } else {
            $port = jeedom::getUsbMapping(config::byKey('port', 'rpict'));
			if (!file_exists($port)) {
				log::add('rpict', 'error', 'Le port n\'existe pas');
				return false;
			}
			$cle_api = config::byKey('api');
			if ($cle_api == '') {
				log::add('rpict', 'error', 'Erreur de clé api, veuillez la vérifier.');
				return false;
			}
        }
		if ($modemVitesse == "") {
			$modemVitesse = '38400';
		}
        
        exec('sudo chmod 777 ' . $port . ' > /dev/null 2>&1'); // TODO : Vérifier dans futur release si tjs nécessaire

        log::add('rpict', 'info', '--------- Informations sur le master --------');
        log::add('rpict', 'info', 'Debug : ' . $debug);
        log::add('rpict', 'info', 'Port modem : ' . $port);
        log::add('rpict', 'info', 'Vitesse modem : ' . $modemVitesse);
        $debug     = ($debug) ? "1" : "0";
        log::add('rpict', 'info', '---------------------------------------------');

		$rpictPath = $rpictPath . '/rpict.py';
		$cmd          = 'nice -n 19 /usr/bin/python ' . $rpictPath . ' -d ' . $debug . ' -p ' . $port . ' -v ' . $modemVitesse . ' -c ' . config::byKey('api') . ' -r ' . realpath(dirname(__FILE__));
        
        log::add('rpict', 'info', 'Exécution du service : ' . $cmd);
        $result = exec('nohup ' . $cmd . ' >> ' . log::getPathToLog('rpict') . ' 2>&1 &');
        if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
            log::add('rpict', 'error', $result);
            return false;
        }
        sleep(2);
        if (!self::deamonRunning()) {
            sleep(10);
            if (!self::deamonRunning()) {
                log::add('rpict', 'error', 'Impossible de lancer le démon RPICT, vérifiez le port', 'unableStartDeamon');
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
		log::add('rpict', 'info', 'Vérification de l\'état du service : NOK ');
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
		$pidFile = '/tmp/rpict.pid';
        if (file_exists($pidFile)) {
            if (posix_getsid(trim(file_get_contents($pidFile)))) {
                $return['state'] = 'ok';
            } else {
                shell_exec('sudo rm -rf ' . $pidFile . ' 2>&1 > /dev/null;rm -rf ' . $pidFile . ' 2>&1 > /dev/null;');
            }
        }

        $return['launchable'] = 'ok';
        return $return;
    }

    /**
     * appelé par jeedom pour démarrer le deamon
     */
    public static function deamon_start($debug = false)
    {
        if (config::byKey('port', 'rpict') != "") {    // Si un port est sélectionné
            if (!self::deamonRunning()) {
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
        log::add('rpict', 'info', '[deamon_stop] Arret du service');
        $deamonInfo = self::deamon_info();
        if ($deamonInfo['state'] == 'ok') {
			$pidFile = '/tmp/rpict.pid';
			if (file_exists($pidFile)) {
				$pid  = intval(trim(file_get_contents($pidFile)));
				$kill = posix_kill($pid, 15);
				usleep(500);
				if ($kill) {
					return true;
				} else {
					system::kill($pid);
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
    }

    public function preSave()
    {
        $this->setCategory('energy', 1);
        $cmd = $this->getCmd('info', 'HEALTH');
        if (is_object($cmd)) {
            $cmd->remove();
            $cmd->save();
        }
    }

    public function postSave()
    {
        log::add('rpict', 'debug', '-------- Sauvegarde de l\'objet --------');
        foreach ($this->getCmd(null, null, true) as $cmd) {
            switch ($cmd->getConfiguration('info_rpict')) {
                default :
                    log::add('rpict', 'debug', '=> default');
                    if ($cmd->getDisplay('generic_type') == '') {
                        $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                    }
                    break;
            }
        }
        after_template:
        log::add('rpict', 'info', '==> Gestion des id des commandes');
        foreach ($this->getCmd('info') as $cmd) {
            log::add('rpict', 'debug', 'Commande : ' . $cmd->getConfiguration('info_rpict'));
            $cmd->setLogicalId($cmd->getConfiguration('info_rpict'));
            $cmd->save();
        }
        log::add('rpict', 'debug', '-------- Fin de la sauvegarde --------');

        if ($this->getConfiguration('AutoGenerateFields') == '1') {
            $this->CreateFromAbo();
        }

        $this->createOtherCmd();
    }

    public function preRemove()
    {
        log::add('rpict', 'debug', 'Suppression d\'un objet');
    }

    public function createOtherCmd()
    {
        $array = array("HEALTH");
        for ($ii = 0; $ii < 1; $ii++) {
            $cmd = $this->getCmd('info', $array[$ii]);
            if ($cmd === false) {
                $cmd = new rpictCmd();
                $cmd->setName($array[$ii]);
                $cmd->setEqLogic_id($this->id);
                $cmd->setLogicalId($array[$ii]);
                $cmd->setType('info');
                $cmd->setConfiguration('info_rpict', $array[$ii]);
                $cmd->setConfiguration('type', 'health');
                $cmd->setSubType('numeric');
                $cmd->setUnite('Wh');
                $cmd->setIsHistorized(0);
                $cmd->setEventOnly(1);
                $cmd->setIsVisible(0);
                $cmd->save();
            }
        }
    }

    public function CreateFromAbo($_abo)
    {
        $this->setConfiguration('AutoGenerateFields', '0');
        $this->save();
    }

    /*     * ******** MANAGEMENT ZONE ******* */

    public static function dependancy_info()
    {
        $return                  = array();
        $return['log']           = 'rpict_update';
        $return['progress_file'] = '/tmp/rpict_in_progress';
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
