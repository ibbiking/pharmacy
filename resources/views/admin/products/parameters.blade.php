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
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script>
    function traverseChildren(parent, fields = []) {
        if (!parent || !parent.length) return fields;

        for (let i = 0; i < parent.length; i++) {
            const current = parent[i];
            const parentId = fields.length ? fields[fields.length - 1].child_id : null;
            const childId = current.id;
            const label = `Set quantity of ${current.name} in each ${fields.length ? fields[fields.length - 1].child_name : 'parent'}`;

            fields.push({
    parent_id: parentId,
    parent_name: fields.length ? fields[fields.length - 1].child_name : 'Parent',
    child_id: current.id,
    child_name: current.name,
    label: `Set quantity of ${current.name} in each ${fields.length ? fields[fields.length - 1].child_name : 'Base'}`
});

            if (current.children_recursive && current.children_recursive.length > 0) {
                traverseChildren(current.children_recursive, fields);
            }
        }
        return fields;
    }

    document.getElementById('base-category').addEventListener('change', function () {
        const selected = this.options[this.selectedIndex];
        const categoryId = this.value;
        const children = JSON.parse(selected.dataset.children || '[]');
        // const categoryId = this.value;
        // document.getElementById('selected-category-id').value = categoryId;

        const fields = traverseChildren(children);
const container = document.getElementById('parameter-fields');
container.innerHTML = '';

fields.forEach((field, index) => {
    container.innerHTML += `
        <div class="form-group">
            <label>${field.label}</label>
            <input type="number" name="parameters[${index}][quantity]" class="form-control" placeholder="e.g., 5" required>
            <input type="hidden" name="parameters[${index}][parent_category_id]" value="${field.parent_id !== undefined ? field.parent_id : ''}">
            <input type="hidden" name="parameters[${index}][child_category_id]" value="${field.child_id}">
            <input type="hidden" name="parameters[${index}][category_id]" value="${categoryId}">
        </div>
    `;
});
    });
</script>
@endpush