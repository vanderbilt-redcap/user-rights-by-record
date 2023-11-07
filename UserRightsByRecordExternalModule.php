<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 1/14/2018
 * Time: 7:25 PM
 */
namespace Vanderbilt\UserRightsByRecordExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class UserRightsByRecordExternalModule extends AbstractExternalModule
{
	function hook_every_page_before_render($project_id) {
		if ($project_id != "") {
			global $user_rights, $redcap_version,$lang;
			/*echo "<pre>";
			print_r($user_rights);
			echo "</pre>";*/
			/*$_SESSION['username'] = 'test_user';
			$_SESSION['_authsession']['username'] = 'test_user';*/
			$customRights = $dagAssigns = array();
			$actual_link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
			$userProjectID = $this->getRightsProjectID();

			$userProjectData = \Records::getData($userProjectID, 'array');
			$event_id = $this->getFirstEventId($userProjectID);

			foreach ($userProjectData as $recordID => $recordData) {
				if ($recordData[$event_id][$this->getProjectSetting('user-field')] == USERID && $recordData[$event_id][$this->getProjectSetting('project-field')] == $project_id) {
					$customRights = $this->processRightsJSON($project_id, $recordData[$event_id][$this->getProjectSetting("access-field")]);
					$dagAssigns = $this->processDAGs($recordData[$event_id][$this->getProjectSetting("dag-field")]);
				}
			}

			/*$newArray = array("2" => array('dag_id' => "8"), "3" => array('role_id' => '58', 'dag_id' => '8'), "4" => array('role_id' => '58', 'dag_id' => '9'), "5" => array('role_id' => '59', 'dag_id' => '9'), "6" => array('role_id' => '', 'dag_id' => '8'));
			echo json_encode($newArray);*/
			$redcapDashboardURL = APP_PATH_WEBROOT_FULL . "redcap_v$redcap_version/DataEntry/record_status_dashboard.php?pid=$project_id";
			$dagID = "";
			$roleID = "";
			if (!empty($customRights) || !empty($dagAssigns)) {
                if ($_GET['id'] != "") {
                    $dagID = \Records::getRecordGroupId($project_id, $_GET['id']);

                    $user_rights = $this->setCustomRights($project_id, $customRights, $_GET['id'], $user_rights);

                    if (in_array($dagID, $dagAssigns)) {
                        $user_rights['group_id'] = $dagID;
                    }

                    if ($this->recordExists($project_id,$_GET['id']) && ($_GET['page'] != "" && $_GET['page'] != 'configure' && $_GET['page'] != 'ajax_user') && (($customRights[$_GET['id']]['role'] != "" && (!isset($user_rights['forms'][$_GET['page']]) || $user_rights['forms'][$_GET['page']] === "0")) || ($user_rights['group_id'] !== $dagID && $user_rights['group_id'] !== ""))) {
                        echo "<script>window.location = '" . $redcapDashboardURL . "';</script>";
                    }
                } elseif ($actual_link == $redcapDashboardURL) {
                    global $Proj;
                    $formList = array_keys($Proj->forms);
                    $completeFields = array();
                    $formsByEvent = $Proj->eventsForms;
                    $longitudinal = $Proj->longitudinal;
                    $dashboard = $this->getRecordDashboardSettings();
                    $th_span1 = $th_span2 = '';
                    $th_width = 'width:35px;';
                    if ($dashboard['orientation'] == 'V') {
                        $th_span1 = '<span class="vertical-text"><span class="vertical-text-inner">';
                        $th_span2 = '</span></span>';
                        $th_width = '';
                    }
                    /*echo "<pre>";
                    print_r($formsByEvent);
                    echo "</pre>";*/
                    $eventNames = $Proj->eventInfo;
                    /*echo "<pre>";
                    print_r($eventNames);
                    echo "</pre>";*/

                    foreach ($formList as $formName) {
                        $completeFields[$formName] = $formName . "_complete";
                    }

                    $hdrs = \RCView::th(array('rowspan' => ($longitudinal ? '2' : '1'), 'style' => 'text-align:center;color:#800000;padding:5px 10px;vertical-align:bottom;'),
                        $th_span1 . $Proj->table_pk_label . $th_span2);
                    if ($longitudinal) {
                        $prev_event_id = $prev_form_name = null;
                        foreach ($formsByEvent as $eventID => $eventForms) {
                            if ($dashboard['group_by'] == 'event') {
                                // Skip if already did this event
                                if ($prev_event_id == $eventID) continue;
                                // Group by event
                                $hdrs .= \RCView::th(array('class' => 'rsd-left', 'colspan' => count($eventForms), 'style' => 'border-bottom:1px dashed #aaa;color:#800000;font-size:11px;text-align:center;padding:5px;white-space:normal;vertical-align:bottom;'),
                                    \RCView::escape($eventNames[$eventID]['name'])
                                );
                                $prev_event_id = $eventID;
                            } else {
                                // Skip if already did this event
                                if ($prev_form_name == $eventNames[$eventID]['name']) continue;
                                // Group by form
                                $hdrs .= \RCView::th(array('class' => 'rsd-left', 'colspan' => count($eventForms), 'style' => 'border-bottom:1px dashed #aaa;font-size:11px;text-align:center;padding:5px;white-space:normal;vertical-align:bottom;'),
                                    \RCView::escape($Proj->forms[$eventNames[$eventID]['name']]['menu'])
                                );
                                $prev_form_name = $eventNames[$eventID]['name'];
                            }
                        }
                        $rows = \RCView::tr('', $hdrs);
                        $hdrs = "";
                    }

                    $prev_form = $prev_event = null;
                    foreach ($formsByEvent as $eventID => $eventForms) {
                        if ($dashboard['group_by'] == 'event') {
                            // Group by event
                            foreach ($eventForms as $formName) {
                                $hdrs .= \RCView::th(array('class' => ($longitudinal && $prev_event != $eventID ? ' rsd-left' : ''), 'style' => $th_width . ($longitudinal ? 'border-top:0;' : '') . 'font-size:11px;text-align:center;padding:3px;white-space:normal;vertical-align:bottom;'),
                                    \RCView::div(array('style' => ($longitudinal ? 'font-weight:normal;' : '')),
                                        $th_span1 . \RCView::escape($Proj->forms[$formName]['menu']) . $th_span2
                                    )
                                );
                            }
                        } else {
                            // Group by form
                            $hdrs .= \RCView::th(array('class' => ($longitudinal && $prev_form != $eventNames[$eventID]['name'] ? ' rsd-left' : ''), 'style' => $th_width . ($longitudinal ? 'border-top:0;' : '') . 'color:#800000;font-size:11px;text-align:center;padding:3px;white-space:normal;vertical-align:bottom;'),
                                \RCView::div(array('style' => ($longitudinal ? 'font-weight:normal;' : '')),
                                    $th_span1 . \RCView::escape($eventNames[$eventID]['name']) . $th_span2
                                )
                            );
                        }
                        $prev_form = $eventNames[$eventID]['name'];
                        $prev_event = $eventID;
                    }
                    $rows .= \RCView::tr('', $hdrs);
                    $rows = \RCView::thead('', $rows);

                    $data = \REDCap::getData($project_id, 'array', '', array($Proj->table_pk));

                    /*$formStatus = \Records::getFormStatus($project_id,array_keys($data));
                    echo "<pre>";
                    print_r($formStatus);
                    echo "</pre>";*/
                    $extra_record_labels = \Records::getCustomRecordLabelsSecondaryFieldAllRecords();// Obtain custom record label & secondary unique field labels for ALL records.
                    $recordsPerArm = array();

                    foreach ($data as $recordID => $junkData) {
                        $recordRights = array();
                        $groupID = \Records::getRecordGroupId($project_id, $recordID);
                        $recordRights = $this->setCustomRights($project_id, $customRights, $recordID, $recordRights);
                        $recordData = $this->getFormStatus($project_id, $recordID);

                        if (empty($recordRights) && ($user_rights['group_id'] != $groupID && $user_rights['group_id'] !== "") && !in_array($groupID, $dagAssigns)) continue;

                        $this_row = \RCView::td(array('style' => 'font-size:12px;'),
                            \RCView::a(array('href' => APP_PATH_WEBROOT . "DataEntry/record_home.php?pid=$project_id&arm=" . $this->getFirstArmRecord($recordID, $recordsPerArm) . "&id=" . removeDDEending($recordID), 'style' => 'text-decoration:underline;font-size:13px;'), removeDDEending($recordID)) .
                            // Display custom record label or secondary unique field (if applicable)
                            (isset($extra_record_labels[$recordID]) ? '&nbsp;&nbsp;' . $extra_record_labels[$recordID] : '')
                        );
                        $lockimgStatic = trim(\RCView::img(array('class' => 'lock', 'src' => 'lock_small.png')));
                        $esignimgStatic = trim(\RCView::img(array('class' => 'esign', 'src' => 'tick_shield_small.png')));
                        $lockimgMultipleStatic = trim(\RCView::img(array('class' => 'lock', 'src' => 'locks_small.png')));
                        $esignimgMultipleStatic = trim(\RCView::img(array('class' => 'esign', 'src' => 'tick_shields_small.png')));
                        $completeStatuses = array();
                        foreach ($recordData as $eventID => $eventData) {
                            $this->getStatusCount($eventID, $eventData, $completeStatuses);
                        }
                        //echo "Record ID: $recordID<br/>";
                        /*echo "<pre>";
                        print_r($recordData);
                        echo "</pre>";
                        echo "<pre>";
                        print_r($completeStatuses);
                        echo "</pre>";*/

                        $prev_form = $prev_event = null;
                        $rowclass = "even";
                        foreach ($formsByEvent as $eventID => $eventForms) {
                            foreach ($eventForms as $formName) {
                                $form_has_multiple_instances = (count($recordData[$eventID][$formName]) > 1);
                                $statusIconStyle = ($form_has_multiple_instances) ? 'width:22px;' : 'width:16px;margin-right:6px;';

                                $addRptBtn = '';
                                $highest_instance = max(array_keys($recordData[$event_id][$formName]));
                                if (empty($highest_instance)) $highest_instance = '1';
                                if ($Proj->isRepeatingForm($eventID, $formName)) {
                                    // Get next instance number
                                    $next_instance = $highest_instance + 1;
                                    // Display button
                                    $this_url = APP_PATH_WEBROOT . "DataEntry/index.php?pid=$project_id&id=" . urlencode(removeDDEending($recordID)) . "&event_id={$eventID}&page={$formName}";
                                    $addRptBtn = "<button title='" . js_escape($lang['grid_43']) . "' onclick=\"window.location.href='$this_url&instance=$next_instance';\" class='btn btn-defaultrc btnAddRptEv " . ($form_has_multiple_instances == '' ? "invis" : "opacity50") . "'>+</button>";
                                }

                                if ($longitudinal) {
                                    if ($dashboard['group_by'] == 'event') {
                                        $grouping_class = ($prev_event != $eventID ? 'rsd-left' : '');
                                    } else {
                                        $grouping_class = ($prev_form != $formName ? 'rsd-left' : '');
                                    }
                                } else {
                                    $grouping_class = '';
                                }
                                if ($form_has_multiple_instances) {
                                    $href = "javascript:;";
                                    $onclick = "onclick=\"loadInstancesTable(this,'" . js_escape($recordID) . "', {$eventID}, '{$formName}');\"";
                                } else {
                                    $href = APP_PATH_WEBROOT . "DataEntry/index.php?pid=$project_id&id=" . removeDDEending($recordID) . "&page={$formName}&event_id={$eventID}&instance=$highest_instance";
                                    $onclick = "";
                                }
                                if (($recordRights['forms'][$formName] > 0 && isset($recordRights['forms'][$formName])) || ($recordRights['forms'][$formName] == "" && $user_rights['forms'][$formName] > 0) || SUPER_USER) {
                                    $td = "<a href='$href' $onclick style='text-decoration:none;'><img src='" . APP_PATH_IMAGES . ($completeStatuses[$eventID][$formName]['icon'] != "" ? $completeStatuses[$eventID][$formName]['icon'] : "circle_gray.png") . "' class='fstatus' style='$statusIconStyle'></a>" . $addRptBtn;
                                } else {
                                    $td = "";
                                }

                                $this_row .= \RCView::td(array('class' => $grouping_class, 'style' => 'text-align:center;'), $td);
                                $prev_form = $formName;
                                $prev_event = $eventID;
                            }
                        }
                        $rowclass = ($rowclass == "even") ? "odd" : "even";
                        $rows .= \RCView::tr(array('class' => $rowclass), $this_row);
                    }
                    /*echo "<pre>";
                    print_r($data);
                    echo "</pre>";*/
                    $rows = str_replace("\n", "", $rows);
                    $rows = str_replace("'", "\\\"", $rows);
                    echo "<script type='text/javascript' src='" . $this->getUrl('js/jquery.min.js') . "'></script>
				<script type='text/javascript'>
				$(document).ready(function() {
					$('#record_status_table').html('$rows');
				});
				</script>";
                }
            }
		}
	}


