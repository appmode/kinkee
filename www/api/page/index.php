<?php 

//----------------------------------------------------------------------------//
// Require Files
//----------------------------------------------------------------------------//
require_once('../../../mustache.php/src/Mustache/Autoloader.php');
Mustache_Autoloader::register();

//----------------------------------------------------------------------------//
// Config
//----------------------------------------------------------------------------//

//----------------------------------------------------------------------------//
// Script
//----------------------------------------------------------------------------//

// get post data
$strData = file_get_contents("php://input"); 

// convert data to object
$objData = json_decode($strData);

$intId      = (int)$objData->id;
$strBaseDir = "../store/";
$strSaveDir = "{$strBaseDir}{$intId}/";
$strTemplateDir = "../../../template/";

// save json
file_put_contents("{$strSaveDir}json", $objData->json);

// save jsonp
file_put_contents("{$strSaveDir}jsonp", "w3.kinkee.loadJSON({$objData->json})");

// init mustache
$objMustache = new Mustache_Engine();

// compile html
$arrValue = Array();
$arrValue['title']    = "this is the page title";
$arrValue['fb-jssdk'] = "this is the fb launch code";
$arrValue['page']     = trim($objData->html);

$strTemplate = file_get_contents("{$strTemplateDir}index.html");
file_put_contents("{$strSaveDir}index.html", $objMustache->render($strTemplate, $arrValue));

// save image list
$strFolder = getcwd(); 
chdir("{$strSaveDir}image/"); 
file_put_contents("json", json_encode(glob("*.*")));
chdir($strFolder);

// recompile main index
//TODO!!!!

// recompile category indexes
//TODO!!!!

?> 
