<!-- Sidebar -->
<style>
	#sidebar-menu a .menu-arrow {
		transition: transform 0.2s ease-in-out;
	}
	#sidebar-menu a.subdrop .menu-arrow {
		transform: rotate(90deg);
	}
</style>
<div class="sidebar" id="sidebar">
	<div class="sidebar-inner slimscroll">
		<div id="sidebar-menu" class="sidebar-menu">

			<ul>
				<li class="menu-title">
					<span>Main</span>
				</li>
				<li class="{{ route_is('dashboard') ? 'active' : '' }}">
					<a href="{{route('dashboard')}}"><i class="fe fe-home"></i> <span>Dashboard</span></a>
				</li>

				@can('view-category')
				<li class="submenu">
					<a href="#"><i class="fas fa-box"></i> <span> Packaging</span> <span
							class="fas fa-chevron-right menu-arrow"></span></a>
					<ul style="display: none;">
						<li><a class="{{ route_is('categories.*') ? 'active' : '' }}"
								href="{{route('categories.index')}}">Packaging</a></li>
						@can('create-category')
						<li><a class="{{ route_is('categories.create') ? 'active' : '' }}"
								href="{{route('categories.create')}}">Add Packaging</a></li>
						@endcan
					</ul>
				</li>
				@endcan

				<li class="submenu">
					<a href="#"><i class="fas fa-building"></i> <span> Companies</span> <span
							class="fas fa-chevron-right menu-arrow"></span></a>
					<ul style="display: none;">
						<li><a class="{{ route_is('companies.*') ? 'active' : '' }}"
								href="{{route('companies.index')}}">Companies</a></li>
						<li><a class="{{ route_is('companies.create') ? 'active' : '' }}"
								href="{{route('companies.create')}}">Add Company</a></li>
					</ul>
				</li>

				<li class="submenu">
					<a href="#"><i class="fas fa-flask"></i> <span> Farmulas</span> <span
							class="fas fa-chevron-right menu-arrow"></span></a>
					<ul style="display: none;">
						<li><a class="{{ route_is('farmulas.*') ? 'active' : '' }}"
								href="{{route('farmulas.index')}}">Farmulas</a></li>
						<li><a class="{{ route_is('farmulas.create') ? 'active' : '' }}"
								href="{{route('farmulas.create')}}">Add Farmula</a></li>
					</ul>
				</li>

				<li class="submenu">
					<a href="#"><i class="fas fa-cubes"></i> <span> Product Types</span> <span
							class="fas fa-chevron-right menu-arrow"></span></a>
					<ul style="display: none;">
						<li><a class="{{ route_is('product-types.*') ? 'active' : '' }}"
								href="{{route('product-types.index')}}">Product Types</a></li>
						<li><a class="{{ route_is('product-types.create') ? 'active' : '' }}"
								href="{{route('product-types.create')}}">Add Product Type</a></li>
					</ul>
				</li>

				<li class="submenu">
					<a href="#"><i class="fas fa-bolt"></i> <span> Strengths</span> <span
							class="fas fa-chevron-right menu-arrow"></span></a>
					<ul style="display: none;">
						<li><a class="{{ route_is('strengths.*') ? 'active' : '' }}"
								href="{{route('strengths.index')}}">Strengths</a></li>
						<li><a class="{{ route_is('strengths.create') ? 'active' : '' }}"
								href="{{route('strengths.create')}}">Add Strength</a></li>
					</ul>
				</li>

				<li class="submenu">
					<a href="#"><i class="fas fa-percentage"></i> <span> Taxes</span> <span
							class="fas fa-chevron-right menu-arrow"></span></a>
					<ul style="display: none;">
						<li><a class="{{ route_is('taxes.*') ? 'active' : '' }}"
								href="{{route('taxes.index')}}">Taxes</a></li>
						<li><a class="{{ route_is('taxes.create') ? 'active' : '' }}"
								href="{{route('taxes.create')}}">Add Tax</a></li>
					</ul>
				</li>

				@can('view-supplier')
				<li class="submenu">
					<a href="#"><i class="fas fa-truck-loading"></i> <span> Supplier</span> <span
							class="fas fa-chevron-right menu-arrow"></span></a>
					<ul style="display: none;">
						<li><a class="{{ route_is('suppliers.*') ? 'active' : '' }}"
								href="{{route('suppliers.index')}}">Supplier</a></li>
						@can('create-supplier')
						<li><a class="{{ route_is('suppliers.create') ? 'active' : '' }}"
								href="{{route('suppliers.create')}}">Add Supplier</a></li>
						@endcan
					</ul>
				</li>
				@endcan

				@can('view-products')
				<li class="submenu">
					<a href="#"><i class="fas fa-pills"></i> <span> Products</span> <span
							class="fas fa-chevron-right menu-arrow"></span></a>
					<ul style="display: none;">
						<li><a class="{{ route_is(('products.*')) ? 'active' : '' }}"
								href="{{route('products.index')}}">Products</a></li>
						@can('create-product')<li><a class="{{ route_is('products.create') ? 'active' : '' }}"
								href="{{route('products.create')}}">Add Product</a></li>@endcan
						<li><a class="{{ route_is('products.drafts') ? 'active' : '' }}"
								href="{{route('products.drafts')}}">Draft Products</a></li>
						@can('view-outstock-products')<li><a class="{{ route_is('outstock') ? 'active' : '' }}"
								href="{{route('outstock')}}">Out-Stock</a></li>@endcan
						@can('view-expired-products')<li><a class="{{ route_is('expired') ? 'active' : '' }}"
								href="{{route('expired')}}">Expired</a></li>@endcan
					</ul>
				</li>
				@endcan

				@can('view-purchase')
				<li class="submenu">
					<a href="#"><i class="fas fa-shopping-basket"></i> <span> Purchase</span> <span
							class="fas fa-chevron-right menu-arrow"></span></a>
					<ul style="display: none;">
						<li><a class="{{ route_is('purchases.*') ? 'active' : '' }}"
								href="{{route('purchases.index')}}">Purchase</a></li>
						@can('create-purchase')
						<li><a class="{{ route_is('purchases.create') ? 'active' : '' }}"
								href="{{route('purchases.create')}}">Add Purchase</a></li>
						@endcan
					</ul>
				</li>
				@endcan

				<li class="submenu">
					<a href="#"><i class="fas fa-cash-register"></i> <span> POS</span> <span
							class="fas fa-chevron-right menu-arrow"></span></a>
					<ul style="display: none;">
						<li><a class="{{ route_is('pos.*') ? 'active' : '' }}"
								href="{{route('pos.index')}}">POS</a></li>
						@can('create-purchase')
						<li><a class="{{ route_is('pos.create') ? 'active' : '' }}"
								href="{{route('pos.create')}}">Add POS</a></li>
						@endcan
					</ul>
				</li>

				@can('view-sales')
				<li class="submenu">
					<a href="#"><i class="fas fa-file-invoice-dollar"></i> <span> Sales / Invoices</span> <span
							class="fas fa-chevron-right menu-arrow"></span></a>
					<ul style="display: none;">
						<li><a class="{{ route_is('invoices.*') ? 'active' : '' }}"
								href="{{route('invoices.index')}}">Invoices</a></li>
						@can('create-sale')
						<li><a class="{{ route_is('pos.index') ? 'active' : '' }}"
								href="{{route('pos.index')}}">Add Sale</a></li>
						@endcan
					</ul>
				</li>
				@endcan

				@can('view-reports')
				<li class="submenu">
					<a href="#"><i class="fas fa-chart-bar"></i> <span> Reports</span> <span
							class="fas fa-chevron-right menu-arrow"></span></a>
					<ul style="display: none;">
						<li><a class="{{ route_is('purchases.report') ? 'active' : '' }}"
								href="{{route('purchases.report')}}">Purchase Report</a></li>
						<li><a class="{{ route_is('reports.sales') ? 'active' : '' }}"
								href="{{route('reports.sales')}}">POS Sales Report</a></li>
						<li><a class="{{ route_is('reports.returns') ? 'active' : '' }}"
								href="{{route('reports.returns')}}">Returns Report</a></li>
						<li><a class="{{ route_is('reports.profit_loss') ? 'active' : '' }}"
								href="{{route('reports.profit_loss')}}">Profit / Loss Report</a></li>
						<li><a class="{{ route_is('reports.expiry') ? 'active' : '' }}"
								href="{{route('reports.expiry')}}">Expiry Report</a></li>
					</ul>
				</li>
				@endcan

				<li class="submenu">
					<a href="#"><i class="fas fa-sliders-h"></i> <span> Preferences</span> <span
							class="fas fa-chevron-right menu-arrow"></span></a>
					<ul style="display: none;">
						<li><a class="{{ route_is('global-sale-price-preferences.index') ? 'active' : '' }}"
								href="{{route('global-sale-price-preferences.index')}}">Sale Price Preferences</a></li>
					</ul>
				</li>
				
				<li class="{{ route_is('pharmacies.*') ? 'active' : '' }}">
					<a href="{{route('pharmacies.index')}}"><i class="fas fa-clinic-medical"></i> <span>Pharmacy Name</span></a>
				</li>

				@can('view-access-control')
				<li class="submenu">
					<a href="#"><i class="fas fa-shield-alt"></i> <span> Access Control</span> <span
							class="fas fa-chevron-right menu-arrow"></span></a>
					<ul style="display: none;">
						@can('view-permission')
						<li><a class="{{ route_is('permissions.index') ? 'active' : '' }}"
								href="{{route('permissions.index')}}">Permissions</a></li>
						@endcan
						@can('view-role')
						<li><a class="{{ route_is('roles.*') ? 'active' : '' }}"
								href="{{route('roles.index')}}">Roles</a></li>
						@endcan
					</ul>
				</li>
				@endcan

				@can('view-users')
				<li class="{{ route_is('users.*') ? 'active' : '' }}">
					<a href="{{route('users.index')}}"><i class="fe fe-users"></i> <span>Users</span></a>
				</li>
				@endcan

				<li class="{{ route_is('profile') ? 'active' : '' }}">
					<a href="{{route('profile')}}"><i class="fe fe-user-plus"></i> <span>Profile</span></a>
				</li>
				<li class="{{ route_is('backup.index') ? 'active' : '' }}">
					<a href="{{route('backup.index')}}"><i class="material-icons">backup</i> <span>Backups</span></a>
				</li>
				@can('view-settings')
				<li class="{{ route_is('settings') ? 'active' : '' }}">
					<a href="{{route('settings')}}">
						<i class="material-icons">settings</i>
						<span> Settings</span>
					</a>
				</li>
				@endcan
			</ul>
		</div>
	</div>
</div><!-- Visit codeastro.com for more projects -->
<!-- /Sidebar -->