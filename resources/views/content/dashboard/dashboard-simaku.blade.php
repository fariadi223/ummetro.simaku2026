@extends('layouts/layoutMaster')

@section('title', 'Dashboard')

@section('content')
<div class="card">
  <div class="card-body">
    <h4 class="mb-1">Selamat datang, {{ $user->name }}</h4>
    <p class="text-muted mb-4">SIMAKU &mdash; Sistem Informasi Manajemen dan Akuntansi Keuangan</p>

    <dl class="row mb-0">
      <dt class="col-sm-3">Wewenang</dt>
      <dd class="col-sm-9">{{ $user->roleLabel() }}</dd>

      <dt class="col-sm-3">Unit</dt>
      <dd class="col-sm-9">{{ $unit ? $unit->kode . ' - ' . $unit->nama : '-' }}</dd>

      <dt class="col-sm-3">Periode</dt>
      <dd class="col-sm-9">{{ $periode }}</dd>
    </dl>
  </div>
</div>
@endsection
