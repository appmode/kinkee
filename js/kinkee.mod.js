//----------------------------------------------------------------------------//
/*  
 * (c) Copyright 2013 Flame Herbohn
 * 
 * author    : Flame Herbohn
 * download  : https://github.com/appmode/kinkee
 * license   : see files in license/ for details
 */
//----------------------------------------------------------------------------//

//----------------------------------------------------------------------------//
// kinkee.mod.js
//----------------------------------------------------------------------------//
/*
 * Module for doing all the kinkee stuff
 */

// Define Class
W3_kinkee_module = function()
{
	// module name
	this.name          = 'kinkee';
	
	// element cache
	this._objElements  = false;
	
	this._arrImages    = [];
	
	this._objMdConverter;
	
	this._objPage       = false;
	
	this._strSaveTarget = '/api/page/index.php';
	
	this._strCropTarget = '/api/image/crop/index.php?image=';
	
	this._elmDrag		= false;
	
	this._elmCropImage  = false;
	
	// standard crop width
	this._intCropWidth  = 400;
	
	this._objImgAreaSelect = false;
	
	// imgAreaSelect options
	this._objImgAreaSelectOptions =
	{
		handles     : true,
		show        : false,
		instance    : true
	}
	
	this.getURL = function($strType)
	{
		switch ($strType)
		{
			case 'crop':
				return "/image/" + this._objPage.id + "/crop/";
		}
	}
	
	this._handleReply = function($objRequest, $strType)
	{
		// get elements
		var $objElements = this._getElements();
		
		switch ($strType)
		{
			case 'load':
				var $objPage = JSON.parse($objRequest.responseText);
				if ('id' in $objPage)
				{
					this._objPage = $objPage;
				}
				else
				{
					//TODO!!!! : error
					return;
				}
				
				// set document title
				document.title = $objPage.title;
				
				// update document content
				this.loadContent($objPage.article.content);
				
				// update document push values????
				//TODO!!!!
				
				// set image upload target
				$objElements.imageTarget.value = $objPage.id;
				
				// update editor
				this.editPage();
				
				// save page to history
				window.history.pushState(
							{
								"html":this._getElements().page.innerHTML,
								"pageTitle":$objPage.title},
							"", 
							$objPage.url);
				break;
			case 'loadImages':
				var $arrImages = JSON.parse($objRequest.responseText);
				this.addImages($arrImages);
				break;
		}
	}
	
	this.initEditor = function()
	{
		// init fileUpload plugin
		$(function(){
			$('#kinkeeImageUpload').fileupload(
				{
					dropZone: $('#kinkeeImageEditor'),
					dataType: 'json',
					add: function (e, data)
						{
							// automaically upload files
							data.submit();
						},
					done: function (e, data)
					{
						// add uploaded images
						w3.kinkee.addImages(data.result.files);
					}
				});
		});
		
		// init imgAreaSelect
		//var $elmImage = document.getElementById('kinkeeImgAreaSelect');
		//this._objImgAreaSelect = $($elmImage).imgAreaSelect(this._objImgAreaSelectOptions);
		
		// hide imgAreaSelect when modal hides
		$('#kinkeeImgAreaSelectModal').on('hide.bs.modal', function(){w3.kinkee.onImgAreaSelectHide()});
		
		// init markdown converter
		this._objMdConverter = new Showdown.converter();
	}
	
	this.initImgAreaSelect = function()
	{
		var $elmImage = document.getElementById('kinkeeImgAreaSelect');
		this._objImgAreaSelect = $($elmImage).imgAreaSelect(this._objImgAreaSelectOptions);
	}
	
	this.addImages = function($arrImages)
	{
		var $elmFragment = document.createDocumentFragment();
		var $elmTemp;
		var $objImage;
		var i = 0;
		for ( ; $objImage = $arrImages[i++] ; )
		{
			$objImage = this.makeImageObject($objImage);
			if (!$objImage.error)
			{
				// create image element
				$elmTemp = document.createElement('img');
				$elmFragment.appendChild($elmTemp);
				$elmTemp.src = $objImage.thumbnailUrl;
				$elmTemp.setAttribute('data-draggable', 1);
				$elmTemp.setAttribute('data-name', $objImage.name);
				$elmTemp.setAttribute('data-url', $objImage.url);
			}
		}
		// add images to image editor
		this._getElements().imageEditor.appendChild($elmFragment);
	}
	
	this.makeImageObject = function($objImage)
	{
		var $strURL = '/api/store/' + this._objPage.id + '/image/';
		if (typeof($objImage) != 'object')
		{
			$objImage = {"name":$objImage};
		}
		if (!$objImage.url)
		{
			$objImage.url = $strURL + $objImage.name;
		}
		if (!$objImage.thumbnailUrl)
		{
			$objImage.thumbnailUrl = $strURL + 'thumbnail/' + $objImage.name;
		}
		return $objImage;
	}
	
	this.updatePushData = function()
	{
		// update any data-push elements that require an update
		var $objList = document.querySelectorAll('[data-push][data-update]');
		for (var i=0; i < $objList.length; i++)
		{
			console.log($objList[i].tagName);
			// check how content needs to be updated
			switch ($objList[i].getAttribute('data-update'))
			{
				case 'replace':
					//TODO!!!!
					break;
				case 'expand':
					//TODO!!!!
					break;
				case 'auto':
					//TODO!!!!
					break;
			}
		}
	}
	
	this.updateContent = function()
	{
		// get elements
		var $objElements = this._getElements();
		
		var $strLabel;
		var $elmTarget;
		var $elmTemp = $objElements.contentEditor.firstChild;
		while ($elmTemp)
		{
			$elmTarget = $elmTemp.elmContentTarget;
			this.updateContentValue($elmTarget, $elmTemp.value);
			$elmTemp = $elmTemp.nextSibling;
		}
	}
	
	this.loadContent = function($arrValue)
	{
		// get list of editable content on the page
		var $arrElements = document.querySelectorAll('[data-content]');
		
		var $strLabel
		var $elmTarget;
		var i = 0;
		for ( ; $elmTarget = $arrElements[i++] ; )
		{
			$strLabel = $elmTarget.getAttribute('data-content');
			this.updateContentValue($elmTarget, $arrValue[$strLabel], false);
		}
	}
	
	this.updateContentValue = function($elmTarget, $mixValue, $bolUpdateData)
	{
		var $strLabel = $elmTarget.getAttribute('data-content');
		switch ($elmTarget.getAttribute('data-type'))
		{
			case 'imageGallery':
				break;
			default:
				switch ($elmTarget.tagName)
				{
					case 'H1':
						$elmTarget.innerHTML = $mixValue;
						this._setContentValue($strLabel, $mixValue, $bolUpdateData);
						break;
					case 'IMG':
						$elmTarget.src = $mixValue;
						this._setContentValue($strLabel, $mixValue, $bolUpdateData);
						break;
					case 'DIV':
						$elmTarget.innerHTML = this._objMdConverter.makeHtml($mixValue);
						this._setContentValue($strLabel, $mixValue, $bolUpdateData);
						break;
				}
		}
	}
	
	this._getContentValue = function($strLabel)
	{
		if (this._objPage &&
			'article' in this._objPage &&
			'content' in this._objPage.article &&
			$strLabel in this._objPage.article.content)
		{
			console.log($strLabel, this._objPage.article.content[$strLabel]);
			return this._objPage.article.content[$strLabel];
		}
		return null;
	}
	
	this._setContentValue = function($strLabel, $mixValue, $bolUpdateData)
	{
		if (false === $bolUpdateData)
		{
			return;
		}
		if (!this._objPage)
		{
			this._objPage = {};
		}
		if (!('article' in this._objPage))
		{
			this._objPage.article = {};
		}
		if (!('content' in this._objPage.article))
		{
			this._objPage.article.content = {};
		}
		this._objPage.article.content[$strLabel] = $mixValue;
	}

	this.editPage = function()
	{
		// init editor
		this.initEditor();
		
		// set page to edit mode
		document.body.addClass('kinkeeEdit');
		
		// get elements
		var $objElements = this._getElements();
		
		// clear editor
		$objElements.contentEditor.innerHTML = "";
		$objElements.imageEditor.innerHTML   = "";
		
		// get list of images from server
		this.loadImages();
		
		// get list of editable content on the page
		var $arrElements = document.querySelectorAll('[data-content]');
		
		// add an edit input for each content item
		var $strValue;
		var $arrValue;
		var $strLabel;
		var $elmFragment = document.createDocumentFragment();
		var $elmTarget;
		var $elmTemp;
		var $elmImg;
		var i = 0;
		for ( ; $elmTarget = $arrElements[i++] ; )
		{
			$strLabel = $elmTarget.getAttribute('data-content');
			$mixValue = this._getContentValue($strLabel);
			switch ($elmTarget.getAttribute('data-type'))
			{
				case 'imageGallery':
					$elmTemp = document.createElement('div');
					$elmTemp.className   = "kinkeeEditor_imageGallery";
					$elmTemp.setAttribute('data-dropTarget', 1);
					$elmTemp.setAttribute('data-dragAction', 'remove');
					break;
				default:
					switch ($elmTarget.tagName)
					{
						case 'H1':
							$elmTemp = document.createElement('input');
							$elmTemp.className   = "form-control";
							$elmTemp.placeholder = $strLabel;
							if (null !== $mixValue)
							{
								$elmTemp.value = $mixValue;
							}
							break;
						case 'IMG':
							$strValue = "";
							if ($mixValue)
							{
								$arrValue = $mixValue.split('/');
								$arrValue.splice(-2,1);
								$arrValue = $arrValue.join('/').split('.');
								$arrValue.splice(2,6);
								$strValue = $arrValue.join('.');
							}
							$elmTemp = document.createElement('div');
							$elmTemp.className = "kinkeeEditor_image";
							$elmTemp.setAttribute('data-dropTarget', 2);
							$elmTemp.value = $mixValue;
							$elmImg  = document.createElement('img');
							$elmImg.setAttribute('data-dropTarget', 2);
							$elmImg.setAttribute('data-url', $strValue);
							$elmImg.src = $mixValue;
							$elmTemp.appendChild($elmImg);
							$elmTemp.onclick = function(){w3.kinkee.onCropableImageClick(event)};
							break;
						case 'DIV':
							$elmTemp = document.createElement('textarea');
							$elmTemp.className   = "form-control";
							$elmTemp.placeholder = $strLabel;
							if (null !== $mixValue)
							{
								$elmTemp.value = $mixValue;
							}
							break;
						default:
							$elmTemp = document.createElement('textarea');
					}
			}
			$elmTemp.elmContentTarget = $elmTarget;
			$elmFragment.appendChild($elmTemp);
		}
		// add inputs to content editor
		$objElements.contentEditor.appendChild($elmFragment);
	}
	
	this.cropImage = function($elmImage)
	{
		// get elements
		var $objElements = this._getElements();
		
		// cach image
		this._elmCropImage = $elmImage;
		
		// load image in imgAreaSelect
		$('#kinkeeImgAreaSelectModal').modal('show');
		this._objImgAreaSelectOptions.show = true;
		$objElements.imgAreaSelect.src     = $elmImage.getAttribute('data-url');
	}
	
	this.onCropableImageClick = function($objEvent)
	{
		this.cropImage($objEvent.target);
	}
	
	this.onImgAreaSelectLoad = function($objEvent)
	{
		this._objImgAreaSelectOptions.imageWidth  = $objEvent.target.width;
		this._objImgAreaSelectOptions.imageHeight = $objEvent.target.height;
		window.setTimeout("w3.kinkee.initImgAreaSelect()", 1000);
	}

	this.onImgAreaSelectUseCrop = function()
	{
		// get selection
		var $objCrop = this._objImgAreaSelect.getSelection();
		
		// extract name & ext from source image path
		var $arrPath = this._elmCropImage.getAttribute('data-url').split(/[\.\/]/);
		var $strExt  = $arrPath.pop();
		var $strName = $arrPath.pop();
		var $intUID  = $arrPath.pop();
		
		// fail if any dimensions are zero
		if (0 == $objCrop.width || 
			0 == $objCrop.height ||
			0 == $intWidth ||
			0 == $intHeight)
		{
			return false;
		}
		
		// set resize width & height
		var $intWidth  = $objCrop.width;
		var $intHeight = $objCrop.height;
		//TODO : standard crop width ???? : this._intCropWidth

		// construct crop name
		var $strCrop =  this.getURL('crop') +
						[$intUID,
						 $strName,
						 $objCrop.x1,
						 $objCrop.y1,
						 $objCrop.width,
						 $objCrop.height,
						 $intWidth,
						 $intHeight,
						 $strExt].join('.');

		// cache crop value
		this._elmCropImage.parentNode.value = $strCrop;
		
		// display cropped image
		this._elmCropImage.src = this._strCropTarget + $strCrop;
		
		// uncache crop image
		this._elmCropImage = false;
		
		// hide modal
		$('#kinkeeImgAreaSelectModal').modal("hide");
	}

	this.onImgAreaSelectHide = function()
	{
		// hide imgAreaSelect plugin
		this._objImgAreaSelectOptions.show = false;
		if (this._objImgAreaSelect)
		{
			this._objImgAreaSelect.setOptions(this._objImgAreaSelectOptions);
			this._objImgAreaSelect.cancelSelection();
			this._objImgAreaSelect.update();
		}
		
		// unload the image
		this._getElements().imgAreaSelect.src = "";
	}
	
	this.onWindowResize = function()
	{
		// cancel imgAreaSelect selection
		if (true == this._objImgAreaSelectOptions.show)
		{
			this._objImgAreaSelect.cancelSelection();
			this._objImgAreaSelect.update();
		}
	}
	
	this.handleDrop = function($objEvent)
	{
		// return if we were not dragging anything
		if (false == this._elmDrag)
		{
			return;
		}
		
		// get elements
		var $objElements = this._getElements();
		
		var $strURL;
		var $elmDrag   = this._elmDrag;
		this._elmDrag  = false;
		var $elmTarget = $objEvent.target;
		var $strImage  = $elmDrag.getAttribute('data-name');
		var $strThumb  = $elmDrag.src;
		var $elmTemp;
	
		if ($strThumb)
		{
			if ($objEvent.stopPropagation)
			{
				$objEvent.stopPropagation();
			}
			$objEvent.preventDefault();
			
			// drop on image gallery
			if ('DIV' == $elmTarget.tagName && 
			    1 == $elmTarget.getAttribute('data-dropTarget'))
			{
				// create image element
				$elmTemp = $elmDrag.cloneNode();
				
				// add image to container
				$elmTarget.appendChild($elmTemp);
			}
			// drop on image
			else if (2 == $elmTarget.getAttribute('data-dropTarget'))
			{
				if ('DIV' == $elmTarget.tagName)
				{
					$elmTarget = $elmTarget.firstChild;
				}
				
				$strURL = $elmDrag.getAttribute('data-url');
				
				// update image source
				$elmTarget.src = "";
				//$elmTarget.src = $strURL;
				$elmTarget.setAttribute('data-url', $strURL);
				
				// load image in imgAreaSelect
				this.cropImage($elmTarget);
				
				// do not remove the source image
				return;
			}
			// drop on image in gallery
			else if('IMG' == $elmTarget.tagName &&
			       1 == $elmTarget.parentNode.getAttribute('data-dropTarget'))
			{
				// create image element
				$elmTemp = $elmDrag.cloneNode();
				
				// add image to container
				$elmTarget.parentNode.insertBefore($elmTemp, $elmTarget);
			}
			
			// remove source image
			switch ($elmDrag.parentNode.getAttribute('data-dragAction'))
			{
				case 'remove':
					$elmDrag.parentNode.removeChild($elmDrag);
					break;
			}
		}
		//$objEvent.preventDefault();
	}
	
	this.handleDragOver = function($objEvent)
	{
		$objEvent.preventDefault();
	}
	
	this.handleDragStart = function($objEvent)
	{
		var $elmTarget = $objEvent.target;
		this._elmDrag  = false;
		
		if ('IMG' == $elmTarget.tagName &&
			$elmTarget.getAttribute('data-draggable'))
		{
			this._elmDrag = $elmTarget;
			return;
		}
		$objEvent.preventDefault();
	}
	
	this._getElements = function()
	{
		if (false == this._objElements)
		{
			var e = {};
			e.contentEditor   = document.getElementById('kinkeeContentEditor');
			e.imageEditor     = document.getElementById('kinkeeImageEditor');
			e.imageTarget     = document.getElementById('kinkeeImageTarget');
			e.page            = document.getElementById('kinkeePage');
			e.imgAreaSelect   = document.getElementById('kinkeeImgAreaSelect');
			this._objElements = e;
		}
		return this._objElements;
	}
	
	this.loadJSON = function($objJson)
	{
		this._objPage = $objJson;
	}
	
	this.savePage = function()
	{
		// update content
		this.updateContent();
		
		// buid data object to save
		var $objData = 
		{
			"id"    : this._objPage.id,
			"json"  : JSON.stringify(this._objPage),
			"html"  : this._getElements().page.innerHTML
		}
		console.log(this._objPage);
		// send data to server
		this.w3.requestPost(this.path, this._strSaveTarget, $objData, 'save');
	}
	
	this.loadPage = function($strURL)
	{
		this.w3.requestGet(this.path, $strURL, null, 'load');
	}
	
	this.loadImages = function()
	{
		var $strURL = '/api/store/' + this._objPage.id + '/image/json';
		this.w3.requestGet(this.path, $strURL, null, 'loadImages');
	}
} 

// register module
window[W3_NAMESPACE].registerModule(new W3_kinkee_module());

// Remove Class Definition
delete(W3_kinkee_module);

// hijack back/forward buttons
window.onpopstate = function(e){
    if(e.state)
    {
		//TODO!!!! : how do we change the page data????
        //document.getElementById("content").innerHTML = e.state.html;
        //document.title = e.state.pageTitle;
    }
};

// window resize
$(window).resize(function(){window[W3_NAMESPACE].kinkee.onWindowResize()});
