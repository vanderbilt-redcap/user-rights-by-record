<style type='text/css'>
    .picklist {
        width:90%;
        text-overflow: ellipsis;
        overflow: hidden;
        margin-top:5px;
        margin-bottom:5px;
    }
</style>


<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 1/16/2018
 * Time: 5:38 PM
 */
require_once("base.php");
$projectID = $_GET['pid'];
if ($projectID != "") {
	//global $redcap_version;
    global $Proj;

	//require_once(dirname(dirname(dirname(__FILE__)))."/redcap_v$redcap_version/Config/init_project.php");
	require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
	/*$HtmlPage = new HtmlPage();
	$HtmlPage->PrintHeaderExt();*/
	$userList = getUserList($projectID);

	$dagList = getDAGList($projectID);
	$roleList = getRoleList($projectID);
	$recordList = getRecordList($projectID,$Proj->table_pk);

	$userProjectID = $module->getRightsProjectID();
	$userProject = new \Project($userProjectID);
	$event_id = $module->getFirstEventId($userProjectID);

	$groupData = \REDCap::getData($userProjectID, 'array', "", array(), $event_id, array(), false, false, false, "([" . $module->getProjectSetting('project-field') . "] = '$projectID')");

	$groupList = array();
	$usersByGroup = array();
	foreach ($groupData as $group) {
	    if ($group[$event_id][$module->getProjectSetting('group-field')] != "" && !in_array($group[$event_id][$module->getProjectSetting('group-field')],$groupList)) {
	        $groupList[$group[$event_id][$module->getProjectSetting('group-field')]] = $group[$event_id][$module->getProjectSetting('group-field')];
        }
        $usersByGroup[$group[$event_id][$module->getProjectSetting('group-field')]][] = $group[$event_id][$module->getProjectSetting('user-field')];
    }

	//$rightsModule = new \Vanderbilt\UserRightsByRecordExternalModule\UserRightsByRecordExternalModule($projectID);
    $postSelectGroup = htmlentities($_POST['select_group'], ENT_QUOTES);
    $postNewGroup = htmlentities($_POST['new_group'], ENT_QUOTES);
	
	echo "<table class='table table-bordered'><tr><th>Select a User to Apply Rights</th></tr>
    <form action='".$module->getUrl('configure.php')."' method='POST'>
        <tr><td>
            <input type='radio' id='user_radio' name='assign_type' ".($_POST['assign_type'] == "individual" || $_POST['assign_type'] == "" ? "checked": "")." value='individual' onclick='showHide(\"user_div\",this,\"individual\",\"group_div\");'>Individual User
            <input type='radio' id='group_radio' name='assign_type' ".($_POST['assign_type'] == "multiple" || $postSelectGroup != "" ? "checked" : "")." value='multiple' onclick='showHide(\"group_div\",this,\"multiple\",\"user_div\");'>Multiple Users
        </td></tr><tr><td>
        <div id='user_div' ".($_POST['assign_type'] != "individual" && $_POST['assign_type'] != "" ? "style='display:none;'": "").">
        <select name='select_user'>";
	foreach ($userList as $userName => $realName) {
		echo "<option value='$userName' ".($_POST['select_user'] == $userName ? "selected" : "").">$realName ($userName)</option>";
	}
	echo "</select>
		    <input type='submit' value='Load User' name='load_user'/>
		</div>
		<div id='group_div' style='display:none;'>
		    <select name='select_group' onchange='showHide(\"new_group\",this,\"new\");'>
		        <option></option>
		        <option value='new'>New Group</option>";
	        $groupCount = 0;
	        foreach ($groupList as $groupName) {
	            echo "<option value='$groupName' ".(db_real_escape_string($postSelectGroup) == $groupName || db_real_escape_string($postNewGroup) == $groupName ? "selected" : "").">$groupName</option>";
	            $groupCount++;
            }
            if ($postNewGroup != "") {
				echo "<option value='".db_real_escape_string($postNewGroup)."' selected>".db_real_escape_string($postNewGroup)."</option>";
            }
            echo "</select>
            <input type='text' id='new_group' name='new_group' style='display:none;'/>
		    <input type='submit' value='Load Custom Group' name='load_group'/>
        </div>
	</td></tr></form></table>";

	if (isset($_POST['update_rights']) && $_POST['update_rights'] != "") {
	    $postArray = array();

	    if (is_array($_POST['select_user'])) {
	        $userIDs = $_POST['select_user'];
			echo "<script>
            $(document).ready(function() {
            	$('#group_radio').trigger('onclick');
            });";
			echo "</script>";
        }
        else {
			$userIDs = array($_POST['select_user']);
		}

	    foreach ($_POST["add_new_right_role"] as $index => $value) {
	        $postArray[$index]['role'] = $value;
        }
        /*foreach ($_POST["add_new_select_dag"] as $index => $value) {
	        $postArray[$index]['dag'] = $value;
        }*/

        //$dagAssigns = implode(",", $_POST['dagid']);
        $dagAssigns = array();
        foreach ($_POST['dagid'] as $dagID) {
            $dagName = $Proj->getUniqueGroupNames($dagID);
            if ($dagName != "") {
                $dagAssigns[$dagID] = $Proj->getUniqueGroupNames($dagID);
            }
        }

        foreach ($_POST['recordid'] as $index => $value) {
            foreach ($value as $key=>$subValue) {
				$postArray[$index]['record'][$subValue] = $subValue;
			}
        }

		$groupAssign = "";
		if ($postSelectGroup != "" && $postSelectGroup != "new") {
			$groupAssign = db_real_escape_string($postSelectGroup);
		}
		elseif ($postNewGroup != "") {
			$groupAssign = db_real_escape_string($postNewGroup);
		}

	    $json_encoder = json_encode($postArray);

        foreach ($userIDs as $userID) {
			$data = \REDCap::getData($userProjectID, 'array', "", array(), $event_id, array(), false, false, false, "([" . $module->getProjectSetting('project-field') . "] = '$projectID' and [" . $module->getProjectSetting('user-field') . "] = '" . $userID . "')");
			$recordID = "";
			if (empty($data)) {
				$recordID = $module->getAutoId($userProjectID);
			} else {
				foreach ($data as $record_id => $recordData) {
					$recordID = $record_id;
				}
			}
			//$module->saveData($userProjectID, $recordID, $event_id, array($module->getProjectSetting("project-field") => $projectID, $module->getProjectSetting("user-field") => $userID, $module->getProjectSetting("group-field") => $groupAssign, $module->getProjectSetting("dag-field") => $dagAssigns));
            \Records::saveData($userProjectID, 'array', [$recordID => [$event_id => array($module->getProjectSetting("project-field") => $projectID, $module->getProjectSetting("access-field") => $json_encoder, $module->getProjectSetting("user-field") => $userID, $module->getProjectSetting("group-field") => $groupAssign, $module->getProjectSetting("dag-field") => json_encode($dagAssigns))]],'overwrite');
		}
		if ($groupAssign != "") {
			foreach ($usersByGroup[$groupAssign] as $user) {
			    if (!in_array($user,$userIDs)) {
					$removedata = \REDCap::getData($userProjectID, 'array', "", array(), $event_id, array(), false, false, false, "([" . $module->getProjectSetting('project-field') . "] = '$projectID' and [" . $module->getProjectSetting('user-field') . "] = '" . $user . "')");
                    foreach ($removedata as $record_id => $recordData) {
                        $recordID = $record_id;
                    }

					\Records::saveData($userProjectID, 'array', [$recordID => [$event_id => array($module->getProjectSetting("group-field") => "")]],'overwrite');
                }
			}
		}
	}
	elseif (isset($_POST['load_user']) && $_POST['select_user'] != "") {
		$userID = db_real_escape_string($_POST['select_user']);
		//$data = \REDCap::getData($userProjectID, 'array', $userID);
		$data = \REDCap::getData($userProjectID, 'array', "", array(), $event_id, array(), false, false, false, "([" . $module->getProjectSetting('project-field') . "] = '$projectID' and [" . $module->getProjectSetting('user-field') . "] = '".$userID."')");

		foreach ($data as $recordID => $recordData) {
		    $customRights = json_decode($recordData[$event_id][$module->getProjectSetting("access-field")],true);
			$dagAssigns = $recordData[$event_id][$module->getProjectSetting("dag-field")];
        }

		//$customRights = json_decode($data[1][$event_id][$module->getProjectSetting("access-field")], true);

        $hiddenFields = array('select_user'=>$userID);
		drawRightsTables($dagList,$roleList,'',$hiddenFields,$module->getUrl('configure.php'));
		/*echo "<script type='text/javascript'>
		var roles = \"";
			foreach ($roleList as $roleType => $roleData) {
				echo "<option value='" . str_replace("'", "\\'", $roleType) . "'>" . str_replace("'", "\\'", $roleData) . "</option>";
			}
			echo "\";";
		echo "var dags = \"";
		foreach ($dagList as $dagType => $dagData) {
			echo "<option value='" . str_replace("'", "\\'", $dagType) . "'>" . str_replace("'", "\\'", $dagData) . "</option>";
		}
		echo "\";";
		echo "</script>";
		echo "<form action='".$module->getUrl('configure.php')."' method='POST'>
		<table id='user_roles_table' class='table table-bordered' style='width:100%;font-weight:normal;'>
		    <tr>
                <th style='text-align:center;' class='col-xs-1'>
                    User Roles
                </th>
                <td class='col-xs-11'>
                    <table id='table-tr' class='table table-bordered' style='width:100%;font-weight:normal;'>
                        <input type='hidden' name='select_user' value='$userID'/>
                        <tr id='add_new_select'>
                            <td colspan='2' style='text-align:center;' class='col-md-6'><input id='roles_addbutton' type='button' value='Add New Role' style='margin:auto;' onclick='addRightsRow(this);'/></td>
                            <td colspan='2' style='text-align:center;' class='col-md-6'><input type='submit' name='update_rights' value='Submit Rights'/></td>
                        </tr>
                    </table>
                </td>
            </tr>
		</table>
		</form>";*/

		if (!empty($customRights) || !empty($dagAssigns)) {
		    echo "<script>
            $(document).ready(function() {
                ".generatePrefill($customRights,$dagAssigns)."
                });
             </script>";
        }
	}
	elseif (isset($_POST['load_group']) && $postSelectGroup != "") {
	    $usersGroup = array();
	    if ($postNewGroup != "") {
	        $postGroup = db_real_escape_string($postNewGroup);
        }
        else {
	        $postGroup = $postSelectGroup;
			$postGroupData = \REDCap::getData($userProjectID, 'array', "", array(), $event_id, array(), false, false, false, "([" . $module->getProjectSetting('project-field') . "] = '$projectID' and [" . $module->getProjectSetting('group-field') . "] = '".db_real_escape_string($postSelectGroup)."')");
			foreach ($postGroupData as $recordID => $recordData) {
				$customRights = json_decode($recordData[$event_id][$module->getProjectSetting("access-field")],true);
				$dagAssigns = $recordData[$event_id][$module->getProjectSetting("dag-field")];
				$usersGroup[$recordData[$event_id][$module->getProjectSetting("user-field")]] = $recordData[$event_id][$module->getProjectSetting("user-field")];
			}
        }

        $userList = array_merge(array_flip($usersGroup),$userList);
	    $hiddenFields = array('new_group'=>$postNewGroup,'select_group'=>($postSelectGroup != "" ? $postSelectGroup : $postNewGroup));
	    drawRightsTables($dagList,$roleList,$userList,$hiddenFields,$module->getUrl('configure.php'),$usersGroup);
		/*echo "<script type='text/javascript'>
		var roles = \"";
		foreach ($roleList as $roleType => $roleData) {
			echo "<option value='" . str_replace("'", "\\'", $roleType) . "'>" . str_replace("'", "\\'", $roleData) . "</option>";
		}
		echo "\";";
		echo "var dags = \"";
		foreach ($dagList as $dagType => $dagData) {
			echo "<option value='" . str_replace("'", "\\'", $dagType) . "'>" . str_replace("'", "\\'", $dagData) . "</option>";
		}
		echo "\";";
		echo "</script>";

		echo "<form action='".$module->getUrl('configure.php')."' method='POST'>
		<table id='table-tr' class='table table-bordered' style='width:100%;font-weight:normal;'>
		    <input type='hidden' value='".$postNewGroup."' name='new_group' />
		    <input type='hidden' value='".($postSelectGroup != "" ? $postSelectGroup : $postNewGroup)."' name='select_group' />";
		$userCount = 0;
		echo "<tr>";
		    foreach ($userList as $userName => $realName) {
		        if ($userCount % 4 == 0 && $userCount !== 0) {
                    echo "</tr><tr>";
                }
                $userCount++;
		        echo "<td><input type='checkbox' name='select_user[]' value='$userName' ".(in_array($userName,$usersGroup) ? "checked" : "")."> $realName ($userName)</td>";
            }
            echo "</tr>";
		echo "<tr id='add_new_select'><td colspan='2' style='text-align:center;' class='col-md-6'><input id='roles_addbutton' type='button' value='Add New Role' style='margin:auto;' onclick='addRightsRow(this);'/></td>
			<td colspan='2' style='text-align:center;' class='col-md-6'><input type='submit' name='update_rights' value='Submit Rights'/></td></tr>
		</table></form>";*/

	    echo "<script>
            $(document).ready(function() {
            	$('#group_radio').trigger('onclick');
            	".(!empty($customRights) || !empty($dagAssigns) ? generatePrefill($customRights,$dagAssigns) : "")."
            });";
	    echo "</script>";
    }
}

