{{-- @extends('shop')
   
@section('content')
<table id="cart" class="table table-bordered">
    <thead>
        <tr>
            <th>Product</th>
            <th>Price</th>
            <th>Total</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @php $total = 0 @endphp
        @if(session('cart'))
            @foreach(session('cart') as $id => $details)
                 
                <tr rowId="{{ $id }}">
                    <td data-th="Product">
                        <div class="row">
                            <div class="col-sm-3 hidden-xs"><img src="{{ $details['image'] }}" class="card-img-top"/></div>
                            <div class="col-sm-9">
                                <h4 class="nomargin">{{ $details['name'] }}</h4>
                            </div>
                        </div>
                    </td>
                    <td data-th="Price">${{ $details['price'] }}</td>
                    
                    <td data-th="Subtotal" class="text-center"></td>
                    <td class="actions">
                        <a class="btn btn-outline-danger btn-sm delete-product"><i class="fa fa-trash-o"></i></a>
                    </td>
                </tr>
            @endforeach
        @endif
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="text-right">
                <a href="{{ url('/home') }}" class="btn btn-primary"><i class="fa fa-angle-left"></i> Continue Shopping</a>
                <button class="btn btn-danger">Checkout</button>
            </td>
        </tr>
    </tfoot>
</table>
@endsection
   
@section('scripts')
<script type="text/javascript">
   
    $(".edit-cart-info").change(function (e) {
        e.preventDefault();
        var ele = $(this);
        $.ajax({
            url: '{{ route('update.sopping.cart') }}',
            method: "patch",
            data: {
                _token: '{{ csrf_token() }}', 
                id: ele.parents("tr").attr("rowId"), 
            },
            success: function (response) {
               window.location.reload();
            }
        });
    });
   
    $(".delete-product").click(function (e) {
        e.preventDefault();
   
        var ele = $(this);
   
        if(confirm("Do you really want to delete?")) {
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
   
</script>
@endsection --}}

@extends('shop')
   
@section('content')
<div class="container">
    <table id="cart" class="table table-bordered">
        <!-- Existing cart table code -->
    </table>

    <!-- M-Pesa Payment Modal -->
    <div class="modal fade" id="mpesaPaymentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Payment via M-Pesa</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="mpesaPaymentForm">
                        @csrf
                        <div class="form-group">
                            <label for="phone_number">M-Pesa Phone Number</label>
                            <input type="tel" class="form-control" id="phone_number" 
                                   placeholder="Enter your M-Pesa registered phone number" 
                                   pattern="^(?:254|\+254|0)?(7(?:(?:[0-9][0-9])|(?:0[0-9])|(?:1[0-9]))\d{6})$" 
                                   required>
                        </div>
                        <button type="submit" class="btn btn-success">Pay with M-Pesa</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
   
@section('scripts')
<script type="text/javascript">
    // Existing cart scripts...

    // M-Pesa Payment Script
    $(".btn-danger").click(function(e) {
        e.preventDefault();
        $('#mpesaPaymentModal').modal('show');
    });

    $("#mpesaPaymentForm").submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: '{{ route('mpesa.stk-push') }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                phone_number: $('#phone_number').val()
            },
            success: function(response) {
                if (response.success) {
                    alert('M-Pesa STK Push sent. Please complete payment on your phone.');
                    $('#mpesaPaymentModal').modal('hide');
                } else {
                    alert('Payment failed: ' + response.message);
                }
            },
            error: function(xhr) {
                alert('Error: ' + xhr.responseJSON.message);
            }
        });
    });
</script>
@endsection