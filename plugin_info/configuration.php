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
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}

$port = config::byKey('port', 'rpict');
$core_version = '1.1.1';
if (!file_exists(dirname(__FILE__) . '/info.json')) {
    log::add('rpict','warning','Pas de fichier info.json');
}
$data = json_decode(file_get_contents(dirname(__FILE__) . '/info.json'), true);
if (!is_array($data)) {
    log::add('rpict','warning','Impossible de décoder le fichier info.json');
}
try {
    $core_version = $data['pluginVersion'];
} catch (\Exception $e) {
    log::add('rpict','warning','Impossible de récupérer la version.');
}
?>

<form class="form-horizontal">
    <fieldset>
        <div class="form-group div_local">
            <label class="col-lg-4 control-label">Port du modem :</label>
            <div class="col-lg-4">
                <select id="select_port" class="configKey form-control" data-l1key="port">
                    <option value="">Aucun</option>
                    <?php
                    foreach (jeedom::getUsbMapping('', true) as $name => $value) {
                        echo '<option value="' . $name . '">' . $name . ' (' . $value . ')</option>';
                    }
                    echo '<option value="serie">Liaison Série</option>';
                    ?>
                </select>
                <input id="port_serie" class="configKey form-control" data-l1key="modem_serie_addr" style="margin-top:5px;display:none" placeholder="Renseigner le port série (ex : /dev/ttyAMA0)"/>
                <script>
                $( "#select_port" ).change(function() {
                    $( "#select_port option:selected" ).each(function() {
                        if($( this ).val() == "serie"){
                            $("#port_serie").show();
                        }
                        else{
                            $("#port_serie").hide();
                        }
                    });
                });
                </script>
            </div>
        </div>
        <div class="form-group div_local">
            <label class="col-lg-4 control-label">Vitesse : </label>
            <div class="col-lg-4">
                <!--<input id="port_serie" class="configKey form-control" data-l1key="modem_vitesse" style="margin-top:5px;" placeholder="1200"/>-->
                <select class="configKey form-control" id="port_serie" data-l1key="modem_vitesse">
                    <option value="">{{Par défaut}}</option>
                    <option value="1200">1200</option>
                    <option value="2400">2400</option>
                    <option value="4800">4800</option>
                    <option value="9600">9600</option>
                    <option value="19200">19200</option>
                    <option style="font-weight: bold;" value="38400">38400</option>
                    <option value="56000">56000</option>
                    <option value="115200">115200</option>
                </select>
            </div>
        </div>
    </fieldset>
</form>

<script>
        $('#btn_diagnostic').on('click',function(){
            $('#md_modal').dialog({title: "{{Diagnostique de résolution d'incident}}"});
            $('#md_modal').load('index.php?v=d&plugin=rpict&modal=diagnostic').dialog('open');
        });

        $('#bt_stopRpictDeamon').on('click', function () {
            $.ajax({// fonction permettant de faire de l'ajax
                type: "POST", // methode de transmission des données au fichier php
                url: "plugins/rpict/core/ajax/rpict.ajax.php", // url du fichier php
                data: {
                    action: "stopDeamon",
                },
                dataType: 'json',
                error: function (request, status, error) {
                    handleAjaxError(request, status, error);
                },
                success: function (data) { // si l'appel a bien fonctionné
                if (data.state != 'ok') {
                    $('#div_alert').showAlert({message: data.result, level: 'danger'});
                    return;
                }
                $('#div_alert').showAlert({message: 'Le daemon a été correctement arrêté : il se relancera automatiquement dans 1 minute', level: 'success'});
                $('#ul_plugin .li_plugin[data-plugin_id=rpict]').click();
            }
            });
        });

        $('#bt_restartRpictDeamon').on('click', function () {
			$.ajax({// fonction permettant de faire de l'ajax
				type: "POST", // methode de transmission des données au fichier php
				url: "plugins/rpict/core/ajax/rpict.ajax.php", // url du fichier php
				data: {
					action: "restartDeamon",
				},
				dataType: 'json',
				error: function (request, status, error) {
					handleAjaxError(request, status, error);
				},
				success: function (data) { // si l'appel a bien fonctionné
				if (data.state != 'ok') {
					$('#div_alert').showAlert({message: data.result, level: 'danger'});
					return;
				}
				$('#div_alert').showAlert({message: '{{Le démon a été correctement (re)démaré}}', level: 'success'});
				$('#ul_plugin .li_plugin[data-plugin_id=rpict]').click();
				}
			});
        });


</script>

<style type="text/css">
[type="checkbox"][class="configKey"]:not(:checked),
    [type="checkbox"][class="configKey"]:checked {
        display:none;
    }
    [type="checkbox"][class="configKey"]:not(:checked) + label,
    [type="checkbox"][class="configKey"]:checked + label {
        position: relative;
        padding-left: 75px;
        cursor: pointer;
    }
    [type="checkbox"][class="configKey"]:not(:checked) + label:before,
    [type="checkbox"][class="configKey"]:checked + label:before,
    [type="checkbox"][class="configKey"]:not(:checked) + label:after,
    [type="checkbox"][class="configKey"]:checked + label:after {
        content: '';
        position: absolute;
    }
    [type="checkbox"][class="configKey"]:not(:checked) + label:before,
    [type="checkbox"][class="configKey"]:checked + label:before {
        left:0; top: -3px;
        width: 65px; height: 30px;
        background: #DDDDDD;
        border-radius: 15px;
        -webkit-transition: background-color .2s;
        -moz-transition: background-color .2s;
        -ms-transition: background-color .2s;
        transition: background-color .2s;
    }
    [type="checkbox"][class="configKey"]:not(:checked) + label:after,
    [type="checkbox"][class="configKey"]:checked + label:after {
        width: 20px; height: 20px;
        -webkit-transition: all .2s;
        -moz-transition: all .2s;
        -ms-transition: all .2s;
        transition: all .2s;
        border-radius: 50%;
        background: #d9534f;
        top: 2px; left: 5px;
    }

    /* on checked */
    [type="checkbox"][class="configKey"]:checked + label:before {
        background:#DDDDDD;
    }
    [type="checkbox"][class="configKey"]:checked + label:after {
        background: #62c462;
        top: 2px; left: 40px;
    }

    [type="checkbox"][class="configKey"]:checked + label .ui,
    [type="checkbox"][class="configKey"]:not(:checked) + label .ui:before,
    [type="checkbox"][class="configKey"]:checked + label .ui:after {
        position: absolute;
        left: 6px;
        width: 65px;
        border-radius: 15px;
        font-size: 14px;
        font-weight: bold;
        line-height: 22px;
        -webkit-transition: all .2s;
        -moz-transition: all .2s;
        -ms-transition: all .2s;
        transition: all .2s;
    }
    [type="checkbox"][class="configKey"]:not(:checked) + label .ui:before {
        content: "no";
        left: 32px
    }
    [type="checkbox"][class="configKey"]:checked + label .ui:after {
        content: "yes";
        color: #62c462;
    }
    [type="checkbox"][class="configKey"]:focus + label:before {
        border: 1px dashed #777;
        -webkit-box-sizing: border-box;
        -moz-box-sizing: border-box;
        -ms-box-sizing: border-box;
        box-sizing: border-box;
        margin-top: -1px;
    }
</style>