	private function getFormAccess($project_id,$role_id) {
		$formAccess = array();
		if (!is_numeric($role_id) || !is_numeric($role_id)) return $formAccess;
		$sql = "SELECT data_entry
			FROM redcap_user_roles
			WHERE project_id=".$project_id."
			AND role_id=".$role_id;
		//echo "$sql<br/>";
		$roleForms = db_result($this->query($sql),0);
		$roleForms = ltrim($roleForms,"[");
		$roleForms = rtrim($roleForms,"]");
		$formArray = array();
		$formArray = explode("][",$roleForms);
		foreach ($formArray as $formData) {
			$formElement = explode(",",$formData);
			$formAccess[$formElement[0]] = $formElement[1];
		}
		return $formAccess;
	}

	public function getRightsProjectID() {
		return $this->getProjectSetting('user-project');
	}

	public function processRightsJSON($project_id,$rights) {
		$rightsArray = array();
		if (!is_array($rights)) {
			$rights = json_decode($rights,true);
		}
		foreach ($rights as $rightData) {
			foreach ($rightData['record'] as $recordIndex => $recordID) {
				if (!isset($rightsArray[$recordID])) {
					$rightsArray[$recordID] = array("role"=>$rightData['role'],"dag"=>\Records::getRecordGroupId($project_id,$recordID));
				}
			}
		}
		return $rightsArray;
	}

