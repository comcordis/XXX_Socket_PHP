<?php

abstract class XXX_Socket_Server extends XXX_Socket
{
	const CLASS_NAME = 'XXX_Socket_Server';
	
	public $started = false;
	
	protected $clients = array();
	
	protected $clientLimit = 512;
	protected $queueLimit = 256;
	
	protected $clientsConnected = 0;
	protected $clientsDisconnected = 0;
		
	protected $lastServerTimerEventTimestamp = 0;
	protected $serverTimerInterval = 5;
		
	protected $lastClientTimerEventTimestamp = 0;
	protected $clientTimerInterval = 5;
	
	protected $socketServerClientClass = 'XXX_Socket_ServerClient';

	public function __construct ($name = '')
	{
		parent::__construct($name);
		
		$this->lastServerTimerEventTimestamp = $this->lastClientTimerEventTimestamp = XXX_TimestampHelpers::getCurrentTimestamp();
				
		$this->clients = array();
	}
	
	public function __destruct ()
	{
		$this->stop();
	}
	
	public function setSocketServerClientClass ($socketServerClientClass = 'XXX_Socket_ServerClient')
	{
		$this->socketServerClientClass = 'XXX_Socket_ServerClient';
	}
		
	public function checkForTimerEvents ()
	{
		$now = XXX_TimestampHelpers::getCurrentTimestamp();
		
		if ($this->serverTimerInterval > 0 && $now - $this->lastServerTimerEventTimestamp >= $this->serverTimerInterval)
		{
			$this->onTimer();
		
			$this->lastServerTimerEventTimestamp = $now;
		}
		
		if ($this->serverTimerInterval > 0 && $now - $this->lastClientTimerEventTimestamp >= $this->serverTimerInterval)
		{
			foreach ($this->clients as $clientID)
			{
				$socketServerClientInstance = XXX_SocketProcessor::getSocketClientInstanceForID($clientID);
				
				if ($socketServerClientInstance !== false)
				{
					$socketServerClientInstance->checkForSessionAndIdleTimeOuts();
					
					$socketServerClientInstance->onTimer();
				}
			}
			
			$this->lastClientTimerEventTimestamp = $now;
		}
	}
		
	public function start ($localHost = 0, $localPort = 0)
	{
		$this->started = false;
		
		$opened = $this->open();
		
		if ($opened)
		{
			$bound = $this->bind($localHost, $localPort);
			
			if ($bound)
			{
				$listening = $this->listen($this->queueLimit);
				
				if ($listening)
				{
					$this->setConnectedTimestampToNow();
					$this->connected = true;
					
					$this->started = true;
					
					$this->onStart();
				}
			}
		}
		
		if (!$this->started)
		{
			$this->close();
		}
		
		return $this->started;
	}
	
	public function stop ()
	{
		if ($this->started)
		{
			$this->disconnectAllClients();
			
			$this->close();
			
			$this->onStop();
		}
	}
	
	public function hasReachedClientLimit ()
	{
		return (XXX_Array::getFirstLevelItemTotal($this->clients) == $this->clientLimit);
	}
	
	public function addClientID ($clientID = 0)
	{
		$clientID = XXX_Default::toPositiveInteger($clientID, 0);
		
		if (!XXX_Array::hasValue($this->clients, $clientID))
		{
			$this->clients[] = $clientID;
			
			++$this->clientsConnected;
		}
	}
	
	public function removeClientID ($clientID = 0)
	{
		$clientID = XXX_Default::toPositiveInteger($clientID, 0);
		
		$key = XXX_Array::getKeyForValue($this->clients, $clientID);
		
		if ($key != -1)
		{
			unset($this->clients[$key]);
			
			++$this->clientsDisconnected;
			
			$this->onClientDisconnect($clientID);
		}
		
		if (XXX_Array::getFirstLevelItemTotal($this->clients) == 0)
		{
			$this->onNoClients();
		}
	}
	
