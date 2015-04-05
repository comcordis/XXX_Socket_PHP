<?php

/*

TODO limits, ook voor HTTP, zodat hij niet geflood kan worden met ellenlange headers.bijv., 
TODO authentication!

TODO the warnings on send/receive in a non-blocking context, might be due to delays, thereby disconnecting and not retrying again.



*/

class XXX_Socket_Client extends XXX_Socket
{
	const CLASS_NAME = 'XXX_Socket_Client';
		
	// Reading
	protected $readVariables = array
	(
		'valid' => false,
		'keepReading' => false,
		
		// buffer | file
		'destination' => 'buffer',
		
		'buffer' => '',
		
		'file' => '',
		'fileAppend' => false,
		'fileStream' => false,
		'file_standardizePath' => true,
		
		// Remainder after being cut off by delimiter or length
		'socketSurplus' => '',
		
		// delimited | http | fixedLength | shortPrefixedLength | longPrefixedLength
		'format' => 'delimited',
		
		'bytesRead' => 0,
		
		'headLength' => 0,
		'bodyLength' => 0,
		
		// Length of the data length specification
		'dataLengthLength' => 1,
		// Length of the data
		'dataLength' => 1,
		
		/*
		
		\0 = normal
		\r\n | \r | \n = text
		\r\n\r\n = HTTP
		
		*/
		
		'delimiter' => "\n",
		
		'reachedHead' => false,
		
		'reachedPrefixedLengthLength' => false,
		'reachedPrefixedLength' => false,
		
		'reachedEnd' => false
	);
	
	// Writing
	protected $writeVariables = array
	(
		'valid' => false,
		'keepWriting' => false,
		
		// buffer | file
		'source' => 'buffer',
		
		'buffer' => '',
		
		'file' => '',
		'fileOffset' => false,
		'fileStream' => false,
		'file_standardizePath' => true,
		
		// Remainder after being blocked
		'chunkRemainder' => '',
		
		'dataLengthSpecificationBuffer' => '',
		
		// delimited | fixedLength | shortPrefixedLength | longPrefixedLength
		'format' => 'delimited',
		
		'bytesWritten' => 0,
		
		'dataLengthLengthSpecification' => '',
		'dataLengthSpecification' => '',
		
		// Length of the data length specification
		'dataLengthLength' => 1,
		// Length of the data
		'dataLength' => 1,
		
		/*
		
		\0 = normal
		\r | \n = text
		\r\n\r\n = HTTP
		
		*/
		
		'delimiter' => "\n",
		
		'reachedDataLengthLengthSpecification' => false,
		'reachedDataLengthSpecification' => false,
		
		'reachedEnd' => false
	);
		
	public function connect ($remoteHost, $remotePort)
	{
		$result = false;
		
		$opened = $this->open();
		
		if ($opened)
		{
			$this->connecting = true;			
			
			$connected = socket_connect($this->socketResource, $remoteHost, $remotePort);
			
			if ($connected !== false)
			{	
				$this->setConnectedTimestampToNow();
				$this->connected = true;
				
				$this->settings['remoteHost'] = $remoteHost;
				$this->settings['remotePort'] = $remotePort;
				
				$result = true;
				
				$this->onConnect();
			}
			else
			{
				$this->close();
				
				XXX_Debug::errorNotification(array(self::CLASS_NAME, 'connect'), 'Could not connect to "' . $this->getRemoteEndPointDescription() . '" | PHP: "' . $this->getError() . '"');
				
			}		
		}
		
		return $result;
	}
	
	/////////////////////////
	//
	// I/O
	//
	/////////////////////////
	
		/*
		
		Out-of-band data:
			Unlike exceptions in C++, socket exceptions do not indicate that an error has occured.
			Socket exceptions usually refer to the notification that out-of-band data has arrived.
			Out-of-band data (called "urgent data" in TCP) looks to the application like a separate stream of data from the main data stream.
			This can be useful for separating two different kinds of data.
			Note that just because it is called "urgent data" does not mean that it will be delivered any faster, or with higher priorety than data in the in-band data stream.
			Also beware that unlike the main data stream, the out-of-bound data may be lost if your application can't keep up with it.
		
		*/
	
		/////////////////////////
		//
		// Reading (Input)
		//
		/////////////////////////
			
			/*
			
			Things that could go wrong:
				- Socket closes
				- Socket times out (nothing to read becomes available soon enough)
				- Reading more than needed (move the surplus to bufferSurplus and always read the bufferSurplus before reading from socket)
				- Reading 0 bytes			
			*/
			
			// Read to destination
		
				public function setReadDestinationToBuffer ()
				{
					if (!$this->isReading())
					{			
						$this->readVariables['destination'] = 'buffer';
						
						$this->readVariables['file'] = '';		
						$this->readVariables['fileAppend'] = false;
						$this->readVariables['fileStream'] = false;
						
						$this->readVariables['buffer'] = '';
					}
				}
				
				public function setReadDestinationToFile ($file = '', $append = false)
				{
					if (!$this->isReading())
					{
						$this->readVariables['destination'] = 'file';
						
						$this->readVariables['file'] = $file;		
						$this->readVariables['fileAppend'] = $append ? true : false;
						$this->readVariables['fileStream'] = false;
						
						$this->readVariables['buffer'] = '';
					}
				}
			
			// Read format
						
				public function resetReadFormat ()
				{
					if (!$this->isReading())
					{
						$this->readVariables['headLength'] = 0;
						$this->readVariables['bodyLength'] = 0;
						
						$this->readVariables['dataLengthLength'] = 1;
						$this->readVariables['dataLength'] = 1;
						
						$this->readVariables['bytesRead'] = 0;
						
						$this->readVariables['delimiter'] = "\n";
						
						$this->readVariables['reachedHead'] = false;
						
						$this->readVariables['reachedPrefixedLengthLength'] = false;
						$this->readVariables['reachedPrefixedLength'] = false;
						$this->readVariables['reachedEnd'] = false;
					}
				}
				
				// Untill the delimiter is found
				public function setReadFormatToDelimited ($delimiter = "\n")
				{
					if (!$this->isReading())
					{
						$this->resetReadFormat();
						
						$this->readVariables['format'] = 'delimited';
						
						$this->readVariables['delimiter'] = $delimiter;
					}
				}
				
				// Untill nothing can be read
				public function setReadFormatToEmpty ()
				{
					if (!$this->isReading())
					{
						$this->resetReadFormat();
						
						$this->readVariables['format'] = 'empty';
					}
				}
				
				// Untill a custom stop trigger
				public function setReadFormatToCustom ()
				{
					if (!$this->isReading())
					{
						$this->resetReadFormat();
						
						$this->readVariables['format'] = 'custom';
					}
				}
				
				// Untill \r\n\r\n, then parse headers for content length and read the content length
				public function setReadFormatToHTTP ()
				{
					if (!$this->isReading())
					{
						$this->resetReadFormat();
						
						$this->readVariables['format'] = 'http';
						
						$this->readVariables['delimiter'] = "\r\n\r\n";
					}
				}
				
