<?php

namespace mako;

use \mako\Config;

/**
* Mako response class.
*
* @author     Frederic G. Østby
* @copyright  (c) 2008-2012 Frederic G. Østby
* @license    http://www.makoframework.com/license
*/

class Response
{
	//---------------------------------------------
	// Class variables
	//---------------------------------------------
	
	/**
	* Holds the response body.
	*
	* @var string
	*/
	
	protected $body = '';
	
	/**
	* Asset location.
	*
	* @var string
	*/
	
	protected $assetLocation;

	/**
	* Check ETag?
	*
	* @var boolean
	*/

	protected $checkEtag = false;
	
	/**
	* Compress output?
	*
	* @var boolean
	*/
	
	protected $compressOutput;
	
	/**
	* List of HTTP status codes.
	*
	* @var array
	*/
	
	protected $statusCodes = array
	(
		// 1xx Informational
		
		'100' => 'Continue',
		'101' => 'Switching Protocols',
		'102' => 'Processing',
		
		// 2xx Success
		
		'200' => 'OK',
		'201' => 'Created',
		'202' => 'Accepted',
		'203' => 'Non-Authoritative Information',
		'204' => 'No Content',
		'205' => 'Reset Content',
		'206' => 'Partial Content',
		'207' => 'Multi-Status',
		
		// 3xx Redirection
		
		'300' => 'Multiple Choices',
		'301' => 'Moved Permanently',
		'302' => 'Found',
		'303' => 'See Other',
		'304' => 'Not Modified',
		'305' => 'Use Proxy',
		//'306' => 'Switch Proxy',
		'307' => 'Temporary Redirect',
		
		// 4xx Client Error
		
		'400' => 'Bad Request',
		'401' => 'Unauthorized',
		'402' => 'Payment Required',
		'403' => 'Forbidden',
		'404' => 'Not Found',
		'405' => 'Method Not Allowed',
		'406' => 'Not Acceptable',
		'407' => 'Proxy Authentication Required',
		'408' => 'Request Timeout',
		'409' => 'Conflict',
		'410' => 'Gone',
		'411' => 'Length Required',
		'412' => 'Precondition Failed',
		'413' => 'Request Entity Too Large',
		'414' => 'Request-URI Too Long',
		'415' => 'Unsupported Media Type',
		'416' => 'Requested Range Not Satisfiable',
		'417' => 'Expectation Failed',
		'418' => 'I\'m a teapot',
		'421' => 'There are too many connections from your internet address',
		'422' => 'Unprocessable Entity',
		'423' => 'Locked',
		'424' => 'Failed Dependency',
		'425' => 'Unordered Collection',
		'426' => 'Upgrade Required',
		'449' => 'Retry With',
		'450' => 'Blocked by Windows Parental Controls',
		
		// 5xx Server Error
		
		'500' => 'Internal Server Error',
		'501' => 'Not Implemented',
		'502' => 'Bad Gateway',
		'503' => 'Service Unavailable',
		'504' => 'Gateway Timeout',
		'505' => 'HTTP Version Not Supported',
		'506' => 'Variant Also Negotiates',
		'507' => 'Insufficient Storage',
		'509' => 'Bandwidth Limit Exceeded',
		'510' => 'Not Extended',
		'530' => 'User access denied',
	);
	
	/**
	* Output filter (callback function).
	*
	* @var callback
	*/
	
	protected $outputFilter;
	
	//---------------------------------------------
	// Class constructor, destructor etc ...
	//---------------------------------------------
	
	/**
	* Constructor.
	*
	* @access  protected
	* @param   string     (optional) Response body
	*/
	
	public function __construct($body = null)
	{
		$config = Config::get('response');
		
		$this->assetLocation  = $config['asset_location'];
		$this->compressOutput = $config['compress_output'];

		if($body !== null)
		{
			$this->body($body);
		}
	}

	/**
	* Factory method making method chaining possible right off the bat.
	*
	* @access  public
	* @param   string         (optional) Response body
	* @return  mako\Response
	*/

	public static function factory($body = null)
	{
		return new static($body);
	}
	
	//---------------------------------------------
	// Class methods
	//---------------------------------------------

	/**
	* Sets the response body.
	*
	* @access  public
	* @param   string    Response body
	*/

	public function body($body)
	{
		$this->body = (string) $body;
	}
	
	/**
	* Adds output filter that all output will be passed through before being sent.
	*
	* @access  public
	* @param   callback  Callback function used to filter output
	*/
	
	public function filter($filter)
	{
		$this->outputFilter = $filter;
	}
	
	/**
	* Sends HTTP status header.
	*
	* @access  public
	* @param   int     HTTP status code
	*/
	
	public function status($statusCode)
	{
		if(isset($this->statusCodes[$statusCode]))
		{
			if(isset($_SERVER['FCGI_SERVER_VERSION']))
			{
				$protocol = 'Status:';
			}
			else
			{
				$protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
			}
			
			
			header($protocol . ' ' . $statusCode . ' '. $this->statusCodes[$statusCode]);
		}
	}
	
	/**
	* Redirects to another location.
	*
	* @access  public
	* @param   string  (optional) Location
	* @param   int     (optional) HTTP status code
	*/
	
	public function redirect($location = '', $statusCode = 302)
	{
		$this->status($statusCode);

		if(strpos($location, '://') === false)
		{
			$location = URL::to($location);
		}
		
		header('Location: ' . $location);
		
		exit();
	}

	/**
	* Will enable response cache using ETags.
	*
	* @access  public
	*/

	public function cache()
	{
		$this->checkEtag = true;
	}
	
	/**
	* Send output to browser.
	*
	* @access  public
	* @param   int     (optional) HTTP status code
	*/
	
	public function send($statusCode = null)
	{
		if($statusCode !== null)
		{
			$this->status($statusCode);
		}

		// Print output to browser (if there is any)

		if($this->body !== '')
		{
			$search  = array
			(
				'[mako:exe_time]',
				'[mako:assets]',
				'[mako:version]',
				'[mako:charset]',
			);

			$replace = array
			(
				round(microtime(true) - MAKO_START, 4),
				$this->assetLocation,
				Mako::VERSION,
				MAKO_CHARSET,
			);

			$this->body = str_ireplace($search, $replace, $this->body);
			
			// Pass output through filter
			
			if(!empty($this->outputFilter))
			{
				$this->body = call_user_func($this->outputFilter, $this->body);
			}

			// Check ETag

			if($this->checkEtag === true)
			{
				$hash = '"' . sha1($this->body) . '"';

				header('ETag: ' . $hash);

				if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $hash === $_SERVER['HTTP_IF_NONE_MATCH'])
				{
					$this->status(304);

					return; // Don't send any output
				}
			}

			// Compress output (if enabled)

			if($this->compressOutput === true)
			{
				ob_start('ob_gzhandler');
			}

			echo $this->body;
		}
	}

	/**
	* Method that magically converts the response object into a string.
	*
	* @access  public
	* @return  string
	*/

	public function __toString()
	{
		return $this->body;
	}
}

/** -------------------- End of file --------------------**/