<div class="modal fade" id="formEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5">Tambah PPPoE</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="FormEdit" method="POST">
                @method('PUT')
                @csrf
                <div class="modal-body">
                    <div class="col-12 mb-3">
                        <div class="form-group">
                            <label for="edit-name">Nama</label>
                            <div class="position-relative">
                                <input disabled name="name" id="edit-name" type="text" class="form-control"
                                    value="{{ old('name') }}" placeholder="Nama Mitra" id="area-type">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="edit-email">Email</label>
                                    <div class="position-relative">
                                        <input disabled name="email" id="edit-email" type="email"
                                            class="form-control" value="{{ old('email') }}" id="email">
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="phone_number">Nomor HP</label>
                                    <div class="position-relative">
                                        <input disabled id="edit-phone" name="edit-phone" type="number"
                                            class="form-control" value="{{ old('phone_number') }}" id="phone_number">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-12">
                                <label for="transmitter">Transmitter</label>
                                <div class="form-group">
                                    <select id="edit-transmit" name="transmitter" class="form-select">
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
                                    <label for="capacity">Kapasitas Internet <smaal class="text-warning">Mbps</smaal>
                                    </label>
                                    <div class="position-relative">
                                        <input id="edit-capacity" name="capacity" type="number" class="form-control "
                                            value="{{ old('capacity') }}" id="capacity">
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="price">Harga</label>
                                    <div class="position-relative">
                                        <input id="edit-price" name="price" type="text" class="form-control"
                                            value="{{ old('price') }}" id="price"
                                            oninput="formatRupiah(this); calculateTotal();">
                                    </div>
                                </div>
                            </div>
                        </div>
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
                                        <input name="kso" type="checkbox" id="kso"
                                            class="form-check-input" onclick="calculateTotal()">
                                        <label for="kso">Beban KSO (3.25%)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-lg-12 text-end">
                                <span id="total_price_text" class="fw-bold text-success"
                                    style="font-size: 1.2rem;">Total: Rp
                                    0</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger rounded-pill"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
