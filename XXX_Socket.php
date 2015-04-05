<?php

/*

##################################################


http://blogs.sitepoint.com/php-command-line-1/

aan de andere kant, voor een chat socket server zal je echt niet veel nodig hebben..
100 clients die allemaal 1 x per 5 seconden een string van 100 bytes sturen is 10 kb per 5 seconden (ongeveer).
dat moet je adsl lijntje makkelijk aankunnen en je server thuis ook, tenijz je een hele oude bak hebt....


Use a cronjob to check that it is live every x minutes, make it possible to close via a command in the socket, or a timeout

// Set time limit to indefinite execution
set_time_limit (0); 


http://togagames.com/boards/index.php?topic=185.0
http://www.togagames.com/downloads/socketServer.txt
http://www.chabotc.com/phpsocketdaemon/
http://www.devshed.com/c/a/PHP/An-Introduction-to-Sockets-in-PHP/
http://www.devshed.com/c/a/PHP/Developing-an-Extensible-TCP-Server-with-Sockets-in-PHP/
http://www.devshed.com/c/a/PHP/Emulating-a-Basic-Web-Server-with-Sockets-in-PHP/
http://www.devshed.com/c/a/PHP/Socket-Programming-With-PHP/
http://devzone.zend.com/article/1086
http://nanoserv.si.kz/
http://www.php-cli.com/php-cli-tutorial.shtml

Emulate parallel processing by triggering processes via sockets (NON-blocking) and cancelling/disconnecting them on __destruct (And a Map/Reduce logic)
http://phplens.com/phpeverywhere/?q=node/view/254
http://sebastian-bergmann.de/archives/750-Map-and-Reduce-in-PHP.html

http://nl.php.net/popen
http://www.php.net/manual/en/function.readfile.php
http://www.electrictoolserver.com/php-cli-counter/ (Replacing a line, for example for monitoring purposes)

http://dev.kafol.net/2008/11/writing-chat-server-socket-in-php.html






HTTP server: needs to be triggered onRead when delimiter is reached.
File server: read from local file chunked and write to socket


##################################################







Failure scenario's:
	- open
		- the remote side can't be routed to
		- isn't listening on the accepted port
		- blocked by a firewall
		- the listening service has hung
	- write
		- the connection has already closed
		- the connection closes during transfer
	- read
		- the connection has already closed (or if connectionless the connection fails
		- the connection closes during transfer
		- no data becomes available (or takes too long)
	- close
		- the connection fails
		
##################################################



TODO read maximum. For attacks/errors...




InterProcess communication: http://www.php.net/manual/en/function.socket-create-pair.php

*/

abstract class XXX_Socket
{
	const CLASS_NAME = 'XXX_Socket';
	
	public static $IDCounter = 0;
	
	public $ID;
	public $name = '';
	
	public $socketResource;
	
	public $opened = false;
	public $connected = false;
	public $timedOut = false;
	
	public $settings = array
	(
		'addressingType' => 'ipV4',
		'transportType' => 'tcp',
		
		'localHost' => 0,
		'localPort' => 0,
				
		'readChunkSize' => 4096, // TODO fix that when using low values the first part of the response is always double...
		'writeChunkSize' => 4096,
		
		'osReadBufferSize' => 8192,
		'osWriteBufferSize' => 8192,
		
		'addressReuse' => false,
		'blockingMode' => true,
		
		// Usefull when in non-blocking mode
		'activeOperation' => false,
		
		// recv | read
		'readMethod' => 'recv',
		
		// send | write
		'writeMethod' => 'send'
	);
	
	public $connectedTimestamp = 0; // Used for session time out
	public $createdTimestamp = 0;
	public $lastActionTimestamp = 0; // Used for idle time out
	
	public $sessionTimeOut = 10;
	public $idleTimeOut = 10;
	
	protected $bytesWritten = 0;
	protected $bytesRead = 0;
	
	/////////////////////////
	
	public function __construct ($name = '')
	{
		$this->ID = ++self::$IDCounter;
		
		$this->createdTimestamp = XXX_TimestampHelpers::getCurrentTimestamp();
		
		$this->name = $name;
	}
		
	public function setSettings (array $settings)
	{
		$this->settings = XXX_Array::merge($this->settings, $settings);
		
		$this->settings['addressingType'] = XXX_Default::toOption($this->settings['addressingType'], array('ipV4', 'ipV6', 'unix'), 'ipV4');
		$this->settings['transportType'] = XXX_Default::toOption($this->settings['transportType'], array('tcp', 'udp', 'icmp'), 'tcp');
	}
	
