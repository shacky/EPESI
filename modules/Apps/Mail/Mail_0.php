<?php
/**
 * Simple mail client
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package apps-mail
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Mail extends Module {
	private $lang;
	
	public function construct() {
		$this->lang = $this->init_module('Base/Lang');
	}

	////////////////////////////////////////////////////////////
	//account management
	public function account_manager() {
		$gb = $this->init_module('Utils/GenericBrowser',null,'accounts');
		$ret = $gb->query_order_limit('SELECT id,mail FROM apps_mail_accounts WHERE user_login_id='.Base_UserCommon::get_my_user_id(),'SELECT count(mail) FROM apps_mail_accounts WHERE user_login_id='.Base_UserCommon::get_my_user_id());
		$gb->set_table_columns(array(
			array('name'=>$this->lang->t('Mail'), 'order'=>'mail')
				));
		while($row=$ret->FetchRow()) {
			$r = & $gb->get_new_row();
			$r->add_data($row['mail']);
			$r->add_action($this->create_callback_href(array($this,'account'),array($row['id'],'edit')),'Edit');
			$r->add_action($this->create_callback_href(array($this,'account'),array($row['id'],'view')),'View');
			$r->add_action($this->create_confirm_callback_href($this->lang->ht('Are you sure?'),array($this,'delete_account'),$row['id']),'Delete');
		}
		$this->display_module($gb);
		Base_ActionBarCommon::add('add','New account',$this->create_callback_href(array($this,'account'),array(null,'new')));
	}
	
	public function account($id,$action='view') {
		if($this->is_back()) return false;

		$f = $this->init_module('Libs/QuickForm');

		$defaults=null;
		if($action!='new') {
			$ret = DB::Execute('SELECT * FROM apps_mail_accounts WHERE id=%d',array($id));
			$defaults = $ret->FetchRow();
		}

		$cols = array(
				array('name'=>'header','label'=>$this->lang->t(ucwords($action).' account'),'type'=>'header'),
				array('name'=>'mail','label'=>$this->lang->t('Mail address'),'rule'=>array(array('type'=>'email','message'=>$this->lang->t('This isn\'t valid e-mail address')))),
				array('name'=>'login','label'=>$this->lang->t('Login')),
				array('name'=>'password','label'=>$this->lang->t('Password'),'type'=>'password'),
				
				array('name'=>'in_header','label'=>$this->lang->t('Incoming mail'),'type'=>'header'),
				array('name'=>'incoming_protocol','label'=>$this->lang->t('Incoming protocol'),'type'=>'select','values'=>array(0=>'POP3',1=>'IMAP'), 'default'=>0,'param'=>array('onChange'=>'if(this.value==1)this.form.pop3_method.disabled=true;else this.form.pop3_method.disabled=false;')),
				array('name'=>'incoming_server','label'=>$this->lang->t('Incoming server address')),
				array('name'=>'incoming_ssl','label'=>$this->lang->t('Receive with SSL')),
				array('name'=>'pop3_method','label'=>$this->lang->t('POP3 authorization method'),'type'=>'select','values'=>array('auto'=>'Automatic', 'CRAM-MD5'=>'CRAM-MD5', 'APOP'=>'APOP', 'PLAIN'=>'PLAIN', 'LOGIN'=>'LOGIN', 'USER'=>'USER'), 'default'=>'auto', 'param'=>((isset($defaults) && $defaults['incoming_protocol'])?array('disabled'=>0):null)),

				array('name'=>'out_header','label'=>$this->lang->t('Outgoing mail'),'type'=>'header'),
				array('name'=>'smtp_server','label'=>$this->lang->t('SMTP server address')),
				array('name'=>'smtp_auth','label'=>$this->lang->t('SMTP authorization required')),
				array('name'=>'smtp_ssl','label'=>$this->lang->t('Send with SSL'))
			);
		
		$f->add_table('apps_mail_accounts',$cols);
		$f->setDefaults($defaults);
		
		if($action=='view') {
			Base_ActionBarCommon::add('edit','Edit',$this->create_callback_href(array($this,'account'),array($id,'edit')));
			$f->freeze();
		} else {
			$f->addElement('submit',null,'Save','style="display:none"'); //provide on ENTER submit event
			if($f->validate()) {
				$values = $f->exportValues();
				$dbup = array('id'=>$id, 'user_login_id'=>Base_UserCommon::get_my_user_id());
				foreach($cols as $v)
					if(isset($values[$v['name']]))
						$dbup[$v['name']] = DB::qstr($values[$v['name']]);
				DB::Replace('apps_mail_accounts', $dbup, array('id','user_login_id'), true);
				return false;	
			}
			Base_ActionBarCommon::add('save','Save',' href="javascript:void(0)" onClick="'.addcslashes($f->get_submit_form_js(),'"').'"');
		}
		$f->display();

		Base_ActionBarCommon::add('back','Back',$this->create_back_href());

		return true;
	}

	public function delete_account($id){
		DB::Execute('DELETE FROM apps_mail_accounts WHERE id=%d',array($id));
	}
	

	//////////////////////////////////////////////////////////////////
	//applet	
	public function applet($conf) {
		$gb = $this->init_module('Utils/GenericBrowser',null,'applet');
		$gb->set_table_columns(array(
			array('name'=>$this->lang->t('Mail')),
			array('name'=>$this->lang->t('Messages'))
				));
		foreach($conf as $id=>$on) 
			if($on) {
				$account = DB::GetAll('SELECT * FROM apps_mail_accounts WHERE id=%d',array($id));
				$account = $account[0];
				$r = & $gb->get_new_row();
				$r->add_data($account['mail'],$account['num_msgs']);
				$r->add_action($this->create_callback_href(array($this,'applet_update_num_of_msgs'),$id),'Update');
			}
 		$this->display_module($gb,array(true),'automatic_display');
	}
	
	public function applet_update_num_of_msgs($id) {
		$ret = Apps_MailCommon::update_num_of_msgs($id);
		if(is_string($ret)) {
			if($ret=='')
				Epesi::alert($this->lang->ht('Unknown authorization error'));
			else
				Epesi::alert($ret);
		}
		return false;
	}
}

?>