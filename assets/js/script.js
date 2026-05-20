/**
 * Script JavaScript untuk Sistem Absensi SMA
 */

// Fungsi untuk confirm delete
function confirmDelete(message = 'Apakah Anda yakin ingin menghapus data ini?') {
    return confirm(message);
}

// Fungsi untuk format tanggal
function formatDate(date) {
    const options = { year: 'numeric', month: 'long', day: 'numeric', language: 'id-ID' };
    return new Date(date).toLocaleDateString('id-ID', options);
}

// Fungsi untuk highlight errors
function highlightErrors(errors) {
    errors.forEach(function(error) {
        const element = document.querySelector(`[name="${error}"]`);
        if (element) {
            element.classList.add('is-invalid');
        }
    });
}

// Fungsi untuk clear errors
function clearErrors() {
    document.querySelectorAll('.is-invalid').forEach(function(element) {
        element.classList.remove('is-invalid');
    });
}

// Fungsi untuk print
function printData() {
    window.print();
}

// Fungsi untuk export ke CSV
function exportToCSV(filename = 'data.csv') {
    const csv = [];
    const rows = document.querySelectorAll('table tr');
    
    rows.forEach(function(row) {
        const cols = row.querySelectorAll('td, th');
        const csvRow = [];
        
        cols.forEach(function(col) {
            csvRow.push('"' + col.innerText + '"');
        });
        
        csv.push(csvRow.join(','));
    });
    
    downloadCSV(csv.join('\n'), filename);
}

// Fungsi untuk download CSV
function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    downloadLink.href = URL.createObjectURL(csvFile);
    downloadLink.download = filename;
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Fungsi untuk select/unselect all
function selectAll(source) {
    const checkboxes = document.querySelectorAll('input[type="checkbox"].item-checkbox');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = source.checked;
    });
}

// Fungsi untuk toggle detail
function toggleDetail(id) {
    const detail = document.getElementById('detail-' + id);
    if (detail) {
        detail.style.display = detail.style.display === 'none' ? 'block' : 'none';
    }
}

// Initialize Bootstrap tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});

// Auto-hide alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        setTimeout(function() {
            bsAlert.close();
        }, 5000);
    });
}, 100);
