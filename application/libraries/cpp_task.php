<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ==============================================================
 *
 * C++
 *
 * ==============================================================
 *
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('application/libraries/LanguageTask.php');

class Cpp_Task extends Task {

    public function __construct($sourceFileName, $sourcecodetree, $input, $params) {
        parent::__construct($sourceFileName, $sourcecodetree, $input, $params);
        $this->default_params['compileargs'] = array(
            '-Wall',
        );
    }

    public static function getVersionCommand() {
        return array('gcc --version', '/gcc \(.*\) ([0-9.]*)/');
    }

    public function compile() {
        $this->executableFileName = $execFileName = "jobeExecutalbeBinary";
        $compileargs = $this->getParam('compileargs');
        $linkargs = $this->getParam('linkargs');

        $cmd = "g++ " .
            implode(' ', $compileargs) .
            " `find . -name '*.cpp'` " .
            " -I./ " .
            " -o $execFileName " .
            implode(' ', $linkargs);

        list($retval, $output, $stderr) = $this->run_in_sandbox($cmd);
        if ($retval) {
            $this->cmpinfo = $stderr;
        }
    }


    // A default name for C++ programs
    public function defaultFileName($sourcecode) {
        return 'prog.cpp';
    }


    // The executable is the output from the compilation
    public function getExecutablePath() {
        return "./" . $this->executableFileName;
    }


    public function getTargetFile() {
        return '';
    }
};
