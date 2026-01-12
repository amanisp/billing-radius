<x-modal-form id="formCreateModal" title="Tambah Data Pelanggan" action="{{ route('mitra.store') }}">
    <div class="col-12 mb-3">
        <div class="row">
            <div class="col-lg-6">

                <div class="form-group">
                    <label for="area-name">Nama</label>
                    <div class="position-relative">
                        <input name="name" type="text" class="form-control @error('name')is-invalid @enderror"
                            value="{{ old('name') }}" placeholder="Nama" id="area-type">
                        @error('name')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <label for="segmentasi">Segmentasi</label>
                <div class="form-group">
                    <select name="segmentasi" class="form-select">
                        <option value="C">CORPORATE</option>
                        <option value="P">POP/MITRA</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="position-relative">
                        <input name="email" type="email" class="form-control @error('email')is-invalid @enderror"
                            value="{{ old('email') }}" id="email">
                        @error('email')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="phone_number">Nomor HP</label>
                    <div class="position-relative">
                        <input name="phone_number" type="number"
                            class="form-control @error('phone_number')is-invalid @enderror"
                            value="{{ old('phone_number') }}" id="phone_number">
                        @error('phone_number')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="nik">NIK</label>
                    <div class="position-relative">
                        <input name="nik" type="number" class="form-control @error('nik')is-invalid @enderror"
                            value="{{ old('nik') }}" id="nik">
                        @error('nik')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="address">Alamat</label>
                    <div class="position-relative">
                        <input name="address" type="text" class="form-control @error('address')is-invalid @enderror"
                            value="{{ old('address') }}" id="address">
                        @error('address')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <label for="area_id">Area</label>
                <div class="form-group">
                    <select name="area_id" id="selectArea" class="form-select">
                        @foreach ($area as $areas)
                            <option value="{{ $areas->id }}">{{ $areas->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-lg-6">
                <label for="pop_id">POP</label>
                <div class="form-group">
                    <select name="pop_id" id="selectPop" class="form-select">
                        @foreach ($pop as $pops)
                            <option value="{{ $pops->id }}">{{ $pops->name }}
                                ({{ $pops->device_name }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="active_date">Tanggal Aktif</label>
                    <div class="position-relative">
                        <input required name="active_date" type="date"
                            class="form-control @error('active_date')is-invalid @enderror"
                            value="{{ old('active_date') }}" id="active_date">
                        @error('active_date')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <label for="transmitter">Transmitter</label>
                <div class="form-group">
                    <select name="transmitter" class="form-select">
                        <option value="Wireless">Wireless</option>
                        <option value="Fiber Optic">Fiber Optic</option>
                        <option value="SFP">SFP</option>
                        <option value="SFP+">SFP+</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="capacity">Kapasitas Internet <smaal class="text-warning">Mbps</smaal></label>
                    <div class="position-relative">
                        <input name="capacity" type="number"
                            class="form-control @error('capacity')is-invalid @enderror" value="{{ old('capacity') }}"
                            id="capacity">
                        @error('capacity')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="price">Harga</label>
                    <div class="position-relative">
                        <input name="price" type="text" class="form-control @error('price')is-invalid @enderror"
                            value="{{ old('price') }}" id="price"
                            oninput="formatRupiah(this); calculateTotal();">
                        @error('price')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
        <hr>
        <div class="row mt-2">
            <div class="col-lg-4">
                <div class="form-check">
                    <div class="checkbox">
                        <input name="ppn" type="checkbox" id="ppn" class="form-check-input"
                            onclick="calculateTotal()">
                        <label for="ppn">PPN (11%)</label>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="form-check">
                    <div class="checkbox">
                        <input name="bhpuso" type="checkbox" id="bhpuso" class="form-check-input"
                            onclick="calculateTotal()">
                        <label for="bhpuso">Pajak BHP, USO (1.75%)</label>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="form-check">
                    <div class="checkbox">
                        <input name="kso" type="checkbox" id="kso" class="form-check-input"
                            onclick="calculateTotal()">
                        <label for="kso">Beban KSO (3.25%)</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-lg-12 text-end">
                <span id="total_price_text" class="fw-bold text-success" style="font-size: 1.2rem;">Total: Rp
                    0</span>
            </div>
        </div>
    </div>
</x-modal-form>
