  <div class="ttd">
    <div>
      Metro, {{ now()->locale('id')->translatedFormat('d F Y') }}<br>
      {{ $unit->nama }}
      <div class="nama">{{ $unit->nama_pimp }}</div>
      @if ($unit->nip)<div>NIP. {{ $unit->nip }}</div>@endif
    </div>
  </div>
</div>
<script>
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
</script>
</body>
</html>
