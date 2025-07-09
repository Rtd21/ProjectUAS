// Menjalankan semua skrip setelah seluruh halaman HTML dimuat
document.addEventListener('DOMContentLoaded', function() {

    /**
     * FUNGSI 1: Menghilangkan pesan notifikasi sukses secara otomatis
     */
    const successAlert = document.querySelector('.message.success');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.transition = 'opacity 0.5s ease';
            successAlert.style.opacity = '0';
            setTimeout(() => successAlert.remove(), 500); 
        }, 4000);
    }

    /**
     * FUNGSI 2: Validasi konfirmasi password di sisi klien
     */
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(event) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirm').value;
            let errorDiv = document.getElementById('passwordError');

            if (password !== confirmPassword) {
                event.preventDefault(); 
                if (!errorDiv) {
                    const newErrorDiv = document.createElement('div');
                    newErrorDiv.className = 'message error';
                    newErrorDiv.id = 'passwordError';
                    newErrorDiv.textContent = 'Konfirmasi password tidak cocok!';
                    registerForm.insertBefore(newErrorDiv, registerForm.firstChild);
                } else {
                    errorDiv.textContent = 'Konfirmasi password tidak cocok!';
                }
            } else {
                if (errorDiv) {
                    errorDiv.remove();
                }
            }
        });
    }

    /**
     * FUNGSI 3: Logika untuk menampilkan modal berkas di halaman admin
     */
    const modal = document.getElementById("berkasModal");
    const span = document.getElementsByClassName("close")[0];

    if (modal && span) {
        span.onclick = function() {
            modal.style.display = "none";
        }
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        document.querySelectorAll('.lihat-berkas-btn').forEach(button => {
            button.addEventListener('click', function() {
                const berkasUrls = this.getAttribute('data-berkas');
                const urlArray = berkasUrls.split(',');
                const berkasListDiv = document.getElementById('berkasList');
                berkasListDiv.innerHTML = '';

                if (berkasUrls && urlArray.length > 0 && urlArray[0] !== '') {
                    urlArray.forEach((url, index) => {
                        if (url.trim() !== '') {
                            const a = document.createElement('a');
                            a.href = url.trim();
                            a.textContent = 'Lihat Berkas ' + (index + 1);
                            a.target = '_blank';
                            berkasListDiv.appendChild(a);
                        }
                    });
                } else {
                    berkasListDiv.innerHTML = '<p>Tidak ada berkas yang bisa ditampilkan.</p>';
                }
                modal.style.display = "block";
            });
        });
    }

    /**
     * FUNGSI 4: Inisialisasi Grafik di Dashboard Admin
     */
    const kategoriCanvas = document.getElementById('kategoriChart');
    if (kategoriCanvas) {
        const kategoriLabels = JSON.parse(kategoriCanvas.dataset.labels);
        const kategoriValues = JSON.parse(kategoriCanvas.dataset.values);

        const ctxKategori = kategoriCanvas.getContext('2d');
        new Chart(ctxKategori, {
            type: 'pie',
            data: {
                labels: kategoriLabels,
                datasets: [{
                    label: 'Jumlah Pendaftar',
                    data: kategoriValues,
                    backgroundColor: ['rgba(0, 123, 255, 0.8)', 'rgba(23, 162, 184, 0.8)'],
                    borderColor: ['rgba(0, 123, 255, 1)', 'rgba(23, 162, 184, 1)'],
                    borderWidth: 1
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'top' } } }
        });
    }

    const statusCanvas = document.getElementById('statusChart');
    if (statusCanvas) {
        const statusLabels = JSON.parse(statusCanvas.dataset.labels);
        const statusValues = JSON.parse(statusCanvas.dataset.values);

        const ctxStatus = statusCanvas.getContext('2d');
        new Chart(ctxStatus, {
            type: 'bar',
            data: {
                labels: statusLabels,
                datasets: [{
                    label: 'Jumlah Pendaftar',
                    data: statusValues,
                    backgroundColor: 'rgba(0, 123, 255, 0.6)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                plugins: { legend: { display: false } }
            }
        });
    }

    /**
     * FUNGSI 5: Fungsionalitas "Pilih Semua" Checkbox
     */
    const pilihSemuaCheckbox = document.getElementById('pilihSemua');
    if (pilihSemuaCheckbox) {
        pilihSemuaCheckbox.addEventListener('click', function(event) {
            document.querySelectorAll('.pilih-satu').forEach(function(checkbox) {
                checkbox.checked = event.target.checked;
            });
        });
    }

});