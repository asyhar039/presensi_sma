"use strict";

/**
 * Script JavaScript untuk Sistem Absensi SMA
 */
// Fungsi untuk confirm delete
function confirmDelete() {
  var message = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : 'Apakah Anda yakin ingin menghapus data ini?';
  return confirm(message);
} // Fungsi untuk format tanggal


function formatDate(date) {
  var options = {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    language: 'id-ID'
  };
  return new Date(date).toLocaleDateString('id-ID', options);
} // Fungsi untuk highlight errors


function highlightErrors(errors) {
  errors.forEach(function (error) {
    var element = document.querySelector("[name=\"".concat(error, "\"]"));

    if (element) {
      element.classList.add('is-invalid');
    }
  });
} // Fungsi untuk clear errors


function clearErrors() {
  document.querySelectorAll('.is-invalid').forEach(function (element) {
    element.classList.remove('is-invalid');
  });
} // Fungsi untuk print


function printData() {
  window.print();
} // Fungsi untuk export ke CSV


function exportToCSV() {
  var filename = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : 'data.csv';
  var csv = [];
  var rows = document.querySelectorAll('table tr');
  rows.forEach(function (row) {
    var cols = row.querySelectorAll('td, th');
    var csvRow = [];
    cols.forEach(function (col) {
      csvRow.push('"' + col.innerText + '"');
    });
    csv.push(csvRow.join(','));
  });
  downloadCSV(csv.join('\n'), filename);
} // Fungsi untuk download CSV


function downloadCSV(csv, filename) {
  var csvFile = new Blob([csv], {
    type: 'text/csv'
  });
  var downloadLink = document.createElement('a');
  downloadLink.href = URL.createObjectURL(csvFile);
  downloadLink.download = filename;
  document.body.appendChild(downloadLink);
  downloadLink.click();
  document.body.removeChild(downloadLink);
} // Fungsi untuk select/unselect all


function selectAll(source) {
  var checkboxes = document.querySelectorAll('input[type="checkbox"].item-checkbox');
  checkboxes.forEach(function (checkbox) {
    checkbox.checked = source.checked;
  });
} // Fungsi untuk toggle detail


function toggleDetail(id) {
  var detail = document.getElementById('detail-' + id);

  if (detail) {
    detail.style.display = detail.style.display === 'none' ? 'block' : 'none';
  }
} // Initialize Bootstrap tooltips


document.addEventListener('DOMContentLoaded', function () {
  // Tooltips
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  }); // Popovers

  var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
  popoverTriggerList.map(function (popoverTriggerEl) {
    return new bootstrap.Popover(popoverTriggerEl);
  });
}); // Auto-hide alerts

setTimeout(function () {
  var alerts = document.querySelectorAll('.alert');
  alerts.forEach(function (alert) {
    var bsAlert = new bootstrap.Alert(alert);
    setTimeout(function () {
      bsAlert.close();
    }, 5000);
  });
}, 100);
//# sourceMappingURL=script.dev.js.map
