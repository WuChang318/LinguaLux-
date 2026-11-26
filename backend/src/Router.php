<?php
declare(strict_types=1);

namespace App;

class Router
{
    private $routes = [];

    public function get($pattern, $handler): void { $this->add('GET', $pattern, $handler); }
    public function post($pattern, $handler): void { $this->add('POST', $pattern, $handler); }
    public function put($pattern, $handler): void { $this->add('PUT', $pattern, $handler); }
    public function delete($pattern, $handler): void { $this->add('DELETE', $pattern, $handler); }

    public function add($method, $pattern, $handler): void
    {
        $this->routes[] = [$method, $pattern, $handler];
    }

    public function dispatch(): void
    {
        $method = Request::method();
        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
        $path = Request::apiPath();
        foreach ($this->routes as $route) {
            list($m, $pattern, $handler) = $route;
            if ($m !== $method) {
                continue;
            }
            $params = $this->match($pattern, $path);
            if ($params !== null) {
                call_user_func($handler, $params);
                return;
            }
        }
        Response::error('接口不存在', 404, 404);
    }

    private function match($pattern, $path)
    {
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        if (preg_match($regex, $path, $m)) {
            $params = [];
            foreach ($m as $k => $v) {
                if (is_string($k)) {
                    $params[$k] = $v;
                }
            }
            return $params;
        }
        return null;
    }
}
