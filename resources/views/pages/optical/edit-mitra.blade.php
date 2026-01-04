 <x-modal-form id="formEditModal" title="Edit Data ODP/ODC" action="">
     @method('PUT') <!-- Tambahkan method PUT untuk update -->
     <div class="col-12 mb-3">
         <div class="row">
             <div class="col-lg-6">
                 <div class="form-group">
                     <label for="edit-name">Nama</label>
                     <input required name="name" type="text" class="form-control" id="edit-name">
                 </div>
             </div>
             <div class="col-lg-6">
                 <div class="form-group">
                     <label for="edit-capacity">Kapasitas</label>
                     <input required name="capacity" type="number" class="form-control" id="edit-capacity">
                 </div>
             </div>
         </div>

         <div class="row">
             <div class="col-lg-6">
                 <label for="edit-name">Area</label>
                 <input required name="area_id" disabled type="text" class="form-control" id="edit-area">
             </div>
             <div class="col-lg-6">
                 <label for="type">Type</label>
                 <div class="form-group">
                     <select id="edit-type" name="type" class="form-select ">
                         <option value="ODP">ODP</option>
                         <option value="ODC">ODC</option>
                     </select>
                 </div>
             </div>
         </div>

         <input id="edit-lat" readonly name="lat" type='hidden'
             class="form-control @error('lat')is-invalid @enderror" value="{{ old('lat') }}">
         <input id="edit-lng" readonly name="lng" type='hidden'
             class="form-control @error('lng')is-invalid @enderror" value="{{ old('lng') }}">

         <div id="edit-maps"></div>

     </div>
 </x-modal-form>
