<?php

namespace rakesh\apartment;

class View_Chat extends \View{

	public $options = [];
	private $member_lister;
	private $form;
	private $chat_history_lister;

	private $contact_to_id = 0;
	private $contact_to_name = "";
	private $contact_to_mage = "";

	function init(){
		parent::init();
		
		// if(!@$this->app->apartment->id){
		// 	$this->add('View_Error')->set('first update partment data');
		// 	return;
		// }
		$this->contact_to_id = $this->app->stickyGET('contact_to_id')?:0;
		$this->contact_to_name = $this->app->stickyGET('contact_to_name')?:"";
		$this->contact_to_image = $this->app->stickyGET('contact_to_image')?:"";

		$this->addChatHistory();
		$this->addMemberLister();
		$this->addForm();
	}

	function addForm(){

		$this->form = $form = $this->add('Form',null,'message_form',['form/horizontal']);
		$form->addField('Line','message','')->validate('required');
		$form->addSubmit('send');
	}

	function addMemberLister(){

		$this->member_lister = $lister = $this->add('CompleteLister',null,'ap_member_list',['view\chat','ap_member_list']);
		$lister->addHook('formatRow',function($l){
			if($l->model['image_id']){
				$l->current_row_html['profile_image'] = $l->model['image'];
			}else{
				$l->current_row_html['profile_image'] = 'websites/apartment/www/dist/img/avatar04.png';
			}
		});

	}

	function addChatHistory(){
		// sent
		// replies
		$this->chat_history_lister = $lister = $this->add('CompleteLister',null,'ap_chat_lister',['view\chat','ap_chat_lister']);

		$lister->addHook('formatRow',function($l){
			if($l->model['from_id'] == $this->app->apartmentmember->id){
				$l->current_row_html['chat_direction'] = "sent";
			}else{
				$l->current_row_html['chat_direction'] = "replies";
			}

			if($l->model['image_id']){
				$l->current_row_html['profile_image'] = $l->model['image'];
			}else{
				$l->current_row_html['profile_image'] = 'websites/apartment/www/dist/img/avatar04.png';
			}
		});
	}

	function recursiveRender(){

		// member lister
		$member_model = $this->add('rakesh\apartment\Model_Member');
		$member_model->addCondition('apartment_id',$this->app->apartment->id)
					->addCondition('status','Active');
		$this->member_lister->setModel($member_model);

		// chat history
		$chat_history_model = $this->add('rakesh\apartment\Model_MessageSent');
		$chat_history_model->addCondition('related_id',$this->app->apartment->id);
		$chat_history_model->addCondition([['from_id',$this->app->apartmentmember->id],['to_id',$this->app->apartmentmember->id]]);
		if($this->contact_to_id)
			$chat_history_model->addCondition([['from_id',$this->contact_to_id],['to_id',$this->contact_to_id]]);

		$this->chat_history_lister->setModel($chat_history_model);
		// if contact is selected then updated name
		$this->chat_history_lister->template->trySet('selected_name',$this->contact_to_name);
		$this->chat_history_lister->template->trySet('selected_member_img',$this->contact_to_image);

		// form submission
		if($this->form->isSubmitted()){
			$send_msg = $this->add('rakesh\apartment\Model_MessageSent');
			
			$send_msg['from_id'] = $this->app->apartmentmember->id;
			$send_msg['from_raw'] = ['name'=>$this->app->apartmentmember['name'],'id'=>$this->app->apartmentmember->id];
			$send_msg['to_id'] = $this->contact_to_id;
			$send_msg['to_raw'] = json_encode(['name'=>$this->contact_to_name,'id'=>$this->contact_to_id]);
			$send_msg['related_contact_id'] = $this->contact_to_id; // if communication is around some contact like group because group is contact
			$send_msg['mailbox'] = "InternalMessage";
			$send_msg['created_by_id'] = $this->contact_to_id;
			$send_msg['related_id'] = $this->app->apartment->id;
			// $send_msg['title'] = $f['subject'];
			$send_msg['description'] = $this->form['message'];
			$send_msg->save();
			
			// $this->chat_history_lister->js()->reload()->execute();
			$this->form->js(null,$this->chat_history_lister->js()->reload())
				->univ()
				->successMessage('send')->execute();
		}

		// reload member chat
		$this->member_lister->js('click',$this->chat_history_lister->js()->reload(
			[
				'contact_to_id'=>$this->js()->_selectorThis()->data('memberid'),
				'contact_to_name'=>$this->js()->_selectorThis()->data('name'),
				'contact_to_image'=>$this->js()->_selectorThis()->data('profileimage'),
			])
		)->_selector('li.contact');
		


		parent::recursiveRender();

	}
	
	function defaultTemplate(){
		return ['view\chat'];
	}

}