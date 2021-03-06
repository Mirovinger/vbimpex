<?php if (!defined('IDIR')) { die; }
/*======================================================================*\
|| ####################################################################
|| # vBulletin Impex
|| # ----------------------------------------------------------------
|| # All PHP code in this file is Copyright 2000-2014 vBulletin Solutions Inc.
|| # This code is made available under the Modified BSD License -- see license.txt
|| # http://www.vbulletin.com 
|| ####################################################################
\*======================================================================*/
/**
* ikon_mysql_004 Import User module
*
* @package			ImpEx.ikon_mysql
*
*/
class ikon_mysql_004 extends ikon_mysql_000
{
	var $_version 		= '0.0.1';
	var $_dependent 	= '001';
	var $_modulestring 	= 'Import User';


	function ikon_mysql_004()
	{
		// Constructor
	}


	function init(&$sessionobject, &$displayobject, &$Db_target, &$Db_source)
	{
		if ($this->check_order($sessionobject,$this->_dependent))
		{
			if ($this->_restart)
			{
				if ($this->restart($sessionobject, $displayobject, $Db_target, $Db_source,'clear_imported_users'))
				{
					$displayobject->display_now('<h4>Imported users have been cleared</h4>');
					$this->_restart = true;
				}
				else
				{
					$sessionobject->add_error('fatal',
											 $this->_modulestring,
											 get_class($this) . '::restart failed , clear_imported_users','Check database permissions');
				}
			}


			// Start up the table
			$displayobject->update_basic('title','Import User');
			$displayobject->update_html($displayobject->do_form_header('index',substr(get_class($this) , -3)));
			$displayobject->update_html($displayobject->make_hidden_code(substr(get_class($this) , -3),'WORKING'));
			$displayobject->update_html($displayobject->make_hidden_code('import_user','working'));
			$displayobject->update_html($displayobject->make_table_header($this->_modulestring));


			// Ask some questions
			$displayobject->update_html($displayobject->make_input_code('Users to import per cycle (must be greater than 1)','userperpage', 2000));
			$displayobject->update_html($displayobject->make_yesno_code("Would you like to import the custom avatars ","importcustomavatars",0));
			$displayobject->update_html($displayobject->make_input_code('Full Path to avatar folder (if above is yes)','avatarspath',$sessionobject->get_session_var('avatarspath'),1,60));
			$displayobject->update_html($displayobject->make_yesno_code("Would you like to associated imported users with existing users if the email address matches ?","email_match",0));

			// End the table
			$displayobject->update_html($displayobject->do_form_footer('Continue','Reset'));


			// Reset/Setup counters for this
			$sessionobject->add_session_var(substr(get_class($this) , -3) . '_objects_done', '0');
			$sessionobject->add_session_var(substr(get_class($this) , -3) . '_objects_failed', '0');
			$sessionobject->add_session_var('userstartat','0');
			$sessionobject->add_session_var('userdone','0');
		}
		else
		{
			// Dependant has not been run
			$displayobject->update_html($displayobject->do_form_header('index',''));
			$displayobject->update_html($displayobject->make_description('<p>This module is dependent on <i><b>' . $sessionobject->get_module_title($this->_dependent) . '</b></i> cannot run until that is complete.'));
			$displayobject->update_html($displayobject->do_form_footer('Continue',''));
			$sessionobject->set_session_var(substr(get_class($this) , -3),'FALSE');
			$sessionobject->set_session_var('module','000');
		}
	}


