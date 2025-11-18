<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Validar QR</title>
<link rel="stylesheet" href="../../public/css/styles.css">
<script src="https://unpkg.com/html5-qrcode"></script>
</head>
<body>

  <p class="title__validate">Escanea el código QR</p>
  <div id="reader"></div>
  <div id="result"></div>

<script>
const resultDiv = document.getElementById("result");
let scanning = true;

const html5QrCode = new Html5Qrcode("reader");


function onScanSuccess(decodedText) {

  // si ya se procesó un código, no hacer nada
  if (!scanning) return;
  scanning = false;

  // detener el escáner
  html5QrCode.stop().then(() => {
    console.log("Escaneo detenido.");
  }).catch(err => {
    console.error("Error al detener el escáner", err);
  });


  fetch("/access/public/index.php?p=validate_check", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "qr=" + encodeURIComponent(decodedText)
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === "success") {
      alert('Se registró con éxito')
      // resultDiv.innerHTML = `
      //   <div id="infoCode" class='success'>
      //     <p>Se registró con éxito</p>
      //     <p><strong>${data.message}</strong></p>
      //     <p>Usuario: ${data.data.user}</p>
      //     <p>Nombre: ${data.data.name}</p>
      //     <p>Centro: ${data.data.center}</p>
      //     <button onclick="hiddeInfo">Aceptar</button>
      //   </div>`;
    } else if (data.status === "exists") {
      alert('Ya se registró')
      
      // resultDiv.innerHTML = `
      //   <div class='exists'>
      //     <p><strong>${data.message}</strong></p>
      //     <p>Usuario: ${data.data.user}</p>
      //     <p>Nombre: ${data.data.name}</p>
      //     <p>Centro: ${data.data.center}</p>
      //   </div>`;
    } else {
      resultDiv.innerHTML = `<div class='error'>${data.message}</div>`;
    }

    
  })
  .catch(() => {
    resultDiv.innerHTML = "<div class='error'>Error en la conexión</div>";
  });
}

new Html5Qrcode("reader").start(
  { facingMode: "environment" },
  { fps: 10, qrbox: 250 },
  onScanSuccess
);

const hiddeInfo = () => {
  document.getElementById('infoCode').style.display = 'none';
}

</script>
</body>
</html>
