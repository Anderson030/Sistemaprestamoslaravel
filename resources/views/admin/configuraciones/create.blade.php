@extends('adminlte::page')

@section('content_header')
    <h1><b>Configuraciones/Registro de una nueva configuración </b></h1>
    <hr>
@stop

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Lleno los datos del formulario</h3>
                    <!-- /.card-tools -->
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <form action="{{ route('admin.configuraciones.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                       <div class="row">
                           <div class="col-md-9">
                               <div class="row">
                                   <div class="col-md-3">
                                       <div class="form-group">
                                           <label for="">Nombre de la institución</label><b> (*)</b>
                                           <div class="input-group mb-3">
                                               <div class="input-group-prepend">
                                                   <span class="input-group-text"><i class="fas fa-home"></i></span>
                                               </div>
                                               <input type="text" class="form-control" name="nombre" placeholder="Escriba aquí..." required>
                                           </div>
                                           @error('nombre')
                                           <small style="color: red">{{$message}}</small>
                                           @enderror
                                       </div>
                                   </div>
                                   <div class="col-md-4">
                                       <div class="form-group">
                                           <label for="">Descripción de la institución</label><b> (*)</b>
                                           <div class="input-group mb-3">
                                               <div class="input-group-prepend">
                                                   <span class="input-group-text"><i class="fas fa-university"></i></span>
                                               </div>
                                               <input type="text" name="descripcion" class="form-control" placeholder="Escriba aquí..." required>
                                           </div>
                                           @error('descripcion')
                                           <small style="color: red">{{$message}}</small>
                                           @enderror
                                       </div>
                                   </div>
                                   <div class="col-md-5">
                                       <div class="form-group">
                                           <label for="">Dirección</label> <b> (*)</b>
                                           <div class="input-group mb-3">
                                               <div class="input-group-prepend">
                                                   <span class="input-group-text"><i class="fas fa-map-marked"></i></span>
                                               </div>
                                               <input type="text" name="direccion" class="form-control" placeholder="Escriba aquí..." required>
                                           </div>
                                           @error('direccion')
                                           <small style="color: red">{{$message}}</small>
                                           @enderror
                                       </div>
                                   </div>
                               </div>
                               <div class="row">
                                   <div class="col-md-3">
                                       <div class="form-group">
                                           <label for="">Teléfono</label> <b> (*)</b>
                                           <div class="input-group mb-3">
                                               <div class="input-group-prepend">
                                                   <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                               </div>
                                               <input type="text" name="telefono" class="form-control" placeholder="Escriba aquí..." required>
                                           </div>
                                           @error('telefono')
                                           <small style="color: red">{{$message}}</small>
                                           @enderror
                                       </div>
                                   </div>
                                   <div class="col-md-3">
                                       <div class="form-group">
                                           <label for="">Email</label> <b> (*)</b>
                                           <div class="input-group mb-3">
                                               <div class="input-group-prepend">
                                                   <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                               </div>
                                               <input type="email" name="email" class="form-control" placeholder="Escriba aquí..." required>
                                           </div>
                                           @error('email')
                                           <small style="color: red">{{$message}}</small>
                                           @enderror
                                       </div>
                                   </div>
                                   <div class="col-md-3">
                                       <div class="form-group">
                                           <label for="">Página web</label>
                                           <div class="input-group mb-3">
                                               <div class="input-group-prepend">
                                                   <span class="input-group-text"><i class="fas fa-pager"></i></span>
                                               </div>
                                               <input type="text" name="web" class="form-control" placeholder="Escriba aquí...">
                                           </div>
                                       </div>
                                   </div>
                                   <div class="col-md-3">
                                       <div class="form-group">
                                           <label for="">Moneda</label> <b> (*)</b>
                                           <div class="input-group mb-3">
                                               <div class="input-group-prepend">
                                                   <span class="input-group-text"><i class="fas fa-coins"></i></span>
                                               </div>
                                               <select name="moneda" id="" class="form-control">
                                                   <option value="usd">Dólar Estadounidense (USD)</option>
                                                   <option value="eur">Euro (EUR)</option>
                                                   <option value="cop">Peso Colombiano (COP)</option>
                                               </select>
                                           </div>
                                       </div>
                                   </div>
                               </div>
                           </div>
                           <div class="col-md-3">
                               <div class="form-group">
                                   <label for="logo">Logo</label> <b> (*)</b>
                                   <input type="file" id="file" name="logo" accept=".jpg, .jpeg, .png" class="form-control" required>
                                   @error('logo')
                                   <small style="color: red;">{{$message}}</small>
                                   @enderror
                                   <br>
                                   <center><output id="list"></output></center>
                                   <script>
                                       function archivo(evt) {
                                           var files = evt.target.files; // FileList object
                                           // Obtenemos la imagen del campo "file".
                                           for (var i = 0, f; f = files[i]; i++) {
                                               //Solo admitimos imágenes.
                                               if (!f.type.match('image.*')) {
                                                   continue;
                                               }
                                               var reader = new FileReader();
                                               reader.onload = (function (theFile) {
                                                   return function (e) {
                                                       // Insertamos la imagen
                                                       document.getElementById("list").innerHTML = ['<img class="thumb thumbnail" src="',e.target.result, '" width="70%" title="', escape(theFile.name), '"/>'].join('');
                                                   };
                                               })(f);
                                               reader.readAsDataURL(f);
                                           }
                                       }
                                       document.getElementById('file').addEventListener('change', archivo, false);
                                   </script>
                               </div>
                           </div>
                       </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <a href="{{url('/admin/configuraciones')}}" class="btn btn-secondary">Cancelar</a>
                                    <button type="submit" class="btn btn-primary">Registrar</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
    </div>
@stop

@section('css')

@stop

@section('js')

@stop