function drawRightsTables($dagList,$roleList,$userList,$hiddenFields,$destination,$usersGroup = array())
{
	echo "<script type='text/javascript'>
		var roles = \"";
	foreach ($roleList as $roleType => $roleData) {
		echo "<option value='" . str_replace("'", "\\'", $roleType) . "'>" . str_replace("'", "\\'", $roleData) . "</option>";
	}
	echo "\";";
	echo "var dags = \"";
	foreach ($dagList as $dagType => $dagData) {
		echo "<option value='" . str_replace("'", "\\'", $dagType) . "'>" . str_replace("'", "\\'", $dagData) . "</option>";
	}
	echo "\";";
	echo "</script>";

	echo "<form action='" . $destination . "' method='POST'>
	<table id='dags_table' class='table table-bordered' style='width:100%;font-weight:normal;'>";
	foreach ($hiddenFields as $fieldName => $fieldValue) {
		echo "<input type='hidden' value='$fieldValue' name='$fieldName' />";
	}
	$userCount = 0;
	if (is_array($userList)) {
		echo "<tr>";
		foreach ($userList as $userName => $realName) {
			if ($userCount % 4 == 0 && $userCount !== 0) {
				echo "</tr><tr>";
			}
			$userCount++;
			echo "<td><input type='checkbox' name='select_user[]' value='$userName' " . (in_array($userName, $usersGroup) ? "checked" : "") . "> $realName ($userName)</td>";
		}
		echo "</tr>";
	}
	echo "<tr>
                <th style='text-align:center;' class='col-xs-1 dagHeader'>
                    DAG Assignments
                </th>
                <td class='col-xs-11'>";
	                $count = 0;
	                $tableHTML = "<table class='col-md-12 table table-bordered'><tr>";
                    foreach ($dagList as $dagID => $dagName) {
                        if ($count % 4 == 0 && $count !== 0) {
                            $tableHTML .= "</tr><tr>";
                        }
						$tableHTML .= "<td><input type='checkbox' id='dagid_".urlencode($dagID)."' name='dagid[".urlencode($dagID)."]' value='$dagID'>$dagName</td>";
						$count++;
                    }
                    $tableHTML .= "</tr></table>";
                    echo $tableHTML;
                echo "</td>
		    </tr>
		</table>
	<table id='user_roles_table' class='table table-bordered' style='width:100%;font-weight:normal;'>
		    <tr>
                <th style='text-align:center;' class='col-xs-1'>
                    User Roles Assignments
                </th>
                <td class='col-xs-11'>
                    <table id='table-tr' class='table table-bordered' style='width:100%;font-weight:normal;'>";

	echo "<tr id='add_new_right'><td colspan='2' style='text-align:center;' class='col-md-6'><input id='roles_addbutton' type='button' value='Add New Role' style='margin:auto;' onclick='addRightsRow(this);'/></td>
                        <td colspan='2' style='text-align:center;' class='col-md-6'><input type='submit' name='update_rights' value='Submit Rights'/></td></tr>
                    </table>
                </td>
		    </tr>
		</table>
		</form>";
}