				// Untill fixed length
				public function setReadFormatToFixedLength ($length = 1)
				{
					if (!$this->isReading())
					{
						$this->resetReadFormat();
						
						$length = XXX_Default::toPositiveInteger($length, 1);
						
						$this->readVariables['format'] = 'fixedLength';
						
						$this->readVariables['dataLength'] = $length;
					}
				}
				
				// Untill the length prefixed in the message, shortPrefixed - For lengths 0 - 255 - Used for strings
				// - 1 byte (ascii code point represting the length of the data) for integers 0 - 255
				// - data
				public function setReadFormatToShortPrefixedLength ()
				{
					if (!$this->isReading())
					{
						$this->resetReadFormat();
						
						$this->readVariables['format'] = 'shortPrefixedLength';
					}
				}
				
				// Untill the length prefixed in the message, longPrefixed - For lengths > 255 - Used for files
				// - 1 byte (ascii code point representing the length of the length specification) for integers 0 - 255
				// - x byte (ascii code point represting the length of the data) for integers > 255
				// - data
				public function setReadFormatToLongPrefixedLength ()
				{
					if (!$this->isReading())
					{
						$this->resetReadFormat();
						
						$this->readVariables['format'] = 'longPrefixedLength';
					}
				}
				
				// Test for and process the format
				public function processReadFormat ()
				{
					if ($this->isReadValid())
					{	
						if ($this->readVariables['format'] == 'http')
						{
							if ($this->readVariables['bytesRead'] > 0)
							{
								if (!$this->readVariables['reachedHead'])
								{
									$content = false;
									
									if (XXX_Type::isResource($this->readVariables['fileStream']))
									{
										$content = XXX_FileSystem_Local::getFileContent($this->readVariables['file']);
									}
									else if ($this->readVariables['destination'] == 'buffer')
									{
										$content = $this->readVariables['buffer'];
									}
									
									if ($content !== false)
									{
										$firstPosition = XXX_String::findFirstPosition($content, $this->readVariables['delimiter']);
										
										if ($firstPosition !== false)
										{
											$content = $this->readVariables['buffer'];
											
											$content = XXX_String::normalizeLineSeparators($content);
											
											$headers = XXX_String::splitToArray($content, XXX_String::$lineSeparator);
											
											$headLength = $firstPosition + XXX_String::getByteSize($this->readVariables['delimiter']);
											
											$bodyLength = 0;
											
											foreach ($headers as $key => $line)
											{
												$parts = XXX_String::splitToArray($line, ':');
												
												$name = XXX_String::convertToLowerCase($parts[0]);
												
												$value = XXX_String::trim($parts[1]);
												
												if ($name == 'content-length')
												{
													$bodyLength = XXX_Type::makeInteger($value);
													
													break;
												}
											}
											
											$this->readVariables['headLength'] = $headLength;
											$this->readVariables['bodyLength'] = $bodyLength;
											
											$this->readVariables['bytesRead'] = 0;
											
											$this->readVariables['reachedHead'] = true;
										}
									}
								}
							}
						}					
						else if ($this->readVariables['format'] == 'shortPrefixedLength')
						{							
							if (!$this->readVariables['reachedPrefixedLength'] && $this->readVariables['bytesRead'] == 1)
							{
								$this->readVariables['dataLength'] = XXX_String::asciiCharactersToInteger($this->readVariables['buffer']);
								
								$this->readVariables['buffer'] = '';
								$this->readVariables['bytesRead'] = 0;
																
								$this->readVariables['reachedPrefixedLength'] = true;
							}
						}
						else if ($this->readVariables['format'] == 'longPrefixedLength')
						{
							if (!$this->readVariables['reachedPrefixedLengthLength'] && $this->readVariables['bytesRead'] == 1)
							{
								$this->readVariables['dataLengthLength'] = XXX_String::asciiCharactersToInteger($this->readVariables['buffer']);
								
								$this->readVariables['buffer'] = '';
								$this->readVariables['bytesRead'] = 0;
								
								$this->readVariables['reachedPrefixedLengthLength'] = true;
							}
							else if ($this->readVariables['reachedPrefixedLengthLength'] && !$this->readVariables['reachedPrefixedLength'] && $this->readVariables['bytesRead'] == $this->readVariables['dataLengthLength'])
							{
								$this->readVariables['dataLength'] = XXX_String::asciiCharactersToInteger($this->readVariables['buffer']);
															 
								$this->readVariables['buffer'] = '';
								$this->readVariables['bytesRead'] = 0;
								
								$this->readVariables['reachedPrefixedLength'] = true;
							}
						}
					}
				}
				
			// Read buffer & socketSurplus
			
				public function clearReadBuffer ()
				{
					// Application level
					$this->readVariables['buffer'] = '';				
					$this->readVariables['bytesRead'] = 0;					
				}
				
				public function clearReadSocketSurplus ()
				{
					// Application level
					$this->readVariables['socketSurplus'] = '';
										
					// OS level
					while (true)
					{
						$rawDataChunk = $this->readRawChunkFromSocket($this->settings['readChunkSize']);
						
						if ($rawDataChunk === false || $rawDataChunk['length'] == 0)
						{
							break;
						}
					}
				}
				
				public function getReadBuffer ()
				{
					return $this->readVariables['buffer'];
				}
			
			// Read information
				
				public function isReading ()
				{
					return $this->settings['activeOperation'] == 'read';
				}
				
				public function isReadValid ()
				{
					return $this->readVariables['valid'];
				}
			