	public function processDAGs($dagAssigns) {
		$dagArray = array();
		if ($dagAssigns != "") {
		    if (json_decode($dagAssigns) !== null) {
		        $dagArray = array_keys(json_decode($dagAssigns,true));
            }
            else {
                $dagArray = explode(",", $dagAssigns);
            }
		}
		return $dagArray;
	}

	public function setCustomRights($project_id, $customRights,$recordID,$userRights) {
		if (isset($customRights[$recordID])) {
			$formAccess = $this->getFormAccess($project_id, $customRights[$recordID]['role']);
			$userRights['group_id'] = $customRights[$recordID]['dag'];
			$userRights['role_id'] = $customRights[$recordID]['role'];
			$userRights['forms'] = $formAccess;
		}
		return $userRights;
	}

	public function getStatusCount($eventID, $eventData, &$completeStatuses) {
		//$completeStatuses = array();
		foreach ($eventData as $formName => $formData) {
			if (empty($formData)) {
				$completeStatuses[$eventID][$formName]['icon'] = "circle_gray.png";
				continue;
			}
			foreach ($formData as $formStatus) {
				if (isset($completeStatuses[$eventID][$formName])) {
					if ($completeStatuses[$eventID][$formName]['previous_status'] === $formStatus && $completeStatuses[$eventID][$formName]['icon'] != "circle_blue_stack.png") {
						if ($formStatus === "2") {
							$completeStatuses[$eventID][$formName]['icon'] = "circle_green_stack.png";
						}
						elseif ($formStatus === "1") {
							$completeStatuses[$eventID][$formName]['icon'] = "circle_yellow_stack.png";
						}
						elseif ($formStatus === "0") {
							$completeStatuses[$eventID][$formName]['icon'] = "circle_red_stack.png";
						}
						else {
							$completeStatuses[$eventID][$formName]['icon'] = "circle_gray.png";
						}
						//$completeStatuses[$eventID][$formName]['icon'] = "many";
					} else {
						$completeStatuses[$eventID][$formName]['icon'] = "circle_blue_stack.png";
					}
					$completeStatuses[$eventID][$formName]['previous_status'] = $formStatus;
				} else {
					$completeStatuses[$eventID][$formName]['previous_status'] = $formStatus;
					if ($formStatus === "2") {
						$completeStatuses[$eventID][$formName]['icon'] = "circle_green.png";
					}
					elseif ($formStatus === "1") {
						$completeStatuses[$eventID][$formName]['icon'] = "circle_yellow.png";
					}
					elseif ($formStatus === "0") {
						$completeStatuses[$eventID][$formName]['icon'] = "circle_red.png";
					}
					else {
						$completeStatuses[$eventID][$formName]['icon'] = "circle_gray.png";
					}
					//$completeStatuses[$eventID][$formName]['icon'] = $formStatus;
				}
			}
		}
		//return $completeStatuses;
	}