function getUserList($project_id) {
    global $module;
	$userlist = array();
	$sql = "SELECT d2.username,CONCAT(d2.user_lastname, ', ', d2.user_firstname) as name
		FROM redcap_user_rights d
		JOIN redcap_user_information d2
			ON d.username = d2.username
		WHERE d.project_id=?
		ORDER BY name";
	$result = $module->query($sql,[$module->escape($project_id)]);
	while ($row = db_fetch_assoc($result)) {
		$userlist[$row['username']] = $row['name'];
	}
	return $userlist;
}

function getDAGList($project_id) {
    global $module;
	$dagList = array();
	$sql = "SELECT group_id, group_name
		FROM redcap_data_access_groups
		WHERE project_id=?
		ORDER BY group_name";
	$result = $module->query($sql,[$module->escape($project_id)]);
	while ($row = db_fetch_assoc($result)) {
		$dagList[$row['group_id']] = $row['group_name'];
	}
	return $dagList;
}

function getRoleList($project_id) {
    global $module;
	$roleList = array();
	$sql = "SELECT role_id, role_name
		FROM redcap_user_roles
		WHERE project_id=?";
	$result = $module->query($sql,$module->escape($project_id));
	while ($row = db_fetch_assoc($result)) {
		$roleList[$row['role_id']] = $row['role_name'];
	}
	return $roleList;
}

