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
 * ClassName: Controller
 * Inherits: Nothing
 *
 * Description:
 * This is the controller for the webgui.
 * With the models, views and classes it handles the actions
 * and manipulates the data.
 *
 * The only public function is runAction, which calls the function
 * according to the action.
 * The default action is actionIndedx, which builds and then display
 * the index page, where a user can change his password and see his logs.
 *
 * Also there are functions to change the password,
 * read out a users log and escape input.
 */
class Controller{

	/**
	 * @var $html (object) variable for holding the html object
	 */
	private $html = null;

	/**
	 * Function: __construct
	 *
	 * Description:
	 * Creates a new html object.
	 */
	public function __construct(){
		$this->html = new Html();
	}

	/**
	 * Function: runAction
	 *
	 * Description:
	 * Calls the corresponding function depending on the action.
	 * Default action = index
	 *
	 * @param $action (string) the called action
	 */
	public function runAction($action='index'){
		if($action == 'index' || $action == 'pin'){
			$this->actionIndex();
		}elseif($_SESSION['user']['isAdmin']){
		// other actions are just for admins
			if($action == 'admin'){
				$this->actionAdmin();
			}elseif($action == 'log'){
				$this->actionLog();
			}elseif($action == 'useredit'){
				if(isset($_GET['id'])){
					$id = (int)$_GET['id'];
					$this->actionUserEdit($id);
				}else{
					$this->actionError('Missing paramaters.');
				}
			}elseif($action == 'userdelete'){
				if(isset($_POST['userid'])){
					$id = $_POST['userid'];
					$this->actionUserDelete($id);
				}else{
					$this->actionError('Missing paramaters.');
				}
			}elseif($action == 'multiplePermission'){
				if(isset($_POST['userid'])){
					$id = $_POST['userid'];
					$this->actionMultipleDeny($id);
				}else{
					$this->actionError('Missing paramaters.');
				}
			}elseif($action == 'useradd'){
				$this->actionUserAdd();
			}elseif($action == 'userview'){
				if(isset($_GET['id'])){
					$id = (int)$_GET['id'];
					$this->actionUserView($id);
				}else{
					$this->actionError('Missing paramaters.');
				}
			}else{
				$this->actionError('Requested action does not exist.');
			}
		}else{
			$this->actionError('Requested action does not exist.');
			return;
		}
	}

	/**
	 * Function: actionIndex
	 *
	 * Description:
	 * Build the index-page on which an user can change his pin
	 * and see his logs.
	 */
	private function actionIndex(){
		$conf = Config::getInstance();
		$max_password_length = $conf->max_password_length;
		
		$user = User::model()->findByAttributes(array('username' => $_SESSION['user']['username'] ));
		$user_log = $this->createUserLog($user);

		$message = '';
		if(isset($_POST['submit'])){
			$message = $this->changePassword($user);
			$_SESSION['newlogin'] = true;
		}

		$user_string = $user->givenName . ' ' . $user->surName .', ' . $user->department . ' (' . $user->username . ')';
		$content = $this->html->getPage('pin');
		$content = str_replace('{{USER_LOG}}', $user_log, $content);
		$content = str_replace('{{SAVE_MSG}}', $message, $content);
		$content = str_replace('{{MAX_PW_LENGTH}}', $max_password_length , $content);
		$content = str_replace('{{USERNAME}}', $user_string, $content);
		echo $this->html->buildPage('Password', $content, 'pin');
	}

	/**
	 * Function: actionError
	 *
	 * Description:
	 * Displays an Error.
	 *
	 * @param $error (string) The error message
	 */
	public function actionError($error){
		$content = $this->html->getPage('error');
		$content = str_replace('{{DETAIL_MSG}}', $error, $content);
		echo $this->html->buildPage('Error', $content, 'error');
		die();
	}

