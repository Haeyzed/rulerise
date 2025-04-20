<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'message' => $this->message,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at ? $this->read_at->format('Y-m-d H:i:s') : null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at->diffForHumans(),
            
            'sender' => $this->whenLoaded('sender', function () {
                return [
                    'id' => $this->sender->id,
                    'name' => $this->sender->first_name . ' ' . $this->sender->last_name,
                    'email' => $this->sender->email,
                    'profile_picture_url' => $this->sender->profile_picture_url,
                    'user_type' => $this->sender->user_type,
                ];
            }),
            
            'receiver' => $this->whenLoaded('receiver', function () {
                return [
                    'id' => $this->receiver->id,
                    'name' => $this->receiver->first_name . ' ' . $this->receiver->last_name,
                    'email' => $this->receiver->email,
                    'profile_picture_url' => $this->receiver->profile_picture_url,
                    'user_type' => $this->receiver->user_type,
                ];
            }),
            
            'job' => $this->whenLoaded('job', function () {
                return [
                    'id' => $this->job->id,
                    'title' => $this->job->title,
                    'slug' => $this->job->slug,
                ];
            }),
            
            'application' => $this->whenLoaded('application', function () {
                return [
                    'id' => $this->application->id,
                    'status' => $this->application->status,
                ];
            }),
        ];
    }
}
