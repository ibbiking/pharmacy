<div class="modal-header bg-primary text-white">
    <h5 class="modal-title mb-0">
        <i class="fas fa-capsules mr-2"></i>{{ $detailsTitle }}
    </h5>
    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>

<div class="modal-body" style="background: #f6f9ff;">
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h4 class="text-primary mb-2">{{ $itemName }}</h4>
            <div class="d-flex flex-wrap">
                <span class="badge badge-pill badge-info mr-2 mb-2">ID: {{ $itemId }}</span>
                <span class="badge badge-pill {{ $statusClass }} mr-2 mb-2">Status: {{ $statusLabel }}</span>
                @if($barcode)
                    <span class="badge badge-pill badge-dark mr-2 mb-2">Barcode: {{ $barcode }}</span>
                @endif
            </div>
            @if($description)
                <p class="mb-0 mt-2 text-muted">{{ $description }}</p>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-light"><strong>Core Information</strong></div>
                <div class="card-body">
                    <p class="mb-2"><strong>Company:</strong> {{ $companyName ?: '-' }}</p>
                    <p class="mb-2"><strong>Product Type:</strong> {{ $typeName ?: '-' }}</p>
                    <p class="mb-2"><strong>Rack:</strong> {{ $rack ?: '-' }}</p>
                    <p class="mb-2"><strong>Max Discount Amount:</strong> {{ number_format((float)$discountAmount, 2) }}</p>
                    <p class="mb-0"><strong>Max Discount Percent:</strong> {{ number_format((float)$discountPercent, 2) }}%</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-light"><strong>Strengths & Farmulas</strong></div>
                <div class="card-body">
                    <p class="mb-1"><strong>Strengths:</strong></p>
                    @if(count($strengthNames))
                        <div class="mb-2">
                            @foreach($strengthNames as $name)
                                <span class="badge badge-info mr-1 mb-1">{{ $name }}</span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted">-</p>
                    @endif

                    <p class="mb-1"><strong>Farmulas:</strong></p>
                    @if(count($farmulaNames))
                        <div>
                            @foreach($farmulaNames as $name)
                                <span class="badge badge-secondary mr-1 mb-1">{{ $name }}</span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted">-</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-gradient" style="background: linear-gradient(90deg, #4f46e5, #06b6d4); color: #fff;">
            <strong>Packaging & Pricing Parameters</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-bordered mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Parent Category</th>
                            <th>Child Category</th>
                            <th>Quantity</th>
                            <th>Purchase Price</th>
                            <th>Sale Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($parameters as $index => $param)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $param['parent_category'] }}</td>
                                <td>{{ $param['child_category'] }}</td>
                                <td>{{ rtrim(rtrim(number_format((float)$param['quantity'], 4, '.', ''), '0'), '.') }}</td>
                                <td>{{ number_format((float)$param['purchase_price'], 2) }}</td>
                                <td>{{ number_format((float)$param['sale_price'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">No parameter details available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
