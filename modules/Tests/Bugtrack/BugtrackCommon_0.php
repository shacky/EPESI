<?php
/**
 * Software Development - Bug Tracking
 * @author jtylek@telaxus.com
 * @copyright jtylek@telaxus.com
 * @license SPL
 * @version 0.1
 * @package apps-bugtrack
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_BugtrackCommon extends ModuleCommon {
/*
    public static function get_bugtrack($id) {
		return Utils_RecordBrowserCommon::get_record('bugtrack', $id);
    }
*/
    
    public static function display_bugtrack($v) {
		return Utils_RecordBrowserCommon::create_linked_label('bugtrack', 'Project Name', $v['id']);
	}
	

    public static function access_bugtrack($action, $param){
		$i = self::Instance();
		switch ($action) {
			case 'add':
			case 'browse':	return $i->acl_check('browse bugtrack');
			case 'view':	static $me;
					if($i->acl_check('view bugtrack')) return true;
					if(!isset($me)) {
						$me = Utils_RecordBrowserCommon::get_records('bugtrack', array('login'=>Acl::get_user()));
						if (is_array($me) && !empty($me)) $me = array_shift($me);
					}
					if ($me) return array('Project Name'=>$me['Project Name']);
					return false;
			case 'edit':	return $i->acl_check('edit bugtrack');
			case 'delete':	return $i->acl_check('delete bugtrack');
		}
		return false;
    }
    
    public static function menu() {
		return array('Projects'=>array('__submenu__'=>1,'Bugtrack'=>array()));
	}
    
    public static function caption() {
		return 'Bugtrack';
	}

	public static function search_format($id) {
		if(!self::Instance()->acl_check('browse bugtrack')) return false;
		$row = Utils_RecordBrowserCommon::get_records('bugtrack',array('id'=>$id));
		if(!$row) return false;
		$row = array_pop($row);
		return Utils_RecordBrowserCommon::record_link_open_tag('bugtrack', $row['id']).Base_LangCommon::ts('Tests_Bugtrack', 'Bug (attachment) #%d, %s', array($row['id'], $row['project_name'])).Utils_RecordBrowserCommon::record_link_close_tag();
	}

/*
	public function admin_caption() {
		return 'Bugtrack';
	}
*/
}

?>