    function getDataTable($project_id){
        return method_exists('\REDCap', 'getDataTable') ? \REDCap::getDataTable($project_id) : "redcap_data"; 
    }

	public function getAutoId($project_id) {
		$table = $this->getDataTable($project_id);
		$sql = "SELECT MAX(CAST(record as UNSIGNED))
				FROM $table
				WHERE project_id=$project_id";
		//echo "$sql<br/>";
		$highestRecord = db_result($this->query($sql),0);
		$highestRecord++;
		return $highestRecord;
	}

	public function getFormStatus($project_id,$record_id) {
		$returnArray = array();
		$table = $this->getDataTable($project_id);
		$sql = "SELECT d2.event_id,d2.field_name,d2.value,d.form_name
				FROM redcap_metadata d
				JOIN $table d2
					ON d.field_name=d2.field_name AND d.project_id=d2.project_id
				WHERE d.project_id=$project_id
				AND d2.record='$record_id'
				AND d.field_name LIKE '%_complete'";
		//echo "$sql<br/>";
		$result = $this->query($sql);
		while ($row = db_fetch_assoc($result)) {
			$returnArray[$row['event_id']][$row['form_name']][] = $row['value'];
		}
		return $returnArray;
	}

	/*function redcap_module_link_check_display($project_id, $link) {
		if(\REDCap::getUserRights(USERID)[USERID]['design'] == '1'){
			return $link;
		}
		return null;
	}*/

