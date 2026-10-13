<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_are_grouped_by_sidebar_page(): void
    {
        $this->actingAs(User::factory()->create(['role_name' => 'Admin']));

        $response = $this->get(route('permissions.index'));

        $response->assertOk()
            ->assertSee('data-permission-group="Dashboard"', false)
            ->assertSee('data-permission-group="Client List"', false)
            ->assertSee('data-permission-group="Import Events"', false)
            ->assertSee('data-permission-group="Event Records"', false)
            ->assertSee('data-permission-group="Users"', false)
            ->assertSee('data-permission-group="Roles"', false)
            ->assertSee('data-permission-group="Permissions"', false)
            ->assertSee('data-permission-group="Activity Logs"', false)
            ->assertSee('data-permission-feature="View Payroll Category"', false)
            ->assertSee('data-permission-feature="Export Transaction Event Records"', false)
            ->assertSee('data-permission-feature="Print Payroll Transaction PDF"', false)
            ->assertViewHas('permissionGroups', function ($groups): bool {
                $eventRecords = $groups->get('Event Records')->pluck('feature')->all();
                $allFeatures = $groups->flatten(1)->pluck('feature');

                return $groups->get('Client List')->pluck('feature')->all() === [
                    'Client List',
                    'View Client',
                    'Edit Client',
                    'Archive Clients',
                    'Delete Clients Without Transactions',
                    'Send Transactions',
                ] && $groups->get('Import Events')->pluck('feature')->all() === [
                    'Events',
                    'Import CSV',
                    'Preview Import',
                    'Download Template',
                    'Export Events',
                    'Transfer Selected',
                    'Transfer to Client',
                    'Force Create Client',
                    'Transfer Event',
                    'Delete Event',
                    'Duplicate Review',
                    'Mark Not Duplicate',
                    'View Archive Files',
                    'Download Archive',
                ] && $eventRecords === [
                    'Event Records',
                    'Events Records Duplicates',
                    'View Payroll Category',
                    'Export Transaction Event Records',
                    'Print Payroll Transaction PDF',
                    'Edit Transaction Event Record',
                    'Tag Transaction Event Record Status',
                    'Undo Transfer',
                    'View Removed Duplicates',
                    'Reset Duplicate Review',
                    'Merge to Oldest Client',
                    'Not a duplicate',
                ] && $allFeatures->count() === $allFeatures->unique()->count();
            });
    }
}
