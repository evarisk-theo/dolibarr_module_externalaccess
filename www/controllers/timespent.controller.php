<?php

/**
 * Class TasksController
 */
class TimeSpentController extends Controller
{

	/**
	 * check current access to controller
	 *
	 * @param void
	 * @return  bool
	 */
	public function checkAccess() {
		global $conf, $user;
		$this->accessRight = !empty($conf->projet->enabled) && $conf->global->EACCESS_ACTIVATE_PROJECTS && !empty($user->rights->externalaccess->view_projects);
		return parent::checkAccess();
	}

	/**
	 * action method is called before html output
	 * can be used to manage security and change context
	 *
	 * @return void
	 */
	public function action()
	{
		global $langs;
		$context = Context::getInstance();
		if (!$context->controllerInstance->checkAccess()) { return; }

		$context->title = $langs->trans('ViewTimeSpent');
		$context->desc = $langs->trans('ViewTimeSpentDesc');
		$context->menu_active[] = 'tasks';

		$hookRes = $this->hookDoAction();
		if (empty($hookRes)){
		}
	}


	/**
	 *
	 * @return void
	 */
	public function display()
	{
		global $conf, $user;
		$context = Context::getInstance();
		if (!$context->controllerInstance->checkAccess()) {  return $this->display404(); }

		$this->loadTemplate('header');

		$hookRes = $this->hookPrintPageView();
		if (empty($hookRes)){
			print '<section id="section-task"><div class="container">';
			$this->printTaskTimeSpentTable($user->socid, $user->contact_id);
			print '</div></section>';
		}

		$this->loadTemplate('footer');
	}

	/**
	 * @param int $socId socid
	 * @param int $contactId contactId
	 * @return void
	 */
	public function printTaskTimeSpentTable($socId = 0, $contactId = 0)
	{
		global $langs, $db, $conf, $hookmanager;
		$context = Context::getInstance();

		include_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
		include_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
		//include_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';
		include_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
		///home/florian/Develop/www/module/dolibarr/htdocs

		$langs->load('projects', 'main');

		$taskId = GETPOST('id');

		$sql = 'SELECT ptt.task_duration, ptt.task_datehour';

		// Add fields from hooks
		$parameters = array();
		$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters); // Note that $action and $object may have been modified by hook
		$sql .= $hookmanager->resPrint;

		$sql.= ' FROM '.MAIN_DB_PREFIX.'projet_task_time as ptt';
//		$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'element_contact as ct ON ct.element_id=p.rowid';
//		$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'c_type_contact as cct ON cct.rowid=ct.fk_c_type_contact';
//		$sql.= '  AND  cct.element=\'project\' AND cct.source=\'external\'';
//		$sql.= '  AND  ct.fk_socpeople='.(int) $contactId;

		// Add From from hooks
		$parameters = array();
		$reshook = $hookmanager->executeHooks('printFieldListFrom', $parameters); // Note that $action and $object may have been modified by hook
		$sql .= $hookmanager->resPrint;
//
		$sql.= ' WHERE ptt.fk_task = '. $taskId;
//		$sql.= ' AND p.fk_soc = '. intval($socId);
//		$sql.= ' AND p.fk_statut = '.Project::STATUS_VALIDATED;
//		$sql.= ' AND p.entity IN ('.getEntity("project").')';//Compatibility with Multicompany

