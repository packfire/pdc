<?php

/**
 * Packfire Dependency Checker (pdc)
 * By Sam-Mauris Yong
 * 
 * Released open source under New BSD 3-Clause License.
 * Copyright (c) Sam-Mauris Yong <sam@mauris.sg>
 * All rights reserved.
 */

namespace Packfire\PDC\Analyzer;

use Packfire\PDC\Report\ReportType;
use Packfire\PDC\Report\IReport;
use Packfire\PDC\Toolbelt;

/**
 * Analyzes source code for namespace, class declaration and usage
 * 
 * @author Sam-Mauris Yong <sam@mauris.sg>
 * @copyright Sam-Mauris Yong <sam@mauris.sg>
 * @license http://www.opensource.org/licenses/BSD-3-Clause The BSD 3-Clause License
 * @package Packfire\PDC\Analyzer
 * @since 1.0.4
 * @link https://github.com/packfire/pdc/
 */
class Analyzer implements IAnalyzer {

    /**
     * The file object
     * @var \Packfire\PDC\Analyzer\IFile
     * @since 1.0.8
     */
    private $file;

    /**
     * Original PHP source code
     * @var array
     * @since 1.0.4
     */
    private $source;

    /**
     * PHP source code tokens
     * @var array
     * @since 1.0.4
     */
    private $tokens;

    /**
     * The cache of number of tokens
     * @var integer
     * @since 1.0.4
     */
    private $count;

    /**
     * Create a new Analyzer object
     * @param \Packfire\PDC\Analyzer\IFile $file The file to analyze
     * @since 1.0.4
     */
    public function __construct(IFile $file) {
        $this->file = $file;
        $this->source = $file->source();
        $this->tokens = token_get_all($this->source);
        $this->count = count($this->tokens);
    }

    protected static function checkClassExists($namespace){
        if(class_exists($namespace) || interface_exists($namespace)){
            return true;
        }else{
            $autoloads = spl_autoload_functions();
            if($autoloads){
                foreach($autoloads as $autoload){
                    call_user_func($autoload, $namespace);
                    if(class_exists($namespace) || interface_exists($namespace)){
                        return true;
                    }
                }
            }
            return false;
        }
    }

    protected function checkMismatch($name) {
        return preg_match('`(class|interface|trait)\\s' . $name . '\\W`s', $this->source) == 1;
    }

    protected function fetchNamespace() {
        $namespace = '';
        if (preg_match('`namespace\\s(?<namespace>[a-zA-Z\\\\]+);`s', $this->source, $namespace)) {
            $namespace = $namespace['namespace'];
        } else {
            $namespace = '';
        }
        return $namespace;
    }

    protected function checkClasses($namespace, IReport $report){
        $index = $this->useIndexing();
        $classes = $this->findUsages();
        $used = array();
        foreach($classes as $name){
            if(!preg_match('`(parent|self|static|^\$)`', $name)){
                $resolved = $name;
                if(isset($index[$name])){
                    $used[$name] = true;
                    $resolved = $index[$name];
                }elseif(substr($name, 0, 1) != '\\'){
                    $resolved = $namespace . '\\' . $name;
                }
                if(!self::checkClassExists($resolved)){
                    $report->increment(ReportType::NOT_FOUND, $resolved);
                }
            }
        }
        $diff = array_diff(array_keys($index), array_keys($used));
        if(count($diff) > 0){
            foreach($diff as $unused){
                $report->increment(ReportType::UNUSED, $unused);
            }
        }
    }

    /**
     * Perform analysis on the file
     * @param \Packfire\PDC\Report\IReport $report The report to be generated later
     * @since 1.0.4
     */
    public function analyze(IReport $report) {
        $report->processFile($this->file->path());
        $report->increment(ReportType::FILE);
        $className = $this->file->className();

        $namespace = $this->fetchNamespace();
        if ($namespace) {
            if (!$this->checkMismatch($className)) {
                $report->increment(ReportType::MISMATCH, $className);
            }
        } else {
            $report->increment(ReportType::NO_NAMESPACE);
        }

        $this->checkClasses($namespace, $report);
    }

