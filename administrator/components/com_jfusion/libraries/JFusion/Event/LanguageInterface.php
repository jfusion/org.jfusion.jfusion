<?php namespace JFusion\Event;

/**
 * Created by PhpStorm.
 * User: fanno
 * Date: 18-03-14
 * Time: 14:14
 */
interface LanguageInterface
{
	/**
	 * Loads a language file for framework
	 *
	 * @return  boolean if loaded or not
	 */
	function onLanguageLoadFramework();

	/**
	 * Loads a language file for plugin
	 *
	 * @param   string  $jname Plugin name
	 *
	 * @return  boolean if loaded or not
	 */
	function onLanguageLoadPlugin($jname);
}