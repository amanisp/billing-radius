<div class="modal fade" id="modalExcel" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="exampleModalLabel">Import Excel</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="importExcel" enctype="multipart/form-data" class="mb-3" method="POST">
                    @csrf
                    <div class="input-group">
                        <input type="file" name="file" class="form-control" id="inputGroupFile04"
                            aria-describedby="inputGroupFileAddon04" aria-label="Upload">
                        <button class="btn btn-outline-primary" type="submit"
                            id="inputGroupFileAddon04">Upload</button>
                    </div>
                </form>
                <span class="text-secondary">Silahkan download format file excel untuk import data
                    PPPoE.</span>
                <a href="{{ asset('xlsx/template_import_pppoe.xlsx') }}"
                    class="btn btn-outline-success btn-sm mt-2">Download</a>
                <hr>
                <h6 class="">LOG</h6>
                <div id="errorLog"></div>
            </div>
        </div>
    </div>
</div>
