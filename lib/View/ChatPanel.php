<?php

namespace rakesh\apartment;

class View_ChatPanel extends \View{

	public $options = [];
	private $member_lister;
	private $form;
	private $chat_history_lister;

	private $contact_to_id = 0;
	private $contact_to_name = "";
	private $contact_to_image = "";

	private $my_uuid = "";
	public $title = "";
	public $usertyping = false;
	public $hasError = false; /*used for display error properly*/

	function init(){
		parent::init();
		
		if(!@$this->app->apartment->id){
			$this->add('View_Error')->set('first update apartment data');
			return;
		}

		$this->default_img_url = 'websites/'.$this->app->current_website_name.'/www/dist/img/avatar04.png';

		$chat_id = $this->app->stickyGET('chatid');
		$this->chat_type = $chat_type = $this->app->stickyGET('chattype');
		if($chat_id){
			$this->contact_to_id = $chat_id;

			if($chat_type == "group")
				$member_model = $this->add('rakesh\apartment\Model_Group');
			else
				$member_model = $this->add('rakesh\apartment\Model_Member');

			$member_model->addCondition('apartment_id',$this->app->apartment->id)
					->addCondition('status','Active')
					->addCondition('id',$chat_id);
			$member_model->tryLoadAny();
			if(!$member_model->loaded()){
				$this->add('View_Error')->set('record not found');
				$this->hasError = true;
				return;
			} 

			if($chat_type == "group" AND !$member_model->isPermitted($this->app->apartmentmember->id)){
				$this->add('View_Error')->set('You are not found permitted');
				$this->hasError = true;
				return;	
			}
			
			$this->contact_to_image = $member_model['image']?:$this->default_img_url;
			$this->contact_to_name = $member_model['name'];
		}else{
			$this->contact_to_id = $this->app->stickyGET('contact_to_id')?:0;
			$this->contact_to_name = $this->app->stickyGET('contact_to_name')?:"";
			$this->contact_to_image = $this->app->stickyGET('contact_to_image')?:$this->default_img_url;
		}

		if(!$this->hasError){
			$this->addChatHistory();		
		}

		$this->title = 'Chat with '.$this->contact_to_name;

		// update date and count
		$this->app->apartmentmember['chatpanel_last_login_at'] = $this->app->now;
		// $this->app->apartmentmember['chatmessage_read_at'] = $this->add("rakesh\apartment\Model_MessageSent")
		// 			->addCondition([['from_id',$this->app->apartmentmember->id],['from_id',$this->contact_to_id]])
		// 			->addCondition([['to_id',$this->app->apartmentmember->id],['to_id',$this->contact_to_id]])
		// 			->setOrder('id','desc')
		// 			->setLimit(1)->tryLoadAny()->id;
		$this->app->apartmentmember->save()->reload();
	}

	function addForm(){

		$this->form = $form = $this->add('Form',null,'message_form');
		$msg_field = $form->addField('Line','message');
		$msg_field->setAttr('PlaceHolder','Type a message')
				->addClass('msginputbox')
				->validate('required');
		$this->send_button = $this->add('Button',null,'send_button')->set(' ')->setIcon('fa fa fa-send')->addClass('btn btn-primary');
	}

	function addChatHistory(){
		// left
		// right
		$this->chat_history_lister = $lister = $this->add('CompleteLister',null,'ap_chat_lister',['view\chatpanel2','ap_chat_lister']);

		$lister->addHook('formatRow',function($l){
			if($l->model['from_id'] == $this->app->apartmentmember->id){
				$l->current_row_html['chat_direction'] = "left";
				$l->current_row_html['member_name'] = $l->model['from'];
			}else{
				$l->current_row_html['chat_direction'] = "right";
				$l->current_row_html['member_name'] = $l->model['from'];
			}

			if($l->model['image']){
				$l->current_row_html['profile_image'] = $l->model['image'];
			}else{
				$l->current_row_html['profile_image'] = $this->default_img_url;
			}

			if($l->model['created_at']){
				// $date = $this->add('xepan\base\xDate');
				// $diff = $date->diff($this->app->now,$l->model['created_at']);
				// $l->current_row_html['send_date'] = $diff;
				$l->current_row_html['send_date'] = date('M d H:i a',strtotime($l->model['created_at']));
			}else
				$l->current_row_html['send_date'] = "";

		});

		// $lister->addClass('ap-chat-message-trigger-reload');
		$lister->js('reload')->reload();

		if($this->contact_to_id){
			$this->addForm();
		}
	}

