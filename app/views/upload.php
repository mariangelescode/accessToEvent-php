<!-- app/views/upload.php -->
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Subir Excel/CSV y generar boletossss</title>
<link rel="stylesheet" href="../../public/css/styles.css">
</head>
<body>
  <div class="center">
    <h1>Sube tu archivo CSV</h1>
  </div>
  <form action="/access/public/index.php?p=upload" method="POST" enctype="multipart/form-data">
    <input id="file" type="file" name="csv_file" accept=".csv" hidden>
    <label for="file" class="pointer">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
      </svg>
    </label>
    <div id="selected"></div>
    <button class="btn-primary" type="submit">Generar boletos</button>
  </form>
  <p class="instructions">Instrucciones: abre tu Excel y guarda como <strong>CSV (delimitado por comas)</strong>. Cada fila: <em>usuario,nombre,centro</em>. No uses encabezados o descártalos antes.</p>

  
  <script>
    const input = document.getElementById('file');
    const selected = document.getElementById('selected');

    input.addEventListener('change', (e) => {
      const files = e.target.files;
      if(!files || files.length === 0){
        selected.textContent = 'No hay archivos seleccionados.';
        return;
      }
      // Si multiple: mostrar lista resumida
      if(files.length === 1){
        selected.textContent = files[0].name + ' — ' + Math.round(files[0].size / 1024) + ' KB';
      } else {
        selected.textContent = files.length + ' archivos seleccionados';
      }
    });


  </script>
</body>
</html>
