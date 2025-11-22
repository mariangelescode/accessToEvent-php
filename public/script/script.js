let qrScanner;
let isProcessing = false;

function onScanSuccess(qrMessage) {
    if (isProcessing) return;
    isProcessing = true;

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

    fetch("./index.php?p=validate_check", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "qr=" + encodeURIComponent(qr)
    })
    .then(res => res.json())
    .then(data => {

        if (data.status === "error") {
            return Swal.fire({
                icon: "error",
                title: "Error",
                text: data.message
            }).then(() => {
                isProcessing = false;
                restartScanner();
            });
        }

        if (data.status === "exists") {
            return Swal.fire({
                icon: "warning",
                title: "Ya registrado",
                html: `<b>${data.data.name}</b><br>Centro: ${data.data.center}`
            }).then(() => {
                isProcessing = false;
                restartScanner();
            });
        }

        if (data.status === "success") {
            return Swal.fire({
                icon: "success",
                title: "Registrado",
                html: `<b>${data.data.name}</b><br>Centro: ${data.data.center}`
            }).then(() => {
                isProcessing = false;
                restartScanner();
            });
        }

    })
    .catch(err => {
        console.error(err);
        Swal.fire({
            icon: "error",
            title: "Error",
            text: "Hubo un problema al validar el QR."
        }).then(() => {
            isProcessing = false;
            restartScanner();
        });
    });
}

function restartScanner() {
    setTimeout(() => {
        qrScanner.clear().then(() => {
            qrScanner.render(onScanSuccess);
        });
    }, 1000);
}