				// Should be after reading a chunk.
				public function canKeepReading ()
				{
					if ($this->isReadValid() && !$this->readVariables['reachedEnd'])
					{
						switch ($this->readVariables['format'])
						{
							case 'delimited':
								$content = false;
								
								if ($this->readVariables['destination'] == 'file')
								{
									if (XXX_Type::isResource($this->readVariables['fileStream']))
									{
										$content = XXX_FileSystem_Local::getFileContent($this->readVariables['file']);
									}
									else
									{
										$this->readVariables['keepReading'] = false;
										
										$this->readVariables['valid'] = false;
									}
								}
								else if ($this->readVariables['destination'] == 'buffer')
								{
									$content = $this->readVariables['buffer'];
								}
																
								if ($content !== false)
								{
									$firstPosition = XXX_String::findFirstPosition($content, $this->readVariables['delimiter']);
									
									if ($firstPosition !== false)
									{
										$this->readVariables['keepReading'] = false;
										
										$this->readVariables['reachedEnd'] = true;
									}
								}
								break;
							case 'empty':
								break;
							case 'custom':
								break;
							case 'http':
								if ($this->readVariables['reachedHead'] && ($this->readVariables['bodyLength'] == 0 || ($this->readVariables['bodyLength'] > 0 && $this->readVariables['bytesRead'] >= $this->readVariables['bodyLength'])))
								{
									$this->readVariables['keepReading'] = false;
									
									$this->readVariables['reachedEnd'] = true;
								}
								break;
							case 'fixedLength':
								if ($this->readVariables['dataLength'] > 0 && $this->readVariables['bytesRead'] >= $this->readVariables['dataLength'])
								{							
									$this->readVariables['keepReading'] = false;
									
									$this->readVariables['reachedEnd'] = true;
								}
								break;
							case 'shortPrefixedLength':
								if ($this->readVariables['reachedPrefixedLength'] && $this->readVariables['dataLength'] > 0 && $this->readVariables['bytesRead'] >= $this->readVariables['dataLength'])
								{							
									$this->readVariables['keepReading'] = false;
									
									$this->readVariables['reachedEnd'] = true;
								}
								break;
							case 'longPrefixedLength':
								if ($this->readVariables['reachedPrefixedLengthLength'] && $this->readVariables['reachedPrefixedLength'] && $this->readVariables['dataLength'] > 0 && $this->readVariables['bytesRead'] >= $this->readVariables['dataLength'])
								{							
									$this->readVariables['keepReading'] = false;
									
									$this->readVariables['reachedEnd'] = true;
								}
								break;
						}
						
						// Has not timed out or closed
						if ($this->hasTimedOutOrClosed())
						{
							$this->readVariables['keepReading'] = false;
							
							$this->readVariables['valid'] = false;
						}
					}
					else
					{
						$this->readVariables['keepReading'] = false;
					}
										
					return $this->readVariables['keepReading'];
				}
				
				// Should be at the end
				public function hasReadingCompleted ()
				{
					return $this->readVariables['reachedEnd'];
				}
				
				public function setReadReachedEnd ()
				{
					$this->readVariables['keepReading'] = false;
					$this->readVariables['reachedEnd'] = true;
				}
				
				public function hasTimedOutOrClosed ()
				{
					$hasTimedOutOrClosed = false;					
				
					if ($this->hasTimedOut() || $this->hasClosed())
					{
						$hasTimedOutOrClosed = true;
					}
					
					if ($this->readVariables['valid'] && $hasTimedOutOrClosed)
					{
						$this->readVariables['valid'] = false;
					}
					
					return $hasTimedOutOrClosed;
				}
				
				public function addBytesRead ($bytesRead = 0)
				{
					$this->bytesRead += $bytesRead;
					
					if ($this->serverID > 0)
					{
						$socketServerInstance = XXX_SocketProcessor::getSocketServerInstanceForID($this->serverID);
						
						if ($socketServerInstance)
						{
							$socketServerInstance->addBytesRead($bytesRead);
						}
					}
				}
				
			// Read start/stop
			
				// Stops the reading, closes a file stream if opened
				public function stopReading ()
				{
					$this->settings['activeOperation'] = false;
					
					$this->readVariables['valid'] = false;
					$this->readVariables['keepReading'] = false;
					
					if ($this->readVariables['destination'] == 'file' && XXX_Type::isResource($this->readVariables['fileStream']))
					{
						XXX_FileSystem_Local::fileStream_close($this->readVariables['fileStream']);
					}
				}
				
				public function startReading ()
				{
					// No operation should be active
					if ($this->settings['activeOperation'] == false)
					{				
						// Connection should not have timed out or closed
						if (!$this->hasTimedOut() && !$this->hasClosed())
						{
							$error = false;
							
							// Open file stream
							if ($this->readVariables['destination'] == 'file')
							{
								if ($this->readVariables['fileAppend'])
								{
									$this->readVariables['fileStream'] = XXX_FileSystem_Local::fileStream_openForAppendedWriting($this->readVariables['file'], $this->readVariables['file_standardizePath']);
								}
								else
								{
									$this->readVariables['fileStream'] = XXX_FileSystem_Local::fileStream_openForWriting($this->readVariables['file'], $this->readVariables['file_standardizePath']);
								}
								
								if (!XXX_Type::isResource($this->readVariables['fileStream']))
								{
									$error = true;
								}
							}
							
							if (!$error)
							{						
								$this->settings['activeOperation'] = 'read';
								
								$this->readVariables['valid'] = true;
								$this->readVariables['keepReading'] = true;
							}
						}
					}
					
					if (!$this->readVariables['valid'])
					{
						$this->stopReading();
					}
					
					return $this->readVariables['valid'];
				}
			
			// Read raw data chunk
			
				/*
				
				socket_read:
					- Modes:
						- PHP_BINARY_READ (Default)
						- PHP_NORMAL_READ
					- Returns:
						- Success: data string
							- If binary
								- If \0 is reached
								- If specified length has been read
							- If text
								- If \n or \r is reached
								- If specified length has been read
						- Failure (Error/Remote side closed the connection): false
							- Error code via socket_last_error(). This code via socket_strerror() gives description.
				
				socket_recv:
					- Flags:
						- MSG_OOB - Process out-of-band data.
						- MSG_PEEK - Receive data from the beginning of the receive queue without removing it from the queue.
						- MSG_WAITALL - Block until at least specified maximum number of bytes are received. However, if a signal is caught or the remote host disconnects, the function may return less data.
						- MSG_DONTWAIT - Returns even if it would normally have blocked.
					- Returns:
						- Success: number of bytes received
						- Failure (Error/Remote side closed the connection): false
							- Error code via socket_last_error(). This code via socket_strerror() gives description.
					- Data buffer:
						- Success: data string
						- Failure: NULL
							- If an error occurs
							- If the connection is reset
							- If no data is available
							
				socket_read is similar to socket_recv with a flags value of 0
					
				*/
				
				protected function readRawChunkFromSocket ($length = 1)
				{
					$result = false;
					
					$error = false;
										
					if ($this->settings['readMethod'] == 'read')
					{
						$rawChunk = @socket_read($this->socketResource, $length, PHP_BINARY_READ);
						
						if ($rawChunk === false)
						{
							// Error
							$error = true;
						}
						else
						{
							// Success
							$bytesRead = XXX_String::getByteSize(XXX_Type::makeBinaryString($rawChunk));
						}
					}
					else if ($this->settings['readMethod'] == 'recv')
					{
						$readOperation = @socket_recv($this->socketResource, $rawChunk, $length, 0);
						
						if ($readOperation === false)
						{
							// Error
							$error = true;
						}
						else
						{
							$bytesRead = $readOperation;
							
							if ($rawChunk === NULL)
							{
								// No data is available
								$rawChunk = '';
							}
							else
							{
								// Success
							}
						}
					}
										
					if (!$error)
					{
						$result = array
						(
							'data' => $rawChunk,
							'length' => $bytesRead
						);
					}
					
					return $result;
				}
				
