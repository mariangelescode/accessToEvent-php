<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Validar QR</title>
<link rel="stylesheet" href="../../public/css/styles.css">
</head>
<body>
  
  <p class="title__validate">Escanea el código QR</p>
  
  <p class="counter">
      Registrados: <b><?php echo $totalRegistrados ?? 0; ?></b>
  </p>
  <div id="reader"></div>
  
  <script src="https://unpkg.com/html5-qrcode"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../../public/script/script.js"></script>

</body>
</html>
