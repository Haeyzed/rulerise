<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\DeleteMessageRequest;
use App\Http\Requests\Message\GetMessageRequest;
use App\Http\Requests\Message\SendMessageRequest;
use App\Http\Resources\MessageResource;
use App\Services\MessageService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for employer messages
 */
class MessagesController extends Controller implements HasMiddleware
{
    /**
     * Message service instance
     *
     * @var MessageService
     */
    protected MessageService $messageService;

    /**
     * Create a new controller instance.
     *
     * @param MessageService $messageService
     * @return void
     */
    public function __construct(MessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api', 'role:employer,employer_staff']),
        ];
    }

    /**
     * Get all messages for the authenticated user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        // Prepare filters
        $filters = [];
        if ($request->has('is_read')) {
            $filters['is_read'] = filter_var($request->input('is_read'), FILTER_VALIDATE_BOOLEAN);
        }
        
        if ($request->has('type')) {
            $filters['type'] = $request->input('type');
        }
        
        if ($request->has('job_id')) {
            $filters['job_id'] = $request->input('job_id');
        }
        
        if ($request->has('application_id')) {
            $filters['application_id'] = $request->input('application_id');
        }
        
        if ($request->has('search')) {
            $filters['search'] = $request->input('search');
        }

        // Get sort parameters
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $perPage = $request->input('per_page', config('app.pagination.per_page', 10));

        $messages = $this->messageService->getUserMessages(
            $user,
            $filters,
            $sortBy,
            $sortOrder,
            $perPage
        );

        return response()->json([
            'success' => true,
            'message' => 'Messages retrieved successfully',
            'data' => MessageResource::collection($messages),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'from' => $messages->firstItem(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'to' => $messages->lastItem(),
                'total' => $messages->total(),
            ]
        ]);
    }

    /**
     * Get a specific message
     *
     * @param int $id
     * @param GetMessageRequest $request
     * @return JsonResponse
     */
    public function show(int $id, GetMessageRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $message = $this->messageService->getMessage($id, $user);
            
            return response()->success(
                new MessageResource($message),
                'Message retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->error($e->getMessage());
        }
    }

    /**
     * Send a new message
     *
     * @param SendMessageRequest $request
     * @return JsonResponse
     */
    public function store(SendMessageRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $data = $request->validated();
            
            $message = $this->messageService->sendMessage($data, $user);
            
            return response()->created(
                new MessageResource($message->load(['sender', 'receiver', 'job', 'application'])),
                'Message sent successfully'
            );
        } catch (Exception $e) {
            return response()->error('Failed to send message: ' . $e->getMessage());
        }
    }

    /**
     * Delete a message
     *
     * @param int $id
     * @param DeleteMessageRequest $request
     * @return JsonResponse
     */
    public function destroy(int $id, DeleteMessageRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $this->messageService->deleteMessage($id, $user);
            
            return response()->success(null, 'Message deleted successfully');
        } catch (Exception $e) {
            return response()->error('Failed to delete message: ' . $e->getMessage());
        }
    }

    /**
     * Mark a message as read
     *
     * @param int $id
     * @return JsonResponse
     */
    public function markAsRead(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $this->messageService->markMessageAsRead($id, $user);
            
            return response()->success(null, 'Message marked as read');
        } catch (Exception $e) {
            return response()->error('Failed to mark message as read: ' . $e->getMessage());
        }
    }

    /**
     * Mark all messages as read
     *
     * @return JsonResponse
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $user = auth()->user();
            $count = $this->messageService->markAllMessagesAsRead($user);
            
            return response()->success(['count' => $count], 'All messages marked as read');
        } catch (Exception $e) {
            return response()->error('Failed to mark all messages as read: ' . $e->getMessage());
        }
    }

    /**
     * Get unread message count
     *
     * @return JsonResponse
     */
    public function getUnreadCount(): JsonResponse
    {
        $user = auth()->user();
        $count = $this->messageService->getUnreadMessageCount($user);
        
        return response()->success(['count' => $count], 'Unread message count retrieved successfully');
    }
}
