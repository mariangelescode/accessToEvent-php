// let qrScanner;
// let isProcessing = false;

// function onScanSuccess(qrMessage) {
//     if (isProcessing) return;
//     isProcessing = true;

//     qrScanner.clear().then(() => {
//         processQR(qrMessage);
//     });
// }

// document.addEventListener("DOMContentLoaded", () => {
//     qrScanner = new Html5QrcodeScanner(
//         "reader",
//         { fps: 10, qrbox: 250 },
//         false
//     );
//     qrScanner.render(onScanSuccess);
// });

// function processQR(qr) {

//     fetch("https://developermpercastre.com/access/public/index.php?p=validate_check", {
//         method: "POST",
//         headers: { "Content-Type": "application/x-www-form-urlencoded" },
//         body: "qr=" + encodeURIComponent(qr)
//     })
//     .then(res => res.json())
//     .then(data => {

//         if (data.status === "error") {
//             return Swal.fire({
//                 icon: "error",
//                 title: "Error",
//                 text: data.message
//             }).then(() => {
//                 isProcessing = false;
//                 restartScanner();
//             });
//         }

//         if (data.status === "exists") {
//             return Swal.fire({
//                 icon: "warning",
//                 title: "Ya registrado",
//                 html: `<b>${data.data.name}</b><br>Centro: ${data.data.center}`
//             }).then(() => {
//                 isProcessing = false;
//                 restartScanner();
//             });
//         }

//         if (data.status === "success") {
//             return Swal.fire({
//                 icon: "success",
//                 title: "Registrado",
//                 html: `<b>${data.data.name}</b><br>Centro: ${data.data.center}`
//             }).then(() => {
//                 isProcessing = false;
//                 restartScanner();
//             });
//         }

//     })
//     .catch(err => {
//         console.error(err);
//         Swal.fire({
//             icon: "error",
//             title: "Error",
//             text: "Hubo un problema al validar el QR."
//         }).then(() => {
//             isProcessing = false;
//             restartScanner();
//         });
//     });
// }

// function restartScanner() {
//     setTimeout(() => {
//         qrScanner.clear().then(() => {
//             qrScanner.render(onScanSuccess);
//         });
//     }, 1000);
// }


let qrScanner;
let isProcessing = false;

function onScanSuccess(qrMessage) {
    if (isProcessing) return;
    isProcessing = true;

    // Ocultar lector mientras sale el mensaje
    document.getElementById("reader").style.display = "none";

    qrScanner.clear().then(() => {
        processQR(qrMessage);
    });
}

document.addEventListener("DOMContentLoaded", () => {

    // HACER EL LECTOR EXTRA GRANDE
    const width = Math.min(window.innerWidth * 0.9, 400); 
    const height = width;

    qrScanner = new Html5QrcodeScanner(
        "reader",
        { 
            fps: 10,
            qrbox: { width, height }   // <-- AQUÍ SE AGRANDA
        },
        false
    );

    qrScanner.render(onScanSuccess);
});

function processQR(qr) {

    fetch("https://developermpercastre.com/access/public/index.php?p=validate_check", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "qr=" + encodeURIComponent(qr)
    })
    .then(res => res.json())
    .then(data => {

        let icon = "info"; 
        let title = "Aviso";
        let html = "";

        if (data.status === "error") {
            icon = "error";
            title = "<span style='font-size:2em'>Error</span>";
            html = data.message;
        }

        if (data.status === "exists") {
            icon = "warning";
            title = "<span style='font-size:2em'>Ya registrado</span>";
            html = `<b><span style='font-size:1.7em'>${data.data.name}</span></b><br><span style='font-size:1.7em'>Centro: ${data.data.center}</span>`;
        }

        if (data.status === "success") {
            icon = "success";
            title = "<span style='font-size:2em'>Registrado</span>";
            html = `<b><span style='font-size:1.7em'>${data.data.name}</span></b><br><span style='font-size:1.7em'>Centro: ${data.data.center}</span>`;
        }

        return Swal.fire({
            icon,
            title,
            html,
            width: '50rem',
            padding: '2.5rem',
            confirmButtonText: "Volver a escanear",
            confirmButtonColor: "#74b0ff",
            didOpen: () => {
                const btn = document.querySelector('.swal2-confirm');
                btn.style.fontSize = '2em';
                btn.style.padding = '20px 35px';
            }
        }).then(() => restartScanner());
    })
    .catch(err => {
        console.error(err);

        Swal.fire({
            icon: "error",
            title: "<span style='font-size:2em'>Error</span>",
            html: "<span style='font-size:2em'>Hubo un problema al validar el QR.</span>",
            width: '50rem',
            padding: '2.5rem',
            confirmButtonText: "Reintentar",
            confirmButtonColor: "#74b0ff",
            customClass: {
                confirmButton: 'big-btn'
            }
        }).then(() => restartScanner());

    });
}



function restartScanner() {
    isProcessing = false;

    document.getElementById("reader").style.display = "block";

    setTimeout(() => {
        qrScanner.clear().then(() => {
            qrScanner.render(onScanSuccess);
        });
    }, 500);
}