	public function __destruct ()
	{
		$this->close();
	}
	
	public static function isValidResource ($resource)
	{
		return ($resource && is_resource($resource) && get_resource_type($resource) == 'Socket');
	}	
			
	/////////////////////////
	//
	// Opening / Closing
	//
	/////////////////////////
	
		public function open ()
		{
			if (!$this->opened)
			{
				$socketCreationSettings = $this->processSocketCreationSettings($this->settings['addressingType'], $this->settings['transportType']);
								
				// 1. Create a socket
				$this->socketResource = socket_create($socketCreationSettings['addressingType'], $socketCreationSettings['socketType'], $socketCreationSettings['transportType']);
				
				if ($this->isValidResource($this->socketResource))
				{
					if ($this->setAddressReuse($this->settings['addressReuse']))
					{
						$this->setBlockingMode($this->settings['blockingMode']);
						
						$this->opened = true;
						
						$this->onOpen();
					}
				}
			
				
				if (!$this->opened)
				{
					$this->close();
				}
			}
			
			return $this->opened;
		}
		
		public function close ($doNotTriggerCloseEvent = false)
		{
			$result = false;
			
			if ($this->opened)
			{			
				$resourceID = $this->getResourceID();
				
				if ($this->isValidResource($this->socketResource))
				{
					// Shut down for both sending and receiving
					socket_shutdown($this->socketResource, 2);
					
					socket_close($this->socketResource);
				}
				
				$this->socketResource = (unset) $this->socketResource;
				
				// Is needed for the processor.
				$this->socketResource = $resourceID;
				$this->opened = false;
				
				$result = true;
				
				if (!$doNotTriggerCloseEvent)
				{
					$this->onClose();
				}
			}
			
			return $result;
		}
	
		public function localDisconnect ()
		{
			$closed = $this->close();
			
			if ($closed)
			{
				$this->connected = false;
				
				$this->onLocalDisconnect();
				$this->onDisconnect();
			}
		}
		
		public function remoteDisconnect ()
		{
			$closed = $this->close();
						
			if ($closed)
			{
				$this->connected = false;
				
				$this->onRemoteDisconnect();
				$this->onDisconnect();
			}
		}
		
	/////////////////////////
	//
	// Status
	//
	/////////////////////////
	
		public function hasClosed ()
		{
			$result = false;
			
			if (!$this->opened)
			{			
				$result = true;
			}
			else if (!$this->isValidResource($this->socketResource))
			{
				$this->close();
				
				$result = true;
			}
			
			return $result;
		}
				
		public function isOpen ()
		{
			return $this->opened;
		}
		
		public function getResourceID ()
		{
			return (int) $this->socketResource;
		}
		
		public function getResource ()
		{
			return $this->socketResource;
		}
		
		public function getID ()
		{
			return $this->ID;
		}
		
		public function getTimeConnected ()
		{
			return XXX_TimestampHelpers::getCurrentTimestamp() - $this->connectedTimestamp;
		}
		
		public function setConnectedTimestampToNow ()
		{
			$now = XXX_TimestampHelpers::getCurrentTimestamp();
			
			$this->connectedTimestamp = $now;
			$this->lastActionTimestamp = $now;
		}
		
		public function setLastActionTimestampToNow ()
		{
			$this->lastActionTimestamp = XXX_TimestampHelpers::getCurrentTimestamp();
		}
		
		public function checkForSessionAndIdleTimeOuts ()
		{
			$result = false;
			
			$now = XXX_TimestampHelpers::getCurrentTimestamp();
		
			$idleTime = $now - $this->lastActionTimestamp;
			$sessionTime = $now - $this->connectedTimestamp;
			
			if ($this->sessionTimeOut > 0 && $sessionTime > $this->sessionTimeOut)
			{
				$this->onSessionTimeOut();
				
				$this->localDisconnect();
				
				$this->timedOut = true;
			}
			else if ($this->idleTimeOut > 0 && $idleTime > $this->idleTimeOut)
			{
				$this->onIdleTimeOut();
				
				$this->localDisconnect();
				
				$this->timedOut = true;
			}
			
			return $this->timedOut;
		}
		
		public function hasTimedOut ()
		{
			return $this->timedOut;
		}
		
		public function isBlockingModeEnabled ()
		{
			return $this->settings['blockingMode'];
		}
		
		public function isConnected ()
		{
			return $this->connected;
		}
	
	/////////////////////////
	//
	// Settings
	//
	/////////////////////////	
		