				protected function readRawChunkFromSocketSurplus ($length = 1)
				{
					$result = false;
					
					if ($this->readVariables['socketSurplus'] !== '')
					{					
						$bufferSurplusLength = XXX_String::getByteSize(XXX_Type::makeBinaryString($this->readVariables['socketSurplus']));
						
						if ($bufferSurplusLength <= $length)
						{
							$rawChunk = $this->readVariables['socketSurplus'];
							$bytesRead = $bufferSurplusLength;
							
							$this->readVariables['socketSurplus'] = '';
						}
						else
						{
							$rawChunk = XXX_String::getPart($this->readVariables['socketSurplus'], 0, $length);
							$bytesRead = $length;
							
							$this->readVariables['socketSurplus'] = XXX_String::getPart($this->readVariables['socketSurplus'], $length);
						}
						
						$result = array
						(
							'data' => $rawChunk,
							'length' => $bytesRead
						);
					}
					
					return $result;
				}
			
			// Read chunks
			
				protected function determineReadChunkLength ()
				{
					$length = 0;
					
					$lengthBasedOnRemainingDataLengthLength = XXX_Number::lowest($this->readVariables['dataLengthLength'] - $this->readVariables['bytesRead'], $this->settings['readChunkSize']);
					$lengthBasedOnRemainingDataLength = XXX_Number::lowest($this->readVariables['dataLength'] - $this->readVariables['bytesRead'], $this->settings['readChunkSize']);
					$lengthBasedOnRemainingBodyLength = XXX_Number::lowest($this->readVariables['bodyLength'] - $this->readVariables['bytesRead'], $this->settings['readChunkSize']);
					
					switch ($this->readVariables['format'])
					{
						case 'delimited':
							$length = $this->settings['readChunkSize'];
							break;
						case 'empty':
							$length = $this->settings['readChunkSize'];
							break;
						case 'custom':
							$length = $this->settings['readChunkSize'];
							break;
						case 'http':
							if (!$this->readVariables['reachedHead'])
							{
								$length = $this->settings['readChunkSize'];
							}
							else
							{
								$length = $lengthBasedOnRemainingBodyLength;
							}
							break;
						case 'fixedLength':		
							$length = $lengthBasedOnRemainingDataLength;
							break;
						case 'shortPrefixedLength':
							if (!$this->readVariables['reachedPrefixedLength'])
							{
								$length = 1;
							}
							else
							{		
								$length = $lengthBasedOnRemainingDataLength;
							}
							break;
						case 'longPrefixedLength':
							if (!$this->readVariables['reachedPrefixedLengthLength'])
							{
								$length = 1;
							}
							else if (!$this->readVariables['reachedPrefixedLength'])
							{		
								$length = $lengthBasedOnRemainingDataLengthLength;
							}
							else
							{		
								$length = $lengthBasedOnRemainingDataLength;
							}
							break;
					}
										
					return $length;
				}
				
				protected function processRawChunkToSocketSurplus ($rawChunk)
				{
					// See if something needs to go to the socket surplus?? Cut it off...
					if ($this->readVariables['format'] == 'delimited' || ($this->readVariables['format'] == 'http' && !$this->readVariables['reachedHead']))
					{
						// Concatenate the previous read chunks with the new raw chunk
						if ($this->readVariables['destination'] == 'file')
						{
							$concatenated = XXX_FileSystem_Local::getFileContent($this->readVariables['file']) . $rawChunk['data'];
						}
						else if ($this->readVariables['destination'] == 'buffer')
						{
							$concatenated = $this->readVariables['buffer'] . $rawChunk['data'];
						}
						
						// If it contains the delimiter, split off the part including the delimiter and store the surplus to the socket surplus
						if (XXX_String::findFirstPosition($concatenated, $this->readVariables['delimiter']) !== false)
						{
							// Cut off									
							$parts = XXX_String::splitToArray($concatenated, $this->readVariables['delimiter']);									
							$splitItems = XXX_Array::splitOffFirstItem($parts);
							
							// Within
							$rawChunk['data'] = $splitItems['firstItem'] . $this->readVariables['delimiter'];
							$rawChunk['length'] = XXX_String::getByteSize(XXX_Type::makeBinaryString($rawChunk['data']));
							
							// Surplus
							$this->readVariables['socketSurplus'] .= XXX_Array::joinValuesToString($splitItems['array'], $this->readVariables['delimiter']);
							
							if ($this->readVariables['format'] == 'delimited')
							{	
								$this->readVariables['keepReading'] = false;
								
								$this->readVariables['reachedEnd'] = true;
							}
						}
					}
					
					return $rawChunk;
				}
				
				// Should always be preceeded by calling startReading() and afterwards canKeepReading() with a true as result
				public function readChunk ()
				{
					$result = false;
					
					if ($this->readVariables['keepReading'])
					{						
						// Determine how much to read
						
							$expectedLength = $this->determineReadChunkLength();
						
						// Read the raw data chunk
							
							// Socket surplus
							if ($this->readVariables['socketSurplus'] !== '')
							{
								$rawChunk = $this->readRawChunkFromSocketSurplus($expectedLength);						
							}
							// Socket
							else
							{
								$rawChunk = $this->readRawChunkFromSocket($expectedLength);
							}
						
						// Process the result
						
							if ($rawChunk !== false && $rawChunk['data'] !== '')
							{
								$rawChunk = $this->processRawChunkToSocketSurplus($rawChunk);
								
								$fileStreamError = false;
								
								// Process the read data to it's destination
								if ($this->readVariables['destination'] == 'buffer')
								{
									$this->readVariables['buffer'] .= $rawChunk['data'];
								}							
								else if ($this->readVariables['destination'] == 'file')
								{
									if (XXX_Type::isResource($this->readVariables['fileStream']))
									{
										// If it's just a prefix, don't write to file but to buffer
										if ($this->readVariables['format'] == 'shortPrefixedLength' && !$this->readVariables['reachedPrefixedLength'])
										{
											$this->readVariables['buffer'] .= $rawChunk['data'];
										}
										// If it's either a prefixedLengthLength or prefixedLength
										else if ($this->readVariables['format'] == 'longPrefixedLength' && (!$this->readVariables['reachedPrefixedLengthLength'] || !$this->readVariables['reachedPrefixedLength']))
										{
											$this->readVariables['buffer'] .= $rawChunk['data'];
										}
										// If it's actual data, write to file
										else
										{
											$written = XXX_FileSystem_Local::fileStream_writeChunk($this->readVariables['fileStream'], $rawChunk['data'], XXX_String::getByteSize($rawChunk['data']));
											
											if (!$written)
											{
												$fileStreamError = true;
											}
										}
									}
									else
									{
										$fileStreamError = true;
									}
								}
																
								if (!$fileStreamError)
								{			
									$this->readVariables['bytesRead'] += $rawChunk['length'];
									$this->addBytesRead($rawChunk['length']);
									
									$this->setLastActionTimestampToNow();
									
									$result = $rawChunk['data'];
								}
								else
								{
									XXX_Debug::errorNotification(array(self::CLASS_NAME, 'readChunk'), 'File resource invalid "' . $this->readVariables['file'] . '" with "' . $this->getLocalEndPointDescription() . '",  "' . $this->getRemoteEndPointDescription() . '" | PHP: "' . $this->getError() . '"');
									
									// Leads to invalidating the next canKeepReading call
									$this->readVariables['valid'] = false;
								}
							}
							else
							{
								if ($this->isBlockingModeEnabled())
								{
									XXX_Debug::errorNotification(array(self::CLASS_NAME, 'readChunk'), 'Could not read from "' . $this->getLocalEndPointDescription() . '",  "' . $this->getRemoteEndPointDescription() . '" | PHP: "' . $this->getError() . '"');
								
									// Leads to invalidating the next canKeepReading call
									$this->readVariables['valid'] = false;
								}
							}
					}
					
					return $result;
				}
				
				
				/*
				
				return:
					- false: error
					- empty string: '' (No data available)
					- string: 'abc'
				
				*/
				