function getRecordList($project_id,$recordField) {
    global $module;
    $recordList = array();
    $table = $module->getDataTable($project_id);
    $sql = "SELECT DISTINCT(record)
        FROM $table
        WHERE project_id=?";
	$result = $module->query($sql,[$module->escape($project_id)]);
	//$resultCount = 0;
	while ($row = db_fetch_assoc($result)) {
		$recordList[$row['record']] = $row['record'];
		//$resultCount++;
	}
    return $recordList;
}

function generatePrefill($data,$dags) {
	$returnString = "";

	foreach ($data as $index => $rightsData) {
		$returnString .= "$('#roles_addbutton').click();
				var rowCount = $('.add_new_right_row').length;
				$('#add_new_right_role_'+rowCount).val('" . $rightsData['role'] . "').trigger('onchange');";
		/*if ($rightsData['exempt'] == "on") {
		    $returnString .= "$('#exempt_check_'+rowCount).prop('checked',true);";
        }*/

        foreach ($rightsData['record'] as $recordIndex => $recordID) {
		    $returnString .= "$('input[id^=\"recordid_'+rowCount+'\"][value=\"".$recordID."\"]').prop('checked',true);";
        }
	}
	$dagFixed = array();

	if (json_decode($dags) !== null) {
	    $dagFixed = array_keys(json_decode($dags,true));
    }
    else {
        $dagFixed = explode(",",$dags);
    }
	foreach ($dagFixed as $dagIndex => $dagID) {
		$returnString .= "$('input[id^=\"dagid_$dagID\"][value=\"".$dagID."\"]').prop('checked',true);";
	}
	return $returnString;
}

