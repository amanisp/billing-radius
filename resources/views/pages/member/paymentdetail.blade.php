{{-- Modal Payment Detail --}}
<div class="modal fade" id="paymentDetailModal" tabindex="-1" aria-labelledby="paymentDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentDetailModalLabel">Payment Detail - <span id="member-name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="FormPaymentDetail" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12">
                            <label for="payment_type">Billing Type</label>
                            <div class="form-group">
                                <select name="payment_type" id="payment_type" class="form-select" required>
                                    <option value="prabayar">PRABAYAR</option>
                                    <option value="pascabayar">PASCABAYAR</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="billing_period">Billing Period</label>
                            <div class="form-group">
                                <select name="billing_period" id="billing_period" class="form-select" required>
                                    <option value="fixed">Fixed Date</option>
                                    <option value="renewal">Renewal</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="active_date">Active Date</label>
                                <div class="position-relative">
                                    <input name="active_date" id="active_date" type="date" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="ppn">PPN %</label>
                                <div class="position-relative">
                                    <input name="ppn" type="number" class="form-control" id="ppn"
                                        min="0" max="100" step="0.01" placeholder="0">
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="discount">Discount</label>
                                <div class="position-relative">
                                    <input name="discount" type="number" class="form-control" id="discount"
                                        min="0" step="0.01" placeholder="0">
                                </div>
                            </div>
                        </div>
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