				// This reads everything within 1 blocking period! How to overcome it is by knowing the end...
				
				// The read operation could fail because no data becomes available (or takes too long) or the connection closes		
				public function readAvailableChunks ($returnData = false, $expectValue = false)
				{
					$data = false;
						
					$keepReading = true;
									
					while (true)
					{
						if ($keepReading)
						{
							$dataChunk = $this->readChunk();
							
							if ($dataChunk === false)
							{
								//echo '[Line: false (Break)]' . "\r\n";
								
								if (!$expectValue || ($expectValue && $data !== false && $data !== ''))
								{
									$this->setReadReachedEnd();
									
									break;
								}
							}
							else if ($dataChunk === '')
							{
								//echo '[Line: empty (Break)]' . "\r\n";
								
								if ($returnData)
								{
									if ($returnData)
									{
										// If it's not a string yet...
										if ($data === false)
										{
											$data = '';
										}
									}
									else
									{
										$data = true;
									}
								}
								
								if (!$expectValue || ($expectValue && $data !== false && $data !== ''))
								{
									$this->setReadReachedEnd();
									break;
								}
							}
							else
							{
								//echo '[Line: ' . $dataChunk . ']' . "\r\n";
								
								if ($returnData)
								{
									// If it's not a string yet...
									if ($data === false)
									{
										$data = '';
									}
									
									$data .= $dataChunk;
								}
								else
								{
									$data = true;
								}
																								
								$this->processReadFormat();
							}
						}
						else
						{
							break;
						}
						
						$keepReading = $this->canKeepReading();
					}
					
					if ($data === false || $this->hasTimedOutOrClosed())
					{
						$this->stopReading();
						
						$this->clearReadBuffer();
						$this->clearReadSocketSurplus();
						
						if ($this->isBlockingModeEnabled())
						{						
							$this->remoteDisconnect();
						}
					}
					else
					{
						if ($data === '')
						{
							if ($this->isBlockingModeEnabled())
							{
								$this->remoteDisconnect();
							}
						}
						else
						{
							$this->onRead();
							
							if ($this->hasReadingCompleted())
							{						
								$this->stopReading();
								
								$this->onReadComplete();					
							}
						}
					}
					
					return $data;
				}
				
				public function readQuickAndDirty ($expectValue = true)
				{
					$result = false;
		
					$this->setReadDestinationToBuffer();
					$this->setReadFormatToEmpty();
					
					if ($this->startReading())
					{
						$readAvailableChunks = $this->readAvailableChunks(false, $expectValue);
						
						if ($readAvailableChunks !== false)
						{
							$result = $this->getReadBuffer();
						}
					}
					
					$this->clearReadBuffer();
					
					return $result;
				}
			
		/////////////////////////
		//
		// Writing (Output)
		//
		/////////////////////////
			
			/*
			
			Things that could go wrong:
				- Socket closes
				- Socket times out (nothing to read becomes available soon enough)
				- Writing less than needed (move the remainder to chunk remainder and always write first from the chunkRemainder before from the source)
				- Writing 0 bytes
			*/
			
			// Write from source
		
				public function setWriteSourceToBuffer ()
				{
					if ($this->isWriting())
					{
						$this->writeVariables['source'] = 'buffer';
											
						$this->writeVariables['buffer'] = '';
						
						$this->writeVariables['file'] = '';		
						$this->writeVariables['fileOffset'] = false;
						$this->writeVariables['fileStream'] = false;
					}
				}
				
				public function setWriteSourceToFile ($file = '', $offset = 0)
				{
					if (!$this->isWriting())
					{
						$this->writeVariables['source'] = 'file';
						
						$this->writeVariables['buffer'] = '';
						
						$this->writeVariables['file'] = $file;		
						$this->writeVariables['fileOffset'] = $offset;
						$this->writeVariables['fileStream'] = false;
					}
				}
			
			// Write format
			
				public function resetWriteFormat ()
				{
					if (!$this->isWriting())
					{
						$this->writeVariables['dataLengthLengthSpecification'] = '';
						$this->writeVariables['dataLengthSpecification'] = '';
						
						$this->writeVariables['dataLengthLength'] = 1;
						$this->writeVariables['dataLength'] = 1;
						
						$this->writeVariables['bytesWritten'] = 0;
						
						$this->writeVariables['delimiter'] = "\n";
						
						$this->writeVariables['reachedPrefixedLengthLength'] = false;
						$this->writeVariables['reachedPrefixedLength'] = false;
						$this->writeVariables['reachedEnd'] = false;
					}
				}
				
				public function setWriteFormatToDelimited ($delimiter = "\n")
				{
					if (!$this->isWriting())
					{
						$this->resetWriteFormat();
						
						$this->writeVariables['format'] = 'delimited';
						
						$this->writeVariables['delimiter'] = $delimiter;
					}
				}
											
				public function setWriteFormatToEmpty ()
				{
					if (!$this->isWriting())
					{
						$this->resetWriteFormat();
						
						$this->writeVariables['format'] = 'empty';
					}
				}
				
				public function setWriteFormatToFixedLength ($length = 1)
				{
					if (!$this->isWriting())
					{
						$this->resetWriteFormat();
						
						$length = XXX_Default::toPositiveInteger($length, 1);
						
						$this->writeVariables['format'] = 'fixedLength';
						
						$this->writeVariables['dataLength'] = $length;
					}
				}
				
				public function setWriteFormatToShortPrefixedLength ()
				{
					if (!$this->isWriting())
					{
						$this->resetWriteFormat();
						
						$this->writeVariables['format'] = 'shortPrefixedLength';
					}
				}
				
				public function setWriteFormatToLongPrefixedLength ()
				{
					if (!$this->isWriting())
					{
						$this->resetWriteFormat();
						
						$this->writeVariables['format'] = 'longPrefixedLength';
					}
				}
			
