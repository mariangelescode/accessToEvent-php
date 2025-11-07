<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Validar QR</title>
<script src="https://unpkg.com/html5-qrcode"></script>
<style>
body { font-family: Arial; text-align: center; background: #f8f9fa; }
#reader { width: 300px; margin: 20px auto; }
#result { margin-top: 20px; font-size: 18px; }
.success { color: green; }
.error { color: red; }
.exists { color: orange; }
</style>
</head>
<body>

<h2>Escanea el código QR</h2>
<div id="reader"></div>
<div id="result"></div>

<script>
const resultDiv = document.getElementById("result");

function onScanSuccess(decodedText) {
  fetch("/access/public/index.php?p=validate_check", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "qr=" + encodeURIComponent(decodedText)
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === "success") {
      resultDiv.innerHTML = `
        <div class='success'>
          <p><strong>${data.message}</strong></p>
          <p>Usuario: ${data.data.user}</p>
          <p>Nombre: ${data.data.name}</p>
          <p>Centro: ${data.data.center}</p>
        </div>`;
    } else if (data.status === "exists") {
      resultDiv.innerHTML = `
        <div class='exists'>
          <p><strong>${data.message}</strong></p>
          <p>Usuario: ${data.data.user}</p>
          <p>Nombre: ${data.data.name}</p>
          <p>Centro: ${data.data.center}</p>
        </div>`;
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
</script>
</body>
</html>
