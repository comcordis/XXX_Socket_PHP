<?php

abstract class XXX_SocketProcessor
{
	const CLASS_NAME = 'XXX_SocketProcessor';
	
	// Overall limit
	public static $clientLimit = 1024;
	
	// Seconds to sleep if nothing happens
	public static $idleTimeOut = 0.1;
	
	// How long to block waiting for things to happen
	public static $awaitEventBlockingTimeOut = 2;
	
	public static $lastProcessorTimerEventTimestamp;
	public static $processorTimerInterval = 5;
	
	// Resource to instance mapping
	public static $socketServerInstances = array();
	public static $socketClientInstances = array();
	
	// Instance to resource mapping
	public static $socketServerInstanceIDs = array();
	public static $socketClientInstanceIDs = array();

	/////////////////////////
	//
	// Registering
	//
	/////////////////////////	
	
		public static function registerServerInstance ($socketServerInstance)
		{
			$result = false;
			
			if (!is_subclass_of($socketServerInstance, 'XXX_Socket_Server'))
			{
				XXX_Debug::errorNotification(array(self::CLASS_NAME, 'registerServerInstance'), 'Invalid socketServer class specified. It has to be a sub class of XXX_Socket_Server.');
			}
			else
			{
				$resourceID = $socketServerInstance->getResourceID();
					
				self::$socketServerInstances[$resourceID] = $socketServerInstance;
				self::$socketServerInstanceIDs[$socketServerInstance->getID()] = $resourceID;
				
				$result = true;
			}
			
			return $result;
		}
		
		public static function registerClientInstance ($socketClientInstance)
		{
			$result = false;
			
			if (!is_subclass_of($socketClientInstance, 'XXX_Socket_Client'))
			{
				XXX_Debug::errorNotification(array(self::CLASS_NAME, 'registerClientInstance'), 'Invalid socketClient class specified. It has to be a sub class of XXX_Socket_Client.');
			}
			else
			{
				$resourceID = $socketClientInstance->getResourceID();
					
				self::$socketClientInstances[$resourceID] = $socketClientInstance;
				self::$socketClientInstanceIDs[$socketClientInstance->getID()] = $resourceID;
				
				$result = true;
			}
			
			return $result;
		}
	
	
	/////////////////////////
	//
	// Resources for processing
	//
	/////////////////////////	
	
		public static function getSocketResourcesToCheckForReadChanges ()
		{
			$socketResources = array();
			
			foreach (self::$socketClientInstances as $socketClientInstance)
			{
				$socketResources[] = $socketClientInstance->socketResource;
			}
			
			foreach (self::$socketServerInstances as $socketServerInstance)
			{
				$socketResources[] = $socketServerInstance->socketResource;
			}
			
			return $socketResources;
		}
	
		public static function getSocketResourcesToCheckForWriteChanges ()
		{
			$socketResources = array();
			
			foreach (self::$socketClientInstances as $socketClientInstance)
			{
				if ($socketClientInstance->isWriting())
				{
					$socketResources[] = $socketClientInstance->socketResource;
				}
			}
			
			return $socketResources;
		}
	
		public static function getSocketResourcesToCheckForExceptions ()
		{
			return self::getSocketResourcesToCheckForReadChanges();
		}

	/////////////////////////
	//
	// Information / Other
	//
	/////////////////////////	
	
		public static function cleanUp ()
		{
			foreach (self::$socketClientInstances as $socketClientInstance)
			{
				if ($socketClientInstance->hasClosed())
				{
					$ID = $socketClientInstance->getID();
					$resourceID = $socketClientInstance->getResourceID();
					
					$socketClientInstance->__destruct();
					
					if (isset(self::$socketClientInstances[$resourceID]))
					{
						unset(self::$socketClientInstances[$resourceID]);
					}
					
					if (isset(self::$socketClientInstanceIDs[$ID]))
					{
						unset(self::$socketClientInstanceIDs[$ID]);
					}
				}
			}
			
			foreach (self::$socketServerInstances as $socketServerInstance)
			{
				if ($socketServerInstance->hasClosed())
				{
					$ID = $socketServerInstance->getID();
					$resourceID = $socketServerInstance->getResourceID();
					
					$socketServerInstance->__destruct();
					
					if (isset(self::$socketServerInstances[$resourceID]))
					{
						unset(self::$socketServerInstances[$resourceID]);
					}
					
					if (isset(self::$socketServerInstanceIDs[$ID]))
					{
						unset(self::$socketServerInstanceIDs[$ID]);
					}
				}
			}
		}
		