				protected function processWriteFormat ()
				{
					if ($this->isWriteValid())
					{	
						if ($this->writeVariables['format'] == 'shortPrefixedLength')
						{
							if (!$this->writeVariables['reachedPrefixedLength'] && $this->writeVariables['bytesWritten'] == 1)
							{
								$this->writeVariables['bytesWritten'] = 0;
								
								$this->writeVariables['reachedPrefixedLength'] = true;
							}
						}
						else if ($this->writeVariables['format'] == 'longPrefixedLength')
						{
							if (!$this->writeVariables['reachedPrefixedLengthLength'] && $this->writeVariables['bytesWritten'] == 1)
							{
								$this->writeVariables['bytesWritten'] = 0;
								
								$this->writeVariables['reachedPrefixedLengthLength'] = true;
							}
							else if ($this->writeVariables['reachedPrefixedLengthLength'] && !$this->writeVariables['reachedPrefixedLength'] && $this->writeVariables['bytesWritten'] == $this->writeVariables['dataLengthLength'])
							{
								$this->writeVariables['bytesWritten'] = 0;
								
								$this->writeVariables['reachedPrefixedLength'] = true;
							}
						}
					}
				}
			
			// Write buffer & chunkRemainder
				
				public function clearWriteBuffer ()
				{
					// Application level
					$this->writeVariables['buffer'] = '';
					
					$this->writeVariables['bytesWritten'] = 0;
				}
				
				public function clearWriteChunkRemainder ()
				{
					$this->writeVariables['chunkRemainder'] = '';
				}
				
				public function setWriteBuffer ($buffer = '')
				{
					$this->writeVariables['buffer'] = $buffer;
				}
				
				public function appendWriteBuffer ($buffer = '')
				{
					$this->writeVariables['buffer'] .= $buffer;
				}
			
			// Write information
			
				public function isWriting ()
				{
					return $this->settings['activeOperation'] == 'write';
				}
				
				public function isWriteValid ()
				{
					return $this->writeVariables['valid'] == true;
				}
				
				// Should be after writing a chunk.
				public function canKeepWriting ()
				{
					if ($this->writeVariables['valid'])
					{
						// chunkRemainder should be empty						
						if ($this->writeVariables['chunkRemainder'] == '')
						{						
							switch ($this->writeVariables['format'])
							{
								case 'delimited':
									if ($this->writeVariables['source'] == 'file' && $this->writeVariables['bytesWritten'] >= $this->writeVariables['dataLength'])
									{
										$this->writeVariables['keepWriting'] = false;
										
										$this->writeVariables['reachedEnd'] = true;
									}
									else if ($this->writeVariables['source'] == 'buffer' && $this->writeVariables['bytesWritten'] >= $this->writeVariables['dataLength'])
									{
										$this->writeVariables['keepWriting'] = false;
										
										$this->writeVariables['reachedEnd'] = true;
									}
									break;
								case 'empty':
								case 'fixedLength':
									if ($this->writeVariables['dataLength'] > 0 && $this->writeVariables['bytesWritten'] >= $this->writeVariables['dataLength'])
									{							
										$this->writeVariables['keepWriting'] = false;
										
										$this->writeVariables['reachedEnd'] = true;
									}
									break;
								case 'shortPrefixedLength':
									if ($this->writeVariables['reachedPrefixedLength'] && $this->writeVariables['dataLength'] > 0 && $this->writeVariables['bytesWritten'] >= $this->writeVariables['dataLength'])
									{							
										$this->writeVariables['keepWriting'] = false;
										
										$this->writeVariables['reachedEnd'] = true;
									}
									break;
								case 'longPrefixedLength':
									if ($this->writeVariables['reachedPrefixedLengthLength'] && $this->writeVariables['reachedPrefixedLength'] && $this->writeVariables['dataLength'] > 0 && $this->writeVariables['bytesWritten'] >= $this->writeVariables['dataLength'])
									{							
										$this->writeVariables['keepWriting'] = false;
										
										$this->writeVariables['reachedEnd'] = true;
									}
									break;
							}
						}
											
						// Has not timed out or closed
						if ($this->hasTimedOut() || $this->hasClosed())
						{
							$this->writeVariables['keepWriting'] = false;
							
							$this->writeVariables['valid'] = false;
						}
					}
					else
					{
						$this->writeVariables['keepWriting'] = false;
					}
					
					return $this->writeVariables['keepWriting'];
				}
				
				// Should be at the end
				public function hasWritingCompleted ()
				{
					if ($this->writeVariables['valid'])
					{
						if ($this->hasTimedOut() || $this->hasClosed())
						{
							$this->writeVariables['valid'] = false;
						}
					}
					
					return $this->writeVariables['valid'];
				}
				
				public function addBytesWritten ($bytesWritten = 0)
				{
					$this->bytesWritten += $bytesWritten;
					
					if ($this->serverID > 0)
					{
						$socketServerInstance = XXX_SocketProcessor::getSocketServerInstanceForID($this->serverID);
						
						if ($socketServerInstance)
						{
							$socketServerInstance->addBytesWritten($bytesWritten);
						}
					}
				}
							
			// Write start/stop
				
				// Stops the writing, closes a file stream if opened
				public function stopWriting ()
				{
					$this->settings['activeOperation'] = false;
					
					$this->writeVariables['valid'] = false;
					$this->writeVariables['keepWriting'] = false;
					
					if ($this->writeVariables['source'] == 'file' && XXX_Type::isResource($this->writeVariables['fileStream']))
					{
						XXX_FileSystem_Local::fileStream_close($this->writeVariables['fileStream']);
					}
				}
				
