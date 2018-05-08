<?php
if (!isConnect()) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
$date = array(
	'start' => init('startDate', date('Y-m-d', strtotime('-1 month ' . date('Y-m-d')))),
	'end' => init('endDate', date('Y-m-d', strtotime('+1 days ' . date('Y-m-d')))),
);

if (init('object_id') == '') {
	$object = jeeObject::byId($_SESSION['user']->getOptions('defaultDashboardObject'));
} else {
	$object = jeeObject::byId(init('object_id'));
}
if (!is_object($object)) {
	$object = jeeObject::rootObject();
}
$allObject = jeeObject::buildTree();
if (count($object->getEqLogic(true, false, 'thermostat')) == 0) {
	foreach ($allObject as $object_li) {
		if (count($object_li->getEqLogic(true, false, 'thermostat')) > 0) {
			$object = $object_li;
			break;
		}
	}
}
if (is_object($object)) {
	$_GET['object_id'] = $object->getId();
}
sendVarToJs('object_id', init('object_id'));
?>

<div class="row row-overflow" id="div_thermostat">
    <div class="col-lg-2">
        <div class="bs-sidebar">
            <ul id="ul_object" class="nav nav-list bs-sidenav">
                <li class="nav-header">{{Liste objets}}</li>
                <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}" style="width: 100%"/></li>
                <?php

foreach ($allObject as $object_li) {
	if ($object_li->getIsVisible() == 1 && count($object_li->getEqLogic(true, false, 'thermostat')) > 0) {
		$margin = 5 * $object_li->parentNumber();
		if ($object_li->getId() == init('object_id')) {
			echo '<li class="cursor li_object active" ><a data-object_id="' . $object_li->getId() . '" href="index.php?v=d&p=panel&m=camera&object_id=' . $object_li->getId() . '" style="padding: 2px 0px;"><span style="position:relative;left:' . $margin . 'px;">' . $object_li->getHumanName(true) . '</span><span style="font-size : 0.65em;float:right;position:relative;top:7px;">' . $object_li->getHtmlSummary() . '</span></a></li>';
		} else {
			echo '<li class="cursor li_object" ><a data-object_id="' . $object_li->getId() . '" href="index.php?v=d&p=panel&m=camera&object_id=' . $object_li->getId() . '" style="padding: 2px 0px;"><span style="position:relative;left:' . $margin . 'px;">' . $object_li->getHumanName(true) . '</span><span style="font-size : 0.65em;float:right;position:relative;top:7px;">' . $object_li->getHtmlSummary() . '</span></a></li>';
		}
	}
}
?>
         </ul>
     </div>
 </div>
 <div class="col-lg-10">
    <div id="div_object">
        <legend style="height: 35px;">
            <span class="objectName"></span>
            <span class="pull-right">
                {{Du}} <input class="form-control input-sm in_datepicker" id='in_startDate' style="display : inline-block; width: 150px;" value='<?php echo $date['start'] ?>'/> {{au}}
                <input class="form-control input-sm in_datepicker" id='in_endDate' style="display : inline-block; width: 150px;" value='<?php echo $date['end'] ?>'/>
                <a class="btn btn-success btn-sm tooltips" id='bt_validChangeDate' title="{{Attention une trop grande plage de date peut mettre très longtemps a etre calculer ou même ne pas s'afficher}}">{{Ok}}</a>
            </span>
        </legend>
    </div>
    <div class="row">
        <div class="col-lg-4" id="div_displayEquipement"></div>
        <div class="col-lg-8" id="div_chartRuntime"></div>
    </div>
    <div id="div_charts"></div>
</div>
</div>
</div>
<?php include_file('desktop', 'panel', 'js', 'thermostat');?>