	/**
	 * Function: actionAdmin
	 *
	 * Description:
	 * Creates the Page which displays the list with all users.
	 *
	 * @param $saveMsg (string) The message which is displayed if an user was deleted.
	 */
	private function actionAdmin($saveMsg = ''){
		$users = User::model()->findAllByOrder(array('username'=>'ASC'));

		// array which holds the data for the table, first row = tbl head
		$tbl = array(
				array('UserID','Username','Name', 'Department', '<div style="width:100px;">Mobile</div>',
				'<div style="width:120px;">Last synced</div>', '<div style="width:120px;">Last Login</div>',
				'<div style="width:120px;">Last PW change</div>', '<div style="width:60px;">synced</div>', '<div style="width:100px;">Permission</div>', '', '', '')
		);

		$i=0;
		foreach($users as $user){
			//create the row for the usr and add all columns to the row
			$row = array();
			$row[] = $user->id;

			$row[] = $user->username;
			$row[] = $user->surName . ' ' . $user->givenName;
			$row[] = $user->department;
			$row[] = $user->mobileNumber;

			if(strtotime($user->lastSynced) < 0 || strtotime($user->lastSynced) === false){
				$row[] = 'Never';
			}else{
				$row[] =  date('H:i:s d.m.Y', strtotime($user->lastSynced));
			}
			if(strtotime($user->lastLogin) < 0 || strtotime($user->lastLogin) === false){
				$row[] = 'Never';
			}else{
				$row[] = date('H:i:s d.m.Y', strtotime($user->lastLogin));
			}
			if(strtotime($user->lastChange) < 0 || strtotime($user->lastChange) === false){
				$row[]  = 'Never';
			}else{
				$row[] = date('H:i:s d.m.Y', strtotime($user->lastChange));
			}

			//highlight the users which can't log in ( denied or not synced )
			if(!$user->synced){
				if($user->permissionStatus == 0)
					$row[] = '<div class="noLogin">No</div>';
				else
					$row[] = 'no';
			}else{
				$row[] = 'yes';
			}
			$row[] = (($user->permissionStatus == 1) ? '<div class="noLogin">'.$user->permissionStatusString().'</div>' : $user->permissionStatusString());

			$row[] = '<a href="index.php?action=userview&id=' . $user->id . '"><img src="./images/detail_16.png" /></a>';
			$row[] = '<a href="index.php?action=useredit&id=' . $user->id . '"><img src="./images/edit_16.png" /></a>';
			$user_string  = $user->surName . ' ' . $user->givenName . ', ' . $user->department . ' (' . $user->username . ')';
			$row[]  = '<a href="#" onclick="deleteUser(this, ' . $user->id . ', \'' . $user_string . '\')"><img src="./images/cancel_16.png" /></a>';
			// add the row to the array which holds the informations for the table
			$tbl[] = $row;
		}
		// create the table
		$user_table = $this->html->createTable($tbl, '', 'usertable');
		// build the page
		$content = $this->html->getPage('admin');
		$content = str_replace(array('{{SAVE_MSG}}', '{{USER_TABLE}}'), array($saveMsg, $user_table), $content);
		echo $this->html->buildPage('Admin', $content, 'admin');
	}

	/**
	 * Function: actionLog
	 *
	 * Description:
	 * Creates the page which displays all logs.
	 */
	private function actionLog(){
		// the array in which the informations for the table are hold
		$arr = array(array('User','Host', 'Time','Priority', 'Message'));

		// get all logs
		$logs = Log::model()->findAllByOrder(array('time'=>'DESC'));
		foreach($logs as $log){
			// add an row for every log the the array which holds the informations for the table
			$td_arr = array();
			if(isset($log->id_user) && $log->id_user){
				$user = User::model()->findByPk($log->id_user);
				$td_arr[] = $log->id_user . '( ' .$user->username . ')';
			}else{
				$td_arr[] =  $log->id_user;
			}
			$td_arr[] = $log->host ;
			if(strtotime($log->time) < 0 || strtotime($log->time) === false){
				$td_arr[] = 'Never';
			}else{
				$td_arr[] =  date('H:i:s d.m.Y', strtotime($log->time)) ;
			}
			$td_arr[] = $log->priority;
			$td_arr[] = $log->message;
			$arr[] = $td_arr;
		}
		// create the table
		$log_table = $this->html->createTable($arr, 'center', 'logtable');
		// build the page
		$content = $this->html->getPage('log');
		$content = str_replace('{{LOG_TABLE}}', $log_table, $content);
		echo $this->html->buildPage('Admin', $content, 'log');
	}

