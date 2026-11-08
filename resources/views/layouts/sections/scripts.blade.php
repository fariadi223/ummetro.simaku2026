<!-- BEGIN: Vendor JS-->
<script src="{{ asset(mix('assets/vendor/libs/jquery/jquery.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/libs/popper/popper.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/js/bootstrap.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/libs/node-waves/node-waves.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/libs/hammer/hammer.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/libs/typeahead-js/typeahead.js')) }}"></script>
<script src="{{ asset(mix('assets/vendor/js/menu.js')) }}"></script>
@yield('vendor-script')
<!-- END: Page Vendor JS-->
<!-- BEGIN: Theme JS-->
<script src="{{ asset(mix('assets/js/main.js')) }}"></script>
<script src="{{ asset('js/config/pages_siakad.js')}}"></script>
<!-- END: Theme JS-->
<!-- Pricing Modal JS-->
@stack('pricing-script')
<!-- END: Pricing Modal JS-->
<!-- BEGIN: Page JS-->
@yield('page-script')
<!--
<script  type="text/javascript">
  var waConfig = {
    phone :" 6282180431681 ",
    call :"Message Us",
    position :"ww-right",
    size : "ww-normal",
    text : "",
    type: "ww-extended",
    brand: "UM METRO",
    subtitle: "",
    welcome: "Assalamualaikum.."
  };
  var proto = document.location.protocol, host = "cloudfront.net", url = proto + "//d3kzab8jj16n2f." + host;
    var s = document.createElement("script"); s.type = "text/javascript"; s.async = true; s.src = url + "/v2/main.js";

    s.onload = function () { tmWidgetInit(waConfig) };
    var x = document.getElementsByTagName("script")[0]; x.parentNode.insertBefore(s, x);
</script>
-->
<!-- END: Page JS-->
