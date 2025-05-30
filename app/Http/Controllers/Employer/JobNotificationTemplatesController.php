<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\NotificationTemplateRequest;
use App\Services\EmployerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for managing job notification templates
 */
class JobNotificationTemplatesController extends Controller implements HasMiddleware
{
    /**
     * Employer service instance
     *
     * @var EmployerService
     */
    protected EmployerService $employerService;

    /**
     * Create a new controller instance.
     *
     * @param EmployerService $employerService
     * @return void
     */
    public function __construct(EmployerService $employerService)
    {
        $this->employerService = $employerService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api','role:employer,employer_staff']),
        ];
    }

    /**
     * Get notification templates
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        $templates = $employer->notificationTemplates;

        return response()->json($templates, 'Job templates retrieved successfully.');
    }

    /**
     * Update notification template
     *
     * @param NotificationTemplateRequest $request
     * @return JsonResponse
     */
    public function updateTemplate(NotificationTemplateRequest $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        $data = $request->validated();

        $templateId = $data['id'] ?? null;

        $template = $this->employerService->saveNotificationTemplate(
            $employer,
            $data,
            $templateId
        );

        $message = $templateId ? 'Template updated successfully' : 'Template created successfully';

        return response()->success($template, $message);
    }
}
