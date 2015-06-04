<?

	class Cms_GoogleAnalytics
	{
		const report_visitors_overview = 'VisitorsOverviewReport';
		const report_content = 'ContentReport';
		const report_dashboard = 'DashboardReport';

		public $username;
		public $password;
		public $siteId;
		public $client_id;
		public $client_secret;
		public $redirect_url;
		public $code;
		public $auth_expires;
		public $token;
		
		public $captcha_value;
		public $captcha_token;
		public $auth_token = null;

		protected $auth_url = 'https://accounts.google.com/o/oauth2/auth';
		protected $token_url = 'https://www.googleapis.com/oauth2/v3/token';
		protected $feed_url = 'https://www.googleapis.com/analytics/v2.4/data';
		protected $refresh_url = 'https://www.googleapis.com/oauth2/v3/token';
		protected $scope = 'https://www.googleapis.com/auth/analytics.readonly';
		
		protected $refresh_token;
		protected $isLoggedIn = false;
		protected $debug = true;
		protected $time_to_expire = 59;
		protected $log = array();
		
		public function __construct()
		{
			Cms_Stats_Settings::get()->copy_details($this);	
			
			$this->refresh_token = $this->token;
			$this->token = null;
		}
		
		public function __destruct()
		{
			$this->auth_token = null;
			$this->username = null;
			$this->password = null;
			$this->siteId = null;
			$this->client_id = null;
			$this->client_secret = null;
			$this->redirect_url = null;
			$this->code = null;
			$this->auth_expires = null;
			$this->refresh_token = null;
			$this->read_log($this->debug);
			$this->log = array();
			$this->scope = null;
		}
		
		public function login()
		{
			if ($this->isLoggedIn)
				return;
			
			$this->log('Login to Google and Analytics API');
			
			$data = array(
			  'response_type'=> 'code',
			  'client_id'=> $this->client_id,
			  'scope'=> $this->scope,
			  'access_type'=>'offline',
				'redirect_uri'=>trim($this->redirect_url),
				'state'=> root_url('/ga_oauth_request_response/',true),
			);
			
			if(!$this->refresh_token)
				$data['approval_prompt'] = 'force';
				
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $this->auth_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->url_encode_array($data));
			$output = curl_exec($ch);
			$info = curl_getinfo($ch);
			curl_close($ch);
			
			if($info['http_code'] == 302)
			{
				if(isset($info['redirect_url']))
					Phpr::$response->redirect($info['redirect_url']);	
				
					throw new Phpr_SystemException($output);
			}
			else			
				throw new Phpr_SystemException('Error connecting to Google Analytics. Google error: '.$output);

			if (!$this->auth_token)
				throw new Phpr_SystemException('Error connecting to Google Analytics. Authentication ticket not found in Google API response.');
			
			$this->isLoggedIn = true;	
		}
		
		public function expired()
		{				
			if($this->auth_expires)
			{
				$now = strtotime(Phpr_DateTime::now()->format('%Y-%m-%d %T'));
				$expires = strtotime($this->auth_expires->addMinutes($this->time_to_expire)->format('%Y-%m-%d %T'));
								
				if($now >= $expires)
					return true;
			}	
			
			return false;
		}
		
		
		public function authenticate()
		{
			$this->log('Authenticating...');
			
			if ($this->isLoggedIn)
				return;
			
			$expired = $this->expired();
									
			if(!$this->auth_token || (!$this->refresh_token && $this->auth_token && $expired))
				$this->login();
			
			if($this->refresh_token && $this->auth_token && $expired)
				$this->refresh_token();

			$this->isLoggedIn = true;
			
			return true;
		}
		
		protected function url_encode_array(&$fields)
		{
			$result = array();
			foreach ($fields as $name=>$value)
				$result[] = $name.'='.urlencode($value);
				
			return implode('&', $result);
		}
		
		public function downloadReport($dimensions, $metrics, $start, $end, $sort = null)
		{	
			$this->log('Report - Downloading...');
											
			$this->authenticate();

			$this->log('Authenticated');
			
			$get_fields = array(
				'ids'=>'ga:'.$this->siteId,
				'dimensions'=>implode(',', $dimensions),
				'metrics'=>implode(',', $metrics),
				'start-date'=>$start->format('%Y-%m-%d'),
				'end-date'=>$end->format('%Y-%m-%d')
			);

			if ($sort)
				$get_fields['sort'] = $sort;
						
			$url = $this->feed_url.'?'.$this->url_encode_array($get_fields);
			$headers = array('Authorization: Bearer '.$this->auth_token);
								
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
			
			$response = curl_exec($ch);
			$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			
			curl_close($ch);
			
			if ($code != 200)
				throw new Phpr_ApplicationException('Error downloading Google Analytics report. Invalid response from Google Analytics API. Response code: '.$code);

			if (!preg_match(',\</feed\>\s*$,', $response))
				throw new Phpr_ApplicationException('Error downloading Google Analytics report. Response text is not an XML document.');
			
			$ch = null;
			$code = null;
			unset($get_fields);
				
			return $response;
		}
		
		public function request_token()
		{
			if(!$this->code)
				throw new Phpr_ApplicationException('Error requesting Google Analytics token. Invalid response from Google Analytics API.');
						
			$this->log('Requesting Token - Pending');
				
			$data = array(
				'code'=>$this->code,
				'client_id'=>$this->client_id,
				'client_secret'=>$this->client_secret,
				'redirect_uri'=>trim($this->redirect_url),
				'grant_type'=>'authorization_code'
			);
					
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL,$this->token_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->url_encode_array($data));
			$response = curl_exec($ch);
			$info = curl_getinfo($ch);
			curl_close($ch);
			
			$result = json_decode($response);
						
			if(isset($result->error))
			{
				$description = isset($result->error_description) ? ' - '.$result->error_description : '';
				throw new Phpr_ApplicationException('Error requesting Google Analytics token. Invalid response from Google Analytics API. Response code: '.$result->error.$description);
			}
				
			if ($info['http_code'] != 200)
				throw new Phpr_ApplicationException('Error requesting Google Analytics token. Invalid response from Google Analytics API. Response code: '.$info['http_code']);
											
			$params = array(
				'ga_access_token'=>$result->access_token,
				'ga_access_expires'=>Phpr_DateTime::now(),
				'id'=> 1
			);
			
			$refresh_token=null;
			if(isset($result->refresh_token))
			{
				$refresh_token = "ga_refresh_token=:ga_refresh_token, ";
				$params['ga_refresh_token'] = $result->refresh_token;
			}

			Db_Dbhelper::query('update cms_stats_settings set '.$refresh_token.'ga_access_token=:ga_access_token, ga_access_expires=:ga_access_expires where id=:id', $params);
			
			$ch = null;
			$result = null;
			$response = null;
			unset($data, $info, $params);
			
			$this->isLoggedIn = true;
			
			$this->log('Requesting Token - Successful');
						
			Phpr::$response->redirect(Phpr::$request->getCurrentUrl());
		}
		
		public function refresh_token()
		{
			$this->log('Refreshing Access Token - Pending...');
			
			$data = array(
				'refresh_token'=>$this->refresh_token,
				'client_id'=>$this->client_id,
				'client_secret'=>$this->client_secret,
				'grant_type'=>'refresh_token'
			);
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL,$this->refresh_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->url_encode_array($data));
			$response = curl_exec($ch);
			$info = curl_getinfo($ch);
			curl_close($ch);
						
			$result = json_decode($response);
						
			if(isset($result->error))
			{
				$description = isset($result->error_description) ? ' - '.$result->error_description : '';
				throw new Phpr_ApplicationException('Error requesting Google Analytics refresh token. Invalid response from Google Analytics API. Response code: '.$result->error.$description);
			}
				
			if($info['http_code'] != 200)
				throw new Phpr_ApplicationException('Error requesting Google Analytics refresh token. Invalid response from Google Analytics API. Response code: '.$info['http_code']);
											
			$params = array(
				'ga_access_token'=>$result->access_token,
				'ga_access_expires'=>Phpr_DateTime::now(),
				'id'=> 1
			);
			
			Db_Dbhelper::query('update cms_stats_settings set ga_access_token=:ga_access_token, ga_access_expires=:ga_access_expires where id=:id', $params);
			
			$this->log('Refreshing Access Token - Successful');
						
			$ch = null;
			$result = null;
			$response = null;
			unset($data, $info, $params);
		}
		
		private function log($message)
		{
			$this->log[] = $message;
		}
		
		public function read_log($output=false)
		{
			if($output)
				tracelog($this->log);	
		}
		
	}

?>