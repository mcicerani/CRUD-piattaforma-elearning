//Toggle form di modifica

function toggleForm(type, id) {
    const formRow = document.getElementById(`${type}-form-${id}`);
    formRow.style.display = formRow.style.display === 'none' ? 'table-row' : 'none';
}

function toggle(type) {
    const formRow = document.getElementById(`${type}`);
    formRow.style.display = formRow.style.display === 'none' ? 'block' : 'none';
}