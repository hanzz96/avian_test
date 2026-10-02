<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Semua error pada API dirender terpusat di sini:
     * - CustomException        => 400 + message dari exception
     * - Validation / HTTP (404, 405, dst) => status aslinya
     * - Error lainnya          => 500 "Internal Server Error"
     * Di luar production ditambahkan key "stacktrace".
     */
    public function render($request, Throwable $e)
    {
        if ($this->shouldRenderJson($request)) {
            return $this->renderApiError($e);
        }

        return parent::render($request, $e);
    }

    protected function shouldRenderJson(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    protected function renderApiError(Throwable $e): JsonResponse
    {
        $body = [];

        if ($e instanceof CustomException) {
            $status = 400;
            $body['message'] = $e->getMessage();
        } elseif ($e instanceof ValidationException) {
            $status = $e->status;
            $body['message'] = $e->getMessage();
            $body['errors'] = $e->errors();
        } elseif ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $body['message'] = $e->getMessage() ?: (\Symfony\Component\HttpFoundation\Response::$statusTexts[$status] ?? 'Error');
        } else {
            $status = 500;
            $body['message'] = 'Internal Server Error';
        }

        if (! app()->isProduction()) {
            if ($e instanceof ValidationException) {
                // Validasi gagal sebelum controller jalan, jadi trace tidak menunjuk ke kode kita.
                $body['source'] = $this->validationSourceOf($e);
            }
            $body['stacktrace'] = $this->stacktraceOf($e);
        }

        return response()->json($body, $status);
    }

    /**
     * Stacktrace hanya berisi kode aplikasi (tanpa frame di vendor/), path relatif terhadap root project.
     * Frame pertama = titik exception dilempar bila berasal dari kode kita; sisanya urutan pemanggilan.
     *
     * @return array<int, array{file: ?string, line: ?int, function: string}>
     */
    protected function stacktraceOf(Throwable $e): array
    {
        $frames = [[
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'function' => get_class($e),
        ]];

        foreach ($e->getTrace() as $frame) {
            $frames[] = [
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'function' => ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'],
            ];
        }

        $vendor = base_path('vendor').DIRECTORY_SEPARATOR;

        return array_values(array_map(
            fn ($f) => ['file' => $this->relativePath($f['file'])] + $f,
            array_filter($frames, fn ($f) => $f['file'] !== null && ! str_starts_with($f['file'], $vendor))
        ));
    }

    /**
     * Untuk error validasi: tunjukkan endpoint (controller action) dan FormRequest yang menolak input,
     * lengkap dengan lokasi `rules()`-nya.
     *
     * @return array{action: ?string, form_request: ?string, file: ?string, line: ?int}
     */
    protected function validationSourceOf(ValidationException $e): array
    {
        $route = request()->route();
        $source = ['action' => $route?->getActionName(), 'form_request' => null, 'file' => null, 'line' => null];

        if ($route && is_object($controller = $route->getController())) {
            $method = new \ReflectionMethod($controller, $route->getActionMethod());
            foreach ($method->getParameters() as $param) {
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType && is_subclass_of($type->getName(), \Illuminate\Foundation\Http\FormRequest::class)) {
                    $rules = new \ReflectionMethod($type->getName(), 'rules');
                    $source['form_request'] = $type->getName();
                    $source['file'] = $this->relativePath($rules->getFileName());
                    $source['line'] = $rules->getStartLine();
                }
            }
        }

        return $source;
    }

    protected function relativePath(?string $path): ?string
    {
        return $path === null ? null : ltrim(str_replace(base_path(), '', $path), DIRECTORY_SEPARATOR);
    }
}
