@extends('admin.layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title">Set Parameters for {{ $product->product_name }}</h4>

                @if($parameters->count())
                <button type="button" id="enable-update" class="btn btn-primary btn-sm">
                    Update Parameters
                </button>
                @endif
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('products.parameters.store', $product->id) }}">
                    @csrf

                    <h5>Base Category: {{ $baseCategory->name ?? 'N/A' }}</h5>

                    <div id="parameter-fields">

                        {{-- Base Category Qty & Price --}}
                        @if ($baseCategory)
                        <div class="form-group">
                            <label>{{ $baseCategory->name }}</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <input type="number" value="1" class="form-control" disabled>
                                    <input type="hidden" name="parameters[{{ $baseCategory->id }}][quantity]" value="1">
                                </div>
                                <div class="col-md-5">
                                    <input type="number" step="0.01"
                                        name="parameters[{{ $baseCategory->id }}][static_category_unit_purchase_price]"
                                        value="{{ $parameters[$baseCategory->id]->static_category_unit_purchase_price ?? '' }}"
                                        class="form-control param-input" placeholder="Unit purchase price (e.g., 800)" {{
                                        $parameters->count() ? 'disabled' : '' }} required>
                                </div>
                                <div class="col-md-5">
                                    <input type="number" step="0.01"
                                        name="parameters[{{ $baseCategory->id }}][static_category_unit_sale_price]"
                                        value="{{ $parameters[$baseCategory->id]->static_category_unit_sale_price ?? '' }}"
                                        class="form-control param-input" placeholder="Unit sale price (e.g., 1000)" {{
                                        $parameters->count() ? 'disabled' : '' }} required>
                                </div>
                            </div>

                            {{-- Parent = Self for Base Category --}}
                            <input type="hidden" name="parameters[{{ $baseCategory->id }}][parent_category_id]"
                                value="{{ $baseCategory->id }}">
                            <input type="hidden" name="parameters[{{ $baseCategory->id }}][child_category_id]"
                                value="{{ $baseCategory->id }}">
                            <input type="hidden" name="parameters[{{ $baseCategory->id }}][category_id]"
                                value="{{ $baseCategory->id }}">
                        </div>
                        @endif

                        {{-- Recursive Children Fields --}}
                        @php
                        function renderFields($children, $parentId, $parentName, $baseId, $parameters) {
                        foreach ($children as $child) {
                        $childId = is_array($child) ? $child['id'] : $child->id;
                        $childName = is_array($child) ? $child['name'] : $child->name;

                        $param = $parameters[$childId] ?? null;
                        $qty = $param->quantity ?? '';
                        $price = $param->static_category_unit_sale_price ?? '';

                        echo "
                        <div class='form-group'>
                            <label>{$childName} (per {$parentName})</label>
                            <div class='row'>
                                <div class='col-md-3'>
                                    <input type='number' name='parameters[{$childId}][quantity]' value='{$qty}'
                                        class='form-control param-input'
                                        placeholder='Quantity (e.g., 5)' " . ($parameters->count() ? 'disabled' : '') . "
                                        required>
                                </div>
                                <div class='col-md-4'>
                                    <input type='number' step='0.01'
                                        name='parameters[{$childId}][static_category_unit_purchase_price]' value='" . ($param->static_category_unit_purchase_price ?? '') . "'
                                        class='form-control param-input'
                                        placeholder='Unit purchase price' " . ($parameters->count() ? 'disabled' : '') . "
                                        required>
                                </div>
                                <div class='col-md-5'>
                                    <input type='number' step='0.01'
                                        name='parameters[{$childId}][static_category_unit_sale_price]' value='{$price}'
                                        class='form-control param-input'
                                        placeholder='Unit sale price' " . ($parameters->count() ? 'disabled' : '') . "
                                        required>
                                </div>
                            </div>

                            <input type='hidden' name='parameters[{$childId}][parent_category_id]' value='{$parentId}'>
                            <input type='hidden' name='parameters[{$childId}][child_category_id]' value='{$childId}'>
                            <input type='hidden' name='parameters[{$childId}][category_id]' value='{$baseId}'>
                        </div>
                        ";

                        if (!empty($child->children ?? $child['children'] ?? [])) {
                        $nextChildren = is_array($child) ? $child['children'] : $child->children;
                        renderFields($nextChildren, $childId, $childName, $baseId, $parameters);
                        }
                        }
                        }
                        if ($baseCategory) {
                        renderFields($children, $baseCategory->id, $baseCategory->name, $baseCategory->id, $parameters);
                        }
                        @endphp
                    </div>

                    <button type="submit" id="submit-parameters" class="btn btn-success" @if($parameters->count())
                        disabled @endif>
                        {{ $parameters->count() ? 'Update Parameters' : 'Save Parameters' }}
                    </button>
                </form>

                @if($parameters->count())
                <div class="mt-4">
                    <h5>Existing Parameters</h5>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Parent Category</th>
                                <th>Child Category</th>
                                <th>Quantity</th>
                                <th>Unit Purchase Price</th>
                                <th>Unit Sale Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($parameters as $index => $param)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $param->parentCategory->name ?? 'Base' }}</td>
                                <td>{{ $param->childCategory->name ?? '-' }}</td>
                                <td>{{ $param->quantity }}</td>
                                <td>{{ $param->static_category_unit_purchase_price ?? '-' }}</td>
                                <td>{{ $param->static_category_unit_sale_price ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const updateBtn = document.getElementById('enable-update');
        if (updateBtn) {
            updateBtn.addEventListener('click', function () {
                // enable inputs
                document.querySelectorAll('.param-input').forEach(el => {
                    el.disabled = false;
                });

                // enable button
                const submitBtn = document.getElementById('submit-parameters');
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            });
        }
    });
</script>
@endpush