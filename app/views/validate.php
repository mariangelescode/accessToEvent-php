<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Validar QR</title>
<!-- <link rel="stylesheet" href="../../public/css/styles.css"> -->
 <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

 <link rel="stylesheet" href="/access/public/css/styles.css?v=<?=time()?>">

</head>
<body>
  
  <!-- <p class="title__validate">Escanea el código QR</p>
  
  <p class="counter">
      Registrados: <b><?php echo $totalRegistrados ?? 0; ?></b>
  </p>
  <div id="reader"></div> -->
  
<div id="main-container">
    <h1>Validación QR</h1>

    <div id="reader"></div>

    <div id="result"></div>

    <div id="message" class="message-box" style="display:none;"></div>

    <button id="restartBtn" style="display:none;">Volver a escanear</button>
</div>
  
  <script src="https://unpkg.com/html5-qrcode"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- <script src="../../public/script/script.js"></script> -->
   <!-- <script src="/access/public/script/script.js"></script> -->
    <script src="/access/public/script/script.js?v=<?=time()?>"></script>



</body>
</html>
