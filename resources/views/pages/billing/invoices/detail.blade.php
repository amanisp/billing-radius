<div class="modal fade" id="invoiceDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('billing.create') }}" method="post">
                    @csrf
                    <div class="row">
                        <input readonly name="member_id" type="text" class="form-control d-none" id="member_id">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="subsperiode">Subscription Periode</label>
                                <select id="subsperiode" name="subsperiode" class="form-select">
                                    @for ($i = 1; $i <= 12; $i++)
                                        <option value="{{ $i }}">{{ $i }} Bulan</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="fullname">Fullname</label>
                                <div class="position-relative">
                                    <input readonly name="fullname" type="text" class="form-control" id="fullname">
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="duedate">Due Date</label>
                                <div class="position-relative">
                                    <input readonly name="duedate" type="date" class="form-control" id="duedate">
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="periode">Periode</label>
                                <div class="position-relative">
                                    <input name="periode" type="text" class="form-control" id="periode" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <label for="item">Item</label>
                                <div class="position-relative">
                                    <input name="item" type="text" class="form-control" id="items" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="amount">Amount</label>
                                        <div class="position-relative">
                                            <input name="amount" type="text" class="form-control" id="amount"
                                                readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="vat">PPN</label>
                                        <div class="position-relative">
                                            <input name="vat" type="text" class="form-control" id="vat"
                                                readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="disc">Discount</label>
                                        <div class="position-relative">
                                            <input name="disc" type="text" class="form-control" id="disc"
                                                readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-outline-success">Generate Invoice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
