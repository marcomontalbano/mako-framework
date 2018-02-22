<?php

/**
 * @copyright Frederic G. Østby
 * @license   http://www.makoframework.com/license
 */

namespace mako\reactor;

use mako\reactor\CommandInterface;
use mako\reactor\exceptions\InvalidArgumentException;
use mako\reactor\exceptions\InvalidOptionException;
use mako\reactor\exceptions\MissingArgumentException;
use mako\reactor\exceptions\MissingOptionException;
use mako\reactor\traits\SuggestionTrait;
use mako\syringe\Container;
use mako\utility\Str;

/**
 * Command dispatcher.
 *
 * @author Frederic G. Østby
 */
class Dispatcher
{
	use SuggestionTrait;

	/**
	 * Container.
	 *
	 * @var \mako\syringe\Container
	 */
	protected $container;

	/**
	 * Constructor.
	 *
	 * @param \mako\syringe\Container $container Container
	 */
	public function __construct(Container $container)
	{
		$this->container = $container;
	}

	/**
	 * Resolves the command.
	 *
	 * @param  string                         $command Command class
	 * @return \mako\reactor\CommandInterface
	 */
	protected function resolve(string $command): CommandInterface
	{
		return $this->container->get($command);
	}

	/**
	 * Checks for invalid arguments or options.
	 *
	 * @param \mako\reactor\CommandInterface $command           Command arguments
	 * @param array                          $providedArguments Provided arguments
	 */
	protected function checkForInvalidArguments(CommandInterface $command, array $providedArguments)
	{
		$commandArguments = array_keys($command->getCommandArguments() + $command->getCommandOptions());

		foreach(array_keys($providedArguments) as $name)
		{
			if(!in_array($name, ['arg0', 'arg1']) && !in_array($name, $commandArguments))
			{
				if(strpos($name, 'arg') === 0)
				{
					throw new InvalidArgumentException(vsprintf('Invalid argument [ %s ].', [$name]), $name);
				}

				throw new InvalidOptionException(vsprintf('Invalid option [ %s ].', [$name]), $name, $this->suggest($name, $commandArguments));
			}
		}
	}

	/**
	 * Checks for missing required arguments or options.
	 *
	 * @param array  $commandArguments  Command arguments
	 * @param array  $providedArguments Provided arguments
	 * @param string $exception         Exception to throw
	 */
	protected function checkForMissingArgumentsOrOptions(array $commandArguments, array $providedArguments, string $exception)
	{
		$providedArguments = array_keys($providedArguments);

		foreach($commandArguments as $name => $details)
		{
			if(isset($details['optional']) && $details['optional'] === false && !in_array($name, $providedArguments))
			{
				$type = $exception === MissingArgumentException::class ? 'argument' : 'option';

				throw new $exception(vsprintf('Missing required %s [ %s ].', [$type, $name]), $name);
			}
		}
	}

	/**
	 * Checks for missing required arguments.
	 *
	 * @param \mako\reactor\CommandInterface $command           Command instance
	 * @param array                          $providedArguments Provided arguments
	 */
	protected function checkForMissingArguments(CommandInterface $command, array $providedArguments)
	{
		$this->checkForMissingArgumentsOrOptions($command->getCommandArguments(), $providedArguments, MissingArgumentException::class);
	}

	/**
	 * Checks for missing required options.
	 *
	 * @param \mako\reactor\CommandInterface $command           Command instance
	 * @param array                          $providedArguments Provided arguments
	 */
	protected function checkForMissingOptions(CommandInterface $command, array $providedArguments)
	{
		$this->checkForMissingArgumentsOrOptions($command->getCommandOptions(), $providedArguments, MissingOptionException::class);
	}

	/**
	 * Checks arguments and options.
	 *
	 * @param \mako\reactor\CommandInterface $command           Command instance
	 * @param array                          $providedArguments Provided arguments
	 */
	protected function checkArgumentsAndOptions(CommandInterface $command, array $providedArguments)
	{
		if($command->isStrict())
		{
			$this->checkForInvalidArguments($command, $providedArguments);
		}

		$this->checkForMissingArguments($command, $providedArguments);

		$this->checkForMissingOptions($command, $providedArguments);
	}

	/**
	 * Converts arguments to camel case.
	 *
	 * @param  array $arguments Arguments
	 * @return array
	 */
	protected function convertArgumentsToCamelCase(array $arguments): array
	{
		return array_combine(array_map(function($key)
		{
			return Str::underscored2camel($key);
		}, array_keys($arguments)), array_values($arguments));
	}

	/**
	 * Executes the command.
	 *
	 * @param  \mako\reactor\CommandInterface $command   Command instance
	 * @param  array                          $arguments Command arguments
	 * @return mixed
	 */
	protected function execute(CommandInterface $command, array $arguments)
	{
		return $this->container->call([$command, 'execute'], $this->convertArgumentsToCamelCase($arguments));
	}

	/**
	 * Dispatches the command.
	 *
	 * @param  string $command   Command class
	 * @param  array  $arguments Command arguments
	 * @return int
	 */
	public function dispatch(string $command, array $arguments): int
	{
		$command = $this->resolve($command);

		if($command->shouldExecute())
		{
			$this->checkArgumentsAndOptions($command, $arguments);

			$returnValue = $this->execute($command, $arguments);
		}

		return isset($returnValue) ? (is_int($returnValue) ? $returnValue : CommandInterface::STATUS_SUCCESS) : CommandInterface::STATUS_SUCCESS;
	}
}
