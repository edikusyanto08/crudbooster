<?php

namespace crocodicstudio\crudbooster\Modules\ModuleGenerator;

use crocodicstudio\crudbooster\controllers\CBController;
use crocodicstudio\crudbooster\controllers\FormValidator;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\DB;
use crocodicstudio\crudbooster\helpers\CRUDBooster;

class AdminModulesController extends CBController
{
    public function cbInit()
    {
        $this->table = 'cms_moduls';
        $this->primaryKey = 'id';
        $this->titleField = 'name' ;
        $this->limit = 100;
        $this->buttonAdd = false;
        $this->buttonExport = false;
        $this->button_import = false;
        $this->buttonFilter = false;
        $this->buttonDetail = false;
        $this->buttonBulkAction = false;
        $this->button_action_style = 'button_icon';
        $this->orderby = ['is_protected' => 'asc', 'name' => 'asc'];

        $this->makeColumns();

        $this->form = Form::makeForm($this->table);

        $this->script_js = "
 			$(function() {
 				$('#table_name').change(function() {
					var v = $(this).val();
					$('#path').val(v);
				})	
 			}) ";

        $this->addaction[] = [
            'label' => 'Module Wizard',
            'icon' => 'fa fa-wrench',
            'url' => CRUDBooster::mainpath('step1').'/[id]',
            "showIf" => "[is_protected] == 0",
        ];

        $this->index_button[] = ['label' => 'Generate New Module', 'icon' => 'fa fa-plus', 'url' => CRUDBooster::mainpath('step1'), 'color' => 'success'];
    }
    // public function getIndex() {
    // 	$data['page_title'] = 'Module Generator';
    // 	$data['result'] = DB::table('cms_moduls')->where('is_protected',0)->orderby('name','asc')->get();
    // 	$this->cbView('CbModulesGen::index',$data);
    // }	

    function hookBeforeDelete($id)
    {
        $controller = ModulesRepo::getControllerName($id);
        DB::table('cms_menus')->where('path', 'like', '%'.$controller.'%')->delete();
        @unlink(controller_path($controller));
    }

    public function getTableColumns($table)
    {
        $columns = \Schema::getColumnListing($table);

        return response()->json($columns);
    }

    public function getCheckSlug($slug)
    {
        $check = DB::table('cms_moduls')->where('path', $slug)->count();
        $lastId = DB::table('cms_moduls')->max('id') + 1;

        return response()->json(['total' => $check, 'lastid' => $lastId]);
    }

    public function getAdd()
    {
        $this->cbLoader();

        return redirect()->route("ModulesControllerGetStep1");
    }

    public function getStep1($id = 0, Step1Handler $handler)
    {
        $this->cbLoader();
        return $handler->showForm($id);
    }

    public function getStep2($id, Step2Handler $handler)
    {
        $this->cbLoader();
        return $handler->showForm($id);
    }

    public function postStep2(Step1Handler $handler)
    {
        $this->cbLoader();

        return $handler->handleFormSubmit();
    }

    public function postStep3(Step2Handler $handler)
    {
        $this->cbLoader();
        return $handler->handleFormSubmit();
    }

    public function getStep3($id, Step3Handler $step3)
    {
        $this->cbLoader();
        return $step3->showForm($id);
    }

    public function getTypeInfo($type = 'text')
    {
        header("Content-Type: application/json");
        echo file_get_contents(CbComponentsPath($type).'/info.json');
    }

    public function postStep4(Step3Handler $handler)
    {
        $this->cbLoader();
        return $handler->handleFormSubmit();
    }

    public function getStep4($id, Step4Handler $handler)
    {
        $this->cbLoader();
        return $handler->showForm($id);
    }

    public function postStepFinish(Step4Handler $handler)
    {
        $this->cbLoader();
        return $handler->handleFormSubmit();
    }

