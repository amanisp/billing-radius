<x-modal-form id="formCreateModal" title="Tambah Data ODP/ODC" action="{{ route('optical.store') }}">
    <div class="col-12 mb-3">
        <div class="row">
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="name">Nama</label>
                    <div class="position-relative">
                        <input required name="name" type="text"
                            class="form-control @error('name')is-invalid @enderror" value="{{ old('name') }}"
                            placeholder="Nama POP/ODP/ODC" id="name">
                        @error('name')
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
                    <select name="area_id" class="form-select ">
                        @foreach ($area as $areas)
                            <option value="{{ $areas->id }}">{{ $areas->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-lg-6">
                <label for="type">Type</label>
                <div class="form-group">
                    <select name="type" class="form-select ">
                        <option disabled selected>Pilih Salah Satu</option>
                        <option value="ODP">ODP</option>
                        <option value="ODC">ODC</option>
                    </select>
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