	function getUserList($project_id) {
		$userlist = array();
		$sql = "SELECT d2.username,CONCAT(d2.user_firstname, ' ', d2.user_lastname) as name
		FROM redcap_user_rights d
		JOIN redcap_user_information d2
			ON d.username = d2.username
		WHERE d.project_id=$project_id";
		$result = $this->query($sql);
		while ($row = db_fetch_assoc($result)) {
			$userlist[$row['username']] = $row['name'];
		}
		return $userlist;
	}

    // Return array of settings for Custom Record Status Dashboard using the rd_id
    private static function getRecordDashboardSettings($rd_id=null)
    {
        global $lang, $Proj;
        // Validate rd_id
        $rd_id = (int)$rd_id;
        // Set default dashboard settings
        $dashboard = getTableColumns('redcap_record_dashboards');
        // If we're showing the default dashboard, then return default array
        if (empty($rd_id)) return $dashboard;
        // Get the dashboard
        $sql = "select * from redcap_record_dashboards where rd_id = $rd_id and project_id = ".PROJECT_ID;
        $q = db_query($sql);
        if ($q && db_num_rows($q)) {
            // Overlay values from table
            $dashboard = db_fetch_assoc($q);
        }
        // Always force group_by event if classic project
        if (!$Proj->longitudinal) {
            $dashboard['group_by'] = 'event';
            $dashboard['sort_event_id'] = $Proj->firstEventId;
        }
        return $dashboard;
    }

    // Return first arm that a given record exists in
    private static function getFirstArmRecord($this_record, $recordsPerArm)
    {
        // Loop through arms till we find it. If not found, default to arm 1.
        foreach ($recordsPerArm as $arm=>$records) {
            if (isset($records[$this_record])) return $arm;
        }
        return '1';
    }

    // Does the record we're viewing actually exist? Need to let people see these in case of trying to make a new record
    private static function recordExists($project_id,$record) {
	    if (is_numeric($project_id)) {
            $table = $this->getDataTable($project_id);
            $sql = "SELECT record FROM $table WHERE project_id=$project_id AND record=$record LIMIT 1";
            $q = db_query($sql);
            if ($q && db_num_rows($q)) {
                return true;
            }
        }
	    return false;
    }
}