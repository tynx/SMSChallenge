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
 * ClassName: Validator
 * Inherits: Nothing
 *
 * Description:
 * This class provides functions to validate input.
 * You can validate if the input is a valid Email-Address, number,
 * phone number or a valid string.
 * The return value is alway false if input was not valid.
 * If the input is valid the return value is true except for the phonenumber.
 * The return value there is the phone number in the right format.
 */
Class Validator{

	/**
	 * @var (array) All allowed country codes.
	 */
	private $allowedCountryCodes = array('0041');

	/**
	 * @var (string) Default country codes.
	 */
	private $defaultCountryCode = '0041';

	/**
	 * Function: isNumber
	 *
	 * Description:
	 * Checks if the input is a number, if specified it also checks the length.
	 *
	 * @param $input the input which you want to validate
	 * @param $minlength the minimum length which the number should be long ( optional)
	 * @param $maxlength the maximum length which the number should be long ( optional)
	 * @return (bol) If the input is a valid number
	 */
	public function isNumber($input, $minlength=0, $maxlength=null){
		$valid = 0;
		if($input !== null && is_numeric($input)){
			$valid ++;
		}

		// check min length
		if($minlength !== null && strlen($input) >= $minlength){
			$valid ++;
		}elseif($maxlength === null){
			$valid ++;
		}

		// check max length
		if($maxlength !== null && strlen($input) <= $maxlength){
			$valid ++;
		}elseif($maxlength === null){
			$valid ++;
		}

		// if everthing is fine return true
		if($valid === 3)
			return true;
		return false;
	}

	/**
	 * Function: isString
	 *
	 * Description:
	 * Checks if the input is a string, if specified it also checks the length.
	 * @param $input the input which you want to validate
	 * @param $minlength the minimum length which the string should be long ( optional)
	 * @param $maxlength the maximum length which the string should be long ( optional)
	 * @return (bol) If the input is valid string
	 */
	public function isString($input, $minlength=0, $maxlength=null){
		$valid = 0;
		if($input !== null && is_string($input)){
			$valid ++;
		}

		// check max length
		if($maxlength !== null && strlen($input) <= $maxlength){
			$valid ++;
		}elseif($maxlength === null){
			$valid ++;
		}

		// check min length
		if($minlength !== null && strlen($input) >= $minlength){
			$valid ++;
		}elseif($minlength === null){
			$valid ++;
		}

		// if everthing is fine return true
		if($valid === 3)
			return true;
		return false;
	}

	/**
	 * Function: isEmail
	 *
	 * Description:
	 * Checks if the input is valid E-Mail Address.
	 *
	 * @param $input the input which you want to validate
	 * @return (bol) If the given email is valid
	 */
	public function isEmail($input){
		if (filter_var($input, FILTER_VALIDATE_EMAIL))
			return true;
		return false;
	}

	/**
	 * Function: isPhoneNumber
	 *
	 * Description:
	 * This function validates a phone number.
	 * As phonenumbers are a complicated/complex and
	 * sadly not really consistent. See RFC 3966.
	 * So feel free to implement your own validator as
	 * your country probably has its own rules/styles.
	 *
	 * @param $input the input which you want to validate
	 * @return (bol) If the given input is a valid phoneNumber
	 */
	public function isPhoneNumber($input){
		$tel = preg_replace("/[^0-9]/", '', $input);
		if($tel != '')
			return $tel;
		return false;
	}

	/**
	 * Function: isPhoneNumber
	 *
	 * Description:
	 * !!! CH - SWITZERLAND VERSION !!!
	 * Checks if the input is valid CH phoneNumber
	 *
	 * @param $input the input which you want to validate
	 * @return (string) the phone number in the correct format
	 */
/*	public function isPhoneNumber($input){
		// validation just for CH mobile Numbers!!!

		// just numbers
		$tel = preg_replace("/[^0-9]/", '', $input);
		$len = strlen($tel);

		// check length
		if($len < 10 || $len > 13)
			return false;

		// extract country code
		$cc = substr($tel, 0, ($len-9)); // country code
		$nmbr = substr($tel, $len-9, 9);

		//check country code and change the format
		if($cc == '0' || $cc == '41' || $cc == '0041' || $cc == '041'){
			$cc = '41';
		}else{
			return false;
		}

		// change format to rfc 3966 format --> +41-79-885 00 80
		$phoneNmbr = '+' . $cc .'-' . substr($nmbr, 0, 2) . '-' .substr($nmbr, 2, 3) . ' ' . substr($nmbr, 5, 2) . ' ' . substr($nmbr, 7, 2)  ;

		return $phoneNmbr;
	}
*/
}
?>
