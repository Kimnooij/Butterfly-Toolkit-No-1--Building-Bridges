<?php
function com_pcoe_mei_uninstall()
{
	
	$main_component_id=JRequest::getInt('eid');
	$children=MEIHelper_Uninstall::get_children_extension($main_component_id);
	foreach($children as $child)
	{
		$installer =new JInstaller();
		switch($child['type'])
		{
			case 'component' :
			case 'module':
			case 'plugin':
			case 'template':					
				$type=$child['type'];
				$id=$child['id'];
				if($child['type']=='template')
					$id=$child['name'];
				
				$clientId=$child['clientid'];
				//echo $type . ' ' . $id . ' ' .$clientId . '<br>';
				$result	= $installer->uninstall($type, $id, $clientId );
				break;					
			case 'language':
				$tag=$child['tag'];					
				$language_folder = JPATH_SITE .DS. 'language'.DS.$tag;
				if($child['clientid']==1)
					$language_folder = JPATH_ADMINISTRATOR .DS.'language'.DS.$tag;
				//echo $type . ' ' . $language_folder . ' ' .$clientId . '<br>';
				$result = $installer->uninstall( 'language', $language_folder);
				break;
		}
	echo ucfirst(($child['type'])) . " '" . $child['name'] . "' successfully uninstalled.<br />";		
	}
	/*-------- Drop Table --------*/
	$db=JFactory::getDBO();
	$queries=array();
	$queries[]="DROP TABLE IF  EXISTS `#__pcoe_mei_extensions`";	
	foreach($queries as $query)
	{
		$db->setQuery($query);
		$db->query();		
	}
	/*------- End Drop Table ---------*/
	return true;
}
com_pcoe_mei_uninstall();
class MEIHelper_Uninstall
{
	function get_children_extension($id)
	{
		$array_extensions=array();
		$db=JFactory::getDBO();
		$q="SELECT * 
				FROM #__pcoe_mei_extensions
				Where #__pcoe_mei_extensions.main_id={$id}";
		$db->setQuery($q);
		$children=$db->loadObjectList();
		foreach($children as $child)
		{
			$extension_id=$child->extension_id;
			$name=$child->name;
			$type=$child->type;
			$extension=array();
			$extension['id']=$child->extension_id;
			$extension['clientid']='0';
			if($type=='component')
			{
				$row=MEIHelper_Uninstall::get_component_info($extension_id);
				if($row!=null)
				{
					$extension['name']=$row->admin_menu_alt;
					$extension['version']=$row->version;
					$extension['date']=$row->creationdate;
					$extension['author']=$row->author;
					$extension['type']=$type;
					
					$array_extensions[]=$extension;
				}				
				
			}
			
			if($type=='module')
			{
				$row=MEIHelper_Uninstall::get_module_info($extension_id);
				if($row!=null)
				{
					$extension['name']=$row->name;
					$extension['version']=$row->version;
					$extension['date']=$row->creationdate;
					$extension['author']=$row->author;
					$extension['type']=$type;
					$array_extensions[]=$extension;
				}
			}	
			
			if($type=='plugin')
			{
				$row=MEIHelper_Uninstall::get_plugin_info($extension_id);
				if($row!=null)
				{
					$extension['name']=$row->name;
					$extension['version']=$row->version;
					$extension['date']=$row->creationdate;
					$extension['author']=$row->author;
					$extension['type']=$type;
					$array_extensions[]=$extension;
				}
			}			
			if($type=='language')
			{
				$extension['tag']=$child->name;
				$row=MEIHelper_Uninstall::get_language_info($name,true);
				if($row!=null)
				{
					$extension['name']=$row->name;
					$extension['version']=$row->version;
					$extension['date']=$row->creationdate;
					$extension['author']=$row->author;
					$extension['type']=$type;
					$extension['clientid']='0';					
					$array_extensions[]=$extension;
				}
				$row=MEIHelper_Uninstall::get_language_info($name,false);
				if($row!=null)
				{
					$extension['name']=$row->name;
					$extension['version']=$row->version;
					$extension['date']=$row->creationdate;
					$extension['author']=$row->author;
					$extension['type']=$type;
					$extension['clientid']='1';
					$array_extensions[]=$extension;
				}
			}
			
			if($type=='template')
			{
				$row=MEIHelper_Uninstall::get_template_info($name);
				if($row!=null)
				{
					$extension['name']=$row->name;
					$extension['version']=$row->version;
					$extension['date']=$row->creationdate;
					$extension['author']=$row->author;
					$extension['type']=$type;
					$extension['clientid']='0';
					$array_extensions[]=$extension;
				}
				$row=MEIHelper_Uninstall::get_template_info($name,false);
				if($row!=null)
				{
					$extension['name']=$row->name;
					$extension['version']=$row->version;
					$extension['date']=$row->creationdate;
					$extension['author']=$row->author;
					$extension['type']=$type;
					$extension['clientid']='1';
					$array_extensions[]=$extension;
				}
			}		
			
		}
		return $array_extensions;
	}
	function get_component_info($id)
	{
		$db=JFactory::getDBO();
		$query = 'SELECT *' .
				' FROM #__components' .
				' WHERE parent = 0 AND id='. $id;
		$db->setQuery($query);
		$row = $db->loadObject();
		
		/* Get the component base directory */
		$adminDir = JPATH_ADMINISTRATOR .DS. 'components';
		$siteDir = JPATH_SITE .DS. 'components';
		
		 /* Get the component folder and list of xml files in folder */
		$folder = $adminDir.DS.$row->option;
		if (JFolder::exists($folder)) {
			$xmlFilesInDir = JFolder::files($folder, '.xml$');
		} else {
			$folder = $siteDir.DS.$row->option;
			if (JFolder::exists($folder)) {
				$xmlFilesInDir = JFolder::files($folder, '.xml$');
			} else {
				$xmlFilesInDir = null;
			}
		}

		if (count($xmlFilesInDir))
		{
			foreach ($xmlFilesInDir as $xmlfile)
			{
				if ($data = JApplicationHelper::parseXMLInstallFile($folder.DS.$xmlfile)) {
					foreach($data as $key => $value) {
						$row->$key = $value;
					}
				}
				$row->jname = JString::strtolower(str_replace(" ", "_", $row->name));
			}
		}
		return ($row);
	}
	function get_module_info($mid)
	{
		$db=JFactory::getDBO();
		$query = 'SELECT id, module, client_id, title, iscore' .
				' FROM #__modules' .
				' WHERE id='.$mid;
			
		$db->setQuery($query);
		$row = $db->loadObject();
		
		// path to module directory
		if ($row->client_id == "1") {
			$moduleBaseDir = JPATH_ADMINISTRATOR.DS."modules";
		} else {
			$moduleBaseDir = JPATH_SITE.DS."modules";
		}

		// xml file for module
		$xmlfile = $moduleBaseDir . DS . $row->module .DS. $row->module.".xml";

		if (file_exists($xmlfile))
		{
			if ($data = JApplicationHelper::parseXMLInstallFile($xmlfile)) {
				foreach($data as $key => $value) {
					$row->$key = $value;
				}
			}
		}
		return $row;
	}
	function get_plugin_info($mid)
	{
		$db=JFactory::getDBO();
		$query = 'SELECT id, name, folder, element, client_id, iscore' .
				' FROM #__plugins' .
				' WHERE id=' . $mid . 
				' ORDER BY iscore, folder, name';
		$db->setQuery($query);
		$row = $db->loadObject();

		// Get the plugin base path
		$baseDir = JPATH_ROOT.DS.'plugins';

		
		// Get the plugin xml file
			$xmlfile = $baseDir.DS.$row->folder.DS.$row->element.".xml";

			if (file_exists($xmlfile)) {
				if ($data = JApplicationHelper::parseXMLInstallFile($xmlfile)) {
					foreach($data as $key => $value)
					{
						$row->$key = $value;
					}
				}
			}
		return $row;
	}
	function get_language_info($tag,$front=true)
	{
		
		// Get the plugin base path
		$baseDir = JPATH_ROOT.DS.'plugins';

		$language_folder = JPATH_ADMINISTRATOR .DS.'language'.DS.$tag;
		if($front==true)
			$language_folder = JPATH_SITE .DS. 'language'.DS.$tag;
		// Get the plugin xml file
		
		if(JFolder::exists($language_folder))
		{
			$files = JFolder::files( $language_folder, '^([-_A-Za-z]*)\.xml$' );
			foreach ($files as $file)
			{
				$data = JApplicationHelper::parseXMLLangMetaFile($language_folder.DS.$file);

				$row 			= new StdClass();
				// If we didn't get valid data from the xml file, move on...
				if (!is_array($data)) 
				{
					continue;
				}

				// Populate the row from the xml meta file
				foreach($data as $key => $value) 
				{
					$row->$key = $value;
				}
				$xmlfile = $language_folder.DS.$row->element.".xml";

				if (file_exists($xmlfile)) 
				{
					if ($data = JApplicationHelper::parseXMLInstallFile($xmlfile)) 
					{
						foreach($data as $key => $value)
						{
							$row->$key = $value;
						}
					}
				}
			}
		}
		return $row;
	}
	function get_template_info($template_name,$front=true)
	{		
		$language_folder = JPATH_ADMINISTRATOR .DS.'templates'.DS.$template_name;
		if($front==true)
			$language_folder = JPATH_SITE .DS. 'templates'.DS.$template_name;
		// Get the plugin xml file
		if(JFolder::exists($language_folder))
		{
			$files = JFolder::files( $language_folder, '^([-_A-Za-z]*)\.xml$' );
			foreach ($files as $file)
			{
				$data = JApplicationHelper::parseXMLInstallFile($language_folder.DS.$file);
				foreach($data as $key => $value)
				{
					$row->$key = $value;
				}				
			}
		}
		return $row;
	}
}
?>