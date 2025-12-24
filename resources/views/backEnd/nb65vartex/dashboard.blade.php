@extends('backEnd.layouts.master')
@section('title','General paymentcharge Manage')

@section('css')
<link href="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css" rel="stylesheet" type="text/css" />
<link href="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css" rel="stylesheet" type="text/css" />
<link href="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css" rel="stylesheet" type="text/css" />
<link href="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-select-bs5/css/select.bootstrap5.min.css" rel="stylesheet" type="text/css" />

    <style>
        a.canvasjs-chart-credit {
            display: none !important;
        }
        body {
        /*  background: linear-gradient(135deg, #f8f9fc 0%, #eef1f7 100%) !important;*/
         /* background: #0f111a !important;*/
        }

        .graph-pie {
            background: #fff;
            margin-bottom: 20px;
        }

        .des-item h5 {
            color: #979797;
        }

        .des-item h2 {
            font-weight: 800;
            color: #6a6a6a;
        }

        .chart-des {
            padding-top: 50px;
        }

        .inner-chart {
            position: absolute;
            top: 25%;
            left: 34%;
            opacity: 1;
            z-index: 9;
            text-align: center;
        }

        .inner-chart h5 {
            text-transform: capitalize;
        }

        .main-Pie {
            position: relative;
        }

        .ex-pro {
            margin-top: 14px;
            margin-left: 8px;
        }

       /* /////////////////// DASHBOARD SECTION START ////////////////////*/
       /* Dashboard Section */
    .school-admin-dashboard-section {
        padding: 40px 1%;
    }
.school-admin-dashboard-section h1 {
  text-align: center;
  margin-bottom: 50px;
  font-size: 2.5rem;
  font-weight: 700;
  color: #f5f5f5;
  letter-spacing: 1px;
  position: relative;
}

.school-admin-dashboard-section h1::after {
  content: '';
  width: 80px;
  height: 4px;
  background: linear-gradient(90deg, #00c6ff, #0072ff);
  position: absolute;
  bottom: -10px;
  left: 50%;
  transform: translateX(-50%);
  border-radius: 5px;
}

.sc-admin-dashboard {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 30px;
}

.sc-admin-card {
    background: rgb(255 255 255);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border-radius: 18px;
    padding: 25px 30px;
    display: flex;
    align-items: center;
    transition: all 0.35s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 25px rgb(166 166 166 / 12%), 0 4px 10px rgba(0, 0, 0, 0.05);
    /* border: 1px solid rgba(255, 255, 255, 0.35); */
}

.sc-admin-card:hover {
  transform: translateY(-6px);
  box-shadow: 
    0 18px 40px rgba(0, 0, 0, 0.18),
    0 6px 15px rgba(0, 0, 0, 0.08);
  
  background: rgba(255, 255, 255, 0.65); /* Slightly brighter on hover */
}

.sc-admin-card::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -100%;
  width: 200%;
  height: 200%;
  background: linear-gradient(
      120deg,
      rgba(255, 255, 255, 0.4),
      rgba(255, 255, 255, 0)
  );
  transition: all 0.7s ease;
}

.sc-admin-card:hover::before {
  left: 100%;
}




/*
.sc-admin-card {
  background: #1a1b2a;
  border-radius: 18px;
  padding: 25px 30px;
  display: flex;
  align-items: center;
  transition: all 0.35s ease;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.6);
}

.sc-admin-card:hover {
  transform: translateY(-8px);
  box-shadow: 0 15px 35px rgba(0, 0, 0, 0.8);
}

.sc-admin-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(120deg, rgba(255,255,255,0.08), rgba(255,255,255,0));
  transition: all 0.6s;
}

.sc-admin-card:hover::before {
  left: 100%;
}*/

.sc-admin-icon {
  width: 70px;
  height: 70px;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 30px;
  color: #fff;
  margin-right: 20px;
  flex-shrink: 0;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.4);
  background: linear-gradient(135deg, #0072ff, #00c6ff);
}

