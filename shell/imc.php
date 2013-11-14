<?php

require_once dirname(dirname(dirname(__FILE__))).'/shell/abstract.php';

/**
 * Interactive Magento Console compatible with Magento 1.8.0.0
 *
 * @package     Mage_Shell
 * @author      Sonny Gauran <sonny.gauran@outsourced.ph>
 */
class Mage_Interactive_Console extends Mage_Shell_Abstract
{
    /**
     * Run script
     *
     */
    public function run()
    {
        IMC::getInstance()->read();
    }
}

/**
 * @link https://github.com/amacgregor/mdg_imc Magento Interactive Console
 */
class IMC {
    protected static $instance = null;
    protected $historyFile = null;
    protected $histSize    = 20;
    protected $history     = array();

    protected function __construct()
    {

        if (!empty($_SERVER['HOME'])) {
            $this->historyFile = $_SERVER['HOME'].'/.imc_history';
            if (!file_exists($this->historyFile)) {
                file_put_contents($this->historyFile, '');
            }
            readline_read_history($this->historyFile);
            $this->history = explode(file_get_contents($this->historyFile), "\n");
            if (isset($_ENV['HISTSIZE']) && $_ENV['HISTSIZE'] > 0) {
                $this->histSize = $_ENV['HISTSIZE'];
            }
        }
        
        readline_completion_function(array($this, 'completeCallback'));
        register_shutdown_function(array($this, 'fatalErrorShutdown'));  
        # // Catch Ctrl+C, kill and SIGTERM
        pcntl_signal(SIGTERM, array($this, 'sigintShutdown'));  
        pcntl_signal(SIGINT, array($this, 'sigintShutdown')); 
    }

    public function fatalErrorShutdown()
    {
        $this->quit();
    }

    public function sigintShutdown($signal)
    {
        if ($signal === SIGINT || $signal === SIGTERM) {
            $this->quit();
        }
    }

    public function __destruct()
    {
        if (!empty($this->historyFile) && is_writable($this->historyFile)) {
            readline_write_history($this->historyFile);
        }
    }

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new IMC();
        }
        return self::$instance;
    }

    public function read()
    {
        while (true) {
            $line = readline('magento > ');
            if ($line == 'exit') {
                $this->quit();
            }
            if (!empty($line)) {
                $this->addToHistory($line);
                eval($line);
                echo "\n";
            }
        }
    }

    protected function addToHistory($line)
    {
        if ($histsize = count($this->history) > $this->histSize) {
            $this->history = array_slice($this->history, $histsize - $this->histSize);
        }
        readline_add_history($line);
    }

    public function quit($code=0)
    {
        $this->__destruct();//just to be safe, if eval causes fatal error we have to call explicitly
        exit($code);
    }

    protected function completeCallback($line)
    {
        if (!empty($line)) {
            $line = preg_quote($line);
            $funcs = get_defined_functions();
            $constants = get_defined_constants();//use these?
            $avail = array_merge(get_declared_classes(),$funcs['user'], $funcs['internal'], array());
        /*$classNameRegex = '[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*';
            if (substr($line, -4) == '\:\:') {
        $class = substr($line,0, -4);
        if (in_array($class, $avail)) {
                    $methods = get_class_methods($class);
                    foreach ($methods as $key => $method) {
                        $methods[$key] = $class.'::'.$method;
                    }
                    return $methods;
        }
        }*/
            $matches =  preg_grep("/^$line/", $avail);
            if (!empty($matches)) {//will segfault if we return empty array after 3 times...
                return $matches;
            }
        }
    }
}

$shell = new Mage_Interactive_Console();
$shell->run();
