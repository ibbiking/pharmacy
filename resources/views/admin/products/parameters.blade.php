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
                        @php
                        function renderFields($children, $parentId, $parentName, $baseId, $parameters) {
                        foreach ($children as $child) {
                        $childId = is_array($child) ? $child['id'] : $child->id;
                        $childName = is_array($child) ? $child['name'] : $child->name;

                        $param = $parameters[$childId] ?? null;
                        $qty = $param->quantity ?? '';

                        echo "
                        <div class='form-group'>
                            <label>Set quantity of {$childName} in each {$parentName}</label>
                            <input type='number' name='parameters[{$childId}][quantity]' value='{$qty}'
                                class='form-control param-input'
                                placeholder='e.g., 5' " . ($parameters->count() ? 'disabled' : '') . " required>
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
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($parameters as $index => $param)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $param->parentCategory->name ?? 'Base' }}</td>
                                <td>{{ $param->childCategory->name ?? '-' }}</td>
                                <td>{{ $param->quantity }}</td>
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
    function traverseChildren(children, fields = [], parentId = null, parentName = 'Base', baseCategoryId = null) {
    if (!children || !children.length) return fields;

    for (let i = 0; i < children.length; i++) {
        const current = children[i];

        fields.push({
            parent_id: parentId || baseCategoryId,
            parent_name: parentName,
            child_id: current.id,
            child_name: current.name,
            label: `Set quantity of ${current.name} in each ${parentName}`
        });

        if (current.children && current.children.length > 0) {
            traverseChildren(
                current.children,
                fields,
                current.id,
                current.name,
                baseCategoryId
            );
        }
    }

    return fields;
}

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