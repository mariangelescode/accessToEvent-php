const resultDiv = document.getElementById("result");
let scanning = true; // bandera para permitir un solo escaneo

// creamos el escáner una sola vez
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

  // procesar el código
  fetch("/access/public/index.php?p=validate_check", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "qr=" + encodeURIComponent(decodedText)
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === "success") {
      alert('✅ Se registró con éxito');
    } else if (data.status === "exists") {
      alert('⚠️ Ya se registró');
    } else {
      alert('❌ ' + data.message);
    }

    // opcional: volver a activar el escaneo después de unos segundos
    setTimeout(() => {
      scanning = true;
      html5QrCode.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: 250 },
        onScanSuccess
      );
    }, 1000); // reinicia en 3 segundos
  })
  .catch(() => {
    alert("Error en la conexión");
  });
}

// iniciar el escáner
html5QrCode.start(
  { facingMode: "environment" },
  { fps: 10, qrbox: 250 },
  onScanSuccess
);