		/*
		
		Prefixes:
		
		SO_ = Socket Option		
		SOL_ = Socket Option Level 
		
		AF_ Addressing Family
		PF_ Protocol Family
		
		*For most options to be effective, socket_set_option Should be called before bind, connect etc.
		
		*/
				
		/*		
		
		TCP: socket_create(AF_INET, SOCK_STREAM, SOL_TCP); 
		UDP: socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		ICMP: socket_create(AF_INET, SOCK_RAW, 1);
				
		TCP: Connection required. Guarantees that no data is lost (otherwise it will be re-transmitted). Guarantees the order. Well suited for sending images, files, emails etc. (Everything that needs to be received whole/complete).
		UDP: Connectionless. Does not guarantee delivery or order. Well suited for streaming data such as video or audio (If data is lost, there is no point in trying to get it again as the part may have already been played.)
		ICMP: For ping requests etc.
		
		*/
		
		public static function processSocketCreationSettings ($addressingType = 'ipV4', $transportType = 'tcp')
		{
			switch ($addressingType)
			{
				case 'unix':
				case AF_UNIX:
					$addressingType = AF_UNIX;
					break;
				case 'ipV6':
				case AF_INET6:
					$addressingType = AF_INET6;
					break;
				case 'ipV4':
				case AF_INET:
				default:
					$addressingType = AF_INET;
					break;
			}
					
			switch ($transportType)
			{
				case 'icmp':
				case SOL_ICMP:
					$socketType = SOCK_RAW;
					if (XXX_Type::isConstant('SOL_ICMP'))
					{
						$transportType = SOL_ICMP;
					}
					else
					{
						$transportType = getprotobyname('icmp');
					}
					break;
				case 'udp':
				case SOL_UDP:
					$socketType = SOCK_DGRAM;
					$transportType = SOL_UDP;
					break;
				case 'tcp':
				case SOL_TCP:
				default:
					$socketType = SOCK_STREAM;
					$transportType = SOL_TCP;
					break;
			}
			
			$result = array
			(
				'addressingType' => $addressingType,
				'socketType' => $socketType,
				'transportType' => $transportType
			);
			
			return $result;
		}
		
		/*
		
		Buffer sizes: The size the kernel allocates to buffer either read or write operations between the program that owns the socket and the actual operation at kernel level.
			- Read: The time between the time data arrives and is read by the program that owns the socket.
			- Write: The time the data is written by the program that owns the socket and the data is actually sent.
		
		SO_RCVBUF: 
			- TCP: If data arrives on the network and the program that owns the socket isn't reading it, the buffer is filled up. (And the sender is told to slow down (using the TCP window adjustment mechanism).
			- UDP: For UDP, once full, new incoming packets are dropped.
		
		SO_SNDBUF:
			- TCP:
				- If the remote side isn't reading, (For example: the remote buffer becomes full, then TCP communicates this back to your kernel, and your kernel stops sending data and instead accumulates it in the local buffer untill it fills up.)
				- If there is a network problem and the kernel isn't getting acknowledgements the data is sent. It will then slow down sending the data untill eventually the outgoing buffer fills up. If so future write() calls to this socket by the application will block or return EAGAIN if it's non blocking.
			- UDP: Whatever you send goes directly out to the network.		
		
		*/
		
		public function setOperationBufferSize ($bufferSize = 8192, $type = 'read')
		{
			$result = false;
			
			$bufferSize = XXX_Default::toPositiveInteger($bufferSize, 8192);
			$type = XXX_Default::toOption($type, array('read', 'write'), 'read');
			
			$setOption = socket_set_option($this->socketResource, SOL_SOCKET, ($type == 'read' ? SO_RCVBUF : SO_SNDBUF), $bufferSize);
			
			if ($setOption !== false)
			{
				$this->settings['os' . XXX_String::capitalizeFirstWord($type) . 'BufferSize'] = $bufferSize;
				
				$result = true;
			}
			else
			{
				XXX_Debug::errorNotification(array(self::CLASS_NAME, 'setOperationBufferSize'), 'Unable to set buffer size "' . $bufferSize . '" ("' . $type . '") option for "' . $this->getLocalEndPointDescription() . '" | PHP: "' . $this->getError() . '"');
			}
			
			return $result;
		}
		
		public function setWriteBufferSize ($bufferSize = 8192)
		{
			return $this->setOperationBufferSize($bufferSize, 'write');
		}
		
		public function setReadBufferSize ($bufferSize = 8192)
		{
			return $this->setOperationBufferSize($bufferSize, 'read');
		}
		
