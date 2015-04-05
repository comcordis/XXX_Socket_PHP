<?php

class XXX_Socket_Server_HTTP extends XXX_Socket_Server
{
	const CLASS_NAME = 'XXX_Socket_Server_HTTP';
	
	protected $socketServerClientClass = 'XXX_Socket_ServerClient_HTTP';
	
	public static $counter = 0;
	
	public static function parseRequest ($request = '')
	{
		$headers = XXX_String::splitToArray($request, XXX_String::$lineSeparator);
		
		$request = array();
		
		$request['uri'] = $headers[0];
		unset($headers[0]);
		
		// URI header
		
			// For example: "GET /index.html HTTP/1.1"
			
			$uri = $request['uri'];
			
			// Get part before first space
			$request['method'] = XXX_String::convertToLowerCase(XXX_String::getPart($uri, 0, XXX_String::findFirstPosition($uri, ' ')));
			
			// HTTP/1.0 or HTTP/1.1 - get the 1.0 or 1.1 part
			$request['version'] = XXX_String::getPart($uri, XXX_String::findFirstPosition($uri, 'HTTP/') + 5, 3);
			
			// Remove the method and a space
			$uri = XXX_String::getPart($uri, XXX_String::getByteSize($request['method']) + 1);
			
			// Get the part untill the next space
			$request['url'] = XXX_String::getPart($uri, 0, XXX_String::findFirstPosition($uri, ' '));
			
		// Other headers
					
		foreach ($headers as $line)
		{
			$line = XXX_String::trim($line);
			
			if ($line != '')
			{
				$separatorPosition = XXX_String::findFirstPosition($line, ':');
				
				$header = XXX_String::getPart($line, 0, $separatorPosition);
				$value = XXX_String::trim(XXX_String::getPart($line, $separatorPosition + 1));
				
				$request[XXX_String::convertToLowerCase($header)] = XXX_String::convertToLowerCase($value);
			}
		}
		
		$request['keepAlive'] = false;
		
		foreach ($request as $header => $value)
		{
			if ($header == 'connection' && $value == 'keep-alive')
			{
				// Disable to test rapid connecting/disconnecting
				//$request['keepAlive'] = true;
			}
		}
		
		return $request;
	}
	
	public static function composeResponse (array $request, $idleTimeOut = 10, $sessionTimeOut = 10)
	{
		$validVersion = ($request['version'] && ($request['version'] == '1.0' || $request['version'] == '1.1'));
		$validMethod = ($request['method'] && ($request['method'] == 'get' || $request['method'] == 'post'));

		if (!$validVersion || !$validMethod)
		{
			$header = 'HTTP/' . $request['version'] .' 400 Bad Request' . "\r\n";
			$output = '400: Bad request';
			$header .= 'Content-Length: ' . XXX_String::getByteSize($output) . "\r\n";
		}
		else
		{
			$request['url'] = '/index.html';
			
			
			// handle request
			if (XXX_Type::isEmpty($request['url']))
			{
				$request['url'] = '/';
			}
			
			// Default directory index
			if ($request['url'] == '/' || $request['url'] == '')
			{
				$request['url'] = '/index.html';
			}
			
			// parse get params into $params variable
			
			$questionMarkPosition = XXX_String::findFirstPosition($request['url'], '?');
			
			if ($questionMarkPosition !== false)
			{
				$queryString = XXX_String::getPart($request['url'], $questionMarkPosition + 1);
								
				$params = explode('&', $queryString);
				
				foreach ($params as $key => $param)
				{
					$pair = explode('=', $param);
					
					$params[$pair[0]] = isset($pair[1]) ? $pair[1] : '';
					unset($params[$key]);
				}
				
				// Cut off query string
				$request['url'] = substr($request['url'], 0, $questionMarkPosition);
			}

			$file = 'c:/server/phpSocketDaemon/htdocs' . $request['url'];
			
			if (true || (file_exists($file) && is_file($file)))
			{
				$header = 'HTTP/' . $request['version'] . ' 200 OK' . "\r\n";
				
				$header .= 'Accept-Ranges: bytes' . "\r\n";
				$header .= 'Last-Modified: ' . gmdate('D, d M Y H:i:s T', filemtime($file)) . "\r\n";
				
				$body = 'Count: ' . (++self::$counter);
				
				//$body = 'Hello';
				
				$size = XXX_String::getByteSize($body);
				
				//$size = filesize($file);
				$header .= 'Content-Length: ' . $size . "\r\n";
				//$body = file_get_contents($file); // TODO loop direct flushing read/write to socket instead of in memory as a string...
			}
			else
			{
				$header = 'HTTP/' . $request['version'] . ' 404 Not Found' . "\r\n";
				
				$body = '<h1>404: Document not found.</h1>';
				$header .= 'Content-Length: ' . XXX_String::getByteSize($body) . "\r\n";
			}
		}
		
		$header .= 'Date: ' . gmdate('D, d M Y H:i:s T') . "\r\n";
		
		if ($request['keepAlive'])
		{
			$header .= 'Connection: Keep-Alive' . "\r\n";
			$header .= 'Keep-Alive: timeout=' . $idleTimeOut . ' max=' . $sessionTimeOut . "\r\n";
		}
		else
		{			
			$header .= 'Connection: Close' . "\r\n";
		}
				
		return $header . "\r\n" . $body;
	}	
}



?>