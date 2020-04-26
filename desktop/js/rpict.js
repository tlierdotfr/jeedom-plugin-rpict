
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

$('#bt_stopRpictDaemon').on('click', function() {
    stopRpictDeamon();
});

function stopRpictDeamon() {
    $.ajax({// fonction permettant de faire de l'ajax
        type: "POST", // methode de transmission des données au fichier php
        url: "plugins/rpict/core/ajax/rpict.ajax.php", // url du fichier php
        data: {
            action: "stopDeamon",
        },
        dataType: 'json',
        error: function(request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function(data) { // si l'appel a bien fonctionné
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            $('#div_alert').showAlert({message: 'Le démon a été correctement arrêté : il se relancera automatiquement dans 1 minute', level: 'success'});
        }
    });
}

$('#create_data_rpict').on('click', function() {
    document.getElementById("checkbox-autocreate").checked = true;
    $('.eqLogicAction[data-action=save]').click();
});

$('#bt_info_daemon').on('click', function() {
    $('#md_modal').dialog({title: "{{Informations du modem}}"});
    $('#md_modal').load('index.php?v=d&plugin=rpict&modal=info_daemon&plugin_id=rpict_deamon&slave_id=0').dialog('open');
});

$('#bt_config').on('click', function() {
    $('#md_modal').dialog({title: "{{Configuration}}"});
    $('#md_modal').load('index.php?v=d&p=plugin&ajax=1&id=rfxcom').dialog('open');
});

$('#bt_rpictHealth').on('click', function() {
    $('#md_modal').dialog({title: "{{Santé RPICT}}"});
    $('#md_modal').load('index.php?v=d&plugin=rpict&modal=health').dialog('open');
});


$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }
    init(_cmd.id);
    var selRequestType = '';
    var type_of_data = init(_cmd.configuration['type']);
    //alert(type_of_data);
    selRequestType = '<select style="width : 220px;" class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="info_rpict">';
    for (var i = 1; i <= 15; i++) {
        selRequestType += '<option value="ch'+i+'">Channel '+i+'</option>';
    }
    selRequestType += '</select>';

    if(init(_cmd.configuration['type']) == 'panel'){
        var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '" style="display:none">';
    }else if(init(_cmd.configuration['type']) == 'health'){
        var tr = '';
    }
    else{
        var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    }
    if(init(_cmd.configuration['type']) != 'health'){
        tr += '<td>';
        tr += '<span class="cmdAttr expertModeVisible" data-l1key="id"></span>';
        tr += '</td>';
        tr += '<td>';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 140px;" placeholder="{{Nom}}"></td>';
        tr += '</td>';
        tr += '<td>';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="type" value="info" disabled>';
        tr += '<select style="width : 120px;margin-top : 5px;" class="cmdAttr form-control input-sm tooltips" title="{{Numérique pour les indexs et nombres, Autre pour les chaines de caractères (Tranche tarifaire par exemple.}}" data-l1key="subType"><option value="numeric">Numérique</option><option value="binary">Binaire</option><option value="string">Autre</option></select>';
        tr += '</td>';
        tr += '<td>';
        tr +=  selRequestType;
        tr += '</td>';
        tr += '<td>';
        tr += '<span><input class="cmdAttr" style="display:none" data-l1key="configuration" data-l2key="type" value="' + init(_cmd.configuration['type']) +'"/></span>';
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';

        if(init(_cmd.configuration['info_rpict']) == 'TENDANCE_DAY'){
            tr += '<span><input type="checkbox" class="cmdAttr tooltips" title="Spécifie si le calcul de la tendance se fait sur la journée entière ou sur la plage jusqu\'à l\'heure actuelle." data-l1key="configuration" data-l2key="type_calcul_tendance"/> {{Journée entière}}<br/></span>';
        }

        tr += '<input class="cmdAttr form-control tooltips input-sm" data-l1key="unite" style="width : 100px;" placeholder="Unité" title="{{Unité de la donnée (Wh, A, kWh...) pour plus d\'informations aller voir le wiki}}">';

        tr += '<input style="width : 150px;" class="tooltips cmdAttr form-control expertModeVisible input-sm" data-l1key="cache" data-l2key="lifetime" placeholder="{{Lifetime cache}}">';
        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Borne minimum de la valeur}}" style="width : 40%;display : inline-block;"> ';
        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Borne maximum de la valeur}}" style="width : 40%;display : inline-block;">';

        if(init(_cmd.configuration['info_rpict']) == 'ADPS' || init(_cmd.configuration['info_rpict']) == 'ADIR1' || init(_cmd.configuration['info_rpict']) == 'ADIR2' || init(_cmd.configuration['info_rpict']) == 'ADIR3'){
            //tr += '<input class="cmdAttr form-control input-sm" data-l1key="logicalId" value="0">';
            tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="returnStateValue" placeholder="{{Valeur retour d\'état}}" style="margin-top : 5px;">';
            tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="returnStateTime" placeholder="{{Durée avant retour d\'état (min)}}" style="margin-top : 5px;">';
        }

        tr += '</td>';
        tr += '<td>';
        if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
        tr += '<a class="btn btn-default btn-xs cmdAction tooltips" title="Attention, ne sert qu\'a afficher la dernière valeur reçu." data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
        }
        tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
        tr += '</tr>';

        if (isset(_cmd.configuration.info_rpict)) {
        //$('#table_cmd tbody tr:last .cmdAttr[data-l1key=configuration][data-l2key=info_rpict]').value(init(_cmd.configuration.info_rpict));
        //$('#table_cmd tbody tr:last .cmdAttr[data-l1key=configuration][data-l2key=info_rpict]').trigger('change');
        }

        $('#table_cmd tbody').append(tr);
        $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
        var tr = $('#table_cmd tbody tr:last');
        if(init(_cmd.unite) == ''){
            if(init(_cmd.configuration['info_rpict']) == 'ADPS'){
                tr.find('.cmdAttr[data-l1key=unite]').append("A");
                tr.setValues(_cmd, '.cmdAttr');
            }
        }
        else{

        }
    }
}

$('#addDataToTable').on('click', function() {
    var _cmd = {type: 'info'};
    _cmd.configuration = {'type':'data'};
    addCmdToTable(_cmd);
});
