@extends('admin.layouts.app')

@push('page-css')
<style>
/* Modern Switch CSS */
.status-switch {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 20px;
}
.status-switch input { 
    opacity: 0;
    width: 0;
    height: 0;
}
.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 20px;
}
.slider:before {
    position: absolute;
    content: "";
    height: 14px;
    width: 14px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}
input:checked + .slider {
    background-color: #28a745;
}
input:focus + .slider {
    box-shadow: 0 0 1px #28a745;
}
input:checked + .slider:before {
    transform: translateX(20px);
}
</style>
@endpush

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Contacts: {{ $contact_list->name }}</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('bec.dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item"><a href="{{route('bec.contact-lists.index')}}">Contact Lists</a></li>
		<li class="breadcrumb-item active">Contacts</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="card-title">Filter Contacts</h4>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-sm btn-success bulk-btn bulk-enable" disabled>Bulk Enable</button>
                        <button class="btn btn-sm btn-danger bulk-btn bulk-disable" disabled>Bulk Disable</button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('bec.contacts.index', $contact_list->id) }}" method="GET" class="mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="Search by email or data..." value="{{ request('search') }}">
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-control">
                                <option value="">Filter by Status</option>
                                <option value="enabled" {{ request('status') == 'enabled' ? 'selected' : '' }}>Enabled</option>
                                <option value="disabled" {{ request('status') == 'disabled' ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="check-all"></th>
                                <th>Email</th>
                                @foreach($columns as $col)
                                    @if($col->column_name != 'email')
                                        <th>{{ $col->ui_label }}</th>
                                    @endif
                                @endforeach
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($contacts as $contact)
                            <tr data-id="{{ $contact->id }}">
                                <td><input type="checkbox" class="contact-checkbox" value="{{ $contact->id }}"></td>
                                <td>{{ $contact->email }}</td>
                                @foreach($columns as $col)
                                    @if($col->column_name != 'email')
                                        <td>{{ $contact->data[$col->column_name] ?? '-' }}</td>
                                    @endif
                                @endforeach
                                <td>
                                    <span class="badge badge-{{ $contact->status == 'enabled' ? 'success' : 'danger' }} status-badge">
                                        {{ ucfirst($contact->status) }}
                                    </span>
                                </td>
                                <td>
                                    <label class="status-switch">
                                        <input type="checkbox" class="toggle-status-switch" data-id="{{ $contact->id }}" {{ $contact->status == 'enabled' ? 'checked' : '' }}>
                                        <span class="slider"></span>
                                    </label>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $contacts->appends(request()->input())->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script>
$(document).ready(function() {
    function updateBulkButtons() {
        var selectedCount = $('.contact-checkbox:checked').length;
        $('.bulk-btn').prop('disabled', selectedCount === 0);
    }

    $('#check-all').on('click', function() {
        $('.contact-checkbox').prop('checked', this.checked);
        updateBulkButtons();
    });

    $('.contact-checkbox').on('change', function() {
        updateBulkButtons();
    });

    $('.toggle-status-switch').on('change', function() {
        var id = $(this).data('id');
        var isChecked = $(this).is(':checked');
        var switchInput = $(this);
        
        $.post("{{ route('bec.contacts.toggle-status') }}", {
            id: id,
            _token: "{{ csrf_token() }}"
        }, function(res) {
            if(res.success) {
                var badge = switchInput.closest('tr').find('.status-badge');
                badge.text(res.status.charAt(0).toUpperCase() + res.status.slice(1));
                badge.removeClass('badge-success badge-danger').addClass('badge-' + (res.status == 'enabled' ? 'success' : 'danger'));
                
                Snackbar.show({
                    text: 'Status updated to ' + res.status,
                    pos: 'top-right',
                    backgroundColor: res.status == 'enabled' ? '#8dbf42' : '#e7515a'
                });
            } else {
                // Revert switch if failed
                switchInput.prop('checked', !isChecked);
                alert('Failed to update status');
            }
        }).fail(function() {
            switchInput.prop('checked', !isChecked);
            alert('Error updating status');
        });
    });

    $('.bulk-enable, .bulk-disable').on('click', function() {
        var ids = $('.contact-checkbox:checked').map(function() { return $(this).val(); }).get();
        if(ids.length == 0) return;
        
        var action = $(this).hasClass('bulk-enable') ? 'enable' : 'disable';
        
        $.post("{{ route('bec.contacts.bulk-action') }}", {
            ids: ids,
            action: action,
            _token: "{{ csrf_token() }}"
        }, function(res) {
            if(res.success) {
                location.reload();
            }
        });
    });
});
</script>
@endpush