	function resume(&$sessionobject, &$displayobject, &$Db_target, &$Db_source)
	{
		// Set up working variables.
		$displayobject->update_basic('displaymodules','FALSE');
		$target_database_type	= $sessionobject->get_session_var('targetdatabasetype');
		$target_table_prefix	= $sessionobject->get_session_var('targettableprefix');
		$source_database_type	= $sessionobject->get_session_var('sourcedatabasetype');
		$source_table_prefix	= $sessionobject->get_session_var('sourcetableprefix');


		// Per page vars
		$user_start_at			= $sessionobject->get_session_var('userstartat');
		$user_per_page			= $sessionobject->get_session_var('userperpage');
		$class_num				= substr(get_class($this) , -3);


		// Start the timing
		if(!$sessionobject->get_session_var($class_num . '_start'))
		{
			$sessionobject->timing($class_num ,'start' ,$sessionobject->get_session_var('autosubmit'));
		}


		// Get an array of user details
		$user_array 	= $this->get_ikon_mysql_user_details($Db_source, $source_database_type, $source_table_prefix, $user_start_at, $user_per_page);

		$user_group_ids_array = $this->get_imported_group_ids($Db_target, $target_database_type, $target_table_prefix);

		// Display count and pass time
		$displayobject->display_now('<h4>Importing ' . count($user_array) . ' users</h4><p><b>From</b> : ' . $user_start_at . ' ::  <b>To</b> : ' . ($user_start_at + count($user_array)) . '</p>');


		$user_object = new ImpExData($Db_target, $sessionobject, 'user');


		if($sessionobject->get_session_var('importcustomavatars'))
		{
			$path = $sessionobject->get_session_var('avatarspath');

			if($path)
			{
				if(!$path{strlen($path)-1} == '/')
				{
					$path .= '/';
				}
			}
		}


		foreach ($user_array as $user_id => $user_details)
		{
			$try = (phpversion() < '5' ? $user_object : clone($user_object));

			if ($sessionobject->get_session_var('email_match'))
			{
				$try->_auto_email_associate = true;
			}


			// Mandatory
			$try->set_value('mandatory', 'usergroupid',				$user_group_ids_array["$user_details[MEMBER_GROUP]"]);
			$try->set_value('mandatory', 'username',				$user_details['MEMBER_NAME']);
			$try->set_value('mandatory', 'email',					$user_details['MEMBER_EMAIL']);
			$try->set_value('mandatory', 'importuserid',			$user_id);


			// Non Mandatory
			$try->set_value('nonmandatory', 'membergroupids',		'');
			$try->set_value('nonmandatory', 'displaygroupid',		'0');
			$try->_password_md5_already = true;
			$try->set_value('nonmandatory', 'password',				$user_details['MEMBER_PASSWORD']);
			$try->set_value('nonmandatory', 'passworddate',			time());
			$try->set_value('nonmandatory', 'styleid',				'0');
			#$try->set_value('nonmandatory', 'parentemail',			$user_details['parentemail']);
			$try->set_value('nonmandatory', 'homepage',				addslashes($user_details['WEBSITE']));
			$try->set_value('nonmandatory', 'icq',					addslashes($user_details['ICQNUMBER']));
			$try->set_value('nonmandatory', 'aim',					addslashes($user_details['AOLNAME']));
			$try->set_value('nonmandatory', 'yahoo',				addslashes($user_details['YAHOONAME']));
			$try->set_value('nonmandatory', 'showvbcode',			'1');
			$try->set_value('nonmandatory', 'usertitle',			$user_details['MEMBER_TITLE']);
			#$try->set_value('nonmandatory', 'customtitle',			$user_details['customtitle']);
			$try->set_value('nonmandatory', 'joindate',				$user_details['MEMBER_JOINED']);
			#$try->set_value('nonmandatory', 'daysprune',			$user_details['daysprune']);
			$try->set_value('nonmandatory', 'lastvisit',			$user_details['LAST_LOG_IN']);
			$try->set_value('nonmandatory', 'lastactivity',			$user_details['LAST_ACTIVITY']);

			// has post ID
			#$try->set_value('nonmandatory', 'lastpost',			$user_details['lastpost']);

			$try->set_value('nonmandatory', 'posts',				$user_details['MEMBER_POSTS']);
			#$try->set_value('nonmandatory', 'reputation',			$user_details['reputation']);
			#$try->set_value('nonmandatory', 'reputationlevelid',	$user_details['reputationlevelid']);
			$try->set_value('nonmandatory', 'timezoneoffset',		$user_details['TIME_ADJUST']);
			$pm_details = explode('&',$user_details['PM_REMINDER']);
			$try->set_value('nonmandatory', 'pmpopup',				$pm_details[0]);

			#$try->set_value('nonmandatory', 'avatarid',			$user_details['avatarid']);
			#$try->set_value('nonmandatory', 'avatarrevision',		$user_details['avatarrevision']);

			$try->set_value('nonmandatory', 'options',				$this->_default_user_permissions);
			if (!empty($user_details['DAY']) AND !empty($user_details['MONTH']) AND !empty($user_details['YEAR']))
			{
				$try->set_value('nonmandatory', 'birthday',			"$user_details[MONTH]-$user_details[DAY]-$user_details[YEAR]");
				$try->set_value('nonmandatory', 'birthday_search',	"$user_details[YEAR]-$user_details[MONTH]-$user_details[DAY]");
			}
			#$try->set_value('nonmandatory', 'maxposts',			$user_details['maxposts']);
			#$try->set_value('nonmandatory', 'startofweek',			$user_details['startofweek']);
			$try->set_value('nonmandatory', 'ipaddress',			$user_details['MEMBER_IP']);
			#$try->set_value('nonmandatory', 'referrerid',			$user_details['referrerid']);
			#$try->set_value('nonmandatory', 'languageid',			$user_details['languageid']);
			$try->set_value('nonmandatory', 'msn',					addslashes($user_details['MSNNAME']));
			$try->set_value('nonmandatory', 'emailstamp',			'0');
			$try->set_value('nonmandatory', 'threadedmode',			'0');
			// TODO: need an update function after PM's are imported ?
			$try->set_value('nonmandatory', 'pmtotal',				'0');
			#$try->set_value('nonmandatory', 'pmunread',				$user_details['pmunread']);
			$try->set_value('nonmandatory', 'autosubscribe',		'-1');

			#$try->set_value('nonmandatory', 'birthday_search',		$user_details['birthday_search']);

			$try->add_default_value('Interests', 					addslashes($user_details['INTERESTS']));
			$try->add_default_value('Location', 					addslashes($user_details['LOCATION']));
			$try->add_default_value('signature', 					addslashes($this->html_2_bb($user_details['SIGNATURE'])));


			if ($sessionobject->get_session_var('importcustomavatars'))
			{
				if($user_details['MEMBER_AVATAR'])
				{
					if (substr($user_details['MEMBER_AVATAR'], 0,7) == 'http://')
					{
						$try->set_value('nonmandatory', 'avatar', $path . $user_details['MEMBER_AVATAR']);
					}
					else
					{
						$try->set_value('nonmandatory', 'avatar', $path . $user_details['MEMBER_AVATAR']);
					}
				}
			}


			// Check if user object is valid
			if($try->is_valid())
			{
				if($try->import_user($Db_target, $target_database_type, $target_table_prefix))
				{
					$displayobject->display_now('<br /><span class="isucc"><b>' . $try->how_complete() . '%</b></span> :: user -> ' . $user_details['MEMBER_NAME']);
					$sessionobject->add_session_var($class_num . '_objects_done',intval($sessionobject->get_session_var($class_num . '_objects_done')) + 1 );
				}
				else
				{
					$sessionobject->set_session_var($class_num . '_objects_failed',$sessionobject->get_session_var($class_num. '_objects_failed') + 1 );
					$sessionobject->add_error('warning', $this->_modulestring, get_class($this) . '::import_custom_profile_pic failed.', 'Check database permissions and database table');
					$displayobject->display_now("<br />Found avatar user and <b>DID NOT</b> imported to the  {$target_database_type} database");
				}
			}
			else
			{
				$displayobject->display_now("<br />Invalid user object, skipping." . $try->_failedon);
			}
			unset($try);
		}// End resume


		// Check for page end
		if (count($user_array) == 0 OR count($user_array) < $user_per_page)
		{
			$sessionobject->timing($class_num,'stop', $sessionobject->get_session_var('autosubmit'));
			$sessionobject->remove_session_var($class_num . '_start');
			$this->build_user_statistics($Db_target, $target_database_type, $target_table_prefix);

			$displayobject->update_html($displayobject->module_finished($this->_modulestring,
										$sessionobject->return_stats($class_num, '_time_taken'),
										$sessionobject->return_stats($class_num, '_objects_done'),
										$sessionobject->return_stats($class_num, '_objects_failed')
										));


			$sessionobject->set_session_var($class_num ,'FINISHED');
			$sessionobject->set_session_var('import_user','done');
			$sessionobject->set_session_var('module','000');
			$sessionobject->set_session_var('autosubmit','0');
			$displayobject->update_html($displayobject->print_redirect('index.php','1'));
		}


		$sessionobject->set_session_var('userstartat',$user_start_at+$user_per_page);
		$displayobject->update_html($displayobject->print_redirect('index.php'));
	}// End resume
}//End Class
# Autogenerated on : May 27, 2004, 1:49 pm
# By ImpEx-generator 1.0.
/*======================================================================*/
?>
