// Attendi che il DOM sia completamente caricato
document.addEventListener("DOMContentLoaded", function() {

    // Contenitore del form
    const formContainer = document.getElementById("form-container");

    // Form di Login
    const loginForm = `
        <form id="login-form" method="POST">
            <h2>Login</h2>
            <input type="email" name="email" placeholder="Email" required/>
            <input type="password" name="password" placeholder="Password" required/>
            <button type="submit" name="login">Accedi</button>
            <p>Non hai un account? <a href="#" id="show-register">Registrati qui</a></p>
        </form>`;

    // Form di Registrazione
    const registerForm = `
        <form id="register-form" method="POST">
            <h2>Registrazione</h2>
            <input type="text" name="nome" placeholder="Nome" required/>
            <input type="email" name="email" placeholder="Email" required/>
            <input type="password" name="password" placeholder="Password" required/>
            <select name="role" required>
                <option value="studente">Studente</option>
                <option value="professore">Professore</option>
            </select>
            <button type="submit" name="register">Registrati</button>
            <p>Hai già un account? <a href="#" id="show-login">Accedi qui</a></p>
        </form>`;

    // Funzione per mostrare il form di login
    function showLoginForm() {
        formContainer.innerHTML = loginForm;
        document.getElementById("show-register").addEventListener('click', function(event) {
            event.preventDefault(); // Previene il comportamento di default del link
            showRegisterForm();
        });
    }

    // Funzione per mostrare il form di registrazione
    function showRegisterForm() {
        formContainer.innerHTML = registerForm;
        document.getElementById("show-login").addEventListener('click', function(event) {
            event.preventDefault(); // Previene il comportamento di default del link
            showLoginForm();
        });
    }

    // Mostra il form di login di default
    showLoginForm();
});