<?php

namespace App\Services;

use App\Models\Message;
use App\Models\User;
use App\Models\Job;
use App\Models\JobApplication;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MessageService
{
    /**
     * Get all messages for a user
     *
     * @param User $user
     * @param array $filters
     * @param string $sortBy
     * @param string $sortOrder
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserMessages(
        User $user,
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortOrder = 'desc',
        int $perPage = 10
    ): LengthAwarePaginator {
        $query = Message::query()->forUser($user->id);

        // Apply filters
        if (isset($filters['is_read'])) {
            $query->where('is_read', $filters['is_read']);
        }

        if (isset($filters['type']) && $filters['type'] === 'received') {
            $query->received($user->id);
        } elseif (isset($filters['type']) && $filters['type'] === 'sent') {
            $query->sent($user->id);
        }

        if (isset($filters['job_id'])) {
            $query->where('job_id', $filters['job_id']);
        }

        if (isset($filters['application_id'])) {
            $query->where('application_id', $filters['application_id']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);

        // Load relationships
        $query->with(['sender', 'receiver', 'job', 'application']);

        // Paginate results
        return $query->paginate($perPage);
    }

    /**
     * Get unread message count for a user
     *
     * @param User $user
     * @return int
     */
    public function getUnreadMessageCount(User $user): int
    {
        return Message::unread()->received($user->id)->count();
    }

    /**
     * Get a specific message
     *
     * @param int $messageId
     * @param User $user
     * @return Message
     * @throws Exception
     */
    public function getMessage(int $messageId, User $user): Message
    {
        $message = Message::with(['sender', 'receiver', 'job', 'application'])
            ->where('id', $messageId)
            ->where(function ($query) use ($user) {
                $query->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            })
            ->firstOrFail();

        // If the user is the receiver and the message is unread, mark it as read
        if ($user->id === $message->receiver_id && !$message->is_read) {
            $message->markAsRead();
        }

        return $message;
    }

    /**
     * Send a message
     *
     * @param array $data
     * @param User $sender
     * @return Message
     * @throws Exception
     */
    public function sendMessage(array $data, User $sender): Message
    {
        try {
            return DB::transaction(function () use ($data, $sender) {
                $message = new Message();
                $message->sender_id = $sender->id;
                $message->receiver_id = $data['receiver_id'];
                $message->subject = $data['subject'];
                $message->message = $data['message'];
                $message->job_id = $data['job_id'] ?? null;
                $message->application_id = $data['application_id'] ?? null;
                $message->save();

                return $message;
            });
        } catch (Exception $e) {
            throw new Exception('Failed to send message: ' . $e->getMessage());
        }
    }

    /**
     * Delete a message
     *
     * @param int $messageId
     * @param User $user
     * @return bool
     * @throws Exception
     */
    public function deleteMessage(int $messageId, User $user): bool
    {
        try {
            $message = Message::where('id', $messageId)
                ->where(function ($query) use ($user) {
                    $query->where('sender_id', $user->id)
                        ->orWhere('receiver_id', $user->id);
                })
                ->firstOrFail();

            return $message->delete();
        } catch (Exception $e) {
            throw new Exception('Failed to delete message: ' . $e->getMessage());
        }
    }

    /**
     * Mark a message as read
     *
     * @param int $messageId
     * @param User $user
     * @return bool
     * @throws Exception
     */
    public function markMessageAsRead(int $messageId, User $user): bool
    {
        try {
            $message = Message::where('id', $messageId)
                ->where('receiver_id', $user->id)
                ->firstOrFail();

            return $message->markAsRead();
        } catch (Exception $e) {
            throw new Exception('Failed to mark message as read: ' . $e->getMessage());
        }
    }

    /**
     * Mark all messages as read for a user
     *
     * @param User $user
     * @return int Number of messages marked as read
     * @throws Exception
     */
    public function markAllMessagesAsRead(User $user): int
    {
        try {
            return Message::unread()
                ->received($user->id)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);
        } catch (Exception $e) {
            throw new Exception('Failed to mark all messages as read: ' . $e->getMessage());
        }
    }

    /**
     * Get recent messages for a user
     *
     * @param User $user
     * @param int $limit
     * @return Collection
     */
    public function getRecentMessages(User $user, int $limit = 5): Collection
    {
        return Message::received($user->id)
            ->with(['sender', 'job'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
