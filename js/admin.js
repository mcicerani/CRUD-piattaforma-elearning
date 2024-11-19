//Toggle form di modifica

function toggleForm(type, id) {
    const formRow = document.getElementById(`${type}-form-${id}`);
    formRow.style.display = formRow.style.display === 'none' ? 'table-row' : 'none';
}