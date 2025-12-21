@extends('backEnd.layouts.master')
@section('title', 'All Payment History')
@section('css')
    <link href="{{ asset('/public/backEnd/') }}/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css"
        rel="stylesheet" type="text/css" />
    <link href="{{ asset('/public/backEnd/') }}/assets/libs/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css"
        rel="stylesheet" type="text/css" />
    <link href="{{ asset('/public/backEnd/') }}/assets/libs/datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css"
        rel="stylesheet" type="text/css" />
    <link href="{{ asset('/public/backEnd/') }}/assets/libs/datatables.net-select-bs5/css/select.bootstrap5.min.css"
        rel="stylesheet" type="text/css" />

    <style>
        /* প্রিমিয়াম স্ট্যাটাস ব্যাজ ডিজাইন */
        .status-badge {
            padding: 5px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-transform: capitalize;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        /* Credit - Premium Green */
        /* .badge-credit {
            background-color: #e6fcf5; 
            color: #0ca678;           
            border: 1px solid #c3fae8;
        } */
        
        .badge-credit {
            background-color: #0ca678;
            color: white;
            box-shadow: 0 4px 10px rgba(12, 166, 120, 0.3);
        }

        /* Debit - Premium Red */
        /* .badge-debit {
            background-color: #fff5f5; 
            color: #fa5252;          
            border: 1px solid #ffe3e3;
        } */

        .badge-debit {
            background-color: #fa5252;
            color: white;
            box-shadow: 0 4px 10px rgba(250, 82, 82, 0.3);
        }

        /* আইকন এনিমেশন (অপশনাল) */
        .status-badge i {
            font-size: 11px;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid">

        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <h4 class="page-title">Payment History</h4>
                </div>
            </div>
        </div>
        <!-- end page title -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <table id="datatable-buttons" class="table table-striped dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>SL</th>
                                    <th>Member</th>
                                    <th>User Name</th>
                                    <th>Payment Name</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Type</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($data as $key => $value)
                                    <tr>
                                        <td>
                                            {{ $loop->iteration }}
                                        </td>

                                        <td>
                                            {{ $value->member->name }}
                                        </td>

                                        <td>
                                            {{ $value->member->username }}
                                        </td>

                                        <td>
                                            {{ $value->payment_name }}
                                        </td>

                                        <td>
                                            {{ $value->amount }}
                                        </td>

                                        <td>
                                            {{ $value->method }}
                                        </td>

                                        <td>
                                            @if ($value->type == 'credit')
                                                <span class="status-badge badge-credit">
                                                    <i class="fas fa-plus-circle"></i> Credit
                                                </span>
                                            @else
                                                <span class="status-badge badge-debit">
                                                    <i class="fas fa-minus-circle"></i> Debit
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            
                        </table>

                    </div> <!-- end card body-->
                </div> <!-- end card -->
            </div><!-- end col-->
        </div>
    </div>
@endsection


@section('script')
    <!-- third party js -->
    <script src="{{ asset('/public/backEnd/') }}/assets/libs/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="{{ asset('/public/backEnd/') }}/assets/libs/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script src="{{ asset('/public/backEnd/') }}/assets/libs/datatables.net-responsive/js/dataTables.responsive.min.js">
    </script>
    <script
        src="{{ asset('/public/backEnd/') }}/assets/libs/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js">
    </script>
    <script src="{{ asset('/public/backEnd/') }}/assets/libs/datatables.net-buttons/js/dataTables.buttons.min.js"></script>
    <script src="{{ asset('/public/backEnd/') }}/assets/libs/datatables.net-buttons-bs5/js/buttons.bootstrap5.min.js">
    </script>
    <script src="{{ asset('/public/backEnd/') }}/assets/libs/datatables.net-buttons/js/buttons.html5.min.js"></script>
    <script src="{{ asset('/public/backEnd/') }}/assets/libs/datatables.net-buttons/js/buttons.flash.min.js"></script>
    <script src="{{ asset('/public/backEnd/') }}/assets/libs/datatables.net-buttons/js/buttons.print.min.js"></script>
    <script src="{{ asset('/public/backEnd/') }}/assets/libs/datatables.net-keytable/js/dataTables.keyTable.min.js">
    </script>
    <script src="{{ asset('/public/backEnd/') }}/assets/libs/datatables.net-select/js/dataTables.select.min.js"></script>
    <script src="{{ asset('/public/backEnd/') }}/assets/libs/pdfmake/build/pdfmake.min.js"></script>
    <script src="{{ asset('/public/backEnd/') }}/assets/libs/pdfmake/build/vfs_fonts.js"></script>
    <script src="{{ asset('/public/backEnd/') }}/assets/js/pages/datatables.init.js"></script>
    <!-- third party js ends -->
@endsection
