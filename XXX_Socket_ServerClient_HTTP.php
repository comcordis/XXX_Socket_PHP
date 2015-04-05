<?php

class XXX_Socket_ServerClient_HTTP extends XXX_Socket_ServerClient
{
	const CLASS_NAME = 'XXX_Socket_ServerClient_HTTP';
		
	private $keepAlive = false;
	
	public function onConnect ()
	{	
		parent::onConnect();
		
		$this->setReadDestinationToBuffer();
		$this->setReadFormatToHTTP();
		
		if (!$this->startReading())
		{		
			$this->localDisconnect();
		}
	}
	
	public function onReadComplete ()
	{
		parent::onReadComplete();
				
		$request = XXX_Socket_Server_HTTP::parseRequest($this->getReadBuffer());
		
		$this->clearReadBuffer();
				
		$this->keepAlive = $request['keepAlive'];
		$response = XXX_Socket_Server_HTTP::composeResponse($request, $this->idleTimeOut, $this->sessionTimeOut);
		
		//echo $response;
		
		$this->setWriteSourceToBuffer();
		$this->setWriteFormatToEmpty();
		$this->setWriteBuffer($response);
		
		if (!$this->startWriting())
		{
			$this->localDisconnect();
		}
	}

	public function onWriteComplete ()
	{
		$this->clearWriteBuffer();
		
		parent::onWriteComplete();
		
		if (!$this->keepAlive)
		{
			$this->remoteDisconnect();
		}
		// Potentially read the next request
		else
		{
			$this->setReadDestinationToBuffer();
			$this->setReadFormatToHTTP();
			
			if (!$this->startReading())
			{		
				$this->localDisconnect();
			}
		}
	}
}

?>