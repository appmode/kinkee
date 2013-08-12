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
class KinkeeUser extends KinkeeDataRecord
{
	protected $_strSection       = 'user';
	
	protected $_strRecordNameKey = 'email';
	
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
	 * if a password property exists it will be automatically hashed
	 *
	 * if the record already exists an exception will be thrown
	 *
	 * @param  array   Data        data to insert
	 *
	 * @return  void
	 */
	public function insert($arrData)
	{
		if (array_key_exists('password', $arrData))
		{
			$arrData['password'] = $this->hashPassword($arrData['password']);
		}
		parent::insert($arrData);
	}
	
	//------------------------------------------------------------------------//
	// update
	//------------------------------------------------------------------------//
	/**
	 * update()
	 *
	 * update a user record in the file store
	 * 
	 * if a password property exists it will be automatically hashed
	 *
	 * if the record does not already exists an exception will be thrown
	 * 
	 * only data which exists as a property in $arrData will be updated, any
	 * other existing data will remain.
	 * 
	 * The user record is locked during update.
	 *
	 * @param  array   Data          data to be updated
	 *
	 * @return  void
	 */
	public function update($arrData)
	{
		if (array_key_exists('password', $arrData))
		{
			$arrData['password'] = $this->hashPassword($arrData['password']);
		}
		parent::update($arrData);
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
		// fetch record
		$arrData = parent::fetch($strEmail);
		
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
		$arrData = $this->fetch($strEmail, USER_INCLUDE_PASSWORD);
		
		// fail if user doesn't have a password
		if (!array_key_exists('password', $arrData))
		{
			$this->throwException(ERROR_USER_PASSWORD_MISSING);
		}
		
		// fail if user hasn't verified their email yet
		if ($this->hasToken($arrData, USER_TOKEN_VERIFY_EMAIL))
		{
			$this->throwException(ERROR_USER_EMAIL_NOT_VALIDATED);
		}
		
		// check password matches
		$strSalt  = $arrData['password'];
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
			$arrData = $this->fetch($strEmail);
			
			// validate password reset token
			$this->validateToken($arrData, USER_TOKEN_PASSWORD, $strToken);
			
			// remove email verification token (if it exists)
			// user must have a valid email address to get password reset token
			$this->removeToken($arrData, USER_TOKEN_VERIFY_EMAIL);
			
			// add password to data
			$arrData['password'] = $this->hashPassword($strPassword);

			// save record
			KinkeeDataStore::writeData('user', $strRecord, $arrData);
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
			$arrData = $this->fetch($strEmail);
			
			// validate password reset token
			$this->validateToken($arrData, USER_TOKEN_VERIFY_EMAIL, $strToken);
			
			// save record
			KinkeeDataStore::writeData('user', $strRecord, $arrData);
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
		return $arrData;
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
		if (!$this->hasToken($arrData, $strType))
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
		
	public function makeRecordName($strName)
	{
		return sha1($strName);
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
