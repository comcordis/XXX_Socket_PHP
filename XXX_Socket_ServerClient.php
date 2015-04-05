<?php

/*

 
if (readBuffer == 'exit')
{
	disconnect client 
}
else if (readBuffer == 'end')
{
	disconnect all cients
	
	close server
}

function disconnectClient ()
{
	send a message stating the disconnect first
	
	close client socket
}

message for other client
message for all clients


*/

class XXX_Socket_ServerClient extends XXX_Socket_Client
{
	const CLASS_NAME = 'XXX_Socket_ServerClient';
	
	public $serverID = 0;
		
	public function setServerID ($serverID = 0)
	{
		$serverID = XXX_Default::toPositiveInteger($serverID, 0);
		
		$this->serverID = $serverID;
	}
	
	public function close ($doNotTriggerCloseEvent = false)
	{
		$result = false;
		
		if ($this->opened)
		{		
			if ($this->serverID > 0)
			{
				$socketServerInstance = XXX_SocketProcessor::getSocketServerInstanceForID($this->serverID);
				
				if ($socketServerInstance)
				{
					$socketServerInstance->removeClientID($this->ID);
				}
			}
			
			$result = parent::close($doNotTriggerCloseEvent);
		}
		
		return $result;
	}
	
	// Gets passed a socket resource from socket_accept  - mimics regular connect
	public function useAcceptedClientSocketResource ($socketResource)
	{
		$this->socketResource = $socketResource;
		
		$result = false;
		
		// 1. Is a valid resource
		if (XXX_Socket::isValidResource($this->socketResource))
		{		
			// 2. Get information on the local side of the socket (Check-up)
			$localEndPointInformation = $this->getLocalEndPointInformation();
			
			if ($localEndPointInformation !== false)
			{
				$this->settings['localHost'] = $localEndPointInformation['host'];
				$this->settings['localPort'] = $localEndPointInformation['port'];
				
				$remoteEndPointInformation = $this->getRemoteEndPointInformation();
				
				// 3. Get information on the remote side of the socket (Check-up)
				if ($remoteEndPointInformation !== false)
				{
					$this->setConnectedTimestampToNow();
					$this->connected = true;
				
					$this->settings['remoteHost'] = $remoteEndPointInformation['host'];
					$this->settings['remotePort'] = $remoteEndPointInformation['port'];
							
					$this->opened = true;
					
					$this->setBlockingMode($this->settings['blockingMode']);
					
					$this->onOpen();
					$this->onConnect();
					
					$result = true;
				}
			}
		}
		
		if (!$result)
		{
			$this->close();
		}
		
		return $result;
	}
}

?>