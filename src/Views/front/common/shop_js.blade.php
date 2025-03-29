
<!--process cart-->
<script type="text/javascript">
  function addToCartAjax(id, instance = null, storeId = null){
    $.ajax({
        url: "{{ gp247_route_front('cart.add_ajax') }}",
        type: "POST",
        dataType: "JSON",
        data: {
          "id": id,
          "instance":instance,
          "storeId":storeId,
          "_token":"{{ csrf_token() }}"
        },
        async: false,
        success: function(data){
            error = parseInt(data.error);
            if(error ==0)
            {
              setTimeout(function () {
                if(data.instance =='default'){
                  $('.gp247-cart').html(data.count_cart);
                }else{
                  $('.gp247-'+data.instance).html(data.count_cart);
                }
              }, 1000);
              alertJs('success', data.msg);
            }else{
              if(data.redirect){
                window.location.replace(data.redirect);
                return;
              }
              alertJs('error', data.msg);
            }

            }
    });
  }
</script>
<!--//end cart -->