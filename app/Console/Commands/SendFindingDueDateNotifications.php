<?php

namespace App\Console\Commands;

use App\Enums\FindingStatus;
use App\Models\Finding;
use App\Notifications\FindingDueDateApproachingNotification;
use App\Notifications\FindingOverdueNotification;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Command to send due date notifications for findings.
 *
 * This command checks for findings that are approaching their due date
 * or are overdue, and sends notifications to the assigned users if they
 * haven't already received such notifications for those findings today.
 */
class SendFindingDueDateNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'findings:send-due-date-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notifications for findings approaching due date or overdue';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for findings approaching due date...');
        $approachingCount = $this->sendApproachingDueDateNotifications();
        $this->info("Sent {$approachingCount} approaching due date notification(s).");

        $this->info('Checking for overdue findings...');
        $overdueCount = $this->sendOverdueNotifications();
        $this->info("Sent {$overdueCount} overdue notification(s).");

        $total = $approachingCount + $overdueCount;
        $this->info("Total notifications sent: {$total}");

        return Command::SUCCESS;
    }

    /**
     * Send notifications for findings approaching due date (within 3 days).
     */
    private function sendApproachingDueDateNotifications(): int
    {
        $count = 0;

        // Get findings due soon that are not resolved or deferred
        $findings = Finding::query()
            ->dueSoon()
            ->whereNotNull('assigned_to')
            ->whereNotIn('status', [FindingStatus::Resolved, FindingStatus::Deferred])
            ->with(['assignee', 'audit.datacenter'])
            ->get();

        foreach ($findings as $finding) {
            if ($this->hasRecentNotification($finding, 'finding_due_date_approaching')) {
                continue;
            }

            $finding->assignee->notify(new FindingDueDateApproachingNotification($finding));
            $count++;
        }

        return $count;
    }

    /**
     * Send notifications for overdue findings.
     */
    private function sendOverdueNotifications(): int
    {
        $count = 0;

        // Get overdue findings that are not resolved or deferred
        $findings = Finding::query()
            ->overdue()
            ->whereNotNull('assigned_to')
            ->whereNotIn('status', [FindingStatus::Resolved, FindingStatus::Deferred])
            ->with(['assignee', 'audit.datacenter'])
            ->get();

        foreach ($findings as $finding) {
            if ($this->hasRecentNotification($finding, 'finding_overdue')) {
                continue;
            }

            $finding->assignee->notify(new FindingOverdueNotification($finding));
            $count++;
        }

        return $count;
    }

    /**
     * Check if a notification of the given type was already sent today for this finding.
     */
    private function hasRecentNotification(Finding $finding, string $notificationType): bool
    {
        $assignee = $finding->assignee;
        if ($assignee === null) {
            return false;
        }

        // Check if the assignee has received this type of notification for this finding today
        return DatabaseNotification::query()
            ->where('notifiable_type', get_class($assignee))
            ->where('notifiable_id', $assignee->id)
            ->whereDate('created_at', today())
            ->get()
            ->filter(function ($notification) use ($finding, $notificationType) {
                $data = $notification->data;

                return ($data['type'] ?? null) === $notificationType
                    && ($data['finding_id'] ?? null) === $finding->id;
            })
            ->isNotEmpty();
    }
}
