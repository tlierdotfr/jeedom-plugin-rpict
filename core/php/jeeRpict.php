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
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";
set_time_limit(15);

if (!jeedom::apiAccess(init('apikey'), 'rpict')) {
    echo __('Clef API non valide, vous n\'êtes pas autorisé à effectuer cette action (rpict)', __FILE__);
    http_response_code(403);
    die();
}

if (init('test') != '') {
	echo 'OK';
	die();
}

$result = json_decode(file_get_contents("php://input"), true);
if (!is_array($result)) {
	die();
}

/*
if (php_sapi_name() == 'cli') {
    foreach ($argv as $arg) {
        $e=explode("=",$arg);
        if(count($e)==2)
            $myDatas[$e[0]]=$e[1];
        else    
            $myDatas[$e[0]]=0;
    }
    $nid = $myDatas['nid'];
} else {
   $message = filter_input(INPUT_GET, 'message', FILTER_SANITIZE_STRING);
   $nid = filter_input(INPUT_GET, 'nid', FILTER_SANITIZE_STRING);
}
$sentDatas = "";


if($message != ''){
    $text = substr($message, 0, -2);
    $messages = preg_split("#(&|[\*]{2})#", $text);
    foreach ($messages as $key => $value){
        log::add('rpict', 'event', 'Log Daemon : ' . $value);
        $text = $text . date("Y-m-d H:i:s") . " " .  $value . "</br>";
    }
    $cache = cache::byKey('rpict::console', false);
    cache::set('rpict::console', $cache->getValue("") . $text, 1440);
    die();
}

if ($nid == ''){
    log::add('rpict', 'info', 'Pas de NodeID dans la trame');
    echo "Pas de NodeID dans la trame\n";
    die();
}

$rpict = rpict::byLogicalId($nid, 'rpict');


if (!is_object($rpict)) {
    $rpict = rpict::createFromDef($nid);
    if (!is_object($rpict)) {
        log::add('rpict', 'info', 'Aucun équipement trouvé pour la cart RPICT dont le NodeID est ' . $nid);
        echo "Aucun équipement trouvé pour la cart RPICT dont le NodeID est " . $nid . "\n";
        die();
    }
}

//$myDatas = filter_input_array(INPUT_GET, $args);

$healthCmd = $rpict->getCmd('info','health');
$healthEnable = false;
if (is_object($healthCmd)) {
    $healthEnable = true;
}

foreach ($myDatas as $key => $value){
   //echo $key . " => " . $value . "\n"; 
   if ($value != '') {
        $sentDatas = $sentDatas . $key . '=' . $value . ' / ';
	$cmd = $rpict->getCmd('info',$key);

	if ($cmd === false) {
            if($key != 'api' && $key != 'nid'){
                rpict::createCmdFromDef($rpict->getLogicalId(), $key, $value);
                if($healthEnable) {
                    $healthCmd->setConfiguration($key, array("name" => $key, "value" => $value, "update_time" => date("Y-m-d H:i:s")));
                    $healthCmd->save();
                }
            }
        }
	else
	{
            //echo "Update command (".$key.") value to :".$value."\n";
            $cmd->event($value);
            if($healthEnable) {
                $healthCmd->setConfiguration($key, array("name" => $key, "value" => $value, "update_time" => date("Y-m-d H:i:s")));
                $healthCmd->save();
            }
        }
    }
}
log::add('rpict', 'debug', 'Reception de : ' . $sentDatas);
*/


$var_to_log = '';

if (isset($result['device'])) {
    foreach ($result['device'] as $key => $data) {
            log::add('rpict','debug','This is a message from rpict program ' . $key);
    		$eqlogic = rpict::byLogicalId($data['device'], 'rpict');
    		if (is_object($eqlogic)) {
                $healthCmd = $eqlogic->getCmd('info','health');
                $healthEnable = false;
                if (is_object($healthCmd)) {
                    $healthEnable = true;
                }
                $flattenResults = array_flatten($data);
                foreach ($flattenResults as $key => $value) {
                    $cmd = $eqlogic->getCmd('info',$key);
                    if ($cmd === false) {
                        if($key != 'device'){
                            rpict::createCmdFromDef($eqlogic->getLogicalId(), $key, $value);
                            if($healthEnable) {
                                $healthCmd->setConfiguration($key, array("name" => $key, "value" => $value, "update_time" => date("Y-m-d H:i:s")));
                                $healthCmd->save();
                            }
                        }
                    }
                    else{
                        $cmd->event($value);
                        if($healthEnable) {
                            $healthCmd->setConfiguration($key, array("name" => $key, "value" => $value, "update_time" => date("Y-m-d H:i:s")));
                            $healthCmd->save();
                        }
                    }
                }
            }
            else {
                $rpict = ($data['device'] != '') ? rpict::createFromDef($data['device']) : rpict::createFromDef($data['device']);
                if (!is_object($rpict)) {
                    log::add('rpict', 'info', 'Aucun équipement trouvé pour la carte RPICT id n°' . $data['device']);
                    die();
                }
            }
            log::add('rpict','debug',$var_to_log);
        }
    }

function array_flatten($array) {
    global $var_to_log;
    $return = array();
    foreach ($array as $key => $value) {
        $var_to_log = $var_to_log . $key . '=' . $value . '|';
        if (is_array($value))
            $return = array_merge($return, array_flatten($value));
        else
            $return[$key] = $value;
    }
    return $return;
}
