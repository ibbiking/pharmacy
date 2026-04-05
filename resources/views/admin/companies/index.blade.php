@extends('admin.layouts.app')

<x-assets.datatables />

@push('page-css')
<style>
/* Company Name Autocomplete */
.autocomplete-item.autocomplete-active,
.autocomplete-item:hover {
    background-color: #f8f9fa;
    color: #007bff;
}
</style>
@endpush

@push('page-header')
<div class="col-sm-7 col-auto">
	<h3 class="page-title">Companies</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Companies</li>
	</ul>
</div>
<div class="col-sm-5 col">
	<a href="{{route('companies.create')}}" class="btn btn-success float-right mt-2">Add Company</a>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-sm-12">
		<div class="card">
			<div class="card-body">
				<div class="table-responsive">
					<table id="company-table"
						class="datatable table table-striped table-bordered table-hover table-center mb-0">
						<thead>
							<tr style="boder:1px solid black;">
								<th>Name</th>
								<th>Created date</th>
								<th class="text-center action-btn">Actions</th>
							</tr>
						</thead>
						<tbody>

						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="add_companies" aria-hidden="true" role="dialog">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Add Company</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="POST" action="{{route('companies.store')}}">
					@csrf
					<div class="row form-row">
						<div class="col-12">
							<div class="form-group">
								<label>Company Name</label>
								<div class="custom-autocomplete-wrapper position-relative">
									<input type="text" name="name" id="company_name_index_input" autocomplete="off" class="form-control" placeholder="Search or type company name...">
									<div id="company_index_autocomplete_dropdown" class="w-100 position-absolute shadow bg-white" style="display: none; max-height: 200px; overflow-y: auto; z-index: 1000; border-radius: 10px; top: 100%; left: 0; margin-top: 5px; border: 1px solid #ced4da;">
										<ul class="list-unstyled mb-0" id="company_index_autocomplete_list"></ul>
										<div id="index_autocomplete_loading" class="text-center p-2 text-muted" style="display: none;">
											<div class="spinner-border spinner-border-sm" role="status"></div> Loading...
										</div>
									</div>
								</div>
							</div>
						</div>

						{{-- <div class="col-12">
							<div class="form-group">
								<label>Parent Company (optional)</label>
								<select name="parent_company_id" class="select2 form-control">
									<option value="">-- None --</option>
									@foreach($allcompanies as $cat)
									<option value="{{ $cat->id }}">{{ $cat->name }}</option>
									@endforeach
								</select>
							</div>
						</div> --}}
					</div>
					<button type="submit" class="btn btn-success btn-block">Save Changes</button>
				</form>
			</div>
		</div>
	</div>
</div>
<!-- /ADD Modal -->
<!-- Visit codeastro.com for more projects -->
<!-- Edit Details Modal -->
<div class="modal fade" id="edit_company" aria-hidden="true" role="dialog">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Edit Company</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			{{-- <div class="modal-body">
				<form method="post" action="{{route('companies.update')}}">
					@csrf
					@method("PUT")
					<div class="row form-row">
						<div class="col-12">
							<input type="hidden" name="id" id="edit_id">
							<div class="form-group">
								<label>Company</label>
								<input type="text" class="form-control edit_name" name="name">
							</div>
							<div class="form-group">
								<label>Parent Company (optional)</label>
								<select name="parent_company_id" class="select2 form-control edit_parent">
									<option value="">-- None --</option>
									@foreach($allcompanies as $cat)
									<option value="{{ $cat->id }}">{{ $cat->name }}</option>
									@endforeach
								</select>
							</div>
						</div>

					</div>
					<button type="submit" class="btn btn-success btn-block">Save Changes</button>
				</form>
			</div> --}}
		</div>
	</div>
</div>
<!-- /Edit Details Modal -->
@endsection

