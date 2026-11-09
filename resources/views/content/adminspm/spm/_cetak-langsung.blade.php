{{--
  Tautan <a data-cetak-langsung href="…/cetak"> langsung membuka dialog print: lembar dimuat di iframe
  tersembunyi dengan ?cetak=1 (lembar memanggil window.print() sendiri). Ctrl/klik tengah tetap membuka
  lembar di tab baru seperti biasa.
--}}
<script>
  (function () {
    var bingkai = null;

    document.addEventListener('click', function (e) {
      var a = e.target.closest('a[data-cetak-langsung]');
      if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
      e.preventDefault();

      if (!bingkai) {
        bingkai = document.createElement('iframe');
        bingkai.setAttribute('aria-hidden', 'true');
        bingkai.style.cssText = 'position:fixed;width:0;height:0;border:0;right:0;bottom:0;visibility:hidden;';
        document.body.appendChild(bingkai);
      }

      a.classList.add('disabled');
      bingkai.onload = function () { a.classList.remove('disabled'); };
      bingkai.src = a.href + (a.href.indexOf('?') === -1 ? '?' : '&') + 'cetak=1&_=' + Date.now();
    });
  })();
</script>
