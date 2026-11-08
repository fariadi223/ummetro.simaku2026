{{--
  Satu baris akun di tabel berjenjang.
  Parameter: $b (baris dari controller), $lvl (kedalaman 1/2), $grup (kunci kelompok),
             $simpul (kunci unit), $warnaRole, $idSaya.
--}}
@php
  $u = $b->user;
  $tingkat = collect($b->peringatan)->contains(fn ($p) => $p[0] === 'danger') ? 'danger' : (count($b->peringatan) ? 'warning' : '');
  $cari = mb_strtolower("{$u->username} {$u->nama_lengkap} {$b->label} " . ($u->unit ? "{$u->unit->kode} {$u->unit->nama}" : 'universitas'));
  $dataUbah = ['id_unit' => (int) $u->id_unit, 'nama_lengkap' => $u->nama_lengkap, 'username' => $u->username, 'wewenang' => $u->wewenang];
@endphp
<tr class="baris-user lvl-{{ $lvl }}" data-grup="{{ $grup }}" data-simpul="{{ $simpul }}"
    data-cari="{{ $cari }}" data-role="{{ $b->efektif }}" data-masalah="{{ $tingkat }}">
  <td class="nama">
    <div class="d-flex align-items-center gap-2 cabang">
      <span class="user-avatar">{{ strtoupper(mb_substr($u->nama_lengkap ?: $u->username, 0, 2)) }}</span>
      <div>
        <span class="font-monospace fw-semibold">{{ $u->username }}</span>
        @if ($u->idlog === $idSaya)<span class="badge bg-label-success ms-1">Anda</span>@endif
        <div class="small text-muted">{{ $u->nama_lengkap ?: '—' }}</div>
      </div>
    </div>
  </td>
  <td>
    <span class="badge bg-label-{{ $warnaRole[$b->efektif] ?? 'secondary' }}">{{ $b->label }}</span>
    @foreach ($b->peringatan as [$jenis, $pesan])
      <i class="ti {{ $jenis === 'danger' ? 'ti-alert-octagon text-danger' : 'ti-alert-triangle text-warning' }} ms-1"
         data-bs-toggle="tooltip" title="{{ $pesan }}"></i>
    @endforeach
  </td>
  <td class="pw-sel">
    <span class="font-monospace text-muted pw-teks">••••••</span>
    <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-lihat"
            data-url="{{ route('admin.user.lihat-password', $u) }}" title="Lihat password">
      <i class="ti ti-eye"></i>
    </button>
  </td>
  <td class="text-end">
    <div class="d-inline-flex gap-1">
      <button type="button" class="btn btn-sm btn-icon btn-text-warning rounded-pill btn-ubah" title="Ubah"
              data-url="{{ route('admin.user.update', $u) }}" data-user='@json($dataUbah)'
              data-akun-role="{{ $u->isAkunRole() ? 1 : 0 }}">
        <i class="ti ti-pencil"></i>
      </button>
      <button type="button" class="btn btn-sm btn-icon btn-text-info rounded-pill btn-password" title="Ganti password"
              data-url="{{ route('admin.user.password', $u) }}" data-label="{{ $u->username }} ({{ $b->label }})"
              @disabled($u->isAkunRole())>
        <i class="ti ti-key"></i>
      </button>
      @if ($u->idlog !== $idSaya)
        <form method="POST" action="{{ route('admin.user.destroy', $u) }}" class="form-hapus"
              data-label="{{ $u->username }} ({{ $b->label }})">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Hapus">
            <i class="ti ti-trash"></i>
          </button>
        </form>
      @endif
    </div>
  </td>
</tr>
