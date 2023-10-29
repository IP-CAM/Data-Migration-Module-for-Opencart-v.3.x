<?php

/**
 * D2dSoft
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL v3.0) that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL: https://d2d-soft.com/license/AFL.txt
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade this extension/plugin/module to newer version in the future.
 *
 * @author     D2dSoft Developers <developer@d2d-soft.com>
 * @copyright  Copyright (c) 2021 D2dSoft (https://d2d-soft.com)
 * @license    https://d2d-soft.com/license/AFL.txt
 */

class ControllerExtensionModuleD2dDatamigration extends Controller {

    const PACKAGE_URL = 'https://d2d-soft.com/download_package.php';

    protected $migrationApp;

    /* @TODO: MODULE */

    public function index() {
        if(!$this->isInstallLibrary()){
            $this->license();
            return;
        }
        $page_type = $this->getRequestValue('page_type');
        switch($page_type){
            case 'license':
                $this->license();
                break;
            case 'setting':
                $this->setting();
                break;
            default:
                $this->migration();
                break;
        }
        return;
    }

    public function license(){
        if($this->isPost()){
            $this->submitFormLicense();
        }
        $folder = $this->getLibraryFolder();
        if(!is_writeable($folder)){
            $folder_name = 'system' . $this->getLibraryLocation();
            $this->setMessage('error', 'Folder "' . $folder_name . '" must is a writable folder.');
        }
        if(!ini_get('allow_url_fopen')){
            $this->setMessage('error', 'The PHP "allow_url_fopen" must is enabled. Please follow <a href="https://www.a2hosting.com/kb/developer-corner/php/using-php.ini-directives/php-allow-url-fopen-directive" target="_blank">here</a> to enable the setting.');
        }
        if (!extension_loaded('zip')) {
            $this->setMessage('error', 'PHP Zip extension is not installed. Please install the Zip extension.');
        }
        /*if (!function_exists('eval')) {
            $this->setMessage('error', 'Please enable the eval function.');
        }*/
        $messages = $this->getMessage();
        $data = array();
        $data['form_url'] = $this->getAdminModuleUrl('license');
        $data['messages'] = $messages;
        $this->renderPage('license', $data);
    }

    public function setting(){
        if(!$this->isInstallLibrary()){
            $this->redirectModuleAction('license');
            return;
        }
        if($this->isPost()){
            $this->submitFormSetting();
        }
        $app = $this->getMigrationApp();
        $target = $app->getInitTarget();
        $settings = $target->dbSelectSettings();
        $messages = $this->getMessage();
        $data = array();
        $data['index_url'] = $this->getAdminModuleUrl('index');
        $data['license_url'] = $this->getAdminModuleUrl('license');
        $data['migration_url'] = $this->getAdminModuleUrl('migration');
        $data['setting_url'] = $this->getAdminModuleUrl('setting');
        $data['process_url'] = $this->getAdminModuleUrl('process');
        $data['settings'] = $settings;
        $data['messages'] = $messages;
        $this->renderPage('setting', $data);
    }

    public function migration(){
        if(!$this->isInstallLibrary()){
            $this->redirectModuleAction('license');
            return;
        }
        $app = $this->getMigrationApp();
        $initTarget = $app->getInitTarget();
        $response = $app->process(D2dInit::PROCESS_INIT);
        $html_content = '';
        if($response['status'] == D2dCoreLibConfig::STATUS_SUCCESS){
            $html_content = $response['html'];
        }
        $config = $initTarget->getConfigJs();
        $config_data = $this->arrayToJsConfig($config);
        $data = array();
        $data['migration_url'] = $this->getAdminModuleUrl('migration');
        $data['setting_url'] = $this->getAdminModuleUrl('setting');
        $data['process_url'] = $this->getAdminModuleUrl('process');
        $data['html_content'] = $html_content;
        $data['js_config'] = $config_data;
        $this->renderPage('migration', $data);
    }

