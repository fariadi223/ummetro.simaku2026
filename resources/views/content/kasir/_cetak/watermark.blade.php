{{-- Watermark "COPY" untuk cetak ulang BKK (v1: copy_stamp pada bkk_cp). Sertakan di dalam .kertas. --}}
<style>
  .kertas { position: relative; }
  .wm-copy { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; overflow: hidden; z-index: 5; }
  .wm-copy span { transform: rotate(-32deg); font: 800 120pt/1 Arial, Helvetica, sans-serif; letter-spacing: .12em; color: rgba(200, 30, 30, .13);
                  border: 10px solid rgba(200, 30, 30, .13); border-radius: 24px; padding: 4px 36px; white-space: nowrap; }
  @media print {
    .wm-copy { position: fixed; }
    .wm-copy span { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
<div class="wm-copy" aria-hidden="true"><span>COPY</span></div>
