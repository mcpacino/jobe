<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ==============================================================
 *
 * Java
 *
 * ==============================================================
 *
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('application/libraries/LanguageTask.php');

class Java_Task extends Task {
    public function __construct($filename, $input, $params) {
        $params['memorylimit'] = 0;    // Disregard memory limit - let JVM manage memory
        $this->default_params['numprocs'] = 256;     // Java 8 wants lots of processes
        $this->default_params['interpreterargs'] = array(
             "-Xrs",   //  reduces usage signals by java, because that generates debug
                       //  output when program is terminated on timelimit exceeded.
             "-Xss8m",
             "-Xmx200m"
        );

        if (isset($params['numprocs']) && $params['numprocs'] < 256) {
            $params['numprocs'] = 256;  // Minimum for Java 8 JVM
        }

        parent::__construct($filename, $input, $params);
    }

    public function prepare_execution_environment($sourceCode) {
        parent::prepare_execution_environment($sourceCode);

        // Superclass calls subclasses to get filename if it's
        // not provided, so $this->sourceFileName should now be set correctly.
        $extStart = strpos($this->sourceFileName, '.');  // Start of extension
        $this->mainClassName = substr($this->sourceFileName, 0, $extStart);
    }

    public static function getVersionCommand() {
        return array('java -version', '/version "?([0-9._]*)/');
    }

    public function compile() {
        $compileArgs = $this->getParam('compileargs');

        $cmd = '/usr/bin/javac ' .
            implode(' ', $compileArgs) .
            " `find . -name '*.java'` ";

        list($retval, $output, $this->cmpinfo) = $this->run_in_sandbox($cmd);
        if (empty($this->cmpinfo)) {
            $this->executableFileName = $this->sourceFileName;
        }
    }

    // A default name for Java programs. [Called only if API-call does
    // not provide a filename. As a side effect, also set the mainClassName.
    public function defaultFileName($sourcecode) {
        $main = $this->getMainClass($sourcecode);
        if ($main === FALSE) {
            $this->cmpinfo .= "WARNING: can't determine main class, so source file has been named 'prog.java', which probably won't compile.";
            return 'prog.java'; // This will probably fail
        } else {
            return $main.'.java';
        }
    }

    public function getExecutablePath() {
        return '/usr/bin/java';
    }


    private function getClassFileList() {
        exec('find ./ -name "*.class"', $output);
        return $output;
    }

    /*
     * Check $classFile containing main method or not.
     * If no, return NULL.
     * If yes, return full path of it's source file and class name
     *
     * Example:
     *      $classFile = './src/se751/FibonacciTask.class'
     *      return [
     *          'compiledfrom'  => './src/se751/FactorialTask.java',
     *          'classname'     => 'se751.FibonacciTask'
     *      ]
     */
    private function getMainClass($classFile) {
        // Example value of $classFile: './src/se751/FibonacciTask.class'

        exec("javap -public $classFile", $javap, $retval);
        $javap = implode("\n", $javap);

        /*
         * Example value of $javap:
         *
         * Compiled from "FactorialTask.java"
         * class se751.FibonacciTask extends java.util.concurrent.RecursiveTask<java.lang.Integer> {
         *    public java.lang.Integer compute();
         *    public static void main(java.lang.String[]);
         *    public java.lang.Object compute();
         * }
         */

        if (strstr($javap, 'public static void main(java.lang.String[])') == FALSE) {
            return NULL;
        }

        // Example value of $compiledfrom: 'FactorialTask.java'
        preg_match('/Compiled from "(?<compiledfrom>.*)"/', $javap, $matches);
        $compiledfrom=$matches['compiledfrom'];

        // Example value of $fullcompiledfrom: './src/se751/FactorialTask.java'
        $fullcompiledfrom = dirname($classFile) . DIRECTORY_SEPARATOR . $compiledfrom;

        // Example value of $class: 'se751.FibonacciTask'
        preg_match('/class (?<classname>[^ ]+) /', $javap, $matches);
        $classname=$matches['classname'];

        return array('compiledfrom'=>$fullcompiledfrom, 'classname'=>$classname);
    }

    /*
     * Find out all .class files that contains main method.
     */
    private function getMainClassList() {
        $classFileList = $this->getClassFileList();

        $mainClassList = array();
        foreach ($classFileList as $classFile) {
            // check $classFile contain main method and get info of it.
            $mainClass = $this->getMainClass($classFile);
            if ($mainClass) {
                array_push($mainClassList, $mainClass);
            }
        }

        return $mainClassList;
    }

    private function getSelectedClasw($mainClassList) {
        // selectedfile is from webIDE client
        $selectedfile = $this->selectedfile;

        $msg = "Found multiple main methods in:\n";

        foreach ($mainClassList as $mainClass) {
            if (strcmp($mainClass['compiledfrom'], $selectedfile) == 0) {
                return $mainClass;
            }
            $msg = $msg . '  ' . $mainClass['compiledfrom'] . "\n";
        }

        $msg = $msg . 'Please select one to run.';
        throw new Exception($msg);
    }

    /*
     * Pick up the right class for running.
     */
    private function pickMainClass($mainClassList) {
        $count = count($mainClassList);

        if ($count == 0) {
            throw new Exception("Main method not found, please define the main method as:\n  public static void main(String[] args)");
        } elseif ($count == 1) {
            $mainClass = $mainClassList[0];
        } else {
            $mainClass = $this->getSelectedClasw($mainClassList);
        }

        return $mainClass;
    }

    private function getClassPath($mainClass) {
        /*
         * Example of $mainClass:
         *      [
         *          'compiledfrom'  => './src/se751/FactorialTask.java',
         *          'classname'     => 'se751.FibonacciTask'
         *      ]
         *
         * Return './src'
         */

        $levels = count(explode('.', $mainClass['classname']));
        return dirname($mainClass['compiledfrom'], $levels);
    }

    public function getTargetFile() {
        $mainClassList = $this->getMainClassList();
        $mainClass = $this->pickMainClass($mainClassList);

        $classpath = $this->getClassPath($mainClass);
        $classname = $mainClass['classname'];

        return "--class-path $classpath $classname";
    }

    // Get rid of the tab characters at the start of indented lines in
    // traceback output.
    public function filteredStderr() {
        return str_replace("\n\t", "\n        ", $this->stderr);
    }
};