    public function postAddSave()
    {
        $this->cbLoader();
        app(FormValidator::class)->validate(null, $this->form, $this->table);
        $this->inputAssignment();

        //Generate Controller 
        $route_basename = basename(request('path'));
        if ($this->arr['controller'] == '') {
            $this->arr['controller'] = ControllerGenerator::generateController(request('table_name'), $route_basename);
        }

        $this->arr['created_at'] = date('Y-m-d H:i:s');
        $this->arr['id'] = $this->table()->max('id') + 1;
        $this->table()->insert($this->arr);

        //Insert Menu
        if ($this->arr['controller']) {
            $this->createMenuForModule();
        }

        $id_modul = $this->arr['id'];

        $user_id_privileges = CRUDBooster::myPrivilegeId();
        DB::table('cms_privileges_roles')->insert([
            'id_cms_moduls' => $id_modul,
            'id_cms_privileges' => $user_id_privileges,
            'is_visible' => 1,
            'is_create' => 1,
            'is_read' => 1,
            'is_edit' => 1,
            'is_delete' => 1,
        ]);

        //Refresh Session Roles
        $roles = DB::table('cms_privileges_roles')->where('id_cms_privileges', CRUDBooster::myPrivilegeId())->join('cms_moduls', 'cms_moduls.id', '=', 'id_cms_moduls')->select('cms_moduls.name', 'cms_moduls.path', 'is_visible', 'is_create', 'is_read', 'is_edit', 'is_delete')->get();
        Session::put('admin_privileges_roles', $roles);

        $ref_parameter = Request::input('ref_parameter');
        if (request('return_url')) {
            CRUDBooster::redirect(request('return_url'), cbTrans("alert_add_data_success"), 'success');
        } 
        if (request('submit') == cbTrans('button_save_more')) {
            CRUDBooster::redirect(CRUDBooster::mainpath('add'), cbTrans('alert_add_data_success'), 'success');
        }
        CRUDBooster::redirect(CRUDBooster::mainpath(), cbTrans('alert_add_data_success'), 'success');
    }

    public function postEditSave($id)
    {
        $this->cbLoader();

        $row = $this->table()->where($this->primaryKey, $id)->first();


        app(FormValidator::class)->validate($id, $this->form, $this->table);

        $this->inputAssignment();

        //Generate Controller 
        $route_basename = basename(request('path'));
        if ($this->arr['controller'] == '') {
            $this->arr['controller'] = ControllerGenerator::generateController(request('table_name'), $route_basename);
        }

        $this->findRow($id)->update($this->arr);

        //Refresh Session Roles
        $roles = DB::table('cms_privileges_roles')->where('id_cms_privileges', CRUDBooster::myPrivilegeId())->join('cms_moduls', 'cms_moduls.id', '=', 'id_cms_moduls')->select('cms_moduls.name', 'cms_moduls.path', 'is_visible', 'is_create', 'is_read', 'is_edit', 'is_delete')->get();
        Session::put('admin_privileges_roles', $roles);

        CRUDBooster::redirect(Request::server('HTTP_REFERER'), cbTrans('alert_update_data_success'), 'success');
    }

    private function createMenuForModule()
    {
        $parent_menu_sort = DB::table('cms_menus')->where('parent_id', 0)->max('sorting') + 1;
        $parent_menu_id = DB::table('cms_menus')->insertGetId([
            'created_at' => date('Y-m-d H:i:s'),
            'name' => $this->arr['name'],
            'icon' => $this->arr['icon'],
            'path' => '#',
            'type' => 'URL External',
            'is_active' => 1,
            'cms_privileges' => CRUDBooster::myPrivilegeId(),
            'sorting' => $parent_menu_sort,
            'parent_id' => 0,
        ]);

        $arr = [
            'created_at' => date('Y-m-d H:i:s'),
            'type' => 'Route',
            'is_active' => 1,
            'cms_privileges' => CRUDBooster::myPrivilegeId(),
            'parent_id' => $parent_menu_id,
        ];

        DB::table('cms_menus')->insert([
            'name' => cbTrans('text_default_add_new_module', ['module' => $this->arr['name']]),
            'icon' => 'fa fa-plus',
            'path' => $this->arr['controller'].'GetAdd',
            'sorting' => 1,
        ] + $arr);

        DB::table('cms_menus')->insert([
            'name' => cbTrans('text_default_list_module', ['module' => $this->arr['name']]),
            'icon' => 'fa fa-bars',
            'path' => $this->arr['controller'].'GetIndex',
            'cms_privileges' => CRUDBooster::myPrivilegeId(),
            'sorting' => 2,
        ] + $arr);

    }

    private function makeColumns()
    {
        $this->col = [];
        $this->col[] = ['label' => 'name', 'name' => 'name'];
        $this->col[] = ['label' => "Table", 'name' => "table_name"];
        $this->col[] = ['label' => "Path", 'name' => "path"];
        $this->col[] = ['label' => "Controller", 'name' => "controller"];
        $this->col[] = ['label' => "Protected", 'name' => "is_protected", "visible" => false];
    }
}
