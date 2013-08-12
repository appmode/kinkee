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

define("USER_INCLUDE_PASSWORD",                true);

define("USER_TOKEN_PASSWORD",                  "PASSWORD");
define("USER_TOKEN_VERIFY_EMAIL",              "VERIFY_EMAIL");

define("ERROR_USER_EMAIL_MISSING",             200);
define("ERROR_USER_PASSWORD_MISSING",          210);
define("ERROR_USER_PASSWORD_WRONG",            211);
define("ERROR_USER_EMAIL_NOT_VALIDATED",       220);
define("ERROR_USER_TOKEN_INVALID",             230);
define("ERROR_USER_TOKEN_EXPIRED",             231);
define("ERROR_USER_TOKEN_MISSING",             232);

//----------------------------------------------------------------------------//
// KinkeeUser Class
//----------------------------------------------------------------------------//
class KinkeeUser
{	
	// error messages
	private $_arrError = Array(
		ERROR_USER_EMAIL_MISSING       => 'email address missing',
		ERROR_USER_PASSWORD_MISSING    => 'password missing',
		ERROR_USER_PASSWORD_WRONG      => 'password wrong',
		ERROR_USER_EMAIL_NOT_VALIDATED => 'email has not been validated',
		ERROR_USER_TOKEN_INVALID       => 'token is invalid',
		ERROR_USER_TOKEN_EXPIRED       => 'token has expired',
		ERROR_USER_TOKEN_MISSING       => 'token does not exist'
	);
	
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
		// get record name
		$strRecord = $this->getRecordName($arrUser, DATA_MUST_NOT_EXIST);
		
		// insert record
		KinkeeDataStore::insert('user', $strRecord, $arrUser);
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
	public function update($arrUser)
	{
		// get record name
		$strRecord = $this->getRecordName($arrUser, DATA_MUST_EXIST);
		
		// hash password
		if (array_key_exists('password', $arrUser))
		{
			$arrUser['password'] = $this->hashPassword($arrUser['password']);
		}
		
		// update record
		KinkeeDataStore::update('user', $strRecord, $arrUser);
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
	public function fetch($strEmail, $bolIncludePassword=false)
	{
		// get record name
		$strRecord = $this->getRecordName($strEmail, DATA_MUST_EXIST);
		
		// get record
		$arrData = KinkeeDataStore::readData('user', $strRecord);
		
		// remove password
		if (true !== $bolIncludePassword)
		{
			if (array_key_exists('password', $arrData))
			{
				unset($arrData['password']);
			}
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
		$arrUser = $this->fetch($strEmail, USER_INCLUDE_PASSWORD);
		
		// fail if user doesn't have a password
		if (!array_key_exists('password', $arrUser))
		{
			$this->throwException(ERROR_USER_PASSWORD_MISSING);
		}
		
		// fail if user hasn't verified their email yet
		if ($this->hasToken($arrUser, USER_TOKEN_VERIFY_EMAIL))
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
		// get record name
		$strRecord = $this->getRecordName($strEmail, DATA_MUST_EXIST);
		
		// lock record
		$refLock = KinkeeDataStore::lockRecord('user', $strRecord);
		
		try
		{
			// get user data
			$arrUser = $this->fetch($strEmail);
			
			// validate password reset token
			$this->validateToken($arrUser, USER_TOKEN_PASSWORD, $strToken);
			
			// remove email verification token (if it exists)
			// user must have a valid email address to get password reset token
			$this->removeToken($arrUser, USER_TOKEN_VERIFY_EMAIL);
			
			// add password to data
			$arrUser['password'] = $this->hashPassword($strPassword);

			// save record
			KinkeeDataStore::writeData('user', $strRecord, $arrUser);
		}
		catch (Exception $e)
		{
			// unlock record
			KinkeeDataStore::unlockRecord($refLock);

			// rethrow exception
			throw $e;
		}
		
		// unlock record
		KinkeeDataStore::unlockRecord($refLock);
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
		// get record name
		$strRecord = $this->getRecordName($strEmail, DATA_MUST_EXIST);
		
		// lock record
		$refLock = KinkeeDataStore::lockRecord('user', $strRecord);
		
		try
		{
			// get user data
			$arrUser = $this->fetch($strEmail);
			
			// validate password reset token
			$this->validateToken($arrUser, USER_TOKEN_VERIFY_EMAIL, $strToken);
			
			// save record
			KinkeeDataStore::writeData('user', $strRecord, $arrUser);
		}
		catch (Exception $e)
		{
			// unlock record
			KinkeeDataStore::unlockRecord($refLock);

			// rethrow exception
			throw $e;
		}
		
		// unlock record
		KinkeeDataStore::unlockRecord($refLock);
		
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
	public function makeToken($strEmail, $strType, $intExpireInHours=99999)
	{
		// generate random token
		$strToken = $this->generateToken($intExpireInHours);
		
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
	// addToken
	//------------------------------------------------------------------------//
	/**
	 * addToken()
	 *
	 * generates a random token and adds it to a user data array
	 * 
	 * The user data array $arrData will be updated and the token string will
	 * be returned.
	 *
	 * @param  array   Data            the user record to add token to
	 * @param  string  Type            type of token to make
	 * @param  integer  ExpireInHours  optional number of hours before the 
	 *                                 token expires.
	 *                                 default = 99999 (about 11 years)
	 *
	 * @return  string                 the generated token
	 */
	public function addToken(&$arrData, $strType, $intExpireInHours=99999)
	{
		// generate random token
		$strToken = $this->generateToken($intExpireInHours);
		
		// add token to data array
		$arrData["TOKEN::{$strType}"] = $strToken;
		
		// return token
		return $strToken;
	}
	
	//------------------------------------------------------------------------//
	// generateToken
	//------------------------------------------------------------------------//
	/**
	 * generateToken()
	 *
	 * generates a random token
	 *
	 * @param  integer  ExpireInHours  optional number of hours before the 
	 *                                 token expires.
	 *                                 default = 99999 (about 11 years)
	 *
	 * @return  string                 the generated token
	 */
	public function generateToken($intExpireInHours=99999)
	{
		// generate random token
		$s = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
		$strToken = "";
		for ($i = 0; $i < 65; $i++)
		{
			$strToken .= substr($s, mt_rand(0, 61), 1);
		}
		
		// add timestamp to token
		$strToken .= "-". (string)(time() + ((int)$intExpireInHours * 3600));
		
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
	// getRecordName
	//------------------------------------------------------------------------//
	/**
	 * getRecordName()
	 *
	 * get the record path for a user
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
	 * @return  string             path to the user record
	 */
	public function getRecordName($mixUser, $bolMustExist=null)
	{
		// allow for email address passed as a string
		if (is_string($mixUser))
		{
			$strEmail = $mixUser;
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
		
		// check user record exists
		if (true === $bolMustExist)
		{
			KinkeeDataStore::recordMustExist('user', $strRecord);
		}
		// check user record doesn't exist
		elseif (false === $bolMustExist)
		{
			KinkeeDataStore::recordMustNotExist('user', $strRecord);
		}
		
		// make hash of email
		$strHash = sha1($strEmail);
		
		// return path
		return $strHash;
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
}

//----------------------------------------------------------------------------//
// Exception classes
//----------------------------------------------------------------------------//

class KinkeeUserException extends Exception {}


?>
