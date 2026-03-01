<?php

namespace App\Providers;

use App\Events\ConnectionChanged;
use App\Events\ExpectedConnectionConfirmed;
use App\Events\FindingResolved;
use App\Events\ImplementationFileApproved;
use App\Listeners\BroadcastNotificationCreated;
use App\Listeners\DetectDiscrepanciesForConnection;
use App\Listeners\DetectDiscrepanciesForExpectedConnection;
use App\Listeners\DetectDiscrepanciesForImplementationFile;
use App\Listeners\ResolveLinkedDiscrepancy;
use App\Listeners\UpdateLastActiveTimestamp;
use App\Models\Audit;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\DistributionList;
use App\Models\Finding;
use App\Models\Rack;
use App\Models\ReportSchedule;
use App\Observers\AuditObserver;
use App\Observers\DeviceObserver;
use App\Observers\FindingObserver;
use App\Observers\RackObserver;
use App\Policies\DatacenterPolicy;
use App\Policies\DeviceTypePolicy;
use App\Policies\DistributionListPolicy;
use App\Policies\ReportSchedulePolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerGates();
        $this->registerEventListeners();
        $this->registerModelObservers();
    }

    /**
     * Register model policies for authorization.
     */
    private function registerPolicies(): void
    {
        Gate::policy(Datacenter::class, DatacenterPolicy::class);
        Gate::policy(DeviceType::class, DeviceTypePolicy::class);
        Gate::policy(DistributionList::class, DistributionListPolicy::class);
        Gate::policy(ReportSchedule::class, ReportSchedulePolicy::class);
    }

    /**
     * Register Laravel Gates for common permission checks.
     * Gates delegate to underlying Spatie permissions.
     */
    private function registerGates(): void
    {
        // Gate for managing users (CRUD operations on users)
        Gate::define('manage-users', function ($user) {
            return $user->hasAnyPermission([
                'users.view',
                'users.create',
                'users.update',
                'users.delete',
            ]);
        });

        // Gate for managing settings
        Gate::define('manage-settings', function ($user) {
            return $user->hasAnyPermission([
                'settings.view',
                'settings.update',
            ]);
        });

        // Gate for approving implementation files
        Gate::define('approve-implementation-files', function ($user) {
            return $user->hasPermissionTo('implementation-files.approve');
        });
    }

    /**
     * Register event listeners for the application.
     */
    private function registerEventListeners(): void
    {
        // Update last_active_at timestamp on successful login
        Event::listen(Login::class, UpdateLastActiveTimestamp::class);

        // Discrepancy detection event listeners (all queued)
        Event::listen(ConnectionChanged::class, DetectDiscrepanciesForConnection::class);
        Event::listen(ImplementationFileApproved::class, DetectDiscrepanciesForImplementationFile::class);
        Event::listen(ExpectedConnectionConfirmed::class, DetectDiscrepanciesForExpectedConnection::class);

        // Finding resolution listener - auto-resolves linked discrepancies
        Event::listen(FindingResolved::class, ResolveLinkedDiscrepancy::class);

        // Real-time notification broadcasting - broadcasts when database notifications are sent
        Event::listen(NotificationSent::class, BroadcastNotificationCreated::class);
    }

    /**
     * Register model observers for real-time broadcasting.
     *
     * Observers dispatch broadcast events when models are created,
     * updated, or deleted to enable real-time UI updates.
     */
    private function registerModelObservers(): void
    {
        Device::observe(DeviceObserver::class);
        Rack::observe(RackObserver::class);
        Audit::observe(AuditObserver::class);
        Finding::observe(FindingObserver::class);
    }
}
