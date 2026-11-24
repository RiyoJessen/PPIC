document.addEventListener('DOMContentLoaded', () => {
    // Sidebar toggle
    const menuToggle = document.getElementById('menu-toggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('wrapper').classList.toggle('toggled');
        });
    }

    // Interactive table search
    const searchInputs = document.querySelectorAll('.table-search');
    searchInputs.forEach(input => {
        const targetTable = document.querySelector(input.dataset.target);
        if (!targetTable) return;

        input.addEventListener('keyup', () => {
            const query = input.value.toLowerCase();
            const rows = targetTable.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    });

    // Auto-reset notification badge when bell button is clicked or dropdown is opened
    const notifBellButton = document.getElementById('notifBellButton');
    const notifDropdownContainer = document.getElementById('notifDropdownContainer');
    let notifMarkedRead = false;

    function resetNotificationCounter() {
        const badge = document.getElementById('notifBadgeCounter');
        const hasUnread = !!badge || document.querySelectorAll('.notif-unread-dot').length > 0;
        if (notifMarkedRead || !hasUnread) {
            return;
        }
        notifMarkedRead = true;

        if (badge) {
            badge.style.setProperty('display', 'none', 'important');
        }
        const markReadBtn = document.getElementById('notifMarkReadBtn');
        if (markReadBtn) {
            markReadBtn.style.display = 'none';
        }
        const allReadBadge = document.getElementById('notifAllReadBadge');
        if (allReadBadge) {
            allReadBadge.style.display = 'inline-block';
        }
        document.querySelectorAll('.notif-unread-dot').forEach(el => el.remove());
        document.querySelectorAll('.notif-unread-item').forEach(el => {
            el.classList.remove('bg-primary-subtle', 'bg-opacity-10');
        });

        // Kirim request ke backend dengan keepalive agar tetap selesai walau user langsung pindah halaman/divisi
        fetch('/notifications/mark-read-ajax', {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).then(res => {
            if (!res.ok) {
                notifMarkedRead = false;
            }
        }).catch(() => {
            fetch('/notifications/mark-read-ajax', {
                credentials: 'same-origin',
                keepalive: true
            }).catch(() => {
                notifMarkedRead = false;
            });
        });
    }

    if (notifBellButton) {
        notifBellButton.addEventListener('click', resetNotificationCounter);
    }
    if (notifDropdownContainer) {
        notifDropdownContainer.addEventListener('show.bs.dropdown', resetNotificationCounter);
    }
});

// Run AI analysis on demand
function runAiAnalysis(materialId, buttonEl) {
    if (!buttonEl) return;
    const originalText = buttonEl.innerHTML;
    buttonEl.disabled = true;
    buttonEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menganalisis dengan AI...';

    fetch(`/api/ai/analyze?material_id=${materialId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.analysis) {
                const modal = new bootstrap.Modal(document.getElementById('aiAnalysisModal'));
                document.getElementById('modalMaterialName').textContent = data.analysis.material.name;
                document.getElementById('modalRiskBadge').className = 'badge ' + (data.analysis.material.stock_status === 'Kritis' ? 'bg-danger' : (data.analysis.material.stock_status === 'Waspada' ? 'bg-warning text-dark' : 'bg-success'));
                document.getElementById('modalRiskBadge').textContent = data.analysis.material.stock_status;
                document.getElementById('modalRecommendedQty').textContent = `${data.analysis.final_recommended_qty.toLocaleString('id-ID')} ${data.analysis.material.unit}`;
                document.getElementById('modalAiNarrative').innerHTML = data.analysis.narrative.replace(/\n\n/g, '<br><br>').replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                document.getElementById('modalAiSource').textContent = data.analysis.source;
                
                modal.show();
            } else {
                alert('Gagal mengambil analisis AI: ' + (data.error || 'Terjadi kesalahan sistem.'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Koneksi terputus saat menghubungi engine AI.');
        })
        .finally(() => {
            buttonEl.disabled = false;
            buttonEl.innerHTML = originalText;
        });
}
