<?php
//----------------------------------------------------------------------------//
// dataRecord.php
//----------------------------------------------------------------------------//
/*
 * dataRecord base class
 * 
 */

//----------------------------------------------------------------------------//
// Constants
//----------------------------------------------------------------------------//

//----------------------------------------------------------------------------//
// KinkeeDataRecord Class
//----------------------------------------------------------------------------//
class KinkeeDataRecord
{
	protected $_strSection;
	
	protected $_strRecordNameKey;
	
	// error messages
	private $_arrError = Array(

	);
	
	//------------------------------------------------------------------------//
	// insert
	//------------------------------------------------------------------------//
	/**
	 * insert()
	 *
	 * insert a record into the file store
	 *
	 * if the record already exists an exception will be thrown
	 *
	 * @param  array   Data        data to insert
	 *
	 * @return  void
	 */
	public function insert($arrData)
	{
		// get record name
		$strRecord = $this->getRecordName($arrData, DATA_MUST_NOT_EXIST);
		
		// insert record
		KinkeeDataStore::insert($this->_strSection, $strRecord, $arrData);
	}
	
	//------------------------------------------------------------------------//
	// update
	//------------------------------------------------------------------------//
	/**
	 * update()
	 *
	 * update a record in the file store
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
		// get record name
		$strRecord = $this->getRecordName($arrData, DATA_MUST_EXIST);
		
		// update record
		KinkeeDataStore::update($this->_strSection, $strRecord, $arrData);
	}
	
	public function fetch($strName)
	{
		// get record name
		$strRecord = $this->getRecordName($strName, DATA_MUST_EXIST);
		
		// get record
		$arrData = KinkeeDataStore::readData($this->_strSection, $strRecord);
		
		// return data
		return $arrData;
	}
	
	
	public function getRecordName($mixData, $bolMustExist=null)
	{
		// allow for name passed as a string
		if (is_string($mixData))
		{
			$strName = $mixData;
		}
		// throw exception if passed an array wihout a name
		elseif (!array_key_exists($this->_strRecordNameKey, $mixData))
		{
			$this->throwException(ERROR_RECORD_NAME_MISSING);
		}
		// get name from object
		else
		{
			$strName = $mixData[$this->_strRecordNameKey];
		}
		
		// make safe record name
		$strRecord = $this->makeRecordName($strName);
		
		// check record exists
		if (true === $bolMustExist)
		{
			KinkeeDataStore::recordMustExist($_strSection, $strRecord);
		}
		// check user record doesn't exist
		elseif (false === $bolMustExist)
		{
			KinkeeDataStore::recordMustNotExist($_strSection, $strRecord);
		}
		
		// return path
		return $strRecord;
	}
	
	public function makeRecordName($strName)
	{
		return KinkeeDataStore::sanitizeRecordName($strName);
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
		throw new KinkeeRecordException($strError, $intError);
	}
}

//----------------------------------------------------------------------------//
// Exception classes
//----------------------------------------------------------------------------//

class KinkeeRecordException extends Exception {}


?>
