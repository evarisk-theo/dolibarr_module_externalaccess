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
		$langs->load('projects');
		$context = Context::getInstance();
		if (!$context->controllerInstance->checkAccess()) { return; }

		$context->title = $langs->trans('TimeSpent');
		$context->desc = $langs->trans('TimeSpent');
		$context->menu_active[] = 'tasks';

		$hookRes = $this->hookDoAction();
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
		include_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

		$langs->load('projects', 'main');

		$taskId = GETPOST('id');

		$sql = 'SELECT ptt.task_duration, ptt.task_datehour, ptt.note, ptt.fk_user';

		// Add fields from hooks
		$parameters = array();
		$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters); // Note that $action and $object may have been modified by hook
		$sql .= $hookmanager->resPrint;

		$sql.= ' FROM '.MAIN_DB_PREFIX.'projet_task_time as ptt';
		$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'projet_task as t ON ptt.fk_task=t.rowid';
		$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'projet as p ON t.fk_projet=p.rowid';
		$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'element_contact as ct ON ct.element_id=p.rowid';
		$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'c_type_contact as cct ON cct.rowid=ct.fk_c_type_contact';
		$sql.= '  AND  cct.element=\'project\' AND cct.source=\'external\'';
		$sql.= '  AND  ct.fk_socpeople='.(int) $contactId;

		// Add From from hooks
		$parameters = array();
		$reshook = $hookmanager->executeHooks('printFieldListFrom', $parameters); // Note that $action and $object may have been modified by hook
		$sql .= $hookmanager->resPrint;

		$sql.= ' WHERE ptt.fk_task = '. $taskId;
		$sql.= ' AND p.fk_soc = '. intval($socId);
		$sql.= ' AND p.fk_statut = '.Project::STATUS_VALIDATED;
		$sql.= ' AND p.entity IN ('.getEntity("project").')';//Compatibility with Multicompany

		// Add where from hooks
		$parameters = array();
		$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters); // Note that $action and $object may have been modified by hook
		$sql .= $hookmanager->resPrint;

		$sql.= ' ORDER BY ptt.task_datehour DESC';

		$tableItems = $context->dbTool->executeS($sql);

		if (!empty($tableItems))
		{
			$TFieldsCols = array(
				'ptt.user' => array('status' => true),
				'ptt.date' => array('status' => true),
				'ptt.timespent' => array('status' => true),
				'ptt.note' => array('status' => true),
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

			$task = new Task($db);
			$task->fetch($taskId);
			$task->fetchObjectLinked();

			print '<div style="text-align: right"><a href="'. $_SERVER['PHP_SELF'] . '?controller=tasks&ctoken=' . GETPOST('ctoken') .'">'. $langs->trans('BackToList') . ' <i class="fa fa-arrow-left"></i></a></div>';

			print '<table class="table table-striped">';

			print '<tr style="text-align: center"><td>';
			print $langs->trans('Task');
			print '</td><td>';
			print $langs->trans('PlannedWorkload');
			print '</td><td>';
			print $langs->trans('TimeSpent');
			print '</td></tr>';

			print '<tr style="text-align: center"><td>';
			print '<b>' . ' ' . $task->ref . ' - ' . $task->label . '</b>';
			print '</td><td>';
			print convertSecondToTime($task->planned_workload);
			print '</td><td>';
			print '<b>' . convertSecondToTime($task->duration_effective, 'allhourmin')	 . '</b>';
			print '</td></tr>';

			print '</table>';
			print '<table id="projettask-list" class="table table-striped" >';

			print '<thead>';
			print '<tr>';

			if (!empty($TFieldsCols['ptt.user']['status'])) {
				print ' <th class="t_timespent_title text-center" >' . $langs->trans('User') . '</th>';
			}
			if (!empty($TFieldsCols['ptt.date']['status'])) {
				print ' <th class="t_timespent_title text-center" >' . $langs->trans('Date') . '</th>';
			}
			if (!empty($TFieldsCols['ptt.timespent']['status'])) {
				print ' <th class="t_timespent_title text-center" >' . $langs->trans('TimeSpent') . '</th>';
			}
			if (!empty($TFieldsCols['ptt.note']['status'])) {
				print ' <th class="t_timespent_title text-center" >' . $langs->trans('Note') . '</th>';
			}
			print '</tr>';

			print '</thead>';

			print '<tbody>';
			foreach ($tableItems as $item)
			{
				$usertmp = new User($db);
				$usertmp->fetch($item->fk_user);

				print '<tr>';

				if (!empty($TFieldsCols['ptt.user']['status'])) {
					print ' <td class="ptt_user_name text-center">' . $usertmp->getFullName($langs) . '</td>';
				}
				if (!empty($TFieldsCols['ptt.date']['status'])) {
					print ' <td class="ptt_timespent_date text-center">' . $item->task_datehour . '</td>';
				}

				if (!empty($TFieldsCols['ptt.timespent']['status'])) {
					$timespent = '';
					if (!empty($item->task_duration != '')) {
						$timespent = convertSecondToTime($item->task_duration  , 'allhourmin');
					}
					print ' <td class="ptt_timespent_value text-center" data-search="' . $timespent . '" data-order="' . $timespent . '"  >' . $timespent . '</td>';
				}
				if (!empty($TFieldsCols['ptt.note']['status'])) {
					print ' <td class="ptt_timespent_note text-center">' . dol_htmlentitiesbr($item->note) . '</td>';
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