				public function startWriting ()
				{
					// No operation should be active
					if ($this->settings['activeOperation'] == false)
					{
						// Connection should not have timed out or closed
						if (!$this->hasTimedOut() && !$this->hasClosed())
						{
							$error = false;
							
							$delimiterPosition = false;
							
							// Open file stream
							if ($this->writeVariables['source'] == 'file')
							{
								$this->writeVariables['fileStream'] = XXX_FileSystem_Local::fileStream_openForReading($this->writeVariables['file'], $this->writeVariables['file_standardizePath']);
								
								if (XXX_Type::isResource($this->writeVariables['fileStream']))
								{
									$dataLength = XXX_FileSystem_Local::getFileSize($this->writeVariables['file'], $this->writeVariables['file_standardizePath']);
									
									if (XXX_Type::isPositiveInteger($this->writeVariables['fileOffset']) && $this->writeVariables['fileOffset'] > 0)
									{								
										$dataLength -= $this->writeVariables['fileOffset'];
										
										$offset = XXX_FileSystem_Local::fileStream_setReadOffset($this->writeVariables['fileStream'], $this->writeVariables['fileOffset']);
										
										if (!$offset)
										{
											$error = true;
										}
									}
												
									// Delimiter position
									if (!$error && $this->writeVariables['format'] == 'delimited')
									{
										// Get file content
										$fileContent = XXX_FileSystem_Local::getFileContent($this->writeVariables['file']);
										
										// Work to offset
										if (XXX_Type::isPositiveInteger($this->writeVariables['fileOffset']))
										{	
											$fileContent = XXX_String::getPart($fileContent, $this->writeVariables['fileOffset']);
										}
										
										$delimiterPosition = XXX_String::findFirstPosition($fileContent, $this->writeVariables['delimiter']);
									}
								}
								else
								{
									$error = true;
								}
							}
							else if ($this->writeVariables['source'] == 'buffer')
							{
								$dataLength = XXX_String::getByteSize($this->writeVariables['buffer']);
								
								$delimiterPosition = XXX_String::findFirstPosition($this->writeVariables['buffer'], $this->writeVariables['delimiter']);
							}
							
							if (!$error)
							{
								$this->settings['activeOperation'] = 'write';
								
								$dataLength = XXX_Default::toPositiveInteger($dataLength, 0);
								
								if ($this->writeVariables['format'] == 'delimited')
								{
									if ($delimiterPosition !== false && $delimiterPosition > -1)
									{
										//$dataLength = $delimiterPosition + XXX_String::getByteSize($this->writeVariables['delimiter']);
									}
									
									$this->writeVariables['dataLength'] = $dataLength;
								}
								else if ($this->writeVariables['format'] == 'empty')
								{
									$this->writeVariables['dataLength'] = $dataLength;
								}
								else if ($this->writeVariables['format'] == 'fixedLength')
								{
									if ($dataLength < $this->writeVariables['dataLength'])
									{
										$this->writeVariables['dataLength'] = $dataLength;
									}
								}
								
								if ($this->writeVariables['format'] == 'shortPrefixedLength' || $this->writeVariables['format'] == 'longPrefixedLength')
								{
									$this->writeVariables['dataLength'] = $dataLength;
									
									$this->writeVariables['dataLengthSpecification'] = XXX_String::integerToAsciiCharacters($dataLength);
								}
								
								if ($this->writeVariables['format'] == 'longPrefixedLength')
								{
									$this->writeVariables['dataLengthLength'] = XXX_String::getByteSize($this->writeVariables['dataLengthSpecification']);
									
									$this->writeVariables['dataLengthLengthSpecification'] = XXX_String::integerToAsciiCharacters($this->writeVariables['dataLengthLength']);
									
									$this->writeVariables['dataLengthSpecificationBuffer'] = $this->writeVariables['dataLengthSpecification'];
								}
								
								$this->writeVariables['valid'] = true;
								$this->writeVariables['keepWriting'] = true;
							}
						}
					}
					
					if (!$this->writeVariables['valid'])
					{
						$this->stopWriting();
					}
																				
					return $this->writeVariables['valid'];
				}
			
			// Write raw data chunk
			
				/*
				
				socket_write:
					- Returns:
						- Success: number of bytes written
						- Failure (Error/Remote side closed the connection): false
							- Error code via socket_last_error(). This code via socket_strerror() gives description.
				
				socket_send:
					- Flags:
						- MSG_OOB - Send out-of-band data.
						- MSG_EOR - Indicate a record mark. The sent data completes the record.
						- MSG_EOF - Close the sender side of the socket and include an appropriate notification of this at the end of the sent data. The sent data completes the transaction.
						- MSG_DONTROUTE - Bypass routing, use direct interface.				
					- Returns:
						- Success: number of bytes sent
						- Failure (Error/Remote side closed the connection): false
							- Error code via socket_last_error(). This code via socket_strerror() gives description.
				
				socket_write is similar to socket_send with a flags value of 0
				
				*/
				
				protected function writeRawDataChunk ($dataChunk = '', $length = 0)
				{
					$result = false;
					
					$error = false;
					
					// Write to socket	
					if ($this->settings['writeMethod'] == 'write')
					{
						$bytesWritten = socket_write($this->socketResource, $dataChunk, $length);
						
						if ($bytesWritten === false)
						{
							// Error
							$error = true;
						}
						else
						{
							// Success
						}
					}
					else if ($this->settings['writeMethod'] == 'send')
					{
						$bytesWritten = socket_send($this->socketResource, $dataChunk, $length, 0);					
						
						if ($bytesWritten === false)
						{
							// Error
							$error = true;
						}
						else
						{
							// Success
						}
					}
					
					if (!$error)
					{
						$result = $bytesWritten;
					}
					
					return $result;
				}
			
			// Write chunks
				
				protected function determineWriteChunk ()
				{
					$length = 0;
					$data = '';
					
					if ($this->writeVariables['chunkRemainder'] != '')
					{
						$chunkRemainderLength = XXX_String::getByteSize($this->writeVariables['chunkRemainder']);
						
						$length = XXX_Number::lowest($chunkRemainderLength, $this->settings['writeChunkSize']);
						
						$data = XXX_String::getPart($this->writeVariables['chunkRemainder'], 0, $length);
					}
					else
					{
						$lengthBasedOnRemainingDataLengthLength = XXX_Number::lowest($this->writeVariables['dataLengthLength'] - $this->writeVariables['bytesWritten'], $this->settings['writeChunkSize']);
						$lengthBasedOnRemainingDataLength = XXX_Number::lowest($this->writeVariables['dataLength'] - $this->writeVariables['bytesWritten'], $this->settings['writeChunkSize']);
						
						switch ($this->writeVariables['format'])
						{
							case 'delimited':
								$length = $lengthBasedOnRemainingDataLength;
								break;
							case 'empty':		
								$length = $lengthBasedOnRemainingDataLength;								
								break;
							case 'fixedLength':		
								$length = $lengthBasedOnRemainingDataLength;								
								break;
							case 'shortPrefixedLength':
								if (!$this->writeVariables['reachedPrefixedLength'])
								{
									$length = 1;
								}
								else
								{		
									$length = $lengthBasedOnRemainingDataLength;
								}
								break;
							case 'longPrefixedLength':
								if (!$this->writeVariables['reachedPrefixedLengthLength'])
								{
									$length = 1;
								}
								else if (!$this->writeVariables['reachedPrefixedLength'])
								{		
									$length = $lengthBasedOnRemainingDataLengthLength;
								}
								else
								{		
									$length = $lengthBasedOnRemainingDataLength;
								}
								break;
						}
						
						if (($this->writeVariables['format'] == 'shortPrefixedLength' && $this->writeVariables['reachedPrefixedLength']) || ($this->writeVariables['format'] == 'longPrefixedLength' && $this->writeVariables['reachedPrefixedLengthLength'] && $this->writeVariables['reachedPrefixedLength']))
						{
							if ($this->writeVariables['source'] == 'buffer')
							{
								$data = XXX_String::getPart($this->writeVariables['buffer'], 0, $length);
							}
							else if ($this->writeVariables['source'] == 'file' && XXX_Type::isResource($this->writeVariables['fileStream']))
							{
								$data = XXX_FileSystem_Local::fileStream_readChunk($this->writeVariables['fileStream'], $length);
							}
						}
						else if ($this->writeVariables['format'] == 'shortPrefixedLength' && !$this->writeVariables['reachedPrefixedLength'])
						{
							$data = $this->writeVariables['dataLengthSpecification'];
						}
						else if ($this->writeVariables['format'] == 'longPrefixedLength' && !$this->writeVariables['reachedPrefixedLengthLength'] && !$this->writeVariables['reachedPrefixedLength'])
						{
							$data = $this->writeVariables['dataLengthLengthSpecification'];
						}
						else if ($this->writeVariables['format'] == 'longPrefixedLength' && $this->writeVariables['reachedPrefixedLengthLength'] && !$this->writeVariables['reachedPrefixedLength'])
						{
							$data = XXX_String::getPart($this->writeVariables['dataLengthSpecificationBuffer'], 0, $length);
						}
						else
						{
							if ($this->writeVariables['source'] == 'buffer')
							{
								$data = XXX_String::getPart($this->writeVariables['buffer'], 0, $length);
							}
							else if ($this->writeVariables['source'] == 'file' && XXX_Type::isResource($this->writeVariables['fileStream']))
							{
								$data = XXX_FileSystem_Local::fileStream_readChunk($this->writeVariables['fileStream'], $length);
							}
						}
					}
					
					$result = array
					(
						'data' => $data,
						'length' => XXX_String::getByteSize($data)
					);
											
					return $result;
				}
				
