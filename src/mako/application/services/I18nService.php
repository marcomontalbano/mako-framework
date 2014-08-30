<?php

/**
 * @copyright  Frederic G. Østby
 * @license    http://www.makoframework.com/license
 */

namespace mako\application\services;

use \mako\i18n\I18n;
use \mako\i18n\Loader;

/**
 * I18n service.
 *
 * @author  Frederic G. Østby
 */

class I18nService extends \mako\application\services\Service
{
	/**
	 * Registers the service.
	 * 
	 * @access  public
	 */

	public function register()
	{
		$this->container->registerSingleton(['mako\i18n\I18n', 'i18n'], function($container)
		{
			$app = $container->get('app');

			$cache = $container->get('config')->get('application')['language_cache'];

			$i18n = new I18n(new Loader($container->get('fileSystem'), $app->getApplicationPath() . '/i18n'), $app->getLanguage());

			if($cache !== false)
			{
				$i18n->setCache($container->get('cache')->instance($cache === true ? null : $cache));
			}

			return $i18n;
		});
	}
}