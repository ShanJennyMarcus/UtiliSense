// C:\xampp\htdocs\utilisense\app.js

document.addEventListener('DOMContentLoaded', () => {

    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

   
    const utilitySelect = document.getElementById('calcUtilitySelect');
    const unitInput = document.getElementById('calcUnitsInput');
    const totalPreview = document.getElementById('calcTotalPreview');

    function updateCalculation() {
        if (!utilitySelect || !unitInput || !totalPreview) return;
        const selectedOption = utilitySelect.options[utilitySelect.selectedIndex];
        const rate = parseFloat(selectedOption.getAttribute('data-rate')) || 0;
        const utilName = selectedOption.getAttribute('data-name') || '';

        if (utilName === 'Internet') {
            unitInput.value = 1;
            unitInput.setAttribute('readonly', true);
        } else {
            unitInput.removeAttribute('readonly');
        }

        const units = parseFloat(unitInput.value) || 0;
        const total = rate * units;
        totalPreview.textContent = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    if (utilitySelect && unitInput) {
        utilitySelect.addEventListener('change', updateCalculation);
        unitInput.addEventListener('input', updateCalculation);
        updateCalculation();
    }

 
    const quickSearch = document.getElementById('quickTableSearch');
    if (quickSearch) {
        quickSearch.addEventListener('keyup', function () {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.searchable-table tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }
});

function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="bi bi-eye"></i>';
    }
}

function openPaymentModal(billId, utilityName, period, amountDue) {
    document.getElementById('modalBillId').value = billId;
    document.getElementById('modalUtilityName').textContent = utilityName;
    document.getElementById('modalBillingPeriod').textContent = period;
    document.getElementById('modalAmountDue').textContent = '₱' + parseFloat(amountDue).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
}