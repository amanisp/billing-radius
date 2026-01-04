<x-modal-form id="formEditModal" title="Edit Data POP" action="">
    @method('PUT') <!-- Tambahkan method PUT untuk update -->
    <div class="col-12 mb-3">
        <div class="col">
            <div class="form-group">
                <label for="edit-name">Nama</label>
                <input required name="name" type="text" class="form-control" id="edit-name">
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="ip-public">IP Publik</label>
                    <input required name="ip_public" type="text" class="form-control" id="ip-public">
                </div>
            </div>
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="edit-capacity">Kapasitas</label>
                    <input required name="capacity" type="number" class="form-control" id="edit-capacity">
                </div>
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-lg-6">
                <label for="edit-name">Area</label>
                <input required name="area_id" disabled type="text" class="form-control" id="edit-area">
            </div>
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="edit_device">Nama Perangkat</label>
                    <input required name="device_name" type="text" class="form-control" id="edit_device">
                </div>
            </div>
        </div>

        <input id="edit-lat" readonly name="lat" type="hidden"
            class="form-control @error('lat')is-invalid @enderror" value="{{ old('lat') }}">
        <input id="edit-lng" readonly name="lng" type="hidden"
            class="form-control @error('lng')is-invalid @enderror" value="{{ old('lng') }}">

        <div id="edit-maps"></div>

    </div>
</x-modal-form>