/* Individual Gradient Icons */
.bg-blue { background: linear-gradient(135deg, #0072ff, #00c6ff); }
.bg-green { background: linear-gradient(135deg, #00b894, #55efc4); }
.bg-purple { background: linear-gradient(135deg, #6c5ce7, #a29bfe); }
.bg-orange { background: linear-gradient(135deg, #fd7e14, #fdcb6e); }
.bg-red { background: linear-gradient(135deg, #d63031, #ff6b6b); }
.bg-pink { background: linear-gradient(135deg, #e84393, #fd79a8); }
.bg-teal { background: linear-gradient(135deg, #00cec9, #81ecec); }
.bg-indigo { background: linear-gradient(135deg, #6c5ce7, #341f97); }
.bg-cyan { background: linear-gradient(135deg, #00bcd4, #00acc1); }
.bg-gray { background: linear-gradient(135deg, #636e72, #2d3436); }

.school-admin-details h3 {
  margin: 0;
  font-size: 1.8rem;
  font-weight: 700;
}

.school-admin-details p {
  margin: 6px 0 0;
  font-size: 1rem;
  font-weight: 500;
}


.uniq-design-admin {
    padding: 40px 30px 0px 30px;
}
#TodaychartContainer, #MonthchartContainer, #YearchartContainer {
    background: #1a1b2a !important;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.6);
    overflow: hidden;
    border-radius: 18px;

}




a { text-decoration: none; }

@media (max-width: 600px) {
  .sc-admin-card {
    flex-direction: column;
    text-align: center;
    padding: 20px;
  }
  .sc-admin-icon {
    margin-right: 0;
    margin-bottom: 12px;
  }
}/* Dashboard Section */


       /* /////////////////// DASHBOARD SECTION END ////////////////////*/
        </style>

@endsection
@section('content')
    <!-- Start Content-->
    <div class="container-fluid">
        

        <section class="school-admin-dashboard-section">
            <div class="sc-admin-dashboard">

              <a href="#"><div class="sc-admin-card">
                  <div class="sc-admin-icon bg-blue"><i data-feather="briefcase"></i>  </div>
                  <div class="school-admin-details">
                    <h3> {{ $total_balance }} </h3>
                    <p>All Balance</p>
                  </div>
              </div></a>

              <a href="#"><div class="sc-admin-card">
                  <div class="sc-admin-icon bg-green"><i data-feather="printer"></i></div>
                  <div class="school-admin-details">
                    <h3> {{ $total_cash }} </h3>
                    <p>Total Cash</p>
                  </div>
              </div></a>

              <a href="#"><div class="sc-admin-card">
                  <div class="sc-admin-icon bg-purple"><i data-feather="download-cloud"></i></div>
                  <div class="school-admin-details">
                    <h3> {{ $total_deposit }} </h3>
                    <p>Total Deposit</p>
                  </div>
              </div></a>

              <a href="#"><div class="sc-admin-card">
                  <div class="sc-admin-icon bg-orange"><i data-feather="upload-cloud"></i></div>
                  <div class="school-admin-details">
                    <h3> {{ $total_withdraw }} </h3>
                    <p>Total Withdraw</p>
                  </div>
              </div></a>

              <!-- <a href="#"><div class="sc-admin-card">
                  <div class="sc-admin-icon bg-teal"><i class="fa-solid fa-money-check-dollar"></i></div>
                  <div class="school-admin-details">
                    <h3>10</h3>
                    <p>Complete Fee</p>
                  </div>
              </div></a>

              <a href="#"><div class="sc-admin-card">
                  <div class="sc-admin-icon bg-red"><i class="fa-solid fa-circle-exclamation"></i></div>
                  <div class="school-admin-details">
                    <h3>1000</h3>
                    <p>Due Fee</p>
                  </div>
              </div></a>

              <a href="#"><div class="sc-admin-card">
                  <div class="sc-admin-icon bg-indigo"><i class="fa-solid fa-book-open"></i></div>
                  <div class="school-admin-details">
                    <h3>20</h3>
                    <p>Subjects</p>
                  </div>
              </div></a>

              <a href="#"><div class="sc-admin-card">
                  <div class="sc-admin-icon bg-pink"><i class="fa-solid fa-calculator"></i></div>
                  <div class="school-admin-details">
                    <h3>10</h3>
                    <p>Total Exams</p>
                  </div>
              </div></a> -->

              <!-- <a href="#"><div class="sc-admin-card">
                  <div class="sc-admin-icon bg-cyan"><i class="fa-solid fa-clock"></i></div>
                  <div class="school-admin-details">
                    <h3>95%</h3>
                    <p>Attendance</p>
                  </div>
              </div></a>

              <a href="#"><div class="sc-admin-card">
                  <div class="sc-admin-icon bg-gray"><i class="fa-solid fa-calendar-check"></i></div>
                  <div class="school-admin-details">
                    <h3>8</h3>
                    <p>Events</p>
                  </div>
              </div></a> -->

            </div>
        </section>



    </div> <!-- container -->
    @endsection

@section('script')
<!-- third party js -->
<script src="{{asset('/public/backEnd/')}}/assets/libs/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
<script src="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-responsive/js/dataTables.responsive.min.js"></script>
<script src="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js"></script>
<script src="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-buttons/js/dataTables.buttons.min.js"></script>
<script src="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-buttons-bs5/js/buttons.bootstrap5.min.js"></script>
<script src="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-buttons/js/buttons.html5.min.js"></script>
<script src="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-buttons/js/buttons.flash.min.js"></script>
<script src="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-buttons/js/buttons.print.min.js"></script>
<script src="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-keytable/js/dataTables.keyTable.min.js"></script>
<script src="{{asset('/public/backEnd/')}}/assets/libs/datatables.net-select/js/dataTables.select.min.js"></script>
<script src="{{asset('/public/backEnd/')}}/assets/libs/pdfmake/build/pdfmake.min.js"></script>
<script src="{{asset('/public/backEnd/')}}/assets/libs/pdfmake/build/vfs_fonts.js"></script>
<script src="{{asset('/public/backEnd/')}}/assets/js/pages/datatables.init.js"></script>
<!-- third party js ends -->
@endsection
