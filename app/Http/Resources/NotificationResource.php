<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isSystem = $this->type === 'system';
        $canRedirect = false;
        $actionUrl = null;

        if (! $isSystem && ! empty($this->action_url) && $this->action_url !== '#' && $user !== null) {
            $canRedirect = app(NotificationService::class)->canUserAccessUrl($user, $this->action_url);
            if ($canRedirect) {
                $actionUrl = $this->action_url;
            }
        }

        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $actionUrl,
            'can_redirect' => $canRedirect,
            'is_reminder' => ! $canRedirect,
            'data' => $this->data,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }
}
