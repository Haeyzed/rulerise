<?php

namespace App\Providers;

use App\Helpers\TranslateTextHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ResponseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * This method is used to bind services into the container.
     * In this case, it does not register any specific service.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * This method is used to define custom response macros that can be accessed globally
     * throughout the application. Each macro returns a custom JSON response with
     * specific status codes and message formats.
     */
    public function boot(): void
    {
        /**
         * Macro for successful responses.
         *
         * @param mixed $data The data to include in the response body.
         * @param string $message The message to include in the response.
         * @param int $status The HTTP status code for the response.
         *
         * @return JsonResponse
         */
        Response::macro('success', function ($data = null, string $message = 'Operation successful', int $status = HttpResponse::HTTP_OK) {
            return Response::json([
                'success' => true,
                'message' => TranslateTextHelper::translate($message),
                'data' => $data,
            ], $status);
        });

        /**
         * Macro for error responses.
         *
         * @param string $message The error message to include in the response.
         * @param int $status The HTTP status code for the response.
         * @param mixed $errors Any additional error information to include in the response.
         *
         * @return JsonResponse
         */
        Response::macro('error', function (string $message = 'An error occurred', int $status = HttpResponse::HTTP_BAD_REQUEST, $errors = null) {
            return Response::json([
                'success' => false,
                'message' => TranslateTextHelper::translate($message),
                'errors' => $errors,
            ], $status);
        });

        /**
         * Macro for a bad request response.
         *
         * @param string $message The error message to include in the response.
         * @param mixed $errors Any additional error information to include in the response.
         *
         * @return JsonResponse
         */
        Response::macro('badRequest', function (string $message = 'Bad request', $errors = null) {
            return Response::json([
                'success' => false,
                'message' => TranslateTextHelper::translate($message),
                'errors' => $errors,
            ], HttpResponse::HTTP_BAD_REQUEST);
        });

        /**
         * Macro for an unauthorized response.
         *
         * @param string $message The message to include in the response.
         *
         * @return JsonResponse
         */
        Response::macro('unauthorized', function (string $message = 'Unauthorized') {
            return Response::json([
                'success' => false,
                'message' => TranslateTextHelper::translate($message),
            ], HttpResponse::HTTP_UNAUTHORIZED);
        });

        /**
         * Macro for a forbidden response.
         *
         * @param string $message The message to include in the response.
         *
         * @return JsonResponse
         */
        Response::macro('forbidden', function (string $message = 'Forbidden') {
            return Response::json([
                'success' => false,
                'message' => TranslateTextHelper::translate($message),
            ], HttpResponse::HTTP_FORBIDDEN);
        });

        /**
         * Macro for a not found response.
         *
         * @param string $message The message to include in the response.
         *
         * @return JsonResponse
         */
        Response::macro('notFound', function (string $message = 'Resource not found') {
            return Response::json([
                'success' => false,
                'message' => TranslateTextHelper::translate($message),
            ], HttpResponse::HTTP_NOT_FOUND);
        });

        /**
         * Macro for an unprocessable entity response.
         *
         * @param string $message The error message to include in the response.
         * @param mixed $errors Any additional error information to include in the response.
         *
         * @return JsonResponse
         */
        Response::macro('unprocessableEntity', function (string $message = 'Unprocessable entity', $errors = null) {
            return Response::json([
                'success' => false,
                'message' => TranslateTextHelper::translate($message),
                'errors' => $errors,
            ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        });

        /**
         * Macro for a server error response.
         *
         * @param string $message The error message to include in the response.
         *
         * @return JsonResponse
         */
        Response::macro('serverError', function (string $message = 'Server Error') {
            return Response::json([
                'status' => false,
                'message' => $message,
            ], HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        });

        /**
         * Macro for a resource created response.
         *
         * @param mixed $data The data to include in the response body.
         * @param string $message The message to include in the response.
         *
         * @return JsonResponse
         */
        Response::macro('created', function ($data = null, string $message = 'Resource created successfully') {
            return Response::json([
                'success' => true,
                'message' => TranslateTextHelper::translate($message),
                'data' => $data,
            ], HttpResponse::HTTP_CREATED);
        });

        /**
         * Macro for a no content response (204).
         *
         * @return JsonResponse
         */
        Response::macro('noContent', function () {
            return Response::json(null, HttpResponse::HTTP_NO_CONTENT);
        });

        /**
         * Macro for a paginated success response.
         *
         * @param mixed $data The paginated data to include in the response.
         * @param string $message The message to include in the response.
         *
         * @return JsonResponse
         */


        Response::macro('paginatedSuccess', function ($data, string $message = 'Success', int $status = HttpResponse::HTTP_OK) {
            $response = [
                'status' => true,
                'message' => $message,
                'data' => $data->items(),
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                ],
            ];

            if (method_exists($data, 'nextPageUrl') && $data->nextPageUrl()) {
                $response['links']['next'] = $data->nextPageUrl();
            }

            if (method_exists($data, 'previousPageUrl') && $data->previousPageUrl()) {
                $response['links']['prev'] = $data->previousPageUrl();
            }

            return Response::json($response, $status);
        });

        /**
         * Macro for a too many requests response (429).
         *
         * @param string $message The error message to include in the response.
         * @param mixed $errors Any additional error information to include in the response.
         *
         * @return JsonResponse
         */
        Response::macro('tooManyRequests', function (string $message = 'Too many requests', $errors = null) {
            return Response::json([
                'success' => false,
                'message' => TranslateTextHelper::translate($message),
                'errors' => $errors,
            ], HttpResponse::HTTP_TOO_MANY_REQUESTS);
        });

        /**
         * Macro for a method not allowed response (405).
         *
         * @param string $message The error message to include in the response.
         *
         * @return JsonResponse
         */
        Response::macro('methodNotAllowed', function (string $message = 'Method Not Allowed') {
            return Response::json([
                'success' => false,
                'message' => TranslateTextHelper::translate($message),
            ], HttpResponse::HTTP_METHOD_NOT_ALLOWED);
        });

    }
}
