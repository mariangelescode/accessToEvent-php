// const resultDiv = document.getElementById("result");
// let scanning = true; // bandera para permitir un solo escaneo

// // creamos el escáner una sola vez
// const html5QrCode = new Html5Qrcode("reader");

// function onScanSuccess(decodedText) {
//   // si ya se procesó un código, no hacer nada
//   if (!scanning) return;
//   scanning = false;

//   // detener el escáner
//   html5QrCode.stop().then(() => {
//     console.log("Escaneo detenido.");
//   }).catch(err => {
//     console.error("Error al detener el escáner", err);
//   });

//   // procesar el código
//   fetch("/access/public/index.php?p=validate_check", {
//     method: "POST",
//     headers: { "Content-Type": "application/x-www-form-urlencoded" },
//     body: "qr=" + encodeURIComponent(decodedText)
//   })
//   .then(res => res.json())
//   .then(data => {
//     if (data.status === "success") {
//       alert('✅ Se registró con éxito');
//     } else if (data.status === "exists") {
//       alert('⚠️ Ya se registró');
//     } else {
//       alert('❌ ' + data.message);
//     }

//     // opcional: volver a activar el escaneo después de unos segundos
//     setTimeout(() => {
//       scanning = true;
//       html5QrCode.start(
//         { facingMode: "environment" },
//         { fps: 10, qrbox: 250 },
//         onScanSuccess
//       );
//     }, 1000); // reinicia en 3 segundos
//   })
//   .catch(() => {
//     alert("Error en la conexión");
//   });
// }

// // iniciar el escáner
// html5QrCode.start(
//   { facingMode: "environment" },
//   { fps: 10, qrbox: 250 },
//   onScanSuccess
// );

let qrScanner; // instancia global

function onScanSuccess(qrMessage) {
    // Detener escaneo inmediatamente
    qrScanner.clear().then(() => {
        processQR(qrMessage);
    });
}

document.addEventListener("DOMContentLoaded", () => {
    qrScanner = new Html5QrcodeScanner(
        "reader",
        { fps: 10, qrbox: 250 },
        false
    );
    qrScanner.render(onScanSuccess);
});

function processQR(qr) {

    fetch("../../public/index.php?route=validate/check", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "qr=" + encodeURIComponent(qr)
    })
    .then(res => res.json())
    .then(data => {

        // Mostrar modal según caso
        if (data.status === "error") {
            return Swal.fire({
                icon: "error",
                title: "Error",
                text: data.message
            }).then(() => restartScanner());
        }

        if (data.status === "exists") {
            return Swal.fire({
                icon: "warning",
                title: "Ya registrado",
                html: `
                    <b>${data.data.name}</b><br>
                    Centro: ${data.data.center}
                `
            }).then(() => restartScanner());
        }

        if (data.status === "success") {
            return Swal.fire({
                icon: "success",
                title: "Registrado",
                html: `
                    <b>${data.data.name}</b><br>
                    Centro: ${data.data.center}
                `,
                confirmButtonText: "OK"
            }).then(() => restartScanner());
        }

    })
    .catch(err => {
        console.error(err);
        Swal.fire({
            icon: "error",
            title: "Error",
            text: "Hubo un problema al validar el QR."
        }).then(() => restartScanner());
    });
}

// Reinicia el lector sin duplicados
function restartScanner() {
    document.getElementById("reader").innerHTML = "";
    qrScanner.render(onScanSuccess);
}
