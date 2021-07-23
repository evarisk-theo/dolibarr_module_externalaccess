<?php

/**
 * Class ProjectsController
 */
class ProjectsController extends Controller
{

	/**
	 * ProjectsController constructor.
	 */
	public function __construct()
	{
		global $conf, $user;
		parent::__construct();

		$this->accessRight = !empty($conf->projet->enabled) && $conf->global->EACCESS_ACTIVATE_PROJECTS && !empty($user->rights->externalaccess->view_projects);
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

		$context->title = $langs->trans('ViewProjects');
		$context->desc = $langs->trans('ViewProjectsDesc');
		$context->menu_active[] = 'projects';

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
			print '<section id="section-project"><div class="container">';
			$this->printProjectTable($user->socid);
			print '</div></section>';
		}

		$this->loadTemplate('footer');
	}

	/**
	 * @param int $socId socid
	 * @return void
	 */
	public function printProjectTable($socId = 0)
	{
		global $langs, $db, $conf, $hookmanager;
		$context = Context::getInstance();

		include_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
		include_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';

		$langs->load('sendings', 'main');


		$sql = 'SELECT rowid ';

		// Add fields from hooks
		$parameters = array();
		$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters); // Note that $action and $object may have been modified by hook
		$sql .= $hookmanager->resPrint;

		$sql.= ' FROM `'.MAIN_DB_PREFIX.'projet` ';

		// Add From from hooks
		$parameters = array();
		$reshook = $hookmanager->executeHooks('printFieldListFrom', $parameters); // Note that $action and $object may have been modified by hook
		$sql .= $hookmanager->resPrint;

		$sql.= ' WHERE fk_soc = '. intval($socId);
		$sql.= ' AND fk_statut > 0';
		$sql.= ' AND entity IN ('.getEntity("expedition").')';//Compatibility with Multicompany

		// Add where from hooks
		$parameters = array();
		$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters); // Note that $action and $object may have been modified by hook
		$sql .= $hookmanager->resPrint;

		$sql.= ' ORDER BY projet.datec DESC';

		$tableItems = $context->dbTool->executeS($sql);

		if (!empty($tableItems))
		{
			//TODO : ajouter la variable $dataTableConf en paramètre du hook => résoudre le souci de "order"
			//      $dataTableConf = array(
			//          'language' => array(
			//              'url' => $context->getRootUrl() . 'vendor/data-tables/french.json',
			//          ),
			//          'order' => array(),
			//          'responsive' => true,
			//          'columnDefs' => array(
			//              array(
			//                  'orderable' => false,
			//                  'aTargets' => array(-1),
			//              ),
			//              array(
			//                  'bSearchable' => false,
			//                  'aTargets' => array(-1, -2),
			//              ),
			//          ),
			//      );

			$TFieldsCols = array(
				'ref' => array('status' => true),
				'reftoshow' => array('status' => true),
				'dated' => array('status' => true),
				'datef' => array('status' => true),
				'downloadlink' => array('status' => true),
				'status' => array('status' => true),
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


			$TOther_fields_all = unserialize($conf->global->EACCESS_LIST_ADDED_COLUMNS);
			if (empty($TOther_fields_all))
				$TOther_fields_all = array();

			$TOther_fields_project = unserialize($conf->global->EACCESS_LIST_ADDED_COLUMNS_PROJECT);
			if (empty($TOther_fields_project))
				$TOther_fields_project = array();

			$TOther_fields = array_merge($TOther_fields_all, $TOther_fields_project);

			print '<table id="expedition-list" class="table table-striped" >';

			print '<thead>';

			print '<tr>';

			if (!empty($TFieldsCols['ref']['status'])){
				print ' <th class="text-center" >'.$langs->trans('Ref').'</th>';
			}

			if (!empty($TOther_fields)) {
				foreach ($TOther_fields as $field) {
					if ($field === 'ref_client' && !isset($object->field)) $field = 'ref_customer';
					if (property_exists('Expedition', $field) || strstr($field, 'linked'))
					{
						print ' <th class="'.$field.'_title text-center" >'.$langs->trans($field).'</th>';
					}
				}
			}
			if (!empty($TFieldsCols['reftoshow']['status'])) {
				print ' <th class="reftoshow_title text-center" >' . $langs->trans('pdfLinkedDocuments') . '</th>';
			}
			if (!empty($TFieldsCols['delivery_date']['status'])) {
				print ' <th class="text-center delivery_date" >' . $langs->trans('DateLivraison') . '</th>';
			}
			if (!empty($TFieldsCols['status']['status'])) {
				print ' <th class="statut_title text-center" >' . $langs->trans('Status') . '</th>';
			}
			if (!empty($TFieldsCols['downloadlink']['status'])) {
				print ' <th class="downloadlink_title text-center" ></th>';
			}
			print '</tr>';

			print '</thead>';

			print '<tbody>';
			foreach ($tableItems as $item)
			{
				$object = new Expedition($db);
				$object->fetch($item->rowid);
				$object->fetchObjectLinked();

				load_last_main_doc($object);
				$dowloadUrl = $context->getRootUrl().'script/interface.php?action=downloadExpedition&id='.$object->id;

				if (!empty($object->last_main_doc) && is_readable(DOL_DATA_ROOT.'/'.$object->last_main_doc) && is_file(DOL_DATA_ROOT.'/'.$object->last_main_doc)){
					$viewLink = '<a href="'.$dowloadUrl.'" target="_blank" >'.$object->ref.'</a>';
					$downloadLink = '<a class="btn btn-xs btn-primary btn-strong" href="'.$dowloadUrl.'&amp;forcedownload=1" target="_blank" ><i class="fa fa-download"></i> '.$langs->trans('Download').'</a>';
				}
				else {
					$viewLink = $object->ref;
					$downloadLink =  $langs->trans('DocumentFileNotAvailable');
				}

				$reftoshow = '';
				$reftosearch = '';
				$linkedobjects = pdf_getLinkedObjects($object, $langs);
				if (! empty($linkedobjects))
				{
					foreach ($linkedobjects as $linkedobject)
					{
						if (!empty($reftoshow)){
							$reftoshow.= ', ';
							$reftosearch.= ' ';
						}
						$reftoshow.= $linkedobject["ref_value"]; //$linkedobject["ref_title"].' : '.
						$reftosearch.= $linkedobject["ref_value"];
					}
				}
				print '<tr>';
				if (!empty($TFieldsCols['ref']['status'])) {
					print ' <td data-search="' . $object->ref . '" data-order="' . $object->ref . '"  >' . $viewLink . '</td>';
				}

				$total_more_fields = 0;
				if (!empty($TOther_fields)) {
					foreach ($TOther_fields as $field) {
						if ($field === 'ref_client' && !isset($object->field)) $field = 'ref_customer';
						if (property_exists('Expedition', $field)) {
							$total_more_fields+=1;
							if ($field =='shipping_method_id') {
								$code = $langs->getLabelFromKey($db, $object->shipping_method_id, 'c_shipment_mode', 'rowid', 'code');
								$field_label = $langs->trans("SendingMethod" . strtoupper($code));
								print ' <td class="'.$field.'_value" data-search="' . strip_tags($field_label) . '" data-order="' . strip_tags($field_label) . '" >' . $field_label . '</td>';
							} else {
								print ' <td class="'.$field.'_value" data-search="' . strip_tags($object->{$field}) . '" data-order="' . strip_tags($object->{$field}) . '" >' . $object->{$field} . '</td>';
							}
						}
						elseif (strstr($field, 'linked')) {
							$Tfield_parts = explode('-', $field);

							$linkedobject_class=$Tfield_parts[1];
							$linkedobject_field=$Tfield_parts[2];
							$linkedobject_field_type=$Tfield_parts[3];

							$TLinkedObjects = $object->linkedObjects[$linkedobject_class];

							print ' <td class="'.$field.'_value" >';
							if (!empty($TLinkedObjects)) {
								foreach ($TLinkedObjects as $id=>$objectlinked) {
									if ($linkedobject_field_type == 'timestamp') print dol_print_date($objectlinked->{$linkedobject_field}). ' ';
									else print $objectlinked->{$linkedobject_field} . ' ';
								}
							}
							print '</td>';
						}
					}
				}

				if (!empty($TFieldsCols['reftoshow']['status'])) {
					print ' <td class="reftoshow_value data-search="' . $reftosearch . '" data-order="' . $reftosearch . '"  >' . $reftoshow . '</td>';
				}
				if (!empty($TFieldsCols['delivery_date']['status'])) {
					print ' <td data-search="' . dol_print_date($object->date_delivery) . '" data-order="' . $object->date_delivery . '" >' . dol_print_date($object->date_delivery) . '</td>';
				}
				if (!empty($TFieldsCols['status']['status'])) {
					print ' <td class="statut_value text-center" >' . $object->getLibStatut(0) . '</td>';
				}
				if (!empty($TFieldsCols['downloadlink']['status'])) {
					print ' <td class="downloadlink_value text-right" >' . $downloadLink . '</td>';
				}
				print '</tr>';
			}
			print '</tbody>';

			print '</table>';
			?>
			<script type="text/javascript" >
				$(document).ready(function(){
					//$("#expedition-list").DataTable(<?php //echo json_encode($dataTableConf) ?>//);
					$("#expedition-list").DataTable({
						"language": {
							"url": "<?php print $context->getRootUrl(); ?>vendor/data-tables/french.json"
						},
						"order": [[<?php echo ($total_more_fields + 2); ?>, 'desc']],

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