    public function process(){
        $action_type = $this->getRequestValue('action_type', 'import');
        if($action_type == 'import'){
            $app = $this->getMigrationApp();
            $process = $this->getArrayValue($_REQUEST, 'process');
            if(!$process || !in_array($process, array(
                    D2dInit::PROCESS_SETUP,
                    D2dInit::PROCESS_CHANGE,
                    D2dInit::PROCESS_UPLOAD,
                    D2dInit::PROCESS_STORED,
                    D2dInit::PROCESS_STORAGE,
                    D2dInit::PROCESS_CONFIG,
                    D2dInit::PROCESS_CONFIRM,
                    D2dInit::PROCESS_PREPARE,
                    D2dInit::PROCESS_CLEAR,
                    D2dInit::PROCESS_IMPORT,
                    D2dInit::PROCESS_RESUME,
                    D2dInit::PROCESS_REFRESH,
                    D2dInit::PROCESS_AUTH,
                    D2dInit::PROCESS_FINISH))){
                $this->responseJson(array(
                    'status' => 'error',
                    'message' => 'Process Invalid.'
                ));
                return;
            }
            $response = $app->process($process);
            $this->responseJson($response);
            return;
        }
        if($action_type == 'download'){
            $app = $this->getMigrationApp();
            $app->process(D2dInit::PROCESS_DOWNLOAD);
            return;
        }
        $this->responseJson(array(
            'status' => 'error',
            'message' => ''
        ));
        return;
    }

    protected function submitFormLicense(){
        $license = $this->getRequestValue('license');
        if(!$license){
            return false;
        }
        $install = $this->downloadAndExtraLibrary($license);
        if(!$install){
            return false;
        }
        $app = $this->getMigrationApp();
        $initTarget = $app->getInitTarget();
        $install_db = $initTarget->setupDatabase($license);
        if(!$install_db){
            $this->redirectModuleAction('license');
            return false;
        }
        $this->redirectModuleAction('migration');
        return $this;
    }

    protected function submitFormSetting(){
        $keys = array(
            'license', 'storage', 'taxes', 'manufacturers', 'customers', 'orders', 'reviews', 'delay', 'retry', 'src_prefix', 'target_prefix', 'other'
        );
        $app = $this->getMigrationApp();
        $target = $app->getInitTarget();
        $request = $this->getRequestValue(null);
        foreach($keys as $key){
            $value = $this->getArrayValue($request, $key, '');
            $target->dbSaveSetting($key, $value);
        }
        $this->setMessage('success', 'Save successfully.');
        return true;
    }

    public function install(){
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('module_d2d_datamigration', array('module_d2d_datamigration_status' => 1));
    }

    public function uninstall(){
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_d2d_datamigration');
    }

    /* @TODO: PROCESS */

    protected function renderPage($template, $data = array()){
        /* @var $load Loader */
        $load = $this->load;
        /* @var $language Language */
        $language = $this->language;
        /* @var $document Document */
        $document = $this->document;
        /* @var $response Response */
        $response = $this->response;

        $load->language('extension/module/d2d_datamigration');
        $document->setTitle($language->get('heading_title'));
        $styles = array(
            'view/javascript/d2d_datamigration/css/select2.min.css',
            'view/javascript/d2d_datamigration/css/style.css',
            'view/javascript/d2d_datamigration/css/custom.css',
        );
        foreach($styles as $style){
            $document->addStyle($style);
        }
        $scripts = array(
            'view/javascript/d2d_datamigration/js/bootbox.min.js',
            'view/javascript/d2d_datamigration/js/select2.min.js',
            'view/javascript/d2d_datamigration/js/jquery.form.min.js',
            'view/javascript/d2d_datamigration/js/jquery.validate.min.js',
            'view/javascript/d2d_datamigration/js/jquery.extend.js',
            'view/javascript/d2d_datamigration/js/jquery.migration.js',
        );
        foreach($scripts as $script){
            $document->addScript($script);
        }

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $language->get('text_home'),
            'href' => $this->getAdminUrl('common/dashboard')
        );
        $data['breadcrumbs'][] = array(
            'text' => $language->get('text_extension'),
            'href' => $this->getAdminUrl('marketplace/extension')
        );
        $data['breadcrumbs'][] = array(
            'text' => $language->get('heading_title'),
            'href' => $this->getAdminModuleUrl('')
        );

        $data['header'] = $load->controller('common/header');
        $data['column_left'] = $load->controller('common/column_left');
        $data['footer'] = $load->controller('common/footer');

