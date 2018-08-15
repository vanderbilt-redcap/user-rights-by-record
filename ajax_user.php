<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 3/6/2018
 * Time: 2:50 PM
 */

$projectID = $_GET['pid'];
$assignType = $_GET['assign_type'];
$users = "";
if ($projectID != "") {
	if ($assignType == "individual") {
		$users = json_encode($module->getUserList($projectID));
	}
	elseif ($assignType == "multiple") {
		$userProjectID = $module->getRightsProjectID();
		$userProject = new \Project($userProjectID);
		$event_id = $module->getFirstEventId($userProjectID);
	}
}
echo $users;