<?php
//----------------------------------------------------------------------------//
// build.php
//----------------------------------------------------------------------------//
/*
 * Build
 * 
 * Provides functionality to build static pages for the site
 * 
 */

//----------------------------------------------------------------------------//
// KinkeeBuild Class
//----------------------------------------------------------------------------//
class KinkeeBuild
{
	private $_strBasePath = "";
	public function renderJS()
	{
		//--------------------------------------------------------------------//
		// render w3.js
		//--------------------------------------------------------------------//
		$arrW3 = Array(	'copyright.w3',
						'prototype', 
						'w3'
						);
		$strW3 = "";
		foreach ($arrW3 as $strFile)
		{
			$strW3 .= file_get_contents("{$this->_strBasePath}w3.js/js/{$strFile}.js");
		}
		file_put_contents("{$this->_strBasePath}www/js/w3.js", $strW3);
		
		//--------------------------------------------------------------------//
		// render kinkee
		//--------------------------------------------------------------------//
		$arrKinkee = Array(	'copyright',
						    'kinkee.mod'
						    );
		$strKinkee = "";
		foreach ($arrKinkee as $strFile)
		{
			$strKinkee .= file_get_contents("{$this->_strBasePath}js/{$strFile}.js");
		}
		file_put_contents("{$this->_strBasePath}www/js/kinkee.js", $strKinkee);
	}
	
	public function renderCSS()
	{
		
	}
	
	public function renderArticle()
	{
		
	}
}

?>
