<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ==============================================================
 *
 * Python3
 *
 * ==============================================================
 *
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('application/libraries/LanguageTask.php');

class Python3_Task extends Task {
    public function __construct($sourceFileName, $sourcecodetree, $input, $params) {
        parent::__construct($sourceFileName, $sourcecodetree, $input, $params);
        $this->default_params['memorylimit'] = 400; // Need more for numpy
        $this->default_params['interpreterargs'] = array('-BE');
    }

    public static function getVersionCommand() {
        return array('python3 --version', '/Python ([0-9._]*)/');
    }

    public function compile() {
        $cmd = "python3 -m py_compile {$this->sourceFileName}";
        $this->executableFileName = $this->sourceFileName;
        list($retval, $output, $this->cmpinfo) = $this->run_in_sandbox($cmd);
        if (!empty($this->cmpinfo) && !empty($output)) {
            $this->cmpinfo = $output . '\n' . $this->cmpinfo;
        }
    }


    // A default name for Python3 programs
    public function defaultFileName($sourcecode) {
        return 'prog.py';
    }


    public function getExecutablePath() {
        return '/usr/bin/python3';
     }


     public function getTargetFile() {
         // selectedfile is from webIDE client
         $selectedfile = $this->selectedfile;

         if (empty($selectedfile)) {
             throw new Exception('Please select a file to run.');
         }

         return $selectedfile;
     }
};
