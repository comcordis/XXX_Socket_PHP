<?php

class XXX_Socket_Client_FTP_Data extends XXX_Socket_Client
{
	const CLASS_NAME = 'XXX_Socket_Client_FTP_Data';
	
	
	public function onConnect ()
	{	
		parent::onConnect();
		
		$this->disableBlockingMode();
	}
	
	public function disconnect ()
	{
		return $this->localDisconnect();
	}
}

?>