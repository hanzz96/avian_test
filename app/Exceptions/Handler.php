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
            $body['stacktrace'] = $this->stacktraceOf($e);
        }

        return response()->json($body, $status);
    }

    /**
     * @return array<int, string>
     */
    protected function stacktraceOf(Throwable $e): array
    {
        return array_merge(
            [get_class($e).': '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()],
            explode("\n", $e->getTraceAsString())
        );
    }
}