    protected function useIndexing() {
        $index = array();
        $uses = array();
        preg_match_all('`use\\s(?<namespace>[a-zA-Z\\\\]+)(\\sas\\s(?<alias>[a-zA-Z]+)|);`s', $this->source, $uses, PREG_SET_ORDER);
        foreach ($uses as $use) {
            if (isset($use['alias'])) {
                $index[$use['alias']] = $use['namespace'];
            } else {
                if (false !== $pos = strrpos($use['namespace'], '\\')) {
                    $alias = substr($use['namespace'], $pos + 1);
                } else {
                    $alias = $use['namespace'];
                    $index[Toolbelt::classFromNamespace($alias)] = $alias;
                }
                $index[$alias] = $use['namespace'];
            }
        }
        return $index;
    }

    /**
     * Get all class names used in the source code
     * @return array Returns an array of string containing all the class names
     * @since 1.0.4
     */
    protected function findUsages() {
        $classes = array();
        for ($idx = 0; $idx < $this->count; ++$idx) {
            if (is_array($this->tokens[$idx])) {
                $current = $this->tokens[$idx][0];
                if ($current == T_NEW || $current == T_INSTANCEOF) {
                    $idx += 2;
                    $class = $this->fullClass($idx);
                    $classes[] = $class;
                } elseif ($current == T_PAAMAYIM_NEKUDOTAYIM) {
                    $reset = $idx;
                    while ($this->tokens[$idx - 1][0] == T_NS_SEPARATOR
                    || $this->tokens[$idx - 1][0] == T_STRING) {
                        --$idx;
                    }
                    $class = $this->fullClass($idx);
                    $classes[] = $class;
                    $idx = $reset;
                } elseif ($current == T_CATCH) {
                    while (++$idx < $this->count) {
                        if (is_array($this->tokens[$idx])) {
                            $current = $this->tokens[$idx][0];
                            if ($current == T_STRING
                                    || $current == T_NS_SEPARATOR) {
                                $class = $this->fullClass($idx);
                                $classes[] = $class;
                                --$idx;
                                break;
                            }
                        }
                    }
                } elseif ($current == T_EXTENDS || $current == T_IMPLEMENTS) {
                    while (++$idx < $this->count) {
                        if (is_array($this->tokens[$idx])) {
                            $current = $this->tokens[$idx][0];
                            if ($current == T_STRING
                                    || $current == T_NS_SEPARATOR) {
                                $class = $this->fullClass($idx);
                                $classes[] = $class;
                                --$idx;
                            } elseif ($current == T_IMPLEMENTS) {
                                --$idx;
                                break;
                            }
                        } elseif ($this->tokens[$idx] == '{') {
                            break;
                        }
                    }
                }elseif($current == T_FUNCTION){
                    $idx += 3;
                    if($this->tokens[$idx] == '('){
                        while (++$idx < $this->count) {
                            if (is_array($this->tokens[$idx])) {
                                $current = $this->tokens[$idx][0];
                                if($current == T_NS_SEPARATOR
                                        || ($current == T_STRING && $this->tokens[$idx][1] != 'null')){
                                    $classes[] = $this->fullClass($idx);
                                }
                            }elseif($this->tokens[$idx] == ')'){
                                break;
                            }
                        }
                    }
                }
            }
        }
        return $classes;
    }

    /**
     * Get the full namespace / classname
     * @param integer &$start (reference) The start index to read
     *       the Namespace/Classname from
     * @return string Returns the full namespace or classname read
     * @since 1.0.4
     */
    protected function fullClass(&$start) {
        $class = '';
        do {
            if (is_array($this->tokens[$start])) {
                $current = $this->tokens[$start][0];
                if ($current == T_NS_SEPARATOR || $current == T_STRING
                        || $current == T_VARIABLE || $current == T_STATIC) {
                    $class .= $this->tokens[$start][1];
                } else {
                    break;
                }
            } else {
                break;
            }
        } while (++$start < $this->count);
        return $class;
    }

}