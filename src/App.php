<?php

declare(strict_types=1);

namespace App;

use App\Support\Env;

/**
 * Front-controller: moi request deu di qua day.
 */
final class App
{
    /** @var array<string,string> */
    private array $routes = [];

    public function __construct()
    {
        $this->registerRoutes();
    }

    public function run(): void
    {
        $method = $this->resolveMethod();
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        $key = strtoupper($method) . ' ' . $path;

        foreach ($this->routes as $routeKey => $controller) {
            [$rMethod, $rPath] = explode(' ', $routeKey, 2);
            if (strtoupper($rMethod) !== strtoupper($method)) {
                continue;
            }

            $params = $this->matchPath($rPath, $path);
            if ($params !== false) {
                $this->dispatch($controller, $params);

                return;
            }
        }

        $this->notFound();
    }

    /**
     * Xac dinh HTTP method thuc su cua request.
     *
     * HTML form chi ho tro GET/POST. De goi cac route PUT/DELETE, template
     * truyen them `?_method=PUT` (hoac `_method` trong body voi POST).
     * Chi chap nhan gia tri tu danh sach cho phep; moi truong khong duoc
     * ghi de REQUEST_METHOD.
     */
    private function resolveMethod(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method !== 'POST') {
            return $method;
        }

        $override = $_POST['_method']
            ?? ($_GET['_method'] ?? null);

        if (!is_string($override)) {
            return $method;
        }

        $override = strtoupper(trim($override));

