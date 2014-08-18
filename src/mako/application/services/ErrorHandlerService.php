<?php

/**
 * @copyright  Frederic G. Østby
 * @license    http://www.makoframework.com/license
 */

namespace mako\application\services;

use \mako\error\ErrorHandler;
use \mako\error\handlers\CLIHandler;
use \mako\error\handlers\WebHandler;

/**
 * Error handler service.
 *
 * @author  Frederic G. Østby
 */

class ErrorHandlerService extends \mako\application\services\Service
{
	/**
	 * Helper method that ensures lazy loading of the logger.
	 * 
	 * @access  protected
	 * @param   \mako\error\ErrorHandler  $errorHandler  Error handler instance
	 */

	protected function setLogger($errorHandler)
	{
		if($this->container->get('config')->get('application.error_handler.log_errors'))
		{
			$errorHandler->setLogger($this->container->get('logger'));
		}	
	}

	/**
	 * Registers the service.
	 * 
	 * @access  public
	 */

	public function register()
	{
		$errorHandler = new ErrorHandler();

		// Register the appropriate exception handler

		if($this->container->get('app')->isCommandLine())
		{
			$errorHandler->handle('\Exception', function($exception) use ($errorHandler)
			{
				$this->setLogger($errorHandler);

				return (new CLIHandler($exception))->handle($this->container->get('config')->get('application.error_handler.display_errors'));
			});
		}
		else
		{
			$errorHandler->handle('\Exception', function($exception) use ($errorHandler)
			{
				$this->setLogger($errorHandler);

				$webHandler = new WebHandler($exception);

				$webHandler->setRequest($this->container->get('request'));

				$webHandler->setResponse($this->container->getFresh('response'));

				return $webHandler->handle($this->container->get('config')->get('application.error_handler.display_errors'));
			});
		}

		// Register error handler in the container

		$this->container->registerInstance(['mako\error\ErrorHandler', 'errorHandler'], $errorHandler);
	}
}