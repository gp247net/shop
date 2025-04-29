@php
        //Order in 30 days
        $totalsInMonth = \GP247\Shop\Admin\Models\AdminOrder::getSumOrderTotalInMonth()->keyBy('md')->toArray();
        $rangDays = new \DatePeriod(
            new \DateTime('-1 month'),
            new \DateInterval('P1D'),
            new \DateTime('+1 day')
        );
        $orderInMonth  = [];
        $amountInMonth  = [];
        foreach ($rangDays as $i => $day) {
            $date = $day->format('m-d');
            $orderInMonth[$date] = (float)($totalsInMonth[$date]['total_order'] ?? '');
            $amountInMonth[$date] = (float)($totalsInMonth[$date]['total_amount'] ?? 0);
        }

        //Order in 12 months
        $totalMonth = \GP247\Shop\Admin\Models\AdminOrder::getSumOrderTotalInYear()
            ->pluck('total_amount', 'ym')->toArray();
        $dataInYear = [];
        for ($i = 12; $i >= 0; $i--) {
            $date = date("Y-m", strtotime(date('Y-m-01') . " -$i months"));
            $dataInYear[$date] = (float)($totalMonth[$date] ?? 0);
        }
        //End order in 12 months

@endphp
<div class="card">
  <div class="card-header">
    <h5 class="card-title">{{ gp247_language_render('admin.dashboard.order_month') }}</h5>

    <div class="card-tools">
      <button type="button" class="btn btn-tool" data-card-widget="collapse">
        <i class="fas fa-minus"></i>
      </button>
      <button type="button" class="btn btn-tool" data-card-widget="remove">
        <i class="fas fa-times"></i>
      </button>
    </div>
  </div>
  <!-- /.card-header -->
  <div class="card-body">
    <div class="row">
      <div class="col-md-12">
        <div id="chart-month" style="width:100%; height:auto;"></div>
      </div>
    </div>
    <!-- /.row -->
  </div>
  <!-- ./card-body -->
  <!-- /.card-footer -->
</div>
<!-- /.card -->

<div class="card">
<div class="card-header">
  <h5 class="card-title">{{ gp247_language_render('admin.dashboard.order_year') }}</h5>

  <div class="card-tools">
    <button type="button" class="btn btn-tool" data-card-widget="collapse">
      <i class="fas fa-minus"></i>
    </button>
    <button type="button" class="btn btn-tool" data-card-widget="remove">
      <i class="fas fa-times"></i>
    </button>
  </div>
</div>
<!-- /.card-header -->
<div class="card-body">
  <div class="row">

    <div class="col-md-12">
      <div id="chart-year" style="width:100%; height:auto;"></div>
    </div>
  </div>
  <!-- /.row -->
</div>
<!-- ./card-body -->
</div>
<!-- /.card -->


@push('scripts')
  <script src="{{ gp247_file('GP247/Core/plugin/chartjs/highcharts.js') }}"></script>
  <script src="{{ gp247_file('GP247/Core/plugin/chartjs/highcharts-3d.js') }}"></script>
  <script type="text/javascript">
  document.addEventListener('DOMContentLoaded', function () {
      var myChart = Highcharts.chart('chart-month', {
          credits: {
              enabled: false
          },
          title: {
              text: '{{ gp247_language_render('admin.dashboard.order_month') }}'
          },
          xAxis: {
              categories: {!! json_encode(array_keys($orderInMonth)) !!},
              crosshair: false

          },

          yAxis: [{
              min: 0,
              title: {
                  text: '{{ gp247_language_render('admin.dashboard.order') }}'
              },
          }, {
              title: {
                  text: '{{ gp247_language_render('admin.dashboard.amount') }}'
              },
              opposite: true
          },
          ],

          legend: {
                align: 'left',
                verticalAlign: 'top',
                borderWidth: 0
            },

          tooltip: {
              headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
              pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
                  '<td style="padding:0"><b>{point.y:.0f} </b></td></tr>',
              footerFormat: '</table>',
              shared: true,
              useHTML: true
          },
          plotOptions: {
            column: {
                      pointPadding: 0.2,
                      borderWidth: 0
                  },
          },

          series: [
          {
              type: 'column',
              name: '{{ gp247_language_render('admin.dashboard.order') }}',
              data: {!! json_encode(array_values($orderInMonth)) !!},
              dataLabels: {
                  enabled: true,
                  format: '{point.y:.0f}'
              }
          },
          {
              type: 'line',
              name: '{{ gp247_language_render('admin.dashboard.amount') }}',
              color: '#c7730c',
              yAxis: 1,
              data: {!! json_encode(array_values($amountInMonth)) !!},
              borderWidth: 0,
              dataLabels: {
                  enabled: true,
                  borderRadius: 3,
                  backgroundColor: 'rgba(252, 255, 197, 0.7)',
                  borderWidth: 0.5,
                  borderColor: '#AAA',
                  y: -6
              }
          },
        ]
      });
  });



// Set up the chart
var chart = new Highcharts.Chart({
    chart: {
        renderTo: 'chart-year',
        type: 'column',
        options3d: {
            enabled: true,
            alpha: 0,
            beta: 10,
            depth: 50,
            viewDistance: 25
        }
    },
    title: {
        text: '{{ gp247_language_render('admin.dashboard.order_year') }}'
    },
    legend: {
            enabled: false,
      },
    credits: {
              enabled: false
          },
    xAxis: {
        categories: {!! json_encode(array_keys($dataInYear)) !!},
        crosshair: false,
    },
    yAxis: [
            {
                min: 0,
                title: {
                    text: '{{ gp247_language_render('admin.dashboard.amount') }}'
                },
            }
          ],
    plotOptions: {
        column: {
            depth: 25
        },
        series: {
            dataLabels: {
                enabled: true,
                borderRadius: 3,
                backgroundColor: 'rgba(252, 255, 197, 0.7)',
                borderWidth: 0.5,
                borderColor: '#AAA',
                y: -6
            }
        }
    },
    series: [
      {
        name : '{{ gp247_language_render('admin.dashboard.amount') }}',
        data: {!! json_encode(array_values($dataInYear)) !!},
      },
      {
          type : 'line',
          color: '#d05135',
          name : '{{ gp247_language_render('admin.dashboard.amount') }}',
          data: {!! json_encode(array_values($dataInYear)) !!}
      }
  ]
});

function showValues() {
    $('#alpha-value').html(chart.options.chart.options3d.alpha);
    $('#beta-value').html(chart.options.chart.options3d.beta);
    $('#depth-value').html(chart.options.chart.options3d.depth);
}

// Activate the sliders
$('#sliders input').on('input change', function () {
    chart.options.chart.options3d[this.id] = parseFloat(this.value);
    showValues();
    chart.redraw(false);
});

showValues();
</script>

@endpush