				// Should always be preceeded by calling startWriting() and afterwards canKeepWriting() with a true as result
				public function writeChunk ()
				{
					$result = false;
					
					if ($this->writeVariables['keepWriting'])
					{
						// Determine how much to write
						
							$chunk = $this->determineWriteChunk();
						
						// Write the raw data chunk
						
							$bytesWritten = $this->writeRawDataChunk($chunk['data'], $chunk['length']);
						
						// Process the result
						
							if ($bytesWritten !== false)
							{								
								// Process the written chunk to update the variables
									
									// Written from chunkRemainder
									if ($this->writeVariables['chunkRemainder'] != '')
									{
										$this->writeVariables['chunkRemainder'] = XXX_String::getPart($this->writeVariables['chunkRemainder'], $bytesWritten);
									}
									// Written from source
									else
									{
										if ($this->writeVariables['source'] == 'buffer')
										{
											if ($this->writeVariables['format'] == 'shortPrefixedLength' && !$this->writeVariables['reachedPrefixedLength'])
											{
											}
											else if ($this->writeVariables['format'] == 'longPrefixedLength' && !$this->writeVariables['reachedPrefixedLengthLength'] && !$this->writeVariables['reachedPrefixedLength'])
											{
											}
											else if ($this->writeVariables['format'] == 'longPrefixedLength' && $this->writeVariables['reachedPrefixedLengthLength'] && !$this->writeVariables['reachedPrefixedLength'])
											{
												$this->writeVariables['dataLengthSpecificationBuffer'] = XXX_String::getPart($this->writeVariables['dataLengthSpecificationBuffer'], $bytesWritten);
											}
											else
											{
												$this->writeVariables['buffer'] = XXX_String::getPart($this->writeVariables['buffer'], $bytesWritten);
											}
										}
									}
								
								// See if something needs to go to chunkRemainder
								if ($chunk['length'] !== $bytesWritten)
								{
									$this->writeVariables['chunkRemainder'] .= XXX_String::getPart($chunk['data'], $bytesWritten);
								}
																
								$this->writeVariables['bytesWritten'] += $bytesWritten;
								$this->addBytesWritten($bytesWritten);
								
								$this->setLastActionTimestampToNow();
								
								$result = $bytesWritten;
							}
							else
							{
								XXX_Debug::errorNotification(array(self::CLASS_NAME, 'writeChunk'), 'Could not write to "' . $this->getLocalEndPointDescription() . '",  "' . $this->getRemoteEndPointDescription() . '" | PHP: "' . $this->getError() . '"');
								
								// Leads to invalidating the next canKeepWriting call
								$this->writeVariables['valid'] = false;
							}
					}
					
					return $result;
				}
								
				public function writeAvailableChunks ($returnData = false)
				{
					$bytesWrittenTotal = false;
						
					$keepWriting = true;
					
					while (true)
					{
						if ($keepWriting)
						{
							$bytesWritten = $this->writeChunk();
							
							if ($bytesWritten !== false && $bytesWritten !== 0)
							{
								if ($returnData)
								{							
									if ($bytesWrittenTotal === false)
									{
										$bytesWrittenTotal = 0;
									}
									
									$bytesWrittenTotal += $bytesWritten;
								}
								else
								{
									$bytesWrittenTotal = true;
								}
											
								$this->processWriteFormat();
							}
							else
							{
								break;
							}
						}
						else
						{
							break;
						}
						
						$keepWriting = $this->canKeepWriting();
					}
										
					if ($bytesWrittenTotal === 0 || $bytesWrittenTotal === false || !$this->hasWritingCompleted())
					{
						$bytesWrittenTotal = false;
						
						$this->stopWriting();
						
						$this->clearWriteBuffer();
						$this->clearWriteChunkRemainder();
						
						if ($this->isBlockingModeEnabled())
						{
							$this->remoteDisconnect(); // TODO in nonblocking context this is false???
						}
					}
					else
					{			
						$this->onWrite();
						
						if ($this->writeVariables['reachedEnd'])
						{
							$this->stopWriting();
							
							$this->onWriteComplete();
						}
					}
					
					return $bytesWrittenTotal;
				}
				
				public function writeQuickAndDirty ($buffer = '')
				{
					$result = false;
					
					$this->setWriteSourceToBuffer();
					$this->setWriteFormatToEmpty();
					$this->setWriteBuffer($buffer);
					
					if ($this->startWriting())
					{
						$writtenAvailableChunks = $this->writeAvailableChunks();
						
						if ($writtenAvailableChunks !== false)
						{
							$result = true;
						}
					}
					
					$this->stopWriting();
					
					$this->clearWriteBuffer();
		
					return $result;
				}
	
	/////////////////////////
	//
	// Events
	//
	/////////////////////////
	
		public function onConnect ()
		{
			XXX_Log::logLine('		Client ' . $this->ID . ': Connected', 'daemon');
		}
					
		public function onRead ()
		{
			XXX_Log::logLine('		Client ' . $this->ID . ': <- Read', 'daemon');
		}
		
		public function onReadComplete ()
		{
			XXX_Log::logLine('		Client ' . $this->ID . ': |<- Read completed', 'daemon');
		}
		
		public function onWrite ()
		{
			XXX_Log::logLine('		Client ' . $this->ID . ': Write ->', 'daemon');
		}
		
		public function onWriteComplete ()
		{
			XXX_Log::logLine('		Client ' . $this->ID . ': Write completed ->|', 'daemon');
		}
		
		public function onTimer ()
		{
			XXX_Log::logLine('		Client ' . $this->ID . ': Timer', 'daemon');
		}
}

?>