<x-modal-form id="formCreateModal" title="Tambah Data POP" action="{{ route('optical.store') }}">
    <div class="col-12 mb-3">
        <div class="col">
            <div class="form-group">
                <label for="name">Nama</label>
                <div class="position-relative">
                    <input required name="name" type="text" class="form-control @error('name')is-invalid @enderror"
                        value="{{ old('name') }}" placeholder="Nama POP/ODP/ODC" id="name">
                    @error('name')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="ip_public">IP Publik</label>
                    <div class="position-relative">
                        <input name="ip_public" type="text"
                            class="form-control @error('ip_public')is-invalid @enderror" value="{{ old('ip_public') }}"
                            placeholder="103.xxx.xxx.xxx" id="ip_public">
                        @error('ip_public')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="capasity">Kapasitas</label>
                    <div class="position-relative">
                        <input name="capacity" type="number"
                            class="form-control @error('capacity')is-invalid @enderror" value="{{ old('capacity') }}"
                            placeholder="Example: 8" id="capasity">
                        @error('capacity')
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
                    <select class="form-select" name="area_id">
                        @foreach ($area as $areas)
                            <option value="{{ $areas->id }}">{{ $areas->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="device_name">Nama Perangkat</label>
                    <div class="position-relative">
                        <input name="device_name" type="text"
                            class="form-control @error('device_name')is-invalid @enderror"
                            value="{{ old('device_name') }}" placeholder="Mikrotik/Cisco/Juniper" id="device_name">
                        @error('device_name')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
        <input id="lat" readonly name="lat" type="hidden"
            class="form-control @error('lat')is-invalid @enderror" value="{{ old('lat') }}">
        <input id="lng" readonly name="lng" type="hidden"
            class="form-control @error('lng')is-invalid @enderror" value="{{ old('lng') }}">
        <div id="map"></div>
    </div>
</x-modal-form>
