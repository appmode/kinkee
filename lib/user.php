<?php
//----------------------------------------------------------------------------//
// user.php
//----------------------------------------------------------------------------//
/*
 * User management class
 * 
 */

//----------------------------------------------------------------------------//
// Constants
//----------------------------------------------------------------------------//

define("USER_MUST_EXIST",       true);
define("USER_MUST_NOT_EXIST",   false);
define("HASH_PASSWORD",         true);

define("TOKEN_PASSWORD",        "PASSWORD");
define("TOKEN_VERIFY_EMAIL",    "VERIFY_EMAIL");

define("ERROR_USER_EMAIL_MISSING",             100);
define("ERROR_USER_PASSWORD_MISSING",          110);
define("ERROR_USER_PASSWORD_WRONG",            111);
define("ERROR_USER_USER_EXISTS",               200);
define("ERROR_USER_USER_NOT_EXISTS",           210);
define("ERROR_USER_MKDIR_FAIL",                300);
define("ERROR_USER_SAVE_FAIL",                 400);
define("ERROR_USER_READ_FAIL",                 450);
define("ERROR_USER_JSON_ENC_FAIL",             500);
define("ERROR_USER_JSON_DEC_FAIL",             600);
define("ERROR_USER_EMAIL_NOT_VALIDATED",       700);
define("ERROR_USER_TOKEN_INVALID",             800);
define("ERROR_USER_TOKEN_EXPIRED",             810);
define("ERROR_USER_TOKEN_MISSING",             820);

define("ERROR_USER_LOCK_FAIL",                 999);


//----------------------------------------------------------------------------//
// KinkeeUser Class
//----------------------------------------------------------------------------//
class KinkeeUser
{
	private $_strBaseDir;
	