	/**
	 * Function: actionUserEdit
	 *
	 * Description:
	 * Creates the page to edit an user and save changes to an user in the db.
	 *
	 * @param $id (int) the id of the user
	 */
	private function actionUserEdit($id, $message = ''){
		$conf = Config::getInstance();
		$max_password_length = $conf->max_password_length;
		$user = User::model()->findByPk($id);
		if($user == null)
			$this->actionError('User does not exist.');

		// save password changes
		if(isset($_POST['submitPassword'])){
			$message = $this->changePassword($user);
			$l = new Logger();
			$l->info( $user->username . ' password set by Admin ('.$_SESSION['user']['username'].')');
		// save Permission changes
		}elseif(isset($_POST['submitPermi'])){
			$permission = (int)$_POST['permission'];
			if($permission == 0 || $permission == 1 || $permission == 2){
				$user->permissionStatus = $permission;
				$user->save();
				$message = '<div class="flash-success">Successsfully updated user.</div>';
				$l = new Logger();
				$l->info( $user->username . '\'s permission modified by Admin('.$_SESSION['user']['username'].')');

			}
		// save user information changes
		}elseif(isset($_POST['submitInfo'])){
			$v = new Validator();
			$message = '';

			// get post data
			$username = strtoupper($this->escape($_POST['username']));
			$surname = $this->escape($_POST['surName']);
			$givenname = $this->escape($_POST['givenName']);
			$email = $this->escape($_POST['email']);
			$notes = $this->escape($_POST['Notes']);
			$mobile = $this->escape($_POST['mobile']);

			// check if all required fields were filled
			if($username == NULL || $surname == NULL || $givenname == NULL){
				$message = 'Please fill all required fields';
			}else{
				// valite given informations and set message if invalid
				if(!$v->isString($username, 1, 1024))
					$message .= 'Username not valid! <br />';
				if(!$v->isString($surname, 2, 1024))
					$message .= 'Surename not valid! <br />';
				if(!$v->isString($givenname, 2, 1024))
					$message .= 'Givenname not valid! <br />';
				if($email != NULL && !$v->isEmail($email))
					$message .= 'Email not valid! <br />';
				if($notes != NULL && !$v->isString($notes, 0 , 1024))
					$message .= 'Notes not valid! <br />';
				if(($mobile = $v->isPhoneNumber($mobile)) == FALSE)
					$message .= 'Mobilenumber not valid! <br />';
			}
			// display a message if the username is already in use
			if(strtoupper($user->username) != $username){
				if(User::model()->findByAttributes(array('username' => $username )))
					$message .= 'Username already in use';
			}

			// if not everything was fine display the error message
			if($message != ''){
				$message = '<div class="flash-error">User not changed:  &nbsp;' . $message. ' </div>';
			}else{
				// if everything was fine, save the changes to the db
				$arg = array('username' => $username, 'surName' => $surname, 'givenName' => $givenname, 'email'=> $email, 'Notes' => $notes, 'mobileNumber' => $mobile );
				$user->populate($arg);
				$user->save();
				$message = '<div class="flash-success">Successsfully changed user ' . $surname . ' ' . $givenname . ' : ' . $username . '</div>';
				$l = new Logger();
				$l->info( $user->username . ' modified by Admin('.$_SESSION['user']['username'].')');
			}
		}

		// if the user is an extern user also display the fields to change his informations
		$editExtern = '';
		if($user->dn == NULL){
			$editExtern = $this->html->getPage('externEdit');
			$srch = array('{{EDIT_USER_ID}}', '{{USERNAME}}', '{{surName}}', '{{givenName}}', '{{email}}', '{{mobileNumber}}', '{{Notes}}', '{{MAX_PW_LENGTH}}' );
			$rplc = array($user->id, $user->username, $user->surName, $user->givenName, $user->email, $user->mobileNumber, $user->Notes, $max_password_length);
			$editExtern = str_replace($srch, $rplc, $editExtern);
		}

		// build page
		$content = $this->html->getPage('useredit');
		$user_string = $user->givenName . ' ' . $user->surName . ' (' . $user->username . ')';
		$search = array('{{EDIT_USER}}', '{{EDIT_USER_ID}}', '{{SAVE_MSG}}', '{{EXTERNEDIT}}', '{{MAX_PW_LENGTH}}');
		$replace = array($user_string, $user->id, $message, $editExtern, $max_password_length);
		$content = str_replace($search, $replace, $content);

		$rep_selected = array('', '', '');
		$rep_selected[($user->permissionStatus)] = 'selected';
		$search_selected = array('{{SELECT_0}}', '{{SELECT_1}}', '{{SELECT_2}}');
		$content = str_replace($search_selected, $rep_selected, $content);

		echo $this->html->buildPage('User edit', $content, 'admin');
	}

