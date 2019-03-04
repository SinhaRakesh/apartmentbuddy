<?php

namespace rakesh\apartment;

class View_Master extends \View{

	public $options = [];
	public $title = "Configuration";

	function init(){
		parent::init();
		
		if(!@$this->app->apartment->id){
			$this->add('View_Error')->set('first update partment data');
			return;
		}

		$master_model = $this->add('rakesh\apartment\Model_Config_Master',
			[
				'fields'=>[
							'flat_size'=>'Text',
							'flat_status'=>'Text',
							'complain_category'=>'Text',
							'expenses_category'=>'Text',
							'staff_type'=>'Text'
						],
					'config_key'=>'Apartment_Config',
					'application'=>'rakesh\apartment'
			]);
		$master_model->tryLoadAny();
		
		$ft = $this->add('View')->addClass('flattabs');
		$tabs = $ft->add('Tabs');
		$flat_size_tab = $tabs->addTab('Flat Size');
		$flat_status_tab = $tabs->addTab('Flat Status');
		$notice_tab = $tabs->addTab('Notice Board');
		$complain_tab = $tabs->addTab('Complain Category');
		$complain_department_tab = $tabs->addTab('Complain Department');
		$expenses_category = $tabs->addTab('Expenses Category');
		$staff_type = $tabs->addTab('Staff Type');
		$apartment_admin = $tabs->addTab('Apartment Admin');


		// flat size
		$form_size = $flat_size_tab->add('Form');
		$field_size = $form_size->addField('Text','flat_size',"")
				->setFieldHint('Comma(,) seperated values i.e. 1BHK,2BHK')
				->set($master_model['flat_size']);
		if(!$master_model['flat_size']){
			$field_size->set('1 BHK,2 BHK,3 BHK,4 BHK, Other');
		}
		$form_size->addSubmit('Save')->addClass('btn btn-primary');
		if($form_size->isSubmitted()){
			$master_model['flat_size'] = $form_size['flat_size'];
			$master_model->save();
			$form_size->js(null,$form_size->js()->reload())->univ()->successMessage('saved successfully ')->execute();
		}


		// flat status
		$form_status = $flat_status_tab->add('Form');
		$field_status = $form_status->addField('Text','flat_size',"")
				->setFieldHint('Comma(,) seperated values i.e. Sold,Rent')
				->set($master_model['flat_status']);
		if(!$master_model['flat_status']){
			$field_status->set('Sold,OnRent,Vacant');
		}
		$form_status->addSubmit('Save')->addClass('btn btn-primary');
		if($form_status->isSubmitted()){
			$master_model['flat_status'] = $form_status['flat_status'];
			$master_model->save();
			$form_size->js(null,$form_size->js()->reload())->univ()->successMessage('saved successfully ')->execute();
		}

		// notice board
		$notice_tab->add('rakesh\apartment\View_NoticeBoard');

		// complain category
		$complain_form = $complain_tab->add('Form');
		$complain_status_field = $complain_form->addField('Text','complain_category',"")
				->setFieldHint('Comma(,) seperated values i.e. Accounts,Lifts')
				->set($master_model['complain_category']);
		if(!$master_model['complain_category']){
			$complain_status_field->set('Accounts,Drains,Electrical,Garbage Disposal,Garden,Housekeeping,Lifts,Playarea,Security,Plumbing');
		}
		$complain_form->addSubmit('Save')->addClass('btn btn-primary');
		if($complain_form->isSubmitted()){
			$master_model['complain_category'] = $complain_form['complain_category'];
			$master_model->save();
			$complain_form->js(null,$complain_form->js()->reload())->univ()->successMessage('saved successfully ')->execute();
		}

		// complain Department
		$cmp_dept_model = $this->add('rakesh\apartment\Model_ComplainDepartment');
		$cmp_dept_model->addCondition('apartment_id',$this->app->apartment->id);

		$complain_dept_crud = $complain_department_tab->add('xepan\base\CRUD');
		$complain_dept_crud->setModel($cmp_dept_model,null,['name']);
		$complain_dept_crud->grid->addPaginator(5);
		$complain_dept_crud->grid->js(true)->find('.main-box-body')->addClass('table-responsive');
		$complain_dept_crud->grid->addColumn('edit');
		$complain_dept_crud->grid->addColumn('delete');


		// expenses_category
		$form_ex = $expenses_category->add('Form');
		$form_ex->addField('Text','expenses_category')
			->setFieldHint('Comma(,) seperated values i.e. Lift,Electrician')
				->set($master_model['expenses_category']);
		$form_ex->addSubmit('Save');
		if($form_ex->isSubmitted()){
			$master_model['expenses_category'] = $form_ex['expenses_category'];
			$master_model->save();
			$form_size->js(null,$form_ex->js()->reload())->univ()->successMessage('saved successfully ')->execute();
		}

		// staff type
		$form_stype = $staff_type->add('Form');
		$field_stype = $form_stype->addField('Text','staff_type')
			->setFieldHint('Comma(,) seperated values i.e. Security Guard, Gatekipper, Servent etc.')
				->set($master_model['staff_type']);
		if(!$master_model['staff_type']) 
			$field_stype->set('Security Guard, Gatekipper, Servent');
		$form_stype->addSubmit('Save');
		if($form_stype->isSubmitted()){
			$master_model['staff_type'] = $form_stype['staff_type'];
			$master_model->save();
			$form_stype->js(null,$form_stype->js()->reload())->univ()->successMessage('saved successfully ')->execute();
		}


		// apartment admins
		$member_model = $this->add('rakesh\apartment\Model_Member')
			->addCondition('apartment_id',$this->app->apartment->id);
		$form_admin = $apartment_admin->add('Form');
		$field = $form_admin->addField('DropDown','member');
		$field->setEmptyText("Please Select");
		$field->setModel($member_model);
		$field->validate('required');
		$form_admin->addSubmit();

		$model_admin = $this->add('rakesh\apartment\Model_Member')
			->addCondition('is_apartment_admin',true)
			->addCondition('apartment_id',$this->app->apartment->id);

		$grid = $apartment_admin->add('Grid');
		$grid->setModel($model_admin,['user','first_name','last_name','flat_name']);

		if($form_admin->isSubmitted()){
			if(!$form_admin['member']) $form_admin->displayError('member','please select member')->execute();

			$m = $this->add('rakesh\apartment\Model_Member');
			$m->addCondition('apartment_id',$this->app->apartment->id)
				->addCondition('id',$form_admin['member'])
				->tryLoadAny();
				
			if($m->loaded()){
				$m['is_apartment_admin'] = true;
				$m->save();
			}

			$form_admin->js(null,[$form_admin->js()->reload(),$grid->js()->reload()])->univ()->successMessage('updated successfully')->execute();
		}
	}
}