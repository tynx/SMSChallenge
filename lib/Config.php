<?php
/**
 * Copyright (C) 2013 Luginbühl Timon, Müller Lukas, Swisscom AG
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * For more informations see the license file or see <http://www.gnu.org/licenses/>.
 */
 
/**
 * ClassName: Config
 * Inherits: Nothing
 *
 * Description:
 * This class parses the config file. It automatically searches for the
 * config file in /etc/ or the local directory Once parsed this class
 * provides a fast way to return config-values. This is done by the
 * singleton pattern.
 */
class Config{

	/**
	 * @var (Config-Object) Singleton instance of itself
	 */
	private static $instance;

	/**
	 * @var (string) Default name of the config file (location
	 * independent)
	 */
	private $config_file='smschallenge.conf';

	/**
	 * @var (assoc-array) the loaded settings are saved in here
	 */
	private $settings = array();

	/**
	 * Function: __construct
	 *
	 * Description:
	 * Make constructor private so only one object can be created.
	 */
	private function __construct(){ }

	/**
	 * Function: __clone
	 *
	 * Description:
	 * Make clone private so only one object can be created.
	 */
	private function __clone(){ }

	/**
	 * Function: getInstance
	 *
	 * Description:
	 * Get the singleton instance of this class
	 *
	 * @return (Config object) the singleton instance
	 */
	public static function getInstance(){
		if (!isset(self::$instance)) {
			self::$instance = new Config();
			self::$instance->loadConfig();
		}
		return self::$instance;
	}

	/**
	 * Function: __get
	 *
	 * Description:
	 * Returns the value of the config line according to the key
	 *
	 * @param $key (string) the config-line name (key)
	 * @return (mixed) the config value
	 */
	public function __get($key){
		if(array_key_exists($key, $this->settings))
			return $this->settings[$key];
		return null;
	}

	/**
	 * Function: parseLine
	 *
	 * Description:
	 * Parses a single line according to some rules:
	 * * If the line starts with # the line is ignored
	 * * If the line has a # in it, it only parses until first occurence
	 * * If the line is empty, its ignored
	 * * If left-side assignement (x=y => x) is not given, line is ignored
	 * * If right-side assignement (x=y => y) is not given, line is ignored
	 * * If no "=" is given, line is ignored
	 * 
	 * Parsed lines are autmatically saved into the local
	 * $settings-array with the according key
	 *
	 * @param $line (string) a single line of the config
	 * @return (void) nothing
	 */
	private function parseLine($line){
		if(strpos($line, "#") !== FALSE){
			$line = substr($line, 0, strpos($line, "#"));
		}
		if($line == '' ) // The line is just a comment
			return;
		$args = explode("=", $line, 2);
		if(count($args) != 2) // We do not have a valid line
			return;
		$this->settings[$args[0]] = $args[1];
	}

	/**
	 * Function: loadConfig
	 *
	 * Description:
	 * Opens the config files. Searches in /etc/ and in the local folder
	 * and if no files is found throws an Exception. If a file is found
	 * every line is given to parseLine() to extract the information out
	 * of it.
	 *
	 * @return (void) nothing
	 */
	public function loadConfig(){
		$file='';
		if(file_exists('/etc/' . $this->config_file)){
			$file = '/etc/' . $this->config_file;
		}
		if(file_exists('./' . $this->config_file)){
			$file = './' . $this->config_file;
		}
		if($file=='')
			throw new Exception("No config file was found!");

		$content = file_get_contents($file);
		$lines = explode("\n", $content);
		foreach($lines as $line){
			$this->parseLine($line);
		}
	}
}

?>
