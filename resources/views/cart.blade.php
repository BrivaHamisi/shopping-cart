@extends('shop')

@section('head')
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection
   
@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0 text-center">
                        <i class="fa fa-shopping-cart me-2"></i>Your Shopping Cart
                    </h2>
                </div>
                <div class="card-body p-0">
                    <table id="cart" class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3 text-center">Price</th>
                                <th class="px-4 py-3 text-center">Quantity</th>
                                <th class="px-4 py-3 text-center">Total</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $total = 0 @endphp
                            @if(session('cart'))
                                @foreach(session('cart') as $id => $details)
                                    @php 
                                        $subtotal = $details['price'] * $details['quantity'];
                                        $total += $subtotal;
                                    @endphp
                                    <tr rowId="{{ $id }}" class="align-middle">
                                        <td data-th="Product" class="px-4 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-3" style="max-width: 100px;">
                                                    <img src="{{ $details['image'] }}" class="img-fluid rounded shadow-sm"/>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h5 class="mb-0">{{ $details['name'] }}</h5>
                                                    <p class="text-muted mb-0 small">Unique Product</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-th="Price" class="text-center px-4 py-3">
                                            <strong class="text-primary">${{ $details['price'] }}</strong>
                                        </td>
                                        <td data-th="Quantity" class="text-center px-4 py-3">
                                            <div class="input-group justify-content-center">
                                                <input type="number" value="{{ $details['quantity'] }}" 
                                                       class="form-control form-control-sm text-center update-cart" 
                                                       style="max-width: 80px;" 
                                                       min="1"/>
                                            </div>
                                        </td>
                                        <td data-th="Subtotal" class="text-center px-4 py-3">
                                            <strong>${{ number_format($subtotal, 2) }}</strong>
                                        </td>
                                        <td class="text-center px-4 py-3">
                                            <a class="btn btn-outline-danger btn-sm delete-product">
                                                <i class="fa fa-trash"></i> Remove
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                    
                    @if(session('cart') && count(session('cart')) > 0)
                        <div class="card-footer bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="{{ url('/home') }}" class="btn btn-outline-secondary">
                                        <i class="fa fa-angle-left me-2"></i>Continue Shopping
                                    </a>
                                </div>
                                <div class="text-end">
                                    <h4 class="mb-3">Total: <strong class="text-primary">${{ number_format($total, 2) }}</strong></h4>
                                    <button class="btn btn-success checkout-btn" data-total="{{ $total }}">
                                        Proceed to Checkout <i class="fa fa-angle-right ms-2"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <h4 class="text-muted">Your cart is empty</h4>
                            <a href="{{ url('/home') }}" class="btn btn-primary mt-3">
                                Start Shopping
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- M-Pesa Payment Modal -->
    <div class="modal fade" id="mpesaPaymentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-mobile me-2"></i>M-Pesa Payment
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="mpesaPaymentForm">
                        @csrf
                        <div class="mb-4">
                            <label for="phone_number" class="form-label">M-Pesa Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-phone"></i></span>
                                <input type="tel" class="form-control" id="phone_number" 
                                       placeholder="Enter your M-Pesa registered phone number" 
                                       pattern="^(?:254|\+254|0)?(7(?:(?:[0-9][0-9])|(?:0[0-9])|(?:1[0-9]))\d{6})$" 
                                       required>
                            </div>
                            <small class="form-text text-muted">Format: 254712345678</small>
                        </div>
                        <div class="mb-4">
                            <div class="card bg-light border-0">
                                <div class="card-body">
                                    <h6 class="card-title">Payment Summary</h6>
                                    <p class="mb-0">Total Amount: <strong class="text-primary" id="modal-total-display"></strong></p>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="total_amount" name="total_amount" value="{{ $total }}">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fa fa-lock me-2"></i>Pay with M-Pesa
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
   
@section('scripts')
<script type="text/javascript">
    $(document).ready(function() {
        // Checkout Button Script
        $(".checkout-btn").click(function(e) {
            e.preventDefault();
            var total = $(this).data('total');
            
            if (total <= 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Empty Cart',
                    text: 'Your cart is empty. Please add items before checkout.'
                });
                return;
            }
            
            $('#total_amount').val(total);
            $('#modal-total-display').text('$' + total.toFixed(2));
            
            $('#mpesaPaymentModal').modal('show');
        });

        // Update Cart Quantity
        $(".update-cart").change(function (e) {
            e.preventDefault();
            var ele = $(this);
            var quantity = ele.val();
            
            $.ajax({
                url: '{{ route('update.shopping.cart') }}',
                method: "patch",
                data: {
                    _token: '{{ csrf_token() }}', 
                    id: ele.parents("tr").attr("rowId"),
                    quantity: quantity
                },
                success: function (response) {
                   window.location.reload();
                }
            });
        });
   
        // Delete Product from Cart
        $(".delete-product").click(function (e) {
            e.preventDefault();
    
            var ele = $(this);
    
            Swal.fire({
                title: 'Are you sure?',
                text: 'Do you want to remove this item from cart?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, remove it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '{{ route('delete.cart.product') }}',
                        method: "DELETE",
                        data: {
                            _token: '{{ csrf_token() }}', 
                            id: ele.parents("tr").attr("rowId")
                        },
                        success: function (response) {
                            window.location.reload();
                        }
                    });
                }
            });
        });

        // M-Pesa Payment Script
        $("#mpesaPaymentForm").submit(function(e) {
            e.preventDefault();
            
            $.ajax({
                url: '{{ route('mpesa.stk-push') }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    phone_number: $('#phone_number').val(),
                    total_amount: $('#total_amount').val()
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Payment Initiated',
                            text: 'M-Pesa STK Push sent. Please complete payment on your phone.'
                        });
                        
                        $('#mpesaPaymentModal').modal('hide');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Payment Failed',
                            text: response.message
                        });
                    }
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: xhr.responseJSON.message
                    });
                }
            });
        });
    });
</script>
@endsection

@push('styles')
<style>
    body {
        background-color: #f4f6f9;
    }
    .table-hover tbody tr:hover {
        background-color: rgba(0,123,255,0.05);
    }
</style>
@endpush