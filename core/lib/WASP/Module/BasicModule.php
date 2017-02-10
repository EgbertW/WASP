<?php

namespace WASP\Module;

/**
 * The BasicModule implements the Module interface and will be used
 * as a fallback class for the module manager when the module does not
 * implement its own module class.
 */
class BasicModule implements Module
{
    protected $name;
    protected $path;

    public function __construct($name, $path)
    {
        $this->name = $name;
        $this->path = $path;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function registerTasks()
    {}
}
