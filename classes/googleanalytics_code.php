<?php
	
	class GoogleAnalytics_Code extends Cms_Controller
	{
		
		public static function create()
		{
			return new self();	
		}
		
		public function help()
		{
			//tracelog($this->tracking_code);	
		}
	}