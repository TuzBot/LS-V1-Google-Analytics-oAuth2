<?php
	
	class GoogleAnalytics_Module extends Core_ModuleBase
	{
		protected function createModuleInfo()
		{
			return new Core_ModuleInfo(
				"Google Analytics",
				"Updates Google Analytics to use OAuth2",
				"iD30",
				"http://www.id30.com");
		}
		
		public function register_access_points()
		{
			return array(
				'oauth2callback'=> 'grant_oauth2_request_token',
				'ga_oauth_request_response'=> 'grant_oauth2_request_response',
				'ga_test'=> 'ga_test_func'
			);
		}
		
				
		public function ga_test_func($params)
		{
			$ga_auth = new Cms_GoogleAnalytics();
			$ga_auth->refresh_token();
		}
				
		public function subscribeEvents()
		{
			Backend::$events->addEvent('core:onInitialize', $this, 'initialize');
		}
				
		public function initialize() 
		{
			spl_autoload_register('GoogleAnalytics_Module::autoloader', true, true);
		}
		
		public static function autoloader($class) 
		{
			if (strtolower($class) == 'cms_googleanalytics') 
			{
				require_once __DIR__ . '/../classes/cms_googleanalytics.php';
			}
			
			if (strtolower($class) == 'cms_stats_settings') 
			{
				require_once __DIR__ . '/../models/cms_stats_settings.php';
			}
			
			$old_ga_stats_files = PATH_APP.'/modules/cms/controllers/cms_settings/stats.htm';
			if(file_exists(@$old_ga_stats_files))
			{
				$new_content = file_get_contents(__DIR__ . '/../partials/stats.htm');
				file_put_contents($old_ga_stats_files,$new_content);
				$new_content = null;
			}
			$old_ga_stats_files = null;
		}
		
		public function grant_oauth2_request_token($params)
		{
			$code = Phpr::$request->getField('code');
			if(!strlen($code))
				return;
				
			$ga_auth = new Cms_GoogleAnalytics();
			$expired = $ga_auth->expired();
			
			if(!$ga_auth->auth_token || ($ga_auth->auth_token && $expired))
			{
				$ga_auth->code = $code;				
				$ga_auth->request_token();
			}
			else
			{
				$url = Phpr::$request->getField('state');
				$this->grant_oauth2callback_access($url);
			}
		}
		
		public function grant_oauth2callback_access($url)
		{
			if(!strlen($url))
				return;
						
			Phpr::$response->redirect(urldecode($url));
		}
		
		public function grant_oauth2_request_response($params)
		{
			$admin_url = Phpr::$config->get('BACKEND_URL', 'backend');
			
			Phpr::$session->flash['success'] = 'Statistics settings have been saved.';
			Phpr::$response->redirect(root_url($admin_url.'/system/settings'));	
		}
	}