		/*
		
		SO_RCVTIMEO & SO_SNDTIMEO are probably the most widely unimplemented or incompatible options.
		Avoid using them. You can better roll your own. Or use non-blocking sockets with a select based time-out.
				
		Receive time-out for blocking receive (read) calls.
		
		*/
			
		public function setOperationTimeOut ($seconds = 1, $type = 'read')
		{
			$result = false;
			
			$seconds = XXX_Default::toPositiveInteger($seconds, 1);
			$type = XXX_Default::toOption($type, array('read', 'write'), 'read');
			
			$setOption = socket_set_option($this->socketResource, SOL_SOCKET, ($type == 'read' ? SO_RCVTIMEO : SO_SNDTIMEO), array('sec' => $seconds, 'usec' => 0));
			
			if ($setOption !== false)
			{
				$this->settings[$type . 'TimeOut'] = $seconds;
				
				$result = true;
			}
			else
			{
				XXX_Debug::errorNotification(array(self::CLASS_NAME, 'setOperationTimeOut'), 'Unable to set time out "' . $seconds . '" ("' . $type . '") option for "' . $this->getLocalEndPointDescription() . '" | PHP: "' . $this->getError() . '"');
			}
			
			return $result;
		}
		
		public function setWriteTimeOut ($seconds = 1)
		{
			return $this->setOperationTimeOut($seconds, 'write');
		}
		
		public function setReadTimeOut ($seconds = 1)
		{
			return $this->setOperationTimeOut($seconds, 'read');
		}
		
		/*
		
		Blocking mode: Waiting for input to become available or output to be cleared.
		Non-Blocking mode: Just check if input is available at once or output is written at once.
		
		*/
		
		public function setBlockingMode ($blockingMode = true)
		{
			$result = false;
			
			if ($blockingMode)
			{
				$result = socket_set_block($this->socketResource);
			}
			else
			{
				$result = socket_set_nonblock($this->socketResource);
			}
			
			if ($result !== false)
			{
				$this->settings['blockingMode'] = $blockingMode ? true : false;
				
				$result = true;
			}
			else
			{
				XXX_Debug::errorNotification(array(self::CLASS_NAME, 'setBlockingMode'), 'Unable to set blocking mode "' . ($blockingMode ? 'blocking' : 'non-blocking') . '" for "' . $this->getLocalEndPointDescription() . '" | PHP: "' . $this->getError() . '"');
			}
			
			return $result;
		}
		
		public function enableBlockingMode ()
		{
			return $this->setBlockingMode(true);
		}
		
		public function disableBlockingMode ()
		{
			return $this->setBlockingMode(false);
		}
		
		/*
		
		SO_REUSEADDR allows a socket to bind to an address already in use by another socket. (In other words "hijack" it.)
				
		http://msdn.microsoft.com/en-us/library/ms740621%28v=vs.85%29.aspx
		
		Socket security
		
			Processes that bind to the same port previously bound by another application process.
			Which makes it possible for an application to "hijack" the port of another application, which could easily lead to a "denial of service" attack or data theft.
						
			"ephemeral" local ports: Any random port that is free/available. Ports > 1023
			static "service" ports:  A specificly appointed port Ports < 1024
			
			socketClients should always bind to "ephemeral" local ports by specifying 0 and 0 for host (SOCKADDR) when calling the bind method (Let the OS decide)
			
			socketServers normally bind to static "service" ports.
		
			So for most applications, there is not usually a conflict for bind requests between client and server applications.
		
		The SO_REUSEADDR socket option allows a socket to forcibly bind to a port already in use by another service.
		
		*/
		
		public function setAddressReuse ($reuse = false)
		{
			$result = false;
			
			$setOption = socket_set_option($this->socketResource, SOL_SOCKET, SO_REUSEADDR, ($reuse ? 1 : 0));
			
			if ($setOption !== false)
			{
				$result = true;
			}
			else
			{
				XXX_Debug::errorNotification(array(self::CLASS_NAME, 'setAddressReuse'), 'Unable to set address reuse ("' . ($reuse ? 'reuse' : 'don\t reuse') . '") option for "' . $this->getLocalEndPointDescription() . '" | PHP: "' . $this->getError() . '"');
			}
			
			return $result;
		}
		
		public function allowAddressReuse ()
		{
			return $this->setAddressReuse(true);
		}
		
		public function disallowAddressReuse ()
		{
			return $this->setAddressReuse(false);
		}
		
		
		public function setReadMethod ($readMethod = 'recv')
		{
			if ($readMethod == 'recv' || $readMethod == 'read')
			{
				$this->settings['readMethod'] = $readMethod;
			}
		}
		