@push('page-js')
<script>
	$(document).ready(function() {
        var table = $('#company-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{route('companies.index')}}",
            columns: [
                {data: 'name', name: 'name'},
                {data: 'created_at', name: 'created_at'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });

        // Autocomplete for Inline Modal
        let autocompletePage = 1;
        let autocompleteHasMore = true;
        let autocompleteLoading = false;
        let autocompleteTimer;
        let currentFocus = -1;
        let isSelecting = false;

        const $input = $('#company_name_index_input');
        const $dropdown = $('#company_index_autocomplete_dropdown');
        const $list = $('#company_index_autocomplete_list');
        const $loader = $('#index_autocomplete_loading');

        function fetchAutocomplete(term, page, append = false) {
            if(autocompleteLoading) return;
            autocompleteLoading = true;
            if(!append) {
                $list.empty();
                $dropdown.show();
                currentFocus = -1;
            }
            $loader.show();

            $.ajax({
                url: "{{ route('companies.autocomplete') }}",
                data: { term: term, page: page },
                success: function(res) {
                    if(res.results.length === 0 && !append) {
                        $dropdown.hide();
                    } else {
                        res.results.forEach(function(item) {
                            let safeText = $('<div>').text(item.text).html();
                            $list.append(`<li class="autocomplete-item p-2 border-bottom" style="cursor:pointer;" data-val="${safeText}">${safeText}</li>`);
                        });
                    }
                    autocompleteHasMore = res.pagination.more;
                    autocompleteLoading = false;
                    $loader.hide();
                },
                error: function() {
                    autocompleteLoading = false;
                    $loader.hide();
                }
            });
        }

        $input.on('input', function() {
            clearTimeout(autocompleteTimer);
            let term = $(this).val();
            autocompletePage = 1;
            autocompleteHasMore = true;
            
            if(term.length < 1) {
                $dropdown.hide();
                return;
            }
            
            autocompleteTimer = setTimeout(() => {
                fetchAutocomplete(term, autocompletePage, false);
            }, 300);
        });

        $input.on('focus', function() {
            if (isSelecting) return;
            let term = $(this).val();
            if(term.length >= 1) {
                $dropdown.show();
            }
        });

        function addActive(items) {
            if (!items || !items.length) return false;
            items.removeClass('autocomplete-active text-primary bg-light');
            if (currentFocus >= items.length) currentFocus = 0;
            if (currentFocus < 0) currentFocus = (items.length - 1);
            
            let activeEl = $(items[currentFocus]);
            activeEl.addClass('autocomplete-active text-primary bg-light');

            let itemTop = activeEl.position().top;
            let itemHeight = activeEl.outerHeight();
            let listHeight = $dropdown.height();
            let scrollPos = $dropdown.scrollTop();

            if (itemTop < 0) {
                $dropdown.scrollTop(scrollPos + itemTop);
            } else if (itemTop + itemHeight > listHeight) {
                $dropdown.scrollTop(scrollPos + itemTop + itemHeight - listHeight);
            }
        }

        $input.on('keydown', function(e) {
            let items = $list.find('.autocomplete-item');
            if (!$dropdown.is(':visible') || !items.length) return;

            if (e.keyCode === 40) { // Down
                e.preventDefault();
                currentFocus++;
                addActive(items);
            } else if (e.keyCode === 38) { // Up
                e.preventDefault();
                currentFocus--;
                addActive(items);
            } else if (e.keyCode === 13) { // Enter
                e.preventDefault();
                if (currentFocus > -1) {
                    $(items[currentFocus]).click();
                }
            } else if (e.keyCode === 27) { // Escape
                $dropdown.hide();
            }
        });

        $dropdown.on('scroll', function() {
            if($(this).scrollTop() + $(this).innerHeight() >= $(this)[0].scrollHeight - 5) {
                if(autocompleteHasMore && !autocompleteLoading) {
                    autocompletePage++;
                    fetchAutocomplete($input.val(), autocompletePage, true);
                }
            }
        });

        $(document).on('click', '.autocomplete-item', function() {
            isSelecting = true;
            $input.val($(this).data('val'));
            $dropdown.hide();
            $input.focus(); 
            setTimeout(() => { isSelecting = false; }, 100);
        });

        $(document).on('click', function(e) {
            if(!$(e.target).closest('.custom-autocomplete-wrapper').length) {
                $dropdown.hide();
            }
        });
    });
</script>
@endpush