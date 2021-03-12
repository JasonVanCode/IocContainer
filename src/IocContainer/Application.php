<?php
namespace IocContainer;
/*
 * @Author: your name
 * @Date: 2021-03-12 16:09:39
 * @LastEditTime: 2021-03-12 16:23:20
 * @LastEditors: Please set LastEditors
 * @Description: In User Settings Edit
 * @FilePath: \IocContainer\src\IocContainer\IocContainer.php
 */
class Application {

    const DIRECTORY_SEPARATOR = '/';

    protected static $instance;

    protected $basePath;

    protected $bindings = [];

    protected $instances = [];

    protected $aliases = [];

    protected $serviceProviders = [];

    protected $loadedProviders = [];
    
    public function __construct($basePath = null)
    {
        
    }

    public function singleton($abstract, $concrete = null){
        $this->bind($abstract, $concrete, true);
    }

    public function bind($abstract, $concrete = null, $shared = false){

        //如果$concrete不是闭包的话
        if(!$concrete instanceof \Closure){
            $concrete = $this->getClosure($abstract,$concrete);
        }

        $this->bindings[$abstract] = compact('concrete','shared');

    }

    public function getClosure($abstract,$concrete)
    {
        return function($container,$parameters = []) use ($abstract,$concrete){

            if($abstract == $concrete){
                return $container->build($concrete);
            }
            
            return $container->resolve($concrete,$parameters,$raiseEvemts = false);

        };
    }


    public function build($concrete)
    {
        if($concrete instanceof \Closure){
            return $concrete($this,[]);
        }
       
        //反射机制
        $reflector = new \ReflectionClass($concrete);

        if(!$reflector->isInstantiable()){
            return '无法实例';
        }

        $constructor = $reflector->getConstructor();

        if(is_null($constructor)){
            return new $concrete;
        }
        $dependencies = $constructor->getParameters();
        try {
            $instances = $this->resolveDependencies($dependencies);
        } catch (\Throwable $th) {
            //throw $th;
        }
        return $reflector->newInstanceArgs($instances);

    }

    public function resolveDependencies(array $dependencies)
    {
        $results = [];
        foreach($dependencies as $dependency){

            $results[] = is_null($dependency->getClass())?$this->resolvePrimitive($dependency):$this->resolveClass($dependency);
        }
        return $results;
    }

     //__construct 需要传入对象
     public function resolveClass(\ReflectionParameter $parameter)
     {
         try{
             return $this->make($parameter->getClass()->name);
         }catch(\Exception $e){
             var_dump($e->getMessage());
         }
     }

    //__construct 需要传入普通变量
    public function resolvePrimitive(\ReflectionParameter $parameter)
    {

        // if (! is_null($concrete = $this->getContextualConcrete('$'.$parameter->name))) {
        //     return $concrete instanceof Closure ? $concrete($this) : $concrete;
        // }

        //判断对象是否有默认值
        if($parameter->isDefaultValueAvailable()){
            return $parameter->getDefaultValue();
        }

    }

    protected function unresolvablePrimitive(\ReflectionParameter $parameter)
    {
        $message = "Unresolvable dependency resolving [$parameter] in class {$parameter->getDeclaringClass()->getName()}";
        return $message;
    }


    protected function getContextualConcrete($abstract)
    {
        if (! is_null($binding = $this->findInContextualBindings($abstract))) {
            return $binding;
        }

        // Next we need to see if a contextual binding might be bound under an alias of the
        // given abstract type. So, we will need to check if any aliases exist with this
        // type and then spin through them and check for contextual bindings on these.
        if (empty($this->abstractAliases[$abstract])) {
            return;
        }

        foreach ($this->abstractAliases[$abstract] as $alias) {
            if (! is_null($binding = $this->findInContextualBindings($alias))) {
                return $binding;
            }
        }
    }

    /**
     * Find the concrete binding for the given abstract in the contextual binding array.
     *
     * @param  string  $abstract
     * @return \Closure|string|null
     */
    protected function findInContextualBindings($abstract)
    {
        return $this->contextual[end($this->buildStack)][$abstract] ?? null;
    }


   

    public function resolve($abstract,$parameters = [],$raiseEvemts = true)
    {
        
        $concrete = $this->getConcrete($abstract);

        if($concrete === $abstract || $concrete instanceof \Closure){
            $object = $this->build($concrete);
        }else{
            $object = $this->make($concrete);
        }

        return $object;
    }

    public function getConcrete($abstract)
    {   
        if(isset($this->bindings[$abstract])){
            return $this->bindings[$abstract]['concrete'];
        }
        return $abstract;
    }


    public function make($abstract,$parameters = [])
    {
        return $this->resolve($abstract,$parameters);
    }


   
}