		public static function canAcceptMoreClients ()
		{
			return (XXX_Array::getFirstLevelItemTotal(self::$socketClientInstances) < self::$clientLimit);
		}
	
	/////////////////////////
	//
	// Instances for management/communication
	//
	/////////////////////////	
	
		public static function getSocketClassInstanceForSocketResource ($socketResource)
		{
			$resourceID = (int) $socketResource;
			
			$result = false;
			
			if (isset(self::$socketClientInstances[$resourceID]))
			{
				$result = self::$socketClientInstances[$resourceID];
			}
			else if (isset(self::$socketServerInstances[$resourceID]))
			{
				$result = self::$socketServerInstances[$resourceID];
			}
			
			return $result;
		}
	
		public static function getSocketServerInstanceForID ($ID)
		{
			$result = false;
			
			if (isset(self::$socketServerInstanceIDs[$ID]))
			{
				$resourceID = self::$socketServerInstanceIDs[$ID];
				
				if ($resourceID)
				{
					$result = self::getSocketClassInstanceForSocketResource($resourceID);
				}
			}
			
			return $result;
		}
	
		public static function getSocketClientInstanceForID ($ID)
		{
			$result = false;
			
			if (isset(self::$socketClientInstanceIDs[$ID]))
			{
				$resourceID = self::$socketClientInstanceIDs[$ID];
				
				if ($resourceID)
				{
					$result = self::getSocketClassInstanceForSocketResource($resourceID);
				}
			}
			
			return $result;
		}

	/////////////////////////
	//
	// Process
	//
	/////////////////////////	
	