		public function setWriteMethod ($writeMethod = 'send')
		{
			if ($writeMethod == 'send' || $writeMethod == 'write')
			{
				$this->settings['writeMethod'] = $writeMethod;
			}
		}
		
		// How many bytes at once per operation
		public function setChunkSize ($chunkSize = 8192, $type = 'read')
		{
			$result = false;
			
			$chunkSize = XXX_Default::toPositiveInteger($chunkSize, 8192);
			$type = XXX_Default::toOption($type, array('read', 'write'), 'read');
			
			$this->settings[$type . 'ChunkSize'] = $chunkSize;
			
			$result = true;
			
			return $result;
		}
		
		public function setWriteChunkSize ($chunkSize = 8192)
		{
			return $this->setChunkSize($chunkSize, 'write');
		}
		
		public function setReadChunkSize ($chunkSize = 8192)
		{
			return $this->setChunkSize($chunkSize, 'read');
		}
				
	/////////////////////////
	//
	// Information
	//
	/////////////////////////		
		
		/*
		
		Works with:
			- socket_connect resources (unless 'unix' addressingType)
			- socket_accept resources
			- primary server based on socket_create resources followed by socket_bind
		
		*/
		
		public function getLocalEndPointInformation ()
		{
			$result = false;
			
			$informationAvailable = socket_getsockname($this->socketResource, $host, $port);
			
			if ($informationAvailable)
			{
				$result = array
				(
					'host' => $host,
					'port' => $port
				);
			}
			
			return $result;
		}
		
		public function getLocalEndPointDescription ()
		{
			$result = '';
			
			$localEndPointInformation = $this->getLocalEndPointInformation();
			
			if ($localEndPointInformation)
			{
				$result = $localEndPointInformation['host'] . ($localEndPointInformation['port'] ? ':' . $localEndPointInformation['port'] : '');
				
				$result .= ' (' . $this->socketResource . ')';
			}
			
			return $result;
		}
		
		/*
		
		Works with:
			- socket_accept resources (unless 'unix' addressingType)
			- socket_connect resources
			- primary server based on socket_create resources followed by socket_bind
		
		*/
		
		public function getRemoteEndPointInformation ()
		{
			$result = false;
			
			$informationAvailable = socket_getpeername($this->socketResource, $host, $port);
			
			if ($informationAvailable)
			{
				$result = array
				(
					'host' => $host,
					'port' => $port
				);
			}
			
			return $result;
		}
		
		public function getRemoteEndPointDescription ()
		{
			$result = '';
			
			$remoteEndPointInformation = $this->getRemoteEndPointInformation();
			
			if ($remoteEndPointInformation)
			{
				$result = $remoteEndPointInformation['host'] . ($remoteEndPointInformation['port'] ? ':' . $remoteEndPointInformation['port'] : '');
				
				$result .= ' (' . $this->socketResource . ')';
			}
			
			return $result;
		}
		
		public function getError ()
		{			
			$code = socket_last_error($this->socketResource);
			
			$description = socket_strerror($code);
			
			// Clear, otherwise, if run as a server, it might flood the memory.
			socket_clear_error($this->socketResource);
						
			$result = array
			(
				'code' => $code,
				'description' => $description
			);
			
			return $result;
		}
		
	/////////////////////////
	//
	// Events
	//
	/////////////////////////	
		
		// Triggered when a socket is opened (either by "open" or via socket_accept etc.)
		public function onOpen ()
		{
			XXX_Log::logLine('		Client ' . $this->ID . ': Open', 'daemon');
		}
		
		// Triggered when a socket is closed
		public function onClose ()
		{
			XXX_Log::logLine('		Client ' . $this->ID . ': Closed', 'daemon');
		}
		
		public function onSessionTimeOut ()
		{
			XXX_Log::logLine('		Client ' . $this->ID . ': Session timed out', 'daemon');	
		}
		
		public function onIdleTimeOut ()
		{
			XXX_Log::logLine('		Client ' . $this->ID . ': Idle time out', 'daemon');	
		}
		
		public function onDisconnect ()
		{
			XXX_Log::logLine('		Client ' . $this->ID . ': Disconnected', 'daemon');
		}
		
		public function onRemoteDisconnect ()
		{
			XXX_Log::logLine('		Client ' . $this->ID . ': Disconnected remotely.', 'daemon');
		}
		
		public function onLocalDisconnect ()
		{
			XXX_Log::logLine('		Client ' . $this->ID . ': Disconnected locally.', 'daemon');
		}
}

?>