<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Controller for managing user notifications.
 *
 * Provides endpoints for fetching, marking as read, and managing
 * in-app notifications for the authenticated user.
 */
class NotificationController extends Controller
{
    /**
     * Get the authenticated user's notifications.
     *
     * Returns paginated notifications ordered by most recent first.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json([
            'data' => $notifications->map(fn ($notification) => $this->formatNotification($notification)),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Get unread notification count for the authenticated user.
     *
     * Useful for polling the notification badge count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $notificationId): JsonResponse
    {
        $user = $request->user();

        $notification = $user->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => $this->formatNotification($notification),
        ]);
    }

    /**
     * Mark all notifications as read for the authenticated user.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'All notifications marked as read.',
            'unread_count' => 0,
        ]);
    }

    /**
     * Format a notification for the API response.
     *
     * @return array<string, mixed>
     */
    private function formatNotification(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'type' => $this->getNotificationType($notification->type),
            'message' => $data['message'] ?? $this->buildMessage($notification),
            'link' => $data['link'] ?? $this->buildLink($notification),
            'file_name' => $data['file_name'] ?? null,
            'datacenter_name' => $data['datacenter_name'] ?? null,
            'read' => $notification->read_at !== null,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
            'relative_time' => $this->formatRelativeTime($notification->created_at),
        ];
    }

    /**
     * Build a link URL based on notification type and data.
     */
    private function buildLink(DatabaseNotification $notification): ?string
    {
        $data = $notification->data;
        $type = $this->getNotificationType($notification->type);

        // For implementation file notifications, link to the datacenter page
        if (in_array($type, ['file_awaiting_approval', 'file_approved']) && isset($data['datacenter_id'])) {
            return route('datacenters.show', ['datacenter' => $data['datacenter_id']]);
        }

        // For finding notifications, link to the finding page
        if (in_array($type, [
            'finding_assigned',
            'finding_reassigned',
            'finding_status_changed',
            'finding_due_date_approaching',
            'finding_overdue',
        ]) && isset($data['finding_id'])) {
            return route('findings.show', ['finding' => $data['finding_id']]);
        }

        // For audit notifications, link to the audit page
        if (in_array($type, ['audit_assigned', 'audit_reassigned']) && isset($data['audit_id'])) {
            return route('audits.show', ['audit' => $data['audit_id']]);
        }

        return null;
    }

    /**
     * Get a human-readable notification type from the class name.
     */
    private function getNotificationType(string $type): string
    {
        $shortType = class_basename($type);

        return match ($shortType) {
            'ImplementationFileAwaitingApprovalNotification' => 'file_awaiting_approval',
            'ImplementationFileApprovedNotification' => 'file_approved',
            'FindingAssignedNotification' => 'finding_assigned',
            'FindingReassignedNotification' => 'finding_reassigned',
            'FindingStatusChangedNotification' => 'finding_status_changed',
            'FindingDueDateApproachingNotification' => 'finding_due_date_approaching',
            'FindingOverdueNotification' => 'finding_overdue',
            'AuditAssignedNotification' => 'audit_assigned',
            'AuditReassignedNotification' => 'audit_reassigned',
            default => 'general',
        };
    }

    /**
     * Build a message from notification data if not explicitly provided.
     */
    private function buildMessage(DatabaseNotification $notification): string
    {
        $data = $notification->data;
        $type = $this->getNotificationType($notification->type);

        return match ($type) {
            'file_awaiting_approval' => sprintf(
                'File "%s" in %s is awaiting your approval',
                $data['file_name'] ?? 'Unknown',
                $data['datacenter_name'] ?? 'a datacenter'
            ),
            'file_approved' => sprintf(
                'Your file "%s" in %s has been approved',
                $data['file_name'] ?? 'Unknown',
                $data['datacenter_name'] ?? 'a datacenter'
            ),
            'finding_assigned' => sprintf(
                'You have been assigned to finding: %s',
                $data['title'] ?? 'Unknown'
            ),
            'finding_reassigned' => sprintf(
                'Finding "%s" has been reassigned to %s',
                $data['title'] ?? 'Unknown',
                $data['new_assignee_name'] ?? 'another user'
            ),
            'finding_status_changed' => sprintf(
                'Finding "%s" status changed from %s to %s',
                $data['title'] ?? 'Unknown',
                $data['old_status_label'] ?? 'Unknown',
                $data['new_status_label'] ?? 'Unknown'
            ),
            'finding_due_date_approaching' => sprintf(
                'Finding "%s" due date is approaching (%s)',
                $data['title'] ?? 'Unknown',
                $data['due_date'] ?? 'Unknown'
            ),
            'finding_overdue' => sprintf(
                'Finding "%s" is overdue by %d day(s)',
                $data['title'] ?? 'Unknown',
                $data['days_overdue'] ?? 0
            ),
            'audit_assigned' => sprintf(
                'You have been assigned to audit: %s',
                $data['audit_name'] ?? 'Unknown'
            ),
            'audit_reassigned' => sprintf(
                'You have been removed from audit: %s',
                $data['audit_name'] ?? 'Unknown'
            ),
            default => $data['message'] ?? 'You have a new notification',
        };
    }

    /**
     * Format a timestamp to a relative time string.
     */
    private function formatRelativeTime(\DateTimeInterface|string|null $datetime): string
    {
        if (! $datetime) {
            return 'just now';
        }

        if (is_string($datetime)) {
            $datetime = new \DateTime($datetime);
        }

        $now = new \DateTime;
        $diff = $now->getTimestamp() - $datetime->getTimestamp();

        if ($diff < 60) {
            return 'just now';
        }

        if ($diff < 3600) {
            $minutes = floor($diff / 60);

            return $minutes.' minute'.($minutes !== 1 ? 's' : '').' ago';
        }

        if ($diff < 86400) {
            $hours = floor($diff / 3600);

            return $hours.' hour'.($hours !== 1 ? 's' : '').' ago';
        }

        if ($diff < 604800) {
            $days = floor($diff / 86400);

            return $days.' day'.($days !== 1 ? 's' : '').' ago';
        }

        if ($diff < 2592000) {
            $weeks = floor($diff / 604800);

            return $weeks.' week'.($weeks !== 1 ? 's' : '').' ago';
        }

        if ($diff < 31536000) {
            $months = floor($diff / 2592000);

            return $months.' month'.($months !== 1 ? 's' : '').' ago';
        }

        $years = floor($diff / 31536000);

        return $years.' year'.($years !== 1 ? 's' : '').' ago';
    }
}
