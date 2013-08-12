<?php
//----------------------------------------------------------------------------//
// dataStore.php
//----------------------------------------------------------------------------//
/*
 * DataStore class
 * 
 */

//----------------------------------------------------------------------------//
// Constants
//----------------------------------------------------------------------------//

define("DATA_MUST_EXIST",       true);
define("DATA_MUST_NOT_EXIST",   false);

define("ERROR_DATA_EXISTS",               100);
define("ERROR_DATA_NOT_EXISTS",           110);
define("ERROR_DATA_MKDIR_FAIL",           120);
define("ERROR_DATA_WRITE_FAIL",           130);
define("ERROR_DATA_READ_FAIL",            140);
define("ERROR_DATA_JSON_ENC_FAIL",        150);
define("ERROR_DATA_JSON_DEC_FAIL",        160);

define("ERROR_DATA_LOCK_FAIL",            199);
//ERROR_DATA_MERGE_FAIL


//----------------------------------------------------------------------------//
// KinkeeDataStore Class
//----------------------------------------------------------------------------//
class KinkeeDataStore
{
	private static rtrim(dirname(__FILE__), '/')."/../store/";
	
	// error messages
	private static $_arrError = Array(
		ERROR_DATA_EXISTS              => 'record exists',
		ERROR_DATA_NOT_EXISTS          => 'record does not exist',
		ERROR_DATA_MKDIR_FAIL          => 'could not make folder',
		ERROR_DATA_SAVE_FAIL           => 'write record failed',
		ERROR_DATA_WRITE_FAIL          => 'read record failed',
		ERROR_DATA_JSON_ENC_FAIL       => 'JSON encode failed',
		ERROR_DATA_JSON_DEC_FAIL       => 'JSON decode failed',
		
		ERROR_DATA_LOCK_FAIL           => 'could not lock record'
	);
	
	public static function sanitizeRecordName($strRecord)
	{
		$strRecord = preg_replace('/[^a-zA-Z0-9\._]/', '-', $strRecord);
		$strRecord = trim($strRecord, '.-_');
		
		return $strRecord;
	}
	
	public static function recordExists($strSection, $strRecord, $strFileName='json')
	{
		$strPath = self::_strBaseDir."/$strSection/$strRecord/$strFileName";
		
		if (is_file($strPath))
		{
			return true;
		}
		return false;
	}
	
	public static function recordMustExist($strSection, $strRecord, $strFileName='json')
	{
		// check record exists
		if (false == self::recordExists($strSection, $strRecord, $strFileName))
		{
			self::throwException(ERROR_DATA_NOT_EXISTS);
		}
	}
	
	public static function recordMustNot($strSection, $strRecord, $strFileName='json')
	{
		// check record exists
		if (true == self::recordExists($strSection, $strRecord, $strFileName))
		{
			self::throwException(ERROR_DATA_EXISTS);
		}
	}
	
	//------------------------------------------------------------------------//
	// lockRecord
	//------------------------------------------------------------------------//
	/**
	 * lockRecord()
	 *
	 * lock a data record for write
	 *
	 * @param  string  Path        path to the data record
	 *
	 * @return  reference          lock file refference to be passed to the
	 *                             unlockRecord() method
	 */
	public static function lockRecord($strSection, $strRecord)
	{
		$strPath = self::_strBaseDir."/$strSection/$strRecord/.lock";
		
		if (!$refLock = fopen($strPath, "r+"))
		{
			self::throwException(ERROR_DATA_LOCK_FAIL);
		}
		if (!flock($refLock, LOCK_EX))
		{
			fclose($refLock);
			self::throwException(ERROR_DATA_LOCK_FAIL);
		}
		return $refLock;
	}
	
	//------------------------------------------------------------------------//
	// unlockRecord
	//------------------------------------------------------------------------//
	/**
	 * unlockRecord()
	 *
	 * unlock a data record
	 *
	 * @param  ref     Lock        lock file refference as returned by the 
	 *                             lockRecord() method
	 *
	 * @return  void
	 */
	public static function unlockRecord($refLock)
	{
		flock($refLock, LOCK_UN);
		fclose($refLock);
	}
	
	public static function readData($strSection, $strRecord, $strFileName='json')
	{
		$strPath = self::_strBaseDir."/$strSection/$strRecord/$strFileName";
		
		// read file
		if (!$strData = file_get_contents($strPath)
		{
			self::throwException(ERROR_DATA_READ_FAIL);
		}
		
		// json decode data
		if (!$arrData = json_decode($strData, true))
		{
			self::throwException(ERROR_DATA_JSON_DEC_FAIL);
		}
		
		// return data
		return $arrData;
	}
	
	public static function writeData($strSection, $strRecord, $arrData, $strFileName='json')
	{
		$strPath = self::_strBaseDir."/$strSection/$strRecord";
		$strFile = "{$strPath}/$strFileName";
		
		// make folder if it doesn't exist
		if (!is_dir($strPath))
		{
			if (!mkdir($strPath, 0770)
			{
				$this->throwException(ERROR_DATA_MKDIR_FAIL);
			}
		}
		
		// json encode data
		if (!$strData = json_encode($arrData))
		{
			self::throwException(ERROR_DATA_JSON_ENC_FAIL);
		}
		
		// save file
		if (!file_put_contents($strFile, $strData)
		{
			self::throwException(ERROR_DATA_WRITE_FAIL);
		}
	}
	
	public static function insert($strSection, $strRecord, $arrData, $strFileName='json')
	{
		// check record doesn't exist
		self::recordMustNotExist($strSection, $strRecord, $strFileName);
		
		// save data
		self::writeData($strSection, $strRecord, $arrData, $strFileName);
	}
	
	public static function update($strSection, $strRecord, $arrData, $strFileName='json')
	{
		// check record exists
		self::recordMustExist($strSection, $strRecord, $strFileName);
		
		// lock record
		$refLock = self::lockRecord($strSection, $strRecord);
		
		try
		{
			// get existing data
			$arrExistingData = self::readData($strSection, $strRecord, $strFileName);
			
			// merge new data -> existing data
			if (!$arrData = array_replace($arrExistingData, $arrData))
			{
				self::throwException(ERROR_DATA_MERGE_FAIL);
			}
			
			// save data
			self::writeData($strSection, $strRecord, $arrData, $strFileName);
		}
		catch (Exception $e)
		{
			// unlock record
			self::unlockRecord($refLock);

			// rethrow exception
			throw $e;
		}
		
		// unlock record
		self::unlockRecord($refLock);	
	}
	
	public static function fetch($strSection, $strRecord, $strFileName='json')
	{
		// check record exists
		self::recordMustExist($strSection, $strRecord, $strFileName);
		
		// return data
		return self::readData($strSection, $strRecord, $strFileName);
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
	public static function throwException($intError)
	{
		$strError = self::_arrError[$intError];
		throw new KinkeeDataException($strError, $intError);
	}
}

//----------------------------------------------------------------------------//
// Exception classes
//----------------------------------------------------------------------------//

class KinkeeDataException extends Exception {}


?>
