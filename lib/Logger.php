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
 * ClassName: Logger
 * Inherits: Nothing
 *
 * Description:
 * This class provides an easy way to set log messages into a mysql
 * database, syslog and files. Every possible combination is possible.
 * Also it is possible to set the amount of messages (level) which
 * should be set.
 */
class Logger{

	/**
	 * @var (string-array) Where should be logged? mysql/file/syslog
	 */
	private $log_types = array();

	/**
	 * @var (string) in case the file logging is activated to
	 * which file should be logged.
	 */
	private $log_file='';

	/**
	 * @var (string) the format of the date for each log message
	 */
	private $date_format='';

	/**
	 * @var (bool) should errors be logged?
	 */
	private $log_error=false;

	/**
	 * @var (bool) should warnings be logged?
	 */
	private $log_waring=false;

	/**
	 * @var (bool) should infos be logged?
	 */
	private $log_info=false;

	/**
	 * @var (bool) should debugs be logged?
	 */
	private $log_debug=false;

	/**
	 * @var (string) the prefix for the syslog-messages
	 */
	private $syslog_prefix='';

	/**
	 * Function: __construct
	 *
	 * Description:
	 * This function loads all the config for logging and saves them
	 * internaly so it has not be done for every log message.
	 */
	public function __construct(){
		$c=Config::getInstance();
		$this->log_types = explode(',', strtolower($c->log_type));
		$this->date_format=$c->date_format;
		$this->log_error = ($c->log_error == "1") ? true : false;
		$this->log_warning = ($c->log_warning == "1") ? true : false;
		$this->log_info = ($c->log_info == "1") ? true : false;
		$this->log_debug = ($c->log_debug == "1") ? true : false;

		if(in_array('file', $this->log_types)){
			$this->log_file=$c->log_file;
			if(empty($this->log_file) || empty($this->date_format))
				throw New Exception('Not all needed settings given for logging into a file!');
			if(!is_writable($this->log_file))
				throw New Exception('Permission error on log-file! File: ' . $this->log_file);
			
		}
		if(in_array('syslog', $this->log_types)){
			$this->syslog_prefix = $c->syslog_prefix;
		}
	}

	/**
	 * Function: setMsg
	 *
	 * Description:
	 * writes a single log message to all types based on the
	 * configuration
	 *
	 * @param $level (string) the name of the level
	 * @param $msg (string) the logmessage itself
	 * @param $user (string) the username of the user
	 */
	private function setMsg($level, $msg, $user){
		// For logging into a file
		if(in_array('file', $this->log_types)){
			if(!is_file($this->log_file) || !is_writable($this->log_file)){
				echo 'No valid log file! Or no permission!\n';
			}else{
				$line = date('[' . $this->date_format) . '] ';
				$line .= strtoupper($level) . ': ' . $msg;
				if($user!==NULL)
					$line .= ' (User: ' . $user . ')';
				$line .= "\n";
				$f = fopen($this->log_file, 'a');
				fwrite($f, $line);
				fclose($f);
			}
		}
		// For logging into syslog
		if(in_array('syslog', $this->log_types)){
			if($user !== NULL)
				$msg .= ' (User: ' . $user . ')';
			
			if($level == 'error')
				syslog(LOG_ERR, $this->syslog_prefix . ' ' .  strtoupper($level) .' '. $msg);
			if($level == 'warning')
				syslog(LOG_WARNING, $this->syslog_prefix . ' ' . strtoupper($level) .  ' ' . $msg);
			if($level == 'info')
				syslog(LOG_INFO, $this->syslog_prefix . ' ' . strtoupper($level) . ' ' . $msg);
			if($level == 'debug')
				syslog(LOG_DEBUG, $this->syslog_prefix . ' ' . strtoupper($level) . ' ' . $msg);
		}
		// For logging into mysql
		if(in_array('mysql', $this->log_types)){
			$u = User::model()->findByAttributes(array('username'=>$user));
			$l = new Log();
			if($u !== NULL)
				$l->id_user = $u->id;
			$l->host = gethostname();
			$l->time = date("Y-m-d H:i:s", time());
			$l->priority = $level;
			$l->message = $msg;
			$l->save();
		}
	}

	/**
	 * Function: error
	 *
	 * Description:
	 * Set an error log message
	 *
	 * @param $msg (string) the logmessage itself
	 * @param $user (string/optional) the username of the user
	 */
	public function error($msg, $user=NULL){
		if($this->log_error)
			$this->setMsg('error', $msg, $user);
	}

	/**
	 * Function: warning
	 *
	 * Description:
	 * Set a warning log message
	 *
	 * @param $msg (string) the logmessage itself
	 * @param $user (string/optional) the username of the user
	 */
	public function warning($msg, $user=NULL){
		if($this->log_warning)
			$this->setMsg('warning', $msg, $user);
	}

	/**
	 * Function: info
	 *
	 * Description:
	 * Set an info log message
	 *
	 * @param $msg (string) the logmessage itself
	 * @param $user (string/optional) the username of the user
	 */
	public function info($msg, $user=NULL){
		if($this->log_info)
			$this->setMsg('info', $msg, $user);
	}

	/**
	 * Function: debug
	 *
	 * Description:
	 * Set a debug log message
	 *
	 * @param $msg (string) the logmessage itself
	 * @param $user (string/optional) the username of the user
	 */
	public function debug($msg, $user=NULL){
		if($this->log_debug)
			$this->setMsg('debug', $msg, $user);
	}

	/**
	 * Function: getLogs
	 *
	 * Description:
	 * Returns lines of the log file. but only file!
	 *
	 * @param $lines (integer) the amount of lines that should be returned
	 * @return (string) the log lines
	 */
	public function getLogs($lines){
		$lines = (int)$lines;
		$data = shell_exec('tail -n ' . $lines . ' ' . $this->log_file);
		return $data;
	}
}

?>
