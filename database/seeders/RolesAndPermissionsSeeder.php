<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        //create permissions
        $arrayOfPermissionNames = [
          'view-sales', 'create-sale','destroy-sale','edit-sale',
          'view-reports','view-category','create-category','destroy-category','edit-category',
          'view-products','create-product','edit-product','destroy-product',
          'view-purchase','create-purchase','edit-purchase','destroy-purchase',
          'view-supplier','create-supplier','edit-supplier','destroy-supplier',
          'view-users','create-user','edit-user','destroy-user',
          'view-access-control',
          'view-role','edit-role','destroy-role','create-role',
          'view-permission','create-permission','edit-permission','destroy-permission',
          'view-expired-products','view-outstock-products','backup-app','backup-db','view-settings',

        ];
       $permissions = collect($arrayOfPermissionNames)->map(function ($permission) {
           return ['name' => $permission, 'guard_name' => 'web'];
       });

      foreach($permissions as $permission) {
          Permission::firstOrCreate(['name' => $permission['name']], ['guard_name' => $permission['guard_name']]);
      }

        // create roles and assign permissions
        $role = Role::firstOrCreate(['name' => 'sales-person']);
        $role->syncPermissions([]);
        $role = Role::firstOrCreate(['name' => 'super-admin']);
        $role->givePermissionTo(Permission::all());
        $role = Role::firstOrCreate(['name' => 'Business Owner']);
        $role->givePermissionTo(Permission::all());
    }
}