?>
<script>
	function addRightsRow(fieldName) {
		var parentID = $(fieldName).closest('tr').prop("id");
		var field = parentID.replace("_addnew","");
		var numRows = $('.'+field+'_table').length + 1;

		var rowHTML = "<tr id='"+field+"_table_"+numRows+"'><td colspan='4' style='padding:0;';><table class='"+field+"_table col-md-12 table table-bordered'><tr style='text-align:center;padding-top:5px;padding-bottom:5px;' class='"+field+"_row'><th colspan='2'>User Roles: <select class='picklist' id='"+field+"_role_"+numRows+"' name='"+field+"_role["+numRows+"]'><option value=''></option>"+roles+"</select></th><th style='width:25px;'><a style='color:white;cursor:pointer;' onclick='removeTable(\""+field+"_table_"+numRows+"\")'>X</a></th></tr><tr><td style='font-weight:bold;'>Select Records to Apply Role:</td><td><input class='"+field+"_"+numRows+"' type='checkbox' onclick='checkAll(this,\"recordid\");'>Check / Uncheck All<br/></td></tr><tr style='background-color:rgba(0,0,0,0.75);'><td style='text-align:center;' colspan='2'></td></tr>";
		<?php
            $tableHTML = "";
            if (!empty($recordList)) {
                /*if (!empty($customRights[1]['record'])) {
					$recordList = array_unique(array_merge($customRights[1]['record'], $recordList));
				}*/
                $count = 0;

				foreach ($recordList as $recordType => $recordData) {
				    if ($count % 2 == 0) {
				        $tableHTML .= "<tr>";
                    }
					$tableHTML .= "<td><input class='\"+field+\"_\"+numRows+\"' type='checkbox' id='recordid_\" + numRows + \"_".urlencode($recordType)."' name='recordid[\"+numRows+\"][".urlencode($recordType)."]' value='$recordData'>$recordData</td>";
				    if ($count % 2 != 0) {
				        $tableHTML .= "</tr>";
                    }
					$count++;
				}
			}
			echo "rowHTML += \"$tableHTML\";";
        ?>
        rowHTML += "</tr></table></td></tr>";
		$('#'+parentID).before(rowHTML);

		return numRows;
	}

	function addDAGsRow(fieldName) {
		var parentIDDAG = $(fieldName).closest('tr').prop("id");
		var fieldDAG = parentIDDAG.replace("_addnew","");
		var numRowsDAG = $('.'+fieldDAG+'_table').length + 1;
		var rowHTMLDAG = "<tr id='"+fieldDAG+"_table_"+numRowsDAG+"'><td colspan='4' style='padding:0;';><table class='"+fieldDAG+"_table col-md-12 table table-bordered'><tr style='text-align:center;padding-top:5px;padding-bottom:5px;' class='"+fieldDAG+"_row'><th class='dagHeader' colspan='2'>DAGs: <select class='picklist' id='"+fieldDAG+"_dag_"+numRowsDAG+"' name='"+fieldDAG+"_dag["+numRowsDAG+"]'><option value=''></option>"+dags+"</select></th><th class='dagHeader' style='width:25px;'><a style='color:white;cursor:pointer;' onclick='removeTable(\""+fieldDAG+"_table_"+numRowsDAG+"\")'>X</a></th></tr><tr style='background-color:rgba(0,0,0,0.75);'><td style='text-align:center;' colspan='2'></td></tr>";
		rowHTMLDAG += "</tr></table></td></tr>";
		$('#'+parentIDDAG).before(rowHTMLDAG);

		return numRowsDAG
    }

	function checkAll(trigger,elementName) {
		var parentClass = trigger.className;

		$('input.'+parentClass+':checkbox').each(function() {
			$(this).prop('checked',trigger.checked);
		});
    }

    function removeTable(tableID) {
		$('#'+tableID).remove();
    }

    function showHide(showID,parent,showValue,hideID = "") {
		if (parent.value == showValue) {
			$('#' + showID).show();
			if (hideID != "") {
				$('#' + hideID).hide();
			}
		}
		else {
			$('#' + showID).hide();
			if (hideID != "") {
				$('#' + hideID).show();
			}
        }
    }

</script>