		// Add where from hooks
		$parameters = array();
		$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters); // Note that $action and $object may have been modified by hook
		$sql .= $hookmanager->resPrint;

		$sql.= ' ORDER BY ptt.task_datehour DESC';

		$tableItems = $context->dbTool->executeS($sql);

		if (!empty($tableItems))
		{

			$TFieldsCols = array(
				't.ref' => array('status' => true),
				't.label' => array('status' => true),
				't.dateo' => array('status' => true),
				't.datee' => array('status' => true),
				't.duration_effective' => array('status' => true),
				't.planned_workload' => array('status' => true),
				't.timespent' => array('status' => true),
				//'downloadlink' => array('status' => true),
			);

			$parameters = array(
				'socId' => $socId,
				'tableItems' =>& $tableItems,
				'TFieldsCols' =>& $TFieldsCols
			);

			$reshook = $hookmanager->executeHooks('listColumnField', $parameters, $context); // Note that $object may have been modified by hook
			if ($reshook < 0) {
				$context->setEventMessages($hookmanager->errors, 'errors');
			} elseif (empty($reshook)) {
				$TFieldsCols = array_replace($TFieldsCols, $hookmanager->resArray); // array_replace is used to preserve keys
			} else {
				$TFieldsCols = $hookmanager->resArray;
			}

			/*$TOther_fields_all = unserialize($conf->global->EACCESS_LIST_ADDED_COLUMNS);
			if (empty($TOther_fields_all))
				$TOther_fields_all = array();

			$TOther_fields_project = unserialize($conf->global->EACCESS_LIST_ADDED_COLUMNS_PROJECT);
			if (empty($TOther_fields_project))
				$TOther_fields_project = array();

			$TOther_fields = array_merge($TOther_fields_all, $TOther_fields_project);*/

			$TOther_fields = unserialize($conf->global->EACCESS_LIST_ADDED_COLUMNS);

			print '<table id="projettask-list" class="table table-striped" >';

			print '<thead>';

			print '<tr>';

			if (!empty($TFieldsCols['t.ref']['status'])) {
				print ' <th class="t_ref_title text-center" >' . $langs->trans('Task').' '.$langs->trans('Ref') . '</th>';
			}
			if (!empty($TFieldsCols['t.label']['status'])) {
				print ' <th class="t_label_title text-center" >' . $langs->trans('Task').' '.$langs->trans('Label') . '</th>';
			}
			if (!empty($TFieldsCols['t.dateo']['status'])) {
				print ' <th class="t_dateo_title text-center" >' . $langs->trans('Task').' '.$langs->trans('DateStart') . '</th>';
			}
			if (!empty($TFieldsCols['t.datee']['status'])) {
				print ' <th class="t_datee_title text-center" >' . $langs->trans('Task').' '.$langs->trans('DateEnd') . '</th>';
			}
			if (!empty($TFieldsCols['t.duration_effective']['status'])) {
				print ' <th class="t_duration_effective_title text-center" >' . $langs->trans('Task').' '.$langs->trans('ProgressCalculated') . '</th>';
			}
			if (!empty($TFieldsCols['t.planned_workload']['status'])) {
				print ' <th class="t_planned_workload_title text-center" >' . $langs->trans('Task').' '.$langs->trans('PlannedWorkload') . '</th>';
			}
			if (!empty($TFieldsCols['t.timespent']['status'])) {
				print ' <th class="t_timespent_title text-center" >' . $langs->trans('TimeSpent') . '</th>';
			}
			print '</tr>';

			print '</thead>';

			print '<tbody>';
			foreach ($tableItems as $item)
			{
				$task = new Task($db);
				$task->fetch($taskId);
				$task->fetchObjectLinked();

				print '<tr>';

				if (!empty($TFieldsCols['t.ref']['status'])) {
					print ' <td class="t_ref_value text-center" data-search="' . $task->ref . '" data-order="' . $task->ref . '"  >' . $task->ref . '</td>';
				}
				if (!empty($TFieldsCols['t.label']['status'])) {
					print ' <td class="t_label_value text-center" data-search="' . $task->label . '" data-order="' . $task->label . '"  >' . $task->label . '</td>';
				}
				if (!empty($TFieldsCols['t.dateo']['status'])) {
					print ' <td class="t_dateo_value text-center" data-search="' . dol_print_date($task->date_start) . '" data-order="' . $task->date_start . '"  >' . dol_print_date($task->date_start) . '</td>';
				}
				if (!empty($TFieldsCols['t.datee']['status'])) {
					print ' <td class="t_datee_value text-center" data-search="' . dol_print_date($task->date_end) . '" data-order="' . $task->date_end . '"  >' . dol_print_date($task->date_end) . '</td>';
				}
				if (!empty($TFieldsCols['t.duration_effective']['status'])) {
					$duration_effective = '';
					if (!empty($task->duration_effective != '')) {
						$duration_effective = convertSecondToTime($task->duration_effective, 'allhourmin');
					}
					print ' <td class="t_duration_effective_value text-center" data-search="' . $duration_effective . '" data-order="' . $duration_effective . '"  >' . $duration_effective . '</td>';
				}
				if (!empty($TFieldsCols['t.planned_workload']['status'])) {
					$planned_workload = '';
					if (!empty($task->planned_workload != '')) {
						$planned_workload = convertSecondToTime($task->planned_workload, 'allhourmin');
					}
					print ' <td class="t_planned_workload_value text-center" data-search="' . $planned_workload . '" data-order="' . $planned_workload . '"  >' . $planned_workload . '</td>';
				}
				if (!empty($TFieldsCols['t.timespent']['status'])) {
					$timespent = '';
					if (!empty($item->task_duration != '')) {
						$timespent = convertSecondToTime($item->task_duration  , 'allhourmin');
					}
					print ' <td class="t_timespent_value text-center" data-search="' . $timespent . '" data-order="' . $timespent . '"  >' . $timespent . '</td>';
				}
				print '</tr>';
			}
			print '</tbody>';

			print '</table>';
			?>
			<script type="text/javascript" >
				$(document).ready(function(){
					//$("#expedition-list").DataTable(<?php //echo json_encode($dataTableConf) ?>//);
					$("#projettask-list").DataTable({
						"language": {
							"url": "<?php print $context->getControllerUrl(); ?>vendor/data-tables/french.json"
						},
						//"order": [[<?php echo ($total_more_fields + 2); ?>, 'desc']],

						responsive: true,

						columnDefs: [{
							orderable: false,
							"aTargets": [-1]
						}, {
							"bSearchable": false,
							"aTargets": [-1, -2]
						}]

					});
				});
			</script>
			<?php
		}
		else {
			print '<div class="info clearboth text-center" >';
			print  $langs->trans('EACCESS_Nothing');
			print '</div>';
		}
	}
}