        $template_path = 'extension/module/d2d_datamigration/' . $template;
        $data['page_content'] = $load->view($template_path, $data);
        $page_template_path = 'extension/module/d2d_datamigration/page';
        $response->setOutput($load->view($page_template_path, $data));
    }

    protected function getAdminUrl($route, $params = array()){
        /* @var $url Url */
        $url = $this->url;
        /* @var $session Session*/
        $session = $this->session;
        $user_token = $session->data['user_token'];
        $params['user_token'] = $user_token;
        $query = http_build_query($params);
        $admin_url = $url->link($route, $query, true);
        $admin_url = str_replace('&amp;', '&', $admin_url);
        return $admin_url;
    }

    protected function getAdminModuleUrl($action, $params = array()){
        $route = 'extension/module/d2d_datamigration';
        if($action){
            $route .= '/' . $action;
        }
        return $this->getAdminUrl($route, $params);
    }

    protected function redirect($route, $params = array()){
        /* @var $response Response */
        $response = $this->response;
        $url = $this->getAdminUrl($route, $params);
        $response->redirect($url);
        return;
    }

    protected function redirectModuleAction($action, $params = array()){
        /* @var $response Response */
        $response = $this->response;
        $url = $this->getAdminModuleUrl($action, $params);
        $response->redirect($url);
        return;
    }

    protected function getRequestValue($key, $def = null){
        $request = $this->request->request;
        if(!$key){
            return $request;
        }
        return $this->getArrayValue($request, $key, $def);
    }

    protected function isPost(){
        return ($this->request->server['REQUEST_METHOD'] == 'POST');
    }

    public function setMessage($type, $message){
        /* @var $session Session*/
        $session = $this->session;
        $sessionData = $session->data;
        $messages = $this->getArrayValue($sessionData, 'migration_message', array());
        if(!$messages){
            $messages = array();
        }
        $messages[] = array(
            'type' => $type,
            'message' => $message
        );
        $session->data['migration_message'] = $messages;
        return $this;
    }

    public function getMessage(){
        /* @var $session Session*/
        $session = $this->session;
        $sessionData = $session->data;
        $messages = $this->getArrayValue($sessionData, 'migration_message', array());
        $session->data['migration_message'] = array();
        return $messages;
    }

    /* @TODO: LIBRARY */

    public function getLibraryLocation(){
        return '/system/library/d2d_datamigration';
    }

    protected function getLibraryFolder(){
        $location = $this->getLibraryLocation();
        $folder = DIR_SYSTEM . '..' . $location;
        return $folder;
    }

    protected function getInitLibrary(){
        $library_folder = $this->getLibraryFolder();
        return $library_folder . '/resources/init.php';
    }

    protected function isInstallLibrary(){
        $init_file = $this->getInitLibrary();
        return file_exists($init_file);
    }

    protected function downloadAndExtraLibrary($license = '')
    {
        $url = self::PACKAGE_URL;
        $library_folder = $this->getLibraryFolder();
        if(!is_dir($library_folder))
            @mkdir($library_folder, 0777, true);
        $tmp_path = $library_folder . '/resources.zip';
        $data = array(
            'license' => $license
        );
        $fp = @fopen($tmp_path, 'wb');
        if(!$fp){
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:40.0) Gecko/20100101 Firefox/40.0');
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        $response = curl_exec($ch);
        if(curl_errno($ch)){
            return false;
        }
        curl_close($ch);
        @fclose($fp);
        if(!$response){
            return false;
        }

        $zip = new ZipArchive;
        if ($zip->open($tmp_path) === TRUE) {
            $zip->extractTo($library_folder);
            $zip->close();

            @unlink($tmp_path);
            return true;
        } else {
            return false;
        }
    }

    protected function getMigrationApp()
    {
        if($this->migrationApp){
            return $this->migrationApp;
        }
        global $d2dDB;
        $d2dDB = $this->db;
        $user_id = $this->session->data['user_id'];
        $library_folder = $this->getLibraryFolder();
        include_once $this->getInitLibrary();
        D2dInit::initEnv();
        $app = D2dInit::getAppInstance(D2dInit::APP_HTTP, D2dInit::TARGET_RAW, 'opencart300');
        $app->setRequest($_REQUEST);
        $config = array();
        $config['user_id'] = $user_id;
        $config['upload_dir'] = $library_folder . '/files';
        $config['upload_location'] = $this->getLibraryLocation() . '/files';
        $config['log_dir'] = $library_folder . '/log';
        $app->setConfig($config);
        $app->setPluginManager($this);
        $this->migrationApp = $app;
        return $this->migrationApp;
    }

    public function getPlugin($name){
        $library_folder = $this->getLibraryFolder();
        $path = $library_folder . '/plugins/' . $name . '.php';
        if(!file_exists($path)){
            return false;
        }
        require_once $path;
        $class_name = 'D2dDataMigrationPlugin' . $name;
        if(!class_exists($class_name)){
            return false;
        }
        $class = new $class_name();
        return $class;
    }

    /* @TODO: UTILS */

    protected function getArrayValue($array, $key, $default = null){
        return isset($array[$key]) ? $array[$key] : $default;
    }

    protected function arrayToJsConfig($array){
        $data = array();
        foreach($array as $k => $v){
            $data[] = "'{$k}':'{$v}'";
        }
        $result = implode(',', $data);
        if($result){
            $result .= ',';
        }
        return $result;
    }

    public function responseJson($data){
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

}