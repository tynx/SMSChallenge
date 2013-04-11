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
 * ClassName: Html
 * Inherits: Nothing
 *
 * Description:
 * This class provides functionality to build HTML Pages.
 * ( buildPage, getPage, buildNavigation and createTable)
 */
class Html{

	/**
	 * @var (string)  The path to site with the basic layout.
	 */
	private $layout = 'html/layout.html';

	/**
	 * @var (string) The base path to the views/ html files.
	 */
	private $basePath = 'html/';

	/**
	 * Function buildPage
	 *
	 * Description:
	 * Replaces the standart placeholders in the layout
	 * with the accoring data.
	 *
	 * @param $site (string) the site name
	 * @param $content (string)	the content
	 * @param $action (string) which action
	 *
	 * @return (string) the basic html page
	 */
	public function buildPage($site, $content, $action){
		if(!file_exists($this->layout)){
			throw new Exception('layout-page couldn\'t be found');
		}

		$html = file_get_contents($this->layout);
		$search = array('{{NAME}}',
						'{{SITE}}',
						'{{NAVIGATION}}',
						'{{CONTENT}}',
						'{{ACCOUNT_NAME}}',
					);

		$replace = array('SMSChallenge',
						$site,
						$this->buildNavigation($action),
						$content,
						$_SESSION['user']['username'],
					);

		return str_replace($search, $replace, $html);
	}

	/**
	 * Function: getPage
	 *
	 * Description:
	 * Get the content of a page (view).
	 *
	 * @param $page (string) the filename of the page
	 *
	 * @return (string) the content of the page
	 */
	public function getPage($page){
		if(file_exists($this->basePath . $page . '.html')){
			return file_get_contents($this->basePath . $page . '.html');
		}
		throw new Exception('requested page doesn\' exist');
	}

	/**
	 * Function bulidNavigation
	 *
	 * Description:
	 * Builds the navigation and displays the administrative pages just to admins.
	 * Also highlight the active page item.
	 *
	 * @param $action which action
	 *
	 * @return $nav (string) the navigation
	 */
	private function buildNavigation($action){
		$nav = '';
		if($action == 'index' || $action == 'pin'){
			$nav .= '<li class="active"><a href="index.php?action=pin">Home</a></li>';
		}else{
			$nav .= '<li><a href="index.php?action=pin">Home</a></li>';
		}

		if(isset($_SESSION['user']['isAdmin']) && $_SESSION['user']['isAdmin']){
			if($action == 'admin'){
				$nav .= '<li class="active"><a href="index.php?action=admin">Admin</a></li>';
			}else{
				$nav .= '<li><a href="index.php?action=admin">Admin</a></li>';
			}
			if($action == 'log'){
				$nav .= '<li class="active"><a href="index.php?action=log">Logs</a></li>';
			}else{
				$nav .= '<li><a href="index.php?action=log">Logs</a></li>';
			}
		}
		return $nav;
	}

	/**
	 * Function createTable
	 *
	 * Description:
	 * Creates a table.
	 *
	 * @param $tbl (String-Array) the content of the table
	 * @param $class optional css class for the table
	 * @param $id optional css id for the table
	 *
	 * @return $table (string) the created table
	 */
	public function createTable($tbl, $class = NULL, $id = NULL){
		$table = '<table';

		// dont' create an invalid table if no tbl content is given
		if(empty($tbl) || $tbl == NULL)
			return "";

		// set class and id if given
		if($class !== NULL)
			$table .= ' class="'.$class.'"' ;
		if($id !== NULL)
			$table .= ' id="'.$id.'"' ;
		$table .='> <thead>';

		$i = 0;
		foreach($tbl as $row){
			if($i == 0){
				foreach($row as $head ){
					$table .= '<th>'. $head .'</th>';
				}
				$table .= '</thead><tbody>';
			}else{
				$table .= ($i%2==0) ? '<tr class="even">' : '<tr class="odd">';

				foreach($row as $field ){
					$table .= '<td>'. $field .'</td>';
				}
				$table .= '</tr>';
			}
			$i++;
		}
		$table .= '</tbody></table>';
		return $table;
	}
}

?>
