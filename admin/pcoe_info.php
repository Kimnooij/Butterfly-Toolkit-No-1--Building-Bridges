<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

class Pcoe_Helper_Info
{	
	function print_pcoe_info()
	{
		$params = Pcoe_Helper_Info::get_tabs_params();
		Pcoe_Helper_Info::create_tabs($params);
		//Pcoe_Helper_Info::print_pcoe_link();	
	}
	
	function print_installation_report($msg)
	{
		$params = Pcoe_Helper_Info::get_tabs_params($msg);
		Pcoe_Helper_Info::create_tabs($params);	
	}
	
	function get_tabs_params($log_msg=null)
	{
		
		$about_msg = Pcoe_Helper_Info::get_about_msg();
		$comment_msg = Pcoe_Helper_Info::get_comment_msg();
		$note_msg = Pcoe_Helper_Info::get_note_msg();
		$copyright_msg = Pcoe_Helper_Info::get_copyright_msg();
		
		$tab_params = array();
		$tab_params = array(
								array('name'=>'About', 'content'=>$about_msg),
								array('name'=>'Copyrights', 'content'=>$copyright_msg),
								array('name'=>'Comments', 'content'=>$comment_msg),
								array('name'=>'Note', 'content'=>$note_msg),
						);
		
		if($log_msg)
		{			
			$tab_params[] =	array('name'=>'Log', 'content'=>$log_msg);
		}
			
		return $tab_params;
	}
	

	function create_tabs($tab_params)
	{
		jimport('joomla.html.pane');
		$pane   =& JPane::getInstance('tabs');
				
		if($tab_params)
		{
			echo "<div style='width:80%'>";
			echo $pane->startPane("maintab");
			
			$i = 1;	
			$style = "style='padding:15px;background-color:#EFF9FF;color:#535D6B;font-size:12px;font-family:Lucida Grande,Lucida Sans Unicode,Arial,Helvetica,sans-serif'";
			foreach($tab_params as $param)
			{			
				echo $pane->startPanel( $param['name'], "tab_$i" );
					echo "<div $style>";
					echo $param['content'];	
					echo "</div>";
				echo $pane->endPanel();
				$i++;
			}
			echo $pane->endPane();
			echo "</div>";
		}	
	}
	
	function print_pcoe_link($msg='Launch PCOE')
	{
		$link = JPATH_ADMINISTRTOR."/components/com_pcoe";
		$style = "style='padding-top:20px;text-align:right;width:80%'";
		echo "<div $style>
				<a href='index.php?option=com_pcoe'>
					<span>$msg</span>
				</a>
			</div>";
	}

	function get_about_msg()
	{
		$msg = "Congratulations on choosing 'Positive Chain of Events' as your reporting mapping component for Joomla. This Joomla component is a development of Butterfly Works based on the original concept and tooling of Ushahidi crisis reporting tool <a href='http://ushahidi.com' target='_blank' >www.ushahidi.com</a>.
	Butterfly Works developed this Joomla component in order to facilitate more people making use of this approach of crowd sourcing and mapping any positive chain of events in their community or issue space. We wish you luck with the component and would be happy to hear where and how you have used it pcoe@butterflyworks.org.
	<br /><br />
	We say a big thank you and express our appreciation to the development team, the original Ushahidi team, Andric van Es, Butterfly Works team and Erik Sankuru,  Sankuru Development Team.
	<br /><br />
	Positive Chain of Evens component version: 1.0.0 for Joomla 1.5 Native. For all issues with this PCOE version, contact pcoe@butterflyworks.org.
	";

		return $msg;
	}

	function get_comment_msg()
	{
		$msg = "This version is Developed by: Butterfly Works <a href='http://butterflyworks.org' target='_blank' >www.butterflyworks.org</a> and Sankuru Development Team <a href='http://sankuru.biz' target='_blank' >www.sankuru.biz</a> in January 2010 with the knowledge of and permission of Ushahid <a href='http://ushahidi.com' target='_blank' >www.ushahidi.com</a> and approved for distribution.
	For all issues with this PCOE version, contact pcoe@butterflyworks.org.";

		return $msg;
	}

	function get_note_msg()
	{
		$msg = "PCOE requires the use of a Google map API key. To obtain the key please visit <a href='http://code.google.com/apis/maps/signup.html' target='_blank' >http://code.google.com/apis/maps/signup.html</a>
	and copy the key in the pcoe component  go to > admin > settings > map";

		return $msg;
	}

	function get_copyright_msg()
	{
		$msg = "Original Copyright (C) 2010 Butterfly Works <a href='http://butterflyworks.org' target='_blank' >www.butterflyworks.org.</a>
	Distributed under the terms of the GNU General Public License. This software may be used without warranty provided and copyright
	statements are left intact.";

		return $msg;
	}		
}
?>
