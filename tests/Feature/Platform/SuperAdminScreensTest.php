<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap 5.11b: super-admin screens, and the access defects found in the legacy ones. */
class SuperAdminScreensTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness('Alpha');
        Role::findOrCreate('super-admin');
    }

    private function superAdmin(): User
    {
        $u = User::create(['name' => 'Root', 'email' => 'root@example.test', 'password' => bcrypt('secret-password')]);
        $u->assignRole('super-admin');

        return $u;
    }

    public function test_sa1_an_owner_cannot_edit_delete_or_promote_other_users(): void
    {
        $stranger = User::create(['name' => 'Stranger', 'email' => 'stranger@example.test', 'password' => bcrypt('x')]);

        // Escalation attempt: give yourself the global super-admin role through the legacy users screen.
        $this->put('/admin/users/'.$this->owner->id, ['name' => 'Owner', 'email' => $this->owner->email, 'role' => 'super-admin']);
        $this->assertFalse($this->owner->fresh()->hasRole('super-admin'));

        // Take over another business's account by setting its password.
        $this->put('/admin/users/'.$stranger->id, ['name' => 'X', 'email' => 'mine@example.test', 'role' => 'sales-person', 'password' => 'p', 'password_confirmation' => 'p']);
        $this->assertSame('stranger@example.test', $stranger->fresh()->email);

        $this->delete('/admin/users/'.$stranger->id, ['id' => $stranger->id]);
        $this->assertNotSoftDeleted($stranger);
    }

    public function test_sa2_backups_are_super_admin_only_and_limited_to_backup_zips(): void
    {
        Storage::fake('local');
        $name = config('backup.backup.name');
        Storage::disk('local')->put("$name/2026-11-06-10-00-00.zip", 'zip');
        Storage::disk('local')->put('secret.txt', 'secret');

        // An owner sees the whole platform's data in a backup: refused.
        $this->get('/admin/backup')->assertForbidden();
        $this->get('/admin/backup/download?disk=local&file_name='.urlencode("$name/2026-11-06-10-00-00.zip"))->assertForbidden();
        $this->delete('/admin/backup/delete/secret.txt?disk=local')->assertForbidden();
        Storage::disk('local')->assertExists('secret.txt');

        // Even a super admin can only reach backup zips on the backup disk.
        $this->actingAs($this->superAdmin());
        $this->get('/admin/backup/download?disk=local&file_name=secret.txt')->assertNotFound();
        $this->delete('/admin/backup/delete/secret.txt?disk=local')->assertNotFound();
        $this->get('/admin/backup/download?disk=uploads&file_name='.urlencode("$name/2026-11-06-10-00-00.zip"))->assertNotFound();
        Storage::disk('local')->assertExists('secret.txt');
    }

    public function test_sa3_app_wide_settings_are_super_admin_only(): void
    {
        $before = setting('app_name');

        // The settings package's own POST /settings only checked "signed in".
        $this->post('/settings', ['app_name' => 'Hijacked', 'app_currency' => '$']);
        $this->post('/admin/settings', ['app_name' => 'Hijacked', 'app_currency' => '$']);
        $this->post('/admin/superadmin/settings', ['app_name' => 'Hijacked', 'app_currency' => '$']);
        $this->assertNotSame('Hijacked', setting('app_name'));
        $this->assertSame($before, setting('app_name'));

        // The super admin changes them on the new screen.
        Storage::fake('uploads');
        $this->actingAs($this->superAdmin());
        $this->get(route('superadmin.settings'))->assertOk()->assertInertia(fn ($page) => $page->component('SuperAdmin/AppSettings'));
        $this->post(route('superadmin.settings.update'), ['app_name' => 'PharmaSuite', 'app_currency' => 'Rs', 'logo' => \Illuminate\Http\UploadedFile::fake()->image('l.png')])
            ->assertSessionHasNoErrors();
        $this->assertSame('PharmaSuite', setting('app_name'));
        Storage::disk('uploads')->assertExists(setting('logo'));
    }

    public function test_sa4_impersonation_needs_a_post_from_a_super_admin(): void
    {
        $beta = Business::create(['name' => 'Beta']);

        $this->post(route('superadmin.businesses.impersonate', $beta->id))->assertForbidden();

        $this->actingAs($this->superAdmin());
        // A plain link (GET) can no longer switch a super admin into a business.
        $this->get('/admin/superadmin/businesses/'.$beta->id.'/impersonate');
        $this->assertNull(session('impersonate_business_id'));

        $this->post(route('superadmin.businesses.impersonate', $beta->id))->assertRedirect(route('dashboard'));
        $this->assertSame($beta->id, session('impersonate_business_id'));
        $this->post(route('superadmin.businesses.stop'))->assertRedirect(route('superadmin.businesses'));
        $this->assertNull(session('impersonate_business_id'));
    }

    public function test_super_admin_lists_businesses_users_and_roles(): void
    {
        $beta = Business::create(['name' => 'Beta Pharmacy']);
        $this->actingAs($this->superAdmin());

        $this->get(route('superadmin.businesses', ['q' => 'beta']))->assertOk()->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Businesses')->where('businesses.total', 1)->where('businesses.data.0.name', 'Beta Pharmacy')
            ->where('stats.count', 2)->where('stats.idle', 2));
        $this->get(route('superadmin.businesses', ['q' => $this->owner->email]))->assertInertia(fn ($page) => $page
            ->where('businesses.data.0.name', 'Alpha')->where('businesses.data.0.owner.email', $this->owner->email));

        $this->get(route('superadmin.users', ['kind' => 'owners']))->assertOk()->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Users')->where('users.total', 1)->where('users.data.0.email', $this->owner->email)
            ->where('users.data.0.memberships.0.role', 'owner'));
        $this->get(route('superadmin.roles'))->assertOk()->assertInertia(fn ($page) => $page->component('SuperAdmin/Roles'));
        $this->get('/admin/permissions')->assertRedirect(route('superadmin.roles'));
    }

    public function test_owners_get_team_instead_of_the_legacy_users_screen_and_no_platform_menu(): void
    {
        $this->get('/admin/users')->assertRedirect(route('settings.team'));
        $this->get('/admin/users/create')->assertRedirect(route('settings.team'));
        $this->get(route('superadmin.users'))->assertForbidden();
        $this->get(route('superadmin.businesses'))->assertForbidden();
        $this->get(route('taxes.index'))->assertInertia(fn ($page) => $page->where('navigation', fn ($groups) => ! collect($groups)->contains('group', 'Platform')));
    }

    public function test_backups_list_download_and_delete_for_the_super_admin(): void
    {
        Storage::fake('local');
        $name = config('backup.backup.name');
        Storage::disk('local')->put("$name/2026-11-06-10-00-00.zip", 'zip');
        $this->actingAs($this->superAdmin());

        $this->get(route('backup.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Backups')->where('summary.count', 1)->where('backups.data.0.name', '2026-11-06-10-00-00.zip'));
        $this->get(route('backup.download', ['file_name' => '2026-11-06-10-00-00.zip', 'disk' => 'local']))->assertOk();
        // Path tricks are reduced to the bare file name inside the backup folder.
        $this->get(route('backup.download', ['file_name' => '../../.env', 'disk' => 'local']))->assertNotFound();
        $this->delete(route('backup.destroy', ['file_name' => '2026-11-06-10-00-00.zip', 'disk' => 'local']))->assertRedirect();
        Storage::disk('local')->assertMissing("$name/2026-11-06-10-00-00.zip");
    }
}