		public static function process ()
		{
			$now = XXX_TimestampHelpers::getCurrentTimestamp();
			
			$timerEventTimestamp = $now;	
			self::$lastProcessorTimerEventTimestamp = $now;			
			
			// Run endlessly
			while (true)
			{
				$readSocketResources = self::getSocketResourcesToCheckForReadChanges();
				$writeSocketResources = self::getSocketResourcesToCheckForWriteChanges();
				$exceptionSocketResources = self::getSocketResourcesToCheckForExceptions();
				
				/*
				
				read: The sockets listed in the read array will be watched to see if characters become available for reading (more precisely, to see if a read will not block - in particular, a socket resource is also ready on end-of-file, in which case a socket_read() will return a zero length string).
				write: The sockets listed in the write array will be watched to see if a write will not block.
				except: The sockets listed in the except array will be watched for exceptions.
		
				On success: integer of socket resources contained in the modified arrays. (May be 0 if timed out)
				On error: false (The error code can be retrieved with socket_last_error())
				
				Be aware that some socket implementations need to be handled very carefully. A few basic rules:
					- You should always try to use socket_select() without timeout. Your program should have nothing to do if there is no data available. Code that depends on timeouts is not usually portable and difficult to debug.
					- No socket resource must be added to any set if you do not intend to check its result after the socket_select() call, and respond appropriately.
						After socket_select() returns, all socket resources in all arrays must be checked.
						Any socket resource that is available for writing must be written to.
						Any socket resource available for reading must be read from.
					- If you read/write to a socket returns in the arrays be aware that they do not necessarily read/write the full amount of data you have requested. Be prepared to even only be able to read/write a single byte.
					- It's common to most socket implementations that the only exception caught with the except array is out-of-bound data received on a socket.			
					
					The tv_sec and tv_usec together form the timeout parameter. The timeout is an upper bound on the amount of time elapsed before socket_select() return. tv_sec may be zero , causing socket_select() to return immediately. This is useful for polling. If tv_sec is NULL (no timeout), socket_select() can block indefinitely.
				
				socket_select: Only needs the native PHP resources, not the custom class instances.
				
				*/
				
				// Blocks untill something changed or times out
				$events = socket_select($readSocketResources, $writeSocketResources, $exceptionSocketResources, self::$awaitEventBlockingTimeOut);
								
				if ($events === false)
				{
					break;
				}
				else if ($events == 0)
				{
					if (self::$idleTimeOut > 0)
					{
						// Saves resources but hurts responsiveness
						usleep(self::$idleTimeOut * 1000000);
					}
				}
				else if ($events > 0)
				{
					// Sockets from which their reads didn't block (read response available)
					foreach ($readSocketResources as $socketResource)
					{
						$socketClassInstance = self::getSocketClassInstanceForSocketResource($socketResource);
						
						if ($socketClassInstance)
						{
							// Incoming clients
							if (is_subclass_of($socketClassInstance, 'XXX_Socket_Server'))
							{
								$socketServerInstance = $socketClassInstance;
								
								if (self::canAcceptMoreClients())
								{								
									$socketClientInstance = $socketServerInstance->acceptClient();
									
									if ($socketClientInstance)
									{								
										self::registerClientInstance($socketClientInstance);							
									}
								}
								else
								{
									self::onClientLimitReached();
									
									$socketServerInstance->onRejectClient();
								}
							}
							// Regular read
							else if (is_subclass_of($socketClassInstance, 'XXX_Socket_Client'))
							{
								$socketClientInstance = $socketClassInstance;
								
								// If it's not reading yet, start reading and read first part
								if (!$socketClientInstance->isReading())
								{
									if ($socketClientInstance->startReading())
									{
										$socketClientInstance->readAvailableChunks();
									}
								}							
								// If it's already reading read part
								else
								{
									$socketClientInstance->readAvailableChunks();
								}
							}
						}
					}
					
					// Sockets from which their writes didn't block (written successfully, ready to write more)
					foreach ($writeSocketResources as $socketResource)
					{
						$socketClassInstance = self::getSocketClassInstanceForSocketResource($socketResource);
						
						if ($socketClassInstance)
						{
							if (is_subclass_of($socketClassInstance, 'XXX_Socket_Client'))
							{
								$socketClientInstance = $socketClassInstance;
								
								// Connected properly
								if ($socketClientInstance->connecting === true)
								{
									$socketClientInstance->connecting = false;
									$socketClientInstance->connected = true;
									
									$socketClientInstance->onConnect();
								}
								
								$socketClientInstance->writeAvailableChunks();
							}
						}
					}
					
					// Sockets that triggered exceptions (errors)
					foreach ($exceptionSocketResources as $socketResource)
					{
						$socketClassInstance = self::getSocketClassInstanceForSocketResource($socketResource);
						
						if ($socketClassInstance)
						{
							if (is_subclass_of($socketClassInstance, 'XXX_Socket_Client'))
							{
								$socketClientInstance = $socketClassInstance;
								
								echo 'Disconnected on failed exception by processor';
								$socketClientInstance->remoteDisconnect();
							}
						}
					}
				}
				
				$now = XXX_TimestampHelpers::getCurrentTimestamp();
				
				// Only do this if more than a second passed, otherwise we would keep looping this for every byte received
				if ($now - $timerEventTimestamp >= 1)
				{
					if (XXX_Type::isFilledArray(self::$socketServerInstances))
					{
						foreach (self::$socketServerInstances as $socketServerInstance)
						{
							$socketServerInstance->checkForTimerEvents();
						}						
					}
					
					// TODO clients that are NON-serverClients
										
					$timerEventTimestamp = $now;
				}
				
				if ($now - self::$lastProcessorTimerEventTimestamp >= self::$processorTimerInterval)
				{
					XXX_FileSystem_Local::clearInformationCache();
			
					self::onTimer();
				
					self::$lastProcessorTimerEventTimestamp = $now;
				}
				
				self::cleanUp();
			}
			
			self::cleanUp();
		}
		
	/////////////////////////
	//
	// Events
	//
	/////////////////////////	
		
		public static function onClientLimitReached ()
		{
			XXX_Log::logLine('Processor: Reached client limit ' . self::$clientLimit . '.', 'daemon');
		}
		
		public static function onTimer ()
		{
			
			XXX_Log::logLine('Processor: ' . print_r(XXX_PHP::getMemoryUsageInformation(), true) . ' | Clients: ' . XXX_Array::getFirstLevelItemTotal(self::$socketClientInstanceIDs), 'daemon');
			
			XXX_Log::saveBuffers();
		}
}

?>