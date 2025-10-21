{{-- Modal Payment Detail --}}
<div class="modal fade" id="paymentDetailModal" tabindex="-1" aria-labelledby="paymentDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentDetailModalLabel">Payment Detail - <span id="member-name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="FormPaymentDetail" method="POST">
                @csrf
                <input type="hidden" name="_method" value="PUT">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="payment_type" class="form-label">Tipe Pembayaran <span
                                        class="text-danger">*</span></label>
                                <select class="form-select" id="payment_type" name="payment_type" required>
                                    <option value="">Pilih Tipe</option>
                                    <option value="prabayar">Prabayar</option>
                                    <option value="pascabayar">Pascabayar</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="billing_period" class="form-label">Periode Tagihan (bulan) <span
                                        class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="billing_period" name="billing_period"
                                    min="1" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="active_date" class="form-label">Tanggal Aktif <span
                                        class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="active_date" name="active_date" required>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="last_invoice" class="form-label">Tanggal Invoice Terakhir</label>
                            <input type="date" class="form-control" id="last_invoice" value=""
                                readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group mb-3">
                                <label for="amount" class="form-label">Jumlah (Rp) <span
                                        class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="amount" name="amount" min="0"
                                    step="1000" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="discount" class="form-label">Diskon (%)</label>
                                <input type="number" class="form-control" id="discount" name="discount" min="0"
                                    max="100" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="ppn" class="form-label">PPN (%)</label>
                                <input type="number" class="form-control" id="ppn" name="ppn" min="0"
                                    max="100" step="0.01" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3">
                        <strong>Total Estimasi:</strong>
                        <p class="mb-0" id="total-estimate">Rp 0</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
