<?php
/**
 * Ensures that systems, asset types and libs are included before they are used.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer_MySource
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   http://matrix.squiz.net/developer/tools/php_cs/licence BSD Licence
 * @version   CVS: $Id: IncludeSystemSniff.php,v 1.17 2008/07/25 04:18:04 squiz Exp $
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

if (class_exists('PHP_CodeSniffer_Standards_AbstractScopeSniff', true) === false) {
    $error = 'Class PHP_CodeSniffer_Standards_AbstractScopeSniff not found';
    throw new PHP_CodeSniffer_Exception($error);
}

/**
 * Ensures that systems, asset types and libs are included before they are used.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer_MySource
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   http://matrix.squiz.net/developer/tools/php_cs/licence BSD Licence
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class MySource_Sniffs_Channels_IncludeSystemSniff extends PHP_CodeSniffer_Standards_AbstractScopeSniff
{

    /**
     * A list of classes that don't need to be included.
     *
     * @var array(string)
     */
    private $_ignore = array(
                        'self',
                        'parent',
                        'channels',
                        'basesystem',
                        'dal',
                        'init',
                        'pdo',
                        'util',
                        'ziparchive',
                       );


    /**
     * Constructs a Squiz_Sniffs_Scope_MethodScopeSniff.
     */
    public function __construct()
    {
        parent::__construct(array(T_FUNCTION), array(T_DOUBLE_COLON, T_EXTENDS), true);

    }//end __construct()


    /**
     * Processes the function tokens within the class.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file where this token was found.
     * @param int                  $stackPtr  The position where the token was found.
     * @param int                  $currScope The current scope opener token.
     *
     * @return void
     */
    protected function processTokenWithinScope(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $currScope)
    {
        $tokens = $phpcsFile->getTokens();

        // Determine the name of the class that the static function
        // is being called on.
        $classNameToken = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true);
        $className      = $tokens[$classNameToken]['content'];
        if (in_array(strtolower($className), $this->_ignore) === true) {
            return;
        }

        $includedClasses = array();

        $fileName = strtolower($phpcsFile->getFilename());
        $matches  = array();
        if (preg_match('|/systems/([^/]+)/([^/]+)?actions.inc$|', $fileName, $matches) !== 0) {
            // This is an actions file, which means we don't
            // have to include the system in which it exists
            // We know the system from the path.
            $includedClasses[] = $matches[1];
        }

        // Go searching for includeSystem and includeAsset calls within this
        // function, or the inclusion of .inc files, which
        // would be library files.
        for ($i = ($currScope + 1); $i < $stackPtr; $i++) {
            if (strtolower($tokens[$i]['content']) === 'includesystem') {
                $systemName        = $phpcsFile->findNext(T_CONSTANT_ENCAPSED_STRING, ($i + 1));
                $systemName        = trim($tokens[$systemName]['content'], " '");
                $includedClasses[] = strtolower($systemName);
            } else if (strtolower($tokens[$i]['content']) === 'includeasset') {
                $typeName          = $phpcsFile->findNext(T_CONSTANT_ENCAPSED_STRING, ($i + 1));
                $typeName          = trim($tokens[$typeName]['content'], " '");
                $includedClasses[] = strtolower($typeName).'assettype';
            } else if (in_array($tokens[$i]['code'], PHP_CodeSniffer_Tokens::$includeTokens) === true) {
                $filePath = $phpcsFile->findNext(T_CONSTANT_ENCAPSED_STRING, ($i + 1));
                $filePath = $tokens[$filePath]['content'];
                $filePath = trim($filePath, " '");
                $filePath = basename($filePath, '.inc');

                $includedClasses[] = strtolower($filePath);
            }
        }//end for

        // Now go searching for includeSystem, includeAsset or require/include
        // calls outside our scope. If we are in a class, look outside the
        // class. If we are not, look outside the function.
        $condPtr = $currScope;
        if ($phpcsFile->hasCondition($stackPtr, T_CLASS) === true) {
            foreach ($tokens[$stackPtr]['conditions'] as $condPtr => $condType) {
                if ($condType === T_CLASS) {
                    break;
                }
            }
        }

        for ($i = 0; $i < $condPtr; $i++) {
            // Skip other scopes.
            if (isset($tokens[$i]['scope_closer']) === true) {
                $i = $tokens[$i]['scope_closer'];
                continue;
            }

            if (strtolower($tokens[$i]['content']) === 'includesystem') {
                $systemName        = $phpcsFile->findNext(T_CONSTANT_ENCAPSED_STRING, ($i + 1));
                $systemName        = trim($tokens[$systemName]['content'], " '");
                $includedClasses[] = strtolower($systemName);
            } else if (strtolower($tokens[$i]['content']) === 'includeasset') {
                $typeName          = $phpcsFile->findNext(T_CONSTANT_ENCAPSED_STRING, ($i + 1));
                $typeName          = trim($tokens[$typeName]['content'], " '");
                $includedClasses[] = strtolower($typeName).'assettype';
            } else if (in_array($tokens[$i]['code'], PHP_CodeSniffer_Tokens::$includeTokens) === true) {
                $filePath = $phpcsFile->findNext(T_CONSTANT_ENCAPSED_STRING, ($i + 1));
                $filePath = $tokens[$filePath]['content'];
                $filePath = trim($filePath, " '");
                $filePath = basename($filePath, '.inc');

                $includedClasses[] = strtolower($filePath);
            }
        }//end for

        if (in_array(strtolower($className), $includedClasses) === false) {
            $error = "Static method called on non-included class or system \"$className\"; include system with Channels::includeSystem() or include class with require_once";
            $phpcsFile->addError($error, $stackPtr);
        }

    }//end processTokenWithinScope()


    /**
     * Processes a token that is found within the scope that this test is
     * listening to.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file where this token was found.
     * @param int                  $stackPtr  The position in the stack where this token
     *                                        was found.
     *
     * @return void
     */
    protected function processTokenOutsideScope(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['code'] === T_EXTENDS) {
            // Find the class name.
            $classNameToken = $phpcsFile->findNext(T_STRING, ($stackPtr + 1));
            $className      = $tokens[$classNameToken]['content'];
        } else {
            // Determine the name of the class that the static function
            // is being called on.
            $classNameToken = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true);
            $className      = $tokens[$classNameToken]['content'];
        }

        // Some systems are always available.
        if (in_array(strtolower($className), $this->_ignore) === true) {
            return;
        }

        $includedClasses = array();

        $fileName = strtolower($phpcsFile->getFilename());
        $matches  = array();
        if (preg_match('|/systems/([^/]+)/([^/]+)?actions.inc$|', $fileName, $matches) !== 0) {
            // This is an actions file, which means we don't
            // have to include the system in which it exists
            // We know the system from the path.
            $includedClasses[] = $matches[1];
        }

        // Go searching for includeSystem, includeAsset or require/include
        // calls outside our scope.
        for ($i = 0; $i < $stackPtr; $i++) {
            // Skip other scopes.
            if (isset($tokens[$i]['scope_closer']) === true) {
                $i = $tokens[$i]['scope_closer'];
                continue;
            }

            if (strtolower($tokens[$i]['content']) === 'includesystem') {
                $systemName        = $phpcsFile->findNext(T_CONSTANT_ENCAPSED_STRING, ($i + 1));
                $systemName        = trim($tokens[$systemName]['content'], " '");
                $includedClasses[] = strtolower($systemName);
            } else if (strtolower($tokens[$i]['content']) === 'includeasset') {
                $typeName          = $phpcsFile->findNext(T_CONSTANT_ENCAPSED_STRING, ($i + 1));
                $typeName          = trim($tokens[$typeName]['content'], " '");
                $includedClasses[] = strtolower($typeName).'assettype';
            } else if (strtolower($tokens[$i]['content']) === 'includewidget') {
                $typeName          = $phpcsFile->findNext(T_CONSTANT_ENCAPSED_STRING, ($i + 1));
                $typeName          = trim($tokens[$typeName]['content'], " '");
                $includedClasses[] = strtolower($typeName).'widgettype';
            } else if (in_array($tokens[$i]['code'], PHP_CodeSniffer_Tokens::$includeTokens) === true) {
                $filePath = $phpcsFile->findNext(T_CONSTANT_ENCAPSED_STRING, ($i + 1));
                $filePath = $tokens[$filePath]['content'];
                $filePath = trim($filePath, " '");
                $filePath = basename($filePath, '.inc');

                $includedClasses[] = strtolower($filePath);
            }
        }//end for

        if (in_array(strtolower($className), $includedClasses) === false) {
            if ($tokens[$stackPtr]['code'] === T_EXTENDS) {
                $error = "Class extends non-included class or system \"$className\"; include system with Channels::includeSystem() or include class with require_once";
            } else {
                $error = "Static method called on non-included class or system \"$className\"; include system with Channels::includeSystem() or include class with require_once";
            }

            $phpcsFile->addError($error, $stackPtr);
        }

    }//end processTokenOutsideScope()


}//end class

?>