	/**
	 * Function: actionUserView
	 *
	 * Description:
	 * Creates the page which displays detailed information about an user.
	 *
	 * @param $id (int) The users id.
	 */
	private function actionUserView($id){
		$user = User::model()->findByPk($id);
		if($user == null)
			$this->actionError('User does not exist.');

		$user_string = $user->givenName . ' ' . $user->surName . ', ' . $user->department . ' (' . $user->username .') ';

		// build the table with the informations
		$arr = array(array('Property&nbsp;  &nbsp; ','Value'));
		if($user->dn !== NULL)
			$arr[] = array('dn', $user->dn);

		$arr[] = array('User', $user_string);
		$arr[] = array('Mobile', $user->mobileNumber);
		$arr[] = array('Telephone', $user->telephoneNumber);
		$arr[] = array('Email', $user->email);
		$arr[] = array('Address', $user->street);
		$arr[] = array('Location', $user->postalcode .' '. $user->city);
		if($user->web != NULL){
			$arr[] = array('Web','<a href="' .$user->web. '">Intranet profile</a>');
		}
		$arr[] = array('Notes', $user->Notes);
		$arr[] = array('Permission', $user->permissionStatusString());
		$arr[] = array('Synced', (($user->synced) ? 'Yes' : 'No'));

		if($user->password == NULL)
			$arr[] = array('Passwort','Not set');
		else
			$arr[] = array('Last PW change', $user->lastChange);

		if($user->synced == true)
			$arr[] = array('Last Synced', $user->lastSynced);
		$arr[] = array('LDAP User', (($user->dn) ? 'Yes' : 'No'));

		// create the table with the informations
		$user_table = $this->html->createTable($arr, 'center noFilter');
		// create the table with the logs
		$user_log = $this->createUserLog($user);

		// build and display the the page
		$content = $this->html->getPage('userview');
		$user_string .= '  <a href="index.php?action=useredit&id=' . $user->id . '"><img src="./images/edit_16.png" /></a>';
		$search = array('{{VIEW_USER}}', '{{USER_LOG}}', '{{USER_INFOS}}' );
		$replace = array($user_string, $user_log, $user_table);
		$content = str_replace($search, $replace, $content);
		echo $this->html->buildPage('View User', $content, 'userview');
	}

	/**
	 * Function: actionUserDelete
	 *
	 * Description:
	 * Deletes an user (or multiple users) and set the according log.
	 *
	 * @param $id (int) The users id
	 */
	private function actionUserDelete($users){
		$ids = explode(';', $users);

		$message = '<div class="flash-notice">';

		// loop trough all users 
		foreach($ids as $id){
			$user = User::model()->findByPk($id);
			if($user == null)
				$message .= 'User does not exist. Given id was:' . $id ;

			$username = $user->username;

			if($user->delete()){
				$message .= 'Successsfully deleted user: ' . $username . ' <br />';
				$l = new Logger();
				$l->info($username . ' removed by Admin('.$_SESSION['user']['username'].')');
			}else{
				$message = 'Error while deleting User: ' . $username . '<br />';
			}
		}

		$message .= '</div>';
		$this->actionAdmin($message);
	}

