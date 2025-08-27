@extends('admin.layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Set Parameters for {{ $product->purchase->product ?? 'Product' }}</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('products.parameters.store', $product->id) }}">
                    @csrf

                    <div class="form-group">
                        <label for="category">Select Base Category</label>
                        <select class="form-control" id="base-category">
                            <option value="">-- Select Category --</option>
                            @foreach($categories as $category)
                            <option value="{{ $category->id }}" data-children='@json($category->childrenRecursive)'>{{
                                $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="parameter-fields">
                        <!-- Dynamic fields will be appended here -->
                    </div>

                    <button type="submit" class="btn btn-success">Save Parameters</button>
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

        // recurse with correct parent context
        if (current.children_recursive && current.children_recursive.length > 0) {
            traverseChildren(
                current.children_recursive,
                fields,
                current.id,
                current.name,
                baseCategoryId
            );
        }
    }

    return fields;
}

    document.getElementById('base-category').addEventListener('change', function () {
    const selected = this.options[this.selectedIndex];
    const categoryId = this.value;
    const children = JSON.parse(selected.dataset.children || '[]');
    
    const fields = traverseChildren(children, [], categoryId, 'Base', categoryId);

    const container = document.getElementById('parameter-fields');
    container.innerHTML = '';

    fields.forEach((field, index) => {
        container.innerHTML += `
            <div class="form-group">
                <label>${field.label}</label>
                <input type="number" name="parameters[${index}][quantity]" class="form-control" placeholder="e.g., 5" required>
                <input type="hidden" name="parameters[${index}][parent_category_id]" value="${field.parent_id || ''}">
                <input type="hidden" name="parameters[${index}][child_category_id]" value="${field.child_id}">
                <input type="hidden" name="parameters[${index}][category_id]" value="${categoryId}">
            </div>
        `;
    });
});
</script>
@endpush