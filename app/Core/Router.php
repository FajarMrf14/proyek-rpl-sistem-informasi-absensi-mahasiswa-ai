<?php

namespace App\Core;

use App\Config\App as AppConfig;
use Exception;

class Router
{
    private array $routes = [];
    private array $middlewareAliases = [];
    private ?\Closure $notFoundHandler = null;

    /**
     * Daftarkan alias middleware
     */
    public function registerMiddleware(string $alias, string $middlewareClass): self
    {
        $this->middlewareAliases[$alias] = $middlewareClass;
        return $this;
    }

    /**
     * Mendaftarkan route GET
     */
    public function get(string $path, array|callable $handler, array $middlewares = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middlewares);
    }

    /**
     * Mendaftarkan route POST
     */
    public function post(string $path, array|callable $handler, array $middlewares = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middlewares);
    }

    /**
     * Mendaftarkan route PUT
     */
    public function put(string $path, array|callable $handler, array $middlewares = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    /**
     * Mendaftarkan route DELETE
     */
    public function delete(string $path, array|callable $handler, array $middlewares = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    /**
     * Helper internal untuk menyimpan route
     */
    private function addRoute(string $method, string $path, array|callable $handler, array $middlewares): self
    {
        $path = '/' . trim($path, '/');
        
        // Ubah route parameter {param} menjadi regex pattern
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = "#^" . $pattern . "$#";

        $this->routes[] = [
            'method'      => $method,
            'path'        => $path,
            'pattern'     => $pattern,
            'handler'     => $handler,
            'middlewares' => $middlewares
        ];

        return $this;
    }

    /**
     * Set kustom handler untuk 404 Not Found
     */
    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    /**
     * Dispatch request dan jalankan handler yang cocok
     */
    public function dispatch(Request $request, Response $response): void
    {
        $requestMethod = $request->method();
        $requestUri = $request->uri();

        // Validasi CSRF Token untuk request modifying (POST, PUT, DELETE)
        $config = AppConfig::config();
        if ($config['csrf_protection'] && in_array($requestMethod, ['POST', 'PUT', 'DELETE'])) {
            $csrfToken = $request->post('_csrf_token') ?? 
                         $request->json('_csrf_token') ?? 
                         $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

            if (!Session::validateCsrfToken($csrfToken)) {
                if ($request->isAjax()) {
                    $response->json(['success' => false, 'message' => 'Invalid or expired CSRF token.'], 403);
                    return;
                }

                Session::setFlash('error', 'Sesi verifikasi formulir telah kedaluwarsa. Silakan coba kembali.');
                $response->redirect($request->uri());
                return;
            }
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $requestMethod && preg_match($route['pattern'], $requestUri, $matches)) {
                // Ekstraksi parameter URL
                $params = array_filter($matches, function ($key) {
                    return !is_numeric($key);
                }, ARRAY_FILTER_USE_KEY);

                // Buat pipeline middleware
                $this->runMiddlewareStack($route['middlewares'], $request, function () use ($route, $request, $response, $params) {
                    $this->executeHandler($route['handler'], $request, $response, $params);
                });

                return;
            }
        }

        // Jika tidak ada route yang cocok (404)
        if ($this->notFoundHandler) {
            call_user_func($this->notFoundHandler, $request, $response);
        } else {
            $response->setStatusCode(404);
            $errorView = dirname(__DIR__) . '/Views/errors/404.php';
            if (file_exists($errorView)) {
                require $errorView;
            } else {
                echo "<h1>404 Not Found</h1><p>Halaman yang Anda cari tidak ditemukan.</p>";
            }
        }
    }

    /**
     * Eksekusi middleware stack secara berantai (onion architecture)
     */
    private function runMiddlewareStack(array $middlewares, Request $request, callable $target): void
    {
        $pipeline = array_reduce(
            array_reverse($middlewares),
            function ($next, $middleware) {
                return function ($req) use ($middleware, $next) {
                    $param = null;
                    if (str_contains($middleware, ':')) {
                        list($middleware, $param) = explode(':', $middleware, 2);
                    }

                    $middlewareClass = $this->middlewareAliases[$middleware] ?? $middleware;

                    if (class_exists($middlewareClass)) {
                        $instance = new $middlewareClass($param);
                        if ($instance instanceof Middleware) {
                            return $instance->handle($req, $next);
                        }
                    }

                    return $next($req);
                };
            },
            $target
        );

        $pipeline($request);
    }

    /**
     * Eksekusi handler controller / closure
     */
    private function executeHandler(array|callable $handler, Request $request, Response $response, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func_array($handler, array_merge([$request, $response], array_values($params)));
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            list($controllerClass, $method) = $handler;

            if (!class_exists($controllerClass)) {
                throw new Exception("Controller {$controllerClass} tidak ditemukan.");
            }

            $controller = new $controllerClass($request, $response);

            if (!method_exists($controller, $method)) {
                throw new Exception("Method {$method} tidak ditemukan pada {$controllerClass}.");
            }

            call_user_func_array([$controller, $method], array_values($params));
        }
    }
}
