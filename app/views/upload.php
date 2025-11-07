<!-- app/views/upload.php -->
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Subir Excel/CSV y generar boletos</title>
</head>
<body>
  <h1>Sube tu archivo CSV (usuario,nombre,centro)</h1>
  <form action="/access/public/index.php?p=upload" method="POST" enctype="multipart/form-data">
  <input type="file" name="csv_file" accept=".csv" required>
  <button type="submit">Subir CSV y generar boletos</button>
</form>




  <p>Instrucciones: abre tu Excel y guarda como <strong>CSV (delimitado por comas)</strong>. Cada fila: <em>usuario,nombre,centro</em>. No uses encabezados o descártalos antes.</p>
</body>
</html>
