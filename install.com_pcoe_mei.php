<?php
function com_pcoe_mei_install()
{
?>
	<div id='pcoe_msg' style='padding-top:15px;display:'>
		<h2>Installing the Positive Chain of Events Components may take a while. Please be patient...</h2>
	</div>
<?php	
	ob_start();
	/*-------- Create Table --------*/
	
	$db=JFactory::getDBO();
	$queries=array();
	$queries[]="CREATE TABLE IF NOT EXISTS `#__pcoe_mei_extensions` (
				`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
				`main_id` INT NULL,
				`extension_id` INT NULL,
				`name` VARCHAR(50) NULL,
				`type` VARCHAR(50) NULL 				 
				);";	
	$queries[]="DELETE FROM `#__pcoe_mei_extensions`;";	
	foreach($queries as $query)
	{
		$db->setQuery($query);
		$db->query();		
	}
	/*------- End Create Table ---------*/
		
	$installer =& JInstaller::getInstance();
	$manifest_xml=$installer->_manifest->document;
	$source=$installer->getPath('source');
	$extension_tag=$manifest_xml->getElementByPath('extension');
	$multi_extension_package=false;
	if($extension_tag!=null)
	{
		$extension_folder=$extension_tag->attributes('folder');	
		$multi_extension_package=(count($extension_tag->children())>0);
	}
	$main_name=MEIHelper_Install::get_package_name($installer);
	$main_installer=$installer;
	if($multi_extension_package) // Multi Extensions Zip
	{
		$main_id=MEIHelper_Install::get_extension_id($installer);		
		$extension_tag=$manifest_xml->getElementByPath('extension');
		$extension_folder=$extension_tag->attributes('folder');
		$extension_message='';
		foreach($extension_tag->children() as $child)
		{
			//$installer_item=new JInstaller();
			$installer_item=& JInstaller::getInstance();
			$package_path=$source.DS . $extension_folder . DS . $child->data();
			$package_zip = JInstallerHelper::unpack($package_path);		
			MEIHelper_Install::install_package($package_zip);
			
			/*-------- Save extension to mei extension table -----------------*/
			$manifest_xml=$installer_item->_manifest->document;			
			$type = $manifest_xml->attributes('type');
			$name=MEIHelper_Install::get_package_name($installer_item);
			$extension_id=	MEIHelper_Install::get_extension_id($installer_item);
			MEIHelper_Install::save_child_extension($main_id,$extension_id,$type,$name);			
			
		}
		$installer->setPath('source', $source);
		$installer->_findManifest();
		$installer->setPath('extension_site', JPath::clean(JPATH_SITE.DS."components".DS.strtolower("com_".str_replace(" ", "", $main_name))));
		$installer->setPath('extension_administrator', JPath::clean(JPATH_ADMINISTRATOR.DS."components".DS.strtolower("com_".str_replace(" ", "", $main_name))));
	}

	$log_msg = ob_get_contents();
	ob_end_clean();
	
	install_report($log_msg);
	
	return true;
}
com_pcoe_mei_install();
class MEIHelper_Install
{
	function install_package($package)
	{
		global $mainframe;
		$installer =& JInstaller::getInstance();
		$installer->setPath('source', $package['dir']);
		if(!$installer->_findManifest())
		{
			echo 'Manifest Not found';
			return false;
		};		
		
		MEIHelper_Install::_buildAdminMenus($installer);
		
		$manifest_xml=$installer->_manifest->document;
		$type = $manifest_xml->attributes('type');
		$name=MEIHelper_Install::get_package_name($installer);
		
		if (!$installer->install($package['dir'])) {
			// There was an error installing the package
			$msg = ucfirst($type) . " '" . $name . "' installation failed.<br />";
			$result = false;
		} else {
			// Package installed sucessfully
			$msg = ucfirst($type) . " '" . $name . "' installed successfully.<br />";
			$result = true;
		}
		
		if($type=='component')
			{
				$option = strtolower("com_".str_replace(" ", "", $name));
				$custom_function=$option .'_install';
				
				echo 'Check Custom Function ' . $custom_function . '<br />';
				if(function_exists($custom_function))
				{
					$custom_function=' return ' . $custom_function . '();';
					echo 'Run Custom Function ' . $custom_function . '<br />';
					eval($custom_function );
				}
			}
		$mainframe->enqueueMessage($msg);
		
		return true;
	}
	function cleanup_tmp_file($package)
	{
		// Cleanup the install files
		if (!is_file($package['packagefile'])) {
			$config =& JFactory::getConfig();
			$package['packagefile'] = $config->getValue('config.tmp_path').DS.$package['packagefile'];
		}		
		JInstallerHelper::cleanupInstall($package['packagefile'], $package['extractdir']);
	}
	function save_child_extension($main_id,$extension_id,$type,$name)
	{
		$db=JFactory::getDBO();		
		$type=$db->Quote($type);
		$name=$db->Quote($name);
		$q="INSERT INTO `#__pcoe_mei_extensions`( `main_id`, `extension_id`, `name`, `type` )
			VALUES({$main_id},{$extension_id},{$name},{$type})";
		$db->setQuery($q);
		$db->query($q);
	}
	function get_package_name($installer)
	{		
		$name='';
		$manifest_xml=$installer->_manifest->document;			
		$type = $manifest_xml->attributes('type');
		switch($type)
		{			
			case 'component':
			case 'template':
				$name_tag=$manifest_xml->getElementByPath('name');
				$name=$name_tag->data();
				break;
			case 'language':
				$name_tag=$manifest_xml->getElementByPath('tag');
				$name=$name_tag->data();
				break;	
			case 'module':
				$element =& $manifest_xml->getElementByPath('files');
				if (is_a($element, 'JSimpleXMLElement') && count($element->children())) 
				{
					$files =& $element->children();
					foreach ($files as $file) 
					{
						if ($file->attributes('module')) 
						{
							$name = $file->attributes('module');
							break;
						}
					}
				}
				break;
			case 'plugin':
				$element =& $manifest_xml->getElementByPath('files');
				if (is_a($element, 'JSimpleXMLElement') && count($element->children())) 
				{
					$files =& $element->children();
					foreach ($files as $file) 
					{
						if ($file->attributes($type)) {
							$name = $file->attributes($type);
							break;
						}
					}
				}
				break;
		}
		return $name;
	}
	function get_extension_id($installer)
	{
		$db=JFactory::getDBO();
		
		$manifest_xml=$installer->_manifest->document;			
		$type = $manifest_xml->attributes('type');
		$name=MEIHelper_Install::get_package_name($installer);
		switch($type)
		{
			case 'component':
				$name='com_'. strtolower(str_replace(' ','',$name));
				$q="SELECT id FROM #__components WHERE `option`='{$name}'";
				$db->setQuery($q);
				return $db->loadResult();
				break;
			case 'module':
				$q="SELECT id FROM #__modules WHERE `module`='{$name}'";
				$db->setQuery($q);
				return $db->loadResult();
				break;
			case 'plugin':
				$folder = $manifest_xml->attributes('group');
				$q="SELECT id FROM #__plugins WHERE `element`='{$name}' AND `folder`='{$folder}'";
				$db->setQuery($q);
				return $db->loadResult();
				break;
		}
		return 0;		
	}
	function _buildAdminMenus($installer)
	{
		$manifest_xml=$installer->_manifest->document;
		if($manifest_xml->attributes('type')!='component')
			return true;
			
		$name=MEIHelper_Install::get_package_name($installer);		
		$adminElement=$manifest_xml->getElementByPath('administration');
		$menuElement = $adminElement->getElementByPath('menu');
		
		
		// Get database connector object
		$db =JFactory::getDBO();

		// Initialize variables
		$option = strtolower("com_".str_replace(" ", "", $name));

		// If a component exists with this option in the table than we don't need to add menus
		// Grab the params for later
		$query = 'SELECT id, params, enabled' .
				' FROM #__components' .
				' WHERE `option` = '.$db->Quote($option) .
				' ORDER BY `parent` ASC';

		$db->setQuery($query);
		$componentrow = $db->loadAssoc(); // will return null on error
		$exists = 0;
		$oldparams = '';

		// Check if menu items exist
		if ($componentrow) {
			// set the value of exists to be the value of the old id
			$exists = $componentrow['id'];
			// and set the old params
			$oldparams = $componentrow['params'];
			// and old enabled
			$oldenabled = $componentrow['enabled'];

			// Don't do anything if overwrite has not been enabled
			if ( ! $installer->getOverwrite() ) {
				return true;
			}

			// Remove existing menu items if overwrite has been enabled
			if ( $option ) {

				$sql = 'DELETE FROM #__components WHERE `option` = '.$db->Quote($option);

				$db->setQuery($sql);
				if (!$db->query()) {
					JError::raiseWarning(100, JText::_('Component').' '.JText::_('Install').': '.$db->stderr(true));
				}
			}
		}

		// Ok, now its time to handle the menus.  Start with the component root menu, then handle submenus.
	
		if (is_a($menuElement, 'JSimpleXMLElement')) {

			$db_name = $menuElement->data();
			$db_link = "option=".$option;
			$db_menuid = 0;
			$db_parent = 0;
			$db_admin_menu_link = "option=".$option;
			$db_admin_menu_alt = $menuElement->data();
			$db_option = $option;
			$db_ordering = 0;
			$db_admin_menu_img = ($menuElement->attributes('img')) ? $menuElement->attributes('img') : 'js/ThemeOffice/component.png';
			$db_iscore = 0;
			// use the old params if a previous entry exists
			$db_params = $exists ? $oldparams : $installer->getParams();
			// use the old enabled field if a previous entry exists
			$db_enabled = $exists ? $oldenabled : 1;

			// This works because exists will be zero (autoincr)
			// or the old component id
			if(intval($exists)>0)
				$query = 'INSERT INTO #__components' .
				' VALUES( '.$exists .', '.$db->Quote($db_name).', '.$db->Quote($db_link).', '.(int) $db_menuid.',' .
				' '.(int) $db_parent.', '.$db->Quote($db_admin_menu_link).', '.$db->Quote($db_admin_menu_alt).',' .
				' '.$db->Quote($db_option).', '.(int) $db_ordering.', '.$db->Quote($db_admin_menu_img).',' .
				' '.(int) $db_iscore.', '.$db->Quote($db_params).', '.(int) $db_enabled.' )';
			else
				$query = 'INSERT INTO #__components' .
				'(`name`, `link`, `menuid`, `parent`, `admin_menu_link`, `admin_menu_alt`, `option`, `ordering`, `admin_menu_img`, `iscore`, `params`, `enabled`) ' . 
				' VALUES( '.$db->Quote($db_name).', '.$db->Quote($db_link).', '.(int) $db_menuid.',' .
				' '.(int) $db_parent.', '.$db->Quote($db_admin_menu_link).', '.$db->Quote($db_admin_menu_alt).',' .
				' '.$db->Quote($db_option).', '.(int) $db_ordering.', '.$db->Quote($db_admin_menu_img).',' .
				' '.(int) $db_iscore.', '.$db->Quote($db_params).', '.(int) $db_enabled.' )';
				
			$db->setQuery($query);
			if (!$db->query()) {
				// Install failed, rollback changes
				$installer->parent->abort(JText::_('Component').' '.JText::_('Install').': '.$db->stderr(true));
				return false;
			}
			// save ourselves a call if we don't need it
			$menuid = $exists ? $exists : $db->insertid(); // if there was an existing value, reuse

			/*
			 * Since we have created a menu item, we add it to the installation step stack
			 * so that if we have to rollback the changes we can undo it.
			 */
			$installer->pushStep(array ('type' => 'menu', 'id' => $menuid));
		} else {

			/*
			 * No menu element was specified so lets first see if we have an admin menu entry for this component
			 * if we do.. then we obviously don't want to create one -- we'll just attach sub menus to that one.
			 */
			$query = 'SELECT id' .
					' FROM #__components' .
					' WHERE `option` = '.$db->Quote($option) .
					' AND parent = 0';
			$db->setQuery($query);
			$menuid = $db->loadResult();

			if (!$menuid) {
				// No menu entry, lets just enter a component entry to the table.
				//$db_name = $this->get('name');
				$db_name = $name;
				$db_link = "";
				$db_menuid = 0;
				$db_parent = 0;
				$db_admin_menu_link = "";
				//$db_admin_menu_alt = $this->get('name');
				$db_admin_menu_alt = $name;
				$db_option = $option;
				$db_ordering = 0;
				$db_admin_menu_img = "";
				$db_iscore = 0;
				$db_params = $installer->getParams();
				$db_enabled = 1;

				$query = 'INSERT INTO #__components' .
					' VALUES( NULL, '.$db->Quote($db_name).', '.$db->Quote($db_link).', '.(int) $db_menuid.',' .
					' '.(int) $db_parent.', '.$db->Quote($db_admin_menu_link).', '.$db->Quote($db_admin_menu_alt).',' .
					' '.$db->Quote($db_option).', '.(int) $db_ordering.', '.$db->Quote($db_admin_menu_img).',' .
					' '.(int) $db_iscore.', '.$db->Quote($db_params).', '.(int) $db_enabled.' )';
				$db->setQuery($query);
				if (!$db->query()) {
					// Install failed, rollback changes
					//$this->parent->abort(JText::_('Component').' '.JText::_('Install').': '.$db->stderr(true));
					return false;
				}
				$menuid = $db->insertid();

				/*
				 * Since we have created a menu item, we add it to the installation step stack
				 * so that if we have to rollback the changes we can undo it.
				 */
				$installer->pushStep(array ('type' => 'menu', 'id' => $menuid));
			}
		}
		
		/*
		 * Process SubMenus
		 */

		// Initialize submenu ordering value
		$ordering = 0;
		$submenu = $adminElement->getElementByPath('submenu');
		if (!is_a($submenu, 'JSimpleXMLElement') || !count($submenu->children())) {
			return true;
		}
		foreach ($submenu->children() as $child)
		{
			if (is_a($child, 'JSimpleXMLElement') && $child->name() == 'menu') {

				$com =& JTable::getInstance('component');
				$com->name = $child->data();
				$com->link = '';
				$com->menuid = 0;
				$com->parent = $menuid;
				$com->iscore = 0;
				$com->admin_menu_alt = $child->data();
				$com->option = $option;
				$com->ordering = $ordering ++;

				// Set the sub menu link
				if ($child->attributes("link")) {
					$com->admin_menu_link = str_replace('&amp;', '&', $child->attributes("link"));
				} else {
					$request = array();
					if ($child->attributes('act')) {
						$request[] = 'act='.$child->attributes('act');
					}
					if ($child->attributes('task')) {
						$request[] = 'task='.$child->attributes('task');
					}
					if ($child->attributes('controller')) {
						$request[] = 'controller='.$child->attributes('controller');
					}
					if ($child->attributes('view')) {
						$request[] = 'view='.$child->attributes('view');
					}
					if ($child->attributes('layout')) {
						$request[] = 'layout='.$child->attributes('layout');
					}
					if ($child->attributes('sub')) {
						$request[] = 'sub='.$child->attributes('sub');
					}
					$qstring = (count($request)) ? '&'.implode('&',$request) : '';
					$com->admin_menu_link = "option=".$option.$qstring;
				}

				// Set the sub menu image
				if ($child->attributes("img")) {
					$com->admin_menu_img = $child->attributes("img");
				} else {
					$com->admin_menu_img = "js/ThemeOffice/component.png";
				}

				// Store the submenu
				if (!$com->store()) {
					// Install failed, rollback changes
					$this->parent->abort(JText::_('Component').' '.JText::_('Install').': '.JText::_('SQL Error')." ".$db->stderr(true));
					return false;
				}

				/*
				 * Since we have created a menu item, we add it to the installation step stack
				 * so that if we have to rollback the changes we can undo it.
				 */
				$installer->pushStep(array ('type' => 'menu', 'id' => $com->id));
			}
		}
	}	
}

function install_report($log_msg)
{
	require_once(JPATH_ADMINISTRATOR.'/components/com_pcoe_mei/pcoe_info.php');
?>
<script>
document.getElementById('pcoe_msg').style.display='none';
</script>

<div style='padding-top:15px;padding-bottom:15px'>
		<h2>Please remember to publish the PCOE modules</h2>
	</div>
<?php
	Pcoe_Helper_Info::print_installation_report($log_msg);				
}

?>
