<?php


class XXX_Socket_Client_FTP_Command extends XXX_Socket_Client
{
	const CLASS_NAME = 'XXX_Socket_Client_FTP_Command';
	
	
	public function __construct ()
	{
		parent::__construct();
		
		$this->settings['blockingMode'] = true;
	}
	
	
	
	protected $authenticated = false;
	
	protected $responseSurplus = array();
	
	
	public function onConnect ()
	{	
		parent::onConnect();
		
		$this->disableBlockingMode();
	}
	
	
	
	
	
	public function sendCommand ($command = '', $expectResponse = true, $clearResponseSurplus = true)
	{
		if ($clearResponseSurplus)
		{
			$this->responseSurplus = array();
		}
		
		/*
		echo "\r\n" . '	>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>' . "\r\n";		
		echo '	Command: ' . $command;		
		echo "\r\n" . '	>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>' . "\r\n";
		*/
		
		$result = false;
		
		$this->enableBlockingMode();
		
		$command .= XXX_String::$lineSeparator;
		
		if ($this->writeQuickAndDirty($command))
		{
			if ($expectResponse)
			{
				$result = $this->getResponse(true);
			}
			else
			{
				$result = true;
			}		
		}
		
		$this->disableBlockingMode();
		
		return $result;
	}
	
	public function getResponse ($expectValue = true)
	{
		$result = false;
		
		$this->disableBlockingMode();
		
		if (XXX_Array::getFirstLevelItemTotal($this->responseSurplus))
		{
			$items = XXX_Array::splitOffFirstItem($this->responseSurplus);
			
			$result = $parts['firstItem'];
			
			$this->responseSurplus = $items['array'];
		}
		else
		{
			$response = '';
			
			$response .= $this->readQuickAndDirty($expectValue);
			
			$code = XXX_Type::makeInteger(XXX_String::getPart($response, 0, 3));
			
			if ($response !== '' && $code > 99)
			{
				$splitResponses = XXX_FileSystem_Remote_FTP_ResponseParser::splitResponses($response);
				
				if ($splitResponses['identical'])
				{
					$result = $splitResponses['responses'][0][0];
				}
				else
				{
					$result = $splitResponses['responses'][0][0];
					
					for ($i = 1, $iEnd = XXX_Array::getFirstLevelItemTotal($splitResponses['responses']); $i < $iEnd; ++$i)
					{
						for ($j = 0, $jEnd = XXX_Array::getFirstLevelItemTotal($splitResponses['responses'][$i]); $j < $jEnd; ++$j)
						{
							$this->responseSurplus[] = $splitResponses['responses'][$i][$j];
						}
					}
				}
			}
		}
		
		/*
		echo "\r\n" . '<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<' . "\r\n";		
		echo 'Response: ' . print_r($result, true);		
		echo "\r\n" . '<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<' . "\r\n";
		*/
		
		return $result;
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	/////////////////////////
	//
	// Connectivity
	//
	/////////////////////////
	
		public function connect ($remoteHost, $remotePort = 21)
		{
			$connected = parent::connect($remoteHost, $remotePort);
			
			if ($connected)
			{
				$response = $this->getResponse();
				
				// OK, welcome message
				if ($response['code'] != 220)
				{
					parent::localDisconnect();
					
					$connected = false;
				}
				else
				{
					$connected = true;
				}
			}
			
			return $connected;
		}
		
		public function disconnect ()
		{
			if ($this->connected)
			{		
				$graceful = false;
				
				$response = $this->sendCommand('QUIT');
					
				// OK, graceful disconnect
				if ($response['code'] == 221)
				{
					$graceful = true;
				}
			}
			
			$this->authenticated = false;
			
							echo 'Disconnected by FTPSocket disconnect';
			return $this->localDisconnect();
		}
		
		public function keepAlive ()
		{
			$result = false;
			
			$response = $this->sendCommand('NOOP');
			
			// OK
			if ($response['code'] == 200)
			{
				$result = true;
			}
			
			return $result;
		}
	
	/////////////////////////
	//
	// Authentication
	//
	/////////////////////////
	
		public function authenticate ($user = '', $pass = '')
		{
			if (!$this->authenticated)
			{		
				$response = $this->sendCommand('USER ' . $user);
				
				// OK, ready for pass input
				if ($response['code'] == 331)
				{
					$response = $this->sendCommand('PASS ' . $pass);
					
					// OK, authenticated
					if ($response['code'] == 230)
					{
						$this->authenticated = true;
					}
				}
			}
			
			return $this->authenticated;
		}
		
		public function isAuthenticated ()
		{
			return $this->authenticated;
		}
	
	/////////////////////////
	//
	// Supported features information
	//
	/////////////////////////
	
		public function detectSystemType ()
		{
			$result = false;
			
			$response = $this->sendCommand('SYST');
			
			// OK, system specification
			if($response['code'] == 215)
			{
				$result = XXX_FileSystem_Remote_FTP_ResponseParser::parseSystemType($response['message']);
			}
			
			return $result;
		}
		
		public function detectFeatures ()
		{
			$result = false;
			
			$response = $this->sendCommand('FEAT');
			
			// OK, listing features
			if ($response['code'] == 211)
			{
				$result = XXX_FileSystem_Remote_FTP_ResponseParser::parseFeatures($response['message']);
			}
			
			return $result;
		}
		
		public function detectServerStatus ()
		{		
			$result = false;
			
			$response = $this->sendCommand('STAT');
			
			// OK, status
			if ($response['code'] == 211)
			{
				$result = XXX_FileSystem_Remote_FTP_ResponseParser::parseServerStatus($response['message']);
			}
			
			return $result;
		}
		
		public function detectHelp ()
		{
			$result = false;
			
			$response = $this->sendCommand('HELP');
			
			// OK, listing supported features
			if ($response['code'] == 214)
			{
				$result = XXX_FileSystem_Remote_FTP_ResponseParser::parseHelp($response['message']);
			}
			
			return $result;
		}
	
	/////////////////////////
	//
	// Data Channel
	//
	/////////////////////////
	
		public function createPassiveDataSocketEndPoint ()
		{
			$result = false;
			
			$response = $this->sendCommand('PASV');
			
			// OK, stating host and port
			if ($response['code'] == 227)
			{
				$result = XXX_FileSystem_Remote_FTP_ResponseParser::parsePASV($response['message']);
			}
			
			return $result;
		}
		
		public function setDataTransferFormat ($dataTransferFormat = 'text')
		{
			$result = false;
			
			$dataTransferFormat = XXX_Default::toOption($dataTransferFormat, array('text', 'binary'), 'binary');
			
			if ($this->dataTransferFormat != $dataTransferFormat)
			{
				$response = $this->sendCommand('TYPE ' . ($dataTransferFormat == 'text' ? 'A' : 'I'));
								
				// OK
				if ($response['code'] == 200)
				{
					$result = true;
				}
				
				$this->dataTransferFormat = $dataTransferFormat;
			}
			else
			{
				$result = true;
			}
			
			return $result;
		}
		
		public function abortDataTransfer ()
		{
			$result = false;
			
			$response = $this->sendCommand('ABOR');
			
			// OK, no transfer in progress
			if ($response['code'] == 225)
			{
				$result = true;
			}
			// OK, closing data channel. transfer in progress aborted
			else if ($response['code'] == 226)
			{
				$result = true;
			}
			
			return $result;
		}
		
		// Keep Alive
		public function noOperation ()
		{
			$result = false;
			
			$response = $this->sendCommand('NOOP');
			
			// OK
			if ($response['code'] == 200)
			{
				$result = true;
			}
			
			return $result;
		}
	
	/////////////////////////
	//
	// Permissions
	//
	/////////////////////////
	
		public function setUmask ($umask = '117')
		{
			$result = false;
					
			$umask = XXX_String::getPart(XXX_String::padLeft($umask, '0', 3), -3, 3);
			
			$response = $this->sendCommand('SITE UMASK ' . $umask);
			
			if ($response['code'] == 200)
			{
				$result = true;
			}
			
			return $result;
		}
		
		public function setIdentifierPermissions ($path = '', $permissions = '660')
		{
			$result = false;
			
			$permissions = XXX_String::getPart(XXX_String::padLeft($permissions, '0', 3), -3, 3);
			
			$response = $this->sendCommand('SITE CHMOD ' . $permissions . ' ' . $path);
			
			if ($response['code'] == 200)
			{
				$result = true;
			}
			
			return $result;
		}
		
	/////////////////////////
	//
	// Identifier
	//
	/////////////////////////
		
		public function doesIdentifierExist ($path = '')
		{
			$result = false;
			
			$response = $this->sendCommand('RNFR ' . $path);
						
			// OK, identifier exists
			if ($response['code'] == 350)
			{
				$response = $this->sendCommand('RNTO ' . $path);
				
				// OK, renamed identical or file left as is
				if ($response['code'] == 250 || $response['code'] == 553 || $response['code'] == 550)
				{
					$result = true;
				}
			}
							
			return $result;
		}
		
		public function moveIdentifier ($oldPath = '', $newPath = '')
		{
			$result = false;
			
			$response = $this->sendCommand('RNFR ' . $oldPath);
			
			// OK, identifier exists
			if ($response['code'] == 350)
			{
				$response = $this->sendCommand('RNTO ' . $newPath);
				
				// OK, renamed
				if ($response['code'] == 250)
				{
					$result = true;
				}
			}
							
			return $result;
		}
		
	/////////////////////////
	//
	// Directory
	//
	/////////////////////////
		
		public function createDirectory ($path = '')
		{
			$result = false;
			
			$response = $this->sendCommand('MKD ' . $path);
			
			// OK, directory created
			if ($response['code'] == 257)
			{
				$result = true;
			}
							
			return $result;
		}
		
		public function changeWorkingDirectory ($path = '')
		{
			$result = false;
			
			$response = $this->sendCommand('CWD ' . $path);
			
			// OK
			if ($response['code'] == 250)
			{
				$result = true;
			}
							
			return $result;
		}
		
		public function getWorkingDirectory ()
		{
			$result = false;
			
			$response = $this->sendCommand('PWD');
			
			// OK
			if ($response['code'] == 257)
			{
				$result = XXX_FileSystem_Remote_FTP_ResponseParser::parseWorkingDirectory($response['message']);
			}
			
			return $result;
		}
		
		public function listMLSD ($path = '')
		{
			$result = false;
			
			$response = $this->sendCommand('MLSD' . (XXX_Type::isValue($path) ? ' ' . $path : ''));
			
			if ($response['code'] == 125 || $response['code'] == 150)
			{
				$result = true;
			}
			
			return $result;
		}
		
		public function listMLST ($path = '')
		{
			$result = false;
			
			$response = $this->sendCommand('MLST' . (XXX_Type::isValue($path) ? ' ' . $path : ''));
			
			if ($response['code'] == 125 || $response['code'] == 150)
			{
				$result = true;
			}
			
			return $result;
		}
		
		public function listLIST ($path = '')
		{
			$result = false;
			
			$response = $this->sendCommand('LIST' . (XXX_Type::isValue($path) ? ' ' . $path : ''));
			
			if ($response['code'] == 125 || $response['code'] == 150)
			{
				$result = true;
			}
			
			return $result;
		}
		
		public function listNLST ($path = '')
		{
			$result = false;
			
			$response = $this->sendCommand('NLST' . (XXX_Type::isValue($path) ? ' ' . $path : ''));
			
			if ($response['code'] == 125 || $response['code'] == 150)
			{
				$result = true;
			}
			
			return $result;
		}
		
		public function deleteDirectory ($path = '')
		{
			$result = false;
			
			$response = $this->sendCommand('RMD ' . $path);
			
			// OK, deleted
			if ($response['code'] == 250)
			{
				$result = true;	
			}
			
			return $result;
		}
		
	/////////////////////////
	//
	// File
	//
	/////////////////////////
		
		public function getFileSize ($path = '')
		{
			$result = false;
				
			$response = $this->sendCommand('SIZE ' . $path);
		
			if ($response['code'] == 213)
			{
				$result = $response['message'];
			}
			
			return $result;
		}
		
		public function getFileModifiedTimestamp ($path = '')
		{
			$result = false;
				
			$response = $this->sendCommand('MDTM ' . $path);
		
			if ($response['code'] == 213)
			{
				$result = $response['message'];
			}
			
			return $result;
		}
		
		public function deleteFile ($path = '')
		{
			$result = false;
					
			$response = $this->sendCommand('DELE ' . $path);
			
			// OK, deleted
			if ($response['code'] == 250)
			{
				$result = true;
			}
			
			return $result;
		}
		
		public function getFileContent ($path = '')
		{
			$result = false;
				
			$response = $this->sendCommand('RETR ' . $path);
		
			if ($response['code'] == 125 || $response['code'] == 150)
			{
				$result = true;
			}
			
			return $result;
		}
		
		public function writeFileContent ($path = '')
		{
			$result = false;
				
			$response = $this->sendCommand('STOR ' . $path);
		
			if ($response['code'] == 125 || $response['code'] == 150)
			{
				$result = true;
			}
			
			return $result;
		}
		
		public function appendFileContent ($path = '')
		{
			$result = false;
			
			$response = $this->sendCommand('APPE ' . $path);
		
			if ($response['code'] == 125 || $response['code'] == 150)
			{
				$result = true;
			}
			
			return $result;
		}
	
	
	
}


?>