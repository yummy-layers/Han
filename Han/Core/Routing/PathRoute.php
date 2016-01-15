<?php
/**
 * IDE: PhpStorm.
 * License: The MIT License (MIT) - Copyright (c) 2016 YummyLayers
 * Date: 13.01.2016
 */

namespace Han\Core\Routing;


use Closure;
use Exception;
use Han\Core\Interfaces\MiddlewareInterface;
use Han\Core\Request;

class PathRoute {

    private $valid = false;
    private $pathArr;
    private $pattern;
    private $patternArr;
    private $isController = false;
    private $controllerArgs = array();
    private $methodNamePosition = false;

    private $middlewareNames;
    private $arguments;

    private $callback;
    private $controllerName;
    private $methodName;

    public function __construct($pattern){

        $this->pattern = $pattern;

        if($pattern == '/' && empty(Request::getPath())){

            $this->valid = true;

        } else {

            $pathValid = false;
            $this->pathArr = Request::getPathArr();
            $this->patternArr = explode('/', $pattern);
            $argumentsArr = array();

            if(count($this->pathArr) == count($this->patternArr)){

                foreach($this->pathArr as $key => $pathPart){

                    if(substr($this->patternArr[ $key ], 0, 1) == '{' && substr($this->patternArr[ $key ], -1) == '}'){

                        if($pathPart){
                            $argumentsArr[] = $pathPart;
                            $pathValid = true;
                        }
                    } else {

                        if($pathPart == $this->patternArr[ $key ]){
                            $pathValid = true;
                        } else {
                            $pathValid = false;
                            break;
                        }
                    }
                }
                // task/edit/3/wer/2 == task
            } elseif(count($this->pathArr) > count($this->patternArr)) {

                foreach($this->patternArr as $key => $patternPart){
                    if($patternPart == $this->pathArr[ $key ]){
                        $pathValid = true;
                    } else {
                        $pathValid = false;
                        break;
                    }
                }
                if($pathValid){
                    $this->methodNamePosition = count($this->patternArr);
                    $this->isController = true;
                }
            }

            $this->valid = $pathValid;

            $this->arguments = $argumentsArr;
        }
    }

    public function setCallback($callback){
        if($callback instanceof Closure){

            $this->callback = $callback;

            return $this;
        } else {
            throw new Exception("Callback parameter is not a Closure");
        }
    }

    public function setMethod($controllerName, $methodName){
        if(is_string($controllerName) && is_string($methodName)){
            $this->controllerName = $controllerName;
            $this->methodName = $methodName;

            return $this;
        } else {
            throw new Exception("Controller name or method name is not a string");
        }
    }

    public function setController($controllerName){
        if(is_string($controllerName)){
            $this->controllerName = $controllerName;

            return $this;
        } else {
            throw new Exception("Controller name is not a string");
        }
    }

    public function setMiddleware($middlewareNames){
        if(!is_array($middlewareNames) && is_string($middlewareNames)) $middlewareNames = array( $middlewareNames );

        $this->middlewareNames = $middlewareNames;

        return $this;
    }

    public function isValid(){
        return $this->valid;
    }

    private function run(){

        if($this->valid){

            $middlewareNext = true;

            if(is_array($this->middlewareNames)) foreach($this->middlewareNames as $middlewareName){
                $middleware = new $middlewareName();
                if($middleware instanceof MiddlewareInterface){
                    $middlewareNext = $middleware->check();
                }
            }

            if($middlewareNext){

                if($this->isController){

                    if($this->controllerName){

                        if(count($this->pathArr) > count($this->patternArr) + 1){
                            $this->controllerArgs = array_slice($this->pathArr, $this->methodNamePosition + 1);
                        } else {
                            $this->controllerArgs = null;
                        }

                        $this->callMethod(
                            $this->controllerName,
                            Request::getPathArr()[ $this->methodNamePosition ] . 'Action',
                            $this->controllerArgs
                        );
                    }

                } else {

                    if($this->callback){
                        $callback = $this->callback;

                        if($this->arguments) $callback(...$this->arguments);
                        else $callback();
                    }
                    if($this->controllerName && $this->methodName){

                        $this->callMethod($this->controllerName, $this->methodName, $this->arguments);
                    }
                }
            }
        }
    }

    private function callMethod($className, $methodName, array $args = null){
        $obj = new $className();
        if(!empty($args)) $obj->$methodName(...$args);
        else $obj->$methodName();
    }

    function __destruct(){
        $this->run();
    }

}