	// error messages
	private $_arrError = Array(
		ERROR_USER_EMAIL_MISSING       => 'email address missing'),
		ERROR_USER_PASSWORD_MISSING    => 'password missing'),
		ERROR_USER_PASSWORD_WRONG      => 'password wrong'),
		ERROR_USER_USER_EXISTS         => 'user exists',
		ERROR_USER_USER_NOT_EXISTS     => 'user does not exist',
		ERROR_USER_MKDIR_FAIL          => 'could not make user folder',
		ERROR_USER_SAVE_FAIL           => 'save user record failed',
		ERROR_USER_READ_FAIL           => 'read user record failed',
		ERROR_USER_JSON_ENC_FAIL       => 'JSON encode failed',
		ERROR_USER_JSON_DEC_FAIL       => 'JSON decode failed',
		ERROR_USER_EMAIL_NOT_VALIDATED => 'email has not been validated',
		ERROR_USER_TOKEN_INVALID       => 'token is invalid',
		ERROR_USER_TOKEN_EXPIRED       => 'token has expired',
		ERROR_USER_TOKEN_MISSING       => 'token does not exist',
		
		ERROR_USER_LOCK_FAIL           => 'could not lock user record'
	);
	
	public function __KinkeeUser($strBaseDir)
	{
		// cache the base dir
		$this->_strBaseDir = rtrim($strBaseDir, '/');
	}
	
	//------------------------------------------------------------------------//
	// insert
	//------------------------------------------------------------------------//
	/**
	 * insert()
	 *
	 * insert a user record into the file store
	 *
	 * if the user already exists an exception will be thrown
	 * 
	 * if the password property is set it must be plain text as it will 
	 * automatically be hashed.
	 *
	 * @param  array   User        user data to insert
	 *
	 * @return  void
	 */
	public function insert($arrUser)
	{
		// save data
		$this->_setData($arrUser, USER_MUST_NOT_EXIST, HASH_PASSWORD);
	}
	
	//------------------------------------------------------------------------//
	// update
	//------------------------------------------------------------------------//
	/**
	 * update()
	 *
	 * update a user record in the file store
	 *
	 * if the user does not already exists an exception will be thrown
	 * 
	 * only data which exists as a property in $arrUser will be updated, any
	 * other existing data will remain.
	 * 
	 * The user record is locked during update.
	 *
	 * @param  array   User          user data to be updated
	 * @param  bool    HashPassword  optional control if the password will be 
	 *                               hashed (if it is set in $arrUser)
	 *                               true  = hash password (default)
	 *                               false = do not hash password
	 *
	 * @return  void
	 */
	public function update($arrUser, $bolHashPassword=true)
	{
		// lock folder
		$refLock = $this->lockFolder($strPath);
		
		try
		{
			// get existing user data
			$arrData = $this->_getData($arrUser['email']);
			
			// merge new data -> existing data
			array_replace($arrData, $arrUser);
			
			// if not given a password to save
			if (!array_key_exists('password', $arrUser))
			{
				// don't re-hash password
				$bolHashPassword = false;
			}
			
			// save data
			$this->_setData($arrUser, USER_MUST_EXIST, $bolHashPassword);
		}
		catch (Exception $e)
		{
			// unlock folder
			$this->unlockFolder($refLock);

			// rethrow exception
			throw $e;
		}
		
		// unlock folder
		$this->unlockFolder($refLock);		
	}
	
	//------------------------------------------------------------------------//
	// fetch
	//------------------------------------------------------------------------//
	/**
	 * fetch()
	 *
	 * fetch a user record from the file store
	 *
	 * The password paramater is automatically removed from the user record.
	 *
	 * @param  string  Email       email address of the user
	 *
	 * @return  array              the user record
	 */
	public function fetch($strEmail)
	{
		// get raw data
		$arrData = $this->_getData($strEmail);
		
		// remove password
		if (array_key_exists('password', $arrData))
		{
			unset($arrData['password']);
		}
		
		// return data
		return $arrData;
	}
	
	//------------------------------------------------------------------------//
	// login
	//------------------------------------------------------------------------//
	/**
	 * login()
	 *
	 * Perform a login (check the password) for a user.
	 * 
	 * If the login fails an exception will be thrown.
	 * 
	 * If the login is successful the user record is returned.
	 *
	 * The password paramater is automatically removed from the returned 
	 * user record.
	 *
	 * @param  string  Email       email address of the user
	 * @param  string  Password    password of the user
	 *
	 * @return  array              the user record
	 */
	public function login($strEmail, $strPassword)
	{
		// fetch user
		$arrUser = $this->_getData($strEmail);
		
		// fail if user doesn't have a password
		if (!array_key_exists('password', $arrUser))
		{
			$this->throwException(ERROR_USER_PASSWORD_MISSING);
		}
		
		// fail if user hasn't verified their email yet
		if ($this->hasToken($arrUser, TOKEN_VERIFY_EMAIL))
		{
			$this->throwException(ERROR_USER_EMAIL_NOT_VALIDATED);
		}
		
		// check password matches
		$strSalt  = $arrUser['password'];
		$strCrypt = crypt($strPassword, $strSalt);
		if ($strSalt != $strCrypt)
		{
			$this->throwException(ERROR_USER_PASSWORD_WRONG);
		}
		
		// remove password
		unset($arrData['password']);
		
		// return data
		return $arrData;
	}
	
	//------------------------------------------------------------------------//
	// resetPassword
	//------------------------------------------------------------------------//
	/**
	 * resetPassword()
	 *
	 * reset a users password, using a password reset token
	 *
	 * To reset a lost password, the user is emailed a token which can be used
	 * with this method to reset their password.
	 *
	 * @param  string  Email       email address of the user
	 * @param  string  Token       a token generated by makeToken()
	 * @param  string  Password    the new password to be set
	 *
	 * @return  void
	 */
	public function resetPassword($strEmail, $strToken, $strPassword)
	{
		// get path to user folder
		$strPath = $this->getFolderPath($strEmail, USER_MUST_EXIST);
		
		// lock folder
		$refLock = $this->lockFolder($strPath);
		
		try
		{
			// get user data
			$arrUser = $this->fetch($strEmail);
			
			// validate password reset token
			$this->validateToken($arrUser, TOKEN_PASSWORD, $strToken);
			
			// remove email verification token (if it exists)
			// user must have a valid email address to get password reset token
			$this->removeToken($arrUser, TOKEN_VERIFY_EMAIL);
			
			// add password to data
			$arrUser['password'] = $this->hashPassword($strPassword);
			
			// save data
			$this->_setData($arrUser, USER_MUST_EXIST);
		}
		catch (Exception $e)
		{
			// unlock folder
			$this->unlockFolder($refLock);

			// rethrow exception
			throw $e;
		}
		
		// unlock folder
		$this->unlockFolder($refLock);
	}
	
	//------------------------------------------------------------------------//
	// verifyEmailAddress
	//------------------------------------------------------------------------//
	/**
	 * verifyEmailAddress()
	 *
	 * verify a users email address
	 *
	 * To verify an email address, the user is emailed a token which can be used
	 * with this method to verify that the email address is real.
	 *
	 * @param  string  Email       email address of the user
	 * @param  string  Token       a token generated by makeToken()
	 *
	 * @return  return_type
	 */
	public function verifyEmailAddress($strEmail, $strToken)
	{
		// get path to user folder
		$strPath = $this->getFolderPath($strEmail, USER_MUST_EXIST);
		
		// lock folder
		$refLock = $this->lockFolder($strPath);
		
		try
		{
			// get user data
			$arrUser = $this->fetch($strEmail);
			
			// validate token
			$this->validateToken($arrUser, TOKEN_VERIFY_EMAIL, $strToken);
			
			// save data
			$this->_setData($arrUser, USER_MUST_EXIST);
		}
		catch (Exception $e)
		{
			// unlock folder
			$this->unlockFolder($refLock);

			// rethrow exception
			throw $e;
		}
		
		// unlock folder
		$this->unlockFolder($refLock);
		
		// return user data
		return $arrUser;
	}

	//------------------------------------------------------------------------//
	// makeToken
	//------------------------------------------------------------------------//
	/**
	 * makeToken()
	 *
	 * generates a random token and adds it to a user record
	 *
	 * @param  string  Email           email address of the user
	 * @param  string  Type            type of token to make
	 * @param  integer  ExpireInHours  optional number of hours before the 
	 *                                 token expires.
	 *                                 default = 99999 (about 11 years)
	 *
	 * @return  string                 the generated token
	 */
	
	//------------------------------------------------------------------------//
	// makeToken
	//------------------------------------------------------------------------//
	/**
	 * makeToken()
	 *
	 * short_comment
	 *
	 * long_comment
	 *
	 * @param  string  Email       param_description
	 * @param  string  Type        param_description
	 * @param  integer  ExpireInHours  optional param_description
	 *                                 default = 99999
	 *
	 * @return  return_type
	 */
	public function makeToken($strEmail, $strType, $intExpireInHours=99999)
	{
		// generate random token
		$s = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
		$strToken = "";
		for ($i = 0; $i < 65; $i++)
		{
			$strToken .= substr($s, mt_rand(0, 61), 1);
		}
		
		// add timestamp to token
		$strToken .= "-". (string)(time() + ((int)$intExpiry * 3600));
		
		// build data array
		$arrData  = Array(
			"email"             => $strEmail,
			"TOKEN::{$strType}" => $strToken
		)
		
		// update data
		$this->update($arrData);
		
		// return token
		return $strToken;
	}
	
	//------------------------------------------------------------------------//
	// validateToken
	//------------------------------------------------------------------------//
	/**
	 * validateToken()
	 *
	 * validate a token against a user data array
	 *
	 * If the token is valid it is removed from the user data array.
	 * If the token is invalid or doesn't exist in the user data array, an 
	 * exception will be thrown.
	 *
	 * @param  array   Data        the user record to validate against
	 * @param  string  Type        type of token to validate
	 * @param  string  Token       the token to validate
	 *
	 * @return  return_type
	 */
	public function validateToken($arrData, $strType, $strToken)
	{
		// fail if the token doesn't exist
		if (!$this->hasToken($arrUser, $strType))
		{
			$this->throwException(ERROR_USER_TOKEN_MISSING);
		}
		
		// validate the token
		if ($strToken !== $arrData["TOKEN::{$strType}"])
		{
			$this->throwException(ERROR_USER_TOKEN_INVALID);
		}
		
		// check if the token has expired
		$arrToken = explode('-', $strToken);
		$intTime  = (int)$arrToken[1];
		if (time() > $intTime)
		{
			$this->throwException(ERROR_USER_TOKEN_EXPIRED);
		}
		
		// remove the token
		$this->removeToken($arrData, $strType);
	}
	
	
	//------------------------------------------------------------------------//
	// removeToken
	//------------------------------------------------------------------------//
	/**
	 * removeToken()
	 *
	 * remove a token from a user data array
	 *
	 * @param  array   Data        the user record to remove token from
	 * @param  string  Type        type of token to remove
	 *
	 * @return  void
	 */
	public function removeToken($arrData, $strType)
	{
		$strToken = "TOKEN::{$strType}";
		if (array_key_exists($strToken, $arrData)
		{
			unset($arrData[$strToken]);
		}
	}
	
	//------------------------------------------------------------------------//
	// hasToken
	//------------------------------------------------------------------------//
	/**
	 * hasToken()
	 *
	 * check for the existence of a token in a user data array
	 *
	 * @param  array   Data        the user record to search
	 * @param  string  Type        type of token to search for
	 *
	 * @return  bool               true  = the token exists in the data array
	 *                             false = the token doesn't exist in the data 
	 *                                     array
	 */
	public function hasToken($arrData, $strType)
	{
		if (array_key_exists("TOKEN::{$strType}", $arrData)
		{
			return true;
		}
		return false;
	}

	//------------------------------------------------------------------------//
	// hashPassword
	//------------------------------------------------------------------------//
	/**
	 * hashPassword()
	 *
	 * make a hash of a password
	 *
	 * @param  string  Password    the password to be hashed
	 *
	 * @return  string             the hashed password
	 */
	public function hashPassword($strPassword)
	{
		$s = "./ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
		$strSalt = "$2a$07$";
		for ($i = 0; $i < 22; $i++)
		{
			$strSalt .= substr($s, mt_rand(0, 63), 1);
		}
		return crypt($strPassword, $strSalt);
	}
	
	//------------------------------------------------------------------------//
	// getFolderPath
	//------------------------------------------------------------------------//
	/**
	 * getFolderPath()
	 *
	 * get the folder path for a user
	 *
	 * If $bolMustExist is set to true and the user doesn't exist, or set to 
	 * false and the user does exist, an exception will be thrown.
	 *
	 * @param  mixed   User        email address of the user (string) or
	 *                             user data record (array)
	 * @param  bool    MustExist   optional specifies if the user must exist
	 *                             true  = user must exist
	 *                             false = user must not exist
	 *                             null  = user may or may not exist (default) 
	 *
	 * @return  string             path to the user folder
	 */
	public function getFolderPath($mixUser, $bolMustExist=null)
	{
		// allow for email address passed as a string
		if (is_string($mixUser))
		{
			$strEmail = $mixUser
		}
		// throw exception if passed an array wihout an email address
		elseif (!array_key_exists('email', $mixUser))
		{
			$this->throwException(ERROR_USER_EMAIL_MISSING);
		}
		// get email address from object
		else
		{
			$strEmail = $mixUser['email'];
		}
		
		// make hash of email
		$strHash = sha1($strEmail);
		
		// get path to user folder
		$strPath = "{$this->_strBaseDir}/{$strHash}";
		$strFile = "{$strPath}/json";
		
		// make folder if it doesn't exist
		if (!is_dir($strPath))
		{
			if (!mkdir($strPath, 0770)
			{
				$this->throwException(ERROR_USER_MKDIR_FAIL);
			}
		}
		
		// check user file exists
		if (true === $bolMustExist && !is_file($strFile))
		{
			$this->throwException(ERROR_USER_USER_NOT_EXISTS);
		}
		// check user file doesn't exist
		elseif (false === $bolMustExist && is_file($strFile))
		{
			$this->throwException(ERROR_USER_USER_EXISTS);
		}
		
		// return path
		return $strPath;
	}
	
	//------------------------------------------------------------------------//
	// lockFolder
	//------------------------------------------------------------------------//
	/**
	 * lockFolder()
	 *
	 * lock a user folder for write
	 *
	 * @param  string  Path        path to the user folder
	 *
	 * @return  reference          lock file refference to be passed to the
	 *                             unlockFolder() method
	 */
	public function lockFolder($strPath)
	{
		if (!$refLock = fopen("{$strPath}/lock", "r+"))
		{
			$this->throwException(ERROR_USER_LOCK_FAIL);
		}
		if (!flock($refLock, LOCK_EX))
		{
			fclose($refLock);
			$this->throwException(ERROR_USER_LOCK_FAIL);
		}
		return $refLock;
	}
	
	//------------------------------------------------------------------------//
	// unlockFolder
	//------------------------------------------------------------------------//
	/**
	 * unlockFolder()
	 *
	 * unlock a user folder
	 *
	 * @param  ref     Lock        lock file refference as returned by the 
	 *                             lockFolder() method
	 *
	 * @return  void
	 */
	public function unlockFolder($refLock)
	{
		flock($refLock, LOCK_UN);
		fclose($refLock);
	}
	
	//------------------------------------------------------------------------//
	// throwException
	//------------------------------------------------------------------------//
	/**
	 * throwException()
	 *
	 * throw an exception
	 *
	 * @param  integer  Error       error id (constant)
	 *
	 * @return  void
	 */
	public function throwException($intError)
	{
		$strError = $this->_arrError[$intError];
		throw new KinkeeUserException($strError, $intError);
	}
	
	//------------------------------------------------------------------------//
	// _getData  (PRIVATE METHOD)
	//------------------------------------------------------------------------//
	/**
	 * _getData()
	 *
	 * get a user record from the file store
	 *
	 * This is the low level file store function which actually reads from the 
	 * file store. The user record is returned complete.
	 *
	 * @param  string  Email       email address of the user
	 *
	 * @return  array              the user record
	 */
	private function _getData($strEmail)
	{			
		// get path to user folder
		$strPath = $this->getFolderPath($strEmail, USER_MUST_EXIST);
		
		// read file
		if (!$strData = file_get_contents("{$strPath}/json")
		{
			$this->throwException(ERROR_USER_READ_FAIL);
		}
		
		// json decode data
		if (!$arrData = json_decode($strData, true))
		{
			$this->throwException(ERROR_USER_JSON_DEC_FAIL);
		}
		
		// return data
		return $arrData;
	}
	
	//------------------------------------------------------------------------//
	// _setData  (PRIVATE METHOD)
	//------------------------------------------------------------------------//
	/**
	 * _setData()
	 *
	 * sava a user record in the file store
	 *
	 * This is the low level file store function which actually writes to the 
	 * file store. Any existing values will be overwritten.
	 *
	 * @param  array   Data          user data to be saved
	 * @param  bool    MustExist     optional specifies if the user must exist
	 *                               true  = user must exist
	 *                               false = user must not exist
	 *                               null  = user may or may not exist (default) 
	 * @param  bool    HashPassword  optional control if the password will be 
	 *                               hashed (if it is set in $arrUser)
	 *                               true  = hash password (default)
	 *                               false = do not hash password
	 *
	 * @return  void
	 */
	private function _setData($arrData, $bolMustExist=null, $bolHashPassword=false)
	{
		// get path to user folder
		$strPath = $this->getFolderPath($arrData, $bolMustExist);
		
		// hash password
		if (true === $bolHashPassword && array_key_exists('password', $arrData))
		{
			$arrUser['password'] = $this->hashPassword($arrData['password']);
		}
		
		// json encode data
		if (!$strData = json_encode($arrData))
		{
			$this->throwException(ERROR_USER_JSON_ENC_FAIL);
		}
		
		// save file
		if (!file_put_contents("{$strPath}/json", $strData)
		{
			$this->throwException(ERROR_USER_SAVE_FAIL);
		}
	}
}

//----------------------------------------------------------------------------//
// Exception classes
//----------------------------------------------------------------------------//

class KinkeeUserException extends Exception {}


?>
