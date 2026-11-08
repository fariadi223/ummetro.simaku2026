{{-- Kartu penggunaan per bidang (MAK 1). Parameter: $perBidang, $rp, $judulBidang --}}
<div class="card h-100">
          <div class="card-header">
            <h5 class="mb-0">Penggunaan per Bidang</h5>
            <small class="text-muted">{{ $judulBidang }}</small>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr><th>Bidang</th><th class="text-end">Kegiatan</th><th class="text-end">Pendapatan</th><th class="text-end">Belanja</th></tr>
              </thead>
              <tbody>
                @forelse ($perBidang as $b)
                  <tr>
                    <td style="white-space: normal;"><span class="badge bg-label-primary me-1">{{ $b->kodemak1 }}</span>{{ Helper::judulMak($b->namamak1) }}</td>
                    <td class="text-end">{{ $b->kegiatan }}</td>
                    <td class="text-end rp">{{ $rp($b->pendapatan) }}</td>
                    <td class="text-end rp">{{ $rp($b->belanja) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-center text-muted py-4">Belum ada rincian anggaran.</td></tr>
                @endforelse
              </tbody>
              @if ($perBidang->isNotEmpty())
                <tfoot>
                  <tr class="fw-semibold"><td>Total</td><td class="text-end">{{ $perBidang->sum('kegiatan') }}</td>
                    <td class="text-end rp">{{ $rp($perBidang->sum('pendapatan')) }}</td><td class="text-end rp">{{ $rp($perBidang->sum('belanja')) }}</td></tr>
                </tfoot>
              @endif
            </table>
          </div>
        </div>
