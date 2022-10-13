<?php

namespace Tarosky\Sitemap\Pattern;


/**
 * Singleton pattern.
 *
 * @package tsmap
 */
abstract class Singleton {

	/**
	 * @var static[]
	 */
	private static $instances = [];

	/**
	 * Encapsulated constructor.
	 */
	final protected function __construct() {
		$this->init();
	}

	/**
	 * Executed in constructor.
	 *
	 * @return void
	 */
	protected function init() {
		// Override this.
	}

	/**
	 * Get instance.
	 *
	 * @return static
	 */
	final public static function get_instance() {
		$class_name = get_called_class();
		if ( ! isset( self::$instances[ $class_name ] ) ) {
			self::$instances[ $class_name ] = new $class_name();
		}
		return self::$instances[ $class_name ];
	}
}