	function recursiveRender(){
		if($this->hasError){
			$this->js('click')->univ()->redirect($this->app->url('dashboard',['mode'=>'chat']))->_selector('.backtochatmember');
			parent::recursiveRender();
			return;
		}

		$chat_history_model = $this->add('rakesh\apartment\Model_MessageSent');
		$chat_history_model->addCondition('related_id',$this->app->apartment->id);

		if($this->chat_type == "group"){
			$chat_history_model->addCondition('to_id',$this->contact_to_id);
		}else{
			$chat_history_model->addCondition([['from_id',$this->app->apartmentmember->id],['to_id',$this->app->apartmentmember->id]]);
			$chat_history_model->addCondition([['from_id',$this->contact_to_id],['to_id',$this->contact_to_id]]);
		}
		$chat_history_model->setOrder('id','desc');
		$chat_history_model->setLimit(5);
		$result = $chat_history_model->getRows();
		$result = array_reverse($result);
		$this->chat_history_lister->setSource($result);
		// $this->chat_history_lister->setModel($chat_history_model);
		// $this->chat_history_lister->add('misc\Controller_AutoPaginator')->setLimit(5);

		// if contact is selected then updated name
		$this->template->trySet('selected_name',$this->contact_to_name?:'Chat History');
		$this->template->trySet('selected_member_img',$this->contact_to_image?:$this->default_img_url);
		$this->template->trySet('aptchatwith',$this->app->apartmentmember->getUUID($this->contact_to_id));
		// form submission
		if($this->contact_to_id && $this->form->isSubmitted()){
			
			if(!$this->contact_to_id) $this->form->js()->univ()->errorMessage('please select member first from your member list')->execute();
			
			$send_msg = $this->add('rakesh\apartment\Model_MessageSent');

			$send_msg['from_id'] = $this->app->apartmentmember->id;
			$send_msg['from_raw'] = ['name'=>$this->app->apartmentmember['name'],'id'=>$this->app->apartmentmember->id];
			$send_msg['to_id'] = $this->contact_to_id;
			$send_msg['to_raw'] = json_encode([['name'=>$this->contact_to_name,'id'=>$this->contact_to_id]]);
			$send_msg['related_contact_id'] = $this->contact_to_id; // if communication is around some contact like group because group is contact
			$send_msg['mailbox'] = "InternalMessage";
			$send_msg['created_by_id'] = $this->contact_to_id;
			$send_msg['related_id'] = $this->app->apartment->id;
			$send_msg['title'] = $this->contact_to_name." messaged you";
			$send_msg['description'] = $message = $this->form['message'];
			$send_msg['type'] = 'chatmessage';
			$send_msg->save();
			$send_msg->sendNotification();
			
			$send_date = date('M d H:i a',strtotime($send_msg['created_at']));

			$send_html = '<div class="direct-chat-msg">
							<div class="direct-chat-info clearfix">
			            		<span class="direct-chat-name pull-left">'.$this->app->apartmentmember['name'].'</span>
			            		<span class="direct-chat-timestamp pull-right">'.$send_date.'</span>
			          		</div>
			          		<img src="'.($this->app->apartmentmember['image']?:$this->default_img_url).'" class="direct-chat-img"/>
			          		<div class="direct-chat-text">'.$message.'</div>
						</div>';

			$js_array = [
				$this->form->js()->_selector('.msginputbox')->val(""),
				$this->chat_history_lister->js()->append($send_html)->_selector('.direct-chat-messages'),
				$this->form->js()->univ()->chatScrollToTop()
			];
			
			$this->form->js(null,$js_array)->univ()->execute();
		}

		$this->send_button->js('click',[$this->form->js()->submit()]);
		
		$this->app->stickyForget('chatid');
		$this->js('click')->univ()->redirect($this->app->url('dashboard',['mode'=>'chat']))->_selector('.backtochatmember');


		$wshost = $this->app->getConfig('ap-websocket-server');
		$uu_id = $this->app->apartmentmember->getUUID();
		$apwsclient = $this->js(true)->_load('apwsclient');
		$apwsclient->eapartment_chatpanel(
				[
					'connected'=>true,
					'username'=>$this->app->apartmentmember['name'],
					'wshost'=>$wshost,
					'uu_id'=>$uu_id,
					'chat_with'=>$this->contact_to_id
				]);
		// $apwsclient->univ()->runWebSocketClient($wshost,$uu_id,['name'=>$this->app->apartmentmember['name']]);

		$this->js(true)->univ()->chatScrollToTop();
		parent::recursiveRender();
	}
	
	function defaultTemplate(){
		return ['view\chatpanel2'];
	}

}