	/**
	 * Function:  actionUserAdd
	 *
	 * Description:
	 * Adds an user if all information were valid or displays
	 * the according error message.
	 */
	private function actionUserAdd(){
		$search_values = array('{{val_username}}', '{{val_surName}}', '{{val_givenName}}', '{{val_email}}', '{{val_tel}}', '{{val_notes}}', );
		$message = '';
		$user = '';

		//external (no LDAP) user
		if(isset($_POST['submitext'])){
			$user = new User();
			$v = new Validator();
			$message = '';

			// read out the post data and escape them
			$username = $this->escape(strtoupper($_POST['username']));
			$surname = $this->escape($_POST['surName']);
			$givenname = $this->escape($_POST['givenName']);
			$email = $this->escape($_POST['email']);
			$notes = $this->escape($_POST['Notes']);
			$mobile = $this->escape($_POST['mobile']);

			// check if all necessary field were filled
			if($username === NULL || $surname === NULL || $givenname === NULL || $mobile === NULL){
				$message = 'Please fill all required fields';
			}else{
				/** validate all informations (if given)
				 * if invalid informations were given, set according message
				 */
				if(!$v->isString($username, 1, 1024))
					$message .= 'Username not valid! Make sure the Username is exactly 8 characters long. <br />';
				if(!$v->isString($surname, 2, 1024))
					$message .= 'Surename not valid! <br />';
				if(!$v->isString($givenname, 2, 1024))
					$message .= 'Givenname not valid! <br />';
				if( $email != NULL && !$v->isEmail($email))
					$message .= 'Email not valid! <br />';
				if($notes !== NULL && !$v->isString($notes, 0 , 2048))
					$message .= 'Notes not valid! <br />';
				if(!$mobile = $v->isPhoneNumber($mobile))
					$message .= 'Mobilenumber not valid! <br />';
			}

			//display a message if the username is already used
			// but overwrite the user
			if(User::model()->findByAttributes(array('username' => $username )))
					$message .= 'Username already in use';

			// display the error message if not everything was fine
			if($message != ''){
				$message = '<div class="flash-error">' . $message. ' </div>';
				$content = $this->html->getPage('useradd');
				$content = str_replace('{{SAVE_MSG}}', $message, $content);
				$replace_values = array($username, $surname, $givenname, $email, $mobile, $notes);
				$content = str_replace($search_values, $replace_values, $content);
				echo $this->html->buildPage('add User', $content, 'useradd');
				return;
			}

			// save the user and set a log
			$arg = array('username' => $username, 'surName' => $surname, 'givenName' => $givenname, 'email'=> $email, 'Notes' => $notes, 'mobileNumber' => $mobile, 'permissionStatus' => '2');
			$user->populate($arg);
			$user->save();
			$l = new Logger();
			$l->info($user->username . ' added by Admin('.$_SESSION['user']['username'].')');
			$message = '<div style="max-width: 1200px; word-wrap: break-word;" class="flash-success"><p> Successsfully added user ' . $surname . ' ' . $givenname . ' : ' . $username . ' and set permission to explicit allowed. </p> <p>Please set an initial password for this user.</p> </div>';
			$this->actionUserEdit($user->id, $message);
			return;
		// internal (LDAP) user
		}elseif(isset($_POST['submitint']) && isset($_POST['NTAccount'])){
			$user = new User();
			$inst = Ldap::getInstance();
			$inst->connect();
			$inst->setSchema('user');
			$NTAccount = $_POST['NTAccount'];

			if(preg_match("/^[a-zA-Z0-9]+$/", $NTAccount) != 1) {
				// if the account name is invalid, set an error message
				$message .= '<div class="flash-error">Invalid username.</div>';
			}else{
				// search user in LDAP
				$arg = 'cn=' .$NTAccount;
				$ldap_array = $inst->search($arg);
				if($inst->isValidUser($ldap_array[0])){
					// if the user is a valid LDAP user remap the fetched informations
					$ret = User::model()->remap($ldap_array[0]);

					if(!array_key_exists('mobileNumber', $ret)){
						// no mobile
						$message .= '<div class="flash-notice"> This user has no mobile Number set in the AD and therefore can not login!</div>';
					}

					if( ($existingUser = User::model()->findByAttributes(array('username' => $NTAccount ))) != NULL){
						// the user already exists so update him
						$ret['id'] = $existingUser->id;
						$user->populate($ret);
						$user->save();
						$l = new Logger();
						$l->info($user->username . ' updated by Admin('.$_SESSION['user']['username'].')' );
						$message = '<div class="flash-notice"> User already exists.</div>';
					}else{
						// new user so add him
						$user->populate($ret);
						$user->synced = true;
						$user->lastSynced=date("Y-m-d H:i:s", time());
						$user->save();
						$l = new Logger();
						$l->info($user->username . ' added by Admin('.$_SESSION['user']['username'].')' );
						$message .= '<div class="flash-success"><p>Successsfully added user ' . $ret["givenName"] . ' ' . $ret["surName"] .', ' . $ret["username"] . ' : ' . $ret["department"]. '</p><p>Please set an initial password for this user</p></div>';
						$this->actionUserEdit($user->id, $message);
						return;
					}
				}else{
					// user wasn't found in LDAP
					$message = '<div class="flash-error">Invalid user. User not found in LDAP</div>';
				}
			}
		}

		// build the useradd-page and display the message
		$content = $this->html->getPage('useradd');
		$content = str_replace('{{SAVE_MSG}}', $message, $content);
		$content = str_replace($search_values, '', $content);
		echo $this->html->buildPage('add User', $content, 'useradd');
	}

