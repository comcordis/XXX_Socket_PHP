<?php
/*
Keep-Alive: 115\r\n
*/

class XXX_Socket_Client_HTTP extends XXX_Socket_Client
{
	const CLASS_NAME = 'XXX_Socket_Client_HTTP';
		
	public function onConnect ()
	{	
		parent::onConnect();
		
		$request = "GET /index.html HTTP/1.0\r\n
		Host: localhost:2001\r\n
		User-Agent: ApacheBench/2.3\r\n
		Accept: */*\r\n
		\r\n";
				
		$this->setWriteSourceToBuffer();
		
		$this->setWriteBuffer($request);
		
		if ($this->startWriting())
		{
			$this->writeAvailableChunks();
		}
		else
		{
			$this->localDisconnect();
		}
	}
	
	public function onReadComplete ()
	{
		parent::onReadComplete();
		
		$response = $this->getReadBuffer();
		
		//echo '|' . $response . '|';
				
		$this->clearReadBuffer();
		
		$this->localDisconnect();
	}

	public function onWriteComplete ()
	{
		$this->clearWriteBuffer();
		
		parent::onWriteComplete();
		
		$this->setReadDestinationToBuffer();		
		$this->setReadFormatToHTTP();
		
		if ($this->startReading())
		{
			//sleep(2);
			
			$this->readAvailableChunks();
		}
		else
		{
			$this->localDisconnect();
		}
	}
}


?>