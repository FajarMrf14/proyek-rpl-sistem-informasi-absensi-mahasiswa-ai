<?php

namespace App\Core;

use App\Config\App as AppConfig;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\RoleMiddleware;

class App
{
    private Router $router;
    private Request $request;
    private Response $response;

    public function __construct()
    {
        // 1. Muat environment configuration
        Env::load(dirname(__DIR__, 2) . '/.env');

        // 2. Set Timezone & Error Handling
        $config = AppConfig::config();
        date_default_timezone_set($config['timezone']);

        if ($config['debug']) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }

        // 3. Inisialisasi Session & Request/Response
        Session::start();
        $this->request = new Request();
        $this->response = new Response();
        $this->router = new Router();

        // 4. Daftarkan alias middleware
        $this->router->registerMiddleware('auth', AuthMiddleware::class);
        $this->router->registerMiddleware('guest', GuestMiddleware::class);
        $this->router->registerMiddleware('role', RoleMiddleware::class);

        // 5. Muat rute aplikasi
        $this->registerRoutes();
    }

    /**
     * Mendaftarkan rute-rute aplikasi
     */
    private function registerRoutes(): void
    {
        $routesFile = dirname(__DIR__) . '/Config/Routes.php';
        if (file_exists($routesFile)) {
            $router = $this->router;
            require $routesFile;
        }
    }

    /**
     * Jalankan aplikasi
     */
    public function run(): void
    {
        // Pemeriksaan Mode Pemeliharaan (Maintenance Mode)
        $config = AppConfig::config();
        if (!empty($config['maintenance_mode'])) {
            $user = Session::get('user');
            $isAdmin = !empty($user) && ($user['role'] ?? '') === 'admin';
            $uri = $this->request->uri();
            $isBypassUri = in_array($uri, ['/login', '/logout'], true) || str_starts_with($uri, '/admin');

            if (!$isAdmin && !$isBypassUri) {
                $this->response->setStatusCode(503);
                if ($this->request->isAjax()) {
                    $this->response->json([
                        'success' => false,
                        'message' => 'Sistem sedang dalam mode pemeliharaan (maintenance mode).'
                    ], 503);
                    return;
                }

                $view503 = dirname(__DIR__) . '/Views/errors/503.php';
                if (file_exists($view503)) {
                    require $view503;
                } else {
                    echo "<h1>503 Service Unavailable</h1><p>Sistem sedang dalam mode pemeliharaan.</p>";
                }
                return;
            }
        }

        $this->router->dispatch($this->request, $this->response);
    }
}