	/**
	 * Function: changePassword
	 *
	 * Description:
	 * Validates the password and sets it in the db if valid.
	 *
	 * @param $user (object) The user
	 *
	 * @return $mesasge (string) error/success message
	 */
	private function changePassword($user){
		$conf = Config::getInstance();
		$max_password_length = $conf->max_password_length;
		$min_password_length = $conf->min_password_length;
		$v = new Validator();
		$pin1 = $_POST['pin1'];
		$pin2 = $_POST['pin2'];

		if(!$v->isString($pin1, $min_password_length, $max_password_length)){
			$message = '<div class="flash-error">Invalid password!';

			if($min_password_length == $max_password_length){
				$message .= 'Password must be exactly' . $max_password_length . 'digits long.';
			}else{
				$message .= 'Password must be between ' . $min_password_length. ' and ' .$max_password_length. ' digits long';
			}
			$message .= '</div>';

		}elseif($pin1 == $pin2){
			$user->password = $pin1;
			$user->save();
			$message = '<div class="flash-success">Successsfully changed password.</div>';
			$l = new Logger();
			$l->info( $user->username . ' changed his PIN');
		}else{
			$message = '<div class="flash-error">Password and retyped password  are not the same</div>';
		}

		return $message;
	}

	/**
	 * Function: actionMultipleDeny
	 * 
	 * Description:
	 * Sets multiple users to denied.
	 * 
	 * @param
	 * $users (array) the users
	 */
	private function actionMultipleDeny($users){
		$ids = explode(';', $users);

		$message = '<div class="flash-notice">';

		// loop trough all users 
		foreach($ids as $id){
			$user = User::model()->findByPk($id);
			if($user == null)
				$message .= 'User does not exist. Given id was:' . $id ;

			$username = $user->username;

			$user->permissionStatus = 1;
			$user->save();
			$message .= 'Successsfully updated user: ' . $user->username . '<br />';
			$l = new Logger();
			$l->info( $user->username . '\'s permission modified by Admin('.$_SESSION['user']['username'].')');
		}

		$message .= '</div>';
		$this->actionAdmin($message);
	}

	/**
	 * Function: createUserLog
	 *
	 * Description:
	 * This function gets all logs of the given user out of the db.
	 *
	 * @param $user (object) The user.
	 * @return $user_log (string) All logs from the user in a table.
	 */
	private function createUserLog($user){
		$tbl = array(
			array('Date', 'Activity'),
			array($user->lastLogin, 'Last login'),
			array($user->lastChange, 'Last password change'),
		);

		$logs = Log::model()->findAllByAttributes(array('id_user' => $user->id));
		if($logs != NULL){
			foreach($logs as $log){
				$tbl[] = array($log->time, $log->message);
			}
		}

		$user_log = $this->html->createTable($tbl, 'center noFilter');
		return $user_log;
	}

	/**
	 * Function: escape
	 *
	 * Description:
	 * Escapes htmlchars in the input
	 *
	 * @param $input (string) The to be escaped string
	 * @return $escaped (string) The escaped string
	 */
	private function escape($input){
			$escaped = htmlspecialchars(trim($input));
			return $escaped;
	}

}

?>