	public function disconnectAllClients ()
	{
		foreach ($this->clients as $clientID)
		{
			$socketServerClientInstance = XXX_SocketProcessor::getSocketClientInstanceForID($clientID);
			
			if ($socketServerClientInstance !== false)
			{
				$this->removeClientID($clientID);
				
				$socketServerClientInstance->localDisconnect();
			}
		}
		
		$this->onDisconnectAllClients();
		$this->onNoClients();
	}
	
	/////////////////////////
	//
	// Statistics
	//
	/////////////////////////
	
		public function addBytesRead ($bytesRead = 0)
		{
			$this->bytesRead += $bytesRead;
		}
		
		public function addBytesWritten ($bytesWritten = 0)
		{
			$this->bytesWritten += $bytesWritten;
		}
		
		public function getStatistics ()
		{
			$now = XXX_TimestampHelpers::getCurrentTimestamp();
			
			// TODO requests per second
			// TODO data per second
			// TODO clients per second
			
			$result = array
			(
				'connectedTimestamp' => $this->connectedTimestamp,
			 
				'lifeTime' => $now - $this->connectedTimestamp,
				
				'clientsConnected' => $this->clientsConnected,
				'clientsDisconnected' => $this->clientsDisconnected,
				
				'clients' => $this->clientsConnected - $this->clientsDisconnect,
				
				'bytesRead' => $this->bytesRead,
				'bytesWritten' => $this->bytesWritten
			);
			
			return $result;
		}
	
	/////////////////////////
	//
	// Clients
	//
	/////////////////////////
		
		public function bind ($localHost = 0, $localPort = 0)
		{	
			$result = false;
			
			// 1. Bind it to a name
			if (socket_bind($this->socketResource, $localHost, $localPort))
			{
				// 2. Get information on the local side of the socket (Check-up)
				$localEndPointInformation = $this->getLocalEndPointInformation();
				
				if ($localEndPointInformation !== false)
				{
					$this->settings['localHost'] = $localEndPointInformation['host'];
					$this->settings['localPort'] = $localEndPointInformation['port'];
					
					$result = true;
				}
			}
			
			if (!$result)
			{
				$this->close();
			}
			
			return $result;
		}
		
		/*
		
		Backlog (queueLimit): A maximum of backlog incoming connections will be queued for processing.
		If a connection request arrives with the queue full the client may receive an error with an indication of ECONNREFUSED, or, if the underlying protocol supports retransmission, the request may be ignored so that retries may succeed.
		
		*/
		
		public function listen ($queueLimit = 16)
		{
			$result = false;
			
			$queueLimit = XXX_Default::toPositiveInteger($queueLimit, 16);
			
			$listening = socket_listen($this->socketResource, $queueLimit);
			
			if ($listening !== false)
			{
				$result = true;
			}
			else
			{
				XXX_Debug::errorNotification(array(self::CLASS_NAME, 'listen'), 'Could not listen on "' . $this->getLocalEndPointDescription() . '" | PHP: "' . $this->getError() . '"');
			}
			
			return $result;
		}
		
		/*
		
		The server itself doesn't do anything except waiting for client connections.
		Once a client connection is received, the socket_accept() function springs into action.
		It accepts the connection request and spawning another child socket to handle all subsequent communication between the client and the server.
		
		*/
		
