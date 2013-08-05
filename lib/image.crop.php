<?php
//----------------------------------------------------------------------------//
// image.crop.php
//----------------------------------------------------------------------------//
/*
 * Image crop class
 * 
 * crop and resize an image
 * 
 */

//----------------------------------------------------------------------------//
// KinkeeImageCrop Class
//----------------------------------------------------------------------------//
class KinkeeImageCrop
{
	//------------------------------------------------------------------------//
	// render  (STATIC METHOD)
	//------------------------------------------------------------------------//
	/**
	 * render()
	 *
	 * render a crop of an image
	 *
	 * @param  string  Name        name of the image to render, in the form of:
	 *                             rand.imageName.cx.cy.cw.ch.w.h.ext
	 *                             for example:
	 *                             981389rha.sample2.0.0.200.200.200.200.jpg
	 * @param  string  SourcePath  path to source image folder
	 * @param  string  TargetPath  optional path to target image folder.
	 *                             If $strTargetPath is not set the raw image 
	 *                             will be output
	 * @param  bool    ForceOutput optional force the image to be output if 
	 *                             $strTargetPath is set and the image is saved.
	 *                             default = false (do not force output)
	 *
	 * @return  bool               true  if the image was rendered
	 *                             false if the image was not rendered
	 */
	public static function render($strName, $strSourcePath, $strTargetPath=null, $bolForceOutput=false)
	{
		// split image name
		$arrImage      = explode(".", $strName);
		$strExt        = array_pop($arrImage);
		$strType       = strtolower($strExt);
		$intHeight     = (int)array_pop($arrImage);
		$intWidth      = (int)array_pop($arrImage);
		$intCropHeight = (int)array_pop($arrImage);
		$intCropWidth  = (int)array_pop($arrImage);
		$intCropY      = (int)array_pop($arrImage);
		$intCropX      = (int)array_pop($arrImage);
		$strFile       = preg_replace('/[^\w-_]/', "", array_pop($arrImage));
		$strUID        = preg_replace('/[^\w-_]/', "", array_pop($arrImage));

		// set path to source image
		$strSourcePath = rtrim($strSourcePath, '/');
		$strSourceFile = "{$strSourcePath}/{$strUID}.{$strFile}.{$strExt}";

		// get source image
		switch ($strType)
		{
			case 'jpg':
			case 'jpeg':
				$imgSource = imagecreatefromjpeg($strSourceFile);
				$strHeader = "Content-Type: image/jpeg";
				break;
			case 'png':
				$imgSource = imagecreatefrompng($strSourceFile);
				$strHeader = "Content-Type: image/png";
				break;
			case 'gif':
				$imgSource = imagecreatefromgif($strSourceFile);
				$strHeader = "Content-Type: image/gif";
				break;
			default:
				return false;
		}

		// crop image
		$imgCrop = imagecreatetruecolor($intWidth, $intHeight);
		imagecopyresampled( $imgCrop,
							$imgSource,
							0,
							0,
							$intCropX,
							$intCropY,
							$intWidth,
							$intHeight,
							$intCropWidth,
							$intCropHeight);
		
		// save or output image
		$strTargetFile = null;
		if ($strTargetPath)
		{
			$strTargetPath = rtrim($strTargetPath, '/');
			$strTargetFile = "{$strTargetPath}/{$strName}";
		}
		else
		{
			header($strHeader);
		}
		switch ($strType)
		{
			case 'jpg':
			case 'jpeg':
				imagejpeg($imgCrop, $strTargetFile, 75);
				break;
			case 'png':
				imagejpeg($imgCrop, $strTargetFile, 9);
				break;
			case 'gif':
				imagegif($imgCrop,  $strTargetFile);
				break;
		}
		
		// output saved image
		if ($strTargetPath && $bolForceOutput)
		{
			header($strHeader);
			readfile($strTargetFile);
		}
		
		return true;
	}
}
?>
