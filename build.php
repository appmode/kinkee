#!/usr/bin/php
<?php
//----------------------------------------------------------------------------//
// build.php
//----------------------------------------------------------------------------//
/*
 * build
 * 
 * A script to build the static files for the site.
 * 
 */

//----------------------------------------------------------------------------//
// Configuration
//----------------------------------------------------------------------------//

// Script options
define("SCRIPT_NAME",              $_SERVER['argv'][0]);
define("SCRIPT_OPTIONS",           "p:");

// help message
define("SCRIPT_HELP", 
"Usage: ".SCRIPT_NAME." -p PAGE_NAME
  -p=name    build the named page.
");

// error messages
define("ERROR_PAGE_NOT_FOUND",     "page not found");

// verbosity (for debugging)
define("SCRIPT_VERBOSITY",         0);

// Define non-configurable constants 
// (constant definitions can be found at the end of the script)
define_constants();

//----------------------------------------------------------------------------//
// Require Files
//----------------------------------------------------------------------------//
require_once('lib/build.php');

//----------------------------------------------------------------------------//
// Exception & Error Handlers
//----------------------------------------------------------------------------//

function exception_handler($objException)
{
    switch (get_class($objException))
    {
        case "OptionHelpException":
            error_log(SCRIPT_HELP);
            die(0);
            break;
        case "OptionMissingException":
            error_log(SCRIPT_NAME.": ".$objException->getMessage()."\n".
                      "Try '".SCRIPT_NAME." --help' for more information.");
            break;
        default:
            error_log(SCRIPT_NAME." encountered an unexpected error : (". 
                      $objException->getCode().") ".
                      $objException->getMessage());
            break;
    }
    die(1);
}

set_exception_handler("exception_handler");

//----------------------------------------------------------------------------//
// Script
//----------------------------------------------------------------------------//

if (!defined("UNIT_TEST") || true !== UNIT_TEST)
{
    main();
}

function main()
{
	$objBuild = new KinkeeBuild();
	
    //--------------------------------------------------------------------//
    // get options
    //--------------------------------------------------------------------//

    $objScript = new getOptions(SCRIPT_OPTIONS);

    // page (optional)
    $strPage  = $objScript->getOption("p");
    
    //--------------------------------------------------------------------//
    // build page(s)
    //--------------------------------------------------------------------//
    if (!$strPage or "*" == $strPage)
    {
		
	}
    else
    {
		
	}

	//--------------------------------------------------------------------//
    // build javascript
    //--------------------------------------------------------------------//
    $objBuild->renderJS();
    
}

//============================================================================//
// Classes
//============================================================================//

//----------------------------------------------------------------------------//
// script options class
//----------------------------------------------------------------------------//
/*
 * A class for working with options passed on the command line
 */
class getOptions
{
    //------------------------------------------------------------------------//
    // __construct
    //------------------------------------------------------------------------//
    /**
     * __construct()
     *
     * Initialize a getOptions object 
     *
     * Automatically checks if the --help option was set on the command line. If
     * the --help option was set an exception of type OptionHelpException will 
     * be thrown. 
     *
     * @param    string     $strOptions       options in the format used by the
     *                                        php getopt() method for short
     *                                        (single character) options.
     *
     * @return   void
     */
    public function __construct($strOptions)
    {
        $this->arrOptions = getopt($strOptions, Array("help"));
        
        // check for a help request
        if (isset($this->arrOptions["help"]))
        {
            throw new OptionHelpException();
        }
    }
    
    //------------------------------------------------------------------------//
    // getOption
    //------------------------------------------------------------------------//
    /**
     * getOption()
     *
     * Get the value of a single option set on the command line.
     *
     * Returns the value set on the command line for the option $strOption. If 
     * an error string $strError is passed to the method, and the option was 
     * not set on the command line, an exception of type OptionMissingException 
     * will be thrown. 
     *
     * @param    string     $strOption        single character used to define
     *                                        the option.
     * @param    string     $strError         optional error message to display
     *                                        if the option was not set when
     *                                        the script was executed.
     *                                        default = null
     *
     * @return   string    The value set on the command line for the option
     *           bool      true   the option was set without a value
     *                     false  the option was not set      
     */
    public function getOption($strOption, $strError=null)
    {
        if (isset($this->arrOptions[$strOption]))
        {
            if ($this->arrOptions[$strOption] === false)
            {
                // an option without a value = false in the options array
                return true;
            }
            return $this->arrOptions[$strOption];
        }
        if (null != $strError)
        {
            throw new OptionMissingException($strError);
        }
        return false;
    } 
}

//----------------------------------------------------------------------------//
// Exception classes
//----------------------------------------------------------------------------//

class OptionMissingException extends Exception {}
class OptionHelpException    extends Exception {}

//============================================================================//
// Constants
//============================================================================//

function define_constants()
{
	
}

?>
