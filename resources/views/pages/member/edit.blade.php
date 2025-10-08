<div class="modal fade" id="formEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5">Edit Member</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            @csrf
            <form id="FormEdit" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="edit-name">Nama</label>
                                <div class="position-relative">
                                    <input name="fullname" type="text" class="form-control" placeholder="Jhon Die"
                                        id="edit-fullname">
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="edit-phone">Nomor HP <small class="text-warning">Optional</small></label>
                                <div class="position-relative">
                                    <input name="phone_number" type="number" class="form-control" id="edit-phone_number">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="edit-email">Email <small class="text-warning">Optional</small></label>
                                <div class="position-relative">
                                    <input name="email" type="email" class="form-control" id="edit-email">
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="edit-nik">NIK <small class="text-warning">Optional</small></label>
                                <div class="position-relative">
                                    <input name="id_card" type="text" class="form-control" id="edit-id_card">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-12">
                        <div class="form-group">
                            <label for="edit-address">Alamat <small class="text-warning">Optional</small></label>
                            <div class="position-relative">
                                <textarea class="form-control" name="address" id="edit-address"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