		public function acceptClient ()
		{
			$result = false;
			
			if (!$this->hasReachedClientLimit())
			{			
				$socketResource = socket_accept($this->socketResource);
					
				if ($socketResource !== false && $this->isValidResource($socketResource))
				{
					$socketServerClientInstance = new $this->socketServerClientClass();
					
					$usedAcceptedClientSocketResource = $socketServerClientInstance->useAcceptedClientSocketResource($socketResource);
					
					if ($usedAcceptedClientSocketResource)
					{			
						if (!is_subclass_of($socketServerClientInstance, 'XXX_Socket_ServerClient'))
						{
							XXX_Debug::errorNotification(array(self::CLASS_NAME, 'acceptClient'), 'Invalid socketServerClient class specified. It has to be a sub class of XXX_Socket_ServerClient.');
						}
						else
						{
							// References from both sides
							$socketServerClientInstance->setServerID($this->ID);
							$this->addClientID($socketServerClientInstance->getID());
							
							$this->onAcceptClient($socketServerClientInstance->getID());
							
							$result = $socketServerClientInstance;
							
							if ($this->hasReachedClientLimit())
							{
								$this->onClientLimitReached();
							}
						}		
					}
					else
					{
						$this->onRejectClient();
				
						XXX_Debug::errorNotification(array(self::CLASS_NAME, 'acceptClient'), 'Invalid socket resource for client on "' . $this->getLocalEndPointDescription() . '" | PHP: "' . $this->getError() . '"');
					}	
				}
				else
				{
					$this->onRejectClient();
				
					XXX_Debug::errorNotification(array(self::CLASS_NAME, 'acceptClient'), 'Could not accept client on "' . $this->getLocalEndPointDescription() . '" | PHP: "' . $this->getError() . '"');
				}
			}
			else
			{
				$this->onRejectClient();
								
				XXX_Debug::errorNotification(array(self::CLASS_NAME, 'acceptClient'), 'Rejected client on "' . $this->getLocalEndPointDescription() . '" because the clientLimit is reached "' . $this->clientLimit . '" | PHP: "' . $this->getError() . '"');
			}
			
			return $result;
		}

	/////////////////////////
	//
	// Events
	//
	/////////////////////////
	
		// Triggered after the client is accepted and ready
		public function onAcceptClient ($clientID)
		{
			XXX_Log::logLine('	Server ' . $this->ID . ($this->name != '' ? (' (' . $this->name . ')') : '') . ': Accepted client ' . $clientID . '.', 'daemon');
		}
		
		// Triggered after the client is rejected
		public function onRejectClient ()
		{
			XXX_Log::logLine('	Server ' . $this->ID . ($this->name != '' ? (' (' . $this->name . ')') : '') . ': Rejected a client.', 'daemon');
		}
		
		// Triggered after all clients are disconnected
		public function onDisconnectAllClients ()
		{
			XXX_Log::logLine('	Server ' . $this->ID . ($this->name != '' ? (' (' . $this->name . ')') : '') . ': All clients were/have been disconnected.', 'daemon');
		}
		
		// Triggered after the client was/has disconnected
		public function onClientDisconnect ($clientID)
		{
			XXX_Log::logLine('	Server ' . $this->ID . ($this->name != '' ? (' (' . $this->name . ')') : '') . ': Client ' . $clientID . ' was/has disconnected.', 'daemon');
		}
		
		public function onClientLimitReached ()
		{
			XXX_Log::logLine('	Server ' . $this->ID . ($this->name != '' ? (' (' . $this->name . ')') : '') . ': Reached client limit ' . $this->clientLimit . '.', 'daemon');
		}
		
		// Triggered after the server is ready and listening for client
		public function onStart ()
		{
			XXX_Log::logLine('	Server ' . $this->ID . ($this->name != '' ? (' (' . $this->name . ')') : '') . ': Started', 'daemon');
		}
		
		public function onStop ()
		{
			XXX_Log::logLine('	Server ' . $this->ID . ($this->name != '' ? (' (' . $this->name . ')') : '') . ': Stopped', 'daemon');
		}
		
		public function onNoClients ()
		{
			XXX_Log::logLine('	Server ' . $this->ID . ($this->name != '' ? (' (' . $this->name . ')') : '') . ': No clients.', 'daemon');
		}
		
		public function onTimer ()
		{
			XXX_Log::logLine('	Server ' . $this->ID . ($this->name != '' ? (' (' . $this->name . ')') : '') . ': Timer.', 'daemon');
		}
}


?>