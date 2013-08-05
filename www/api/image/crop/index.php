<?php

require_once("../../../../lib/image.crop.php");

$strPath   = $_REQUEST['image'];
$strFile   = basename($strPath);
$intPage   = (int)explode('/', trim(dirname($strPath), '/'))[1];
$strSource = "../../store/{$intPage}/image";
$strTarget = "{$strSource}/crop/";

KinkeeImageCrop::render($strFile, $strSource, $strTarget, true);

?>
