<?php

class ApiRouter {
    private $routes = [];

    public function add($method, $path, $controllerAction, $middleware = []) {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'controllerAction' => $controllerAction,
            'middleware' => $middleware
        ];
    }

    public function get($path, $controllerAction, $middleware = []) {
        $this->add('GET', $path, $controllerAction, $middleware);
    }

    public function post($path, $controllerAction, $middleware = []) {
        $this->add('POST', $path, $controllerAction, $middleware);
    }

    public function put($path, $controllerAction, $middleware = []) {
        $this->add('PUT', $path, $controllerAction, $middleware);
    }

    public function delete($path, $controllerAction, $middleware = []) {
        $this->add('DELETE', $path, $controllerAction, $middleware);
    }

    public function dispatch($requestedPath, $requestedMethod) {
        $requestedMethod = strtoupper($requestedMethod);
        
        // Remove query string from path
        if (strpos($requestedPath, '?') !== false) {
            $requestedPath = explode('?', $requestedPath)[0];
        }
        
        $requestedPath = trim($requestedPath, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestedMethod) {
                continue;
            }

            $routePath = trim($route['path'], '/');
            
            // Convert {param} to named regex groups
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $routePath);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $requestedPath, $matches)) {
                // Extract named parameters
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                // Execute middleware first
                foreach ($route['middleware'] as $mw) {
                    if (is_callable($mw)) {
                        $mw();
                    } else if (is_string($mw)) {
                        list($mwClass, $mwMethod) = strpos($mw, '@') !== false ? explode('@', $mw) : [$mw, 'handle'];
                        if (class_exists($mwClass)) {
                            $instance = new $mwClass();
                            if (method_exists($instance, $mwMethod)) {
                                $instance->$mwMethod();
                            }
                        }
                    }
                }

                // Dispatch to controller
                list($controllerClass, $action) = explode('@', $route['controllerAction']);
                
                // Include controller file automatically
                $controllerFile = dirname(__FILE__) . '/controllers/' . $controllerClass . '.php';
                if (file_exists($controllerFile)) {
                    require_once $controllerFile;
                } else {
                    ApiResponse::internalServerError("Controller file $controllerClass.php not found");
                }

                if (class_exists($controllerClass)) {
                    $controllerInstance = new $controllerClass();
                    if (method_exists($controllerInstance, $action)) {
                        // Pass parameters as arguments to the action
                        call_user_func_array([$controllerInstance, $action], [$params]);
                        return;
                    } else {
                        ApiResponse::internalServerError("Action $action not found in controller $controllerClass");
                    }
                } else {
                    ApiResponse::internalServerError("Controller class $controllerClass not found");
                }
            }
        }

        ApiResponse::notFound("Route not found");
    }
}
