<?php

namespace WASP\Module;

/**
 * The Module interface should be implemented by all installed modules that
 * need initialization code. Once the module is located, a class named
 * <ModuleName>\Module will be loaded when it exists, and if it implements
 * this interface, it will be instantiated.
 */
interface Module
{
    /**
     * Store the name and path of the module, and run any
     * required initialization code.
     */
    public function __construct($name, $path);

    /**
     * Called in order to allow the module to register tasks for the scheduler
     * and the CLI task runner.
     */
    public function registerTasks();

    /**
     * @return string the name of the module
     */
    public function getName();

    /**
     * @return string the path of the module
     */
    public function getPath();
}