        return in_array($override, ['PUT', 'PATCH', 'DELETE'], true) ? $override : $method;
    }

    public function get(string $path, string $controller): void
    {
        $this->routes['GET ' . $path] = $controller;
    }

    public function post(string $path, string $controller): void
    {
        $this->routes['POST ' . $path] = $controller;
    }

    public function put(string $path, string $controller): void
    {
        $this->routes['PUT ' . $path] = $controller;
    }

    public function delete(string $path, string $controller): void
    {
        $this->routes['DELETE ' . $path] = $controller;
    }

    /** @param array<string,string> $params */
    private function dispatch(string $controller, array $params): void
    {
        [$class, $method] = explode('@', $controller);

        $rc = new \ReflectionClass($class);
        if (!$rc->isInstantiable()) {
            throw new \RuntimeException("Controller class {$class} is not instantiable.");
        }

        $instance = $rc->newInstanceArgs();

        if (!$rc->hasMethod($method)) {
            throw new \RuntimeException("Method {$class}::{$method} does not exist.");
        }

        $rm = $rc->getMethod($method);
        $rm->invokeArgs($instance, $this->mapArguments($rm, $params));
    }

    /**
     * Chuyen params tu route ({id}) sang danh sach tham so dung cho method.
     *
     * Router khong khai bao ten tham so trung voi signature cua controller,
     * vi du route '/libraries/{id}/pages' tro toi PageController::index($libraryId).
     * Reflection::invokeArgs() se nem loi "Unknown named parameter" neu ten
     * khong khop. O day ta sap xep lai theo THU TU tham so cua method, nen
     * chi can so tham so cua route bang so tham so bat buoc.
     *
     * @param  array<string,string> $params
     * @return list<mixed>
     */
    private function mapArguments(\ReflectionMethod $rm, array $params): array
    {
        $args      = [];
        $route     = array_values($params); // Tham so cua route theo thu tu trong URL
        $usedIndex = [];                    // Chi so route da dung (route khong trung ten)
        $usedName  = [];                    // Ten route da dung

        foreach ($rm->getParameters() as $p) {
            $name = $p->getName();
            $val  = null;

            // Uu tien khop theo TEN (route dat trung ten tham so).
            if (array_key_exists($name, $params) && !isset($usedName[$name])) {
                $val = $params[$name];
                $usedName[$name] = true;
            } else {
                // Route dat ten khac: lan theo thu tu xuat hien chua dung.
                foreach ($route as $i => $v) {
                    if (!isset($usedIndex[$i])) {
                        $val = $v;
                        $usedIndex[$i] = true;
                        break;
                    }
                }
            }

            if ($val === null) {
                if ($p->isDefaultValueAvailable()) {
                    $args[] = $p->getDefaultValue();
                    continue;
                }
                if ($p->allowsNull()) {
                    $args[] = null;
                    continue;
                }

                throw new \RuntimeException(sprintf(
                    'Thiếu tham số "%s" cho %s::%s().',
                    $name,
                    $rm->getDeclaringClass()->getName(),
                    $rm->getName(),
                ));
            }

            $args[] = $this->castArgument($p, $val);
        }

        return $args;
    }

    /** Ép kiểu chuỗi từ URL sang kiểu khai báo trong signature controller. */
    private function castArgument(\ReflectionParameter $p, string $value): mixed
    {
        $type = $p->getType();
        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        return match ($type->getName()) {
            'int'    => (int) $value,
            'float'  => (float) $value,
            'bool'   => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'string' => $value,
            default  => $value,
        };
    }

    /** @return array<string,string>|false */
    private function matchPath(string $pattern, string $path): array|false
    {
        // Giu nguyen "/" (root) thay vi rtrim bien thanh "".
        $pattern = ($pattern === '/') ? '/' : rtrim($pattern, '/');
        $path    = ($path === '/') ? '/' : rtrim($path, '/');

        if ($pattern === $path) {
            return [];
        }

        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        if ($regex === null) {
            return false;
        }

        $fullRegex = '#^' . $regex . '$#';

        if (preg_match($fullRegex, $path, $matches) === 1) {
            $params = [];
            foreach ($matches as $k => $v) {
                if (is_string($k)) {
                    $params[$k] = $v;
                }
            }

            return $params;
        }

        return false;
    }

    private function notFound(): void
    {
        http_response_code(404);

        $ic = static fn (string $p, int $s = 28): string => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" '
            . 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" '
            . 'stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';

        $search = $ic('<circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>');
        $home   = $ic('<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>', 16);
        $logo   = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" '
            . 'stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>';

        // CSS duoc tach ra file de dung chung voi layout — trang loi khong
        // render qua view nen phai tu noi link.
        echo <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>404 — Không tìm thấy trang · WebArchive</title>
<link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="auth">
  <div class="auth-card text-center">
    <div class="logo auth-brand" style="justify-content:center">
      <span class="logo-mark">{$logo}</span>
      <span class="logo-text">
        <span class="logo-name">WebArchive</span>
        <span class="logo-sub">Thư viện Web cá nhân</span>
      </span>
    </div>
    <div style="display:flex;justify-content:center;margin:8px 0 18px;color:var(--text-muted)">{$search}</div>
    <h1 style="font-size:2.625rem;color:var(--brand-600);line-height:1;letter-spacing:-.04em;margin-bottom:10px">404</h1>
    <h2 style="font-size:1.2rem;margin-bottom:8px">Không tìm thấy trang</h2>
    <p class="lead">Địa chỉ bạn yêu cầu không tồn tại hoặc đã được di chuyển.</p>
    <a href="/" class="btn btn-primary btn-block">{$home} Quay về trang chủ</a>
  </div>
</div>
</body>
</html>
HTML;
        exit;
    }

    private function registerRoutes(): void
    {
        // Auth
        $this->get('/', Controller\HomeController::class . '@index');
        $this->get('/login', Controller\AuthController::class . '@showLogin');
        $this->post('/login', Controller\AuthController::class . '@login');
        $this->get('/register', Controller\AuthController::class . '@showRegister');
        $this->post('/register', Controller\AuthController::class . '@register');
        $this->get('/logout', Controller\AuthController::class . '@logout');

        // Libraries
        $this->get('/libraries', Controller\LibraryController::class . '@index');
        $this->post('/libraries', Controller\LibraryController::class . '@store');
        $this->get('/libraries/{id}', Controller\LibraryController::class . '@show');
        $this->put('/libraries/{id}', Controller\LibraryController::class . '@update');
        $this->delete('/libraries/{id}', Controller\LibraryController::class . '@destroy');

        // Library members
        $this->get('/libraries/{id}/members', Controller\LibraryMemberController::class . '@index');
        $this->post('/libraries/{id}/members', Controller\LibraryMemberController::class . '@store');
        $this->put('/libraries/{id}/members/{userId}', Controller\LibraryMemberController::class . '@update');
        $this->delete('/libraries/{id}/members/{userId}', Controller\LibraryMemberController::class . '@destroy');

        // Tags
        $this->get('/libraries/{id}/tags', Controller\TagController::class . '@index');
        $this->post('/libraries/{id}/tags', Controller\TagController::class . '@store');
        $this->put('/libraries/{id}/tags/{tagId}', Controller\TagController::class . '@update');
        $this->delete('/libraries/{id}/tags/{tagId}', Controller\TagController::class . '@destroy');
        $this->post('/pages/{pageId}/tags', Controller\TagController::class . '@attachToPage');
        $this->delete('/pages/{pageId}/tags/{tagId}', Controller\TagController::class . '@detachFromPage');

        // Pages (URL fetch + save)
        $this->get('/libraries/{id}/pages', Controller\PageController::class . '@index');
        $this->post('/libraries/{id}/pages', Controller\PageController::class . '@store');
        $this->get('/pages/{id}', Controller\PageController::class . '@show');
        $this->delete('/pages/{id}', Controller\PageController::class . '@destroy');

        // Reading view (snapshots)
        $this->get('/read/{snapshotId}', Controller\ReadController::class . '@show');

        // Assets (serve with permission check)
        $this->get('/asset/{hash}', Controller\AssetController::class . '@serve');

        // API for fetch status
        $this->get('/api/fetch-logs/{logId}', Controller\ApiController::class . '@fetchStatus');
    }
}